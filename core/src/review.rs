//! The review shown before an AUR package is installed: everything that would come with it (official
//! repository packages and other AUR packages, build-only ones marked), what each piece is in plain
//! words, and the warning signs the AUR itself reports. It never says a package is safe: the best
//! answer is "no warning signs found".

use std::collections::{HashMap, HashSet};
use std::time::{Duration, SystemTime, UNIX_EPOCH};

use serde::Serialize;

use crate::aur::{info_many, RpcItem};
use crate::knowledge::Knowledge;
use crate::pacman::parse_info_blocks;
use crate::sys;

/// Most packages listed, and how many dependency levels are followed.
const MAX_ENTRIES: usize = 40;
const MAX_DEPTH: usize = 4;
const CACHE_SECS: i64 = 6 * 3600;
const DAY: i64 = 86_400;

/// A warning sign. `code` is translated by the interface; `level` is 0 (a note), 1 (look closer) or 2 (serious).
#[derive(Debug, Clone, Serialize, PartialEq, Eq)]
pub struct Signal {
    pub code: &'static str,
    pub level: u8,
}

#[derive(Debug, Clone, Serialize)]
pub struct Entry {
    pub name: String,
    pub version: String,
    /// `aur`, `official`, or `unknown` (a dependency that no repository and no AUR package answered for).
    pub source: &'static str,
    /// Needed only while building; yay or paru removes it afterwards.
    pub build_only: bool,
    pub target: bool,
    /// What the package says about itself.
    pub description: String,
    /// What the glossary says in plain words, in the user's language; `None` for a package it does not know.
    pub plain: Option<String>,
    pub signals: Vec<Signal>,
    pub level: u8,
}

#[derive(Debug, Clone, Serialize)]
pub struct Review {
    pub target: String,
    /// The highest level among the entries.
    pub level: u8,
    /// False when the AUR could not be reached or the tree was cut short: the list may be missing pieces.
    pub complete: bool,
    pub entries: Vec<Entry>,
}

fn now() -> i64 {
    SystemTime::now().duration_since(UNIX_EPOCH).map_or(0, |d| d.as_secs() as i64)
}

/// The warning signs the AUR record of a package shows.
pub fn signals(item: &RpcItem, now: i64) -> Vec<Signal> {
    let mut out = Vec::new();
    let mut add = |code, level| out.push(Signal { code, level });
    if item.maintainer.is_none() {
        add("orphan", 2);
    }
    if item.out_of_date.is_some() {
        add("out_of_date", 1);
    }
    if item.num_votes.unwrap_or(0) < 2 && item.popularity.unwrap_or(0.0) < 0.05 {
        add("few_votes", 1);
    }
    if item.first_submitted.is_some_and(|t| now - t < 30 * DAY) {
        add("new_package", 1);
    }
    if item.last_modified.is_some_and(|t| now - t > 2 * 365 * DAY) {
        add("stale", 1);
    }
    if ["-git", "-svn", "-hg", "-bzr"].iter().any(|s| item.name.ends_with(s)) {
        add("vcs", 0);
    }
    out
}

/// `foo>=1.2` and `foo=3` both mean `foo`.
fn strip_constraint(dep: &str) -> String {
    dep.chars().take_while(|c| !"<>=".contains(*c)).collect::<String>().trim().to_owned()
}

/// The dependencies pacman says are not satisfied yet (`pacman -T` prints exactly those).
fn unsatisfied(deps: &[String]) -> HashSet<String> {
    if deps.is_empty() {
        return HashSet::new();
    }
    let args: Vec<&str> = std::iter::once("-T").chain(deps.iter().map(String::as_str)).collect();
    match sys::run("pacman", &args, Some(Duration::from_secs(10))) {
        Some(out) if out.success => HashSet::new(),
        Some(out) => out.stdout.lines().map(strip_constraint).filter(|d| !d.is_empty()).collect(),
        // Cannot ask: list everything rather than hide something.
        None => deps.iter().map(|d| strip_constraint(d)).collect(),
    }
}

/// `name version` lines from `pacman -Sp --print-format '%n %v'`.
fn parse_print_format(stdout: &str) -> Vec<(String, String)> {
    stdout
        .lines()
        .filter_map(|l| {
            let mut parts = l.split_whitespace();
            Some((parts.next()?.to_owned(), parts.next()?.to_owned()))
        })
        .collect()
}

/// The official packages that would be installed to satisfy `dep` (it and its own official dependencies,
/// minus what is installed). `None` when no repository provides it: then it must come from the AUR.
fn official_resolve(dep: &str) -> Option<Vec<(String, String)>> {
    let out = sys::run("pacman", &["-Sp", "--needed", "--print-format", "%n %v", dep], Some(Duration::from_secs(15)))?;
    out.success.then(|| parse_print_format(&out.stdout))
}

/// AUR records for `names`, the cached ones first; the rest come from one request.
fn fetch_aur(names: &[String], db: Option<&Knowledge>) -> HashMap<String, RpcItem> {
    let mut found = HashMap::new();
    let mut missing = Vec::new();
    for name in names {
        match db.and_then(|d| d.cached_aur(name, CACHE_SECS)).and_then(|j| serde_json::from_str::<RpcItem>(&j).ok()) {
            Some(item) => {
                found.insert(name.clone(), item);
            }
            None => missing.push(name.clone()),
        }
    }
    for item in info_many(&missing) {
        if let (Some(d), Ok(json)) = (db, serde_json::to_string(&item)) {
            d.cache_aur(&item.name, &json);
        }
        found.insert(item.name.clone(), item);
    }
    found
}

fn level_of(signals: &[Signal]) -> u8 {
    signals.iter().map(|s| s.level).max().unwrap_or(0)
}

fn aur_entry(item: &RpcItem, build_only: bool, target: bool, now: i64, lang: &str, db: Option<&Knowledge>) -> Entry {
    let signals = signals(item, now);
    Entry {
        name: item.name.clone(),
        version: item.version.clone().unwrap_or_default(),
        source: "aur",
        build_only,
        target,
        description: item.description.clone().unwrap_or_default(),
        plain: db.and_then(|d| d.explain(&item.name, lang)),
        level: level_of(&signals),
        signals,
    }
}

fn unknown_entry(name: &str, build_only: bool, target: bool) -> Entry {
    let signals = vec![Signal { code: "unresolved", level: 1 }];
    Entry {
        name: name.to_owned(),
        version: String::new(),
        source: "unknown",
        build_only,
        target,
        description: String::new(),
        plain: None,
        level: 1,
        signals,
    }
}

/// Review installing the AUR package `target`. Talks to the AUR and runs `pacman` (read only).
pub fn review(target: &str, lang: &str, db: Option<&Knowledge>) -> Review {
    let now = now();
    let mut entries: Vec<Entry> = Vec::new();
    let mut official: Vec<(String, String, bool)> = Vec::new();
    let mut listed: HashSet<String> = HashSet::from([target.to_owned()]);
    let mut asked: HashSet<String> = HashSet::new();
    let mut pending: Vec<(String, bool)> = vec![(target.to_owned(), false)];
    let mut complete = true;

    for _ in 0..MAX_DEPTH {
        if pending.is_empty() || entries.len() + official.len() >= MAX_ENTRIES {
            break;
        }
        let names: Vec<String> = pending.iter().map(|(n, _)| n.clone()).collect();
        let items = fetch_aur(&names, db);
        let mut next: Vec<(String, bool)> = Vec::new();

        for (name, build_only) in std::mem::take(&mut pending) {
            let is_target = name == target;
            let Some(item) = items.get(&name) else {
                complete &= !is_target;
                entries.push(unknown_entry(&name, build_only, is_target));
                continue;
            };
            entries.push(aur_entry(item, build_only, is_target, now, lang, db));

            let deps: Vec<(String, bool)> = item
                .depends
                .iter()
                .flatten()
                .map(|d| (d.clone(), build_only))
                .chain(item.make_depends.iter().flatten().map(|d| (d.clone(), true)))
                .collect();
            let all: Vec<String> = deps.iter().map(|(d, _)| d.clone()).collect();
            let missing = unsatisfied(&all);

            for (dep, dep_build_only) in deps {
                let base = strip_constraint(&dep);
                if !missing.contains(&base) || !asked.insert(base.clone()) {
                    continue;
                }
                match official_resolve(&base) {
                    Some(packages) => {
                        for (n, v) in packages {
                            if listed.insert(n.clone()) {
                                official.push((n, v, dep_build_only));
                            }
                        }
                    }
                    None if !listed.contains(&base) => {
                        listed.insert(base.clone());
                        next.push((base, dep_build_only));
                    }
                    None => {}
                }
            }
        }
        pending = next;
    }
    // Still something to follow after the last level, or the list hit its limit: it is not the whole tree.
    if !pending.is_empty() || entries.len() + official.len() > MAX_ENTRIES {
        complete = false;
    }
    for (name, build_only) in pending {
        entries.push(unknown_entry(&name, build_only, false));
    }

    if !official.is_empty() {
        let names: Vec<&str> = official.iter().map(|(n, _, _)| n.as_str()).collect();
        let args: Vec<&str> = std::iter::once("-Si").chain(names).collect();
        let described: HashMap<String, String> = sys::run("pacman", &args, Some(Duration::from_secs(15)))
            .map(|o| parse_info_blocks(&o.stdout))
            .unwrap_or_default()
            .into_iter()
            .filter_map(|mut d| Some((d.remove("Name")?, d.remove("Description").unwrap_or_default())))
            .collect();
        for (name, version, build_only) in official {
            entries.push(Entry {
                plain: db.and_then(|d| d.explain(&name, lang)),
                description: described.get(&name).cloned().unwrap_or_default(),
                name,
                version,
                source: "official",
                build_only,
                target: false,
                signals: Vec::new(),
                level: 0,
            });
        }
    }

    // The package asked for first, then the rest in the order found.
    entries.sort_by_key(|e| !e.target);
    Review { target: target.to_owned(), level: entries.iter().map(|e| e.level).max().unwrap_or(0), complete, entries }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::aur::parse_rpc;

    const INFO: &str = include_str!("../tests/fixtures/aur_info_yay.json");
    /// Just after the fixture's `LastModified`.
    const NOW: i64 = 1_793_200_000;

    fn item(edit: impl Fn(&str) -> String) -> RpcItem {
        parse_rpc(&edit(INFO)).unwrap().remove(0)
    }

    fn codes(item: &RpcItem) -> Vec<&'static str> {
        signals(item, NOW).iter().map(|s| s.code).collect()
    }

    #[test]
    fn a_well_kept_package_has_no_signals() {
        assert!(codes(&item(|s| s.to_owned())).is_empty());
    }

    #[test]
    fn an_orphan_is_serious() {
        let orphan = item(|s| s.replace("\"Maintainer\":\"jguer\"", "\"Maintainer\":null"));
        assert_eq!(signals(&orphan, NOW), [Signal { code: "orphan", level: 2 }]);
    }

    #[test]
    fn each_sign_has_its_own_code() {
        assert_eq!(codes(&item(|s| s.replace("\"OutOfDate\":null", "\"OutOfDate\":1790000000"))), ["out_of_date"]);
        assert_eq!(codes(&item(|s| s.replace("\"NumVotes\":2650", "\"NumVotes\":0").replace("\"Popularity\":16.179644", "\"Popularity\":0"))), ["few_votes"]);
        assert_eq!(codes(&item(|s| s.replace("\"FirstSubmitted\":1475688004", "\"FirstSubmitted\":1793000000"))), ["new_package"]);
        assert_eq!(codes(&item(|s| s.replace("\"LastModified\":1793196922", "\"LastModified\":1600000000"))), ["stale"]);
        assert_eq!(codes(&item(|s| s.replace("\"Name\":\"yay\"", "\"Name\":\"yay-git\""))), ["vcs"]);
    }

    #[test]
    fn the_level_is_the_highest_sign() {
        assert_eq!(level_of(&[]), 0);
        assert_eq!(level_of(&[Signal { code: "vcs", level: 0 }, Signal { code: "stale", level: 1 }]), 1);
        assert_eq!(level_of(&[Signal { code: "orphan", level: 2 }, Signal { code: "stale", level: 1 }]), 2);
    }

    #[test]
    fn version_constraints_are_dropped() {
        assert_eq!(strip_constraint("pacman>6.1"), "pacman");
        assert_eq!(strip_constraint("go>=1.26"), "go");
        assert_eq!(strip_constraint("foo=2"), "foo");
        assert_eq!(strip_constraint("git"), "git");
    }

    #[test]
    fn pacman_print_format_is_parsed() {
        let out = "qt6-base 6.9.1-1\nglib2 2.84.2-1\n\nbroken\n";
        assert_eq!(
            parse_print_format(out),
            [("qt6-base".to_owned(), "6.9.1-1".to_owned()), ("glib2".to_owned(), "2.84.2-1".to_owned())]
        );
    }

    #[test]
    fn the_rpc_record_carries_the_dependencies() {
        let yay = item(|s| s.to_owned());
        assert_eq!(yay.depends.as_deref(), Some(&["pacman>6.1".to_owned(), "git".to_owned()][..]));
        assert_eq!(yay.make_depends.as_deref(), Some(&["go>=1.26".to_owned()][..]));
    }

    #[test]
    fn a_record_survives_the_cache_roundtrip() {
        let yay = item(|s| s.to_owned());
        let back: RpcItem = serde_json::from_str(&serde_json::to_string(&yay).unwrap()).unwrap();
        assert_eq!(back.name, "yay");
        assert_eq!(back.maintainer.as_deref(), Some("jguer"));
        assert_eq!(back.depends, yay.depends);
    }
}

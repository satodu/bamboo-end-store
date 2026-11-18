//! npm packages installed globally (`npm install -g`). Search and details come from the registry
//! through the `npm` binary, so the user's own npm configuration (registry, proxy) applies.

use std::collections::HashSet;
use std::time::Duration;

use serde_json::Value;

use crate::manager::PackageManager;
use crate::sys::{self, shell_quote};
use crate::{Details, Package, PackageSource};

const SEARCH_LIMIT: &str = "--searchlimit=25";
const NETWORK: Duration = Duration::from_secs(30);

/// The shell snippet that picks `sudo` only when the global prefix is not writable by the user
/// (the usual case with the distro's `/usr` prefix; a user prefix needs no root).
fn global_npm(action: &str, name: &str) -> String {
    let n = shell_quote(name);
    format!(
        "if [ -w \"$(npm prefix -g)/lib/node_modules\" ]; then npm {action} -g {n}; else sudo npm {action} -g {n}; fi"
    )
}

/// The whole-set update the system updater runs: `npm update -g`, with `sudo` when needed.
pub const UPDATE_ALL_COMMAND: &str =
    "if [ -w \"$(npm prefix -g)/lib/node_modules\" ]; then npm update -g; else sudo npm update -g; fi";

pub fn available() -> bool {
    sys::command_exists("npm")
}

#[derive(Default)]
pub struct NpmManager;

impl NpmManager {
    fn installed_names(&self) -> HashSet<String> {
        self.installed().into_iter().map(|p| p.name).collect()
    }
}

fn package(name: &str, version: &str, description: &str, installed: bool) -> Package {
    Package {
        repo: "npm".into(),
        name: name.into(),
        display_name: String::new(),
        version: version.into(),
        description: description.into(),
        source: PackageSource::Npm,
        installed,
    }
}

fn text(v: &Value, key: &str) -> String {
    v.get(key).and_then(Value::as_str).unwrap_or_default().to_owned()
}

/// `npm search --json`: an array of `{name, version, description, ...}`.
pub fn parse_search(json: &str, installed: &HashSet<String>) -> Vec<Package> {
    let Ok(Value::Array(items)) = serde_json::from_str::<Value>(json) else {
        return Vec::new();
    };
    items
        .iter()
        .filter_map(|i| {
            let name = text(i, "name");
            (!name.is_empty()).then(|| package(&name, &text(i, "version"), &text(i, "description"), installed.contains(&name)))
        })
        .collect()
}

/// `npm ls -g --depth=0 --json`: `{dependencies: {name: {version}}}`. npm exits non-zero for
/// broken or extraneous packages but still prints the list, so the exit code is not checked.
pub fn parse_installed(json: &str) -> Vec<Package> {
    let Ok(value) = serde_json::from_str::<Value>(json) else {
        return Vec::new();
    };
    let Some(Value::Object(deps)) = value.get("dependencies") else {
        return Vec::new();
    };
    let mut list: Vec<Package> = deps.iter().map(|(name, d)| package(name, &text(d, "version"), "", true)).collect();
    list.sort_by(|a, b| a.name.cmp(&b.name));
    list
}

/// `npm view <name> --json` as the details panel's fields.
pub fn parse_view(json: &str) -> Option<Details> {
    let mut v: Value = serde_json::from_str(json).ok()?;
    // A name with several matching versions comes back as an array; the last one is the newest.
    if let Value::Array(items) = &mut v {
        v = items.pop()?;
    }
    let name = text(&v, "name");
    if name.is_empty() {
        return None;
    }
    let mut d = Details::new();
    d.insert("Name".into(), name);
    d.insert("Repository".into(), "npm".into());
    d.insert("Version".into(), text(&v, "version"));
    d.insert("Description".into(), text(&v, "description"));
    d.insert("Licenses".into(), match v.get("license") {
        Some(Value::String(s)) => s.clone(),
        Some(o) => text(o, "type"),
        None => String::new(),
    });
    d.insert("URL".into(), text(&v, "homepage"));
    if let Some(m) = v.get("maintainers").and_then(Value::as_array).and_then(|a| a.first()) {
        // Either "name <mail>" or {name, email}: show the name only.
        let who = m.as_str().map(str::to_owned).unwrap_or_else(|| text(m, "name"));
        d.insert("Maintainer".into(), who.split('<').next().unwrap_or("").trim().to_owned());
    }
    if let Some(size) = v.pointer("/dist/unpackedSize").and_then(Value::as_u64) {
        d.insert("Installed Size".into(), human_size(size));
    }
    if let Some(modified) = v.pointer("/time/modified").and_then(Value::as_str) {
        d.insert("Build Date".into(), modified.replace('T', " ").chars().take(16).collect::<String>() + " UTC");
    }
    d.retain(|_, v| !v.is_empty());
    Some(d)
}

fn human_size(bytes: u64) -> String {
    const UNITS: [&str; 4] = ["B", "KiB", "MiB", "GiB"];
    let mut size = bytes as f64;
    let mut unit = 0;
    while size >= 1024.0 && unit < UNITS.len() - 1 {
        size /= 1024.0;
        unit += 1;
    }
    if unit == 0 { format!("{bytes} B") } else { format!("{size:.1} {}", UNITS[unit]) }
}

/// How many global packages `npm outdated -g --json` reports. An error object (bad token, offline)
/// is not a package and counts for nothing.
pub fn parse_outdated(json: &str) -> usize {
    match serde_json::from_str::<Value>(json) {
        Ok(Value::Object(map)) => map.values().filter(|v| v.get("latest").is_some()).count(),
        _ => 0,
    }
}

impl PackageManager for NpmManager {
    fn search(&self, query: &str) -> Vec<Package> {
        if query.is_empty() || !available() {
            return Vec::new();
        }
        let installed = self.installed_names();
        sys::run("npm", &["search", "--json", SEARCH_LIMIT, "--", query], Some(NETWORK))
            .filter(|o| o.success)
            .map(|o| parse_search(&o.stdout, &installed))
            .unwrap_or_default()
    }

    fn details(&self, name: &str) -> Option<Details> {
        if !available() {
            return None;
        }
        let out = sys::run("npm", &["view", "--json", "--", name], Some(NETWORK)).filter(|o| o.success)?;
        let mut d = parse_view(&out.stdout)?;
        d.insert("is_installed".into(), self.is_installed(name).to_string());
        Some(d)
    }

    fn is_installed(&self, name: &str) -> bool {
        self.installed_names().contains(name)
    }

    fn installed(&self) -> Vec<Package> {
        if !available() {
            return Vec::new();
        }
        sys::run("npm", &["ls", "-g", "--depth=0", "--json"], Some(Duration::from_secs(20)))
            .map(|o| parse_installed(&o.stdout))
            .unwrap_or_default()
    }

    fn install_command(&self, name: &str) -> String {
        global_npm("install", name)
    }

    fn remove_command(&self, name: &str) -> String {
        global_npm("uninstall", name)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    const LS: &str = include_str!("../tests/fixtures/npm_ls_g.json");
    const SEARCH: &str = include_str!("../tests/fixtures/npm_search.json");
    const VIEW: &str = include_str!("../tests/fixtures/npm_view_typescript.json");
    const OUTDATED: &str = include_str!("../tests/fixtures/npm_outdated_g.json");
    const OUTDATED_ERROR: &str = include_str!("../tests/fixtures/npm_outdated_error.json");

    #[test]
    fn installed_lists_scoped_names_sorted() {
        let p = parse_installed(LS);
        let names: Vec<_> = p.iter().map(|p| (p.name.as_str(), p.version.as_str())).collect();
        assert_eq!(names, [("@scope/tool", "0.1.38"), ("typescript", "5.4.5")]);
        assert!(p.iter().all(|p| p.installed && p.source == PackageSource::Npm && p.repo == "npm"));
    }

    #[test]
    fn installed_survives_garbage_and_empty() {
        assert!(parse_installed("").is_empty());
        assert!(parse_installed("{}").is_empty());
        assert!(parse_installed("not json").is_empty());
    }

    #[test]
    fn search_marks_installed_and_tolerates_missing_fields() {
        let installed: HashSet<String> = ["typescript".to_owned()].into();
        let p = parse_search(SEARCH, &installed);
        assert_eq!(p.len(), 3);
        assert!(p[0].installed && !p[1].installed);
        assert_eq!(p[1].name, "@babel/preset-typescript");
        assert_eq!(p[2].description, "");
        assert!(parse_search("[]", &installed).is_empty());
        assert!(parse_search("oops", &installed).is_empty());
    }

    #[test]
    fn view_fills_the_details_panel_fields() {
        let d = parse_view(VIEW).unwrap();
        assert_eq!(d["Version"], "5.4.5");
        assert_eq!(d["Licenses"], "Apache-2.0");
        assert_eq!(d["Maintainer"], "alice");
        assert_eq!(d["Installed Size"], "61.0 MiB");
        assert_eq!(d["Build Date"], "2024-04-02 10:11 UTC");
        assert_eq!(d["URL"], "https://www.typescriptlang.org/");
        assert!(parse_view("{}").is_none());
    }

    #[test]
    fn outdated_counts_packages_not_errors() {
        assert_eq!(parse_outdated(OUTDATED), 2);
        assert_eq!(parse_outdated(OUTDATED_ERROR), 0);
        assert_eq!(parse_outdated(""), 0);
        assert_eq!(parse_outdated("{}"), 0);
    }

    #[test]
    fn commands_use_sudo_only_when_the_prefix_is_not_writable() {
        let m = NpmManager;
        let c = m.install_command("@scope/tool");
        assert!(c.contains("npm install -g '@scope/tool'; else sudo npm install -g '@scope/tool'"));
        assert!(m.remove_command("x").contains("sudo npm uninstall -g 'x'"));
        for script in [c, m.remove_command("a b"), UPDATE_ALL_COMMAND.to_owned()] {
            let ok = std::process::Command::new("bash").arg("-n").arg("-c").arg(&script).status().unwrap().success();
            assert!(ok, "bash -n rejected: {script}");
        }
    }

    #[test]
    fn a_name_cannot_break_out_of_the_command() {
        let c = NpmManager.install_command("x'; rm -rf ~; '");
        assert!(c.contains(r"'x'\''; rm -rf ~; '\'''"), "{c}");
    }
}

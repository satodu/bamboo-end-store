use std::collections::HashSet;
use std::time::Duration;

use crate::manager::PackageManager;
use crate::sys::{self, shell_quote};
use crate::{Details, Package, PackageSource};

pub struct PacmanManager;

impl PacmanManager {
    fn installed_names(&self) -> HashSet<String> {
        sys::run("pacman", &["-Qq"], None)
            .filter(|o| o.success)
            .map(|o| o.stdout.lines().map(str::to_owned).collect())
            .unwrap_or_default()
    }

    /// `pacman -Si a b c`: batch lookup by exact name.
    pub fn packages_by_names(&self, names: &[&str]) -> Vec<Package> {
        let names: Vec<&str> = names.iter().map(|n| n.trim()).filter(|n| !n.is_empty()).collect();
        if names.is_empty() {
            return Vec::new();
        }
        let mut args = vec!["-Si"];
        args.extend(names);
        let Some(out) = sys::run("pacman", &args, None) else {
            return Vec::new();
        };
        let installed = self.installed_names();
        parse_info_blocks(&out.stdout)
            .into_iter()
            .map(|mut d| {
                let name = d.remove("Name").unwrap_or_default();
                Package {
                    repo: d.remove("Repository").unwrap_or_else(|| "core".into()),
                    version: d.remove("Version").unwrap_or_default(),
                    description: d.remove("Description").unwrap_or_default(),
                    installed: installed.contains(&name),
                    source: PackageSource::Pacman,
                    display_name: String::new(),
                    name,
                }
            })
            .collect()
    }
}

impl PackageManager for PacmanManager {
    fn search(&self, query: &str) -> Vec<Package> {
        if query.is_empty() {
            return Vec::new();
        }
        let Some(out) = sys::run("pacman", &["-Ss", query], Some(Duration::from_secs(5))) else {
            return Vec::new();
        };
        if !out.success {
            return Vec::new();
        }
        let installed = self.installed_names();
        let mut packages = parse_search(&out.stdout);
        for p in &mut packages {
            p.installed = installed.contains(&p.name);
        }
        packages
    }

    fn details(&self, name: &str) -> Option<Details> {
        let out = sys::run("pacman", &["-Si", name], None)
            .filter(|o| o.success)
            .or_else(|| sys::run("pacman", &["-Qi", name], None).filter(|o| o.success))?;
        let mut details = parse_info(&out.stdout);
        if details.is_empty() {
            return None;
        }
        details.insert("is_installed".into(), self.is_installed(name).to_string());
        Some(details)
    }

    fn is_installed(&self, name: &str) -> bool {
        sys::run("pacman", &["-Qq", name], None).is_some_and(|o| o.success)
    }

    /// Everything installed: `pacman -Q`, with foreign packages (`-Qm`) marked as AUR.
    fn installed(&self) -> Vec<Package> {
        let Some(all) = sys::run("pacman", &["-Q"], None).filter(|o| o.success) else {
            return Vec::new();
        };
        let foreign = sys::run("pacman", &["-Qm"], None).filter(|o| o.success).map(|o| o.stdout).unwrap_or_default();
        parse_installed(&all.stdout, &foreign)
    }

    fn by_names(&self, names: &[&str]) -> Vec<Package> {
        self.packages_by_names(names)
    }

    fn install_command(&self, name: &str) -> String {
        format!("sudo pacman -S --noconfirm {}", shell_quote(name))
    }

    fn remove_command(&self, name: &str) -> String {
        format!("sudo pacman -Rns --noconfirm {}", shell_quote(name))
    }
}

/// Parse `pacman -Ss`: a `repo/name version [flags]` line followed by an indented description.
/// `installed` is left false; the caller fills it from `pacman -Qq`.
pub fn parse_search(output: &str) -> Vec<Package> {
    let mut packages = Vec::new();
    let mut lines = output.lines().filter(|l| !l.trim().is_empty());

    while let Some(line) = lines.next() {
        let Some((repo, rest)) = line.trim().split_once('/') else {
            continue;
        };
        let mut words = rest.split_whitespace();
        let (Some(name), Some(version)) = (words.next(), words.next()) else {
            continue;
        };
        if repo.contains(char::is_whitespace) {
            continue;
        }
        let description = lines.next().map(|l| l.trim().to_owned()).unwrap_or_default();
        packages.push(Package {
            repo: repo.to_owned(),
            name: name.to_owned(),
            display_name: String::new(),
            version: version.to_owned(),
            description,
            source: PackageSource::Pacman,
            installed: false,
        });
    }
    packages
}

/// `pacman -Q` lines (`name version`); names also listed by `pacman -Qm` are AUR/foreign.
pub fn parse_installed(all: &str, foreign: &str) -> Vec<Package> {
    let foreign: HashSet<&str> = foreign.lines().filter_map(|l| l.split_whitespace().next()).collect();
    all.lines()
        .filter_map(|line| {
            let mut parts = line.split_whitespace();
            let (name, version) = (parts.next()?, parts.next()?);
            Some(Package {
                repo: if foreign.contains(name) { "aur" } else { "local" }.into(),
                name: name.to_owned(),
                display_name: String::new(),
                version: version.to_owned(),
                description: String::new(),
                source: if foreign.contains(name) { PackageSource::Aur } else { PackageSource::Pacman },
                installed: true,
            })
        })
        .collect()
}

/// Parse `Key : Value` lines. Continuation lines without ` : ` are dropped, as in the PHP.
pub fn parse_info(output: &str) -> Details {
    output
        .lines()
        .filter_map(|l| l.split_once(" : "))
        .map(|(k, v)| (k.trim().to_owned(), v.trim().to_owned()))
        .filter(|(k, _)| !k.is_empty())
        .collect()
}

/// Split multi-package `-Si` output on blank lines.
pub fn parse_info_blocks(output: &str) -> Vec<Details> {
    output
        .split("\n\n")
        .map(parse_info)
        .filter(|d| d.contains_key("Name"))
        .collect()
}

#[cfg(test)]
mod tests {
    use super::*;

    const SS: &str = include_str!("../tests/fixtures/pacman_Ss_firefox.txt");
    const SI: &str = include_str!("../tests/fixtures/pacman_Si_firefox.txt");

    #[test]
    fn search_pairs_header_and_description() {
        let p = parse_search(SS);
        assert_eq!(p.len(), 3);
        assert_eq!(p[0].repo, "cachyos-v3");
        assert_eq!(p[0].name, "firefox-pure");
        assert_eq!(p[0].version, "157.0-1");
        assert_eq!(p[2].name, "firefox");
        assert_eq!(p[2].description, "Fast, Private & Safe Web Browser");
    }

    #[test]
    fn installed_marks_foreign_packages_as_aur() {
        let all = include_str!("../tests/fixtures/pacman_Q.txt");
        let foreign = include_str!("../tests/fixtures/pacman_Qm.txt");
        let pkgs = parse_installed(all, foreign);
        assert_eq!(pkgs.len(), 30);
        assert!(pkgs.iter().all(|p| p.installed && !p.version.is_empty()));
        let aur_names: Vec<&str> = foreign.lines().filter_map(|l| l.split_whitespace().next()).collect();
        for p in &pkgs {
            assert_eq!(p.source == PackageSource::Aur, aur_names.contains(&p.name.as_str()), "{}", p.name);
        }
        assert_eq!(parse_installed("a 1\nbroken\n", "")[0].name, "a");
    }

    #[test]
    fn info_reads_key_value_lines() {
        let d = parse_info(SI);
        assert_eq!(d["Name"], "firefox");
        assert_eq!(d["Repository"], "extra");
        assert_eq!(d["Version"], "159.0-1");
        assert!(d["Depends On"].starts_with("alsa-lib"));
    }

    #[test]
    fn info_blocks_split_on_blank_line() {
        let two = format!("{SI}\n\n{SI}");
        assert_eq!(parse_info_blocks(&two).len(), 2);
    }

    #[test]
    fn commands_quote_names() {
        let m = PacmanManager;
        assert_eq!(m.install_command("a'b"), r"sudo pacman -S --noconfirm 'a'\''b'");
    }
}

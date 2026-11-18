//! "Are there updates?": a read-only check the tray icon and the sidebar show. It never installs
//! anything and never asks for a password; the user decides whether to run "Update System".

use std::time::Duration;

use serde::Serialize;

use crate::aur::Helper;
use crate::settings::Settings;
use crate::sys;

const SLOW: Duration = Duration::from_secs(90);

/// Updates waiting, per source.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Default, Serialize)]
pub struct Pending {
    /// False until a check has finished, so the UI can tell "none" from "not looked yet".
    pub checked: bool,
    pub pacman: usize,
    pub aur: usize,
    pub flatpak: usize,
    pub npm: usize,
    pub total: usize,
}

impl Pending {
    fn new(pacman: usize, aur: usize, flatpak: usize, npm: usize) -> Self {
        Self { checked: true, pacman, aur, flatpak, npm, total: pacman + aur + flatpak + npm }
    }
}

/// One update per non-empty line (`checkupdates`, `paru -Qua`).
pub fn count_lines(output: &str) -> usize {
    output.lines().filter(|l| !l.trim().is_empty()).count()
}

/// `flatpak remote-ls --updates --columns=application`: application ids only. A header or a
/// message has spaces; an id never does and always has a dot.
pub fn count_flatpak(output: &str) -> usize {
    output.lines().map(str::trim).filter(|l| l.contains('.') && !l.contains(char::is_whitespace)).count()
}

fn pacman_updates() -> usize {
    // `checkupdates` (pacman-contrib) syncs a private copy of the databases, so it neither needs root
    // nor touches the real ones. Plain `pacman -Qu` only knows what the last `-Sy` fetched.
    let out = if sys::command_exists("checkupdates") {
        sys::run("checkupdates", &[], Some(SLOW))
    } else {
        sys::run("pacman", &["-Qu"], Some(SLOW))
    };
    out.map_or(0, |o| count_lines(&o.stdout))
}

fn aur_updates(helper: Helper) -> usize {
    helper
        .bin()
        .and_then(|bin| sys::run(bin, &["-Qua"], Some(SLOW)))
        .map_or(0, |o| count_lines(&o.stdout))
}

fn flatpak_updates() -> usize {
    if !sys::command_exists("flatpak") {
        return 0;
    }
    sys::run("flatpak", &["remote-ls", "--updates", "--columns=application"], Some(SLOW))
        .map_or(0, |o| count_flatpak(&o.stdout))
}

fn npm_updates() -> usize {
    if !crate::npm::available() {
        return 0;
    }
    // `npm outdated` exits 1 when something is outdated; the JSON on stdout is what counts.
    sys::run("npm", &["outdated", "-g", "--json"], Some(SLOW)).map_or(0, |o| crate::npm::parse_outdated(&o.stdout))
}

/// Look for updates in every source the settings turned on. Blocks for a while (network); call from a worker thread.
pub fn check(settings: &Settings) -> Pending {
    let pacman = pacman_updates();
    let aur = if settings.enable_aur { aur_updates(Helper::detect()) } else { 0 };
    let flatpak = if settings.enable_flatpak { flatpak_updates() } else { 0 };
    let npm = if settings.enable_npm { npm_updates() } else { 0 };
    Pending::new(pacman, aur, flatpak, npm)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn counts_one_update_per_line() {
        let out = "linux 6.1-1 -> 6.2-1\nfirefox 1 -> 2\n\n  \n";
        assert_eq!(count_lines(out), 2);
        assert_eq!(count_lines(""), 0);
    }

    #[test]
    fn flatpak_ignores_headers_and_messages() {
        let out = "Application ID\norg.mozilla.firefox\norg.gimp.GIMP\nNothing to do.\n";
        assert_eq!(count_flatpak(out), 2);
        assert_eq!(count_flatpak(""), 0);
    }

    #[test]
    fn total_adds_up_and_marks_checked() {
        let p = Pending::new(3, 1, 2, 4);
        assert!(p.checked);
        assert_eq!(p.total, 10);
        assert!(!Pending::default().checked);
    }
}

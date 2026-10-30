//! "Bamboo Installs": software that needs more than `pacman -S` (multilib, Wine, 32-bit libraries,
//! accepting terms). Each has an install script run in a terminal. Port of
//! `App\Services\BambooInstallsService` and `scripts/installers/*.sh`, which are embedded here.

use std::collections::HashSet;
use std::path::Path;

use crate::manager::PackageManager;
use crate::sys::shell_quote;
use crate::{Details, Package, PackageSource};

pub struct CustomInstall {
    pub name: &'static str,
    pub display_name: &'static str,
    /// Where the package ends up coming from: shown as the official or AUR label.
    pub repo: &'static str,
    /// English text; also the translation key.
    pub description: &'static str,
    /// Package names that mean "this is installed" (the script may install a `-bin` build).
    pub aliases: &'static [&'static str],
    /// Or a file whose presence means the same (DaVinci Resolve's own installer).
    pub marker_file: Option<&'static str>,
    script: &'static str,
}

pub const CATALOG: [CustomInstall; 4] = [
    CustomInstall {
        name: "steam",
        display_name: "Steam",
        repo: "official",
        description: "The ultimate entertainment platform. (Bamboo custom install applies multilib, steam-devices and optimized graphics drivers automatically).",
        aliases: &["steam"],
        marker_file: None,
        script: include_str!("installers/steam.sh"),
    },
    CustomInstall {
        name: "winboat",
        display_name: "WinBoat",
        repo: "aur",
        description: "Run Windows apps on Linux with seamless desktop integration. Powered by Docker and KVM.",
        aliases: &["winboat", "winboat-bin"],
        marker_file: None,
        script: include_str!("installers/winboat.sh"),
    },
    CustomInstall {
        name: "linuxtoys",
        display_name: "Linux Toys",
        repo: "aur",
        description: "A collection of tools for Linux in a user-friendly way. Essential tweaks, optimizations, kernel tools, and software for gaming and productivity.",
        aliases: &["linuxtoys", "linuxtoys-bin"],
        marker_file: None,
        script: include_str!("installers/linuxtoys.sh"),
    },
    CustomInstall {
        name: "davinci-resolve",
        display_name: "DaVinci Resolve",
        repo: "aur",
        description: "Professional Hollywood-grade video editing, color correction, visual effects, and audio post-production.",
        aliases: &["davinci-resolve", "davinci-resolve-studio"],
        marker_file: Some("/opt/resolve/bin/resolve"),
        script: include_str!("installers/davinci-resolve.sh"),
    },
];

pub fn find(name: &str) -> Option<&'static CustomInstall> {
    CATALOG.iter().find(|c| c.name == name)
}

impl CustomInstall {
    pub fn installed(&self, installed_names: &HashSet<String>) -> bool {
        self.aliases.iter().any(|a| installed_names.contains(*a)) || self.marker_file.is_some_and(|f| Path::new(f).exists())
    }

    pub fn package(&self, installed_names: &HashSet<String>) -> Package {
        Package {
            repo: self.repo.to_owned(),
            name: self.name.to_owned(),
            display_name: self.display_name.to_owned(),
            version: "latest".into(),
            description: self.description.to_owned(),
            source: PackageSource::Custom,
            installed: self.installed(installed_names),
        }
    }

    /// `bash -c '<script>'`: the script travels inside the command, so there is no file to leave behind.
    pub fn install_command(&self) -> String {
        format!("bash -c {}", shell_quote(self.script))
    }

    /// Remove whichever of the known package names is installed (`winboat-bin` rather than `winboat`).
    /// `pacman -Qq` resolves "provides", so `-Qq linuxtoys` answers `linuxtoys-bin`, and `-Rns linuxtoys`
    /// would then fail: the name pacman prints is the one to remove.
    pub fn remove_command(&self) -> String {
        let aliases: Vec<String> = self.aliases.iter().map(|a| shell_quote(a)).collect();
        format!(
            "PKG={default}; for a in {list}; do if real=$(pacman -Qq \"$a\" 2>/dev/null); then PKG=\"$real\"; break; fi; done; echo \":: Removing $PKG...\"; sudo pacman -Rns \"$PKG\"",
            default = shell_quote(self.name),
            list = aliases.join(" ")
        )
    }
}

/// The whole catalogue with installed flags, given the installed package names.
pub fn packages(installed_names: &HashSet<String>) -> Vec<Package> {
    CATALOG.iter().map(|c| c.package(installed_names)).collect()
}

/// Manager for `PackageSource::Custom`: only installs and removals; there is nothing to search.
pub struct CustomManager;

impl PackageManager for CustomManager {
    fn search(&self, _query: &str) -> Vec<Package> {
        Vec::new()
    }

    fn details(&self, name: &str) -> Option<Details> {
        let c = find(name)?;
        Some(
            [("Name", c.name), ("Description", c.description), ("Version", "latest"), ("Repository", c.repo)]
                .into_iter()
                .map(|(k, v)| (k.to_owned(), v.to_owned()))
                .collect(),
        )
    }

    fn is_installed(&self, name: &str) -> bool {
        find(name).is_some_and(|c| c.installed(&crate::pacman::installed_names()))
    }

    fn install_command(&self, name: &str) -> String {
        // An unknown name must never turn into a command: `false` just fails.
        find(name).map_or_else(|| "false".to_owned(), CustomInstall::install_command)
    }

    fn remove_command(&self, name: &str) -> String {
        find(name).map_or_else(|| "false".to_owned(), CustomInstall::remove_command)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn names(list: &[&str]) -> HashSet<String> {
        list.iter().map(|s| (*s).to_owned()).collect()
    }

    #[test]
    fn catalogue_matches_the_old_app() {
        let n: Vec<&str> = CATALOG.iter().map(|c| c.name).collect();
        assert_eq!(n, ["steam", "winboat", "linuxtoys", "davinci-resolve"]);
        assert!(CATALOG.iter().all(|c| c.script.starts_with("#!/bin/bash")));
        // every description is translated in all four languages
        for c in &CATALOG {
            for lang in ["en", "pt", "es", "ru"] {
                assert!(crate::i18n::table(lang).contains_key(c.description), "{lang}: {}", c.name);
            }
        }
    }

    #[test]
    fn aliases_count_as_installed() {
        let steam = find("steam").unwrap();
        assert!(steam.installed(&names(&["steam"])));
        assert!(!steam.installed(&names(&["steam-native-runtime"])));
        let winboat = find("winboat").unwrap();
        assert!(winboat.installed(&names(&["winboat-bin"])));
        assert!(!winboat.installed(&names(&["bash"])));
        assert!(find("davinci-resolve").unwrap().installed(&names(&["davinci-resolve-studio"])));
    }

    #[test]
    fn packages_are_custom_sourced_with_flags() {
        let p = packages(&names(&["linuxtoys-bin"]));
        assert_eq!(p.len(), 4);
        assert!(p.iter().all(|p| p.source == PackageSource::Custom));
        let installed: Vec<&str> = p.iter().filter(|p| p.installed).map(|p| p.name.as_str()).collect();
        assert_eq!(installed, ["linuxtoys"]);
    }

    #[test]
    fn install_command_carries_the_script_quoted() {
        let cmd = find("steam").unwrap().install_command();
        assert!(cmd.starts_with("bash -c '#!/bin/bash"));
        // A shell round trip: the quoted script must reach bash unchanged (printing it instead of running it).
        let out = std::process::Command::new("sh")
            .arg("-c")
            .arg(cmd.replacen("bash -c", "printf %s", 1))
            .output()
            .unwrap();
        assert_eq!(String::from_utf8_lossy(&out.stdout), include_str!("installers/steam.sh"));
    }

    #[test]
    fn remove_command_prefers_the_installed_alias() {
        let cmd = find("winboat").unwrap().remove_command();
        assert!(cmd.contains("'winboat' 'winboat-bin'"));
        assert!(cmd.contains("sudo pacman -Rns \"$PKG\""));
    }

    /// `linuxtoys-bin` provides `linuxtoys`, so `pacman -Qq linuxtoys` succeeds with the other name.
    /// Fake `pacman` and `sudo` on the PATH stand in for the real ones.
    #[test]
    fn remove_uses_the_name_pacman_reports() {
        let dir = std::env::temp_dir().join(format!("bamboo-test-{}-remove", std::process::id()));
        let _ = std::fs::remove_dir_all(&dir);
        std::fs::create_dir_all(&dir).unwrap();
        let script = |name: &str, body: &str| {
            use std::os::unix::fs::PermissionsExt;
            let path = dir.join(name);
            std::fs::write(&path, format!("#!/bin/bash\n{body}\n")).unwrap();
            std::fs::set_permissions(&path, std::fs::Permissions::from_mode(0o755)).unwrap();
        };
        script("pacman", r#"case "$1 $2" in "-Qq linuxtoys") echo linuxtoys-bin;; "-Rns "*) echo "removed $2";; *) exit 1;; esac"#);
        script("sudo", r#"exec "$@""#);
        let path = format!("{}:{}", dir.display(), std::env::var("PATH").unwrap_or_default());
        let out = std::process::Command::new("bash")
            .arg("-c")
            .arg(find("linuxtoys").unwrap().remove_command())
            .env("PATH", path)
            .output()
            .unwrap();
        let text = String::from_utf8_lossy(&out.stdout);
        assert!(out.status.success(), "{text}");
        assert!(text.contains("removed linuxtoys-bin"), "{text}");
        let _ = std::fs::remove_dir_all(&dir);
    }

    #[test]
    fn unknown_names_never_become_commands() {
        let m = CustomManager;
        assert_eq!(m.install_command("rm -rf /"), "false");
        assert_eq!(m.remove_command("../../etc/passwd"), "false");
        assert!(m.details("nope").is_none());
    }
}

//! The full system update the sidebar button runs in a terminal. Port of
//! `PackageService::runBambooUpdate`.

use crate::aur::Helper;
use crate::i18n;
use crate::sys::shell_quote;

fn message(lang: &str, key: &str, helper: &str) -> String {
    i18n::tr(lang, key).replace("{helper}", helper)
}

/// The shell script: EndeavourOS' `eos-update` when present, else a full upgrade with the AUR
/// helper (or plain `sudo pacman -Syu`), then Flatpak, then orphaned packages. Each step prints a
/// translated heading. Orphan removal is left to pacman's own confirmation prompt.
pub fn system_update_command(helper: Helper, lang: &str) -> String {
    let (helper_name, upgrade) = match helper {
        Helper::Paru => ("paru", "paru -Syu"),
        Helper::Yay => ("yay", "yay -Syu"),
        // The old app ran `pacman -Syu` without root, which always failed.
        Helper::None => ("pacman", "sudo pacman -Syu"),
    };
    let say = |key: &str| format!("echo {}", shell_quote(&message(lang, key, helper_name)));
    let aur_step = match helper {
        Helper::None => String::new(),
        _ => format!("{}; {helper_name} -Sua; ", say("console_updating_aur")),
    };

    format!(
        "if command -v eos-update >/dev/null; then {eos}; eos-update; {aur_step}else {sys}; {upgrade}; fi; \
         if command -v flatpak >/dev/null; then {flat}; flatpak update; fi; \
         orphans=$(pacman -Qtdq); \
         if [ -n \"$orphans\" ]; then {orph}; sudo pacman -Rns $orphans; else {none}; fi",
        eos = say("console_starting_eos_update"),
        sys = say("console_starting_update"),
        flat = say("console_updating_flatpak"),
        orph = say("console_removing_orphans"),
        none = say("console_no_orphans"),
    )
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn uses_the_aur_helper_when_there_is_one() {
        let c = system_update_command(Helper::Paru, "en");
        assert!(c.contains("paru -Syu"));
        assert!(c.contains("paru -Sua"), "eos-update path updates the AUR separately");
        assert!(c.contains("eos-update; "));
        assert!(c.contains("flatpak update"));
        assert!(c.contains("sudo pacman -Rns $orphans"));
        assert!(c.contains("Updating AUR packages via paru..."));
    }

    #[test]
    fn without_a_helper_pacman_gets_sudo() {
        let c = system_update_command(Helper::None, "en");
        assert!(c.contains("sudo pacman -Syu"));
        assert!(!c.contains("-Sua"), "no AUR step without a helper");
        assert!(!c.contains(" yay "));
    }

    #[test]
    fn headings_follow_the_language() {
        let c = system_update_command(Helper::Yay, "pt");
        assert!(c.contains("Iniciando atualização do sistema via yay..."));
        assert!(c.contains("Removendo pacotes órfãos..."));
        assert!(!c.contains("{helper}"), "placeholders are filled in");
    }

    #[test]
    fn is_valid_shell() {
        for helper in [Helper::Paru, Helper::Yay, Helper::None] {
            let c = system_update_command(helper, "ru");
            let ok = std::process::Command::new("bash").arg("-n").arg("-c").arg(&c).status().unwrap().success();
            assert!(ok, "bash -n rejected the script for {helper:?}: {c}");
        }
    }
}

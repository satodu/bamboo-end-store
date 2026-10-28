//! Desktop notifications when an install, removal or update ends. Port of
//! `NotificationService` and `SendOperationNotificationListener`; unlike the old app (which announced
//! success even when the command had failed) the exit code decides what is said.

use std::fs;
use std::path::PathBuf;

use crate::i18n::{self, format};
use crate::settings::Settings;

/// The app's icon, written to the cache on first use because notification daemons want a file.
const ICON: &[u8] = include_bytes!("notify/icon.png");

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Note {
    pub title: String,
    pub body: String,
    pub ok: bool,
}

/// What to say about a finished operation. `None` when nothing should be shown: the terminal was
/// closed before the command ended (`code == -2`), so we do not know how it went.
///
/// `name` is the package (or `System Update`), `action` is `installed`, `removed`, `updated` or
/// `configured`, `code` the command's exit code (-1: no terminal could be opened).
pub fn note_for(lang: &str, name: &str, action: &str, code: i32) -> Option<Note> {
    let t = |key: &str| i18n::tr(lang, key);
    match code {
        -2 => None,
        -1 => Some(Note { title: t("Error"), body: format(&t("install_fail"), &[name]), ok: false }),
        0 => Some(match (name, action) {
            ("System Update", _) => Note {
                title: t("Sistema Atualizado"),
                body: t("A atualização do sistema foi concluída com sucesso!"),
                ok: true,
            },
            (_, "removed") => Note {
                title: t("Remoção Concluída"),
                body: format(&t("O pacote %s foi removido do sistema."), &[name]),
                ok: true,
            },
            (_, "installed") => Note {
                title: t("Operação Concluída"),
                body: format(&t("O pacote %s foi instalado com sucesso!"), &[name]),
                ok: true,
            },
            _ => Note { title: t("Operação Concluída"), body: format(&t("op_done_generic"), &[name]), ok: true },
        }),
        failed => Some(Note { title: t("Error"), body: format(&t("%s failed (exit %s)"), &[name, &failed.to_string()]), ok: false }),
    }
}

fn icon_path() -> Option<PathBuf> {
    let cache = std::env::var_os("XDG_CACHE_HOME")
        .map(PathBuf::from)
        .filter(|p| p.is_absolute())
        .or_else(|| std::env::var_os("HOME").map(|h| PathBuf::from(h).join(".cache")))?;
    let dir = cache.join("bamboo-end-store");
    let path = dir.join("icon.png");
    if fs::metadata(&path).map(|m| m.len()).ok() != Some(ICON.len() as u64) {
        fs::create_dir_all(&dir).ok()?;
        fs::write(&path, ICON).ok()?;
    }
    Some(path)
}

/// Show a notification. Errors (no daemon, no session bus) are ignored: it is only a courtesy.
pub fn send(note: &Note) {
    let mut n = notify_rust::Notification::new();
    n.appname("Bamboo End Store").summary(&note.title).body(&note.body).timeout(8000);
    match icon_path() {
        Some(p) => n.icon(&p.to_string_lossy()),
        None => n.icon("system-software-install"),
    };
    let _ = n.show();
}

/// Called when an operation ends: notifies if the setting allows it. Blocks briefly; call from a worker thread.
pub fn operation_finished(settings: &Settings, name: &str, action: &str, code: i32) {
    if !settings.notifications {
        return;
    }
    if let Some(note) = note_for(i18n::language_for(&settings.locale), name, action, code) {
        send(&note);
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn success_messages_follow_the_action() {
        let n = note_for("en", "firefox", "installed", 0).unwrap();
        assert_eq!((n.title.as_str(), n.body.as_str()), ("Operation Completed", "The package firefox was installed successfully!"));
        assert!(n.ok);
        let r = note_for("en", "firefox", "removed", 0).unwrap();
        assert_eq!((r.title.as_str(), r.body.as_str()), ("Removal Completed", "The package firefox was removed from the system."));
        let u = note_for("en", "System Update", "updated", 0).unwrap();
        assert_eq!(u.title, "System Updated");
        assert!(note_for("en", "Flathub", "configured", 0).unwrap().body.contains("Flathub"));
    }

    #[test]
    fn failures_say_so_instead_of_celebrating() {
        let n = note_for("en", "firefox", "installed", 1).unwrap();
        assert!(!n.ok);
        assert_eq!(n.title, "Error");
        assert_eq!(n.body, "firefox failed (exit 1)");
        let none = note_for("en", "firefox", "installed", -1).unwrap();
        assert!(!none.ok && none.body.contains("firefox"));
    }

    #[test]
    fn a_closed_terminal_says_nothing() {
        assert_eq!(note_for("en", "firefox", "installed", -2), None);
    }

    #[test]
    fn uses_the_ui_language() {
        assert_eq!(note_for("pt", "x", "removed", 0).unwrap().title, "Remoção Concluída");
        assert_eq!(note_for("ru", "x", "installed", 3).unwrap().title, "Ошибка");
    }

    #[test]
    fn the_setting_turns_it_off() {
        // would otherwise try the session bus; with the setting off nothing may be attempted
        operation_finished(&Settings { notifications: false, ..Settings::default() }, "x", "installed", 0);
    }

    #[test]
    fn the_embedded_icon_is_a_png() {
        assert!(ICON.starts_with(b"\x89PNG"));
    }
}

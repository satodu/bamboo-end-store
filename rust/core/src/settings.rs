use std::fs;
use std::io;
use std::path::{Path, PathBuf};

use serde::{Deserialize, Serialize};
use serde_json::{Map, Value};

/// User settings. Field names and defaults match the PHP `$settings` array in `store.blade.php`,
/// so a `settings.json` written by the old app loads unchanged.
#[derive(Debug, Clone, PartialEq, Serialize, Deserialize)]
#[serde(default)]
pub struct Settings {
    pub enable_aur: bool,
    pub enable_flatpak: bool,
    pub search_limit: u32,
    pub appimage_path: String,
    pub locale: String,
    pub auto_close_terminal: bool,
    pub terminal_close_delay: u32,
    pub view_mode: String,
    pub appimages_view_mode: String,
    /// Legacy preset id (`theme-bamboo`, `theme-catppuccin`, `theme-nord`, `theme-gruvbox`).
    pub theme: String,
    /// Where the palette comes from: `auto`, `system`, `preset` or `seed`.
    pub theme_source: String,
    /// `#rrggbb` source colour for `theme_source = "seed"`.
    pub theme_seed: String,
    /// Optional `#rrggbb` overrides for the secondary and tertiary colours; empty = derived.
    pub theme_secondary: String,
    pub theme_tertiary: String,
    /// `dark` or `light`. Ignored by `system`, whose file is already one mode.
    pub theme_mode: String,
    /// Matugen scheme type, e.g. `scheme-tonal-spot`.
    pub theme_scheme: String,
    /// Keys this version does not know, kept so they survive a save.
    #[serde(flatten)]
    pub extra: Map<String, Value>,
}

impl Default for Settings {
    fn default() -> Self {
        Self {
            enable_aur: true,
            enable_flatpak: false,
            search_limit: 50,
            appimage_path: String::new(),
            locale: "system".into(),
            auto_close_terminal: true,
            terminal_close_delay: 10,
            view_mode: "grid".into(),
            appimages_view_mode: "grid".into(),
            theme: "theme-bamboo".into(),
            theme_source: "auto".into(),
            theme_seed: String::new(),
            theme_secondary: String::new(),
            theme_tertiary: String::new(),
            theme_mode: "dark".into(),
            theme_scheme: "scheme-tonal-spot".into(),
            extra: Map::new(),
        }
    }
}

impl Settings {
    pub fn from_json(text: &str) -> Option<Self> {
        serde_json::from_str(text).ok()
    }

    /// Load from `path`; missing or corrupt file gives defaults.
    pub fn load_from(path: &Path) -> Self {
        fs::read_to_string(path)
            .ok()
            .and_then(|t| Self::from_json(&t))
            .unwrap_or_default()
    }

    /// Load from the new location, else import the old Laravel/Electron file, else defaults.
    pub fn load() -> Self {
        let paths = std::iter::once(config_path()).chain(legacy_paths());
        paths
            .filter(|p| p.is_file())
            .find_map(|p| fs::read_to_string(p).ok().and_then(|t| Self::from_json(&t)))
            .unwrap_or_default()
    }

    pub fn save(&self) -> io::Result<()> {
        self.save_to(&config_path())
    }

    /// Write atomically (temp file + rename) so a crash never leaves half a file.
    pub fn save_to(&self, path: &Path) -> io::Result<()> {
        if let Some(dir) = path.parent() {
            fs::create_dir_all(dir)?;
        }
        let tmp = path.with_extension("json.tmp");
        let json = serde_json::to_string_pretty(self).map_err(io::Error::other)?;
        fs::write(&tmp, json)?;
        fs::rename(&tmp, path)
    }
}

fn home() -> Option<PathBuf> {
    std::env::var_os("HOME").map(PathBuf::from)
}

fn config_dir() -> PathBuf {
    std::env::var_os("XDG_CONFIG_HOME")
        .map(PathBuf::from)
        .filter(|p| p.is_absolute())
        .or_else(|| home().map(|h| h.join(".config")))
        .unwrap_or_else(|| PathBuf::from(".config"))
}

/// `~/.config/bamboo-end-store/settings.json`
pub fn config_path() -> PathBuf {
    config_dir().join("bamboo-end-store").join("settings.json")
}

/// Where the PHP/NativePHP build kept the file.
fn legacy_paths() -> Vec<PathBuf> {
    let base = config_dir();
    ["bamboo-end-store", "bamboo-end-store-dev"]
        .iter()
        .flat_map(|app| {
            let root = base.join(app).join("storage").join("app");
            [root.join("private").join("settings.json"), root.join("settings.json")]
        })
        .collect()
}

#[cfg(test)]
pub(crate) mod tests {
    use super::*;

    const PHP_FILE: &str = include_str!("../tests/fixtures/settings_php.json");

    #[test]
    fn loads_file_written_by_the_php_app() {
        let s = Settings::from_json(PHP_FILE).unwrap();
        assert!(s.enable_flatpak);
        assert!(!s.auto_close_terminal);
        assert_eq!(s.appimage_path, "/home/user/Applications");
        assert_eq!(s.theme, "theme-catppuccin");
        assert!(s.extra.is_empty());
    }

    #[test]
    fn missing_keys_fall_back_to_defaults() {
        let s = Settings::from_json(r#"{"search_limit": 20}"#).unwrap();
        assert_eq!(s.search_limit, 20);
        assert!(s.enable_aur);
        assert_eq!(s.theme, "theme-bamboo");
    }

    #[test]
    fn unknown_keys_survive_a_roundtrip() {
        let s = Settings::from_json(r#"{"future_flag": true}"#).unwrap();
        let back = Settings::from_json(&serde_json::to_string(&s).unwrap()).unwrap();
        assert_eq!(back.extra["future_flag"], Value::Bool(true));
    }

    #[test]
    fn corrupt_file_gives_defaults() {
        let dir = tempdir("corrupt");
        let path = dir.join("settings.json");
        fs::write(&path, "{not json").unwrap();
        assert_eq!(Settings::load_from(&path), Settings::default());
    }

    #[test]
    fn save_then_load() {
        let dir = tempdir("save");
        let path = dir.join("nested").join("settings.json");
        let mut s = Settings::default();
        s.view_mode = "list".into();
        s.save_to(&path).unwrap();
        assert_eq!(Settings::load_from(&path), s);
        assert!(!path.with_extension("json.tmp").exists());
    }

    pub(crate) fn tempdir(tag: &str) -> PathBuf {
        let dir = std::env::temp_dir().join(format!("bamboo-test-{}-{tag}", std::process::id()));
        let _ = fs::remove_dir_all(&dir);
        fs::create_dir_all(&dir).unwrap();
        dir
    }
}

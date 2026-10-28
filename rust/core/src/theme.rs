//! Palette handling: Material You roles from Matugen, the desktop's own palette file, or a
//! built-in fallback. Pure data; the QML side only receives the resulting role -> hex map.

use std::collections::BTreeMap;
use std::fs;
use std::path::PathBuf;

use serde_json::Value;

use crate::settings::Settings;
use crate::sys;

/// Role name (`surfaceContainerHigh`) to `#rrggbb`.
pub type Palette = BTreeMap<String, String>;

const FALLBACK_DARK: &str = include_str!("theme/fallback_dark.json");
const FALLBACK_LIGHT: &str = include_str!("theme/fallback_light.json");

const SCHEMES: [&str; 10] = [
    "scheme-content",
    "scheme-expressive",
    "scheme-fidelity",
    "scheme-fruit-salad",
    "scheme-monochrome",
    "scheme-neutral",
    "scheme-rainbow",
    "scheme-tonal-spot",
    "scheme-vibrant",
    "scheme-smart",
];

/// Source colours of the four legacy presets (from `resources/css/app.css`).
const PRESETS: [(&str, &str); 4] = [
    ("theme-bamboo", "#7c3aed"),
    ("theme-catppuccin", "#cba6f7"),
    ("theme-nord", "#88c0d0"),
    ("theme-gruvbox", "#fe8019"),
];
const DEFAULT_SEED: &str = "#7c3aed";

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Source {
    /// The desktop's palette file, followed live.
    System,
    Preset,
    Seed,
}

#[derive(Debug, Clone)]
pub struct Theme {
    pub palette: Palette,
    pub dark: bool,
    pub source: Source,
}

pub fn fallback(dark: bool) -> Palette {
    let text = if dark { FALLBACK_DARK } else { FALLBACK_LIGHT };
    serde_json::from_str(text).expect("bundled fallback palette is valid JSON")
}

pub fn is_hex(s: &str) -> bool {
    s.len() == 7 && s.starts_with('#') && s[1..].bytes().all(|b| b.is_ascii_hexdigit())
}

fn camel(snake: &str) -> String {
    let mut parts = snake.split('_');
    let mut out = parts.next().unwrap_or_default().to_owned();
    for p in parts {
        let mut c = p.chars();
        if let Some(f) = c.next() {
            out.extend(f.to_uppercase());
            out.push_str(c.as_str());
        }
    }
    out
}

/// `matugen ... -j hex` output: `colors.<snake_role>.{dark,light}.color`.
/// Stray text before the JSON (log lines) is skipped.
pub fn parse_matugen(output: &str, dark: bool) -> Option<Palette> {
    let json: Value = serde_json::from_str(&output[output.find('{')?..]).ok()?;
    let mode = if dark { "dark" } else { "light" };
    let palette: Palette = json["colors"]
        .as_object()?
        .iter()
        .filter(|(role, _)| *role != "source_color")
        .filter_map(|(role, v)| {
            let hex = v[mode]["color"].as_str().filter(|h| is_hex(h))?;
            Some((camel(role), hex.to_ascii_lowercase()))
        })
        .collect();
    (!palette.is_empty()).then_some(palette)
}

/// The desktop's flat `{"primary": "#e75a50", ...}` palette file.
pub fn parse_system(text: &str) -> Option<Palette> {
    let json: Value = serde_json::from_str(text).ok()?;
    let palette: Palette = json
        .as_object()?
        .iter()
        .filter_map(|(k, v)| Some((k.clone(), v.as_str().filter(|h| is_hex(h))?.to_ascii_lowercase())))
        .collect();
    let usable = palette.contains_key("primary") && (palette.contains_key("surface") || palette.contains_key("background"));
    usable.then_some(palette)
}

/// Fill roles a source lacks: derive the common ones from neighbours, then use the bundled palette.
pub fn complete(mut p: Palette, dark: bool) -> Palette {
    let derive = [
        ("surface", "background"),
        ("background", "surface"),
        ("onBackground", "onSurface"),
        ("surfaceDim", "surface"),
        ("surfaceBright", "surfaceContainerHighest"),
        ("inversePrimary", "primary"),
    ];
    for (role, from) in derive {
        if !p.contains_key(role) {
            if let Some(v) = p.get(from).cloned() {
                p.insert(role.to_owned(), v);
            }
        }
    }
    for (role, v) in fallback(dark) {
        p.entry(role).or_insert(v);
    }
    p
}

/// Perceived brightness below 0.5 counts as dark.
pub fn is_dark(p: &Palette) -> bool {
    let hex = p.get("surface").or_else(|| p.get("background"));
    let Some(h) = hex.filter(|h| is_hex(h)) else {
        return true;
    };
    let ch = |i: usize| f64::from(u8::from_str_radix(&h[i..i + 2], 16).unwrap_or(0));
    (0.299 * ch(1) + 0.587 * ch(3) + 0.114 * ch(5)) / 255.0 < 0.5
}

pub fn preset_seed(id: &str) -> &'static str {
    PRESETS.iter().find(|(n, _)| *n == id).map_or(DEFAULT_SEED, |(_, seed)| seed)
}

/// `~/.cache/ryoku/colors.json`, the palette the Ryoku shell daemon writes from Matugen.
pub fn system_palette_path() -> PathBuf {
    let cache = std::env::var_os("XDG_CACHE_HOME")
        .map(PathBuf::from)
        .filter(|p| p.is_absolute())
        .or_else(|| std::env::var_os("HOME").map(|h| PathBuf::from(h).join(".cache")))
        .unwrap_or_else(|| PathBuf::from(".cache"));
    cache.join("ryoku").join("colors.json")
}

/// A Matugen config with no templates. Running Matugen with the user's own config would
/// re-theme their whole desktop, so the app always passes this one plus `--dry-run`.
fn empty_matugen_config() -> Option<PathBuf> {
    let path = std::env::temp_dir().join(format!("bamboo-matugen-empty-{}.toml", std::process::id()));
    if !path.exists() {
        fs::write(&path, "[config]\n\n[templates]\n").ok()?;
    }
    Some(path)
}

/// Ask Matugen for a palette from `seed`. `None` if Matugen is missing or fails.
pub fn generate(seed: &str, dark: bool, scheme: &str) -> Option<Palette> {
    if !is_hex(seed) || !SCHEMES.contains(&scheme) || !sys::command_exists("matugen") {
        return None;
    }
    let config = empty_matugen_config()?;
    let args = [
        "--config",
        config.to_str()?,
        "--dry-run",
        "--type",
        scheme,
        "--mode",
        if dark { "dark" } else { "light" },
        "--json",
        "hex",
        "color",
        "hex",
        seed,
    ];
    let out = sys::run("matugen", &args, Some(std::time::Duration::from_secs(10))).filter(|o| o.success)?;
    parse_matugen(&out.stdout, dark)
}

fn effective_source(s: &Settings, system_exists: bool) -> Source {
    match s.theme_source.as_str() {
        "system" => Source::System,
        "seed" if is_hex(&s.theme_seed) => Source::Seed,
        "preset" => Source::Preset,
        "auto" if system_exists => Source::System,
        _ => Source::Preset,
    }
}

/// Resolve the settings to a complete palette. Never fails: every path ends at the bundled
/// fallback, so the UI always has colours.
pub fn resolve(s: &Settings) -> Theme {
    let dark_pref = s.theme_mode != "light";
    let path = system_palette_path();
    let source = effective_source(s, path.is_file());

    if source == Source::System {
        if let Some(p) = fs::read_to_string(&path).ok().and_then(|t| parse_system(&t)) {
            let dark = is_dark(&p);
            return Theme { palette: complete(p, dark), dark, source };
        }
    }

    let seed = if source == Source::Seed { s.theme_seed.as_str() } else { preset_seed(&s.theme) };
    let palette = generate(seed, dark_pref, &s.theme_scheme).unwrap_or_else(|| fallback(dark_pref));
    let source = if source == Source::Seed { Source::Seed } else { Source::Preset };
    Theme { palette: complete(palette, dark_pref), dark: dark_pref, source }
}

#[cfg(test)]
mod tests {
    use super::*;

    const MATUGEN: &str = include_str!("../tests/fixtures/matugen_color.json");
    const SYSTEM: &str = include_str!("../tests/fixtures/system_colors.json");

    #[test]
    fn matugen_output_becomes_camel_case_roles() {
        let dark = parse_matugen(MATUGEN, true).unwrap();
        let light = parse_matugen(MATUGEN, false).unwrap();
        assert_eq!(dark["primary"], "#d2bcfd");
        assert_eq!(light["primary"], "#67548e");
        assert!(dark.contains_key("surfaceContainerHighest"));
        assert!(dark.contains_key("onPrimaryContainer"));
        assert!(!dark.contains_key("sourceColor"));
    }

    #[test]
    fn matugen_output_with_leading_noise_parses() {
        assert!(parse_matugen(&format!("some log line\n{MATUGEN}"), true).is_some());
        assert!(parse_matugen("no json here", true).is_none());
    }

    #[test]
    fn system_file_parses_and_is_completed() {
        let p = parse_system(SYSTEM).unwrap();
        assert_eq!(p["primary"], "#e75a50");
        assert!(!p.contains_key("surfaceDim"));
        let dark = is_dark(&p);
        assert!(dark);
        let full = complete(p, dark);
        assert_eq!(full["surfaceDim"], full["surface"], "derived from the file, not the fallback");
        assert_eq!(full["inversePrimary"], "#e75a50");
        assert_eq!(full["primary"], "#e75a50", "file values are never overwritten");
    }

    #[test]
    fn unusable_system_files_are_rejected() {
        assert!(parse_system("{}").is_none());
        assert!(parse_system(r##"{"primary":"red","surface":"#000000"}"##).is_none());
        assert!(parse_system("[1]").is_none());
        assert!(parse_system("garbage").is_none());
    }

    #[test]
    fn fallback_is_complete_for_both_modes() {
        for dark in [true, false] {
            let p = fallback(dark);
            for role in ["primary", "onPrimary", "surface", "onSurface", "surfaceContainerLow", "outline", "error"] {
                assert!(is_hex(&p[role]), "{role}");
            }
            assert_eq!(is_dark(&p), dark);
        }
    }

    #[test]
    fn hex_validation() {
        assert!(is_hex("#7c3aed"));
        assert!(!is_hex("7c3aed"));
        assert!(!is_hex("#7c3ae"));
        assert!(!is_hex("#7c3aeg"));
    }

    #[test]
    fn presets_map_to_seeds() {
        assert_eq!(preset_seed("theme-catppuccin"), "#cba6f7");
        assert_eq!(preset_seed("theme-unknown"), DEFAULT_SEED);
    }

    #[test]
    fn auto_follows_the_system_file_only_when_present() {
        let s = Settings::default();
        assert_eq!(effective_source(&s, true), Source::System);
        assert_eq!(effective_source(&s, false), Source::Preset);
        let seeded = Settings { theme_source: "seed".into(), theme_seed: "#112233".into(), ..Settings::default() };
        assert_eq!(effective_source(&seeded, true), Source::Seed);
        let bad_seed = Settings { theme_source: "seed".into(), theme_seed: "nope".into(), ..Settings::default() };
        assert_eq!(effective_source(&bad_seed, true), Source::Preset);
    }

    #[test]
    fn generate_rejects_bad_arguments() {
        assert!(generate("--help", true, "scheme-tonal-spot").is_none());
        assert!(generate("#7c3aed", true, "--config=/etc/passwd").is_none());
    }

    #[test]
    fn matugen_generate_matches_fixture_when_installed() {
        if !sys::command_exists("matugen") {
            return;
        }
        let p = generate("#7c3aed", true, "scheme-tonal-spot").unwrap();
        assert_eq!(p["primary"], "#d2bcfd");
    }
}

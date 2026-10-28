//! Palette handling: Material You roles from Matugen, the desktop's own palette file, or a
//! built-in fallback. Pure data; the QML side only receives the resulting role -> hex map.

use std::collections::BTreeMap;
use std::fs;
use std::path::PathBuf;

use serde_json::Value;

use crate::settings::Settings;

/// Role name (`surfaceContainerHigh`) to `#rrggbb`.
pub type Palette = BTreeMap<String, String>;

const FALLBACK_DARK: &str = include_str!("theme/fallback_dark.json");
const FALLBACK_LIGHT: &str = include_str!("theme/fallback_light.json");

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

/// Material You palette from a source colour, computed in-process with the same library
/// Matugen is built on, so it matches `matugen color hex` without needing the binary.
/// `primary`/`secondary`/`tertiary` override the roles' own colours (empty or invalid = derived).
pub fn generate(opts: &GenOptions, dark: bool) -> Option<Palette> {
    use material_colors::color::Rgb;
    use material_colors::theme::ThemeBuilder;

    let rgb = |h: &str| is_hex(h).then(|| h.parse::<Rgb>().ok()).flatten();
    let mut builder = ThemeBuilder::with_source(rgb(&opts.seed)?).variant(variant(&opts.scheme));
    if let Some(c) = rgb(&opts.secondary) {
        builder = builder.secondary(c);
    }
    if let Some(c) = rgb(&opts.tertiary) {
        builder = builder.tertiary(c);
    }
    let theme = builder.build();
    let scheme = if dark { theme.schemes.dark } else { theme.schemes.light };
    let mut palette = scheme_to_palette(&scheme);
    if !dark {
        match_matugen_light(&mut palette);
    }
    Some(palette)
}

/// The library returns tone 30 for the light `on*Container` roles; the Material spec, and so
/// Matugen, uses tone 10. The `on*Fixed` roles already hold that tone for the same palette
/// (so they follow secondary/tertiary overrides); error is the fixed M3 constant.
fn match_matugen_light(p: &mut Palette) {
    for family in ["Primary", "Secondary", "Tertiary"] {
        if let Some(tone10) = p.get(&format!("on{family}Fixed")).cloned() {
            p.insert(format!("on{family}Container"), tone10);
        }
    }
    p.insert("onErrorContainer".into(), "#410002".into());
}

/// Inputs for [`generate`].
#[derive(Debug, Clone, Default)]
pub struct GenOptions {
    pub seed: String,
    pub secondary: String,
    pub tertiary: String,
    /// Matugen scheme name, e.g. `scheme-tonal-spot`.
    pub scheme: String,
}

/// Schemes the built-in generator reproduces exactly as `matugen` does (checked on several
/// seeds in both modes). `fidelity`, `vibrant`, `content` and `monochrome` differ in the library,
/// so they are not offered.
pub const SCHEMES: [&str; 5] =
    ["scheme-tonal-spot", "scheme-expressive", "scheme-fruit-salad", "scheme-neutral", "scheme-rainbow"];

/// Unknown or unsupported names use `tonal-spot`, Matugen's default.
fn variant(scheme: &str) -> material_colors::dynamic_color::Variant {
    use material_colors::dynamic_color::Variant::*;
    match scheme {
        "scheme-expressive" => Expressive,
        "scheme-fruit-salad" => FruitSalad,
        "scheme-neutral" => Neutral,
        "scheme-rainbow" => Rainbow,
        _ => TonalSpot,
    }
}

fn scheme_to_palette(s: &material_colors::scheme::Scheme) -> Palette {
    let mut p = Palette::new();
    macro_rules! roles {
        ($($f:ident),* $(,)?) => {$(
            p.insert(camel(stringify!($f)), format!("#{:02x}{:02x}{:02x}", s.$f.red, s.$f.green, s.$f.blue));
        )*};
    }
    roles!(primary, on_primary, primary_container, on_primary_container, inverse_primary, primary_fixed, primary_fixed_dim, on_primary_fixed, on_primary_fixed_variant, secondary, on_secondary, secondary_container, on_secondary_container, secondary_fixed, secondary_fixed_dim, on_secondary_fixed, on_secondary_fixed_variant, tertiary, on_tertiary, tertiary_container, on_tertiary_container, tertiary_fixed, tertiary_fixed_dim, on_tertiary_fixed, on_tertiary_fixed_variant, error, on_error, error_container, on_error_container, surface_dim, surface, surface_tint, surface_bright, surface_container_lowest, surface_container_low, surface_container, surface_container_high, surface_container_highest, on_surface, on_surface_variant, outline, outline_variant, inverse_surface, inverse_on_surface, surface_variant, background, on_background, shadow, scrim);
    p
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

    let from_seed = source == Source::Seed;
    let opts = GenOptions {
        seed: if from_seed { s.theme_seed.clone() } else { preset_seed(&s.theme).to_owned() },
        secondary: if from_seed { s.theme_secondary.clone() } else { String::new() },
        tertiary: if from_seed { s.theme_tertiary.clone() } else { String::new() },
        scheme: s.theme_scheme.clone(),
    };
    let palette = generate(&opts, dark_pref).unwrap_or_else(|| fallback(dark_pref));
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

    fn opts(seed: &str) -> GenOptions {
        GenOptions { seed: seed.into(), scheme: "scheme-tonal-spot".into(), ..GenOptions::default() }
    }

    #[test]
    fn builtin_generator_matches_matugen_output() {
        for dark in [true, false] {
            let ours = generate(&opts("#7c3aed"), dark).unwrap();
            let theirs = parse_matugen(MATUGEN, dark).unwrap();
            assert_eq!(ours, theirs, "dark={dark}");
        }
    }

    #[test]
    fn generator_rejects_bad_input() {
        assert!(generate(&opts("--help"), true).is_none());
        assert!(generate(&opts(""), true).is_none());
    }

    #[test]
    fn unsupported_scheme_uses_tonal_spot() {
        let base = generate(&opts("#7c3aed"), true).unwrap();
        for name in ["scheme-vibrant", "scheme-smart-ish", ""] {
            let p = generate(&GenOptions { scheme: name.into(), ..opts("#7c3aed") }, true).unwrap();
            assert_eq!(p, base, "{name}");
        }
        let expressive = generate(&GenOptions { scheme: "scheme-expressive".into(), ..opts("#7c3aed") }, true).unwrap();
        assert_ne!(expressive["primary"], base["primary"]);
        assert!(SCHEMES.contains(&"scheme-expressive"));
    }

    #[test]
    fn different_seeds_give_different_palettes() {
        let a = generate(&opts("#7c3aed"), true).unwrap();
        let b = generate(&opts("#fe8019"), true).unwrap();
        assert_ne!(a["primary"], b["primary"]);
        assert!(is_hex(&b["surfaceContainerHigh"]));
    }

    #[test]
    fn secondary_override_changes_only_the_secondary_family() {
        let base = generate(&opts("#7c3aed"), true).unwrap();
        let over = generate(&GenOptions { secondary: "#00aa55".into(), ..opts("#7c3aed") }, true).unwrap();
        assert_eq!(base["primary"], over["primary"]);
        assert_ne!(base["secondary"], over["secondary"]);
    }

    #[test]
    fn every_preset_resolves_without_matugen() {
        for (id, _) in PRESETS {
            let s = Settings { theme: id.into(), theme_source: "preset".into(), ..Settings::default() };
            let t = resolve(&s);
            assert_eq!(t.source, Source::Preset);
            assert!(is_hex(&t.palette["primary"]), "{id}");
        }
        let nord = resolve(&Settings { theme: "theme-nord".into(), theme_source: "preset".into(), ..Settings::default() });
        let gruv = resolve(&Settings { theme: "theme-gruvbox".into(), theme_source: "preset".into(), ..Settings::default() });
        assert_ne!(nord.palette["primary"], gruv.palette["primary"]);
    }
}

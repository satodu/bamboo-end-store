//! Translations. English phrases are the keys, as in the PHP app's `lang/*.json`; a missing key
//! shows the English text. The four files are embedded and share one key set (see the tests).

use std::collections::HashMap;
use std::sync::OnceLock;

/// `(code, native name)` in the order the settings screen lists them.
pub const LANGUAGES: [(&str, &str); 4] = [("pt", "Português"), ("en", "English"), ("es", "Español"), ("ru", "Русский")];

const EN: &str = include_str!("i18n/en.json");
const PT: &str = include_str!("i18n/pt.json");
const ES: &str = include_str!("i18n/es.json");
const RU: &str = include_str!("i18n/ru.json");

type Table = HashMap<String, String>;

fn parse(text: &str) -> Table {
    serde_json::from_str(text).expect("bundled translation file is valid JSON")
}

/// The table for a language code; anything unknown gets English.
pub fn table(lang: &str) -> &'static Table {
    static TABLES: OnceLock<[Table; 4]> = OnceLock::new();
    let t = TABLES.get_or_init(|| [parse(EN), parse(PT), parse(ES), parse(RU)]);
    match lang {
        "pt" => &t[1],
        "es" => &t[2],
        "ru" => &t[3],
        _ => &t[0],
    }
}

/// Setting value (`system`, `pt`, `en`, `es`, `ru`) to a language code. `system` follows the
/// environment locale like the PHP app: `LANG`, then `LANGUAGE`; anything else is English.
pub fn resolve(setting: &str, env_lang: Option<&str>) -> &'static str {
    let code = match setting {
        "system" | "" => env_lang.unwrap_or("en"),
        other => other,
    };
    ["pt", "es", "ru"].into_iter().find(|p| code.starts_with(p)).unwrap_or("en")
}

/// The environment's locale string: `LC_ALL`, `LC_MESSAGES`, `LANG`, then `LANGUAGE`.
pub fn env_locale() -> Option<String> {
    ["LC_ALL", "LC_MESSAGES", "LANG", "LANGUAGE"]
        .into_iter()
        .filter_map(|k| std::env::var(k).ok())
        .find(|v| !v.is_empty() && v != "C" && v != "POSIX")
}

pub fn language_for(setting: &str) -> &'static str {
    resolve(setting, env_locale().as_deref())
}

pub fn tr(lang: &str, key: &str) -> String {
    table(lang).get(key).cloned().unwrap_or_else(|| key.to_owned())
}

/// Replace each `%s` with the next argument. Extra placeholders become empty.
pub fn format(template: &str, args: &[&str]) -> String {
    let mut out = String::with_capacity(template.len());
    let mut it = args.iter();
    let mut rest = template;
    while let Some(i) = rest.find("%s") {
        out.push_str(&rest[..i]);
        out.push_str(it.next().copied().unwrap_or(""));
        rest = &rest[i + 2..];
    }
    out.push_str(rest);
    out
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::BTreeSet;
    use std::fs;
    use std::path::Path;

    fn keys(lang: &str) -> BTreeSet<&'static String> {
        table(lang).keys().collect()
    }

    #[test]
    fn every_language_has_the_same_keys() {
        let en = keys("en");
        for lang in ["pt", "es", "ru"] {
            let k = keys(lang);
            let missing: Vec<_> = en.difference(&k).collect();
            let extra: Vec<_> = k.difference(&en).collect();
            assert!(missing.is_empty() && extra.is_empty(), "{lang}: missing {missing:?}, extra {extra:?}");
        }
    }

    #[test]
    fn placeholders_match_the_english_text() {
        for lang in ["pt", "es", "ru"] {
            for (key, value) in table(lang) {
                let want = key.matches("%s").count();
                let have = value.matches("%s").count();
                // Old PHP keys like `install_done` carry the placeholder only in their values.
                let want = if key.contains(' ') || key.contains('%') { want } else { table("en")[key].matches("%s").count() };
                assert_eq!(have, want, "{lang}: {key:?} -> {value:?}");
            }
        }
    }

    #[test]
    fn language_resolution() {
        assert_eq!(resolve("pt", Some("en_US.UTF-8")), "pt");
        assert_eq!(resolve("system", Some("pt_BR.UTF-8")), "pt");
        assert_eq!(resolve("system", Some("ru_RU.UTF-8")), "ru");
        assert_eq!(resolve("system", Some("es")), "es");
        assert_eq!(resolve("system", Some("de_DE")), "en");
        assert_eq!(resolve("system", None), "en");
        assert_eq!(resolve("klingon", None), "en");
    }

    #[test]
    fn lookups_and_fallback() {
        assert_eq!(tr("pt", "Settings"), "Configurações");
        assert_eq!(tr("ru", "Settings"), "Настройки");
        assert_eq!(tr("en", "Settings"), "Settings");
        assert_eq!(tr("pt", "not a real key"), "not a real key");
        assert_eq!(tr("xx", "Settings"), "Settings");
    }

    #[test]
    fn formatting() {
        assert_eq!(format("%s failed (exit %s)", &["x", "3"]), "x failed (exit 3)");
        assert_eq!(format("Результатов: %s", &["5"]), "Результатов: 5");
        assert_eq!(format("%s and %s", &["a"]), "a and ");
        assert_eq!(format("no placeholders", &["a"]), "no placeholders");
    }

    /// Every `Tr.t("literal")` / `Tr.f("literal", ...)` in the QML must be a real key, so a typo
    /// fails here instead of silently showing English in every language.
    #[test]
    fn qml_keys_exist() {
        let dir = Path::new(env!("CARGO_MANIFEST_DIR")).join("../app/qml");
        let mut used = BTreeSet::new();
        for entry in fs::read_dir(&dir).unwrap().filter_map(Result::ok) {
            let path = entry.path();
            if path.extension().is_none_or(|e| e != "qml") {
                continue;
            }
            let text = fs::read_to_string(&path).unwrap();
            for marker in ["Tr.t(\"", "Tr.f(\""] {
                let mut rest = text.as_str();
                while let Some(i) = rest.find(marker) {
                    rest = &rest[i + marker.len()..];
                    let end = rest.find('"').unwrap();
                    used.insert((path.file_name().unwrap().to_string_lossy().into_owned(), rest[..end].replace("\\\"", "\"")));
                }
            }
        }
        assert!(used.len() > 20, "found only {} Tr keys: is the scan looking in the right place?", used.len());
        let en = table("en");
        let missing: Vec<_> = used.iter().filter(|(_, k)| !en.contains_key(k)).collect();
        assert!(missing.is_empty(), "keys missing from the language files: {missing:?}");
    }
}

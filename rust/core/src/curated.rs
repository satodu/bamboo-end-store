//! Curated software categories from the EndeavourOS quickstart list. Port of
//! `App\Services\EndeavourOSCuratedService`.

use std::time::Duration;

use serde::Serialize;

use crate::http::get_text;

pub const QUICKSTART_URL: &str = "https://raw.githubusercontent.com/endeavouros-team/eos-quickstart/main/eos-quickstart.conf";

/// Official EndeavourOS tools shown on the home page. Only the ones the system has are listed.
pub const ESSENTIALS: [&str; 6] =
    ["welcome", "eos-update-notifier", "eos-log-tool", "eos-quickstart", "nvidia-inst", "eos-packagelist"];

#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
pub struct Category {
    /// Name as in the list, e.g. `Image Viewers & Managers`; also the translation key.
    pub name: String,
    /// `image_viewers_and_managers`
    pub slug: String,
    /// Translation key of the description, e.g. `cat_desc_image_viewers_and_managers`.
    pub description_key: String,
    pub packages: Vec<String>,
}

/// Same slug rule as the PHP app: spaces to `_`, `&` to `and`, lower case.
pub fn slug(name: &str) -> String {
    name.replace(' ', "_").replace('&', "and").to_lowercase()
}

fn category(name: &str, packages: &[&str]) -> Category {
    let slug = slug(name);
    Category {
        name: name.to_owned(),
        description_key: format!("cat_desc_{slug}"),
        slug,
        packages: packages.iter().map(|p| (*p).to_owned()).collect(),
    }
}

/// Parse the quickstart file: `[Category]` sections of `package="description"` lines. `[General]`,
/// comments and empty names are skipped; categories without packages are dropped; sorted by name.
pub fn parse_quickstart(text: &str) -> Vec<Category> {
    let mut out: Vec<Category> = Vec::new();
    let mut current: Option<Category> = None;
    for line in text.lines() {
        let line = line.trim();
        if line.is_empty() || line.starts_with('#') || line.starts_with(';') {
            continue;
        }
        if let Some(name) = line.strip_prefix('[').and_then(|l| l.strip_suffix(']')) {
            out.extend(current.take().filter(|c| !c.packages.is_empty()));
            current = (name != "General").then(|| category(name, &[]));
            continue;
        }
        if let Some(cat) = current.as_mut() {
            let package = line.split('=').next().unwrap_or("").trim();
            if !package.is_empty() {
                cat.packages.push(package.to_owned());
            }
        }
    }
    out.extend(current.filter(|c| !c.packages.is_empty()));
    out.sort_by(|a, b| a.name.cmp(&b.name));
    out
}

/// Used when the list cannot be downloaded.
pub fn defaults() -> Vec<Category> {
    let mut v = vec![
        category("Browsers", &["firefox", "vivaldi", "torbrowser-launcher", "chromium", "falkon", "konqueror"]),
        category(
            "Development Tools",
            &["code", "geany", "gnome-builder", "intellij-idea-community-edition", "kdevelop", "netbeans", "pycharm-community-edition", "qtcreator"],
        ),
        category("Communications", &["hexchat", "konversation", "neochat", "pidgin", "polari", "telegram-desktop"]),
        category("Audio Players", &["audacious", "elisa", "lollypop", "rhythmbox", "strawberry"]),
        category("Video Players", &["celluloid", "dragon", "haruna", "smplayer", "totem", "vlc"]),
        category("Office", &["calligra", "libreoffice-fresh", "libreoffice-still", "onlyoffice-bin"]),
        category("Email", &["evolution", "geary", "kmail", "sylpheed", "thunderbird"]),
    ];
    v.sort_by(|a, b| a.name.cmp(&b.name));
    v
}

/// Download and parse the list, falling back to the built-in one.
pub fn fetch() -> Vec<Category> {
    get_text(QUICKSTART_URL, Duration::from_secs(10))
        .map(|t| parse_quickstart(&t))
        .filter(|c| !c.is_empty())
        .unwrap_or_else(defaults)
}

#[cfg(test)]
mod tests {
    use super::*;

    const CONF: &str = include_str!("../tests/fixtures/eos-quickstart.conf");

    #[test]
    fn parses_the_real_file() {
        let cats = parse_quickstart(CONF);
        assert_eq!(cats.len(), 16, "all sections except General");
        assert!(cats.iter().all(|c| c.name != "General" && !c.packages.is_empty()));
        let names: Vec<&str> = cats.iter().map(|c| c.name.as_str()).collect();
        let mut sorted = names.clone();
        sorted.sort();
        assert_eq!(names, sorted);
        let browsers = cats.iter().find(|c| c.name == "Browsers").unwrap();
        assert_eq!(browsers.packages, ["firefox", "vivaldi", "torbrowser-launcher", "chromium", "falkon", "konqueror"]);
        assert_eq!(browsers.description_key, "cat_desc_browsers");
    }

    #[test]
    fn slugs_match_the_php_rule() {
        assert_eq!(slug("Image Viewers & Managers"), "image_viewers_and_managers");
        assert_eq!(slug("Misc System Tools"), "misc_system_tools");
        let c = parse_quickstart(CONF).into_iter().find(|c| c.name.contains('&')).unwrap();
        assert_eq!(c.description_key, format!("cat_desc_{}", c.slug));
    }

    #[test]
    fn comments_and_empty_names_are_skipped() {
        let cats = parse_quickstart("[A]\n#hidden=\"x\"\nreal=\"y\"\n=\"z\"\n\n[Empty]\n\n[B]\nb1=\"d\"\n");
        assert_eq!(cats.len(), 2);
        assert_eq!(cats[0].packages, ["real"]);
        assert_eq!(cats[1].name, "B");
    }

    #[test]
    fn every_category_description_is_translated() {
        // The PHP app shipped a description for each of these in all four languages.
        for c in parse_quickstart(CONF) {
            for lang in ["en", "pt", "es", "ru"] {
                assert_ne!(crate::i18n::tr(lang, &c.description_key), c.description_key, "{lang}: {}", c.description_key);
            }
        }
    }

    #[test]
    fn defaults_are_usable() {
        let d = defaults();
        assert_eq!(d.len(), 7);
        assert!(d.iter().all(|c| !c.packages.is_empty()));
    }
}

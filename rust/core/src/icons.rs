//! Icons for packages that have none of their own. Port of the icon half of
//! `BaseManager` (`getAppId` / `getIcon`), made mostly local:
//!
//! 1. the installed `.desktop` files and the icon theme (instant, offline, exact);
//! 2. the PHP app's table of package -> Flathub app id (a URL the image loads lazily);
//! 3. in the details view only, a strict Flathub search.
//!
//! Flatpak and Bamboo Installs icons are decided in the UI; the Flathub search of the old app took
//! its first hit blindly, here a hit has to actually be that package.

use std::collections::HashMap;
use std::fs;
use std::path::{Path, PathBuf};
use std::sync::{Mutex, OnceLock};

use serde_json::Value;

use crate::{Package, PackageSource};

const FLATHUB_IDS: &str = include_str!("icons/flathub_ids.txt");
const SIZES: [&str; 8] = ["scalable", "512x512", "256x256", "128x128", "96x96", "64x64", "48x48", "32x32"];

/// URL of a Flathub app's icon.
pub fn flathub_icon_url(app_id: &str) -> String {
    format!("https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/{app_id}.png")
}

fn flathub_table() -> &'static HashMap<&'static str, &'static str> {
    static TABLE: OnceLock<HashMap<&'static str, &'static str>> = OnceLock::new();
    TABLE.get_or_init(|| {
        FLATHUB_IDS
            .lines()
            .filter(|l| !l.starts_with('#'))
            .filter_map(|l| l.split_once(' '))
            .collect()
    })
}

/// Flathub app id for a well-known package name.
pub fn flathub_id(name: &str) -> Option<&'static str> {
    flathub_table().get(name.trim().to_lowercase().as_str()).copied()
}

/// `foo-bin` and `foo-git` look for `foo`: the same suffixes the old Flathub search stripped, plus
/// the common `-fresh`, `-still` and `-desktop`.
fn name_variants(name: &str) -> Vec<String> {
    let lower = name.trim().to_lowercase();
    let mut out = vec![lower.clone()];
    for suffix in ["-bin", "-git", "-gtk", "-qt", "-launcher", "-community-edition", "-fresh", "-still", "-desktop", "-stable", "-appimage"] {
        if let Some(stem) = lower.strip_suffix(suffix) {
            if !stem.is_empty() {
                out.push(stem.to_owned());
            }
        }
    }
    out
}

/// Where to look: the user's and the system's icon and `.desktop` directories. Injectable for tests.
#[derive(Debug, Clone)]
pub struct IconDirs {
    pub applications: Vec<PathBuf>,
    pub icon_themes: Vec<PathBuf>,
    pub pixmaps: Vec<PathBuf>,
}

impl IconDirs {
    pub fn system() -> Self {
        let home = std::env::var_os("HOME").map(PathBuf::from);
        let data = std::env::var_os("XDG_DATA_HOME")
            .map(PathBuf::from)
            .filter(|p| p.is_absolute())
            .or_else(|| home.as_ref().map(|h| h.join(".local/share")));
        let mut applications = vec![PathBuf::from("/usr/share/applications")];
        let mut icon_themes = vec![PathBuf::from("/usr/share/icons/hicolor")];
        if let Some(d) = data {
            applications.push(d.join("applications"));
            icon_themes.push(d.join("icons/hicolor"));
        }
        if let Some(h) = home {
            icon_themes.push(h.join(".icons/hicolor"));
        }
        Self { applications, icon_themes, pixmaps: vec![PathBuf::from("/usr/share/pixmaps")] }
    }
}

/// Maps names an app goes by (its `.desktop` file stem, its executable, its window class) to its
/// `Icon=` value, and finds the file for an icon name.
pub struct IconIndex {
    dirs: IconDirs,
    by_name: HashMap<String, String>,
}

/// Split an `Exec=` line into words, keeping a double-quoted part (a path with spaces) together.
fn exec_words(exec: &str) -> Vec<String> {
    let mut words = Vec::new();
    let mut cur = String::new();
    let mut quoted = false;
    for c in exec.chars() {
        match c {
            '"' => quoted = !quoted,
            c if c.is_whitespace() && !quoted => {
                if !cur.is_empty() {
                    words.push(std::mem::take(&mut cur));
                }
            }
            c => cur.push(c),
        }
    }
    if !cur.is_empty() {
        words.push(cur);
    }
    words
}

/// The program of an `Exec=` line: skips `env`, `VAR=value` and options, keeps only the file name.
fn exec_program(exec: &str) -> Option<String> {
    exec_words(exec)
        .into_iter()
        .find(|w| w != "env" && !w.contains('=') && !w.starts_with('-'))
        .and_then(|w| Path::new(&w).file_name().map(|n| n.to_string_lossy().to_lowercase()))
        .filter(|n| !n.is_empty())
}

/// `Icon`, `Exec` and `StartupWMClass` of the `[Desktop Entry]` group.
fn read_desktop(text: &str) -> (Option<String>, Option<String>, Option<String>) {
    let (mut icon, mut exec, mut class) = (None, None, None);
    let mut in_entry = false;
    for line in text.lines() {
        let line = line.trim();
        if line.starts_with('[') {
            in_entry = line == "[Desktop Entry]";
            continue;
        }
        if !in_entry {
            continue;
        }
        if let Some((k, v)) = line.split_once('=') {
            let v = v.trim();
            match k.trim() {
                "Icon" if icon.is_none() && !v.is_empty() => icon = Some(v.to_owned()),
                "Exec" if exec.is_none() => exec = exec_program(v),
                "StartupWMClass" if class.is_none() && !v.is_empty() => class = Some(v.to_lowercase()),
                _ => {}
            }
        }
    }
    (icon, exec, class)
}

impl IconIndex {
    pub fn build(dirs: IconDirs) -> Self {
        let mut by_name = HashMap::new();
        for dir in &dirs.applications {
            let Ok(entries) = fs::read_dir(dir) else { continue };
            for entry in entries.filter_map(Result::ok) {
                let path = entry.path();
                if path.extension().is_none_or(|e| e != "desktop") {
                    continue;
                }
                let Ok(text) = fs::read_to_string(&path) else { continue };
                let (icon, exec, class) = read_desktop(&text);
                let Some(icon) = icon else { continue };
                let stem = path.file_stem().map(|s| s.to_string_lossy().to_lowercase());
                // Later directories (the user's own) win over the system ones.
                for key in [stem, exec, class].into_iter().flatten() {
                    by_name.insert(key, icon.clone());
                }
            }
        }
        Self { dirs, by_name }
    }

    /// The file for an icon name (or absolute path), preferring scalable and large sizes.
    pub fn find_file(&self, icon: &str) -> Option<PathBuf> {
        let direct = Path::new(icon);
        if direct.is_absolute() {
            return direct.is_file().then(|| direct.to_path_buf());
        }
        for theme in &self.dirs.icon_themes {
            for size in SIZES {
                for ext in ["svg", "png"] {
                    let p = theme.join(size).join("apps").join(format!("{icon}.{ext}"));
                    if p.is_file() {
                        return Some(p);
                    }
                }
            }
        }
        for pix in &self.dirs.pixmaps {
            for ext in ["svg", "png"] {
                let p = pix.join(format!("{icon}.{ext}"));
                if p.is_file() {
                    return Some(p);
                }
            }
        }
        None
    }

    /// The icon file of an installed app, found through its `.desktop` file or an icon named like it.
    pub fn lookup(&self, package: &str) -> Option<PathBuf> {
        for candidate in name_variants(package) {
            if let Some(file) = self.by_name.get(&candidate).and_then(|icon| self.find_file(icon)) {
                return Some(file);
            }
            if let Some(file) = self.find_file(&candidate) {
                return Some(file);
            }
        }
        None
    }
}

fn system_index() -> &'static IconIndex {
    static INDEX: OnceLock<IconIndex> = OnceLock::new();
    INDEX.get_or_init(|| IconIndex::build(IconDirs::system()))
}

/// Pick the Flathub search hit that really is `name`: its app id ends in `.name`, or its display
/// name is `name`. The old app took `hits[0]` whatever it was, so "yay" could get any icon.
pub fn pick_flathub_hit(search_json: &str, name: &str) -> Option<String> {
    let wanted = name_variants(name);
    let v: Value = serde_json::from_str(search_json).ok()?;
    v["hits"].as_array()?.iter().find_map(|hit| {
        let id = hit["app_id"].as_str().or_else(|| hit["id"].as_str())?.replace('_', ".");
        let last = id.rsplit('.').next()?.to_lowercase();
        let display = hit["name"].as_str().map(str::to_lowercase);
        let matches = wanted.iter().any(|w| *w == last || display.as_deref() == Some(w.as_str()));
        matches.then_some(id)
    })
}

/// Flathub app id of a package: the table first, then a strict Flathub search. The search is a
/// network call (4 s at most): only for the details view, never for lists.
pub fn flathub_app_id(name: &str) -> Option<String> {
    if let Some(id) = name_variants(name).iter().find_map(|n| flathub_id(n)) {
        return Some(id.to_owned());
    }
    let query = name_variants(name).pop()?;
    let body = crate::http::post_json(
        "https://flathub.org/api/v2/search",
        &serde_json::json!({ "query": query }),
        std::time::Duration::from_secs(4),
    )?;
    pick_flathub_hit(&body, name)
}

/// The icon URL (`file://...` or `https://...`) for a pacman or AUR package, or `""`.
pub fn icon_url(pkg: &Package) -> String {
    static CACHE: OnceLock<Mutex<HashMap<String, String>>> = OnceLock::new();
    if !matches!(pkg.source, PackageSource::Pacman | PackageSource::Aur) {
        return String::new();
    }
    let cache = CACHE.get_or_init(|| Mutex::new(HashMap::new()));
    if let Some(hit) = cache.lock().unwrap().get(&pkg.name) {
        return hit.clone();
    }
    let url = system_index()
        .lookup(&pkg.name)
        .map(|p| format!("file://{}", p.display()))
        .or_else(|| name_variants(&pkg.name).iter().find_map(|n| flathub_id(n)).map(flathub_icon_url))
        .unwrap_or_default();
    cache.lock().unwrap().insert(pkg.name.clone(), url.clone());
    url
}

/// The packages as the JSON the UI reads, each with its `icon_url`.
pub fn packages_json(items: &[Package]) -> String {
    let values: Vec<Value> = items
        .iter()
        .map(|p| {
            let mut v = serde_json::to_value(p).unwrap_or(Value::Null);
            if let Value::Object(map) = &mut v {
                map.insert("icon_url".into(), Value::String(icon_url(p)));
            }
            v
        })
        .collect();
    serde_json::to_string(&values).unwrap_or_else(|_| "[]".into())
}

#[cfg(test)]
mod tests {
    use super::*;

    const FIREFOX_SEARCH: &str = include_str!("../tests/fixtures/flathub_search_firefox.json");

    fn sandbox(tag: &str) -> (PathBuf, IconDirs) {
        let root = std::env::temp_dir().join(format!("bamboo-icons-{}-{tag}", std::process::id()));
        let _ = fs::remove_dir_all(&root);
        for d in ["apps", "hicolor/scalable/apps", "hicolor/48x48/apps", "pixmaps"] {
            fs::create_dir_all(root.join(d)).unwrap();
        }
        let dirs = IconDirs {
            applications: vec![root.join("apps")],
            icon_themes: vec![root.join("hicolor")],
            pixmaps: vec![root.join("pixmaps")],
        };
        (root, dirs)
    }

    fn desktop(root: &Path, file: &str, body: &str) {
        fs::write(root.join("apps").join(file), format!("[Desktop Entry]\nType=Application\n{body}\n[Desktop Action x]\nIcon=wrong\n")).unwrap();
    }

    #[test]
    fn the_flathub_table_is_ported_whole() {
        assert_eq!(flathub_table().len(), 98);
        assert_eq!(flathub_id("firefox"), Some("org.mozilla.firefox"));
        assert_eq!(flathub_id("  FireFox "), Some("org.mozilla.firefox"));
        assert_eq!(flathub_id("not-a-package"), None);
        assert_eq!(flathub_icon_url("a.b"), "https://dl.flathub.org/repo/appstream/x86_64/icons/128x128/a.b.png");
    }

    #[test]
    fn variants_strip_build_suffixes() {
        assert_eq!(name_variants("brave-bin"), ["brave-bin", "brave"]);
        assert_eq!(name_variants("Foo-Git"), ["foo-git", "foo"]);
        assert_eq!(name_variants("-bin"), ["-bin"], "never an empty name");
    }

    #[test]
    fn exec_lines_yield_the_program() {
        assert_eq!(exec_program("env GDK_BACKEND=x11 /usr/bin/Foo --new %U").as_deref(), Some("foo"));
        assert_eq!(exec_program("\"/opt/my app/bin/bar\" %f").as_deref(), Some("bar"));
        assert_eq!(exec_program("--oops"), None);
    }

    #[test]
    fn finds_an_icon_through_the_desktop_file() {
        let (root, dirs) = sandbox("desktop");
        // the package is `foo-bin` but the desktop file and icon belong to `fooapp`
        desktop(&root, "fooapp.desktop", "Name=Foo\nExec=foo --gui\nIcon=fooapp-icon\n");
        fs::write(root.join("hicolor/48x48/apps/fooapp-icon.png"), "png").unwrap();
        let idx = IconIndex::build(dirs);
        let by_exec = idx.lookup("foo-bin").unwrap();
        assert!(by_exec.ends_with("48x48/apps/fooapp-icon.png"));
        assert!(idx.lookup("fooapp").is_some());
        assert!(idx.lookup("unrelated").is_none());
        let _ = fs::remove_dir_all(root);
    }

    #[test]
    fn prefers_scalable_and_reads_only_the_main_group() {
        let (root, dirs) = sandbox("scalable");
        desktop(&root, "bar.desktop", "Name=Bar\nExec=bar\nIcon=bar\n");
        fs::write(root.join("hicolor/48x48/apps/bar.png"), "png").unwrap();
        fs::write(root.join("hicolor/scalable/apps/bar.svg"), "svg").unwrap();
        let idx = IconIndex::build(dirs);
        assert!(idx.lookup("bar").unwrap().ends_with("scalable/apps/bar.svg"));
        assert!(idx.find_file("wrong").is_none(), "the [Desktop Action] Icon must not be used");
        let _ = fs::remove_dir_all(root);
    }

    #[test]
    fn falls_back_to_pixmaps_and_absolute_paths() {
        let (root, dirs) = sandbox("pix");
        fs::write(root.join("pixmaps/baz.png"), "png").unwrap();
        let abs = root.join("elsewhere.png");
        fs::write(&abs, "png").unwrap();
        desktop(&root, "qux.desktop", &format!("Exec=qux\nIcon={}\n", abs.display()));
        let idx = IconIndex::build(dirs);
        assert!(idx.lookup("baz").unwrap().ends_with("pixmaps/baz.png"));
        assert_eq!(idx.lookup("qux").unwrap(), abs);
        let _ = fs::remove_dir_all(root);
    }

    #[test]
    fn a_flathub_hit_must_really_be_the_package() {
        assert_eq!(pick_flathub_hit(FIREFOX_SEARCH, "firefox").as_deref(), Some("org.mozilla.firefox"));
        assert_eq!(pick_flathub_hit(FIREFOX_SEARCH, "firefox-bin").as_deref(), Some("org.mozilla.firefox"));
        // none of the hits is "yay": the old app would have used hits[0]
        assert_eq!(pick_flathub_hit(FIREFOX_SEARCH, "yay"), None);
        assert_eq!(pick_flathub_hit("not json", "firefox"), None);
    }

    #[test]
    fn only_pacman_and_aur_packages_get_a_url_here() {
        let mut p = Package {
            repo: String::new(),
            name: "firefox".into(),
            display_name: String::new(),
            version: String::new(),
            description: String::new(),
            source: PackageSource::Flatpak,
            installed: false,
        };
        assert_eq!(icon_url(&p), "");
        p.source = PackageSource::Pacman;
        assert!(!icon_url(&p).is_empty(), "firefox is in the Flathub table at least");
        let json = packages_json(&[p]);
        assert!(json.contains("\"icon_url\":\""));
    }
}

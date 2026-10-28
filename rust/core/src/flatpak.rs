use std::collections::HashSet;
use std::time::Duration;

use serde_json::{json, Value};

use crate::aur::format_utc;
use crate::http::{get_text, post_json};
use crate::manager::PackageManager;
use crate::sys::{self, shell_quote};
use crate::{Details, Package, PackageSource};

const APPSTREAM: &str = "https://flathub.org/api/v2/appstream/";
const SEARCH: &str = "https://flathub.org/api/v2/search";
const API_RESULTS: usize = 20;
const COLUMNS: &str = "--columns=name,application,version,description";

#[derive(Default)]
pub struct FlatpakManager;

impl FlatpakManager {
    pub fn available(&self) -> bool {
        sys::command_exists("flatpak")
    }

    pub fn installed_flatpaks(&self) -> Vec<Package> {
        if !self.available() {
            return Vec::new();
        }
        sys::run("flatpak", &["list", COLUMNS], None)
            .filter(|o| o.success)
            .map(|o| parse_table(&o.stdout, true))
            .unwrap_or_default()
    }

    fn installed_ids(&self) -> HashSet<String> {
        self.installed_flatpaks().into_iter().map(|p| p.name).collect()
    }

    /// Flathub appstream metadata for one app id: details plus up to 3 screenshot URLs.
    pub fn api_details(&self, app_id: &str) -> Option<(Details, Vec<String>)> {
        let body = get_text(&format!("{APPSTREAM}{app_id}"), Duration::from_secs(10))?;
        parse_appstream(&body)
    }

    pub fn screenshots(&self, app_id: &str) -> Vec<String> {
        self.api_details(app_id).map(|(_, s)| s).unwrap_or_default()
    }

    fn api_search(&self, query: &str, installed: &HashSet<String>) -> Vec<Package> {
        let body = post_json(SEARCH, &json!({ "query": query }), Duration::from_secs(10));
        body.map(|b| parse_api_search(&b, installed)).unwrap_or_default()
    }
}

impl PackageManager for FlatpakManager {
    fn search(&self, query: &str) -> Vec<Package> {
        if query.is_empty() || !self.available() {
            return Vec::new();
        }
        let installed = self.installed_ids();

        let mut merged = sys::run("flatpak", &["search", COLUMNS, query], None)
            .filter(|o| o.success)
            .map(|o| parse_table(&o.stdout, false))
            .unwrap_or_default();
        for p in &mut merged {
            p.installed = installed.contains(&p.name);
        }

        let known: HashSet<String> = merged.iter().map(|p| p.name.clone()).collect();
        merged.extend(self.api_search(query, &installed).into_iter().filter(|p| !known.contains(&p.name)));
        merged
    }

    fn details(&self, name: &str) -> Option<Details> {
        let api = self.api_details(name);

        let mut installed_size = None;
        let mut download_size = None;
        if let Some(o) = sys::run("flatpak", &["info", name], None).filter(|o| o.success) {
            (installed_size, download_size) = parse_sizes(&o.stdout);
        }

        let remote = if installed_size.is_none() || download_size.is_none() || api.is_none() {
            sys::run("flatpak", &["remote-info", "flathub", name], None).filter(|o| o.success)
        } else {
            None
        };
        if let Some(o) = &remote {
            let (i, d) = parse_sizes(&o.stdout);
            installed_size = installed_size.or(i);
            download_size = download_size.or(d);
        }

        let mut details = match api {
            Some((d, _)) => d,
            None => parse_remote_info(&remote?.stdout, name),
        };
        if let Some(v) = installed_size {
            details.insert("Installed Size".into(), v);
        }
        if let Some(v) = download_size {
            details.insert("Download Size".into(), v);
        }
        details.insert("is_installed".into(), self.is_installed(name).to_string());
        Some(details)
    }

    fn is_installed(&self, name: &str) -> bool {
        self.installed_ids().contains(name)
    }

    fn installed(&self) -> Vec<Package> {
        self.installed_flatpaks()
    }

    fn install_command(&self, name: &str) -> String {
        format!("flatpak install -y flathub {}", shell_quote(name))
    }

    fn remove_command(&self, name: &str) -> String {
        format!("flatpak uninstall -y {}", shell_quote(name))
    }
}

/// Tab-separated `name  application  version  description` rows from `flatpak list/search`.
pub fn parse_table(output: &str, installed: bool) -> Vec<Package> {
    output
        .lines()
        .filter_map(|line| {
            let cols: Vec<&str> = line.split('\t').collect();
            if cols.len() < if installed { 3 } else { 4 } {
                return None;
            }
            Some(Package {
                repo: "flathub".into(),
                name: cols[1].trim().to_owned(),
                display_name: cols[0].trim().to_owned(),
                version: cols[2].trim().to_owned(),
                description: cols.get(3).map(|d| d.trim().to_owned()).unwrap_or_default(),
                source: PackageSource::Flatpak,
                installed,
            })
        })
        .collect()
}

/// Flathub `/search` response: `{"hits":[{app_id,name,version,summary}]}`.
/// `id` there is underscore-mangled (`org_mozilla_firefox`), so `app_id` wins.
pub fn parse_api_search(body: &str, installed: &HashSet<String>) -> Vec<Package> {
    let Ok(v) = serde_json::from_str::<Value>(body) else {
        return Vec::new();
    };
    v["hits"]
        .as_array()
        .map(|hits| {
            hits.iter()
                .take(API_RESULTS)
                .filter_map(|hit| {
                    let id = hit["app_id"].as_str().or_else(|| hit["id"].as_str())?;
                    Some(Package {
                        repo: "flathub".into(),
                        name: id.to_owned(),
                        display_name: hit["name"].as_str().map_or_else(|| human_name(id), str::to_owned),
                        version: hit["version"].as_str().unwrap_or("Unknown").to_owned(),
                        description: hit["summary"].as_str().unwrap_or_default().to_owned(),
                        source: PackageSource::Flatpak,
                        installed: installed.contains(id),
                    })
                })
                .collect()
        })
        .unwrap_or_default()
}

/// Flathub `/appstream/<id>` response into UI details and screenshots.
pub fn parse_appstream(body: &str) -> Option<(Details, Vec<String>)> {
    let v: Value = serde_json::from_str(body).ok()?;
    let id = v["id"].as_str().filter(|s| !s.is_empty())?;

    let screenshots = v["screenshots"]
        .as_array()
        .map(|list| list.iter().take(3).filter_map(screenshot_src).collect())
        .unwrap_or_default();

    let release = &v["releases"][0];
    let version = release["version"].as_str().filter(|s| !s.is_empty()).unwrap_or("Unknown");
    let date = timestamp(&release["timestamp"]).map_or_else(|| "---".into(), format_utc);

    let text = |key: &str| v[key].as_str().filter(|s| !s.is_empty());
    let description = text("description").or_else(|| text("summary")).unwrap_or_default();

    let details: Details = [
        ("Name", id.to_owned()),
        ("display_name", text("name").map_or_else(|| human_name(id), str::to_owned)),
        ("Description", strip_tags(description)),
        ("Version", version.to_owned()),
        ("Licenses", text("project_license").unwrap_or("Unknown").to_owned()),
        ("Architecture", "x86_64".into()),
        ("Repository", "Flathub".into()),
        ("Build Date", date),
        (
            "Maintainer",
            text("developer_name").or_else(|| text("project_group")).unwrap_or("Unknown").to_owned(),
        ),
    ]
    .into_iter()
    .map(|(k, v)| (k.to_owned(), v))
    .collect();

    Some((details, screenshots))
}

fn screenshot_src(s: &Value) -> Option<String> {
    let first = |list: &Value, key: &str| {
        list.as_array()?.iter().find_map(|e| e[key].as_str().filter(|u| !u.is_empty()).map(str::to_owned))
    };
    first(&s["sizes"], "src")
        .or_else(|| first(&s["thumbnails"], "url"))
        .or_else(|| s["url"].as_str().filter(|u| !u.is_empty()).map(str::to_owned))
}

/// Appstream timestamps arrive as strings or numbers.
fn timestamp(v: &Value) -> Option<i64> {
    v.as_i64().or_else(|| v.as_str()?.parse().ok()).filter(|t| *t != 0)
}

/// Installed/download size from `flatpak info` or `remote-info`, English or Portuguese.
pub fn parse_sizes(output: &str) -> (Option<String>, Option<String>) {
    let (mut installed, mut download) = (None, None);
    for line in output.lines() {
        let Some((key, val)) = line.trim().split_once(':') else {
            continue;
        };
        let val = val.trim();
        if val.is_empty() {
            continue;
        }
        match key.trim() {
            "Installed" | "Installed Size" | "Installed size" | "Tamanho instalado" => installed = Some(val.to_owned()),
            "Download" | "Download Size" | "Download size" | "Tamanho do download" => download = Some(val.to_owned()),
            _ => {}
        }
    }
    (installed, download)
}

/// Fallback when the appstream API has nothing: build details from `flatpak remote-info`.
pub fn parse_remote_info(output: &str, name: &str) -> Details {
    let mut d = Details::new();
    d.insert("Name".into(), name.to_owned());
    let lines: Vec<&str> = output.trim().lines().collect();
    if let Some((title, desc)) = lines.first().and_then(|l| l.split_once(" - ")) {
        d.insert("display_name".into(), title.trim().to_owned());
        d.insert("Description".into(), desc.trim().to_owned());
    }
    for line in &lines {
        let Some((key, val)) = line.split_once(':') else {
            continue;
        };
        let (key, val) = (key.trim(), val.trim().to_owned());
        let alias = match key {
            "Version" | "Versão" => Some("Version"),
            "License" | "Licença" => Some("Licenses"),
            "Installed" | "Tamanho instalado" | "Installed Size" => Some("Installed Size"),
            "Arch" | "Arq." => Some("Architecture"),
            "Date" | "Data" => Some("Build Date"),
            _ => None,
        };
        if let Some(alias) = alias {
            d.insert(alias.into(), val.clone());
        }
        d.insert(key.into(), val);
    }
    d
}

/// `org.mozilla.firefox` -> `Firefox`.
pub fn human_name(app_id: &str) -> String {
    let last = app_id.rsplit('.').next().unwrap_or(app_id).replace(['-', '_'], " ");
    let mut chars = last.chars();
    chars.next().map_or_else(String::new, |c| c.to_uppercase().chain(chars).collect())
}

/// Drop `<...>` tags, like PHP's `strip_tags`.
fn strip_tags(html: &str) -> String {
    let mut out = String::with_capacity(html.len());
    let mut in_tag = false;
    for c in html.chars() {
        match c {
            '<' => in_tag = true,
            '>' if in_tag => in_tag = false,
            _ if !in_tag => out.push(c),
            _ => {}
        }
    }
    out
}

#[cfg(test)]
mod tests {
    use super::*;

    const APPSTREAM_JSON: &str = include_str!("../tests/fixtures/flathub_appstream_firefox.json");
    const SEARCH_JSON: &str = include_str!("../tests/fixtures/flathub_search_firefox.json");
    const SEARCH_TXT: &str = include_str!("../tests/fixtures/flatpak_search_firefox.txt");
    const LIST_TXT: &str = include_str!("../tests/fixtures/flatpak_list.txt");
    const REMOTE_INFO: &str = include_str!("../tests/fixtures/flatpak_remote_info_firefox.txt");

    #[test]
    fn cli_search_rows() {
        let p = parse_table(SEARCH_TXT, false);
        assert!(!p.is_empty());
        assert_eq!(p[0].display_name, "Firefox");
        assert_eq!(p[0].name, "org.mozilla.firefox");
        assert_eq!(p[0].version, "159.0");
        assert_eq!(p[0].description, "Fast, Private & Safe Web Browser");
        assert!(!p[0].installed);
    }

    #[test]
    fn installed_rows_are_marked() {
        let p = parse_table(LIST_TXT, true);
        assert_eq!(p[0].name, "com.anydesk.Anydesk");
        assert!(p.iter().all(|p| p.installed));
    }

    #[test]
    fn api_search_hits() {
        let installed: HashSet<String> = ["org.mozilla.firefox".to_owned()].into();
        let p = parse_api_search(SEARCH_JSON, &installed);
        assert_eq!(p.len(), 10);
        assert_eq!(p[0].name, "org.mozilla.firefox");
        assert!(p[0].installed);
        assert_eq!(p[0].description, "Fast, Private & Safe Web Browser");
    }

    #[test]
    fn appstream_details_and_screenshots() {
        let (d, shots) = parse_appstream(APPSTREAM_JSON).unwrap();
        assert_eq!(d["Name"], "org.mozilla.firefox");
        assert_eq!(d["display_name"], "Firefox");
        assert_eq!(d["Version"], "159.0");
        assert_eq!(d["Licenses"], "MPL-2.0");
        assert_eq!(d["Maintainer"], "Mozilla");
        assert_eq!(d["Build Date"], "2026-10-22 00:00 UTC");
        assert!(!d["Description"].contains('<'));
        assert!((1..=3).contains(&shots.len()));
        assert!(shots[0].starts_with("https://dl.flathub.org/media/"));
    }

    #[test]
    fn sizes_from_localized_remote_info() {
        let (installed, download) = parse_sizes(REMOTE_INFO);
        assert_eq!(installed.as_deref(), Some("345,2\u{a0}MB"));
        assert_eq!(download.as_deref(), Some("129,5\u{a0}MB"));
    }

    #[test]
    fn remote_info_fallback_maps_keys() {
        let d = parse_remote_info(REMOTE_INFO, "org.mozilla.firefox");
        assert_eq!(d["display_name"], "Firefox");
        assert_eq!(d["Description"], "Fast, Private & Safe Web Browser");
        assert_eq!(d["Version"], "159.0");
        assert_eq!(d["Licenses"], "MPL-2.0");
        assert_eq!(d["Architecture"], "x86_64");
    }

    #[test]
    fn human_names() {
        assert_eq!(human_name("org.mozilla.firefox"), "Firefox");
        assert_eq!(human_name("com.github.tchx84.Flatseal"), "Flatseal");
    }

    #[test]
    fn commands() {
        let m = FlatpakManager;
        assert_eq!(m.install_command("a.b"), "flatpak install -y flathub 'a.b'");
        assert_eq!(m.remove_command("a.b"), "flatpak uninstall -y 'a.b'");
    }
}

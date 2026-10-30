use std::time::Duration;

use serde::{Deserialize, Serialize};

use crate::http::{get_text, urlencode};
use crate::manager::PackageManager;
use crate::pacman::{parse_info, parse_search, PacmanManager};
use crate::sys::{self, shell_quote};
use crate::{Details, Package, PackageSource};

const RPC: &str = "https://aur.archlinux.org/rpc/?v=5";
const MAX_RESULTS: usize = 50;

pub struct AurManager {
    helper: Helper,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Helper {
    Paru,
    Yay,
    /// No AUR helper installed: search/details go through the RPC only.
    None,
}

impl Helper {
    pub fn detect() -> Self {
        if sys::command_exists("paru") {
            Self::Paru
        } else if sys::command_exists("yay") {
            Self::Yay
        } else {
            Self::None
        }
    }

    fn bin(self) -> Option<&'static str> {
        match self {
            Self::Paru => Some("paru"),
            Self::Yay => Some("yay"),
            Self::None => None,
        }
    }
}

impl Default for AurManager {
    fn default() -> Self {
        Self::new(Helper::detect())
    }
}

#[derive(Debug, Deserialize)]
struct RpcResponse {
    #[serde(default)]
    results: Vec<RpcItem>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
#[serde(rename_all = "PascalCase")]
pub struct RpcItem {
    pub name: String,
    pub version: Option<String>,
    pub description: Option<String>,
    #[serde(rename = "URL")]
    url: Option<String>,
    license: Option<Vec<String>>,
    pub last_modified: Option<i64>,
    pub maintainer: Option<String>,
    /// Unix time the maintainer flagged it as outdated, if they did.
    pub out_of_date: Option<i64>,
    pub num_votes: Option<i64>,
    pub popularity: Option<f64>,
    pub first_submitted: Option<i64>,
    pub depends: Option<Vec<String>>,
    pub make_depends: Option<Vec<String>>,
}

impl AurManager {
    pub fn new(helper: Helper) -> Self {
        Self { helper }
    }

    fn rpc(&self, kind: &str, arg: &str, timeout: Duration) -> Vec<RpcItem> {
        let arg_key = if kind == "info" { "arg[]" } else { "arg" };
        let url = format!("{RPC}&type={kind}&{arg_key}={}", urlencode(arg));
        get_text(&url, timeout)
            .and_then(|body| parse_rpc(&body))
            .unwrap_or_default()
    }

    fn installed(&self) -> std::collections::HashSet<String> {
        sys::run("pacman", &["-Qq"], None)
            .filter(|o| o.success)
            .map(|o| o.stdout.lines().map(str::to_owned).collect())
            .unwrap_or_default()
    }
}

impl PackageManager for AurManager {
    fn search(&self, query: &str) -> Vec<Package> {
        if query.is_empty() {
            return Vec::new();
        }
        let installed = self.installed();

        if let Some(bin) = self.helper.bin() {
            if let Some(out) = sys::run(bin, &["-Ss", query], Some(Duration::from_secs(6))).filter(|o| o.success) {
                return parse_search(&out.stdout)
                    .into_iter()
                    .filter(|p| p.repo.eq_ignore_ascii_case("aur"))
                    .take(MAX_RESULTS)
                    .map(|mut p| {
                        p.source = PackageSource::Aur;
                        p.installed = installed.contains(&p.name);
                        p
                    })
                    .collect();
            }
        }

        self.rpc("search", query, Duration::from_secs(10))
            .into_iter()
            .take(MAX_RESULTS)
            .map(|item| Package {
                repo: "aur".into(),
                installed: installed.contains(&item.name),
                version: item.version.unwrap_or_else(|| "Unknown".into()),
                description: item.description.unwrap_or_default(),
                source: PackageSource::Aur,
                display_name: String::new(),
                name: item.name,
            })
            .collect()
    }

    fn details(&self, name: &str) -> Option<Details> {
        let helper_out = self
            .helper
            .bin()
            .and_then(|bin| sys::run(bin, &["-Si", name], None))
            .filter(|o| o.success);
        let out = helper_out.or_else(|| sys::run("pacman", &["-Qi", name], None).filter(|o| o.success));

        let Some(out) = out else {
            let item = self.rpc("info", name, Duration::from_secs(10)).into_iter().next()?;
            let mut d = rpc_details(&item);
            d.insert("is_installed".into(), self.is_installed(name).to_string());
            return Some(d);
        };

        let mut details = normalize_keys(parse_info(&out.stdout));
        if details.is_empty() {
            return None;
        }
        if details.get("Build Date").is_none_or(|v| v.is_empty()) {
            let item = self.rpc("info", name, Duration::from_secs(10)).into_iter().next();
            if let Some(ts) = item.and_then(|i| i.last_modified) {
                details.insert("Build Date".into(), format_utc(ts));
            }
        }
        details.insert("is_installed".into(), self.is_installed(name).to_string());
        Some(details)
    }

    fn is_installed(&self, name: &str) -> bool {
        PacmanManager.is_installed(name)
    }

    fn install_command(&self, name: &str) -> String {
        let bin = if self.helper == Helper::Paru { "paru" } else { "yay" };
        format!("{bin} -S {}", shell_quote(name))
    }

    fn remove_command(&self, name: &str) -> String {
        PacmanManager.remove_command(name)
    }
}

/// What the AUR knows about several packages, in one request. Names it does not know are left out.
pub fn info_many(names: &[String]) -> Vec<RpcItem> {
    if names.is_empty() {
        return Vec::new();
    }
    let args: String = names.iter().map(|n| format!("&arg[]={}", urlencode(n))).collect();
    get_text(&format!("{RPC}&type=info{args}"), Duration::from_secs(10))
        .and_then(|body| parse_rpc(&body))
        .unwrap_or_default()
}

pub(crate) fn parse_rpc(body: &str) -> Option<Vec<RpcItem>> {
    serde_json::from_str::<RpcResponse>(body).ok().map(|r| r.results)
}

fn rpc_details(item: &RpcItem) -> Details {
    let licenses = match &item.license {
        Some(l) if !l.is_empty() => l.join(", "),
        _ => "GPL".into(),
    };
    [
        ("Repository", "aur".to_owned()),
        ("Name", item.name.clone()),
        ("Version", item.version.clone().unwrap_or_else(|| "Unknown".into())),
        ("Description", item.description.clone().unwrap_or_default()),
        ("URL", item.url.clone().unwrap_or_default()),
        ("Licenses", licenses),
        ("Build Date", item.last_modified.map_or_else(|| "---".into(), format_utc)),
        ("Maintainer", item.maintainer.clone().unwrap_or_else(|| "Unknown".into())),
    ]
    .into_iter()
    .map(|(k, v)| (k.to_owned(), v))
    .collect()
}

/// Map localized pacman/helper keys onto the English ones the UI expects.
fn normalize_keys(mut d: Details) -> Details {
    const MAP: [(&str, &str); 9] = [
        ("Repositório", "Repository"),
        ("Nome", "Name"),
        ("Versão", "Version"),
        ("Descrição", "Description"),
        ("Grupos", "Groups"),
        ("Licenças", "Licenses"),
        ("Mantenedor", "Maintainer"),
        ("Última modificação", "Build Date"),
        ("Last Modified", "Build Date"),
    ];
    for (local, standard) in MAP {
        if let Some(v) = d.get(local).cloned() {
            d.entry(standard.to_owned()).or_insert(v);
        }
    }
    d
}

/// Unix timestamp as `YYYY-MM-DD HH:MM UTC`, matching PHP's `date('Y-m-d H:i')`.
pub fn format_utc(ts: i64) -> String {
    let days = ts.div_euclid(86_400);
    let secs = ts.rem_euclid(86_400);
    // Howard Hinnant's civil_from_days.
    let z = days + 719_468;
    let era = z.div_euclid(146_097);
    let doe = z.rem_euclid(146_097);
    let yoe = (doe - doe / 1_460 + doe / 36_524 - doe / 146_096) / 365;
    let doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
    let mp = (5 * doy + 2) / 153;
    let day = doy - (153 * mp + 2) / 5 + 1;
    let month = if mp < 10 { mp + 3 } else { mp - 9 };
    let year = yoe + era * 400 + i64::from(month <= 2);
    format!("{year:04}-{month:02}-{day:02} {:02}:{:02} UTC", secs / 3600, secs % 3600 / 60)
}

#[cfg(test)]
mod tests {
    use super::*;

    const SEARCH: &str = include_str!("../tests/fixtures/aur_search_yay.json");
    const INFO: &str = include_str!("../tests/fixtures/aur_info_yay.json");

    #[test]
    fn rpc_search_parses() {
        let items = parse_rpc(SEARCH).unwrap();
        assert_eq!(items.len(), 54);
        assert_eq!(items[0].name, "akp");
        assert_eq!(items[0].maintainer.as_deref(), Some("Akrata"));
    }

    #[test]
    fn rpc_info_to_details() {
        let item = parse_rpc(INFO).unwrap().remove(0);
        let d = rpc_details(&item);
        assert_eq!(d["Name"], "yay");
        assert_eq!(d["Version"], "13.1.0-1");
        assert_eq!(d["Licenses"], "GPL-3.0-or-later");
        assert_eq!(d["Maintainer"], "jguer");
        assert_eq!(d["Build Date"], "2026-10-28 14:15 UTC");
    }

    #[test]
    fn utc_format() {
        assert_eq!(format_utc(0), "1970-01-01 00:00 UTC");
        assert_eq!(format_utc(1_700_000_000), "2023-11-14 22:13 UTC");
        assert_eq!(format_utc(951_782_400), "2000-02-29 00:00 UTC");
    }

    #[test]
    fn localized_keys_are_mapped() {
        let d: Details = [("Nome".to_owned(), "x".to_owned()), ("Versão".to_owned(), "1".to_owned())].into();
        let d = normalize_keys(d);
        assert_eq!(d["Name"], "x");
        assert_eq!(d["Version"], "1");
    }

    #[test]
    fn install_uses_detected_helper() {
        assert_eq!(AurManager::new(Helper::Paru).install_command("x"), "paru -S 'x'");
        assert_eq!(AurManager::new(Helper::Yay).install_command("x"), "yay -S 'x'");
        assert_eq!(AurManager::new(Helper::None).install_command("x"), "yay -S 'x'");
    }
}

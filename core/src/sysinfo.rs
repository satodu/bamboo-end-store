//! What the sidebar shows about this machine. Reads `/proc` and `/etc/os-release`; no commands.

use std::fs;

use serde::Serialize;

#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
pub struct SystemInfo {
    /// The machine name.
    pub hostname: String,
    /// `CachyOS Linux`, from `PRETTY_NAME` in os-release.
    pub os: String,
    /// `7.3.0-4-cachyos`
    pub kernel: String,
    /// This app's version.
    pub app_version: String,
}

fn first_line(path: &str) -> Option<String> {
    let text = fs::read_to_string(path).ok()?;
    let line = text.lines().next()?.trim();
    (!line.is_empty()).then(|| line.to_owned())
}

/// `PRETTY_NAME`, else `NAME` plus `VERSION_ID`, with the quotes removed.
pub fn parse_os_release(text: &str) -> Option<String> {
    let get = |key: &str| {
        text.lines().find_map(|l| {
            let (k, v) = l.split_once('=')?;
            (k.trim() == key).then(|| v.trim().trim_matches('"').trim_matches('\'').to_owned())
        })
    };
    get("PRETTY_NAME").filter(|s| !s.is_empty()).or_else(|| {
        let name = get("NAME").filter(|s| !s.is_empty())?;
        Some(match get("VERSION_ID").filter(|s| !s.is_empty()) {
            Some(v) => format!("{name} {v}"),
            None => name,
        })
    })
}

pub fn read() -> SystemInfo {
    SystemInfo {
        hostname: first_line("/proc/sys/kernel/hostname")
            .or_else(|| first_line("/etc/hostname"))
            .or_else(|| std::env::var("HOSTNAME").ok().filter(|h| !h.is_empty()))
            .unwrap_or_else(|| "unknown".into()),
        os: fs::read_to_string("/etc/os-release")
            .ok()
            .and_then(|t| parse_os_release(&t))
            .unwrap_or_else(|| "Linux".into()),
        kernel: first_line("/proc/sys/kernel/osrelease").unwrap_or_else(|| "unknown".into()),
        app_version: env!("CARGO_PKG_VERSION").to_owned(),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn os_release_prefers_pretty_name() {
        let t = "NAME=\"EndeavourOS\"\nPRETTY_NAME=\"EndeavourOS\"\nID=endeavouros\nVERSION_ID=2024\n";
        assert_eq!(parse_os_release(t).as_deref(), Some("EndeavourOS"));
        let t = "NAME='Arch Linux'\nPRETTY_NAME=\"Arch Linux\"\n";
        assert_eq!(parse_os_release(t).as_deref(), Some("Arch Linux"));
    }

    #[test]
    fn os_release_falls_back_to_name_and_version() {
        assert_eq!(parse_os_release("NAME=\"Foo\"\nVERSION_ID=\"12\"\n").as_deref(), Some("Foo 12"));
        assert_eq!(parse_os_release("NAME=Foo\n").as_deref(), Some("Foo"));
        assert_eq!(parse_os_release("ID=x\n"), None);
        assert_eq!(parse_os_release(""), None);
    }

    #[test]
    fn reads_this_machine() {
        let i = read();
        assert!(!i.kernel.is_empty() && !i.hostname.is_empty() && !i.os.is_empty());
        assert_eq!(i.app_version, env!("CARGO_PKG_VERSION"));
        if cfg!(target_os = "linux") {
            assert_ne!(i.kernel, "unknown");
        }
    }
}

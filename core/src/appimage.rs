//! AppImage management: list, integrate (`.desktop` entry + icon), launch, remove.
//! Port of `App\Services\AppImageService`. Entries use the same ids (`md5` of the file name)
//! as the PHP app, so integrations it created are recognised.

use std::fs;
use std::io;
use std::os::unix::fs::PermissionsExt;
use std::os::unix::process::CommandExt;
use std::path::{Path, PathBuf};
use std::process::{Command, Stdio};
use std::time::Duration;

use serde::Serialize;

use crate::sys;

const ICON_EXTENSIONS: [&str; 4] = ["png", "svg", "jpg", "jpeg"];
/// Extracting runs the AppImage's own self-extractor; give a big one time but not forever.
const EXTRACT_TIMEOUT: Duration = Duration::from_secs(60);

#[derive(Debug)]
pub enum AppImageError {
    NotFound,
    NotAnAppImage,
    Io(io::Error),
}

impl std::fmt::Display for AppImageError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            Self::NotFound => f.write_str("file not found"),
            Self::NotAnAppImage => f.write_str("not an AppImage"),
            Self::Io(e) => write!(f, "{e}"),
        }
    }
}

impl From<io::Error> for AppImageError {
    fn from(e: io::Error) -> Self {
        Self::Io(e)
    }
}

#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
pub struct AppImage {
    /// File name, e.g. `Foo_1.0.AppImage`.
    pub name: String,
    /// `Foo_1.0` (extension stripped).
    pub display_name: String,
    pub path: String,
    pub size: String,
    pub size_bytes: u64,
    pub has_desktop: bool,
    /// `file://` URL of the extracted icon, when there is one.
    pub icon_url: String,
}

/// Where integration files live. Injected so tests never touch the real home.
#[derive(Debug, Clone)]
pub struct AppImageDirs {
    pub applications: PathBuf,
    pub icons: PathBuf,
    pub scratch: PathBuf,
}

impl AppImageDirs {
    pub fn system() -> Self {
        let data = std::env::var_os("XDG_DATA_HOME")
            .map(PathBuf::from)
            .filter(|p| p.is_absolute())
            .or_else(|| home().map(|h| h.join(".local").join("share")))
            .unwrap_or_else(|| PathBuf::from(".local/share"));
        Self { applications: data.join("applications"), icons: data.join("icons"), scratch: std::env::temp_dir() }
    }
}

fn home() -> Option<PathBuf> {
    std::env::var_os("HOME").map(PathBuf::from)
}

pub struct AppImages {
    dirs: AppImageDirs,
}

impl Default for AppImages {
    fn default() -> Self {
        Self::new(AppImageDirs::system())
    }
}

impl AppImages {
    pub fn new(dirs: AppImageDirs) -> Self {
        Self { dirs }
    }

    /// `~/Applications`, used while the setting is empty.
    pub fn default_directory() -> PathBuf {
        home().unwrap_or_default().join("Applications")
    }

    /// The directory for a stored setting value (`""` means the default).
    pub fn directory_for(setting: &str) -> PathBuf {
        let s = setting.trim();
        if s.is_empty() { Self::default_directory() } else { expand_home(s) }
    }

    pub fn id(file_name: &str) -> String {
        format!("{:x}", md5::compute(file_name.as_bytes()))
    }

    pub fn desktop_path(&self, file_name: &str) -> PathBuf {
        self.dirs.applications.join(format!("appimage-{}.desktop", Self::id(file_name)))
    }

    pub fn has_desktop_entry(&self, file_name: &str) -> bool {
        !file_name.is_empty() && self.desktop_path(file_name).is_file()
    }

    pub fn icon_path(&self, file_name: &str) -> Option<PathBuf> {
        let id = Self::id(file_name);
        ICON_EXTENSIONS
            .iter()
            .map(|ext| self.dirs.icons.join(format!("appimage-{id}.{ext}")))
            .find(|p| p.is_file())
    }

    /// AppImages directly inside `dir`, sorted by name. A missing directory is just empty;
    /// it is created when the first AppImage is registered.
    pub fn list(&self, dir: &Path) -> Vec<AppImage> {
        let Ok(entries) = fs::read_dir(dir) else {
            return Vec::new();
        };
        let mut found: Vec<AppImage> = entries
            .filter_map(Result::ok)
            .filter(|e| is_appimage(&e.path()))
            .filter_map(|e| {
                let path = fs::canonicalize(e.path()).ok()?;
                let meta = fs::metadata(&path).ok().filter(|m| m.is_file())?;
                let name = e.file_name().to_string_lossy().into_owned();
                Some(AppImage {
                    display_name: strip_extension(&name),
                    path: path.to_string_lossy().into_owned(),
                    size: format_bytes(meta.len()),
                    size_bytes: meta.len(),
                    has_desktop: self.has_desktop_entry(&name),
                    icon_url: self.icon_path(&name).map(|p| format!("file://{}", p.display())).unwrap_or_default(),
                    name,
                })
            })
            .collect();
        found.sort_by_key(|a| a.name.to_lowercase());
        found
    }

    /// Copy `source` into `target_dir` (unless already there), make it executable and create its
    /// menu entry and icon.
    pub fn register(&self, source: &Path, target_dir: &Path) -> Result<(), AppImageError> {
        if !source.is_file() {
            return Err(AppImageError::NotFound);
        }
        if !is_appimage(source) {
            return Err(AppImageError::NotAnAppImage);
        }
        fs::create_dir_all(target_dir)?;
        let name = source.file_name().ok_or(AppImageError::NotFound)?.to_string_lossy().into_owned();
        let target = target_dir.join(&name);
        if fs::canonicalize(source).ok() != fs::canonicalize(&target).ok() {
            fs::copy(source, &target)?;
        }
        make_executable(&target)?;
        self.create_desktop_entry(&name, &target)
    }

    pub fn create_desktop_entry(&self, file_name: &str, exec_path: &Path) -> Result<(), AppImageError> {
        let id = Self::id(file_name);
        let icon = self.extract_icon(exec_path, &id).map_or_else(|| "system-run".to_owned(), |p| p.display().to_string());
        let props = self.extract_properties(exec_path, &id);

        let mut name = props.name.clone().unwrap_or_else(|| strip_extension(file_name));
        if !name.to_lowercase().contains("appimage") {
            name.push_str(" (AppImage)");
        }
        let content = desktop_entry(&name, exec_path, &icon, &props);

        fs::create_dir_all(&self.dirs.applications)?;
        fs::write(self.desktop_path(file_name), content)?;
        Ok(())
    }

    /// Delete the AppImage together with its menu entry and icons. Only AppImage files.
    pub fn remove(&self, path: &Path) -> Result<(), AppImageError> {
        if !path.is_file() {
            return Err(AppImageError::NotFound);
        }
        if !is_appimage(path) {
            return Err(AppImageError::NotAnAppImage);
        }
        let name = path.file_name().ok_or(AppImageError::NotFound)?.to_string_lossy().into_owned();
        let id = Self::id(&name);
        let _ = fs::remove_file(self.desktop_path(&name));
        for ext in ICON_EXTENSIONS {
            let _ = fs::remove_file(self.dirs.icons.join(format!("appimage-{id}.{ext}")));
        }
        fs::remove_file(path)?;
        Ok(())
    }

    /// Start the AppImage detached from this process (own session, no stdio).
    pub fn launch(&self, path: &Path) -> Result<(), AppImageError> {
        if !path.is_file() {
            return Err(AppImageError::NotFound);
        }
        make_executable(path)?;
        let mut command = Command::new(path);
        command.stdin(Stdio::null()).stdout(Stdio::null()).stderr(Stdio::null()).process_group(0);
        sys::spawn_retrying_busy(&mut command).ok_or_else(|| io::Error::other("could not start the AppImage"))?;
        Ok(())
    }

    fn scratch_dir(&self, tag: &str, id: &str) -> Option<PathBuf> {
        let dir = self.dirs.scratch.join(format!("bamboo-appimage-{tag}-{id}-{}", std::process::id()));
        let _ = fs::remove_dir_all(&dir);
        fs::create_dir_all(&dir).ok()?;
        Some(dir)
    }

    /// `AppImage --appimage-extract <pattern>` in a scratch dir; returns that dir.
    fn extract(&self, exec: &Path, scratch: &Path, pattern: &str) {
        let _ = make_executable(exec);
        let Some(exec) = exec.to_str() else { return };
        let _ = sys::run_in(Some(scratch), exec, &["--appimage-extract", pattern], Some(EXTRACT_TIMEOUT));
    }

    fn extract_properties(&self, exec: &Path, id: &str) -> DesktopProps {
        let Some(scratch) = self.scratch_dir("desktop", id) else {
            return DesktopProps::default();
        };
        self.extract(exec, &scratch, "*.desktop");
        let found = fs::read_dir(scratch.join("squashfs-root"))
            .ok()
            .into_iter()
            .flatten()
            .filter_map(Result::ok)
            .map(|e| e.path())
            .find(|p| p.extension().is_some_and(|e| e.eq_ignore_ascii_case("desktop")))
            .and_then(|p| fs::read_to_string(p).ok());
        let _ = fs::remove_dir_all(&scratch);
        found.map(|c| parse_desktop_entry(&c)).unwrap_or_default()
    }

    /// Existing icon, else `.DirIcon`, else the first image in the AppImage root.
    fn extract_icon(&self, exec: &Path, id: &str) -> Option<PathBuf> {
        fs::create_dir_all(&self.dirs.icons).ok()?;
        if let Some(existing) = ICON_EXTENSIONS
            .iter()
            .map(|ext| self.dirs.icons.join(format!("appimage-{id}.{ext}")))
            .find(|p| p.is_file())
        {
            return Some(existing);
        }

        let scratch = self.scratch_dir("icon", id)?;
        let root = scratch.join("squashfs-root");
        let mut result = None;

        self.extract(exec, &scratch, ".DirIcon");
        if let Ok(bytes) = fs::read(root.join(".DirIcon")) {
            if let Some(ext) = image_extension(&bytes) {
                let target = self.dirs.icons.join(format!("appimage-{id}.{ext}"));
                if fs::write(&target, bytes).is_ok() {
                    result = Some(target);
                }
            }
        }

        if result.is_none() {
            self.extract(exec, &scratch, "*.png");
            let image = fs::read_dir(&root).ok().into_iter().flatten().filter_map(Result::ok).map(|e| e.path()).find(|p| {
                p.extension().and_then(|e| e.to_str()).is_some_and(|e| ICON_EXTENSIONS.contains(&e.to_lowercase().as_str()))
            });
            if let Some(src) = image {
                let ext = src.extension().and_then(|e| e.to_str()).unwrap_or("png").to_lowercase();
                let target = self.dirs.icons.join(format!("appimage-{id}.{ext}"));
                if fs::copy(&src, &target).is_ok() {
                    result = Some(target);
                }
            }
        }

        let _ = fs::remove_dir_all(&scratch);
        result
    }
}

fn is_appimage(path: &Path) -> bool {
    path.extension().is_some_and(|e| e.eq_ignore_ascii_case("appimage"))
}

fn strip_extension(name: &str) -> String {
    let lower = name.to_lowercase();
    if lower.ends_with(".appimage") { name[..name.len() - ".appimage".len()].to_owned() } else { name.to_owned() }
}

fn expand_home(s: &str) -> PathBuf {
    match (s.strip_prefix("~/"), home()) {
        (Some(rest), Some(h)) => h.join(rest),
        _ if s == "~" => home().unwrap_or_default(),
        _ => PathBuf::from(s),
    }
}

fn make_executable(path: &Path) -> io::Result<()> {
    let mut perms = fs::metadata(path)?.permissions();
    perms.set_mode(0o755);
    fs::set_permissions(path, perms)
}

/// Sniff PNG, JPEG or SVG. `.DirIcon` is often a symlink target of any of them.
fn image_extension(bytes: &[u8]) -> Option<&'static str> {
    if bytes.starts_with(b"\x89PNG\r\n\x1a\n") {
        Some("png")
    } else if bytes.starts_with(&[0xFF, 0xD8, 0xFF]) {
        Some("jpg")
    } else {
        let head = String::from_utf8_lossy(&bytes[..bytes.len().min(512)]).to_lowercase();
        (head.contains("<svg") || head.starts_with("<?xml")).then_some("svg")
    }
}

/// `103.9 MB`: up to two decimals, trailing zeros dropped, like the PHP `round()`.
pub fn format_bytes(bytes: u64) -> String {
    const UNITS: [&str; 5] = ["B", "KB", "MB", "GB", "TB"];
    let mut value = bytes as f64;
    let mut unit = 0;
    while value >= 1024.0 && unit < UNITS.len() - 1 {
        value /= 1024.0;
        unit += 1;
    }
    let text = format!("{value:.2}");
    let text = text.trim_end_matches('0').trim_end_matches('.');
    format!("{text} {}", UNITS[unit])
}

#[derive(Debug, Clone, PartialEq, Eq)]
pub struct DesktopProps {
    pub name: Option<String>,
    pub categories: String,
    pub comment: String,
    pub startup_wm_class: Option<String>,
}

impl Default for DesktopProps {
    fn default() -> Self {
        Self {
            name: None,
            categories: "Utility;".into(),
            comment: "Installed via Bamboo End Store".into(),
            startup_wm_class: None,
        }
    }
}

/// Read Name, Categories, Comment and StartupWMClass from the `[Desktop Entry]` group only
/// (not `[Desktop Action ...]`, whose `Name=` would otherwise win).
pub fn parse_desktop_entry(content: &str) -> DesktopProps {
    let mut props = DesktopProps::default();
    let mut in_entry = false;
    for line in content.lines() {
        let line = line.trim();
        if line.starts_with('[') {
            in_entry = line == "[Desktop Entry]";
            continue;
        }
        if !in_entry {
            continue;
        }
        let Some((key, value)) = line.split_once('=') else { continue };
        let value = value.trim();
        if value.is_empty() {
            continue;
        }
        match key.trim() {
            "Name" if props.name.is_none() => props.name = Some(value.to_owned()),
            "Categories" => props.categories = value.to_owned(),
            "Comment" if props.comment == DesktopProps::default().comment => props.comment = value.to_owned(),
            "StartupWMClass" => props.startup_wm_class = Some(value.to_owned()),
            _ => {}
        }
    }
    props
}

fn desktop_entry(name: &str, exec: &Path, icon: &str, props: &DesktopProps) -> String {
    // In Exec, `%` starts a field code and `"` or `\` must be escaped inside quotes.
    let exec = exec.display().to_string().replace('\\', "\\\\").replace('"', "\\\"").replace('%', "%%");
    let mut out = format!(
        "[Desktop Entry]\nType=Application\nName={name}\nExec=\"{exec}\"\nIcon={icon}\nCategories={}\nTerminal=false\nComment={}\n",
        props.categories, props.comment
    );
    if let Some(class) = &props.startup_wm_class {
        out.push_str(&format!("StartupWMClass={class}\n"));
    }
    out
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::sync::atomic::{AtomicU32, Ordering};

    static N: AtomicU32 = AtomicU32::new(0);

    /// A throwaway sandbox: its own applications/icons/scratch/target dirs.
    struct Sandbox {
        root: PathBuf,
        svc: AppImages,
    }

    impl Sandbox {
        fn new() -> Self {
            let root = std::env::temp_dir().join(format!("bamboo-ai-test-{}-{}", std::process::id(), N.fetch_add(1, Ordering::SeqCst)));
            let _ = fs::remove_dir_all(&root);
            for d in ["apps", "icons", "scratch", "target", "src"] {
                fs::create_dir_all(root.join(d)).unwrap();
            }
            let svc = AppImages::new(AppImageDirs {
                applications: root.join("apps"),
                icons: root.join("icons"),
                scratch: root.join("scratch"),
            });
            Self { root, svc }
        }

        /// A shell script that behaves like an AppImage's `--appimage-extract <pattern>`.
        fn fake(&self, name: &str, with_icon: bool) -> PathBuf {
            let png = "\\211PNG\\r\\n\\032\\n";
            let icon = if with_icon { format!("printf '{png}fakepng' > squashfs-root/.DirIcon") } else { ":".into() };
            let script = format!(
                "#!/bin/sh\n[ \"$1\" = \"--probe\" ] && exit 0\nif [ \"$1\" = \"--appimage-extract\" ]; then\n mkdir -p squashfs-root\n case \"$2\" in\n  .DirIcon) {icon} ;;\n  '*.desktop') printf '[Desktop Entry]\\nName=Fake App\\nCategories=Graphics;\\nComment=Hello\\nStartupWMClass=fakeapp\\n[Desktop Action x]\\nName=Nope\\n' > squashfs-root/fake.desktop ;;\n esac\n exit 0\nfi\necho launched > \"$(dirname \"$0\")/launched.txt\"\n"
            );
            let path = self.root.join("src").join(name);
            fs::write(&path, script).unwrap();
            make_executable(&path).unwrap();
            wait_until_executable(&path);
            path
        }

        fn target(&self) -> PathBuf {
            self.root.join("target")
        }
    }

    /// Linux refuses to execute a file some process still has open for writing (ETXTBSY). Tests run in
    /// parallel, and another test's `fork` briefly holds a copy of our just-closed writer, so wait
    /// until the fake AppImage can really be started.
    fn wait_until_executable(path: &Path) {
        for _ in 0..500 {
            match Command::new(path).arg("--probe").status() {
                Ok(_) => return,
                Err(e) if e.raw_os_error() == Some(26) => std::thread::sleep(Duration::from_millis(10)),
                Err(e) => panic!("cannot run the fake AppImage: {e}"),
            }
        }
        panic!("the fake AppImage stayed busy");
    }

    impl Drop for Sandbox {
        fn drop(&mut self) {
            let _ = fs::remove_dir_all(&self.root);
        }
    }

    #[test]
    fn ids_are_md5_of_the_file_name_like_the_php_app() {
        assert_eq!(AppImages::id("hello"), "5d41402abc4b2a76b9719d911017c592");
    }

    #[test]
    fn byte_formatting_matches_php() {
        assert_eq!(format_bytes(0), "0 B");
        assert_eq!(format_bytes(1024), "1 KB");
        assert_eq!(format_bytes(1_572_864), "1.5 MB");
        assert_eq!(format_bytes(108_947_456), "103.9 MB");
        assert_eq!(format_bytes(1_099_511_627_776), "1 TB");
    }

    #[test]
    fn desktop_entry_reads_only_the_main_group() {
        let p = parse_desktop_entry(
            "[Desktop Entry]\nName=Foo\nCategories=Graphics;\nComment=Hi\nStartupWMClass=foo\n\n[Desktop Action new]\nName=New window\n",
        );
        assert_eq!(p.name.as_deref(), Some("Foo"));
        assert_eq!(p.categories, "Graphics;");
        assert_eq!(p.comment, "Hi");
        assert_eq!(p.startup_wm_class.as_deref(), Some("foo"));
        assert_eq!(parse_desktop_entry("garbage"), DesktopProps::default());
    }

    #[test]
    fn exec_line_is_escaped() {
        let e = desktop_entry("X", Path::new("/a b/c\"d%e"), "i", &DesktopProps::default());
        assert!(e.contains(r#"Exec="/a b/c\"d%%e""#), "{e}");
    }

    #[test]
    fn image_sniffing() {
        assert_eq!(image_extension(b"\x89PNG\r\n\x1a\nxx"), Some("png"));
        assert_eq!(image_extension(&[0xFF, 0xD8, 0xFF, 0xE0]), Some("jpg"));
        assert_eq!(image_extension(b"<?xml version=\"1.0\"?><svg/>"), Some("svg"));
        assert_eq!(image_extension(b"not an image"), None);
    }

    #[test]
    fn register_creates_entry_icon_and_executable() {
        let sb = Sandbox::new();
        let src = sb.fake("Cool_App-1.0.AppImage", true);
        sb.svc.register(&src, &sb.target()).unwrap();

        let target = sb.target().join("Cool_App-1.0.AppImage");
        assert_eq!(fs::metadata(&target).unwrap().permissions().mode() & 0o111, 0o111);

        let entry = fs::read_to_string(sb.svc.desktop_path("Cool_App-1.0.AppImage")).unwrap();
        assert!(entry.contains("Name=Fake App (AppImage)"), "{entry}");
        assert!(entry.contains("Categories=Graphics;"));
        assert!(entry.contains("StartupWMClass=fakeapp"));
        assert!(entry.contains(&format!("Exec=\"{}\"", target.display())));

        let icon = sb.svc.icon_path("Cool_App-1.0.AppImage").unwrap();
        assert_eq!(icon.extension().unwrap(), "png");
        assert!(entry.contains(&format!("Icon={}", icon.display())));
        assert!(sb.svc.has_desktop_entry("Cool_App-1.0.AppImage"));
        assert!(fs::read_dir(sb.root.join("scratch")).unwrap().next().is_none(), "scratch dirs are cleaned");
    }

    #[test]
    fn register_without_icon_falls_back_to_system_run() {
        let sb = Sandbox::new();
        let src = sb.fake("NoIcon.AppImage", false);
        sb.svc.register(&src, &sb.target()).unwrap();
        let entry = fs::read_to_string(sb.svc.desktop_path("NoIcon.AppImage")).unwrap();
        assert!(entry.contains("Icon=system-run"));
        assert!(sb.svc.icon_path("NoIcon.AppImage").is_none());
    }

    #[test]
    fn register_in_place_does_not_copy_onto_itself() {
        let sb = Sandbox::new();
        let src = sb.fake("InPlace.AppImage", true);
        sb.svc.register(&src, &sb.root.join("src")).unwrap();
        assert!(src.is_file());
        assert!(sb.svc.has_desktop_entry("InPlace.AppImage"));
    }

    #[test]
    fn register_rejects_non_appimages_and_missing_files() {
        let sb = Sandbox::new();
        let txt = sb.root.join("src").join("notes.txt");
        fs::write(&txt, "x").unwrap();
        assert!(matches!(sb.svc.register(&txt, &sb.target()), Err(AppImageError::NotAnAppImage)));
        assert!(matches!(sb.svc.register(&sb.root.join("nope.AppImage"), &sb.target()), Err(AppImageError::NotFound)));
    }

    #[test]
    fn list_reports_integration_icon_and_sorts() {
        let sb = Sandbox::new();
        sb.svc.register(&sb.fake("b_app.AppImage", true), &sb.target()).unwrap();
        fs::copy(sb.fake("A_app.appimage", false), sb.target().join("A_app.appimage")).unwrap();
        fs::write(sb.target().join("readme.txt"), "x").unwrap();

        let list = sb.svc.list(&sb.target());
        let names: Vec<&str> = list.iter().map(|a| a.name.as_str()).collect();
        assert_eq!(names, ["A_app.appimage", "b_app.AppImage"]);
        assert!(!list[0].has_desktop && list[0].icon_url.is_empty());
        assert!(list[1].has_desktop && list[1].icon_url.starts_with("file://") && list[1].icon_url.ends_with(".png"));
        assert_eq!((list[0].display_name.as_str(), list[1].display_name.as_str()), ("A_app", "b_app"));
        assert!(sb.svc.list(&sb.root.join("missing")).is_empty());
    }

    #[test]
    fn remove_deletes_file_entry_and_icon_only_for_appimages() {
        let sb = Sandbox::new();
        sb.svc.register(&sb.fake("Gone.AppImage", true), &sb.target()).unwrap();
        let path = sb.target().join("Gone.AppImage");
        sb.svc.remove(&path).unwrap();
        assert!(!path.exists());
        assert!(!sb.svc.has_desktop_entry("Gone.AppImage"));
        assert!(sb.svc.icon_path("Gone.AppImage").is_none());

        let keep = sb.target().join("keep.txt");
        fs::write(&keep, "x").unwrap();
        assert!(matches!(sb.svc.remove(&keep), Err(AppImageError::NotAnAppImage)));
        assert!(keep.exists());
    }

    #[test]
    fn launch_starts_the_program() {
        let sb = Sandbox::new();
        let app = sb.fake("Run.AppImage", false);
        sb.svc.launch(&app).unwrap();
        let marker = sb.root.join("src").join("launched.txt");
        for _ in 0..50 {
            if marker.exists() {
                return;
            }
            std::thread::sleep(Duration::from_millis(100));
        }
        panic!("the AppImage was not started");
    }

    #[test]
    fn directory_setting() {
        assert_eq!(AppImages::directory_for(""), AppImages::default_directory());
        assert_eq!(AppImages::directory_for("/opt/apps"), PathBuf::from("/opt/apps"));
        assert!(AppImages::directory_for("~/x").ends_with("x"));
    }
}

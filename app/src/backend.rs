use core::pin::Pin;
use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::sync::{Arc, OnceLock};

use bamboo_core::{AppImages, Filter, Op, OpOutcome, PackageSource, Settings, Store};
use bamboo_core::store::Page;
use cxx_qt::{CxxQtType, Threading};
use cxx_qt_lib::QString;

#[cxx_qt::bridge]
pub mod qobject {
    unsafe extern "C++" {
        include!("cxx-qt-lib/qstring.h");
        type QString = cxx_qt_lib::QString;
    }

    extern "RustQt" {
        /// Single entry point QML talks to. Lists and details cross as JSON strings.
        #[qobject]
        #[qml_element]
        #[qproperty(bool, busy)]
        #[qproperty(i32, total)]
        /// Repository filter for search and installed lists: all, official, aur or flatpak.
        #[qproperty(QString, repo)]
        /// `relevance` or `name`.
        #[qproperty(QString, sort)]
        /// JSON `{all, official, aur, flatpak}`: how many results each repo filter would show.
        #[qproperty(QString, facets)]
        #[qproperty(QString, results)]
        #[qproperty(QString, details)]
        /// The settings as JSON; changes after every successful `setSetting`.
        #[qproperty(QString, settings)]
        /// JSON `{installed, flathub}`: is the flatpak binary there, and is the Flathub remote set up.
        #[qproperty(QString, flatpak)]
        /// JSON list of curated categories `{name, slug, description_key, packages}`.
        #[qproperty(QString, categories)]
        /// JSON list of official EndeavourOS tools found on this system (empty elsewhere).
        #[qproperty(QString, essentials)]
        /// JSON list of the latest EndeavourOS announcements `{title, link, date, summary, image}`.
        #[qproperty(QString, news)]
        /// JSON `{loaded, failed, items:[{id, author, date, content, url}]}` for the AUR package last asked about.
        #[qproperty(QString, comments)]
        type Backend = super::BackendRust;

        /// 1-based `page`; the page size is the `search_limit` setting.
        #[qinvokable]
        fn search(self: Pin<&mut Backend>, query: &QString, page: i32);

        /// Installed packages (pacman, AUR, Flatpak) filtered by name; fills `results` and `total`.
        #[qinvokable]
        #[cxx_name = "loadInstalled"]
        fn load_installed(self: Pin<&mut Backend>, filter: &QString, page: i32);

        /// The Bamboo Installs catalogue; fills `results` and `total`.
        #[qinvokable]
        #[cxx_name = "loadBambooInstalls"]
        fn load_bamboo_installs(self: Pin<&mut Backend>);

        /// Fetch the AUR community comments of `name` into `comments` (the page is scraped, cached half an hour).
        #[qinvokable]
        #[cxx_name = "loadComments"]
        fn load_comments(self: Pin<&mut Backend>, name: &QString);

        /// Machine name, OS, kernel and app version as JSON (read from `/proc`, instant).
        #[qinvokable]
        #[cxx_name = "systemInfo"]
        fn system_info(self: &Backend) -> QString;

        /// Fill `categories` (downloads the EndeavourOS list the first time).
        #[qinvokable]
        #[cxx_name = "loadCategories"]
        fn load_categories(self: Pin<&mut Backend>);

        /// Fill `news` (an empty list when the setting is off or the feed is unreachable).
        #[qinvokable]
        #[cxx_name = "loadNews"]
        fn load_news(self: Pin<&mut Backend>);

        /// Fill `essentials`.
        #[qinvokable]
        #[cxx_name = "loadEssentials"]
        fn load_essentials(self: Pin<&mut Backend>);

        /// A page of one curated category's packages; fills `results`, `total` and `facets`.
        #[qinvokable]
        #[cxx_name = "loadCategory"]
        fn load_category(self: Pin<&mut Backend>, name: &QString, page: i32);

        /// Refresh `flatpak` (cheap: a PATH lookup and `flatpak remotes`).
        #[qinvokable]
        #[cxx_name = "checkFlatpak"]
        fn check_flatpak(self: Pin<&mut Backend>);

        /// Run the full system update in a terminal; `operationFinished("System Update", "updated", code)`.
        #[qinvokable]
        #[cxx_name = "updateSystem"]
        fn update_system(self: Pin<&mut Backend>);

        /// Open a terminal and install Flatpak itself; `operationFinished("Flatpak", "installed", code)`.
        #[qinvokable]
        #[cxx_name = "installFlatpak"]
        fn install_flatpak(self: Pin<&mut Backend>);

        /// Open a terminal and add the Flathub remote; `operationFinished("Flathub", "configured", code)`.
        #[qinvokable]
        #[cxx_name = "addFlathub"]
        fn add_flathub(self: Pin<&mut Backend>);

        /// Drop every cached search, detail and installed list.
        #[qinvokable]
        #[cxx_name = "clearCache"]
        fn clear_cache(self: &Backend);

        #[qinvokable]
        #[cxx_name = "loadDetails"]
        fn load_details(self: Pin<&mut Backend>, name: &QString, source: &QString);

        /// `{install, remove}`: the commands to type by hand, shown in the details panel.
        #[qinvokable]
        #[cxx_name = "consoleCommands"]
        fn console_commands(self: &Backend, name: &QString, source: &QString) -> QString;

        #[qinvokable]
        fn install(self: Pin<&mut Backend>, name: &QString, source: &QString);

        #[qinvokable]
        fn remove(self: Pin<&mut Backend>, name: &QString, source: &QString);

        #[qinvokable]
        #[cxx_name = "settingsJson"]
        fn settings_json(self: &Backend) -> QString;

        /// `value` is a JSON scalar (`true`, `50`, `"list"`).
        #[qinvokable]
        #[cxx_name = "setSetting"]
        fn set_setting(self: Pin<&mut Backend>, key: &QString, value: &QString) -> bool;

        /// The AppImages in the configured directory; fills `results` and `total`.
        #[qinvokable]
        #[cxx_name = "loadAppImages"]
        fn load_app_images(self: Pin<&mut Backend>);

        /// Copy into the AppImage directory, make executable, create the menu entry and icon.
        #[qinvokable]
        #[cxx_name = "registerAppImage"]
        fn register_app_image(self: Pin<&mut Backend>, path: &QString);

        #[qinvokable]
        #[cxx_name = "removeAppImage"]
        fn remove_app_image(self: Pin<&mut Backend>, path: &QString);

        #[qinvokable]
        #[cxx_name = "launchAppImage"]
        fn launch_app_image(self: Pin<&mut Backend>, path: &QString);

        /// `action` is register, remove or launch; `message` carries the error when `ok` is false.
        #[qsignal]
        #[cxx_name = "appImageFinished"]
        fn app_image_finished(self: Pin<&mut Backend>, action: QString, name: QString, ok: bool, message: QString);

        /// `code` is the command's exit code, or -1 when no terminal was available
        /// and -2 when the terminal was closed early.
        #[qsignal]
        #[cxx_name = "operationFinished"]
        fn operation_finished(self: Pin<&mut Backend>, name: QString, action: QString, code: i32);
    }

    impl cxx_qt::Threading for Backend {}
}

/// One `Store` for the whole app: every `Backend` instance (one per screen) shares its caches,
/// settings and busy state.
static STORE: OnceLock<Arc<Store>> = OnceLock::new();

fn shared_store() -> Arc<Store> {
    STORE.get_or_init(|| Arc::new(Store::new(Settings::load()))).clone()
}

static APPIMAGES: OnceLock<Arc<AppImages>> = OnceLock::new();

fn app_images() -> Arc<AppImages> {
    APPIMAGES.get_or_init(|| Arc::new(AppImages::default())).clone()
}

/// QML hands over plain paths; accept a `file://` URL too.
fn local_path(s: &QString) -> PathBuf {
    let s = s.to_string();
    PathBuf::from(s.strip_prefix("file://").unwrap_or(&s))
}

fn file_name(p: &Path) -> String {
    p.file_name().map(|n| n.to_string_lossy().into_owned()).unwrap_or_default()
}

pub struct BackendRust {
    busy: bool,
    total: i32,
    repo: QString,
    sort: QString,
    facets: QString,
    results: QString,
    details: QString,
    settings: QString,
    flatpak: QString,
    categories: QString,
    essentials: QString,
    news: QString,
    comments: QString,
    store: Arc<Store>,
    /// Bumped per search; a slow older search must not overwrite a newer one.
    search_gen: Arc<AtomicU64>,
}

/// `value` as a JSON string for QML, or `fallback` (itself valid JSON) if it cannot be serialized.
fn json<T: serde::Serialize>(value: &T, fallback: &str) -> QString {
    QString::from(&serde_json::to_string(value).unwrap_or_else(|_| fallback.into()))
}

fn settings_json(store: &Store) -> QString {
    json(&store.settings(), "{}")
}

/// A page of packages already serialized on the worker thread, ready to hand to the UI thread.
struct PageView {
    results: String,
    facets: String,
    total: i32,
}

impl From<Page> for PageView {
    fn from(page: Page) -> Self {
        Self {
            results: bamboo_core::icons::packages_json(&page.items),
            facets: serde_json::to_string(&page.counts).unwrap_or_else(|_| "{}".into()),
            total: page.total as i32,
        }
    }
}

impl Default for BackendRust {
    fn default() -> Self {
        let store = shared_store();
        Self {
            busy: false,
            total: 0,
            repo: QString::from("all"),
            sort: QString::from("relevance"),
            facets: QString::from("{}"),
            results: QString::from("[]"),
            details: QString::from("null"),
            settings: settings_json(&store),
            flatpak: QString::from("{}"),
            categories: QString::from("[]"),
            essentials: QString::from("[]"),
            news: QString::from("[]"),
            comments: QString::from("{\"loaded\":false}"),
            store,
            search_gen: Arc::new(AtomicU64::new(0)),
        }
    }
}

fn parse_source(s: &QString) -> Option<PackageSource> {
    s.to_string().parse().ok()
}

impl qobject::Backend {
    /// The repo/sort the UI has picked, as the core's filter type.
    fn filter(&self) -> Filter {
        Filter::parse(&self.rust().repo.to_string(), &self.rust().sort.to_string())
    }

    /// Run `fetch` on a worker thread and, if no newer load started meanwhile, hand its result to
    /// `apply` on the UI thread. The busy flag spans the load. A slow older load never overwrites a newer one.
    fn load_latest<R, F, A>(mut self: Pin<&mut Self>, fetch: F, apply: A)
    where
        R: Send + 'static,
        F: FnOnce(&Store) -> R + Send + 'static,
        A: FnOnce(Pin<&mut qobject::Backend>, R) + Send + 'static,
    {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let value = fetch(&store);
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    apply(this.as_mut(), value);
                    this.as_mut().set_busy(false);
                }
            });
        });
    }

    /// `load_latest` for the screens that show a filtered, paginated list.
    fn load_page<F>(self: Pin<&mut Self>, fetch: F)
    where
        F: FnOnce(&Store) -> Page + Send + 'static,
    {
        self.load_latest(
            |store| PageView::from(fetch(store)),
            |mut this, view| {
                this.as_mut().set_facets(QString::from(&view.facets));
                this.as_mut().set_total(view.total);
                this.as_mut().set_results(QString::from(&view.results));
            },
        );
    }

    fn search(self: Pin<&mut Self>, query: &QString, page: i32) {
        let query = query.to_string();
        let filter = self.filter();
        self.load_page(move |store| store.search_page(&query, page.max(1) as usize, filter));
    }

    fn load_installed(self: Pin<&mut Self>, text: &QString, page: i32) {
        let text = text.to_string();
        let filter = self.filter();
        self.load_page(move |store| store.installed_page(&text, page.max(1) as usize, filter));
    }

    fn load_category(self: Pin<&mut Self>, name: &QString, page: i32) {
        let name = name.to_string();
        let filter = self.filter();
        self.load_page(move |store| store.category_page(&name, page.max(1) as usize, filter));
    }

    fn load_app_images(self: Pin<&mut Self>) {
        self.load_latest(
            |store| {
                let dir = AppImages::directory_for(&store.settings().appimage_path);
                let items = app_images().list(&dir);
                (items.len() as i32, serde_json::to_string(&items).unwrap_or_else(|_| "[]".into()))
            },
            |mut this, (total, results)| {
                this.as_mut().set_total(total);
                this.as_mut().set_results(QString::from(&results));
            },
        );
    }

    fn load_bamboo_installs(self: Pin<&mut Self>) {
        self.load_latest(
            |store| {
                let items = store.bamboo_installs();
                (items.len() as i32, bamboo_core::icons::packages_json(&items))
            },
            |mut this, (total, results)| {
                this.as_mut().set_total(total);
                this.as_mut().set_results(QString::from(&results));
            },
        );
    }

    fn register_app_image(self: Pin<&mut Self>, path: &QString) {
        let source = local_path(path);
        self.app_image_op("register", source, |svc, store, path| {
            let dir = AppImages::directory_for(&store.settings().appimage_path);
            svc.register(path, &dir)
        });
    }

    fn remove_app_image(self: Pin<&mut Self>, path: &QString) {
        self.app_image_op("remove", local_path(path), |svc, _, path| svc.remove(path));
    }

    fn launch_app_image(self: Pin<&mut Self>, path: &QString) {
        self.app_image_op("launch", local_path(path), |svc, _, path| svc.launch(path));
    }

    /// Run one AppImage operation on a worker thread and report through `appImageFinished`.
    fn app_image_op<F>(mut self: Pin<&mut Self>, action: &'static str, path: PathBuf, op: F)
    where
        F: FnOnce(&AppImages, &Store, &Path) -> Result<(), bamboo_core::appimage::AppImageError> + Send + 'static,
    {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let name = file_name(&path);
            let result = op(&app_images(), &store, &path);
            let (ok, message) = match result {
                Ok(()) => (true, String::new()),
                Err(e) => (false, e.to_string()),
            };
            let _ = thread.queue(move |mut this| {
                this.as_mut().set_busy(false);
                this.as_mut().app_image_finished(
                    QString::from(action),
                    QString::from(&name),
                    ok,
                    QString::from(&message),
                );
            });
        });
    }

    fn load_comments(mut self: Pin<&mut Self>, name: &QString) {
        let store = self.rust().store.clone();
        let name = name.to_string();
        let thread = self.qt_thread();
        self.as_mut().set_comments(QString::from("{\"loaded\":false}"));
        std::thread::spawn(move || {
            let json = match store.aur_comments(&name) {
                Some(items) => serde_json::json!({ "loaded": true, "failed": false, "items": items }),
                None => serde_json::json!({ "loaded": true, "failed": true, "items": [] }),
            };
            let _ = thread.queue(move |mut this| this.as_mut().set_comments(QString::from(&json.to_string())));
        });
    }

    fn system_info(&self) -> QString {
        json(&bamboo_core::sysinfo::read(), "{}")
    }

    fn load_categories(self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        std::thread::spawn(move || {
            let json = serde_json::to_string(&store.categories()).unwrap_or_else(|_| "[]".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_categories(QString::from(&json)));
        });
    }

    fn load_news(self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        std::thread::spawn(move || {
            let items = if store.settings().show_news { bamboo_core::news::recent() } else { Vec::new() };
            let json = serde_json::to_string(&items).unwrap_or_else(|_| "[]".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_news(QString::from(&json)));
        });
    }

    fn load_essentials(self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        std::thread::spawn(move || {
            let json = bamboo_core::icons::packages_json(&store.essentials());
            let _ = thread.queue(move |mut this| this.as_mut().set_essentials(QString::from(&json)));
        });
    }

    fn check_flatpak(self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        std::thread::spawn(move || {
            let json = serde_json::to_string(&store.flatpak_status()).unwrap_or_else(|_| "{}".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_flatpak(QString::from(&json)));
        });
    }

    fn update_system(self: Pin<&mut Self>) {
        self.run_setup("System Update", "updated", |store| store.update_system());
    }

    fn install_flatpak(self: Pin<&mut Self>) {
        self.run_setup("Flatpak", "installed", |store| store.install_flatpak());
    }

    fn add_flathub(self: Pin<&mut Self>) {
        self.run_setup("Flathub", "configured", |store| store.add_flathub());
    }

    /// Run a Flatpak setup command in a terminal, then refresh the status and report the exit code.
    fn run_setup<F>(mut self: Pin<&mut Self>, name: &'static str, action: &'static str, run: F)
    where
        F: FnOnce(&Store) -> OpOutcome + Send + 'static,
    {
        let store = self.rust().store.clone();
        if store.state.is_busy() {
            return;
        }
        let thread = self.qt_thread();
        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let code = run(&store).code();
            bamboo_core::notify::operation_finished(&store.settings(), name, action, code);
            let json = serde_json::to_string(&store.flatpak_status()).unwrap_or_else(|_| "{}".into());
            let _ = thread.queue(move |mut this| {
                this.as_mut().set_flatpak(QString::from(&json));
                this.as_mut().set_busy(false);
                this.as_mut().operation_finished(QString::from(name), QString::from(action), code);
            });
        });
    }

    fn clear_cache(&self) {
        self.rust().store.clear_cache();
    }

    fn load_details(self: Pin<&mut Self>, name: &QString, source: &QString) {
        let Some(source) = parse_source(source) else {
            return;
        };
        let store = self.rust().store.clone();
        let name = name.to_string();
        let thread = self.qt_thread();

        std::thread::spawn(move || {
            // Phase 1: what the package manager knows, so the panel opens at once.
            let base = store.details(&name, source);
            let json = serde_json::to_string(&base).unwrap_or_else(|_| "null".into());
            let queued = thread.queue(move |mut this| this.as_mut().set_details(QString::from(&json)));
            let Some(mut merged) = base.filter(|_| queued.is_ok()) else { return };

            // Phase 2: icon and screenshots from Flathub (network); the panel updates when they arrive.
            let extras = store.details_extras(&name, source);
            if extras.is_empty() {
                return;
            }
            merged.extend(extras);
            let json = serde_json::to_string(&Some(merged)).unwrap_or_else(|_| "null".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_details(QString::from(&json)));
        });
    }

    fn console_commands(&self, name: &QString, source: &QString) -> QString {
        let Some(source) = parse_source(source) else {
            return QString::from("{\"install\":\"\",\"remove\":\"\"}");
        };
        let c = self.rust().store.console_commands(&name.to_string(), source);
        json(&c, "{}")
    }

    fn install(self: Pin<&mut Self>, name: &QString, source: &QString) {
        self.run_op(Op::Install, name, source);
    }

    fn remove(self: Pin<&mut Self>, name: &QString, source: &QString) {
        self.run_op(Op::Remove, name, source);
    }

    fn run_op(mut self: Pin<&mut Self>, op: Op, name: &QString, source: &QString) {
        let Some(source) = parse_source(source) else {
            return;
        };
        let store = self.rust().store.clone();
        if store.state.is_busy() {
            return;
        }
        let name = name.to_string();
        let action = if op == Op::Install { "installed" } else { "removed" };
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let code = store.run(op, &name, source).code();
            bamboo_core::notify::operation_finished(&store.settings(), &name, action, code);
            let _ = thread.queue(move |mut this| {
                this.as_mut().set_busy(false);
                this.as_mut().operation_finished(QString::from(&name), QString::from(action), code);
            });
        });
    }

    fn settings_json(&self) -> QString {
        settings_json(&self.rust().store)
    }

    fn set_setting(mut self: Pin<&mut Self>, key: &QString, value: &QString) -> bool {
        let Ok(value) = serde_json::from_str::<serde_json::Value>(&value.to_string()) else {
            return false;
        };
        let store = &self.rust().store;
        let Ok(serde_json::Value::Object(mut map)) = serde_json::to_value(store.settings()) else {
            return false;
        };
        map.insert(key.to_string(), value);
        let Ok(updated) = serde_json::from_value::<Settings>(serde_json::Value::Object(map)) else {
            return false;
        };
        if store.update_settings(updated).is_err() {
            return false;
        }
        let json = settings_json(store);
        self.as_mut().set_settings(json);
        true
    }
}

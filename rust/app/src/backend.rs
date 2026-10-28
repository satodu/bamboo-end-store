use core::pin::Pin;
use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicU64, Ordering};
use std::sync::{Arc, OnceLock};

use bamboo_core::{AppImages, Filter, Op, OpOutcome, PackageSource, Settings, Store};
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
    comments: QString,
    store: Arc<Store>,
    /// Bumped per search; a slow older search must not overwrite a newer one.
    search_gen: Arc<AtomicU64>,
}

fn settings_json(store: &Store) -> QString {
    QString::from(&serde_json::to_string(&store.settings()).unwrap_or_else(|_| "{}".into()))
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

    fn search(mut self: Pin<&mut Self>, query: &QString, page: i32) {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let query = query.to_string();
        let filter = self.filter();
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let result = store.search_page(&query, page.max(1) as usize, filter);
            let json = bamboo_core::icons::packages_json(&result.items);
            let facets = serde_json::to_string(&result.counts).unwrap_or_else(|_| "{}".into());
            let total = result.total;
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    this.as_mut().set_facets(QString::from(&facets));
                    this.as_mut().set_total(total as i32);
                    this.as_mut().set_results(QString::from(&json));
                    this.as_mut().set_busy(false);
                }
            });
        });
    }

    fn load_installed(mut self: Pin<&mut Self>, filter: &QString, page: i32) {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let text = filter.to_string();
        let filter = self.filter();
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let result = store.installed_page(&text, page.max(1) as usize, filter);
            let json = bamboo_core::icons::packages_json(&result.items);
            let facets = serde_json::to_string(&result.counts).unwrap_or_else(|_| "{}".into());
            let total = result.total;
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    this.as_mut().set_facets(QString::from(&facets));
                    this.as_mut().set_total(total as i32);
                    this.as_mut().set_results(QString::from(&json));
                    this.as_mut().set_busy(false);
                }
            });
        });
    }

    fn load_app_images(mut self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let dir = AppImages::directory_for(&store.settings().appimage_path);
            let items = app_images().list(&dir);
            let json = serde_json::to_string(&items).unwrap_or_else(|_| "[]".into());
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    this.as_mut().set_total(items.len() as i32);
                    this.as_mut().set_results(QString::from(&json));
                    this.as_mut().set_busy(false);
                }
            });
        });
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

    fn load_bamboo_installs(mut self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let items = store.bamboo_installs();
            let json = bamboo_core::icons::packages_json(&items);
            let total = items.len();
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    this.as_mut().set_total(total as i32);
                    this.as_mut().set_results(QString::from(&json));
                    this.as_mut().set_busy(false);
                }
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
        QString::from(&serde_json::to_string(&bamboo_core::sysinfo::read()).unwrap_or_else(|_| "{}".into()))
    }

    fn load_categories(self: Pin<&mut Self>) {
        let store = self.rust().store.clone();
        let thread = self.qt_thread();
        std::thread::spawn(move || {
            let json = serde_json::to_string(&store.categories()).unwrap_or_else(|_| "[]".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_categories(QString::from(&json)));
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

    fn load_category(mut self: Pin<&mut Self>, name: &QString, page: i32) {
        let store = self.rust().store.clone();
        let generation = self.rust().search_gen.clone();
        let mine = generation.fetch_add(1, Ordering::SeqCst) + 1;
        let name = name.to_string();
        let filter = self.filter();
        let thread = self.qt_thread();

        self.as_mut().set_busy(true);
        std::thread::spawn(move || {
            let result = store.category_page(&name, page.max(1) as usize, filter);
            let json = bamboo_core::icons::packages_json(&result.items);
            let facets = serde_json::to_string(&result.counts).unwrap_or_else(|_| "{}".into());
            let total = result.total;
            let _ = thread.queue(move |mut this| {
                if generation.load(Ordering::SeqCst) == mine {
                    this.as_mut().set_facets(QString::from(&facets));
                    this.as_mut().set_total(total as i32);
                    this.as_mut().set_results(QString::from(&json));
                    this.as_mut().set_busy(false);
                }
            });
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
            let code = match run(&store) {
                OpOutcome::Finished(code) => code,
                OpOutcome::NoTerminal => -1,
                OpOutcome::Aborted => -2,
            };
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
            let json = serde_json::to_string(&store.details(&name, source)).unwrap_or_else(|_| "null".into());
            let _ = thread.queue(move |mut this| this.as_mut().set_details(QString::from(&json)));
        });
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
            let code = match store.run(op, &name, source) {
                OpOutcome::Finished(code) => code,
                OpOutcome::NoTerminal => -1,
                OpOutcome::Aborted => -2,
            };
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

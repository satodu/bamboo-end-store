use core::pin::Pin;
use std::fs;
use std::thread;
use std::time::{Duration, SystemTime};

use bamboo_core::theme::{self, Source, Theme};
use bamboo_core::Settings;
use cxx_qt::{CxxQtType, Threading};
use cxx_qt_lib::QString;

#[cxx_qt::bridge]
pub mod qobject {
    unsafe extern "C++" {
        include!("cxx-qt-lib/qstring.h");
        type QString = cxx_qt_lib::QString;
    }

    extern "RustQt" {
        /// Palette source for the `Theme` singleton. `palette` is a JSON role -> `#rrggbb` map.
        #[qobject]
        #[qml_element]
        #[qproperty(QString, palette)]
        #[qproperty(bool, dark)]
        #[qproperty(QString, source)]
        type ThemeBridge = super::ThemeBridgeRust;

        /// Re-read the settings and the palette. Call after changing a `theme_*` setting.
        #[qinvokable]
        fn reload(self: Pin<&mut ThemeBridge>);
    }

    impl cxx_qt::Threading for ThemeBridge {}
    impl cxx_qt::Initialize for ThemeBridge {}
}

pub struct ThemeBridgeRust {
    palette: QString,
    dark: bool,
    source: QString,
}

fn source_name(s: Source) -> &'static str {
    match s {
        Source::System => "system",
        Source::Preset => "preset",
        Source::Seed => "seed",
    }
}

fn palette_json(t: &Theme) -> QString {
    QString::from(&serde_json::to_string(&t.palette).unwrap_or_else(|_| "{}".into()))
}

impl Default for ThemeBridgeRust {
    /// Resolved synchronously so the first frame already has the right colours.
    fn default() -> Self {
        let t = theme::resolve(&Settings::load());
        Self { palette: palette_json(&t), dark: t.dark, source: QString::from(source_name(t.source)) }
    }
}

impl cxx_qt::Initialize for qobject::ThemeBridge {
    /// Follow the desktop palette file: when its mtime changes, resolve again.
    fn initialize(self: Pin<&mut Self>) {
        let thread = self.qt_thread();
        thread::spawn(move || {
            let path = theme::system_palette_path();
            let mtime = || fs::metadata(&path).and_then(|m| m.modified()).ok();
            let mut last: Option<SystemTime> = mtime();
            loop {
                thread::sleep(Duration::from_millis(500));
                let now = mtime();
                if now == last {
                    continue;
                }
                last = now;
                let t = theme::resolve(&Settings::load());
                let applied = thread.queue(move |this| apply(this, t));
                if applied.is_err() {
                    break; // the QObject is gone
                }
            }
        });
    }
}

fn apply(mut this: Pin<&mut qobject::ThemeBridge>, t: Theme) {
    // `palette` last: QML bindings run on its change signal and must already see the new mode.
    this.as_mut().set_dark(t.dark);
    this.as_mut().set_source(QString::from(source_name(t.source)));
    this.as_mut().set_palette(palette_json(&t));
}

impl qobject::ThemeBridge {
    fn reload(self: Pin<&mut Self>) {
        let thread = self.qt_thread();
        thread::spawn(move || {
            let t = theme::resolve(&Settings::load());
            let _ = thread.queue(move |this| apply(this, t));
        });
    }
}

// `rust()` is only needed by siblings; keep the trait import used.
#[allow(unused)]
fn _touch(b: &qobject::ThemeBridge) {
    let _ = b.rust();
}

use core::pin::Pin;

use bamboo_core::{i18n, Settings};
use cxx_qt_lib::QString;

#[cxx_qt::bridge]
pub mod qobject {
    unsafe extern "C++" {
        include!("cxx-qt-lib/qstring.h");
        type QString = cxx_qt_lib::QString;
    }

    extern "RustQt" {
        /// Language tables for the `Tr` singleton. `table` is a JSON key -> text map.
        #[qobject]
        #[qml_element]
        #[qproperty(QString, table)]
        #[qproperty(QString, lang)]
        type I18nBridge = super::I18nBridgeRust;

        /// Re-read the `locale` setting. Call after changing it.
        #[qinvokable]
        fn reload(self: Pin<&mut I18nBridge>);
    }
}

pub struct I18nBridgeRust {
    table: QString,
    lang: QString,
}

fn load() -> (QString, QString) {
    let lang = i18n::language_for(&Settings::load().locale);
    let json = serde_json::to_string(i18n::table(lang)).unwrap_or_else(|_| "{}".into());
    (QString::from(&json), QString::from(lang))
}

impl Default for I18nBridgeRust {
    fn default() -> Self {
        let (table, lang) = load();
        Self { table, lang }
    }
}

impl qobject::I18nBridge {
    fn reload(mut self: Pin<&mut Self>) {
        let (table, lang) = load();
        self.as_mut().set_lang(lang);
        self.as_mut().set_table(table);
    }
}

#[cxx_qt::bridge]
pub mod qobject {
    unsafe extern "C++" {
        include!("cxx-qt-lib/qstring.h");
        type QString = cxx_qt_lib::QString;
    }

    extern "RustQt" {
        #[qobject]
        #[qml_element]
        #[qproperty(QString, status)]
        type Backend = super::BackendRust;

        #[qinvokable]
        fn sources(self: &Backend) -> QString;
    }
}

use core::pin::Pin;
use cxx_qt_lib::QString;

pub struct BackendRust {
    status: QString,
}

impl Default for BackendRust {
    fn default() -> Self {
        Self { status: QString::from("rust core online") }
    }
}

impl qobject::Backend {
    fn sources(self: &Self) -> QString {
        use bamboo_core::PackageSource::*;
        QString::from(format!("{}, {}, {}", Pacman, Aur, Flatpak))
    }
}

#[allow(unused)]
fn _pin(_: Pin<&mut qobject::Backend>) {}

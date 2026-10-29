mod backend;
mod i18n_bridge;
mod theme_bridge;

use std::ffi::CString;
use std::os::raw::{c_char, c_int};

extern "C" {
    /// `app_shim.cpp`: builds the QApplication and the QML engine, loads `url` and runs the event loop.
    fn bamboo_run(argc: c_int, argv: *mut *mut c_char, url: *const c_char) -> c_int;
}

fn main() {
    // Qt-Wayland logs a harmless warning on every focus change of a text field.
    if std::env::var_os("QT_LOGGING_RULES").is_none() {
        std::env::set_var("QT_LOGGING_RULES", "qt.qpa.wayland.textinput=false");
    }

    // Dev aid: BAMBOO_QML=path/to/file.qml loads that file instead of the embedded Main.qml.
    let url = match std::env::var("BAMBOO_QML") {
        Ok(path) => format!("file://{}", std::fs::canonicalize(path).unwrap().display()),
        Err(_) => "qrc:/qt/qml/io/bamboo/store/qml/Main.qml".to_owned(),
    };
    let url = CString::new(url).expect("the QML url has no NUL byte");

    // Qt keeps pointers into argv for the whole run, so the strings are leaked on purpose.
    let mut args: Vec<*mut c_char> = std::env::args()
        .filter_map(|a| CString::new(a).ok())
        .map(CString::into_raw)
        .collect();
    args.push(std::ptr::null_mut());
    let argc = (args.len() - 1) as c_int;

    let code = unsafe { bamboo_run(argc, args.as_mut_ptr(), url.as_ptr()) };
    std::process::exit(code);
}

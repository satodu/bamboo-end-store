mod backend;
mod i18n_bridge;
mod theme_bridge;

use cxx_qt_lib::{QGuiApplication, QQmlApplicationEngine, QUrl};

fn main() {
    // Qt-Wayland logs a harmless warning on every focus change of a text field.
    if std::env::var_os("QT_LOGGING_RULES").is_none() {
        std::env::set_var("QT_LOGGING_RULES", "qt.qpa.wayland.textinput=false");
    }

    let mut app = QGuiApplication::new();
    let mut engine = QQmlApplicationEngine::new();

    // Dev aid: BAMBOO_QML=path/to/file.qml loads that file instead of the embedded Main.qml.
    let url = match std::env::var("BAMBOO_QML") {
        Ok(path) => format!("file://{}", std::fs::canonicalize(path).unwrap().display()),
        Err(_) => "qrc:/qt/qml/io/bamboo/store/qml/Main.qml".to_owned(),
    };
    if let Some(engine) = engine.as_mut() {
        engine.load(&QUrl::from(url.as_str()));
    }

    if let Some(app) = app.as_mut() {
        app.exec();
    }
}

use cxx_qt_build::{CxxQtBuilder, QmlFile, QmlModule};

fn main() {
    CxxQtBuilder::new_qml_module(
        QmlModule::new("io.bamboo.store")
            .qml_file("qml/Main.qml")
            .qml_file("qml/SelfTest.qml")
            .qml_file(QmlFile::from("qml/Theme.qml").singleton(true)),
    )
    .qt_module("Quick")
    .files(["src/backend.rs", "src/theme_bridge.rs"])
    .build();
}

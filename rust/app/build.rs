use cxx_qt_build::{CxxQtBuilder, QmlModule};

fn main() {
    CxxQtBuilder::new_qml_module(
        QmlModule::new("io.bamboo.store")
            .qml_file("qml/Main.qml")
            .qml_file("qml/SelfTest.qml"),
    )
    .qt_module("Quick")
    .files(["src/backend.rs"])
    .build();
}

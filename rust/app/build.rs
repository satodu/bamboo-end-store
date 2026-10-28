use cxx_qt_build::{CxxQtBuilder, QmlFile, QmlModule};

fn main() {
    CxxQtBuilder::new_qml_module(
        QmlModule::new("io.bamboo.store")
            .qml_file("qml/Main.qml")
            .qml_file("qml/SelfTest.qml")
            .qml_file("qml/Gallery.qml")
            .qml_file("qml/ChamferPanel.qml")
            .qml_file("qml/Btn.qml")
            .qml_file("qml/Tag.qml")
            .qml_file("qml/PackageIcon.qml")
            .qml_file("qml/SearchField.qml")
            .qml_file("qml/NavItem.qml")
            .qml_file("qml/PackageCard.qml")
            .qml_file("qml/PackageRow.qml")
            .qml_file(QmlFile::from("qml/Theme.qml").singleton(true))
            .qml_file(QmlFile::from("qml/Style.qml").singleton(true)),
    )
    .qt_module("Quick")
    .files(["src/backend.rs", "src/theme_bridge.rs"])
    .build();
}

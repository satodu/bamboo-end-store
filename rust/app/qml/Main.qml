import QtQuick
import QtQuick.Controls
import io.bamboo.store

ApplicationWindow {
    id: root
    width: 1100
    height: 720
    visible: true
    title: "Bamboo End Store"

    Backend { id: backend }

    Column {
        anchors.centerIn: parent
        spacing: 12
        Label { text: backend.status; font.pixelSize: 22 }
        Label { text: "sources: " + backend.sources() }
    }
}

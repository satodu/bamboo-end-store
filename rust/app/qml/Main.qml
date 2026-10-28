import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Functional bridge check. Real design arrives with the Matugen theme and components.
ApplicationWindow {
    id: root
    width: 1100
    height: 720
    visible: true
    title: "Bamboo End Store"

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string lastEvent: ""
    property int page: 1
    readonly property int pageSize: JSON.parse(backend.settingsJson()).search_limit

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.lastEvent = name + " " + action + " (exit " + code + ")"
        }
    }

    Timer {
        id: debounce
        interval: 350
        onTriggered: { root.page = 1; backend.search(field.text, 1) }
    }

    RowLayout {
        anchors.fill: parent
        anchors.margins: 16
        spacing: 16

        ColumnLayout {
            Layout.fillWidth: true
            Layout.fillHeight: true
            spacing: 8

            TextField {
                id: field
                Layout.fillWidth: true
                placeholderText: "Search pacman, AUR, Flatpak"
                onTextChanged: debounce.restart()
            }

            RowLayout {
                Label {
                    text: backend.busy ? "working..." : backend.total + " results"
                    opacity: 0.7
                    Layout.fillWidth: true
                }
                Button {
                    text: "Prev"
                    enabled: root.page > 1 && !backend.busy
                    onClicked: backend.search(field.text, --root.page)
                }
                Label { text: root.page + " / " + Math.max(1, Math.ceil(backend.total / root.pageSize)) }
                Button {
                    text: "Next"
                    enabled: root.page * root.pageSize < backend.total && !backend.busy
                    onClicked: backend.search(field.text, ++root.page)
                }
            }

            ListView {
                Layout.fillWidth: true
                Layout.fillHeight: true
                clip: true
                model: root.packages
                delegate: ItemDelegate {
                    width: ListView.view.width
                    text: (modelData.display_name || modelData.name) + "   " + modelData.version
                          + "   [" + modelData.source + "]" + (modelData.installed ? "   installed" : "")
                    onClicked: {
                        root.current = modelData
                        backend.loadDetails(modelData.name, modelData.source)
                    }
                }
            }
        }

        ColumnLayout {
            Layout.preferredWidth: 360
            Layout.fillHeight: true
            spacing: 8
            visible: root.current !== null

            Label {
                text: root.current ? (root.current.display_name || root.current.name) : ""
                font.pixelSize: 22
                wrapMode: Text.Wrap
                Layout.fillWidth: true
            }
            Label {
                text: root.info ? (root.info["Description"] || "") : ""
                wrapMode: Text.Wrap
                Layout.fillWidth: true
            }
            Label {
                text: root.info ? "Version " + (root.info["Version"] || "?") + "  ·  " + (root.info["Licenses"] || "") : ""
                opacity: 0.7
            }
            RowLayout {
                Button {
                    text: "Install"
                    enabled: !backend.busy && root.current && !root.current.installed
                    onClicked: backend.install(root.current.name, root.current.source)
                }
                Button {
                    text: "Remove"
                    enabled: !backend.busy && root.current && root.current.installed
                    onClicked: backend.remove(root.current.name, root.current.source)
                }
            }
            Label { text: root.lastEvent; Layout.fillWidth: true; wrapMode: Text.Wrap }
            Item { Layout.fillHeight: true }
        }
    }
}

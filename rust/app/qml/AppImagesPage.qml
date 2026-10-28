import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import QtQuick.Dialogs
import io.bamboo.store

// Manage standalone AppImages: add, integrate into the menu, launch, remove.
Item {
    id: root

    /// The app's settings Backend (owns `settings` and `setSetting`).
    property var settingsBackend
    readonly property var s: JSON.parse(settingsBackend.settings)
    readonly property string viewMode: s.appimages_view_mode === "list" ? "list" : "grid"

    property var apps: JSON.parse(backend.results)
    property string status: ""
    property bool statusFailed: false
    property var pendingRemoval: null

    function reload() { backend.loadAppImages() }
    function pathOf(url) { return decodeURIComponent(url.toString().replace("file://", "")) }
    function register(path) { backend.registerAppImage(path) }

    Component.onCompleted: reload()
    // Reload when the directory setting changes.
    readonly property string dir: s.appimage_path
    onDirChanged: reload()

    Backend {
        id: backend
        onAppImageFinished: (action, name, ok, message) => {
            const label = { "register": "integrated", "remove": "removed", "launch": "launched" }[action]
            root.statusFailed = !ok
            root.status = ok ? name + " " + label : name + " failed: " + message
            if (action !== "launch") root.reload()
        }
    }

    FileDialog {
        id: picker
        title: "Select AppImage"
        nameFilters: ["AppImage (*.AppImage *.appimage)", "All files (*)"]
        onAccepted: root.register(root.pathOf(selectedFile))
    }

    DropArea {
        id: drop
        anchors.fill: parent
        keys: ["text/uri-list"]
        onDropped: drop => {
            for (const u of drop.urls) {
                const p = root.pathOf(u)
                if (p.toLowerCase().endsWith(".appimage")) root.register(p)
            }
        }
    }

    ColumnLayout {
        anchors.fill: parent
        anchors.margins: 28
        spacing: 18

        RowLayout {
            Layout.fillWidth: true
            spacing: 16

            ColumnLayout {
                Layout.fillWidth: true
                spacing: 4
                Text {
                    text: "APPIMAGES"
                    color: Theme.fgSurface
                    font.family: Style.displayFont
                    font.pixelSize: 46
                    font.weight: Font.ExtraBold
                    font.letterSpacing: 2
                    Rectangle {
                        anchors.left: parent.right; anchors.leftMargin: 3
                        anchors.bottom: parent.bottom; anchors.bottomMargin: 9
                        width: 7; height: 7; color: Theme.primary
                    }
                }
                Text {
                    text: "Manage and integrate standalone AppImage applications."
                    color: Theme.fgSurfaceVariant
                    font.pixelSize: 14
                }
            }

            Segmented {
                options: [{ label: "Grid", value: "grid" }, { label: "List", value: "list" }]
                current: root.viewMode
                onActivated: v => root.settingsBackend.setSetting("appimages_view_mode", JSON.stringify(v))
            }
            Btn { text: "Add AppImage"; enabled: !backend.busy; onClicked: picker.open() }
        }

        RowLayout {
            Layout.fillWidth: true
            spacing: 14

            Text {
                text: root.s.appimage_path && root.s.appimage_path.length ? root.s.appimage_path : "~/Applications"
                color: Theme.fgSurfaceVariant
                font.family: Style.mono
                font.pixelSize: 11
                font.letterSpacing: 1
                elide: Text.ElideMiddle
                Layout.maximumWidth: 420
            }
            Text {
                text: root.apps.length + " FOUND"
                color: Theme.fgSurfaceVariant
                font.family: Style.mono
                font.pixelSize: 11
                font.letterSpacing: 1.5
            }
            RuneSpinner { running: backend.busy }
            RowLayout {
                visible: root.status.length > 0
                spacing: 8
                Rune { name: root.statusFailed ? "error" : "success"; size: 16; color: root.statusFailed ? Theme.error : Theme.primary }
                Text {
                    text: root.status
                    color: Theme.fgSurface
                    font.pixelSize: 12
                    elide: Text.ElideRight
                    Layout.maximumWidth: 480
                }
            }
            Item { Layout.fillWidth: true }
        }

        Item {
            Layout.fillWidth: true
            Layout.fillHeight: true

            GridView {
                id: grid
                anchors.fill: parent
                clip: true
                visible: root.viewMode === "grid" && root.apps.length > 0
                model: root.viewMode === "grid" ? root.apps : []
                readonly property int columns: Math.max(1, Math.floor(width / 290))
                cellWidth: Math.floor(width / columns)
                cellHeight: 206
                boundsBehavior: Flickable.StopAtBounds
                ScrollBar.vertical: ScrollBar {}

                delegate: Item {
                    id: cell
                    required property var modelData
                    required property int index
                    width: grid.cellWidth
                    height: grid.cellHeight
                    Reveal {
                        anchors.fill: parent
                        anchors.margins: 8
                        autoPlay: cell.index < 18
                        delay: Math.min(cell.index, 17) * 28
                        AppImageCard {
                            anchors.fill: parent
                            app: cell.modelData
                            busy: backend.busy
                            onLaunchRequested: backend.launchAppImage(cell.modelData.path)
                            onIntegrateRequested: backend.registerAppImage(cell.modelData.path)
                            onRemoveRequested: { root.pendingRemoval = cell.modelData
                                confirm.open("Delete " + cell.modelData.display_name + "?",
                                             "The file, its menu entry and its icon will be deleted. This cannot be undone.") }
                        }
                    }
                }
            }

            ListView {
                id: list
                anchors.fill: parent
                clip: true
                visible: root.viewMode === "list" && root.apps.length > 0
                model: root.viewMode === "list" ? root.apps : []
                boundsBehavior: Flickable.StopAtBounds
                ScrollBar.vertical: ScrollBar {}

                delegate: Item {
                    id: rowItem
                    required property var modelData
                    required property int index
                    width: list.width
                    height: 64
                    Reveal {
                        anchors.fill: parent
                        autoPlay: rowItem.index < 14
                        delay: Math.min(rowItem.index, 13) * 24
                        slide: 6
                        AppImageRow {
                            anchors.fill: parent
                            app: rowItem.modelData
                            busy: backend.busy
                            onLaunchRequested: backend.launchAppImage(rowItem.modelData.path)
                            onIntegrateRequested: backend.registerAppImage(rowItem.modelData.path)
                            onRemoveRequested: { root.pendingRemoval = rowItem.modelData
                                confirm.open("Delete " + rowItem.modelData.display_name + "?",
                                             "The file, its menu entry and its icon will be deleted. This cannot be undone.") }
                        }
                    }
                }
            }

            Plinth {
                anchors.centerIn: parent
                width: Math.min(parent.width - 40, 440)
                height: Math.min(parent.height - 40, 340)
                visible: root.apps.length === 0 && !backend.busy
                caption: "FIG. — NO APPIMAGE."
                actionText: "Register AppImage"
                onAction: picker.open()
            }

            // Drag hint while a file hovers the page.
            ChamferPanel {
                anchors.fill: parent
                visible: drop.containsDrag
                fill: Qt.alpha(Theme.primary, 0.08)
                stroke: Theme.primary
                dashed: true
                padding: 0
                Text {
                    anchors.centerIn: parent
                    text: "DROP TO REGISTER"
                    color: Theme.primary
                    font.family: Style.displayFont
                    font.pixelSize: 34
                    font.weight: Font.ExtraBold
                    font.letterSpacing: 3
                }
            }
        }
    }

    Confirm {
        id: confirm
        acceptText: "Delete"
        onAccepted: if (root.pendingRemoval) backend.removeAppImage(root.pendingRemoval.path)
    }
}

import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Explore screen built from the Galdr/Matugen components. The other tabs arrive in stage 6.
ApplicationWindow {
    id: root
    width: 1280
    height: 800
    minimumWidth: 900
    minimumHeight: 560
    visible: true
    title: "Bamboo End Store"
    color: Theme.surface

    // Qt Quick Controls read these, so any stock control still follows the palette.
    palette {
        window: Theme.surface
        windowText: Theme.fgSurface
        base: Theme.surfaceContainerLow
        text: Theme.fgSurface
        placeholderText: Theme.fgSurfaceVariant
        button: Theme.surfaceContainerHigh
        buttonText: Theme.fgSurface
        highlight: Theme.primary
        highlightedText: Theme.fgPrimary
        light: Theme.surfaceContainerHighest
        mid: Theme.outline
        dark: Theme.outlineVariant
    }

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string lastEvent: ""
    property int page: 1
    readonly property int pageSize: JSON.parse(backend.settingsJson()).search_limit
    readonly property int pageCount: Math.max(1, Math.ceil(backend.total / pageSize))

    function runSearch(q) { field.text = q }

    function setTheme(key, value) {
        backend.setSetting(key, JSON.stringify(value))
        Theme.reload()
    }

    function requestInstall(pkg) { backend.install(pkg.name, pkg.source) }
    function requestRemove(pkg) { backend.remove(pkg.name, pkg.source) }

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.lastEvent = code === 0 ? name + " " + action
                                        : name + " failed (exit " + code + ")"
        }
    }

    Timer {
        id: debounce
        interval: 350
        onTriggered: { root.page = 1; backend.search(field.text, 1) }
    }

    RowLayout {
        anchors.fill: parent
        spacing: 0

        // Sidebar
        Rectangle {
            Layout.preferredWidth: 240
            Layout.fillHeight: true
            color: Theme.surfaceContainerLow

            Rectangle {
                anchors.right: parent.right
                width: Style.border
                height: parent.height
                color: Theme.outlineVariant
            }

            ColumnLayout {
                anchors.fill: parent
                spacing: 0

                Item {
                    Layout.fillWidth: true
                    Layout.preferredHeight: 76
                    Row {
                        anchors.verticalCenter: parent.verticalCenter
                        anchors.left: parent.left
                        anchors.leftMargin: 20
                        spacing: 10
                        Text {
                            text: "BAMBOO"
                            color: Theme.fgSurface
                            font.pixelSize: 20
                            font.weight: Font.Black
                            font.letterSpacing: 3
                        }
                        Text { text: "."; color: Theme.primary; font.pixelSize: 20; font.weight: Font.Black }
                    }
                }

                NavItem { Layout.fillWidth: true; text: "Explore"; glyph: "◈"; active: true }
                NavItem { Layout.fillWidth: true; text: "Installed"; glyph: "◆" }
                NavItem { Layout.fillWidth: true; text: "AppImages"; glyph: "▣" }
                NavItem { Layout.fillWidth: true; text: "Settings"; glyph: "✦" }

                Item { Layout.fillHeight: true }

                ColumnLayout {
                    Layout.margins: 16
                    Layout.fillWidth: true
                    spacing: 8

                    Text {
                        text: "THEME  ·  " + Theme.source + "  ·  " + (Theme.dark ? "dark" : "light")
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 10
                        font.letterSpacing: 1.2
                    }
                    Flow {
                        Layout.fillWidth: true
                        spacing: 6
                        Btn { text: "System"; variant: "ghost"; implicitHeight: 28; onClicked: root.setTheme("theme_source", "system") }
                        Btn { text: "Bamboo"; variant: "ghost"; implicitHeight: 28
                              onClicked: { backend.setSetting("theme", '"theme-bamboo"'); root.setTheme("theme_source", "preset") } }
                        Btn { text: "Catppuccin"; variant: "ghost"; implicitHeight: 28
                              onClicked: { backend.setSetting("theme", '"theme-catppuccin"'); root.setTheme("theme_source", "preset") } }
                        Btn { text: "Nord"; variant: "ghost"; implicitHeight: 28
                              onClicked: { backend.setSetting("theme", '"theme-nord"'); root.setTheme("theme_source", "preset") } }
                        Btn { text: "Gruvbox"; variant: "ghost"; implicitHeight: 28
                              onClicked: { backend.setSetting("theme", '"theme-gruvbox"'); root.setTheme("theme_source", "preset") } }
                        Btn { text: "Dark"; variant: "outline"; implicitHeight: 28; onClicked: root.setTheme("theme_mode", "dark") }
                        Btn { text: "Light"; variant: "outline"; implicitHeight: 28; onClicked: root.setTheme("theme_mode", "light") }
                    }
                }
            }
        }

        // Main column
        ColumnLayout {
            Layout.fillWidth: true
            Layout.fillHeight: true
            Layout.margins: 24
            spacing: 16

            RowLayout {
                Layout.fillWidth: true
                spacing: 16

                SearchField {
                    id: field
                    Layout.fillWidth: true
                    placeholder: "Search pacman, AUR, Flatpak"
                    onTextChanged: debounce.restart()
                }
                Text {
                    text: backend.busy ? "working..." : backend.total + " results"
                    color: Theme.fgSurfaceVariant
                    font.pixelSize: 12
                }
            }

            GridView {
                id: grid
                Layout.fillWidth: true
                Layout.fillHeight: true
                clip: true
                model: root.packages
                readonly property int columns: Math.max(1, Math.floor(width / 300))
                cellWidth: Math.floor(width / columns)
                cellHeight: 200
                boundsBehavior: Flickable.StopAtBounds
                ScrollBar.vertical: ScrollBar {}

                delegate: Item {
                    width: grid.cellWidth
                    height: grid.cellHeight
                    PackageCard {
                        anchors.fill: parent
                        anchors.margins: 8
                        pkg: modelData
                        busy: backend.busy
                        onOpened: { root.current = modelData; backend.loadDetails(modelData.name, modelData.source) }
                        onInstallRequested: root.requestInstall(modelData)
                        onRemoveRequested: root.requestRemove(modelData)
                    }
                }

                Text {
                    anchors.centerIn: parent
                    visible: root.packages.length === 0 && !backend.busy
                    text: field.text.length ? "No packages found" : "Type to search"
                    color: Theme.fgSurfaceVariant
                    font.pixelSize: 14
                }
            }

            RowLayout {
                Layout.fillWidth: true
                visible: root.pageCount > 1
                spacing: 12
                Item { Layout.fillWidth: true }
                Btn { text: "Prev"; variant: "outline"; enabled: root.page > 1 && !backend.busy
                      onClicked: backend.search(field.text, --root.page) }
                Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.pixelSize: 12 }
                Btn { text: "Next"; variant: "outline"; enabled: root.page < root.pageCount && !backend.busy
                      onClicked: backend.search(field.text, ++root.page) }
            }
        }

        // Details
        ChamferPanel {
            Layout.preferredWidth: root.current ? 340 : 0
            Layout.fillHeight: true
            Layout.topMargin: 24
            Layout.bottomMargin: 24
            Layout.rightMargin: root.current ? 24 : 0
            visible: root.current !== null
            fill: Theme.surfaceContainerLow
            padding: 20

            ColumnLayout {
                anchors.fill: parent
                spacing: 12

                RowLayout {
                    Layout.fillWidth: true
                    spacing: 12
                    PackageIcon { name: root.current ? (root.current.display_name || root.current.name) : ""; size: 52 }
                    Text {
                        Layout.fillWidth: true
                        text: root.current ? (root.current.display_name || root.current.name) : ""
                        color: Theme.fgSurface
                        font.pixelSize: 20
                        font.weight: Font.Bold
                        wrapMode: Text.Wrap
                    }
                    Text {
                        text: "✕"
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 14
                        MouseArea { anchors.fill: parent; anchors.margins: -8; cursorShape: Qt.PointingHandCursor
                                    onClicked: root.current = null }
                    }
                }

                Text {
                    Layout.fillWidth: true
                    text: root.info ? (root.info["Description"] || "") : ""
                    color: Theme.fgSurface
                    font.pixelSize: 13
                    wrapMode: Text.Wrap
                    maximumLineCount: 6
                    elide: Text.ElideRight
                }

                Repeater {
                    model: ["Version", "Licenses", "Maintainer", "Build Date", "Installed Size", "Download Size"]
                    delegate: RowLayout {
                        required property string modelData
                        Layout.fillWidth: true
                        visible: root.info && !!root.info[modelData]
                        Text { text: modelData; color: Theme.fgSurfaceVariant; font.pixelSize: 11; Layout.preferredWidth: 100 }
                        Text {
                            Layout.fillWidth: true
                            text: root.info ? (root.info[modelData] || "") : ""
                            color: Theme.fgSurface
                            font.pixelSize: 12
                            font.family: Style.mono
                            elide: Text.ElideRight
                        }
                    }
                }

                Item { Layout.fillHeight: true }

                Text {
                    Layout.fillWidth: true
                    visible: root.lastEvent.length > 0
                    text: root.lastEvent
                    color: Theme.primary
                    font.pixelSize: 12
                    wrapMode: Text.Wrap
                }

                RowLayout {
                    Layout.fillWidth: true
                    Btn {
                        Layout.fillWidth: true
                        text: root.current && root.current.installed ? "Remove" : "Install"
                        variant: root.current && root.current.installed ? "outline" : "filled"
                        busy: backend.busy
                        onClicked: root.current.installed ? root.requestRemove(root.current) : root.requestInstall(root.current)
                    }
                }
            }
        }
    }
}

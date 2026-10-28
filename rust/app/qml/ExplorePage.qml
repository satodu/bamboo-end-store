import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Search across pacman, AUR and Flatpak.
Item {
    id: root

    /// Settings object (parsed JSON) from the app's settings backend.
    property var appSettings: ({ search_limit: 50 })

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string status: ""
    property int page: 1
    readonly property int pageSize: Math.max(1, appSettings.search_limit)
    readonly property int pageCount: Math.max(1, Math.ceil(backend.total / pageSize))

    function runSearch(q) { field.text = q }
    // Automation hook (screenshot harness): open the first result.
    function openFirst() {
        if (packages.length === 0) return
        current = packages[0]
        backend.loadDetails(current.name, current.source)
    }
    onPageSizeChanged: if (field.text.length) { page = 1; backend.search(field.text, 1) }

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.status = code === 0 ? name + " " + action : name + " failed (exit " + code + ")"
            if (field.text.length) backend.search(field.text, root.page)
        }
    }

    Timer {
        id: debounce
        interval: 350
        onTriggered: { root.page = 1; backend.search(field.text, 1) }
    }

    RowLayout {
        anchors.fill: parent
        anchors.margins: 24
        spacing: 24

        ColumnLayout {
            Layout.fillWidth: true
            Layout.fillHeight: true
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
                RuneSpinner { running: backend.busy }
                Text {
                    visible: !backend.busy
                    text: backend.total + " RESULTS"
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 1.5
                }
            }

            Item {
                Layout.fillWidth: true
                Layout.fillHeight: true

                GridView {
                    id: grid
                    anchors.fill: parent
                    clip: true
                    visible: root.packages.length > 0
                    model: root.packages
                    readonly property int columns: Math.max(1, Math.floor(width / 300))
                    cellWidth: Math.floor(width / columns)
                    cellHeight: 200
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

                            PackageCard {
                                anchors.fill: parent
                                pkg: cell.modelData
                                busy: backend.busy
                                onOpened: { root.current = cell.modelData; backend.loadDetails(cell.modelData.name, cell.modelData.source) }
                                onInstallRequested: backend.install(cell.modelData.name, cell.modelData.source)
                                onRemoveRequested: backend.remove(cell.modelData.name, cell.modelData.source)
                            }
                        }
                    }
                }

                Plinth {
                    anchors.centerIn: parent
                    width: Math.min(parent.width - 40, 420)
                    height: Math.min(parent.height - 40, 320)
                    visible: root.packages.length === 0 && !backend.busy
                    caption: field.text.length ? "FIG. — NO PACKAGE." : "FIG. — AWAITING QUERY."
                }
            }

            RowLayout {
                Layout.fillWidth: true
                visible: root.pageCount > 1
                spacing: 12
                Item { Layout.fillWidth: true }
                Btn { text: "Prev"; variant: "outline"; enabled: root.page > 1 && !backend.busy
                      onClicked: backend.search(field.text, --root.page) }
                Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.family: Style.mono; font.pixelSize: 12 }
                Btn { text: "Next"; variant: "outline"; enabled: root.page < root.pageCount && !backend.busy
                      onClicked: backend.search(field.text, ++root.page) }
            }
        }

        Loader {
            Layout.preferredWidth: active ? 340 : 0
            Layout.fillHeight: true
            active: root.current !== null
            sourceComponent: DetailsPanel {
                pkg: root.current
                info: root.info
                status: root.status
                busy: backend.busy
                onClosed: root.current = null
                onInstallRequested: backend.install(root.current.name, root.current.source)
                onRemoveRequested: backend.remove(root.current.name, root.current.source)
            }
        }
    }
}

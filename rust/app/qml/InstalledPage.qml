import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Everything installed: pacman, AUR (foreign) and Flatpak.
Item {
    id: root

    property var appSettings: ({ search_limit: 50 })

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string status: ""
    property int page: 1
    readonly property int pageSize: Math.max(1, appSettings.search_limit)
    readonly property int pageCount: Math.max(1, Math.ceil(backend.total / pageSize))

    function reload() { backend.loadInstalled(filter.text, page) }
    Component.onCompleted: reload()
    onPageSizeChanged: { page = 1; reload() }

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.status = code !== 0 ? Tr.f("%s failed (exit %s)", name, code)
                : action === "installed" ? Tr.f("%s installed", name) : Tr.f("%s removed", name)
            if (code === 0) root.current = null
            root.reload()
        }
    }

    Timer { id: debounce; interval: 250; onTriggered: { root.page = 1; root.reload() } }

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

                Field {
                    id: filter
                    Layout.fillWidth: true
                    rune: "installed"
                    placeholder: Tr.t("Filter installed packages")
                    clearable: true
                    ruleAlways: true
                    onTextChanged: debounce.restart()
                }
                RuneSpinner { running: backend.busy }
                Text {
                    visible: !backend.busy
                    text: Tr.f("%s packages installed", backend.total)
                    font.capitalization: Font.AllUppercase
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 1.5
                }
            }

            Item {
                Layout.fillWidth: true
                Layout.fillHeight: true

                ListView {
                    id: list
                    anchors.fill: parent
                    clip: true
                    visible: root.packages.length > 0
                    model: root.packages
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

                            PackageRow {
                                anchors.fill: parent
                                pkg: rowItem.modelData
                                showInstalledTag: false
                                busy: backend.busy
                                onOpened: { root.current = rowItem.modelData; backend.loadDetails(rowItem.modelData.name, rowItem.modelData.source) }
                                onRemoveRequested: backend.remove(rowItem.modelData.name, rowItem.modelData.source)
                            }
                        }
                    }
                }

                Plinth {
                    anchors.centerIn: parent
                    width: Math.min(parent.width - 40, 420)
                    height: Math.min(parent.height - 40, 320)
                    visible: root.packages.length === 0 && !backend.busy
                    caption: filter.text.length ? Tr.t("FIG. — NO MATCH.") : Tr.t("FIG. — NO SHARD.")
                }
            }

            RowLayout {
                Layout.fillWidth: true
                visible: root.pageCount > 1
                spacing: 12
                Item { Layout.fillWidth: true }
                Btn { text: Tr.t("Prev"); variant: "outline"; enabled: root.page > 1 && !backend.busy
                      onClicked: { root.page--; root.reload() } }
                Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.family: Style.mono; font.pixelSize: 12 }
                Btn { text: Tr.t("Next"); variant: "outline"; enabled: root.page < root.pageCount && !backend.busy
                      onClicked: { root.page++; root.reload() } }
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

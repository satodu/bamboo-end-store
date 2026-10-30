import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Software that needs more than one package manager call: multilib, Wine, 32-bit libraries.
// Each entry runs its install script in a terminal.
Item {
    id: root

    property var appSettings: ({ search_limit: 50 })
    property var settingsBackend
    readonly property string viewMode: appSettings.view_mode === "list" ? "list" : "grid"

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string status: ""

    function reload() { backend.loadBambooInstalls() }
    Component.onCompleted: reload()

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.status = code !== 0 ? Tr.f("%s failed (exit %s)", name, code)
                : action === "installed" ? Tr.f("%s installed", name) : Tr.f("%s removed", name)
            root.reload()
        }
    }

    ConfirmRemove { id: removal; owner: backend }

    RowLayout {
        anchors.fill: parent
        anchors.margins: 28
        spacing: 24

        ColumnLayout {
            Layout.fillWidth: true
            Layout.fillHeight: true
            spacing: 16

            RowLayout {
                Layout.fillWidth: true
                spacing: 16

                Rune { name: "bamboo"; size: 44; color: Theme.primary; Layout.alignment: Qt.AlignTop }
                ColumnLayout {
                    spacing: 4
                    Text {
                        text: Tr.t("Bamboo Installs")
                        color: Theme.fgSurface
                        font.family: Style.displayFont
                        font.pixelSize: 46
                        font.weight: Font.ExtraBold
                        font.capitalization: Font.AllUppercase
                        font.letterSpacing: 2
                        Rectangle {
                            anchors.left: parent.right; anchors.leftMargin: 3
                            anchors.bottom: parent.bottom; anchors.bottomMargin: 9
                            width: 7; height: 7; color: Theme.primary
                        }
                    }
                    Text {
                        text: Tr.t("Custom Scripts & Optimized Packages")
                        color: Theme.fgSurfaceVariant
                        font.family: Style.mono
                        font.pixelSize: 11
                        font.letterSpacing: 1.5
                        font.capitalization: Font.AllUppercase
                    }
                }
                Item { Layout.fillWidth: true }
                RuneSpinner { running: backend.busy }
                Segmented {
                    options: [{ label: Tr.t("Grid"), value: "grid" }, { label: Tr.t("List"), value: "list" }]
                    current: root.viewMode
                    onActivated: v => root.settingsBackend.setSetting("view_mode", JSON.stringify(v))
                }
            }

            Text {
                Layout.fillWidth: true
                Layout.maximumWidth: 900
                text: Tr.t("Esses pacotes requerem passos extras de instalação (como dependências de 32 bits, wine ou aceitação de termos) que os gerenciadores tradicionais falham em lidar automaticamente. O Bamboo automatiza esses passos para você usando scripts especiais.")
                color: Theme.fgSurfaceVariant
                font.pixelSize: 13
                wrapMode: Text.Wrap
                lineHeight: 1.3
            }

            Item {
                Layout.fillWidth: true
                Layout.fillHeight: true

                PackageResults {
                    anchors.fill: parent
                    packages: root.packages
                    viewMode: root.viewMode
                    busy: backend.busy
                    onOpened: pkg => { root.current = pkg; backend.loadDetails(pkg.name, pkg.source) }
                    onInstallRequested: pkg => backend.install(pkg.name, pkg.source)
                    onRemoveRequested: pkg => removal.ask(pkg)
                }

                LoadingBody { active: backend.busy || root.packages.length === 0; caption: Tr.t("Loading Scripts...") }
            }
        }

        Loader {
            Layout.preferredWidth: active ? 340 : 0
            Layout.fillHeight: true
            active: root.current !== null
            sourceComponent: DetailsPanel {
                pkg: root.current
                info: root.info
                comments: JSON.parse(backend.comments)
                commands: JSON.parse(backend.consoleCommands(root.current.name, root.current.source))
                onCommentsRequested: backend.loadComments(root.current.name)
                status: root.status
                busy: backend.busy
                onClosed: root.current = null
                onInstallRequested: backend.install(root.current.name, root.current.source)
                onRemoveRequested: removal.ask(root.current)
            }
        }
    }
}

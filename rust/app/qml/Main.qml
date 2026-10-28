import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

ApplicationWindow {
    id: root
    width: 1280
    height: 800
    minimumWidth: 960
    minimumHeight: 600
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

    readonly property var pages: ["explore", "installed", "bamboo", "appimages", "settings"]
    property string page: "explore"
    property var visited: ({ "explore": true })
    readonly property var appSettings: JSON.parse(appBackend.settings)

    function openPage(name) {
        if (name === page) return
        // a new object: QML does not notice a mutated one, so the Loader would never activate
        visited = Object.assign({}, visited, { [name]: true })
        page = name
        pageReveal.play()
    }

    // First launch only: the sidebar's machine info starts as runes and settles into text.
    property bool intro: false
    Component.onCompleted: intro = !JSON.parse(appBackend.settings).intro_seen

    // Owns the settings JSON for the whole app; each screen has its own Backend for data.
    Backend { id: appBackend }

    RowLayout {
        anchors.fill: parent
        spacing: 0

        // Sidebar
        Rectangle {
            Layout.preferredWidth: 236
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
                    Layout.preferredHeight: 84

                    Reveal {
                        anchors.fill: parent
                        Row {
                            anchors.verticalCenter: parent.verticalCenter
                            anchors.left: parent.left
                            anchors.leftMargin: 22
                            Text {
                                text: "BAMBOO"
                                color: Theme.fgSurface
                                font.family: Style.displayFont
                                font.pixelSize: 32
                                font.weight: Font.ExtraBold
                                font.letterSpacing: 2
                            }
                            Text {
                                text: "."
                                color: Theme.primary
                                font.family: Style.displayFont
                                font.pixelSize: 32
                                font.weight: Font.ExtraBold
                            }
                        }
                    }
                }

                NavItem { Layout.fillWidth: true; text: Tr.t("Explore"); rune: "explore"; active: root.page === "explore"; onClicked: root.openPage("explore") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Installed"); rune: "installed"; active: root.page === "installed"; onClicked: root.openPage("installed") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Bamboo Installs"); rune: "bamboo"; active: root.page === "bamboo"; onClicked: root.openPage("bamboo") }
                NavItem { Layout.fillWidth: true; text: Tr.t("AppImages"); rune: "appimages"; active: root.page === "appimages"; onClicked: root.openPage("appimages") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Settings"); rune: "settings"; active: root.page === "settings"; onClicked: root.openPage("settings") }

                Item { Layout.fillHeight: true }

                SystemInfoBlock {
                    Layout.leftMargin: 22
                    Layout.rightMargin: 12
                    Layout.bottomMargin: 18
                    info: JSON.parse(appBackend.systemInfo())
                    intro: root.intro
                    // remembered, so the intro plays only once
                    onIntroFinished: { appBackend.setSetting("intro_seen", "true"); root.intro = false }
                }

                RuneRuler {
                    Layout.alignment: Qt.AlignLeft
                    Layout.leftMargin: 26
                    Layout.bottomMargin: 22
                    runes: "ᛒᚨᛗᛒᛟᛟ"
                }
            }
        }

        // Content
        Item {
            Layout.fillWidth: true
            Layout.fillHeight: true

            Rectangle { anchors.fill: parent; color: Theme.surface }
            RuneField { anchors.fill: parent }

            Reveal {
                id: pageReveal
                anchors.fill: parent
                slide: 12

                StackLayout {
                    anchors.fill: parent
                    currentIndex: root.pages.indexOf(root.page)

                    ExplorePage { id: explore; appSettings: root.appSettings; settingsBackend: appBackend }

                    Loader {
                        active: !!root.visited["installed"]
                        sourceComponent: InstalledPage { appSettings: root.appSettings; settingsBackend: appBackend }
                    }

                    Loader {
                        active: !!root.visited["bamboo"]
                        sourceComponent: BambooInstallsPage { appSettings: root.appSettings; settingsBackend: appBackend }
                    }

                    Loader {
                        active: !!root.visited["appimages"]
                        sourceComponent: AppImagesPage { settingsBackend: appBackend }
                    }

                    SettingsPage { backend: appBackend }
                }
            }
        }
    }

    // Used by the screenshot harness.
    function runSearch(q) { explore.runSearch(q) }
    function openFirst() { explore.openFirst() }
    function openCategory(name) { explore.openCategory(name) }
}

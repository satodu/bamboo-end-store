import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import Qt.labs.platform as Platform
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

    /// Clicking the logo: the Explore home (the categories), from wherever we are.
    function goHome() {
        explore.goHome()
        openPage("explore")
    }

    /// Show the window again from the tray (also brings it to the front).
    function bringToFront() {
        show()
        raise()
        requestActivate()
    }
    function toggleVisible() { visible ? hide() : bringToFront() }

    // The program no longer ends with its last window (the tray keeps it alive), so closing
    // either hides it to the tray, if that is wanted and there is a tray, or quits.
    onClosing: close => {
        if (appSettings.close_to_tray && tray.available) {
            close.accepted = false
            hide()
        } else {
            Qt.quit()
        }
    }

    Platform.SystemTrayIcon {
        id: tray
        visible: true
        icon.source: Qt.resolvedUrl("../icons/app-icon.png")
        tooltip: "Bamboo End Store"
        onActivated: reason => { if (reason === Platform.SystemTrayIcon.Trigger) root.toggleVisible() }

        menu: Platform.Menu {
            Platform.MenuItem { text: Tr.t("Open Store"); onTriggered: root.bringToFront() }
            Platform.MenuItem { separator: true }
            Platform.MenuItem { text: Tr.t("Update System"); onTriggered: if (!appBackend.busy) appBackend.updateSystem() }
            Platform.MenuItem { separator: true }
            Platform.MenuItem { text: Tr.t("Quit"); onTriggered: Qt.quit() }
        }
    }

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
                            id: logo
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

                        // The logo is a button: it goes to the Explore home.
                        MouseArea {
                            anchors.fill: logo
                            anchors.margins: -8
                            cursorShape: Qt.PointingHandCursor
                            onClicked: root.goHome()
                        }
                    }
                }

                NavItem { Layout.fillWidth: true; text: Tr.t("Explore"); rune: "explore"; active: root.page === "explore"; onClicked: root.openPage("explore") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Installed"); rune: "installed"; active: root.page === "installed"; onClicked: root.openPage("installed") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Bamboo Installs"); rune: "bamboo"; active: root.page === "bamboo"; onClicked: root.openPage("bamboo") }
                NavItem { Layout.fillWidth: true; text: Tr.t("AppImages"); rune: "appimages"; active: root.page === "appimages"; onClicked: root.openPage("appimages") }
                // An action, not a page: runs the full system update in a terminal.
                NavItem {
                    Layout.fillWidth: true
                    text: appBackend.busy ? Tr.t("Running updates") : Tr.t("Update System")
                    rune: "update"
                    busy: appBackend.busy
                    onClicked: if (!appBackend.busy) appBackend.updateSystem()
                }
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

            // A terminal job (system update, Flatpak setup) is running: the work happens in
            // the terminal window, so say so over the page and block clicks until it ends.
            Item {
                id: jobOverlay
                anchors.fill: parent
                visible: opacity > 0
                opacity: appBackend.busy ? 1 : 0
                Behavior on opacity { NumberAnimation { duration: Style.quick; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease } }

                Rectangle { anchors.fill: parent; color: Qt.alpha(Theme.surface, 0.86) }
                MouseArea { anchors.fill: parent; hoverEnabled: true; preventStealing: true }

                ColumnLayout {
                    anchors.centerIn: parent
                    spacing: 18

                    Item {
                        Layout.alignment: Qt.AlignHCenter
                        implicitWidth: 190
                        implicitHeight: 190
                        RuneRing { anchors.fill: parent; size: 190 }
                        RuneSpinner { anchors.centerIn: parent; size: 44; running: jobOverlay.visible }
                    }
                    Text {
                        Layout.alignment: Qt.AlignHCenter
                        text: Tr.t("Running updates")
                        color: Theme.fgSurface
                        font.family: Style.displayFont
                        font.pixelSize: 28
                        font.capitalization: Font.AllUppercase
                    }
                    Text {
                        Layout.alignment: Qt.AlignHCenter
                        text: Tr.t("Working in the terminal window. Close it to cancel.")
                        color: Theme.fgSurfaceVariant
                        font.family: Style.mono
                        font.pixelSize: 11
                        font.letterSpacing: 1.5
                        font.capitalization: Font.AllUppercase
                    }
                }
            }
        }
    }

    // Used by the screenshot harness.
    function runSearch(q) { explore.runSearch(q) }
    function openFirst() { explore.openFirst() }
    function openCategory(name) { explore.openCategory(name) }
}

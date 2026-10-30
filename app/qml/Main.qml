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
    /// Update System always asks first; from the tray the window comes forward so the question is seen.
    function askUpdate() {
        if (appBackend.busy) return
        bringToFront()
        updateConfirm.open(Tr.t("Update the system?"),
            Tr.t("Opens a terminal and updates the official packages, the AUR and Flatpak, then offers to remove orphaned packages. It asks for your password."))
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
            Platform.MenuItem { text: Tr.t("Update System"); onTriggered: root.askUpdate() }
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

            // The empty lower half of the sidebar: big runes cut by the edges, behind the entries.
            RuneField {
                anchors.fill: parent
                anchors.rightMargin: Style.border
                spec: [
                    [0.00, 0.58, 240, 9, 1, 0.06, 31], [1.00, 0.72, 280, 12, 2, 0.07, 27],
                    [0.10, 0.84, 200, 17, 0, 0.05, 33], [0.92, 0.95, 260, 21, 1, 0.06, 29]
                ]
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

                NavLabel { text: Tr.t("Store") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Explore"); rune: "explore"; index: "01"; delay: 60; active: root.page === "explore"; onClicked: root.openPage("explore") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Installed"); rune: "installed"; index: "02"; delay: 100; active: root.page === "installed"; onClicked: root.openPage("installed") }
                NavItem { Layout.fillWidth: true; text: Tr.t("Bamboo Installs"); rune: "bamboo"; index: "03"; delay: 140; active: root.page === "bamboo"; onClicked: root.openPage("bamboo") }
                NavItem { Layout.fillWidth: true; text: Tr.t("AppImages"); rune: "appimages"; index: "04"; delay: 180; active: root.page === "appimages"; onClicked: root.openPage("appimages") }

                NavLabel { text: Tr.t("System") }
                // An action, not a page: runs the full system update in a terminal.
                NavItem {
                    Layout.fillWidth: true
                    text: appBackend.busy ? Tr.t("Running updates") : Tr.t("Update System")
                    rune: "update"
                    delay: 240
                    busy: appBackend.busy
                    onClicked: root.askUpdate()
                }
                NavItem { Layout.fillWidth: true; text: Tr.t("Settings"); rune: "settings"; index: "05"; delay: 280; active: root.page === "settings"; onClicked: root.openPage("settings") }

                Item { Layout.fillHeight: true }

                // The machine, on a small plate with the Galdr line work.
                ChamferPanel {
                    Layout.fillWidth: true
                    Layout.leftMargin: 12
                    Layout.rightMargin: 12
                    Layout.bottomMargin: 14
                    Layout.preferredHeight: sysinfo.implicitHeight + padding * 2
                    padding: 16
                    chamfer: Style.chamferSm
                    fill: Theme.surfaceContainer
                    details: true

                    SystemInfoBlock {
                        id: sysinfo
                        anchors.left: parent.left
                        anchors.right: parent.right
                        anchors.top: parent.top
                        info: JSON.parse(appBackend.systemInfo())
                        intro: root.intro
                        // remembered, so the intro plays only once
                        onIntroFinished: { appBackend.setSetting("intro_seen", "true"); root.intro = false }
                    }
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
            LoadingBody {
                active: appBackend.busy
                ringSize: 190
                title: Tr.t("Running updates")
                caption: Tr.t("Working in the terminal window. Close it to cancel.")
            }
        }
    }

    Confirm {
        id: updateConfirm
        danger: false
        acceptText: Tr.t("Update")
        onAccepted: appBackend.updateSystem()
    }

    // Used by the screenshot harness.
    function runSearch(q) { explore.runSearch(q) }
    function openFirst() { explore.openFirst() }
    function openCategory(name) { explore.openCategory(name) }
}

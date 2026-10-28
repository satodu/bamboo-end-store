import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import QtQuick.Dialogs
import io.bamboo.store

// Every change applies and saves immediately; there is no Save button.
Item {
    id: root

    /// The app's settings Backend (owns the `settings` JSON and `setSetting`).
    property var backend
    readonly property var s: JSON.parse(backend.settings)
    property string note: ""
    /// {installed, flathub}; empty until the first check finishes.
    readonly property var flatpak: JSON.parse(backend.flatpak)
    readonly property bool flatpakKnown: flatpak.installed !== undefined
    property string flatpakError: ""

    onVisibleChanged: if (visible) backend.checkFlatpak()
    Component.onCompleted: backend.checkFlatpak()

    Connections {
        target: root.backend
        function onOperationFinished(name, action, code) {
            if (name !== "Flatpak" && name !== "Flathub") return
            root.flatpakError = code === 0 ? "" : Tr.f("%s failed (exit %s)", name, code)
        }
    }

    function set(key, value) {
        backend.setSetting(key, JSON.stringify(value))
        if (key.indexOf("theme") === 0 || key === "reduce_motion") Theme.reload()
        if (key === "locale") Tr.reload()
    }
    function validHex(h) { return /^#[0-9a-fA-F]{6}$/.test(h) }

    readonly property var presets: [
        { label: "Bamboo", value: "theme-bamboo", color: "#7c3aed" },
        { label: "Catppuccin", value: "theme-catppuccin", color: "#cba6f7" },
        { label: "Nord", value: "theme-nord", color: "#88c0d0" },
        { label: "Gruvbox", value: "theme-gruvbox", color: "#fe8019" }
    ]

    ScrollView {
        id: scroll
        anchors.fill: parent
        contentWidth: availableWidth
        clip: true

        ColumnLayout {
            x: 28
            width: scroll.availableWidth - 56
            spacing: 18

            Item { Layout.preferredHeight: 10 }

            ColumnLayout {
                spacing: 4
                Text {
                    text: Tr.t("Settings")
                    font.capitalization: Font.AllUppercase
                    color: Theme.fgSurface
                    font.family: Style.displayFont
                    font.pixelSize: 46
                    font.weight: Font.ExtraBold
                    font.letterSpacing: 2
                    Rectangle {  // the full stop in the primary
                        anchors.left: parent.right; anchors.leftMargin: 3
                        anchors.bottom: parent.bottom; anchors.bottomMargin: 9
                        width: 7; height: 7; color: Theme.primary
                    }
                }
                Text {
                    text: Tr.t("Manage your repositories and preferences.")
                    color: Theme.fgSurfaceVariant
                    font.pixelSize: 14
                }
            }

            RowLayout {
                Layout.fillWidth: true
                Layout.alignment: Qt.AlignTop
                spacing: 18

                Repeater {
                    model: root.columnPlan
                    delegate: ColumnLayout {
                        id: column
                        required property var modelData
                        Layout.fillWidth: true
                        Layout.preferredWidth: 1
                        Layout.alignment: Qt.AlignTop
                        spacing: 18

                        Repeater {
                            model: column.modelData
                            delegate: Loader {
                                required property string modelData
                                Layout.fillWidth: true
                                sourceComponent: root.sectionComponents[modelData]
                                onLoaded: item.width = Qt.binding(() => width)
                            }
                        }
                    }
                }
            }


            Item { Layout.preferredHeight: 24 }
        }
    }

    // Sections are laid out in 1, 2 or 3 columns by window width, so a big or full-screen window
    // is used instead of one stretched column with controls far from their labels.
    readonly property var sectionComponents: ({
        "repos": sec_repos, "appearance": sec_appearance, "terminal": sec_terminal,
        "appimages": sec_appimages, "maintenance": sec_maintenance
    })
    readonly property var columnPlan: width < 1000
        ? [["repos", "appearance", "terminal", "appimages", "maintenance"]]
        : width < 1650
            ? [["repos", "terminal", "appimages", "maintenance"], ["appearance"]]
            : [["repos", "terminal"], ["appearance"], ["appimages", "maintenance"]]

    Component {
        id: sec_repos
            SettingsSection {
                title: Tr.t("Repositories")
                rune: "appimages"
                delay: 0
                SettingRow {
                    title: Tr.t("Arch User Repository")
                    description: Tr.t("Search and install thousands of community-maintained packages.")
                    Toggle { checked: root.s.enable_aur; onToggled: v => root.set("enable_aur", v) }
                }
                SettingRow {
                    title: Tr.t("Flatpak Integration")
                    description: Tr.t("Access Flathub's universal application ecosystem.")
                    Toggle {
                        checked: root.s.enable_flatpak
                        onToggled: v => { root.set("enable_flatpak", v); if (v) backend.checkFlatpak() }
                    }
                }

                // What Flatpak needs to work here, with a button to fix each missing piece.
                Item {
                    visible: root.s.enable_flatpak
                    Layout.fillWidth: true
                    Layout.preferredHeight: status.implicitHeight + 36

                    ChamferPanel {
                        anchors.fill: parent
                        padding: 18
                        chamfer: Style.chamferSm
                        fill: Theme.surfaceContainerHigh

                        ColumnLayout {
                            id: status
                            anchors.left: parent.left
                            anchors.right: parent.right
                            anchors.top: parent.top
                            spacing: 12

                            Text {
                                text: Tr.t("System Status")
                                color: Theme.fgSurfaceVariant
                                font.family: Style.mono
                                font.pixelSize: 10
                                font.letterSpacing: 2
                                font.capitalization: Font.AllUppercase
                            }
                            StatusRow {
                                Layout.fillWidth: true
                                known: root.flatpakKnown
                                ok: !!root.flatpak.installed
                                okText: Tr.t("Flatpak instalado")
                                badText: Tr.t("Flatpak não instalado")
                                actionText: Tr.t("Install")
                                busy: backend.busy
                                onAction: backend.installFlatpak()
                            }
                            StatusRow {
                                Layout.fillWidth: true
                                known: root.flatpakKnown
                                ok: !!root.flatpak.flathub
                                okText: Tr.t("Flathub configurado")
                                badText: Tr.t("Flathub remote não configurado")
                                actionText: Tr.t("Add")
                                actionEnabled: !!root.flatpak.installed
                                busy: backend.busy
                                onAction: backend.addFlathub()
                            }
                            RowLayout {
                                visible: root.flatpakError.length > 0
                                spacing: 8
                                Rune { name: "error"; size: 16; color: Theme.error }
                                Text {
                                    Layout.fillWidth: true
                                    text: root.flatpakError
                                    color: Theme.fgSurface
                                    font.pixelSize: 12
                                    wrapMode: Text.Wrap
                                }
                            }
                        }
                    }
                }
                SettingRow {
                    title: Tr.t("Search Results Limit")
                    description: Tr.t("Packages per page; lower is faster.")
                    Select {
                        width: 160
                        options: [{ label: Tr.f("%s results", 25), value: 25 }, { label: Tr.f("%s results", 50), value: 50 },
                                  { label: Tr.f("%s results", 100), value: 100 }]
                        current: root.s.search_limit
                        onActivated: v => root.set("search_limit", v)
                    }
                }
            }
    }

    Component {
        id: sec_appearance
            SettingsSection {
                title: Tr.t("Appearance")
                rune: "light"
                delay: 70

                SettingRow {
                    title: Tr.t("Language")
                    description: Tr.t("Choose your interface language.")
                    Select {
                        width: 220
                        // Language names are written in their own language, never translated.
                        options: [{ label: Tr.t("System Default"), value: "system" }, { label: "Português", value: "pt" },
                                  { label: "English", value: "en" }, { label: "Español", value: "es" },
                                  { label: "Русский", value: "ru" }]
                        current: root.s.locale
                        onActivated: v => root.set("locale", v)
                    }
                }

                SettingRow {
                    title: Tr.t("Color source")
                    stacked: true
                    description: Tr.t("Follow the desktop palette, pick a preset, or choose your own main color.")
                    Segmented {
                        options: [{ label: Tr.t("System"), value: "auto" }, { label: Tr.t("Preset"), value: "preset" }, { label: Tr.t("Custom"), value: "seed" }]
                        current: root.s.theme_source === "system" ? "auto" : root.s.theme_source
                        onActivated: v => root.set("theme_source", v)
                    }
                }

                SettingRow {
                    visible: root.s.theme_source === "preset"
                    title: Tr.t("Color Palette")
                    stacked: true
                    description: Tr.t("Choose an accent color theme for the store.")
                    Row {
                        spacing: 8
                        Repeater {
                            model: root.presets
                            delegate: Item {
                                id: chip
                                required property var modelData
                                readonly property bool on: root.s.theme === modelData.value
                                width: 96; height: 34
                                ChamferPanel {
                                    anchors.fill: parent
                                    padding: 0
                                    chamfer: 6
                                    fill: chip.on ? Theme.secondaryContainer : Theme.surfaceContainerHigh
                                    stroke: chip.on ? Theme.primary : Theme.outlineVariant
                                }
                                Row {
                                    anchors.centerIn: parent
                                    spacing: 7
                                    Rectangle { width: 10; height: 10; rotation: 45; color: chip.modelData.color; anchors.verticalCenter: parent.verticalCenter }
                                    Text { text: chip.modelData.label; color: Theme.fgSurface; font.pixelSize: 11; font.weight: Font.DemiBold; anchors.verticalCenter: parent.verticalCenter }
                                }
                                MouseArea {
                                    anchors.fill: parent
                                    cursorShape: Qt.PointingHandCursor
                                    onClicked: root.set("theme", chip.modelData.value)
                                }
                            }
                        }
                    }
                }

                SettingRow {
                    visible: root.s.theme_source === "seed"
                    title: Tr.t("Main color")
                    stacked: true
                    description: Tr.t("Any #rrggbb. Everything else is derived from it, Material You style.")
                    Row {
                        spacing: 10
                        Rectangle {
                            width: 34; height: 34; rotation: 45; scale: 0.7
                            color: root.validHex(seed.text) ? seed.text : Theme.outline
                            anchors.verticalCenter: parent.verticalCenter
                        }
                        Field {
                            id: seed
                            width: 150
                            rune: "light"
                            placeholder: "#7c3aed"
                            maximumLength: 7
                            text: root.s.theme_seed
                            onEdited: if (root.validHex(text)) root.set("theme_seed", text)
                        }
                    }
                }
                SettingRow {
                    visible: root.s.theme_source === "seed"
                    title: Tr.t("Secondary and tertiary")
                    stacked: true
                    description: Tr.t("Optional. Leave empty to derive them from the main color.")
                    Row {
                        spacing: 10
                        Field {
                            width: 130; placeholder: Tr.t("Secondary"); maximumLength: 7
                            text: root.s.theme_secondary
                            onEdited: if (text === "" || root.validHex(text)) root.set("theme_secondary", text)
                        }
                        Field {
                            width: 130; placeholder: Tr.t("Tertiary"); maximumLength: 7
                            text: root.s.theme_tertiary
                            onEdited: if (text === "" || root.validHex(text)) root.set("theme_tertiary", text)
                        }
                    }
                }

                SettingRow {
                    visible: Theme.source !== "system"
                    title: Tr.t("Mode")
                    description: Tr.t("Dark or light surfaces.")
                    Segmented {
                        options: [{ label: Tr.t("Dark"), value: "dark" }, { label: Tr.t("Light"), value: "light" }]
                        current: root.s.theme_mode
                        onActivated: v => root.set("theme_mode", v)
                    }
                }

                SettingRow {
                    title: Tr.t("Current palette")
                    description: Theme.source === "system" ? Tr.t("From the desktop palette file.") : Tr.t("Generated by the Material You algorithm.")
                    Row {
                        spacing: 6
                        Repeater {
                            model: [Theme.primary, Theme.secondary, Theme.tertiary, Theme.primaryContainer, Theme.surfaceContainerHigh, Theme.error]
                            Rectangle {
                                required property color modelData
                                width: 22; height: 22; rotation: 45; scale: 0.8
                                color: modelData
                                border.width: 1; border.color: Theme.outlineVariant
                            }
                        }
                    }
                }

                SettingRow {
                    title: Tr.t("Reduce motion")
                    description: Tr.t("Turns off wipes, typing, drifting runes and the rotating ring.")
                    Toggle { checked: root.s.reduce_motion; onToggled: v => root.set("reduce_motion", v) }
                }
            }
    }

    Component {
        id: sec_terminal
            SettingsSection {
                title: Tr.t("Terminal")
                rune: "active"
                delay: 140
                SettingRow {
                    title: Tr.t("Terminal Auto-close")
                    description: Tr.t("Automatically close the terminal window after the process completes.")
                    Toggle { checked: root.s.auto_close_terminal; onToggled: v => root.set("auto_close_terminal", v) }
                }
                SettingRow {
                    visible: root.s.auto_close_terminal
                    title: Tr.t("Close Delay")
                    description: Tr.t("Countdown before the terminal window closes.")
                    Select {
                        width: 140
                        options: [{ label: Tr.f("%s s", 5), value: 5 }, { label: Tr.f("%s s", 10), value: 10 }, { label: Tr.f("%s s", 15), value: 15 }, { label: Tr.f("%s s", 30), value: 30 }]
                        current: root.s.terminal_close_delay
                        onActivated: v => root.set("terminal_close_delay", v)
                    }
                }
            }
    }

    Component {
        id: sec_appimages
            SettingsSection {
                title: Tr.t("AppImages")
                rune: "bamboo"
                delay: 210
                SettingRow {
                    title: Tr.t("AppImage Storage Directory")
                    stacked: true
                    description: Tr.t("Where your AppImages are stored and managed.")
                    Row {
                        spacing: 10
                        Field {
                            id: dirField
                            width: 260
                            placeholder: "~/Applications"
                            text: root.s.appimage_path
                            onEdited: root.set("appimage_path", text)
                        }
                        Btn { text: Tr.t("Change"); variant: "outline"; onClicked: folder.open() }
                    }
                }

                FolderDialog {
                    id: folder
                    title: Tr.t("AppImage Storage Directory")
                    onAccepted: {
                        const p = decodeURIComponent(selectedFolder.toString().replace("file://", ""))
                        dirField.text = p
                        root.set("appimage_path", p)
                    }
                }
            }
    }

    Component {
        id: sec_maintenance
            SettingsSection {
                title: Tr.t("Maintenance")
                rune: "loading"
                delay: 280
                SettingRow {
                    title: Tr.t("Clear Cache")
                    description: Tr.t("Clear all cached package details, searches and lists.")
                    Row {
                        spacing: 12
                        Rune { visible: root.note.length > 0; name: "success"; size: 17; anchors.verticalCenter: parent.verticalCenter }
                        Btn {
                            text: Tr.t("Clear Cache")
                            variant: "outline"
                            onClicked: { backend.clearCache(); root.note = "cleared" }
                        }
                    }
                }
            }
    }
}

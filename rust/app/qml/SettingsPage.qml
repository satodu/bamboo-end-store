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

    function set(key, value) {
        backend.setSetting(key, JSON.stringify(value))
        if (key.indexOf("theme") === 0 || key === "reduce_motion") Theme.reload()
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
            width: Math.min(scroll.availableWidth - 56, 820)
            spacing: 18

            Item { Layout.preferredHeight: 10 }

            ColumnLayout {
                spacing: 4
                Text {
                    text: "SETTINGS"
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
                    text: "Manage your repositories and preferences."
                    color: Theme.fgSurfaceVariant
                    font.pixelSize: 14
                }
            }

            SettingsSection {
                title: "Repositories"
                rune: "appimages"
                delay: 0
                SettingRow {
                    title: "Arch User Repository"
                    description: "Search and install thousands of community-maintained packages."
                    Toggle { checked: root.s.enable_aur; onToggled: v => root.set("enable_aur", v) }
                }
                SettingRow {
                    title: "Flatpak Integration"
                    description: "Access Flathub's universal application ecosystem."
                    Toggle { checked: root.s.enable_flatpak; onToggled: v => root.set("enable_flatpak", v) }
                }
                SettingRow {
                    title: "Search Results Limit"
                    description: "Packages per page; lower is faster."
                    Segmented {
                        options: [{ label: "25", value: 25 }, { label: "50", value: 50 }, { label: "100", value: 100 }]
                        current: root.s.search_limit
                        onActivated: v => root.set("search_limit", v)
                    }
                }
            }

            SettingsSection {
                title: "Appearance"
                rune: "light"
                delay: 70

                SettingRow {
                    title: "Color source"
                    description: "Follow the desktop palette, pick a preset, or choose your own main color."
                    Segmented {
                        options: [{ label: "System", value: "auto" }, { label: "Preset", value: "preset" }, { label: "Custom", value: "seed" }]
                        current: root.s.theme_source === "system" ? "auto" : root.s.theme_source
                        onActivated: v => root.set("theme_source", v)
                    }
                }

                SettingRow {
                    visible: root.s.theme_source === "preset"
                    title: "Color Palette"
                    description: "Choose an accent color theme for the store."
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
                    title: "Main color"
                    description: "Any #rrggbb. Everything else is derived from it, Material You style."
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
                    title: "Secondary and tertiary"
                    description: "Optional. Leave empty to derive them from the main color."
                    Row {
                        spacing: 10
                        Field {
                            width: 130; placeholder: "secondary"; maximumLength: 7
                            text: root.s.theme_secondary
                            onEdited: if (text === "" || root.validHex(text)) root.set("theme_secondary", text)
                        }
                        Field {
                            width: 130; placeholder: "tertiary"; maximumLength: 7
                            text: root.s.theme_tertiary
                            onEdited: if (text === "" || root.validHex(text)) root.set("theme_tertiary", text)
                        }
                    }
                }

                SettingRow {
                    visible: Theme.source !== "system"
                    title: "Mode"
                    description: "Dark or light surfaces."
                    Segmented {
                        options: [{ label: "Dark", value: "dark" }, { label: "Light", value: "light" }]
                        current: root.s.theme_mode
                        onActivated: v => root.set("theme_mode", v)
                    }
                }

                SettingRow {
                    title: "Current palette"
                    description: Theme.source === "system" ? "From the desktop palette file." : "Generated by the Material You algorithm."
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
                    title: "Reduce motion"
                    description: "Turns off wipes, typing, drifting runes and the rotating ring."
                    Toggle { checked: root.s.reduce_motion; onToggled: v => root.set("reduce_motion", v) }
                }
            }

            SettingsSection {
                title: "Terminal"
                rune: "active"
                delay: 140
                SettingRow {
                    title: "Terminal Auto-close"
                    description: "Automatically close the terminal window after the process completes."
                    Toggle { checked: root.s.auto_close_terminal; onToggled: v => root.set("auto_close_terminal", v) }
                }
                SettingRow {
                    visible: root.s.auto_close_terminal
                    title: "Close Delay"
                    description: "Countdown before the terminal window closes."
                    Segmented {
                        options: [{ label: "5 s", value: 5 }, { label: "10 s", value: 10 }, { label: "15 s", value: 15 }, { label: "30 s", value: 30 }]
                        current: root.s.terminal_close_delay
                        onActivated: v => root.set("terminal_close_delay", v)
                    }
                }
            }

            SettingsSection {
                title: "AppImages"
                rune: "bamboo"
                delay: 210
                SettingRow {
                    title: "AppImage Storage Directory"
                    description: "Where your AppImages are stored and managed."
                    Row {
                        spacing: 10
                        Field {
                            id: dirField
                            width: 300
                            placeholder: "~/Applications"
                            text: root.s.appimage_path
                            onEdited: root.set("appimage_path", text)
                        }
                        Btn { text: "Change"; variant: "outline"; onClicked: folder.open() }
                    }
                }
            }

            SettingsSection {
                title: "Maintenance"
                rune: "loading"
                delay: 280
                SettingRow {
                    title: "Clear Cache"
                    description: "Clear all cached package details, searches and lists."
                    Row {
                        spacing: 12
                        Rune { visible: root.note.length > 0; name: "success"; size: 17; anchors.verticalCenter: parent.verticalCenter }
                        Btn {
                            text: "Clear Cache"
                            variant: "outline"
                            onClicked: { backend.clearCache(); root.note = "cleared" }
                        }
                    }
                }
            }

            Item { Layout.preferredHeight: 24 }
        }
    }

    FolderDialog {
        id: folder
        title: "AppImage Storage Directory"
        onAccepted: {
            const p = decodeURIComponent(selectedFolder.toString().replace("file://", ""))
            dirField.text = p
            root.set("appimage_path", p)
        }
    }
}

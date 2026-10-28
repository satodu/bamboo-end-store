import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Component catalogue: `BAMBOO_QML=app/qml/Gallery.qml cargo run -p bamboo-end-store`
ApplicationWindow {
    id: win
    width: 1100
    height: 860
    visible: true
    title: "Bamboo components"
    color: Theme.surface

    readonly property var sample: [
        { name: "firefox", display_name: "Firefox", version: "159.0-1", source: "pacman", installed: true,
          description: "Fast, Private & Safe Web Browser" },
        { name: "yay-bin", version: "13.1.0-1", source: "aur", installed: false,
          description: "Yet another yogurt. Pacman wrapper and AUR helper written in go. Pre-compiled." },
        { name: "org.mozilla.Thunderbird", display_name: "Thunderbird", version: "140.0", source: "flatpak", installed: false,
          description: "Thunderbird is a free and open source email, newsfeed, chat, and calendaring client" }
    ]

    ScrollView {
        anchors.fill: parent
        contentWidth: availableWidth
        background: Rectangle { color: Theme.surface }

        ColumnLayout {
            width: parent.width
            spacing: 28

            Item { Layout.preferredHeight: 4 }

            RowLayout {
                Layout.leftMargin: 28
                Layout.rightMargin: 28
                spacing: 12
                Text {
                    text: "Components"
                    color: Theme.fgSurface
                    font.pixelSize: 26
                    font.weight: Font.Bold
                }
                Tag { text: Theme.source; tone: "primary" }
                Tag { text: Theme.dark ? "dark" : "light" }
                Item { Layout.fillWidth: true }
                Btn { text: "Dark"; variant: "outline"; onClicked: { backend.setSetting("theme_mode", '"dark"'); Theme.reload() } }
                Btn { text: "Light"; variant: "outline"; onClicked: { backend.setSetting("theme_mode", '"light"'); Theme.reload() } }
            }

            // Buttons
            ColumnLayout {
                Layout.leftMargin: 28
                spacing: 10
                Text { text: "Buttons"; color: Theme.fgSurfaceVariant; font.pixelSize: 12; font.capitalization: Font.AllUppercase; font.letterSpacing: 1.5 }
                Flow {
                    Layout.maximumWidth: win.width - 56
                    spacing: 12
                    Btn { text: "Install"; variant: "filled" }
                    Btn { text: "Tonal"; variant: "tonal" }
                    Btn { text: "Remove"; variant: "outline" }
                    Btn { text: "Ghost"; variant: "ghost" }
                    Btn { text: "Delete"; variant: "danger" }
                    Btn { text: "Busy"; busy: true }
                    Btn { text: "Disabled"; enabled: false }
                }
            }

            // Tags
            ColumnLayout {
                Layout.leftMargin: 28
                spacing: 10
                Text { text: "Tags"; color: Theme.fgSurfaceVariant; font.pixelSize: 12; font.capitalization: Font.AllUppercase; font.letterSpacing: 1.5 }
                Row {
                    spacing: 8
                    Tag { text: "pacman" }
                    Tag { text: "installed"; tone: "primary" }
                    Tag { text: "aur"; tone: "tertiary" }
                    Tag { text: "out of date"; tone: "error" }
                }
            }

            // Search + nav
            RowLayout {
                Layout.leftMargin: 28
                Layout.rightMargin: 28
                spacing: 28
                SearchField { Layout.fillWidth: true; placeholder: "Search pacman, AUR, Flatpak" }
                ChamferPanel {
                    Layout.preferredWidth: 240
                    Layout.preferredHeight: 4 * 42 + 2
                    padding: 1
                    chamfer: 0
                    fill: Theme.surfaceContainerLow
                    ColumnLayout {
                        anchors.fill: parent
                        spacing: 0
                        NavItem { Layout.fillWidth: true; text: "Explore"; glyph: "◈"; active: true }
                        NavItem { Layout.fillWidth: true; text: "Installed"; glyph: "◆"; badge: 128 }
                        NavItem { Layout.fillWidth: true; text: "AppImages"; glyph: "▣" }
                        NavItem { Layout.fillWidth: true; text: "Settings"; glyph: "✦" }
                    }
                }
            }

            // Cards
            ColumnLayout {
                Layout.leftMargin: 28
                spacing: 10
                Text { text: "Grid cards"; color: Theme.fgSurfaceVariant; font.pixelSize: 12; font.capitalization: Font.AllUppercase; font.letterSpacing: 1.5 }
                Row {
                    spacing: 16
                    Repeater {
                        model: win.sample
                        PackageCard { pkg: modelData; width: 300 }
                    }
                }
            }

            // Rows
            ColumnLayout {
                Layout.leftMargin: 28
                Layout.rightMargin: 28
                Layout.fillWidth: true
                spacing: 10
                Text { text: "List rows"; color: Theme.fgSurfaceVariant; font.pixelSize: 12; font.capitalization: Font.AllUppercase; font.letterSpacing: 1.5 }
                Repeater {
                    model: win.sample
                    PackageRow { pkg: modelData; Layout.fillWidth: true }
                }
            }

            Item { Layout.preferredHeight: 24 }
        }
    }

    Backend { id: backend }
}

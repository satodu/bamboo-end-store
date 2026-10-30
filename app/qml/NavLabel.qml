import QtQuick
import QtQuick.Layouts

// Small mono caption with a hairline, grouping the sidebar entries.
RowLayout {
    id: root

    property string text: ""

    Layout.fillWidth: true
    Layout.leftMargin: 22
    Layout.rightMargin: 16
    Layout.topMargin: 14
    Layout.bottomMargin: 4
    spacing: 8

    Text {
        text: root.text
        color: Qt.alpha(Theme.fgSurface, 0.4)
        font.family: Style.mono
        font.pixelSize: 10
        font.letterSpacing: 2
        font.capitalization: Font.AllUppercase
    }
    Rectangle { Layout.fillWidth: true; height: 1; color: Theme.outlineVariant }
}

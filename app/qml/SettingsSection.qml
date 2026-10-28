import QtQuick
import QtQuick.Layouts

// A titled group of settings on a chamfered panel with the Galdr line work.
Item {
    id: root

    property string title: ""
    property string rune: "default"
    property int delay: 0
    default property alias rows: col.data

    implicitHeight: panel.implicitHeight
    Layout.fillWidth: true

    Reveal {
        anchors.fill: parent
        delay: root.delay

        ChamferPanel {
            id: panel
            anchors.fill: parent
            fill: Theme.surfaceContainerLow
            padding: 24
            details: true
            implicitHeight: wrapper.implicitHeight + padding * 2

            ColumnLayout {
                id: wrapper
                anchors.left: parent.left
                anchors.right: parent.right
                anchors.top: parent.top
                spacing: 16

                RowLayout {
                    Layout.fillWidth: true
                    Layout.topMargin: 6
                    spacing: 10
                    Rune { name: root.rune; size: 17; color: Theme.primary }
                    Text {
                        text: root.title
                        color: Theme.fgSurfaceVariant
                        font.family: Style.mono
                        font.pixelSize: 11
                        font.letterSpacing: 2
                        font.capitalization: Font.AllUppercase
                    }
                    Rectangle { Layout.fillWidth: true; height: 1; color: Theme.outlineVariant }
                }

                ColumnLayout {
                    id: col
                    Layout.fillWidth: true
                    spacing: 14
                }
            }
        }
    }
}

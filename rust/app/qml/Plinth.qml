import QtQuick
import QtQuick.Layouts

// Empty state (GALDR.md 7.x): a dashed chamfered plinth, the rune ring, a mono caption
// and at most one action.
Item {
    id: root

    property string caption: "FIG. — NO SHARD."
    property string actionText: ""
    signal action()

    implicitWidth: 360
    implicitHeight: 300

    Reveal {
        anchors.fill: parent

        ChamferPanel {
            anchors.fill: parent
            dashed: true
            fill: "transparent"
            stroke: Theme.outline
            padding: 0

            ColumnLayout {
                anchors.centerIn: parent
                spacing: 18

                RuneRing {
                    Layout.alignment: Qt.AlignHCenter
                    size: Math.min(170, root.height - 130)
                }
                Text {
                    Layout.alignment: Qt.AlignHCenter
                    text: root.caption
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 12
                    font.letterSpacing: 2
                }
                Btn {
                    Layout.alignment: Qt.AlignHCenter
                    visible: root.actionText.length > 0
                    text: root.actionText
                    variant: "outline"
                    onClicked: root.action()
                }
            }
        }
    }
}

import QtQuick
import QtQuick.Layouts

// Modal confirmation on a scrim. Nauthiz (need) marks the warning; the wipe plays on each open.
Item {
    id: root

    property string title: ""
    property string message: ""
    property string acceptText: Tr.t("Delete")

    signal accepted()
    signal rejected()

    anchors.fill: parent
    visible: false
    z: 100

    function open(title, message) {
        root.title = title
        root.message = message
        visible = true
    }
    function close() { visible = false }

    Rectangle {
        anchors.fill: parent
        color: Qt.rgba(0, 0, 0, 0.55)
        MouseArea { anchors.fill: parent; onClicked: { root.close(); root.rejected() } }
    }

    Loader {
        anchors.centerIn: parent
        width: 440
        height: 230
        active: root.visible
        sourceComponent: Reveal {
            ChamferPanel {
                anchors.fill: parent
                fill: Theme.surfaceContainerHigh
                stroke: Theme.error
                padding: 26
                details: true

                ColumnLayout {
                    anchors.fill: parent
                    spacing: 12

                    RowLayout {
                        spacing: 12
                        Rune { name: "error"; size: 26; color: Theme.error }
                        Text {
                            Layout.fillWidth: true
                            text: root.title
                            color: Theme.fgSurface
                            font.family: Style.displayFont
                            font.pixelSize: 26
                            font.weight: Font.ExtraBold
                            font.capitalization: Font.AllUppercase
                            font.letterSpacing: 1.5
                            elide: Text.ElideRight
                        }
                    }
                    Text {
                        Layout.fillWidth: true
                        text: root.message
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 13
                        wrapMode: Text.Wrap
                    }
                    Item { Layout.fillHeight: true }
                    RowLayout {
                        Layout.fillWidth: true
                        spacing: 10
                        Item { Layout.fillWidth: true }
                        Btn { text: Tr.t("Cancel"); variant: "ghost"; onClicked: { root.close(); root.rejected() } }
                        Btn { text: root.acceptText; variant: "danger"; onClicked: { root.close(); root.accepted() } }
                    }
                }
            }
        }
    }
}

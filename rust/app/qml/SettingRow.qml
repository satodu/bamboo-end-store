import QtQuick
import QtQuick.Layouts

// A setting: title and description on the left, the control on the right.
Item {
    id: root

    property string title: ""
    property string description: ""
    default property alias control: slot.data

    implicitHeight: Math.max(44, labels.implicitHeight)
    Layout.fillWidth: true

    RowLayout {
        anchors.fill: parent
        spacing: 24

        ColumnLayout {
            id: labels
            Layout.fillWidth: true
            spacing: 3
            Text {
                text: root.title
                color: Theme.fgSurface
                font.pixelSize: 14
                font.weight: Font.DemiBold
            }
            Text {
                Layout.fillWidth: true
                visible: root.description.length > 0
                text: root.description
                color: Theme.fgSurfaceVariant
                font.pixelSize: 12
                wrapMode: Text.Wrap
            }
        }

        Item {
            id: slot
            Layout.alignment: Qt.AlignVCenter
            Layout.preferredWidth: childrenRect.width
            Layout.preferredHeight: childrenRect.height
        }
    }
}

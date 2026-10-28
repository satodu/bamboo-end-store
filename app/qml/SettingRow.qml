import QtQuick
import QtQuick.Layouts

// A setting: title and description on the left, the control on the right. `stacked` puts the
// control under the text instead, for wide controls in a narrow column.
Item {
    id: root

    property string title: ""
    property string description: ""
    property bool stacked: false
    default property alias control: slot.data

    implicitHeight: Math.max(44, grid.implicitHeight)
    Layout.fillWidth: true

    GridLayout {
        id: grid
        anchors.left: parent.left
        anchors.right: parent.right
        columns: root.stacked ? 1 : 2
        columnSpacing: 24
        rowSpacing: 10

        ColumnLayout {
            Layout.fillWidth: true
            Layout.alignment: Qt.AlignTop
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
            Layout.alignment: root.stacked ? Qt.AlignLeft | Qt.AlignTop : Qt.AlignVCenter
            Layout.fillWidth: root.stacked
            Layout.preferredWidth: root.stacked ? -1 : childrenRect.width
            Layout.preferredHeight: childrenRect.height
        }
    }
}

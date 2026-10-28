import QtQuick

// Sidebar entry. The active one gets the 2 px primary fillet on its leading edge.
Item {
    id: root

    property string text: ""
    property string glyph: "◆"
    property bool active: false
    property int badge: 0

    signal clicked()

    implicitHeight: 42
    implicitWidth: 220

    Rectangle {
        anchors.fill: parent
        color: root.active ? Theme.secondaryContainer : mouse.containsMouse ? Theme.surfaceContainerHigh : "transparent"
        Behavior on color { ColorAnimation { duration: Style.quick } }
    }

    Rectangle {
        width: Style.fillet
        height: parent.height
        color: Theme.primary
        opacity: root.active ? 1 : 0
        Behavior on opacity { NumberAnimation { duration: Style.quick } }
    }

    Row {
        anchors.verticalCenter: parent.verticalCenter
        anchors.left: parent.left
        anchors.leftMargin: 18
        spacing: 12

        Text {
            text: root.glyph
            color: root.active ? Theme.primary : Theme.fgSurfaceVariant
            font.pixelSize: 14
            anchors.verticalCenter: parent.verticalCenter
        }
        Text {
            text: root.text
            color: root.active ? Theme.fgSecondaryContainer : Theme.fgSurface
            font.pixelSize: 14
            font.weight: root.active ? Font.DemiBold : Font.Normal
            anchors.verticalCenter: parent.verticalCenter
        }
    }

    Tag {
        visible: root.badge > 0
        text: root.badge
        tone: "primary"
        anchors.right: parent.right
        anchors.rightMargin: 14
        anchors.verticalCenter: parent.verticalCenter
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        cursorShape: Qt.PointingHandCursor
        onClicked: root.clicked()
    }
}

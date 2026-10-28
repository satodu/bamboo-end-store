import QtQuick

// Sidebar entry led by a rune. The active one gets the 2 px primary fillet drawn down its
// leading edge and its rune turns primary.
Item {
    id: root

    property string text: ""
    /// Rune name from Style.runes
    property string rune: "default"
    property bool active: false
    property int badge: 0
    /// Show the rune spinner at the right edge: an action is running.
    property bool busy: false

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
        height: root.active ? parent.height : 0
        color: Theme.primary
        Behavior on height {
            NumberAnimation { duration: Style.medium; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease }
        }
    }

    Row {
        anchors.verticalCenter: parent.verticalCenter
        anchors.left: parent.left
        anchors.leftMargin: 18
        spacing: 12

        Rune {
            name: root.rune
            size: 21
            width: 22
            horizontalAlignment: Text.AlignHCenter
            anchors.verticalCenter: parent.verticalCenter
            color: root.active ? Theme.primary : Qt.alpha(Theme.fgSurface, 0.45)
            Behavior on color { ColorAnimation { duration: Style.quick } }
        }
        Text {
            text: root.text
            color: root.active ? Theme.fgSecondaryContainer : Theme.fgSurface
            font.pixelSize: 14
            font.weight: root.active ? Font.DemiBold : Font.Normal
            anchors.verticalCenter: parent.verticalCenter
        }
    }

    RuneSpinner {
        visible: root.busy
        running: root.busy
        size: 18
        anchors.right: parent.right
        anchors.rightMargin: 16
        anchors.verticalCenter: parent.verticalCenter
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

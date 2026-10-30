import QtQuick

// Sidebar entry led by a rune. The active one sits on a chamfered plate with a primary hairline and
// its rune turns primary; a hovered one gets the outline of the plate and its rune steps forward.
// `index` is the small mono ordinal at the right edge (Galdr line work); it is left empty on actions.
Item {
    id: root

    property string text: ""
    /// Rune name from Style.runes
    property string rune: "default"
    property bool active: false
    property int badge: 0
    /// Show the rune spinner at the right edge: an action is running.
    property bool busy: false
    /// Ordinal shown at the right edge, e.g. "01".
    property string index: ""
    /// Entrance stagger, in ms.
    property int delay: 0

    signal clicked()

    implicitHeight: 42
    implicitWidth: 220

    Reveal {
        anchors.fill: parent
        delay: root.delay
        slide: 8

        ChamferPanel {
            anchors.fill: parent
            anchors.leftMargin: 10
            anchors.rightMargin: 10
            anchors.topMargin: 2
            anchors.bottomMargin: 2
            padding: 0
            chamfer: Style.chamferSm
            fill: root.active ? Theme.secondaryContainer : "transparent"
            stroke: root.active ? Qt.alpha(Theme.primary, 0.7) : mouse.containsMouse ? Theme.outlineVariant : "transparent"
        }

        // The 2 px fillet of the active rule, drawn down the leading edge.
        Rectangle {
            x: 10
            anchors.verticalCenter: parent.verticalCenter
            width: Style.fillet
            height: root.active ? parent.height - 14 : mouse.containsMouse ? 12 : 0
            color: Theme.primary
            Behavior on height {
                NumberAnimation { duration: Style.medium; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease }
            }
        }

        Row {
            anchors.verticalCenter: parent.verticalCenter
            anchors.left: parent.left
            anchors.leftMargin: root.active || mouse.containsMouse ? 28 : 24
            spacing: 12
            Behavior on anchors.leftMargin {
                NumberAnimation { duration: Style.quick; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease }
            }

            Rune {
                name: root.rune
                size: 21
                width: 22
                horizontalAlignment: Text.AlignHCenter
                anchors.verticalCenter: parent.verticalCenter
                color: root.active ? Theme.primary : Qt.alpha(Theme.fgSurface, mouse.containsMouse ? 0.8 : 0.45)
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

        Text {
            visible: root.index.length > 0 && !root.busy && root.badge === 0
            text: root.index
            anchors.right: parent.right
            anchors.rightMargin: 22
            anchors.verticalCenter: parent.verticalCenter
            color: root.active ? Theme.primary : Qt.alpha(Theme.fgSurface, 0.28)
            font.family: Style.mono
            font.pixelSize: 10
            font.letterSpacing: 1.5
            Behavior on color { ColorAnimation { duration: Style.quick } }
        }

        RuneSpinner {
            visible: root.busy
            running: root.busy
            size: 18
            anchors.right: parent.right
            anchors.rightMargin: 20
            anchors.verticalCenter: parent.verticalCenter
        }

        Tag {
            visible: root.badge > 0
            text: root.badge
            tone: "primary"
            anchors.right: parent.right
            anchors.rightMargin: 18
            anchors.verticalCenter: parent.verticalCenter
        }
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        cursorShape: Qt.PointingHandCursor
        onClicked: root.clicked()
    }
}

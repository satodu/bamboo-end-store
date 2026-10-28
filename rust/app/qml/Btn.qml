import QtQuick

// Chamfered button. variant: "filled" (primary), "tonal", "outline", "ghost", "danger".
Item {
    id: root

    property string text: ""
    property string variant: "filled"
    property bool busy: false
    property real chamfer: Style.chamferSm
    readonly property alias hovered: mouse.containsMouse
    readonly property alias pressed: mouse.pressed

    signal clicked()

    implicitWidth: label.implicitWidth + 36
    implicitHeight: 38
    opacity: enabled ? 1 : 0.38

    readonly property color base: {
        switch (variant) {
        case "filled": return Theme.primary
        case "tonal": return Theme.secondaryContainer
        case "danger": return Theme.error
        default: return "transparent"
        }
    }
    readonly property color ink: {
        switch (variant) {
        case "filled": return Theme.fgPrimary
        case "tonal": return Theme.fgSecondaryContainer
        case "danger": return Theme.fgError
        case "outline": return Theme.primary
        default: return Theme.fgSurface
        }
    }
    // Material state layer: a translucent wash of the ink colour.
    readonly property real washTarget: pressed ? 0.16 : hovered ? 0.08 : 0
    property real wash: washTarget
    Behavior on wash { NumberAnimation { duration: Style.quick; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease } }

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: root.chamfer
        fill: root.base
        stroke: root.variant === "outline" ? Theme.outline : "transparent"
        strokeWidth: root.variant === "outline" ? Style.border : 0
    }

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: root.chamfer
        fill: Qt.rgba(root.ink.r, root.ink.g, root.ink.b, root.wash)
        stroke: "transparent"
        strokeWidth: 0
    }

    Text {
        id: label
        anchors.centerIn: parent
        text: root.busy ? "..." : root.text
        color: root.ink
        font.pixelSize: 12
        font.weight: Font.Bold
        font.letterSpacing: 1.2
        font.capitalization: Font.AllUppercase
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        enabled: root.enabled && !root.busy
        cursorShape: Qt.PointingHandCursor
        onClicked: root.clicked()
    }
}

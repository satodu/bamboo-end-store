import QtQuick

// Chamfered switch (Galdr .toggle). The square knob slides on the Galdr curve.
Item {
    id: root

    property bool checked: false
    signal toggled(bool checked)

    implicitWidth: 56
    implicitHeight: 28
    opacity: enabled ? 1 : 0.38

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferSm
        fill: root.checked ? Theme.primary : Theme.surfaceContainerHighest
        stroke: root.checked ? Theme.primary : Theme.outline
    }

    ChamferPanel {
        width: 18
        height: 18
        y: 5
        x: root.checked ? root.width - width - 5 : 5
        padding: 0
        chamfer: 4
        fill: root.checked ? Theme.fgPrimary : Theme.fgSurfaceVariant
        stroke: "transparent"
        strokeWidth: 0
        Behavior on x {
            NumberAnimation { duration: Style.medium; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease }
        }
    }

    MouseArea {
        anchors.fill: parent
        cursorShape: Qt.PointingHandCursor
        onClicked: root.toggled(!root.checked)
    }
}

import QtQuick

// Laguz (flow) with a 2 px primary light bar sweeping across it. No spinning, no pulsing.
Item {
    id: root

    property bool running: true
    property real size: 26

    implicitWidth: size
    implicitHeight: size
    visible: running

    Rune { anchors.centerIn: parent; glyph: "ᛚ"; size: root.size; color: Qt.alpha(Theme.primary, 0.85) }

    Item {
        anchors.fill: parent
        clip: true
        Rectangle {
            id: bar
            width: parent.width
            height: 2
            color: Theme.primary
            opacity: 0.9

            SequentialAnimation on y {
                loops: Animation.Infinite
                running: root.running && root.visible
                NumberAnimation {
                    from: -2
                    to: root.size
                    duration: Style.still ? 1400 : 1100
                    easing.type: Easing.BezierSpline
                    easing.bezierCurve: Style.ease
                }
                PauseAnimation { duration: 250 }
            }
        }
    }
}

import QtQuick
import QtQuick.Shapes

// Galdr panel line work (GALDR.md 6.1): two primary targeting brackets on the square corners,
// a 14-tick ruler on the top edge (every 4th longer). Drawn on, from the corner outwards.
// Never put this on a bone/light surface panel.
Item {
    id: root
    anchors.fill: parent

    property real leg: 14
    property real inset: 6
    property real draw: Style.still ? 1 : 0

    Component.onCompleted: if (!Style.still) grow.start()

    NumberAnimation {
        id: grow
        target: root
        property: "draw"
        from: 0
        to: 1
        duration: Style.slow
        easing.type: Easing.BezierSpline
        easing.bezierCurve: Style.ease
    }

    Shape {
        anchors.fill: parent
        preferredRendererType: Shape.CurveRenderer

        // top-left bracket
        ShapePath {
            strokeColor: Qt.alpha(Theme.primary, 0.6)
            strokeWidth: 1.5
            fillColor: "transparent"
            joinStyle: ShapePath.MiterJoin
            startX: root.inset
            startY: root.inset + root.leg * root.draw
            PathLine { x: root.inset; y: root.inset }
            PathLine { x: root.inset + root.leg * root.draw; y: root.inset }
        }
        // bottom-right bracket
        ShapePath {
            strokeColor: Qt.alpha(Theme.primary, 0.6)
            strokeWidth: 1.5
            fillColor: "transparent"
            joinStyle: ShapePath.MiterJoin
            startX: root.width - root.inset
            startY: root.height - root.inset - root.leg * root.draw
            PathLine { x: root.width - root.inset; y: root.height - root.inset }
            PathLine { x: root.width - root.inset - root.leg * root.draw; y: root.height - root.inset }
        }
    }

    Repeater {
        model: 14
        Rectangle {
            required property int index
            readonly property bool major: index % 4 === 0
            x: root.inset + root.leg + 10 + index * 5
            y: root.inset
            width: 1
            height: (major ? 6 : 3) * root.draw
            color: Qt.alpha(Theme.fgSurface, 0.3)
        }
    }
}

import QtQuick
import QtQuick.Shapes

// A fill for one item of a chamfered container (segmented control, dropdown list). Where the item
// touches a chamfered corner of the container it is cut the same way, so it never pokes out over
// the corner or the outline. `cutTR` and `cutBL` are the cuts (0 = square corner).
Shape {
    id: root

    property color color: "transparent"
    property real cutTR: 0
    property real cutBL: 0

    /// Cut that sits just inside the outline of a container with chamfer `Style.chamferSm` and a 1 px stroke.
    readonly property real inset: Style.chamferSm - 0.6

    preferredRendererType: Shape.CurveRenderer

    ShapePath {
        fillColor: root.color
        strokeColor: "transparent"
        startX: 0
        startY: 0
        PathLine { x: root.width - root.cutTR; y: 0 }
        PathLine { x: root.width; y: root.cutTR }
        PathLine { x: root.width; y: root.height }
        PathLine { x: root.cutBL; y: root.height }
        PathLine { x: 0; y: root.height - root.cutBL }
        PathLine { x: 0; y: 0 }
    }
}

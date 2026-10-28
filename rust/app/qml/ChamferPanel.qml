import QtQuick
import QtQuick.Shapes

// Rectangle with the top-right and bottom-left corners cut at 45 degrees and a hairline
// outline drawn inside the edge. Children go into the padded content area.
// `details` adds the Galdr line work (brackets + ruler); `dashed` makes an empty plinth.
Item {
    id: root

    property real chamfer: Style.chamfer
    property color fill: Theme.surfaceContainer
    property color stroke: Theme.outlineVariant
    property real strokeWidth: Style.border
    property real padding: Style.pad
    property bool dashed: false
    property bool details: false

    default property alias content: inner.data
    readonly property alias contentItem: inner

    // No implicit size on purpose: sizing from the children loops with the content area
    // (which is sized from this item). Callers set width/height or anchor/Layout the panel.

    Behavior on fill { ColorAnimation { duration: Style.quick } }
    Behavior on stroke { ColorAnimation { duration: Style.quick } }

    Shape {
        anchors.fill: parent
        preferredRendererType: Shape.CurveRenderer

        ShapePath {
            id: path
            readonly property real w: root.width - root.strokeWidth
            readonly property real h: root.height - root.strokeWidth
            readonly property real c: Math.max(0, Math.min(root.chamfer, w / 2, h / 2))
            readonly property real o: root.strokeWidth / 2

            fillColor: root.fill
            strokeColor: root.stroke
            strokeWidth: root.strokeWidth
            strokeStyle: root.dashed ? ShapePath.DashLine : ShapePath.SolidLine
            dashPattern: [4, 4]
            joinStyle: ShapePath.MiterJoin

            startX: o
            startY: o
            PathLine { x: path.o + path.w - path.c; y: path.o }
            PathLine { x: path.o + path.w; y: path.o + path.c }
            PathLine { x: path.o + path.w; y: path.o + path.h }
            PathLine { x: path.o + path.c; y: path.o + path.h }
            PathLine { x: path.o; y: path.o + path.h - path.c }
            PathLine { x: path.o; y: path.o }
        }
    }

    Loader {
        anchors.fill: parent
        active: root.details
        source: "PanelDetails.qml"
    }

    Item {
        id: inner
        x: root.padding
        y: root.padding
        width: root.width - root.padding * 2
        height: root.height - root.padding * 2
    }
}

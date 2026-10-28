import QtQuick
import QtQuick.Shapes
import QtQuick.Effects

// Galdr entrance: a diagonal chamfer wipe plus a short slide, never a plain fade.
// Wraps its content; give it a size (anchors or width/height). Plays once on creation,
// or call play() again. While playing the content is masked, afterwards the layer is dropped.
// Without GPU effects (Qt's software renderer) it falls back to a straight left-to-right wipe.
Item {
    id: root

    property bool autoPlay: true
    property int delay: 0
    property real slide: Style.slide
    property int duration: Style.slow
    property real progress: 0
    readonly property bool effects: GraphicsInfo.api !== GraphicsInfo.Software

    default property alias content: holder.data
    signal finished()

    function play() { progress = 0; anim.restart() }

    Component.onCompleted: {
        if (!autoPlay || Style.still) progress = 1
        else anim.start()
    }

    SequentialAnimation {
        id: anim
        PauseAnimation { duration: root.delay }
        NumberAnimation {
            target: root
            property: "progress"
            from: 0
            to: 1
            duration: root.duration
            easing.type: Easing.BezierSpline
            easing.bezierCurve: Style.ease
        }
        ScriptAction { script: root.finished() }
    }

    Item {
        id: clipper
        height: root.height
        width: root.effects ? root.width : root.width * root.progress
        clip: !root.effects && root.progress < 1

        Item {
            id: holder
            width: root.width
            height: root.height
            transform: Translate { y: (1 - root.progress) * root.slide }
            layer.enabled: root.effects && root.progress < 1
            layer.effect: MultiEffect {
                maskEnabled: true
                maskSource: mask
            }
        }
    }

    // polygon(0 0, 120% 0, 100% 100%, 0 100%) growing from polygon(0 0, 0 0, -20% 100%, 0 100%)
    Item {
        id: mask
        anchors.fill: parent
        visible: false
        layer.enabled: root.effects && root.progress < 1
        layer.smooth: true

        Shape {
            anchors.fill: parent
            ShapePath {
                fillColor: "white"
                strokeColor: "transparent"
                startX: 0
                startY: 0
                PathLine { x: mask.width * 1.2 * root.progress; y: 0 }
                PathLine { x: mask.width * (1.2 * root.progress - 0.2); y: mask.height }
                PathLine { x: 0; y: mask.height }
                PathLine { x: 0; y: 0 }
            }
        }
    }
}

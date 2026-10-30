import QtQuick
import QtQuick.Shapes

// A package icon. When there is none (or while it loads) a chamfered tile shows the first letter of
// the name carved as its Elder Futhark rune (`Style.initialRune`): every package gets its own mark.
// A pacman (official repository) package without an icon gets a Nordic pacman instead: a round shield
// ring, a diamond eye, and its own rune where the pellet would be.
Item {
    id: root

    property string name: ""
    property url source: ""
    property real size: 48
    /// The package source (`pacman`, `aur`, `flatpak`, ...); `pacman` gets the pacman fallback.
    property string kind: ""

    implicitWidth: size
    implicitHeight: size

    readonly property var tints: [
        [Theme.primaryContainer, Theme.fgPrimaryContainer],
        [Theme.secondaryContainer, Theme.fgSecondaryContainer],
        [Theme.tertiaryContainer, Theme.fgTertiaryContainer]
    ]
    // A stable tone picked from the name, so neighbours differ.
    readonly property int tint: {
        let h = 0
        for (let i = 0; i < name.length; i++)
            h = (h * 31 + name.charCodeAt(i)) % 3
        return h
    }
    readonly property string rune: Style.initialRune(name)
    readonly property bool chomper: kind === "pacman"
    readonly property bool showImage: img.status === Image.Ready

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: root.size * 0.22
        visible: !root.showImage
        fill: root.tints[root.tint][0]
        stroke: "transparent"
        strokeWidth: 0

        Text {
            visible: !root.chomper
            anchors.centerIn: parent
            text: root.rune
            color: root.tints[root.tint][1]
            font.family: Style.runeFont
            font.pixelSize: root.size * 0.74
        }
    }

    // Nordic pacman: body with a 60 degree mouth turned to the rune it is about to eat.
    Item {
        id: chomp
        anchors.fill: parent
        visible: root.chomper && !root.showImage

        readonly property real r: root.size * 0.27
        readonly property real cx: root.size * 0.36
        readonly property real cy: root.size * 0.5
        readonly property color ink: root.tints[root.tint][1]

        Shape {
            anchors.fill: parent
            preferredRendererType: Shape.CurveRenderer

            // The body.
            ShapePath {
                fillColor: chomp.ink
                strokeColor: "transparent"
                startX: chomp.cx + chomp.r * Math.cos(Math.PI / 6)
                startY: chomp.cy + chomp.r * Math.sin(Math.PI / 6)
                PathAngleArc { centerX: chomp.cx; centerY: chomp.cy; radiusX: chomp.r; radiusY: chomp.r; startAngle: 30; sweepAngle: 300 }
                PathLine { x: chomp.cx; y: chomp.cy }
                PathLine { x: chomp.cx + chomp.r * Math.cos(Math.PI / 6); y: chomp.cy + chomp.r * Math.sin(Math.PI / 6) }
            }
            // The round-shield ring around it, broken like a carved border.
            ShapePath {
                fillColor: "transparent"
                strokeColor: Qt.alpha(chomp.ink, 0.45)
                strokeWidth: 1
                strokeStyle: ShapePath.DashLine
                dashPattern: [2, 3]
                startX: chomp.cx + chomp.r * 1.3
                startY: chomp.cy
                PathAngleArc { centerX: chomp.cx; centerY: chomp.cy; radiusX: chomp.r * 1.3; radiusY: chomp.r * 1.3; startAngle: 0; sweepAngle: 360 }
            }
        }
        // The eye: a diamond, like the rest of the line work.
        Rectangle {
            width: chomp.r * 0.22; height: width; rotation: 45
            x: chomp.cx + chomp.r * 0.12 - width / 2
            y: chomp.cy - chomp.r * 0.52 - height / 2
            color: root.tints[root.tint][0]
        }
        // The pellet: the package's own rune.
        Text {
            x: root.size * 0.78 - width / 2
            y: chomp.cy - height / 2
            text: root.rune
            color: chomp.ink
            font.family: Style.runeFont
            font.pixelSize: root.size * 0.3
        }
    }

    Image {
        id: img
        anchors.fill: parent
        source: root.source
        asynchronous: true
        cache: true
        fillMode: Image.PreserveAspectFit
        sourceSize.width: root.size * 2
        sourceSize.height: root.size * 2
        visible: root.showImage
    }
}

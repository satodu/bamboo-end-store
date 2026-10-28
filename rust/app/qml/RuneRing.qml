import QtQuick
import QtQuick.Shapes

// Two thin circles with the 24 Elder Futhark runes between them, turning very slowly
// (Galdr: 0.12 deg/frame). Still when motion is reduced.
Item {
    id: root

    property real size: 220
    property color tone: Theme.primary

    implicitWidth: size
    implicitHeight: size

    Item {
        id: ring
        anchors.fill: parent

        RotationAnimator on rotation {
            from: 0
            to: 360
            duration: 110000
            loops: Animation.Infinite
            running: !Style.still && root.visible
        }

        Shape {
            anchors.fill: parent
            preferredRendererType: Shape.CurveRenderer

            ShapePath {
                strokeColor: Qt.alpha(root.tone, 0.5)
                strokeWidth: 1
                fillColor: "transparent"
                PathAngleArc {
                    centerX: root.size / 2; centerY: root.size / 2
                    radiusX: root.size / 2 - 1; radiusY: root.size / 2 - 1
                    startAngle: 0; sweepAngle: 360
                }
            }
            ShapePath {
                strokeColor: Qt.alpha(root.tone, 0.5)
                strokeWidth: 1
                fillColor: "transparent"
                PathAngleArc {
                    centerX: root.size / 2; centerY: root.size / 2
                    radiusX: root.size / 2 - root.size * 0.17; radiusY: root.size / 2 - root.size * 0.17
                    startAngle: 0; sweepAngle: 360
                }
            }
            ShapePath {
                strokeColor: Qt.alpha(Theme.fgSurface, 0.25)
                strokeWidth: 1
                strokeStyle: ShapePath.DashLine
                dashPattern: [1, 4]
                fillColor: "transparent"
                PathAngleArc {
                    centerX: root.size / 2; centerY: root.size / 2
                    radiusX: root.size / 2 + 8; radiusY: root.size / 2 + 8
                    startAngle: 0; sweepAngle: 360
                }
            }
        }

        Repeater {
            model: 24
            Text {
                required property int index
                readonly property real r: root.size / 2 - root.size * 0.085
                text: Style.futhark.charAt(index)
                color: Qt.alpha(Theme.fgSurface, 0.35)
                font.family: Style.runeFont
                font.pixelSize: root.size * 0.075
                x: root.size / 2 - width / 2
                y: root.size / 2 - r - height / 2
                transform: Rotation {
                    origin.x: width / 2
                    origin.y: r + height / 2
                    angle: index * 15
                }
            }
        }
    }
}

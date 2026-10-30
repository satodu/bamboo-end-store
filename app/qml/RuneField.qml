import QtQuick

// Faded rune field: huge runes at 3-7% opacity scattered behind everything, drifting a few
// pixels. Atmosphere only, never carries meaning (GALDR.md 6.1).
Item {
    id: root
    clip: true

    // [x%, y%, size, rune index, tone (0 bone, 1 grey, 2 primary), alpha, drift seconds]
    property var spec: [
        [0.06, 0.10, 260, 0, 0, 0.04, 29], [0.78, 0.06, 180, 4, 1, 0.05, 23],
        [0.45, 0.32, 340, 9, 0, 0.03, 34], [0.90, 0.48, 220, 12, 2, 0.05, 27],
        [0.14, 0.60, 300, 17, 1, 0.04, 31], [0.62, 0.74, 200, 21, 0, 0.06, 25],
        [0.33, 0.90, 150, 6, 2, 0.05, 22], [0.84, 0.88, 280, 15, 0, 0.03, 33]
    ]

    Repeater {
        model: root.spec
        Text {
            id: glyph
            required property var modelData
            readonly property color base: modelData[4] === 2 ? Theme.primary
                                          : modelData[4] === 1 ? Theme.outline : Theme.fgSurface
            text: Style.futhark.charAt(modelData[3])
            font.family: Style.runeFont
            font.pixelSize: modelData[2]
            color: Qt.alpha(base, modelData[5])
            x: root.width * modelData[0] - width / 2
            y: root.height * modelData[1] - height / 2

            property real dx: 0
            property real dy: 0
            transform: Translate { x: glyph.dx; y: glyph.dy }

            SequentialAnimation on dx {
                loops: Animation.Infinite
                running: !Style.still && root.visible
                NumberAnimation { to: 7; duration: glyph.modelData[6] * 500; easing.type: Easing.InOutSine }
                NumberAnimation { to: -7; duration: glyph.modelData[6] * 500; easing.type: Easing.InOutSine }
            }
            SequentialAnimation on dy {
                loops: Animation.Infinite
                running: !Style.still && root.visible
                NumberAnimation { to: -5; duration: glyph.modelData[6] * 420; easing.type: Easing.InOutSine }
                NumberAnimation { to: 5; duration: glyph.modelData[6] * 420; easing.type: Easing.InOutSine }
            }
        }
    }
}

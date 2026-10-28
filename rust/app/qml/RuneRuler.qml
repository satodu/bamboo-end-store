import QtQuick

// Vertical rune ruler: a name transliterated into runes, stacked, at low contrast. One per screen
// (GALDR.md section 11). Decorative.
//
// A slow wave of light travels down it, brightening one rune after another toward the primary
// colour and then fading. It loops forever and stays calm: no pulsing, no glow, only a change of
// tone. It stops when motion is reduced.
Column {
    id: root

    property string runes: "ᛒᚨᛗᛒᛟᛟ"   // BAMBOO
    property real size: 14
    property real alpha: 0.45
    property bool animated: true
    /// One pass top to bottom, in ms (the wave also needs time to enter and leave).
    property int period: 9000

    spacing: size * 0.55

    // Position of the wave in rune units. Starts above the first rune and ends below the last.
    property real phase: -2

    NumberAnimation on phase {
        from: -2
        to: root.runes.length + 1
        duration: root.period
        loops: Animation.Infinite
        running: root.animated && !Style.still && root.visible
    }

    Repeater {
        model: root.runes.length
        Text {
            id: glyph
            required property int index
            // 0 far from the wave, 1 under its crest; a smooth bell, one rune and a half wide
            readonly property real lit: {
                const d = Math.abs(root.phase - index) / 1.6
                return d >= 1 ? 0 : 0.5 * (1 + Math.cos(Math.PI * d))
            }
            readonly property color base: Qt.alpha(index === 0 ? Theme.primary : Theme.fgSurface, root.alpha)
            text: root.runes.charAt(index)
            color: Style.mix(base, Qt.alpha(Theme.primary, 0.95), lit)
            font.family: Style.runeFont
            font.pixelSize: root.size
            scale: 1 + 0.1 * lit
        }
    }
}

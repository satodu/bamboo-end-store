import QtQuick

// Vertical rune ruler: a name transliterated into runes, stacked, at low contrast.
// One per screen (GALDR.md section 11). Decorative.
Column {
    id: root

    property string runes: "ᛒᚨᛗᛒᛟᛟ"   // BAMBOO
    property real size: 14
    property real alpha: 0.45

    spacing: size * 0.55

    Repeater {
        model: root.runes.length
        Text {
            required property int index
            text: root.runes.charAt(index)
            color: Qt.alpha(index === 0 ? Theme.primary : Theme.fgSurface, root.alpha)
            font.family: Style.runeFont
            font.pixelSize: root.size
        }
    }
}

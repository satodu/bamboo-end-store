import QtQuick

// One rune. `name` picks a meaning from Style.runes, or set `glyph` directly.
Text {
    property string name: "default"
    property string glyph: ""
    property real size: 16

    text: glyph.length ? glyph : Style.rune(name)
    color: Theme.primary
    font.family: Style.runeFont
    font.pixelSize: size
    renderType: Text.NativeRendering
}

pragma Singleton
import QtQuick

// Shape language from Galdr (tokens.json): 45-degree chamfers, hairlines, one fillet.
// Colours never live here; they come from Theme (Matugen).
QtObject {
    readonly property real chamfer: 14
    readonly property real chamferSm: 8
    readonly property real chamferXs: 5
    readonly property real border: 1
    readonly property real fillet: 2

    readonly property real gap: 16
    readonly property real gapSm: 8
    readonly property real pad: 20

    readonly property int quick: 140
    readonly property int medium: 260
    readonly property int slow: 420
    readonly property int easing: Easing.OutCubic

    readonly property string mono: "monospace"
}

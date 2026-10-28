pragma Singleton
import QtQuick
import io.bamboo.store

// Material You roles from Matugen (or the desktop palette). Every colour is a binding on the
// bridge's palette, so it updates live when the wallpaper palette or the theme setting changes.
// Magenta marks a role the palette lacks.
//
// Naming: Material's "on*" roles are exposed as "fg*" (onSurface -> fgSurface, onPrimary ->
// fgPrimary). QML reads a property called "onSurface" as a signal handler and silently
// returns black, so the "on" prefix cannot be used.
QtObject {
    id: root

    readonly property ThemeBridge bridge: ThemeBridge {}
    readonly property var c: JSON.parse(bridge.palette)

    readonly property bool dark: bridge.dark
    /// Galdr: wipes, typing, drifting runes and the ring stop when this is on.
    readonly property bool reduceMotion: bridge.reduceMotion
    /// "system", "preset" or "seed"
    readonly property string source: bridge.source

    readonly property color background: c.background ?? "#ff00ff"
    readonly property color fgBackground: c.onBackground ?? "#ff00ff"
    readonly property color surface: c.surface ?? "#ff00ff"
    readonly property color surfaceDim: c.surfaceDim ?? "#ff00ff"
    readonly property color surfaceBright: c.surfaceBright ?? "#ff00ff"
    readonly property color surfaceContainerLowest: c.surfaceContainerLowest ?? "#ff00ff"
    readonly property color surfaceContainerLow: c.surfaceContainerLow ?? "#ff00ff"
    readonly property color surfaceContainer: c.surfaceContainer ?? "#ff00ff"
    readonly property color surfaceContainerHigh: c.surfaceContainerHigh ?? "#ff00ff"
    readonly property color surfaceContainerHighest: c.surfaceContainerHighest ?? "#ff00ff"
    readonly property color surfaceVariant: c.surfaceVariant ?? "#ff00ff"
    readonly property color fgSurface: c.onSurface ?? "#ff00ff"
    readonly property color fgSurfaceVariant: c.onSurfaceVariant ?? "#ff00ff"
    readonly property color outline: c.outline ?? "#ff00ff"
    readonly property color outlineVariant: c.outlineVariant ?? "#ff00ff"
    readonly property color primary: c.primary ?? "#ff00ff"
    readonly property color fgPrimary: c.onPrimary ?? "#ff00ff"
    readonly property color primaryContainer: c.primaryContainer ?? "#ff00ff"
    readonly property color fgPrimaryContainer: c.onPrimaryContainer ?? "#ff00ff"
    readonly property color secondary: c.secondary ?? "#ff00ff"
    readonly property color fgSecondary: c.onSecondary ?? "#ff00ff"
    readonly property color secondaryContainer: c.secondaryContainer ?? "#ff00ff"
    readonly property color fgSecondaryContainer: c.onSecondaryContainer ?? "#ff00ff"
    readonly property color tertiary: c.tertiary ?? "#ff00ff"
    readonly property color fgTertiary: c.onTertiary ?? "#ff00ff"
    readonly property color tertiaryContainer: c.tertiaryContainer ?? "#ff00ff"
    readonly property color fgTertiaryContainer: c.onTertiaryContainer ?? "#ff00ff"
    readonly property color error: c.error ?? "#ff00ff"
    readonly property color fgError: c.onError ?? "#ff00ff"
    readonly property color errorContainer: c.errorContainer ?? "#ff00ff"
    readonly property color fgErrorContainer: c.onErrorContainer ?? "#ff00ff"
    readonly property color inverseSurface: c.inverseSurface ?? "#ff00ff"
    readonly property color inverseFg: c.inverseOnSurface ?? "#ff00ff"
    readonly property color inversePrimary: c.inversePrimary ?? "#ff00ff"
    readonly property color scrim: c.scrim ?? "#ff00ff"
    readonly property color shadow: c.shadow ?? "#ff00ff"
    readonly property color surfaceTint: c.surfaceTint ?? "#ff00ff"

    function reload() { bridge.reload() }
}

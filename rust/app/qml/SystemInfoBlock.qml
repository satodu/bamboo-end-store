import QtQuick
import QtQuick.Layouts

// Machine name, OS, kernel and app version, each led by a rune. On the very first launch the
// values start as runes and settle into text (`intro`); afterwards they just show.
//
// The four runes keep a slow wave of light travelling down them, one after another, forever:
// only a change of tone, calm, no pulsing. It stops when motion is reduced.
ColumnLayout {
    id: root

    /// {hostname, os, kernel, app_version}
    property var info: ({})
    /// Play the runes-to-text intro.
    property bool intro: false

    signal introFinished()

    spacing: 7

    readonly property var rows: [
        { rune: "ᛟ", text: info.hostname || "", tone: 0 },       // Othala: home, the machine
        { rune: "ᛁ", text: info.os || "", tone: 0 },             // Isa: the system's name
        { rune: "ᚦ", text: info.kernel || "", tone: 0 },         // Thurisaz: the thorn, the kernel
        { rune: "ᛒ", text: info.app_version ? "bamboo " + info.app_version : "", tone: 1 }  // Berkanan: this app
    ]
    property int done: 0

    /// Where the wave is, in line units: starts above the first rune, ends below the last.
    property real phase: -2
    NumberAnimation on phase {
        from: -2
        to: root.rows.length + 1
        duration: 7000
        loops: Animation.Infinite
        running: !Style.still && root.visible
    }

    // With reduced motion nothing animates, so the intro is over the moment it is asked for.
    onIntroChanged: if (intro && Style.still) introFinished()

    Repeater {
        model: root.rows
        delegate: RowLayout {
            id: line
            required property var modelData
            required property int index
            spacing: 8

            Rune {
                id: icon
                glyph: line.modelData.rune
                size: 14
                // 0 away from the wave, 1 under its crest: a smooth bell about two lines wide
                readonly property real lit: {
                    const d = Math.abs(root.phase - line.index) / 1.6
                    return d >= 1 ? 0 : 0.5 * (1 + Math.cos(Math.PI * d))
                }
                readonly property color base: line.modelData.tone === 1 ? Theme.primary : Qt.alpha(Theme.fgSurface, 0.5)
                color: Style.mix(base, Qt.alpha(Theme.primary, 0.95), lit)
                scale: 1 + 0.12 * lit
                Layout.preferredWidth: 14
                horizontalAlignment: Text.AlignHCenter
            }
            RuneDecode {
                // long names must not run out of the sidebar
                text: line.modelData.text.length > 24 ? line.modelData.text.slice(0, 23) + "…" : line.modelData.text
                decode: root.intro
                startDelay: 350 + line.index * 260
                color: line.modelData.tone === 1 ? Theme.primary : Theme.fgSurfaceVariant
                // a line that had nothing to animate also reports finished: only count while the intro runs
                onFinished: { if (!root.intro) return; root.done++; if (root.done === root.rows.length) root.introFinished() }
            }
        }
    }
}

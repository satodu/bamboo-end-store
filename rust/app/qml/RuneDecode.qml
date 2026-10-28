import QtQuick

// A line of text that first shows runes and settles into the text one character at a time, left
// to right, while the not-yet-settled ones keep changing (Galdr: technical text enters "being
// filled in"). Characters sit in fixed cells so the line does not jitter as runes become letters.
// With `decode` off, or with reduced motion, it just shows the text.
Row {
    id: root

    property string text: ""
    property bool decode: false
    property color color: Theme.fgSurfaceVariant
    property real pixelSize: 11
    /// Wait before the first character settles, in ms.
    property int startDelay: 300
    /// Time between one character settling and the next, in ms.
    property int perChar: 42

    signal finished()

    readonly property bool playing: decode && !Style.still
    property int elapsed: playing ? 0 : 1000000
    property int tick: 0
    readonly property real cell: metrics.advanceWidth + 0.6

    TextMetrics {
        id: metrics
        font.family: Style.mono
        font.pixelSize: root.pixelSize
        text: "M"
    }

    Repeater {
        model: root.text.length
        Item {
            id: slot
            required property int index
            readonly property string target: root.text.charAt(index)
            readonly property bool settled: target === " " || root.elapsed >= root.startDelay + index * root.perChar
            width: root.cell
            height: root.pixelSize * 1.6

            Text {
                anchors.centerIn: parent
                text: slot.settled ? slot.target : Style.futhark.charAt((slot.index * 7 + root.tick * 5) % Style.futhark.length)
                color: slot.settled ? root.color : Qt.alpha(Theme.primary, 0.85)
                font.family: slot.settled ? Style.mono : Style.runeFont
                font.pixelSize: slot.settled ? root.pixelSize : root.pixelSize + 1
            }
        }
    }

    Timer {
        interval: 60
        repeat: true
        running: root.playing && root.elapsed < root.startDelay + root.text.length * root.perChar + 120
        onTriggered: {
            root.tick++
            root.elapsed += interval
            if (root.elapsed >= root.startDelay + root.text.length * root.perChar + 120) root.finished()
        }
    }

    // Nothing to animate: report right away so callers can rely on `finished`.
    Component.onCompleted: if (!playing) finished()
}

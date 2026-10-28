import QtQuick

// Technical text enters "being filled in": revealed character by character (Galdr rule 5).
// Re-types whenever `full` changes. Sized from the full string so layout does not jump.
Item {
    id: root

    property string full: ""
    property color color: Theme.fgSurface
    property font font
    property int wrapMode: Text.NoWrap
    property int elide: Text.ElideNone
    property int maximumLineCount: 2147483647
    property real progress: 1

    implicitWidth: sizer.implicitWidth
    implicitHeight: sizer.implicitHeight

    onFullChanged: {
        if (Style.still || full.length === 0) { progress = 1; return }
        progress = 0
        type.restart()
    }

    NumberAnimation {
        id: type
        target: root
        property: "progress"
        from: 0
        to: 1
        duration: Math.min(650, root.full.length * 14)
    }

    Text {
        id: sizer
        width: root.width
        text: root.full
        font: root.font
        wrapMode: root.wrapMode
        elide: root.elide
        maximumLineCount: root.maximumLineCount
        opacity: 0
    }
    Text {
        width: root.width
        height: root.height
        text: root.full.slice(0, Math.round(root.progress * root.full.length))
        color: root.color
        font: root.font
        wrapMode: root.wrapMode
        elide: root.elide
        maximumLineCount: root.maximumLineCount
    }
}

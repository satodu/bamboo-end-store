import QtQuick

// A package icon. When there is none (or while it loads) a chamfered tile shows the first letter of
// the name carved as its Elder Futhark rune (`Style.initialRune`): every package gets its own mark.
Item {
    id: root

    property string name: ""
    property url source: ""
    property real size: 48

    implicitWidth: size
    implicitHeight: size

    readonly property var tints: [
        [Theme.primaryContainer, Theme.fgPrimaryContainer],
        [Theme.secondaryContainer, Theme.fgSecondaryContainer],
        [Theme.tertiaryContainer, Theme.fgTertiaryContainer]
    ]
    // A stable tone picked from the name, so neighbours differ.
    readonly property int tint: {
        let h = 0
        for (let i = 0; i < name.length; i++)
            h = (h * 31 + name.charCodeAt(i)) % 3
        return h
    }
    readonly property string rune: Style.initialRune(name)
    readonly property bool showImage: img.status === Image.Ready

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: root.size * 0.22
        visible: !root.showImage
        fill: root.tints[root.tint][0]
        stroke: "transparent"
        strokeWidth: 0

        Text {
            anchors.centerIn: parent
            text: root.rune
            color: root.tints[root.tint][1]
            font.family: Style.runeFont
            font.pixelSize: root.size * 0.74
        }
    }

    Image {
        id: img
        anchors.fill: parent
        source: root.source
        asynchronous: true
        cache: true
        fillMode: Image.PreserveAspectFit
        sourceSize.width: root.size * 2
        sourceSize.height: root.size * 2
        visible: root.showImage
    }
}

import QtQuick

// Remote icon with a letter tile fallback (also used while loading and for packages
// that have no icon, which is most of the pacman and AUR results).
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
    readonly property int tint: {
        let h = 0
        for (let i = 0; i < name.length; i++)
            h = (h * 31 + name.charCodeAt(i)) % 3
        return h
    }
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
            text: root.name.length > 0 ? root.name.charAt(0).toUpperCase() : "?"
            color: root.tints[root.tint][1]
            font.pixelSize: root.size * 0.46
            font.weight: Font.Bold
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

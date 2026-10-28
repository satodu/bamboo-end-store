import QtQuick

// A package icon. When there is none (or while it loads) a chamfered tile shows the rune of the
// package's origin (pacman, AUR, Flatpak...) with the first letter small in the corner, so two
// unknown packages still differ and the origin reads at a glance.
Item {
    id: root

    property string name: ""
    property url source: ""
    /// pacman, aur, flatpak, custom or appimage; empty falls back to the letter alone.
    property string origin: ""
    property real size: 48

    implicitWidth: size
    implicitHeight: size

    readonly property var tints: [
        [Theme.primaryContainer, Theme.fgPrimaryContainer],
        [Theme.secondaryContainer, Theme.fgSecondaryContainer],
        [Theme.tertiaryContainer, Theme.fgTertiaryContainer]
    ]
    // The tone follows the origin so a list reads at a glance; without one, a stable pick from the name.
    readonly property int tint: {
        switch (origin) {
        case "pacman": return 0
        case "flatpak": return 1
        case "aur": return 2
        case "custom": case "appimage": return 0
        }
        let h = 0
        for (let i = 0; i < name.length; i++)
            h = (h * 31 + name.charCodeAt(i)) % 3
        return h
    }
    readonly property string rune: Style.originRunes[origin] || ""
    readonly property string letter: name.length > 0 ? name.charAt(0).toUpperCase() : "?"
    readonly property bool showImage: img.status === Image.Ready

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: root.size * 0.22
        visible: !root.showImage
        fill: root.tints[root.tint][0]
        stroke: "transparent"
        strokeWidth: 0

        // no origin: the letter, big
        Text {
            visible: root.rune.length === 0
            anchors.centerIn: parent
            text: root.letter
            color: root.tints[root.tint][1]
            font.pixelSize: root.size * 0.46
            font.weight: Font.Bold
        }

        // with an origin: its rune, big, and the letter small in the corner
        Text {
            visible: root.rune.length > 0
            anchors.centerIn: parent
            text: root.rune
            color: root.tints[root.tint][1]
            font.family: Style.runeFont
            font.pixelSize: root.size * 0.56
        }
        Text {
            visible: root.rune.length > 0 && root.size >= 34
            anchors.right: parent.right
            anchors.rightMargin: root.size * 0.1
            anchors.bottom: parent.bottom
            anchors.bottomMargin: root.size * 0.06
            text: root.letter
            color: root.tints[root.tint][1]
            opacity: 0.75
            font.pixelSize: root.size * 0.24
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

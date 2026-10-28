import QtQuick

// Small chamfered label. tone: "neutral", "primary", "tertiary", "error".
Item {
    id: root

    property string text: ""
    property string tone: "neutral"

    implicitWidth: label.implicitWidth + 16
    implicitHeight: 20

    readonly property color wash: {
        switch (tone) {
        case "primary": return Theme.primaryContainer
        case "tertiary": return Theme.tertiaryContainer
        case "error": return Theme.errorContainer
        default: return Theme.surfaceContainerHighest
        }
    }
    readonly property color ink: {
        switch (tone) {
        case "primary": return Theme.fgPrimaryContainer
        case "tertiary": return Theme.fgTertiaryContainer
        case "error": return Theme.fgErrorContainer
        default: return Theme.fgSurfaceVariant
        }
    }

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferXs
        fill: root.wash
        stroke: "transparent"
        strokeWidth: 0
    }

    Text {
        id: label
        anchors.centerIn: parent
        text: root.text
        color: root.ink
        font.pixelSize: 10
        font.weight: Font.DemiBold
        font.letterSpacing: 1
        font.capitalization: Font.AllUppercase
    }
}

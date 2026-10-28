import QtQuick
import QtQuick.Layouts

// A shell command in a monospace box with a copy button. The app is console first: every package
// shows the exact command it stands for, ready to paste.
ColumnLayout {
    id: root

    property string label: ""
    property string command: ""
    property bool copied: false

    spacing: 6
    visible: command.length > 0

    // Clipboard access without a native helper: select the text of a hidden edit and copy it.
    TextEdit { id: clip; visible: false }

    Timer { id: reset; interval: 2000; onTriggered: root.copied = false }

    RowLayout {
        Layout.fillWidth: true
        Text {
            Layout.fillWidth: true
            text: root.label
            color: Theme.fgSurfaceVariant
            font.family: Style.mono
            font.pixelSize: 10
            font.letterSpacing: 1.5
            font.capitalization: Font.AllUppercase
            elide: Text.ElideRight
        }
        Btn {
            text: root.copied ? Tr.t("Copied!") : Tr.t("Copy")
            variant: "ghost"
            implicitHeight: 26
            onClicked: {
                clip.text = root.command
                clip.selectAll()
                clip.copy()
                root.copied = true
                reset.restart()
            }
        }
    }

    Item {
        Layout.fillWidth: true
        Layout.preferredHeight: line.implicitHeight + 24

        ChamferPanel {
            anchors.fill: parent
            padding: 0
            chamfer: Style.chamferSm
            fill: Theme.surfaceContainerLowest
            stroke: root.copied ? Theme.primary : Theme.outlineVariant
        }

        Row {
            anchors.fill: parent
            anchors.margins: 12
            spacing: 8
            Text {
                text: "$"
                color: Theme.primary
                font.family: Style.mono
                font.pixelSize: 12
                font.weight: Font.Bold
            }
            Text {
                id: line
                width: parent.width - 20
                text: root.command
                color: Theme.fgSurface
                font.family: Style.mono
                font.pixelSize: 12
                wrapMode: Text.WrapAnywhere
            }
        }
    }
}

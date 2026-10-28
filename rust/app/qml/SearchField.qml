import QtQuick

// Chamfered search box with placeholder and a clear button.
Item {
    id: root

    property alias text: input.text
    property string placeholder: ""

    signal accepted()

    implicitHeight: 44
    implicitWidth: 320

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferSm
        fill: Theme.surfaceContainerLow
        stroke: input.activeFocus ? Theme.primary : Theme.outlineVariant
    }

    TextInput {
        id: input
        anchors.fill: parent
        anchors.leftMargin: 16
        anchors.rightMargin: 40
        verticalAlignment: TextInput.AlignVCenter
        color: Theme.fgSurface
        selectionColor: Theme.primary
        selectedTextColor: Theme.fgPrimary
        font.pixelSize: 14
        clip: true
        onAccepted: root.accepted()

        Text {
            anchors.verticalCenter: parent.verticalCenter
            text: root.placeholder
            color: Theme.fgSurfaceVariant
            font: input.font
            visible: input.text.length === 0 && !input.preeditText
        }
    }

    Text {
        anchors.right: parent.right
        anchors.rightMargin: 14
        anchors.verticalCenter: parent.verticalCenter
        text: "✕"
        color: Theme.fgSurfaceVariant
        font.pixelSize: 13
        visible: input.text.length > 0
        MouseArea {
            anchors.fill: parent
            anchors.margins: -8
            cursorShape: Qt.PointingHandCursor
            onClicked: { input.text = ""; input.forceActiveFocus() }
        }
    }
}

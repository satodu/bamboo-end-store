import QtQuick

// Chamfered text field (GALDR.md 7.23): surface field, 2 px primary left rule, a rune as icon.
Item {
    id: root

    property alias text: input.text
    property alias readOnly: input.readOnly
    property alias validator: input.validator
    property alias inputMethodHints: input.inputMethodHints
    property alias maximumLength: input.maximumLength
    property string placeholder: ""
    /// Rune name from Style.runes, or "" for none.
    property string rune: ""
    property bool clearable: false
    /// Keep the primary rule visible when idle (search); otherwise only while focused.
    property bool ruleAlways: false
    readonly property alias focused: input.activeFocus

    signal accepted()
    signal edited()

    implicitHeight: 44
    implicitWidth: 320

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferSm
        fill: Theme.surfaceContainerLow
        stroke: input.activeFocus ? Theme.primary : Theme.outlineVariant
    }

    Rectangle {
        x: 1
        y: 1
        width: Style.fillet
        height: parent.height - 2 - Style.chamferSm * 0.5
        color: Theme.primary
        opacity: root.ruleAlways || input.activeFocus ? 1 : 0
        Behavior on opacity { NumberAnimation { duration: Style.quick } }
    }

    Rune {
        id: icon
        visible: root.rune.length > 0
        name: root.rune
        size: 17
        color: input.activeFocus ? Theme.primary : Theme.fgSurfaceVariant
        anchors.left: parent.left
        anchors.leftMargin: 14
        anchors.verticalCenter: parent.verticalCenter
    }

    TextInput {
        id: input
        anchors.fill: parent
        anchors.leftMargin: icon.visible ? 42 : 16
        anchors.rightMargin: root.clearable ? 40 : 14
        verticalAlignment: TextInput.AlignVCenter
        color: Theme.fgSurface
        selectionColor: Theme.primary
        selectedTextColor: Theme.fgPrimary
        font.pixelSize: 14
        clip: true
        selectByMouse: true
        onAccepted: root.accepted()
        onTextEdited: root.edited()

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
        visible: root.clearable && input.text.length > 0
        MouseArea {
            anchors.fill: parent
            anchors.margins: -8
            cursorShape: Qt.PointingHandCursor
            onClicked: { input.text = ""; input.forceActiveFocus(); root.edited() }
        }
    }
}

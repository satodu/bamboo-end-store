import QtQuick
import QtQuick.Controls

// Dropdown in the Galdr shape: a chamfered field that opens a chamfered list by diagonal wipe.
// options: [{label, value}]; `current` is the selected value.
Item {
    id: root

    property var options: []
    property var current
    /// Small mono caption inside the field, before the value (e.g. "Sort by").
    property string caption: ""
    readonly property alias opened: popup.visible

    signal activated(var value)

    function open() { popup.open() }
    function close() { popup.close() }

    readonly property string currentLabel: {
        for (const o of options) if (o.value === current) return o.label
        return ""
    }

    implicitHeight: 40
    implicitWidth: 240
    opacity: enabled ? 1 : 0.38

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferSm
        fill: mouse.containsMouse ? Theme.surfaceContainerHigh : Theme.surfaceContainerLow
        stroke: popup.visible || root.activeFocus ? Theme.primary : Theme.outlineVariant
    }

    Row {
        anchors.verticalCenter: parent.verticalCenter
        anchors.left: parent.left
        anchors.leftMargin: 14
        anchors.right: chevron.left
        anchors.rightMargin: 8
        spacing: 10

        Text {
            visible: root.caption.length > 0
            anchors.verticalCenter: parent.verticalCenter
            text: root.caption
            color: Theme.fgSurfaceVariant
            font.family: Style.mono
            font.pixelSize: 10
            font.letterSpacing: 1.5
            font.capitalization: Font.AllUppercase
        }
        Text {
            anchors.verticalCenter: parent.verticalCenter
            width: parent.width - x
            text: root.currentLabel
            color: Theme.fgSurface
            font.pixelSize: 13
            font.weight: Font.DemiBold
            elide: Text.ElideRight
        }
    }

    // Down-pointing rhombus half, drawn as text so it follows the palette.
    Text {
        id: chevron
        anchors.right: parent.right
        anchors.rightMargin: 14
        anchors.verticalCenter: parent.verticalCenter
        text: "▾"
        color: popup.visible ? Theme.primary : Theme.fgSurfaceVariant
        font.pixelSize: 14
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        cursorShape: Qt.PointingHandCursor
        onClicked: popup.visible ? popup.close() : popup.open()
    }

    Popup {
        id: popup
        y: root.height + 4
        width: Math.max(root.width, 180)
        height: Math.min(root.options.length * 38, 320) + 2
        padding: 0
        closePolicy: Popup.CloseOnEscape | Popup.CloseOnPressOutside
        enter: null
        exit: null

        background: Item {}

        contentItem: Loader {
            active: popup.visible
            sourceComponent: Reveal {
                slide: 6
                ChamferPanel {
                    anchors.fill: parent
                    padding: 0
                    chamfer: Style.chamferSm
                    fill: Theme.surfaceContainerHigh
                    stroke: Theme.primary

                    ListView {
                        id: list
                        anchors.fill: parent
                        anchors.margins: 1
                        clip: true
                        model: root.options
                        boundsBehavior: Flickable.StopAtBounds
                        interactive: contentHeight > height

                        delegate: Item {
                            id: row
                            required property var modelData
                            required property int index
                            readonly property bool on: root.current === modelData.value
                            width: list.width
                            height: 38

                            SegmentFill {
                                id: fill
                                anchors.fill: parent
                                color: row.on ? Theme.secondaryContainer : rowMouse.containsMouse ? Theme.surfaceContainerHighest : "transparent"
                                cutTR: row.index === 0 ? fill.inset : 0
                                cutBL: row.index === root.options.length - 1 ? fill.inset : 0
                                Behavior on color { ColorAnimation { duration: Style.quick } }
                            }
                            Rectangle {
                                // stays inside the chamfered bottom-left corner on the last row
                                y: row.index === root.options.length - 1 ? fill.inset : 0
                                width: Style.fillet
                                height: row.on ? parent.height - y : 0
                                color: Theme.primary
                            }
                            Text {
                                anchors.verticalCenter: parent.verticalCenter
                                anchors.left: parent.left
                                anchors.leftMargin: 16
                                anchors.right: parent.right
                                anchors.rightMargin: 12
                                text: row.modelData.label
                                color: row.on ? Theme.fgSecondaryContainer : Theme.fgSurface
                                font.pixelSize: 13
                                font.weight: row.on ? Font.DemiBold : Font.Normal
                                elide: Text.ElideRight
                            }
                            MouseArea {
                                id: rowMouse
                                anchors.fill: parent
                                hoverEnabled: true
                                cursorShape: Qt.PointingHandCursor
                                onClicked: { popup.close(); root.activated(row.modelData.value) }
                            }
                        }
                    }
                }
            }
        }
    }
}

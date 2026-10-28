import QtQuick

// Chamfered segmented control. options: [{label, value}]; the active segment is primary.
Item {
    id: root

    property var options: []
    property var current
    signal activated(var value)

    implicitHeight: 36
    implicitWidth: row.implicitWidth

    ChamferPanel {
        anchors.fill: parent
        padding: 0
        chamfer: Style.chamferSm
        fill: Theme.surfaceContainerLow
        stroke: Theme.outline
    }

    Row {
        id: row
        anchors.fill: parent
        anchors.margins: 1

        Repeater {
            model: root.options
            Item {
                id: seg
                required property var modelData
                required property int index
                readonly property bool on: root.current === modelData.value
                width: Math.max(64, label.implicitWidth + 28)
                height: row.height

                SegmentFill {
                    id: fill
                    anchors.fill: parent
                    color: seg.on ? Theme.primary : segMouse.containsMouse ? Theme.surfaceContainerHigh : "transparent"
                    cutBL: seg.index === 0 ? fill.inset : 0
                    cutTR: seg.index === root.options.length - 1 ? fill.inset : 0
                    Behavior on color { ColorAnimation { duration: Style.quick } }
                }
                Text {
                    id: label
                    anchors.centerIn: parent
                    text: seg.modelData.label
                    color: seg.on ? Theme.fgPrimary : Theme.fgSurface
                    font.pixelSize: 12
                    font.weight: Font.DemiBold
                    font.letterSpacing: 1
                    font.capitalization: Font.AllUppercase
                }
                MouseArea {
                    id: segMouse
                    anchors.fill: parent
                    hoverEnabled: true
                    cursorShape: Qt.PointingHandCursor
                    onClicked: root.activated(seg.modelData.value)
                }
            }
        }
    }
}

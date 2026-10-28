import QtQuick
import QtQuick.Controls

// Thumbnails of a package's screenshots; a click opens the full-window viewer.
Item {
    id: root

    property var images: []

    implicitHeight: 92
    visible: images.length > 0

    /// Open the full-window viewer at picture `i`.
    function open(i) { box.show(i) }

    Lightbox { id: box; images: root.images }

    Flickable {
        anchors.fill: parent
        contentWidth: row.width
        clip: true
        boundsBehavior: Flickable.StopAtBounds
        ScrollBar.horizontal: ScrollBar { policy: ScrollBar.AsNeeded }

        Row {
            id: row
            spacing: 10

            Repeater {
                model: root.images
                delegate: Item {
                    id: thumb
                    required property string modelData
                    required property int index
                    width: 150
                    height: 84

                    ChamferPanel {
                        anchors.fill: parent
                        padding: 0
                        chamfer: Style.chamferSm
                        fill: Theme.surfaceContainerHigh
                        stroke: mouse.containsMouse ? Theme.primary : Theme.outlineVariant
                    }
                    Image {
                        anchors.fill: parent
                        anchors.margins: 2
                        source: thumb.modelData
                        fillMode: Image.PreserveAspectCrop
                        asynchronous: true
                        cache: true
                        sourceSize.width: 320
                    }
                    RuneSpinner { anchors.centerIn: parent; running: false; size: 18 }
                    MouseArea {
                        id: mouse
                        anchors.fill: parent
                        hoverEnabled: true
                        cursorShape: Qt.PointingHandCursor
                        onClicked: box.show(thumb.index)
                    }
                }
            }
        }
    }
}

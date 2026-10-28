import QtQuick
import QtQuick.Controls

// Full-window screenshot viewer on a scrim: previous and next, a counter, Escape or a click outside
// to close. A Popup, so it sits above everything and is not clipped by the details panel.
Popup {
    id: root

    property var images: []
    property int index: 0

    parent: Overlay.overlay
    x: 0
    y: 0
    width: Overlay.overlay ? Overlay.overlay.width : 0
    height: Overlay.overlay ? Overlay.overlay.height : 0
    modal: true
    padding: 0
    closePolicy: Popup.CloseOnEscape
    enter: null
    exit: null

    function show(i) { index = i; open() }
    function next() { index = (index + 1) % images.length }
    function previous() { index = (index - 1 + images.length) % images.length }

    background: Rectangle { color: Qt.rgba(0, 0, 0, 0.82) }

    Overlay.modal: Rectangle { color: "transparent" }

    contentItem: Item {
        focus: true
        Keys.onLeftPressed: root.previous()
        Keys.onRightPressed: root.next()

        MouseArea { anchors.fill: parent; onClicked: root.close() }

        Image {
            id: big
            anchors.fill: parent
            anchors.margins: 70
            source: root.images.length ? root.images[root.index] : ""
            fillMode: Image.PreserveAspectFit
            asynchronous: true
            cache: true
            // do not let a click on the picture close it
            MouseArea { anchors.fill: parent; onClicked: root.next() }
        }
        RuneSpinner { anchors.centerIn: parent; running: big.status === Image.Loading; size: 34 }

        Text {
            anchors.top: parent.top
            anchors.topMargin: 24
            anchors.horizontalCenter: parent.horizontalCenter
            text: (root.index + 1) + " / " + root.images.length
            color: Qt.alpha(Theme.fgSurface, 0.7)
            font.family: Style.mono
            font.pixelSize: 12
            font.letterSpacing: 3
        }

        Btn {
            visible: root.images.length > 1
            anchors.left: parent.left
            anchors.leftMargin: 18
            anchors.verticalCenter: parent.verticalCenter
            text: Tr.t("Prev")
            variant: "outline"
            onClicked: root.previous()
        }
        Btn {
            visible: root.images.length > 1
            anchors.right: parent.right
            anchors.rightMargin: 18
            anchors.verticalCenter: parent.verticalCenter
            text: Tr.t("Next")
            variant: "outline"
            onClicked: root.next()
        }
        Text {
            anchors.top: parent.top
            anchors.right: parent.right
            anchors.margins: 22
            text: "✕"
            color: Qt.alpha(Theme.fgSurface, 0.8)
            font.pixelSize: 20
            MouseArea { anchors.fill: parent; anchors.margins: -10; cursorShape: Qt.PointingHandCursor; onClicked: root.close() }
        }
    }
}

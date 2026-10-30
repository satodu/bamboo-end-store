import QtQuick
import QtQuick.Layouts

// EndeavourOS announcements as a slider. Click opens the post in the browser. It advances by itself
// (not while the pointer is over it, and never under reduced motion).
Item {
    id: root

    // `[{title, link, date, summary, image}]`, newest first; empty when offline or switched off in Settings.
    property var items: []
    property int index: 0
    readonly property var current: items.length > 0 ? items[Math.min(index, items.length - 1)] : null

    visible: current !== null
    implicitHeight: visible ? 232 : 0

    function go(i) {
        if (items.length === 0) return
        index = (i + items.length) % items.length
        reveal.play()
        auto.restart()
    }
    onItemsChanged: index = 0

    Timer {
        id: auto
        interval: 8000
        repeat: true
        running: root.visible && root.items.length > 1 && !hit.containsMouse && !Style.still
        onTriggered: root.go(root.index + 1)
    }

    Reveal {
        id: reveal
        anchors.fill: parent
        ChamferPanel {
            anchors.fill: parent
            fill: Theme.surfaceContainer
            details: true
            stroke: hit.containsMouse ? Theme.primary : Theme.outlineVariant

            MouseArea {
                id: hit
                anchors.fill: parent
                anchors.margins: -Style.pad
                hoverEnabled: true
                cursorShape: Qt.PointingHandCursor
                onClicked: Qt.openUrlExternally(root.current.link)
            }

            RowLayout {
                anchors.fill: parent
                spacing: 20

                Item {
                    Layout.preferredWidth: Math.min(380, parent.width * 0.42)
                    Layout.fillHeight: true
                    visible: parent.width > 560

                    Image {
                        id: cover
                        anchors.fill: parent
                        source: root.current ? root.current.image : ""
                        sourceSize.width: 760
                        fillMode: Image.PreserveAspectCrop
                        asynchronous: true
                        visible: status === Image.Ready
                    }
                    Rune {
                        anchors.centerIn: parent
                        visible: cover.status !== Image.Ready
                        name: "text"
                        size: 64
                        opacity: 0.5
                    }
                }

                ColumnLayout {
                    Layout.fillWidth: true
                    Layout.fillHeight: true
                    spacing: 6

                    RowLayout {
                        spacing: 10
                        Tag { text: Tr.t("Latest from EndeavourOS"); tone: "primary" }
                        Text {
                            text: root.current ? root.current.date : ""
                            color: Theme.fgSurfaceVariant
                            font.family: Style.mono
                            font.pixelSize: 11
                            font.letterSpacing: 1.5
                            font.capitalization: Font.AllUppercase
                        }
                    }
                    Text {
                        Layout.fillWidth: true
                        text: root.current ? root.current.title : ""
                        color: Theme.fgSurface
                        font.family: Style.displayFont
                        font.pixelSize: 28
                        font.weight: Font.ExtraBold
                        font.capitalization: Font.AllUppercase
                        font.letterSpacing: 1
                        wrapMode: Text.WordWrap
                        maximumLineCount: 2
                        elide: Text.ElideRight
                    }
                    Text {
                        Layout.fillWidth: true
                        visible: text.length > 0
                        text: root.current ? root.current.summary : ""
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 13
                        wrapMode: Text.WordWrap
                        maximumLineCount: 3
                        elide: Text.ElideRight
                    }
                    Item { Layout.fillHeight: true }

                    RowLayout {
                        Layout.fillWidth: true
                        spacing: 10
                        Btn {
                            text: Tr.t("Read")
                            onClicked: Qt.openUrlExternally(root.current.link)
                        }
                        Item { Layout.fillWidth: true }
                        Btn {
                            visible: root.items.length > 1
                            text: "<"
                            variant: "ghost"
                            implicitWidth: 38
                            onClicked: root.go(root.index - 1)
                        }
                        // One 2 px rule per slide; the active one is primary.
                        Row {
                            visible: root.items.length > 1
                            spacing: 6
                            Repeater {
                                model: root.items.length
                                Item {
                                    required property int index
                                    width: 22; height: 16
                                    Rectangle {
                                        anchors.verticalCenter: parent.verticalCenter
                                        width: parent.width; height: 2
                                        color: index === root.index ? Theme.primary : Theme.outlineVariant
                                    }
                                    MouseArea {
                                        anchors.fill: parent
                                        cursorShape: Qt.PointingHandCursor
                                        onClicked: root.go(index)
                                    }
                                }
                            }
                        }
                        Btn {
                            visible: root.items.length > 1
                            text: ">"
                            variant: "ghost"
                            implicitWidth: 38
                            onClicked: root.go(root.index + 1)
                        }
                    }
                }
            }
        }
    }
}

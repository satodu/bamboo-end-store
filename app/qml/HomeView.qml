import QtQuick
import QtQuick.Controls
import QtQuick.Layouts

// Explore home: the official EndeavourOS tools this system has, then the curated categories.
Item {
    id: root

    property var categories: []
    property var essentials: []
    property bool busy: false

    signal categoryOpened(string name)
    signal packageOpened(var pkg)
    signal installRequested(var pkg)
    signal removeRequested(var pkg)

    ScrollView {
        id: scroll
        anchors.fill: parent
        contentWidth: availableWidth
        clip: true

        ColumnLayout {
            width: scroll.availableWidth
            spacing: 18

            // Official tools: only present on EndeavourOS.
            ColumnLayout {
                Layout.fillWidth: true
                visible: root.essentials.length > 0
                spacing: 12

                Text {
                    text: Tr.t("Official EndeavourOS Tools")
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 2
                    font.capitalization: Font.AllUppercase
                }
                GridLayout {
                    Layout.fillWidth: true
                    columns: Math.max(1, Math.floor(scroll.availableWidth / 300))
                    columnSpacing: 16
                    rowSpacing: 16
                    Repeater {
                        model: root.essentials
                        delegate: Reveal {
                            required property var modelData
                            required property int index
                            Layout.fillWidth: true
                            Layout.preferredHeight: 184
                            delay: index * 40
                            PackageCard {
                                anchors.fill: parent
                                pkg: modelData
                                busy: root.busy
                                onOpened: root.packageOpened(modelData)
                                onInstallRequested: root.installRequested(modelData)
                                onRemoveRequested: root.removeRequested(modelData)
                            }
                        }
                    }
                }
            }

            ColumnLayout {
                spacing: 4
                Text {
                    text: Tr.t("Explore Categories")
                    color: Theme.fgSurface
                    font.family: Style.displayFont
                    font.pixelSize: 40
                    font.weight: Font.ExtraBold
                    font.capitalization: Font.AllUppercase
                    font.letterSpacing: 2
                    Rectangle {
                        anchors.left: parent.right; anchors.leftMargin: 3
                        anchors.bottom: parent.bottom; anchors.bottomMargin: 8
                        width: 7; height: 7; color: Theme.primary
                    }
                }
                Text {
                    text: Tr.t("Curated software by EndeavourOS")
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 1.5
                    font.capitalization: Font.AllUppercase
                }
            }

            GridLayout {
                Layout.fillWidth: true
                columns: Math.max(1, Math.floor(scroll.availableWidth / 330))
                columnSpacing: 16
                rowSpacing: 16

                Repeater {
                    model: root.categories
                    delegate: Reveal {
                        required property var modelData
                        required property int index
                        Layout.fillWidth: true
                        Layout.preferredHeight: 172
                        autoPlay: index < 24
                        delay: Math.min(index, 23) * 30
                        CategoryTile {
                            anchors.fill: parent
                            category: modelData
                            onOpened: root.categoryOpened(modelData.name)
                        }
                    }
                }
            }

            Plinth {
                Layout.alignment: Qt.AlignHCenter
                Layout.preferredWidth: 420
                Layout.preferredHeight: 300
                visible: root.categories.length === 0
                caption: Tr.t("Loading curated categories...")
            }

            Item { Layout.preferredHeight: 16 }
        }
    }
}

import QtQuick
import QtQuick.Layouts

// Grid card for one search result. `pkg` is an object from Backend.results.
Item {
    id: root

    property var pkg: ({})
    property bool busy: false
    property bool showInstalledTag: true

    signal opened()
    signal installRequested()
    signal removeRequested()

    implicitWidth: 280
    implicitHeight: 184

    ChamferPanel {
        anchors.fill: parent
        padding: 16
        fill: mouse.containsMouse ? Theme.surfaceContainerHigh : Theme.surfaceContainerLow
        stroke: mouse.containsMouse ? Theme.primary : Theme.outlineVariant

        ColumnLayout {
            anchors.fill: parent
            spacing: 10

            RowLayout {
                Layout.fillWidth: true
                spacing: 12

                PackageIcon {
                    name: root.pkg.display_name || root.pkg.name || ""
                    size: 44
                }
                ColumnLayout {
                    Layout.fillWidth: true
                    spacing: 2
                    Text {
                        Layout.fillWidth: true
                        text: root.pkg.display_name || root.pkg.name || ""
                        color: Theme.fgSurface
                        font.pixelSize: 15
                        font.weight: Font.DemiBold
                        elide: Text.ElideRight
                    }
                    Text {
                        Layout.fillWidth: true
                        text: root.pkg.version || ""
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 11
                        font.family: Style.mono
                        elide: Text.ElideRight
                    }
                }
            }

            Text {
                Layout.fillWidth: true
                Layout.fillHeight: true
                text: root.pkg.description || ""
                color: Theme.fgSurfaceVariant
                font.pixelSize: 12
                wrapMode: Text.Wrap
                maximumLineCount: 2
                elide: Text.ElideRight
            }

            RowLayout {
                Layout.fillWidth: true
                spacing: 6

                Tag { text: root.pkg.source || ""; tone: root.pkg.source === "aur" ? "tertiary" : "neutral" }
                Tag { visible: root.showInstalledTag && !!root.pkg.installed; text: Tr.t("Installed"); tone: "primary" }
                Item { Layout.fillWidth: true }
                Btn {
                    text: root.pkg.installed ? Tr.t("Remove") : Tr.t("Install")
                    variant: root.pkg.installed ? "outline" : "filled"
                    busy: root.busy
                    implicitHeight: 32
                    onClicked: root.pkg.installed ? root.removeRequested() : root.installRequested()
                }
            }
        }
    }

    // Under the button so the button still receives its own clicks.
    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        z: -1
        cursorShape: Qt.PointingHandCursor
        onClicked: root.opened()
    }
}

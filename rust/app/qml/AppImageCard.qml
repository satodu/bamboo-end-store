import QtQuick
import QtQuick.Layouts

// Grid card for one AppImage. `app` is an object from Backend.results.
Item {
    id: root

    property var app: ({})
    property bool busy: false

    signal launchRequested()
    signal integrateRequested()
    signal removeRequested()

    implicitWidth: 280
    implicitHeight: 190

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
                    name: root.app.display_name || ""
                    source: root.app.icon_url || ""
                    size: 48
                }
                ColumnLayout {
                    Layout.fillWidth: true
                    spacing: 2
                    Text {
                        Layout.fillWidth: true
                        text: root.app.display_name || ""
                        color: Theme.fgSurface
                        font.pixelSize: 15
                        font.weight: Font.DemiBold
                        elide: Text.ElideRight
                    }
                    Text {
                        text: root.app.size || ""
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 11
                        font.family: Style.mono
                    }
                }
            }

            RowLayout {
                spacing: 6
                Tag { text: "appimage" }
                Tag { visible: !!root.app.has_desktop; text: Tr.t("Integrated"); tone: "primary" }
            }

            Item { Layout.fillHeight: true }

            RowLayout {
                Layout.fillWidth: true
                spacing: 8
                Btn { Layout.fillWidth: true; text: Tr.t("Launch"); variant: "tonal"; implicitHeight: 32; enabled: !root.busy
                      onClicked: root.launchRequested() }
                Btn { Layout.fillWidth: true; visible: !root.app.has_desktop; text: Tr.t("Integrate"); implicitHeight: 32; enabled: !root.busy
                      onClicked: root.integrateRequested() }
                Btn { text: Tr.t("Remove"); variant: "outline"; implicitHeight: 32; enabled: !root.busy
                      onClicked: root.removeRequested() }
            }
        }
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        acceptedButtons: Qt.NoButton
        z: -1
    }
}

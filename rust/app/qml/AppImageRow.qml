import QtQuick
import QtQuick.Layouts

// List-mode row for one AppImage.
Item {
    id: root

    property var app: ({})
    property bool busy: false

    signal launchRequested()
    signal integrateRequested()
    signal removeRequested()

    implicitHeight: 64
    implicitWidth: 640

    Rectangle {
        anchors.fill: parent
        color: mouse.containsMouse ? Theme.surfaceContainerHigh : "transparent"
        Behavior on color { ColorAnimation { duration: Style.quick } }
    }
    Rectangle {
        anchors.bottom: parent.bottom
        width: parent.width
        height: Style.border
        color: Theme.outlineVariant
        opacity: 0.6
    }
    MouseArea { id: mouse; anchors.fill: parent; hoverEnabled: true; acceptedButtons: Qt.NoButton }

    RowLayout {
        anchors.fill: parent
        anchors.leftMargin: 12
        anchors.rightMargin: 12
        spacing: 14

        PackageIcon { name: root.app.display_name || ""; source: root.app.icon_url || ""; size: 38 }

        Text {
            Layout.fillWidth: true
            text: root.app.display_name || ""
            color: Theme.fgSurface
            font.pixelSize: 14
            font.weight: Font.DemiBold
            elide: Text.ElideRight
        }
        Text {
            text: root.app.size || ""
            color: Theme.fgSurfaceVariant
            font.pixelSize: 11
            font.family: Style.mono
        }
        Tag { text: "appimage" }
        Tag { visible: !!root.app.has_desktop; text: Tr.t("Integrated"); tone: "primary" }
        Btn { text: Tr.t("Launch"); variant: "tonal"; implicitHeight: 32; enabled: !root.busy; onClicked: root.launchRequested() }
        Btn { visible: !root.app.has_desktop; text: Tr.t("Integrate"); implicitHeight: 32; enabled: !root.busy; onClicked: root.integrateRequested() }
        Btn { text: Tr.t("Remove"); variant: "outline"; implicitHeight: 32; enabled: !root.busy; onClicked: root.removeRequested() }
    }
}

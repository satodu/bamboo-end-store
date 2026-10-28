import QtQuick
import QtQuick.Layouts

// List-mode row for one search result.
Item {
    id: root

    property var pkg: ({})
    property bool busy: false
    property bool showInstalledTag: true

    signal opened()
    signal installRequested()
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

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        cursorShape: Qt.PointingHandCursor
        onClicked: root.opened()
    }

    RowLayout {
        anchors.fill: parent
        anchors.leftMargin: 12
        anchors.rightMargin: 12
        spacing: 14

        PackageIcon { name: root.pkg.display_name || root.pkg.name || ""; source: Style.iconFor(root.pkg); size: 36 }

        ColumnLayout {
            Layout.fillWidth: true
            spacing: 2
            Text {
                Layout.fillWidth: true
                text: root.pkg.display_name || root.pkg.name || ""
                color: Theme.fgSurface
                font.pixelSize: 14
                font.weight: Font.DemiBold
                elide: Text.ElideRight
            }
            Text {
                Layout.fillWidth: true
                visible: text.length > 0
                text: Tr.t(root.pkg.description || "")
                color: Theme.fgSurfaceVariant
                font.pixelSize: 12
                elide: Text.ElideRight
            }
        }

        Text {
            text: root.pkg.version || ""
            color: Theme.fgSurfaceVariant
            font.pixelSize: 11
            font.family: Style.mono
            Layout.maximumWidth: 140
            elide: Text.ElideRight
        }
        Tag { text: root.pkg.source === "custom" ? "Bamboo" : (root.pkg.source || ""); tone: root.pkg.source === "aur" ? "tertiary" : root.pkg.source === "custom" ? "primary" : "neutral" }
        Tag { visible: root.showInstalledTag && !!root.pkg.installed; text: Tr.t("Installed"); tone: "primary" }
        Btn {
            text: root.pkg.installed ? Tr.t("Remove") : Tr.t("Install")
            variant: root.pkg.installed ? "outline" : "filled"
            busy: root.busy
            implicitHeight: 32
            onClicked: root.pkg.installed ? root.removeRequested() : root.installRequested()
        }
    }
}

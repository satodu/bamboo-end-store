import QtQuick
import QtQuick.Layouts

// Package details. Enters by diagonal wipe; the technical values type themselves in.
// Create it when a package is opened (Loader) so the entrance plays each time.
Item {
    id: root

    property var pkg: ({})
    property var info: null
    property string status: ""
    property bool busy: false

    signal installRequested()
    signal removeRequested()
    signal closed()

    readonly property var fields: ["Version", "Licenses", "Maintainer", "Build Date", "Installed Size", "Download Size"]

    Reveal {
        anchors.fill: parent

        ChamferPanel {
            anchors.fill: parent
            fill: Theme.surfaceContainerLow
            padding: 24
            details: true

            ColumnLayout {
                anchors.fill: parent
                spacing: 14

                RowLayout {
                    Layout.fillWidth: true
                    Layout.topMargin: 6
                    spacing: 12

                    PackageIcon { name: root.pkg.display_name || root.pkg.name || ""; size: 52 }
                    Text {
                        Layout.fillWidth: true
                        text: root.pkg.display_name || root.pkg.name || ""
                        color: Theme.fgSurface
                        font.family: Style.displayFont
                        font.pixelSize: 28
                        font.weight: Font.ExtraBold
                        font.capitalization: Font.AllUppercase
                        font.letterSpacing: 1.5
                        wrapMode: Text.Wrap
                        maximumLineCount: 2
                        elide: Text.ElideRight
                    }
                    Text {
                        text: "✕"
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 14
                        MouseArea {
                            anchors.fill: parent
                            anchors.margins: -10
                            cursorShape: Qt.PointingHandCursor
                            onClicked: root.closed()
                        }
                    }
                }

                RowLayout {
                    spacing: 6
                    Tag { text: root.pkg.source || ""; tone: root.pkg.source === "aur" ? "tertiary" : "neutral" }
                    Tag { visible: !!root.pkg.installed; text: Tr.t("Installed"); tone: "primary" }
                }

                TypedText {
                    Layout.fillWidth: true
                    full: root.info ? (root.info["Description"] || "") : ""
                    color: Theme.fgSurface
                    font.pixelSize: 13
                    wrapMode: Text.Wrap
                    maximumLineCount: 7
                    elide: Text.ElideRight
                }

                Rectangle { Layout.fillWidth: true; height: 1; color: Theme.outlineVariant }

                Repeater {
                    model: root.fields
                    delegate: RowLayout {
                        id: fieldRow
                        required property string modelData
                        readonly property string value: root.info ? (root.info[modelData] || "") : ""
                        Layout.fillWidth: true
                        visible: value.length > 0
                        spacing: 10

                        Text {
                            text: Tr.t(fieldRow.modelData)
                            color: Theme.fgSurfaceVariant
                            font.family: Style.mono
                            font.pixelSize: 10
                            font.letterSpacing: 1.5
                            font.capitalization: Font.AllUppercase
                            Layout.preferredWidth: 104
                        }
                        TypedText {
                            Layout.fillWidth: true
                            full: fieldRow.value
                            color: Theme.fgSurface
                            font.family: Style.mono
                            font.pixelSize: 12
                            elide: Text.ElideRight
                        }
                    }
                }

                Item { Layout.fillHeight: true }

                RowLayout {
                    Layout.fillWidth: true
                    visible: root.status.length > 0
                    spacing: 8
                    Rune { name: root.status.indexOf("failed") >= 0 ? "error" : "success"; size: 16 }
                    Text {
                        Layout.fillWidth: true
                        text: root.status
                        color: Theme.fgSurface
                        font.pixelSize: 12
                        wrapMode: Text.Wrap
                    }
                }

                Btn {
                    Layout.fillWidth: true
                    text: root.pkg.installed ? Tr.t("Remove") : Tr.t("Install")
                    variant: root.pkg.installed ? "outline" : "filled"
                    busy: root.busy
                    onClicked: root.pkg.installed ? root.removeRequested() : root.installRequested()
                }
            }
        }
    }
}

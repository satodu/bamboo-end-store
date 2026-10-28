import QtQuick
import QtQuick.Controls
import QtQuick.Layouts

// Package details. Enters by diagonal wipe; the technical values type themselves in.
// Create it when a package is opened (Loader) so the entrance plays each time.
Item {
    id: root

    property var pkg: ({})
    property var info: null
    property string status: ""
    property bool busy: false
    /// {loaded, failed, items} from Backend.comments; only used for AUR packages.
    property var comments: ({ loaded: false })
    property string tab: "details"
    property int shownComments: 5
    readonly property bool isAur: root.pkg.source === "aur"

    signal commentsRequested()
    signal installRequested()
    signal removeRequested()
    signal closed()

    onPkgChanged: { tab = "details"; shownComments = 5 }

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

                    PackageIcon {
                        name: root.pkg.display_name || root.pkg.name || ""
                        // the list already knew an icon, or the details view found one on Flathub
                        source: Style.iconFor(root.pkg) || (root.info && root.info.icon_url ? root.info.icon_url : "")
                        size: 52
                    }
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
                    Tag { text: root.pkg.source === "custom" ? "Bamboo" : (root.pkg.source || ""); tone: root.pkg.source === "aur" ? "tertiary" : root.pkg.source === "custom" ? "primary" : "neutral" }
                    Tag { visible: !!root.pkg.installed; text: Tr.t("Installed"); tone: "primary" }
                }

                Segmented {
                    visible: root.isAur
                    options: [{ label: Tr.t("Details"), value: "details" }, { label: Tr.t("Comments"), value: "comments" }]
                    current: root.tab
                    onActivated: v => { root.tab = v; if (v === "comments") root.commentsRequested() }
                }

                TypedText {
                    visible: root.tab === "details"
                    Layout.fillWidth: true
                    full: root.info ? Tr.t(root.info["Description"] || "") : ""
                    color: Theme.fgSurface
                    font.pixelSize: 13
                    wrapMode: Text.Wrap
                    maximumLineCount: 7
                    elide: Text.ElideRight
                }

                Rectangle { visible: root.tab === "details"; Layout.fillWidth: true; height: 1; color: Theme.outlineVariant }

                // Label above value: translated labels are long, values (dates, sizes) are wide.
                Repeater {
                    model: root.fields
                    delegate: ColumnLayout {
                        id: fieldRow
                        required property string modelData
                        readonly property string value: root.info ? (root.info[modelData] || "") : ""
                        Layout.fillWidth: true
                        visible: root.tab === "details" && value.length > 0
                        spacing: 2

                        Text {
                            text: Tr.t(fieldRow.modelData)
                            color: Theme.fgSurfaceVariant
                            font.family: Style.mono
                            font.pixelSize: 10
                            font.letterSpacing: 1.5
                            font.capitalization: Font.AllUppercase
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

                // AUR community comments
                ColumnLayout {
                    Layout.fillWidth: true
                    Layout.fillHeight: true
                    Layout.preferredWidth: 1
                    visible: root.tab === "comments"
                    spacing: 10

                    RowLayout {
                        Layout.fillWidth: true
                        Text {
                            Layout.fillWidth: true
                            text: Tr.t("Community Comments") + (root.comments.items && root.comments.items.length ? "  " + root.comments.items.length : "")
                            color: Theme.fgSurfaceVariant
                            font.family: Style.mono
                            font.pixelSize: 10
                            font.letterSpacing: 1.5
                            font.capitalization: Font.AllUppercase
                            elide: Text.ElideRight
                        }
                        Btn {
                            text: Tr.t("AUR page")
                            variant: "ghost"
                            implicitHeight: 28
                            onClicked: Qt.openUrlExternally("https://aur.archlinux.org/packages/" + root.pkg.name + "#comment-form")
                        }
                    }

                    RowLayout {
                        visible: !root.comments.loaded
                        spacing: 10
                        RuneSpinner { running: parent.visible }
                        Text { text: Tr.t("Loading comments..."); color: Theme.fgSurfaceVariant; font.pixelSize: 12 }
                    }
                    Text {
                        visible: !!root.comments.loaded && !!root.comments.failed
                        Layout.fillWidth: true
                        text: Tr.t("Could not load comments.")
                        color: Theme.error
                        font.pixelSize: 12
                        wrapMode: Text.Wrap
                    }
                    Text {
                        visible: !!root.comments.loaded && !root.comments.failed && !!root.comments.items && root.comments.items.length === 0
                        Layout.fillWidth: true
                        text: Tr.t("No comments yet.")
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 12
                    }

                    ListView {
                        id: commentList
                        Layout.fillWidth: true
                        Layout.fillHeight: true
                        clip: true
                        spacing: 10
                        visible: !!root.comments.loaded && !root.comments.failed
                        model: root.comments.items ? root.comments.items.slice(0, root.shownComments) : []
                        boundsBehavior: Flickable.StopAtBounds
                        ScrollBar.vertical: ScrollBar {}

                        delegate: Item {
                            id: comment
                            required property var modelData
                            width: commentList.width - 8
                            height: box.implicitHeight

                            ColumnLayout {
                                id: box
                                width: parent.width
                                spacing: 4

                                RowLayout {
                                    Layout.fillWidth: true
                                    Rune { name: "text"; size: 14; color: Theme.primary }
                                    Text { text: comment.modelData.author; color: Theme.fgSurface; font.pixelSize: 12; font.weight: Font.DemiBold }
                                    Item { Layout.fillWidth: true }
                                    Text { text: comment.modelData.date; color: Theme.fgSurfaceVariant; font.family: Style.mono; font.pixelSize: 10 }
                                }
                                Text {
                                    Layout.fillWidth: true
                                    text: comment.modelData.content
                                    color: Theme.fgSurface
                                    font.pixelSize: 12
                                    wrapMode: Text.Wrap
                                    lineHeight: 1.25
                                }
                                Rectangle { Layout.fillWidth: true; height: 1; color: Theme.outlineVariant; opacity: 0.6 }
                            }
                        }
                    }

                    Btn {
                        Layout.alignment: Qt.AlignHCenter
                        visible: !!root.comments.items && root.comments.items.length > root.shownComments
                        text: Tr.t("Load more")
                        variant: "outline"
                        onClicked: root.shownComments += 5
                    }
                }

                Item { visible: root.tab === "details"; Layout.fillHeight: true }

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

import QtQuick
import QtQuick.Controls
import QtQuick.Layouts

// Every install goes through here. An AUR package is reviewed first: everything that would come with it,
// each piece in plain words, and the warning signs the AUR reports. Serious ones (an orphan) need the
// user to say they understand before the install can go on. Anything else installs at once.
// `owner` is the page's Backend. The review never claims a package is safe.
Item {
    id: root

    property var owner: null
    property var pkg: null
    property bool loading: false
    /// `bamboo_core::review::Review`, or null while loading.
    property var review: null
    property bool acknowledged: false

    readonly property bool needsAck: review !== null && review.level >= 2
    readonly property string command: pkg && owner ? (JSON.parse(owner.consoleCommands(pkg.name, pkg.source)).install || "") : ""

    anchors.fill: parent
    visible: false
    z: 100

    function ask(p) {
        if (!owner) return
        if (p.source !== "aur") { owner.install(p.name, p.source); return }
        pkg = p
        review = null
        acknowledged = false
        loading = true
        visible = true
        owner.reviewInstall(p.name)
    }
    function close() { visible = false; loading = false }

    function signalText(code) {
        switch (code) {
        case "orphan": return Tr.t("Orphan: nobody maintains it.")
        case "out_of_date": return Tr.t("Flagged as out of date.")
        case "few_votes": return Tr.t("Almost nobody has voted for it.")
        case "new_package": return Tr.t("Created less than a month ago.")
        case "stale": return Tr.t("Not updated for over two years.")
        case "vcs": return Tr.t("Built from the project's latest code, not a fixed release.")
        case "unresolved": return Tr.t("Could not be found in the repositories or the AUR.")
        default: return code
        }
    }
    function toneOf(level) { return level >= 2 ? "error" : level === 1 ? "tertiary" : "neutral" }
    function inkOf(level) { return level >= 2 ? Theme.error : level === 1 ? Theme.tertiary : Theme.fgSurfaceVariant }

    Connections {
        target: root.owner
        function onReviewReady(name, json) {
            if (!root.visible || !root.pkg || name !== root.pkg.name) return
            root.review = JSON.parse(json)
            root.loading = false
        }
    }

    Rectangle {
        anchors.fill: parent
        color: Qt.rgba(0, 0, 0, 0.55)
        MouseArea { anchors.fill: parent; onClicked: root.close() }
    }

    Loader {
        anchors.centerIn: parent
        width: Math.min(root.width - 60, 700)
        height: Math.min(root.height - 60, 660)
        active: root.visible
        sourceComponent: Reveal {
            ChamferPanel {
                anchors.fill: parent
                fill: Theme.surfaceContainerHigh
                stroke: root.needsAck ? Theme.error : Theme.primary
                padding: 26
                details: true

                ColumnLayout {
                    anchors.fill: parent
                    spacing: 12

                    RowLayout {
                        spacing: 12
                        Rune { name: root.needsAck ? "error" : "text"; size: 26; color: root.needsAck ? Theme.error : Theme.primary }
                        Text {
                            Layout.fillWidth: true
                            text: Tr.f("Review %s", root.pkg ? (root.pkg.display_name || root.pkg.name) : "")
                            color: Theme.fgSurface
                            font.family: Style.displayFont
                            font.pixelSize: 26
                            font.weight: Font.ExtraBold
                            font.capitalization: Font.AllUppercase
                            font.letterSpacing: 1.5
                            elide: Text.ElideRight
                        }
                    }

                    // Loading
                    RowLayout {
                        visible: root.loading
                        spacing: 12
                        RuneSpinner { running: root.loading; size: 20 }
                        Text {
                            text: Tr.t("Checking what comes with it...")
                            color: Theme.fgSurfaceVariant
                            font.family: Style.mono
                            font.pixelSize: 11
                            font.letterSpacing: 1.5
                            font.capitalization: Font.AllUppercase
                        }
                    }

                    // Verdict
                    Text {
                        Layout.fillWidth: true
                        visible: root.review !== null
                        text: !root.review ? ""
                            : root.review.level >= 2 ? Tr.t("Serious warning signs found. Read them below.")
                            : root.review.level === 1 ? Tr.t("Some things deserve a closer look.")
                            : Tr.t("No warning signs found. That does not prove it is safe: read the PKGBUILD if you are unsure.")
                        color: root.review ? root.inkOf(root.review.level) : Theme.fgSurface
                        font.pixelSize: 13
                        font.weight: Font.DemiBold
                        wrapMode: Text.Wrap
                    }
                    Text {
                        Layout.fillWidth: true
                        visible: root.review !== null
                        text: !root.review ? "" : (root.review.complete ? "" : Tr.t("The list may be incomplete: the AUR could not be fully checked.") + "  ") +
                            Tr.f("Packages to install: %s", root.review.entries.length)
                        color: Theme.fgSurfaceVariant
                        font.family: Style.mono
                        font.pixelSize: 11
                        font.letterSpacing: 1
                        wrapMode: Text.Wrap
                    }

                    // The list
                    ListView {
                        id: list
                        Layout.fillWidth: true
                        Layout.fillHeight: true
                        visible: root.review !== null
                        clip: true
                        spacing: 10
                        model: root.review ? root.review.entries : []
                        ScrollBar.vertical: ScrollBar {}

                        delegate: Item {
                            id: row
                            required property var modelData
                            width: list.width - 12
                            implicitHeight: body.implicitHeight + 14
                            height: implicitHeight

                            Rectangle {
                                width: Style.fillet
                                height: parent.height
                                color: root.inkOf(row.modelData.level)
                                opacity: row.modelData.level > 0 ? 1 : 0.25
                            }
                            ColumnLayout {
                                id: body
                                x: 14
                                width: parent.width - 14
                                spacing: 4

                                RowLayout {
                                    spacing: 8
                                    Text {
                                        text: row.modelData.name
                                        color: Theme.fgSurface
                                        font.family: Style.mono
                                        font.pixelSize: 13
                                        font.weight: Font.Bold
                                    }
                                    Text {
                                        text: row.modelData.version
                                        color: Theme.fgSurfaceVariant
                                        font.family: Style.mono
                                        font.pixelSize: 10
                                    }
                                    Tag {
                                        text: row.modelData.source === "aur" ? "AUR" : row.modelData.source === "official" ? Tr.t("Official") : "?"
                                        tone: row.modelData.source === "aur" ? "tertiary" : "neutral"
                                    }
                                    Tag { visible: row.modelData.build_only; text: Tr.t("Build only") }
                                    Tag { visible: row.modelData.target; text: Tr.t("This one"); tone: "primary" }
                                }
                                Text {
                                    Layout.fillWidth: true
                                    visible: text.length > 0
                                    text: row.modelData.plain || row.modelData.description || ""
                                    color: row.modelData.plain ? Theme.fgSurface : Theme.fgSurfaceVariant
                                    font.pixelSize: 12
                                    font.italic: !row.modelData.plain
                                    wrapMode: Text.Wrap
                                }
                                Text {
                                    Layout.fillWidth: true
                                    visible: !row.modelData.plain && !!row.modelData.description
                                    text: Tr.t("Not in the glossary: this is the package's own description.")
                                    color: Qt.alpha(Theme.fgSurface, 0.4)
                                    font.family: Style.mono
                                    font.pixelSize: 9
                                    font.letterSpacing: 1
                                    font.capitalization: Font.AllUppercase
                                    wrapMode: Text.Wrap
                                }
                                Repeater {
                                    model: row.modelData.signals
                                    Text {
                                        required property var modelData
                                        Layout.fillWidth: true
                                        text: "ᚾ  " + root.signalText(modelData.code)
                                        color: root.inkOf(modelData.level)
                                        font.pixelSize: 12
                                        wrapMode: Text.Wrap
                                    }
                                }
                            }
                        }
                    }

                    CommandBox {
                        Layout.fillWidth: true
                        label: Tr.t("Command")
                        command: root.command
                    }

                    RowLayout {
                        Layout.fillWidth: true
                        visible: root.needsAck
                        spacing: 12
                        Toggle { checked: root.acknowledged; onToggled: v => root.acknowledged = v }
                        Text {
                            Layout.fillWidth: true
                            text: Tr.t("I understand the warnings above and I want to install anyway.")
                            color: Theme.fgSurface
                            font.pixelSize: 13
                            wrapMode: Text.Wrap
                        }
                    }

                    RowLayout {
                        Layout.fillWidth: true
                        spacing: 10
                        Btn {
                            text: Tr.t("View PKGBUILD")
                            variant: "ghost"
                            enabled: !!root.pkg
                            onClicked: Qt.openUrlExternally("https://aur.archlinux.org/cgit/aur.git/tree/PKGBUILD?h=" + root.pkg.name)
                        }
                        Item { Layout.fillWidth: true }
                        Btn { text: Tr.t("Cancel"); variant: "ghost"; onClicked: root.close() }
                        Btn {
                            text: root.needsAck ? Tr.t("Install anyway") : Tr.t("Install")
                            variant: root.needsAck ? "danger" : "filled"
                            enabled: !root.loading && (!root.needsAck || root.acknowledged)
                            onClicked: { const p = root.pkg; root.close(); root.owner.install(p.name, p.source) }
                        }
                    }
                }
            }
        }
    }
}

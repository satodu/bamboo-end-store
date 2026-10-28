import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Search across pacman, AUR and Flatpak.
Item {
    id: root

    /// Settings object (parsed JSON) from the app's settings backend.
    property var appSettings: ({ search_limit: 50 })
    /// The app's settings Backend, to save the grid/list choice.
    property var settingsBackend
    readonly property string viewMode: appSettings.view_mode === "list" ? "list" : "grid"

    property var packages: JSON.parse(backend.results)
    property var info: JSON.parse(backend.details)
    property var current: null
    property string status: ""
    property int page: 1
    readonly property int pageSize: Math.max(1, appSettings.search_limit)
    readonly property int pageCount: Math.max(1, Math.ceil(backend.total / pageSize))

    function runSearch(q) { field.text = q }
    function research() { root.page = 1; if (field.text.length) backend.search(field.text, 1) }
    // A filter for a repository that was just turned off in Settings would hide everything.
    Connections {
        target: root
        function onAppSettingsChanged() {
            if ((backend.repo === "aur" && !root.appSettings.enable_aur)
                    || (backend.repo === "flatpak" && !root.appSettings.enable_flatpak)) {
                backend.repo = "all"
            }
            root.research()
        }
    }
    // Automation hook (screenshot harness): open the first result.
    function openFirst() {
        if (packages.length === 0) return
        current = packages[0]
        backend.loadDetails(current.name, current.source)
    }
    onPageSizeChanged: if (field.text.length) { page = 1; backend.search(field.text, 1) }

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.status = code !== 0 ? Tr.f("%s failed (exit %s)", name, code)
                : action === "installed" ? Tr.f("%s installed", name) : Tr.f("%s removed", name)
            if (field.text.length) backend.search(field.text, root.page)
        }
    }

    Timer {
        id: debounce
        interval: 350
        onTriggered: { root.page = 1; backend.search(field.text, 1) }
    }

    RowLayout {
        anchors.fill: parent
        anchors.margins: 24
        spacing: 24

        ColumnLayout {
            Layout.fillWidth: true
            Layout.fillHeight: true
            spacing: 16

            RowLayout {
                Layout.fillWidth: true
                spacing: 16

                SearchField {
                    id: field
                    Layout.fillWidth: true
                    placeholder: Tr.t("Search pacman, AUR, Flatpak")
                    onTextChanged: debounce.restart()
                }
                RuneSpinner { running: backend.busy }
                Segmented {
                    options: [{ label: Tr.t("Grid"), value: "grid" }, { label: Tr.t("List"), value: "list" }]
                    current: root.viewMode
                    onActivated: v => root.settingsBackend.setSetting("view_mode", JSON.stringify(v))
                }
                Text {
                    visible: !backend.busy
                    text: Tr.f("%s results", backend.total)
                    font.capitalization: Font.AllUppercase
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 1.5
                }
            }

            FilterBar {
                Layout.fillWidth: true
                repo: backend.repo
                sort: backend.sort
                facets: JSON.parse(backend.facets)
                aur: root.appSettings.enable_aur !== false
                flatpak: !!root.appSettings.enable_flatpak
                onRepoPicked: v => { backend.repo = v; root.research() }
                onSortPicked: v => { backend.sort = v; root.research() }
            }

            Item {
                Layout.fillWidth: true
                Layout.fillHeight: true

                PackageResults {
                    anchors.fill: parent
                    packages: root.packages
                    viewMode: root.viewMode
                    busy: backend.busy
                    onOpened: pkg => { root.current = pkg; backend.loadDetails(pkg.name, pkg.source) }
                    onInstallRequested: pkg => backend.install(pkg.name, pkg.source)
                    onRemoveRequested: pkg => backend.remove(pkg.name, pkg.source)
                }

                Plinth {
                    anchors.centerIn: parent
                    width: Math.min(parent.width - 40, 420)
                    height: Math.min(parent.height - 40, 320)
                    visible: root.packages.length === 0 && !backend.busy
                    caption: field.text.length ? Tr.t("FIG. — NO PACKAGE.") : Tr.t("FIG. — AWAITING QUERY.")
                    actionText: field.text.length && backend.repo !== "all" ? Tr.t("Reset filters") : ""
                    onAction: { backend.repo = "all"; root.research() }
                }
            }

            RowLayout {
                Layout.fillWidth: true
                visible: root.pageCount > 1
                spacing: 12
                Item { Layout.fillWidth: true }
                Btn { text: Tr.t("Prev"); variant: "outline"; enabled: root.page > 1 && !backend.busy
                      onClicked: backend.search(field.text, --root.page) }
                Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.family: Style.mono; font.pixelSize: 12 }
                Btn { text: Tr.t("Next"); variant: "outline"; enabled: root.page < root.pageCount && !backend.busy
                      onClicked: backend.search(field.text, ++root.page) }
            }
        }

        Loader {
            Layout.preferredWidth: active ? 340 : 0
            Layout.fillHeight: true
            active: root.current !== null
            sourceComponent: DetailsPanel {
                pkg: root.current
                info: root.info
                status: root.status
                busy: backend.busy
                onClosed: root.current = null
                onInstallRequested: backend.install(root.current.name, root.current.source)
                onRemoveRequested: backend.remove(root.current.name, root.current.source)
            }
        }
    }
}

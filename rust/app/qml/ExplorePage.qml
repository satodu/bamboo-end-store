import QtQuick
import QtQuick.Controls
import QtQuick.Layouts
import io.bamboo.store

// Explore: the home (official tools and curated categories), one category, or a search across
// pacman, AUR and Flatpak.
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
    /// Name of the opened curated category, or "".
    property string category: ""
    readonly property string mode: field.text.length ? "search" : category.length ? "category" : "home"
    readonly property var categoryInfo: JSON.parse(backend.categories).find(c => c.name === category) || ({})
    readonly property int pageSize: Math.max(1, appSettings.search_limit)
    readonly property int pageCount: Math.max(1, Math.ceil(backend.total / pageSize))

    function runSearch(q) { field.text = q }
    /// Load page `p` of whatever the current mode shows.
    function fetch(p) {
        if (mode === "category") backend.loadCategory(category, p)
        else if (mode === "search") backend.search(field.text, p)
    }
    function research() { root.page = 1; fetch(1) }
    function openCategory(name) {
        field.text = ""
        backend.repo = "all"
        category = name
        current = null
        research()
    }
    function closeCategory() { category = ""; current = null }
    Component.onCompleted: { backend.loadCategories(); backend.loadEssentials() }
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
    onPageSizeChanged: research()

    Backend {
        id: backend
        onOperationFinished: (name, action, code) => {
            root.status = code !== 0 ? Tr.f("%s failed (exit %s)", name, code)
                : action === "installed" ? Tr.f("%s installed", name) : Tr.f("%s removed", name)
            root.fetch(root.page)
            backend.loadEssentials()
        }
    }

    Timer {
        id: debounce
        interval: 350
        onTriggered: { if (field.text.length) { root.category = ""; root.research() } }
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
                    visible: root.mode !== "home"
                    options: [{ label: Tr.t("Grid"), value: "grid" }, { label: Tr.t("List"), value: "list" }]
                    current: root.viewMode
                    onActivated: v => root.settingsBackend.setSetting("view_mode", JSON.stringify(v))
                }
                Text {
                    visible: !backend.busy && root.mode !== "home"
                    text: Tr.f("%s results", backend.total)
                    font.capitalization: Font.AllUppercase
                    color: Theme.fgSurfaceVariant
                    font.family: Style.mono
                    font.pixelSize: 11
                    font.letterSpacing: 1.5
                }
            }

            // Header of an opened category: back button, its rune, name and description.
            RowLayout {
                Layout.fillWidth: true
                visible: root.mode === "category"
                spacing: 16

                Btn { text: Tr.t("Explore Categories"); variant: "outline"; onClicked: root.closeCategory() }
                Rune { glyph: Style.categoryRune(root.categoryInfo.slug || ""); size: 30; color: Theme.primary }
                ColumnLayout {
                    Layout.fillWidth: true
                    spacing: 2
                    Text {
                        text: Tr.t(root.category)
                        color: Theme.fgSurface
                        font.family: Style.displayFont
                        font.pixelSize: 32
                        font.weight: Font.ExtraBold
                        font.capitalization: Font.AllUppercase
                        font.letterSpacing: 1.5
                    }
                    Text {
                        Layout.fillWidth: true
                        text: Tr.t(root.categoryInfo.description_key || "")
                        visible: !!root.categoryInfo.description_key && text !== root.categoryInfo.description_key
                        color: Theme.fgSurfaceVariant
                        font.pixelSize: 12
                        elide: Text.ElideRight
                    }
                }
            }

            FilterBar {
                visible: root.mode !== "home"
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

                HomeView {
                    anchors.fill: parent
                    visible: root.mode === "home"
                    categories: JSON.parse(backend.categories)
                    essentials: JSON.parse(backend.essentials)
                    busy: backend.busy
                    onCategoryOpened: name => root.openCategory(name)
                    onPackageOpened: pkg => { root.current = pkg; backend.loadDetails(pkg.name, pkg.source) }
                    onInstallRequested: pkg => backend.install(pkg.name, pkg.source)
                    onRemoveRequested: pkg => backend.remove(pkg.name, pkg.source)
                }

                PackageResults {
                    anchors.fill: parent
                    visible: root.mode !== "home"
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
                    visible: root.mode !== "home" && root.packages.length === 0 && !backend.busy
                    caption: Tr.t("FIG. — NO PACKAGE.")
                    actionText: backend.repo !== "all" ? Tr.t("Reset filters") : ""
                    onAction: { backend.repo = "all"; root.research() }
                }
            }

            RowLayout {
                Layout.fillWidth: true
                visible: root.mode !== "home" && root.pageCount > 1
                spacing: 12
                Item { Layout.fillWidth: true }
                Btn { text: Tr.t("Prev"); variant: "outline"; enabled: root.page > 1 && !backend.busy
                      onClicked: root.fetch(--root.page) }
                Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.family: Style.mono; font.pixelSize: 12 }
                Btn { text: Tr.t("Next"); variant: "outline"; enabled: root.page < root.pageCount && !backend.busy
                      onClicked: root.fetch(++root.page) }
            }
        }

        Loader {
            Layout.preferredWidth: active ? 340 : 0
            Layout.fillHeight: true
            active: root.current !== null
            sourceComponent: DetailsPanel {
                pkg: root.current
                info: root.info
                comments: JSON.parse(backend.comments)
                onCommentsRequested: backend.loadComments(root.current.name)
                status: root.status
                busy: backend.busy
                onClosed: root.current = null
                onInstallRequested: backend.install(root.current.name, root.current.source)
                onRemoveRequested: backend.remove(root.current.name, root.current.source)
            }
        }
    }
}

import QtQuick

// Repository and sort dropdowns, as in the old app. The repository entries show how many
// results each would give. Wraps onto a second line in a narrow column.
Item {
    id: root

    property string repo: "all"
    property string sort: "relevance"
    /// {all, official, aur, flatpak} counts from the last search; {} while there is no query.
    property var facets: ({})
    property bool aur: true
    property bool flatpak: true

    signal repoPicked(string value)
    signal sortPicked(string value)

    // A Flow reports its wrapped height only after layout, so size from what it really holds.
    implicitHeight: flow.childrenRect.height
    height: implicitHeight

    readonly property var repoOptions: {
        const count = k => facets && facets[k] !== undefined ? "  ·  " + facets[k] : ""
        const o = [
            { label: Tr.t("All Repositories") + count("all"), value: "all" },
            { label: Tr.t("Official") + count("official"), value: "official" }
        ]
        if (aur) o.push({ label: "AUR" + count("aur"), value: "aur" })
        if (flatpak) o.push({ label: Tr.t("Flatpaks") + count("flatpak"), value: "flatpak" })
        return o
    }

    Flow {
        id: flow
        width: root.width
        spacing: 12

        Select {
            width: 340
            caption: Tr.t("Repository")
            options: root.repoOptions
            current: root.repo
            onActivated: v => root.repoPicked(v)
        }
        Select {
            width: 230
            caption: Tr.t("Sort by")
            options: [{ label: Tr.t("Relevance"), value: "relevance" }, { label: Tr.t("Name"), value: "name" }]
            current: root.sort
            onActivated: v => root.sortPicked(v)
        }
    }
}

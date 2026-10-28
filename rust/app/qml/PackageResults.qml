import QtQuick
import QtQuick.Controls

// Search or installed results as a grid of cards or a list of rows (the `view_mode` setting).
// The list is laid out in columns: one row stretched across a wide window pushes its action
// button far from the name it belongs to.
Item {
    id: root

    property var packages: []
    property string viewMode: "grid"
    property bool busy: false
    property bool showInstalledTag: true
    /// A list column is never narrower than this.
    property real minRowWidth: 620

    signal opened(var pkg)
    signal installRequested(var pkg)
    signal removeRequested(var pkg)

    readonly property bool hasItems: packages.length > 0

    GridView {
        id: grid
        anchors.fill: parent
        clip: true
        visible: root.viewMode === "grid" && root.hasItems
        model: root.viewMode === "grid" ? root.packages : []
        readonly property int columns: Math.max(1, Math.floor(width / 300))
        cellWidth: Math.floor(width / columns)
        cellHeight: 200
        boundsBehavior: Flickable.StopAtBounds
        ScrollBar.vertical: ScrollBar {}

        delegate: Item {
            id: cell
            required property var modelData
            required property int index
            width: grid.cellWidth
            height: grid.cellHeight

            Reveal {
                anchors.fill: parent
                anchors.margins: 8
                autoPlay: cell.index < 18
                delay: Math.min(cell.index, 17) * 28

                PackageCard {
                    anchors.fill: parent
                    pkg: cell.modelData
                    busy: root.busy
                    showInstalledTag: root.showInstalledTag
                    onOpened: root.opened(cell.modelData)
                    onInstallRequested: root.installRequested(cell.modelData)
                    onRemoveRequested: root.removeRequested(cell.modelData)
                }
            }
        }
    }

    // List mode: rows in 1, 2 or 3 columns by width, so a wide window is used and each row stays
    // short enough that its action button sits next to the name it belongs to.
    GridView {
        id: list
        anchors.fill: parent
        clip: true
        visible: root.viewMode === "list" && root.hasItems
        model: root.viewMode === "list" ? root.packages : []
        readonly property int columns: Math.max(1, Math.min(3, Math.floor(width / root.minRowWidth)))
        cellWidth: Math.floor(width / columns)
        cellHeight: 64
        boundsBehavior: Flickable.StopAtBounds
        ScrollBar.vertical: ScrollBar {}

        delegate: Item {
            id: rowItem
            required property var modelData
            required property int index
            width: list.cellWidth
            height: list.cellHeight

            Reveal {
                width: rowItem.width - 14
                height: rowItem.height
                autoPlay: rowItem.index < 20
                delay: Math.min(rowItem.index, 19) * 20
                slide: 6

                PackageRow {
                    anchors.fill: parent
                    pkg: rowItem.modelData
                    busy: root.busy
                    showInstalledTag: root.showInstalledTag
                    onOpened: root.opened(rowItem.modelData)
                    onInstallRequested: root.installRequested(rowItem.modelData)
                    onRemoveRequested: root.removeRequested(rowItem.modelData)
                }
            }
        }
    }
}

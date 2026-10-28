import QtQuick
import QtQuick.Controls

// Search or installed results as a grid of cards or a list of rows (the `view_mode` setting).
// Rows are capped in width: on a wide window an uncapped row pushes its action button far from
// the name it belongs to.
Item {
    id: root

    property var packages: []
    property string viewMode: "grid"
    property bool busy: false
    property bool showInstalledTag: true
    /// Widest a list row may get, in pixels.
    property real maxRowWidth: 820

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

    ListView {
        id: list
        anchors.fill: parent
        clip: true
        visible: root.viewMode === "list" && root.hasItems
        model: root.viewMode === "list" ? root.packages : []
        boundsBehavior: Flickable.StopAtBounds
        ScrollBar.vertical: ScrollBar {}

        delegate: Item {
            id: rowItem
            required property var modelData
            required property int index
            width: list.width
            height: 64

            Reveal {
                width: Math.min(rowItem.width, root.maxRowWidth)
                height: rowItem.height
                autoPlay: rowItem.index < 14
                delay: Math.min(rowItem.index, 13) * 24
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

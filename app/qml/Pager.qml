import QtQuick
import QtQuick.Layouts

// Prev / page of N / Next, shown only when there is more than one page. The page owner changes
// its own page number when it receives a signal.
RowLayout {
    id: root

    property int page: 1
    property int pageCount: 1
    property bool busy: false

    signal previousRequested()
    signal nextRequested()

    visible: pageCount > 1
    spacing: 12

    Item { Layout.fillWidth: true }
    Btn { text: Tr.t("Prev"); variant: "outline"; enabled: root.page > 1 && !root.busy; onClicked: root.previousRequested() }
    Text { text: root.page + " / " + root.pageCount; color: Theme.fgSurface; font.family: Style.mono; font.pixelSize: 12 }
    Btn { text: Tr.t("Next"); variant: "outline"; enabled: root.page < root.pageCount && !root.busy; onClicked: root.nextRequested() }
}

import QtQuick
import QtQuick.Layouts

// One line of a status checklist: a rune (Dagaz ok, Nauthiz missing, Laguz while checking), the
// text, and either an action button or "OK".
RowLayout {
    id: root

    property bool known: true
    property bool ok: false
    property string okText: ""
    property string badText: ""
    property string actionText: ""
    property bool actionEnabled: true
    property bool busy: false

    signal action()

    spacing: 12

    Rune {
        name: !root.known ? "loading" : root.ok ? "success" : "error"
        size: 18
        color: !root.known ? Theme.fgSurfaceVariant : root.ok ? Theme.primary : Theme.error
    }
    Text {
        Layout.fillWidth: true
        text: !root.known ? Tr.t("Checking...") : root.ok ? root.okText : root.badText
        color: root.ok ? Theme.fgSurface : Theme.fgSurfaceVariant
        font.pixelSize: 13
        font.weight: Font.DemiBold
        wrapMode: Text.Wrap
    }
    Btn {
        visible: root.known && !root.ok
        text: root.actionText
        implicitHeight: 32
        enabled: root.actionEnabled && !root.busy
        onClicked: root.action()
    }
    Text {
        visible: root.known && root.ok
        text: "OK"
        color: Theme.primary
        font.family: Style.mono
        font.pixelSize: 11
        font.letterSpacing: 2
    }
}

import QtQuick
import QtQuick.Layouts

// Covers a page body while something loads: the rune ring turning slowly, the laguz light bar
// in its middle and a caption. A veil lets the old content show through and blocks clicks.
Item {
    id: root

    property bool active: false
    property string title: ""
    property string caption: Tr.t("Loading Packages...")
    property real ringSize: 170

    anchors.fill: parent
    visible: opacity > 0
    opacity: active ? 1 : 0
    z: 5
    Behavior on opacity {
        NumberAnimation { duration: Style.quick; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease }
    }

    Rectangle { anchors.fill: parent; color: Qt.alpha(Theme.surface, 0.86) }
    MouseArea { anchors.fill: parent; hoverEnabled: true; preventStealing: true }

    ColumnLayout {
        anchors.centerIn: parent
        spacing: 16

        Item {
            Layout.alignment: Qt.AlignHCenter
            implicitWidth: root.ringSize
            implicitHeight: root.ringSize
            RuneRing { anchors.fill: parent; size: root.ringSize }
            RuneSpinner { anchors.centerIn: parent; size: root.ringSize * 0.23; running: root.visible }
        }
        Text {
            Layout.alignment: Qt.AlignHCenter
            visible: root.title.length > 0
            text: root.title
            color: Theme.fgSurface
            font.family: Style.displayFont
            font.pixelSize: 28
            font.capitalization: Font.AllUppercase
        }
        Text {
            Layout.alignment: Qt.AlignHCenter
            text: root.caption
            color: Theme.fgSurfaceVariant
            font.family: Style.mono
            font.pixelSize: 11
            font.letterSpacing: 1.5
            font.capitalization: Font.AllUppercase
        }
    }
}

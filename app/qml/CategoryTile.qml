import QtQuick
import QtQuick.Layouts

// One curated category: its rune (a faded watermark that lights up on hover), the name in the
// display face, the package count and the description.
Item {
    id: root

    /// {name, slug, description_key, packages}
    property var category: ({})

    signal opened()

    implicitHeight: 172

    readonly property string description: Tr.t(category.description_key || "")
    readonly property bool hasDescription: !!category.description_key && description !== category.description_key

    ChamferPanel {
        anchors.fill: parent
        padding: 20
        fill: mouse.containsMouse ? Theme.surfaceContainerHigh : Theme.surfaceContainerLow
        stroke: mouse.containsMouse ? Theme.primary : Theme.outlineVariant
        clip: true

        // watermark
        Text {
            anchors.right: parent.right
            anchors.rightMargin: -6
            anchors.bottom: parent.bottom
            anchors.bottomMargin: -22
            text: Style.categoryRune(root.category.slug || "")
            font.family: Style.runeFont
            font.pixelSize: 130
            color: Qt.alpha(Theme.primary, mouse.containsMouse ? 0.22 : 0.07)
            Behavior on color { ColorAnimation { duration: Style.medium } }
        }

        ColumnLayout {
            anchors.fill: parent
            spacing: 6

            RowLayout {
                spacing: 10
                Rune { glyph: Style.categoryRune(root.category.slug || ""); size: 30; color: Theme.primary; Layout.alignment: Qt.AlignTop }
                Text {
                    Layout.fillWidth: true
                    text: Tr.t(root.category.name || "")
                    color: Theme.fgSurface
                    font.family: Style.displayFont
                    font.pixelSize: 24
                    font.weight: Font.ExtraBold
                    font.capitalization: Font.AllUppercase
                    font.letterSpacing: 1.5
                    wrapMode: Text.Wrap
                    maximumLineCount: 2
                    elide: Text.ElideRight
                }
            }

            // the 2 px fillet under the title grows on hover
            Rectangle {
                height: Style.fillet
                width: mouse.containsMouse ? 44 : 24
                color: mouse.containsMouse ? Theme.primary : Theme.outline
                Behavior on width { NumberAnimation { duration: Style.medium; easing.type: Easing.BezierSpline; easing.bezierCurve: Style.ease } }
            }

            Text {
                Layout.fillWidth: true
                Layout.rightMargin: 70
                visible: root.hasDescription
                text: root.description
                color: Theme.fgSurfaceVariant
                font.pixelSize: 12
                wrapMode: Text.Wrap
                maximumLineCount: 2
                elide: Text.ElideRight
            }

            Item { Layout.fillHeight: true }

            Text {
                text: (root.category.packages ? root.category.packages.length : 0) + "  " + Tr.t("Packages")
                color: mouse.containsMouse ? Theme.primary : Theme.fgSurfaceVariant
                font.family: Style.mono
                font.pixelSize: 11
                font.letterSpacing: 1.5
                font.capitalization: Font.AllUppercase
            }
        }
    }

    MouseArea {
        id: mouse
        anchors.fill: parent
        hoverEnabled: true
        cursorShape: Qt.PointingHandCursor
        onClicked: root.opened()
    }
}

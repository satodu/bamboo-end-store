pragma Singleton
import QtQuick

// Shape and motion language from Galdr (tokens.json, GALDR.md sections 11 and 13).
// Colours never live here; they come from Theme (Matugen).
QtObject {
    id: root

    // Fonts are bundled (app/fonts) so the look does not depend on what the system ships.
    readonly property FontLoader runicLoader: FontLoader { source: Qt.resolvedUrl("../fonts/NotoSansRunic-Regular.ttf") }
    readonly property FontLoader displayLoader: FontLoader { source: Qt.resolvedUrl("../fonts/BigShouldersDisplay.ttf") }
    readonly property string runeFont: runicLoader.name
    readonly property string displayFont: displayLoader.name
    readonly property string mono: "monospace"

    // Geometry
    readonly property real chamfer: 14
    readonly property real chamferSm: 8
    readonly property real chamferXs: 5
    readonly property real border: 1
    readonly property real fillet: 2

    readonly property real gap: 16
    readonly property real gapSm: 8
    readonly property real pad: 20

    // Motion: 140 / 260 / 420 ms on cubic-bezier(0.3, 0, 0, 1). All zero when motion is reduced.
    readonly property bool still: Theme.reduceMotion
    readonly property int quick: still ? 0 : 140
    readonly property int medium: still ? 0 : 260
    readonly property int slow: still ? 0 : 420
    readonly property int slide: 10
    readonly property var ease: [0.3, 0, 0, 1, 1, 1]

    // Real Elder Futhark characters, each with a meaning (GALDR.md section 11).
    readonly property var runes: ({
        "default": "ᛁ",     // Isa, ice: the seal rune
        "explore": "ᚲ",     // Kenaz, torch: search
        "installed": "ᛟ",   // Othala, heritage: the archive
        "appimages": "ᚷ",   // Gebo, gift: collection
        "bamboo": "ᛒ",      // Berkanan, growth
        "settings": "ᛇ",    // Eihwaz, the yew: axis, tuning
        "text": "ᚨ",        // Ansuz, message
        "light": "ᛊ",       // Sowilo, sun
        "active": "ᛏ",      // Tiwaz, guidance
        "loading": "ᛚ",     // Laguz, water: flow
        "success": "ᛞ",     // Dagaz, dawn
        "error": "ᚾ"        // Nauthiz, need
    })
    function rune(name) { return runes[name] || runes["default"] }

    // One rune per curated software category, chosen by its meaning. Real Elder Futhark characters.
    readonly property var categoryRunes: ({
        "browsers": "ᚱ",                        // Raido, the journey
        "image_viewers_and_managers": "ᛊ",      // Sowilo, the sun: light
        "image_processing_and_editing": "ᛃ",    // Jera, the turning year: transformation
        "torrent_clients": "ᚷ",                 // Gebo, the gift: sharing
        "communications": "ᛗ",                  // Mannaz, humanity
        "email": "ᛖ",                           // Ehwaz, the horse: the carrier
        "audio_players": "ᛚ",                   // Laguz, water: flow
        "video_players": "ᚹ",                   // Wunjo, joy
        "development_tools": "ᛜ",               // Ingwaz, the seed: making
        "text_editors": "ᚨ",                    // Ansuz, the message: text
        "office": "ᛞ",                          // Dagaz, the day: work
        "personal_security": "ᛉ",               // Algiz, protection
        "math": "ᚺ",                            // Hagalaz, hail: crystalline order
        "personal_finance": "ᚠ",                // Fehu, wealth
        "pdf_viewers": "ᛟ",                     // Othala, heritage: the archive
        "misc_system_tools": "ᚦ"                // Thurisaz, the thorn: the tool
    })
    // A category the table does not know still gets a real rune, always the same one for it.
    function categoryRune(slug) {
        const known = categoryRunes[slug]
        if (known) return known
        let h = 0
        for (let i = 0; i < slug.length; i++) h = (h * 31 + slug.charCodeAt(i)) % futhark.length
        return futhark.charAt(h)
    }

    // The 24 runes of the Elder Futhark, for the ring.
    readonly property string futhark: "ᚠᚢᚦᚨᚱᚲᚷᚹᚺᚾᛁᛃᛇᛈᛉᛊᛏᛒᛖᛗᛚᛜᛞᛟ"
}

import QtQuick
import io.bamboo.store

// Headless check: `BAMBOO_QML=app/qml/SelfTest.qml QT_QPA_PLATFORM=offscreen cargo run -p bamboo-end-store`
// Run with XDG_CONFIG_HOME pointing at a scratch dir: it writes settings.
Item {
    id: root
    property int step: 0

    Backend {
        id: backend
        onResultsChanged: {
            const r = JSON.parse(results)
            console.log("results:", r.length, "of", backend.total, r.slice(0, 3).map(p => p.source + "/" + p.name).join(", "))
            if (r.length > 0) backend.loadDetails(r[0].name, r[0].source)
        }
        onDetailsChanged: {
            const d = JSON.parse(details)
            console.log("details:", d ? d["Name"] + " " + d["Version"] : "null")
            console.log("setSetting(search_limit,\"x\"):", backend.setSetting("search_limit", '"x"'))
        }
    }

    function show(label) {
        console.log(label, "source=" + Theme.source, "dark=" + Theme.dark,
                    "primary=" + Theme.primary, "surface=" + Theme.surface, "fgSurface=" + Theme.fgSurface)
    }

    Timer { interval: 100; running: true; onTriggered: { root.show("initial:"); backend.search("firefox", 1) } }

    // Each step changes a theme setting, reloads, and prints after the worker thread answered.
    Timer {
        id: seq
        interval: 1500; running: true; repeat: true
        onTriggered: {
            root.step++
            if (root.step === 1) { backend.setSetting("theme_source", '"preset"'); backend.setSetting("theme", '"theme-nord"'); Theme.reload() }
            else if (root.step === 2) { root.show("nord/dark:"); backend.setSetting("theme_mode", '"light"'); Theme.reload() }
            else if (root.step === 3) { root.show("nord/light:"); backend.setSetting("theme_source", '"seed"'); backend.setSetting("theme_seed", '"#ff5500"'); Theme.reload() }
            else if (root.step === 4) { root.show("seed #ff5500/light:"); backend.setSetting("theme_source", '"system"'); Theme.reload() }
            else if (root.step === 5) { root.show("system:"); Qt.quit() }
        }
    }
    Timer { interval: 30000; running: true; onTriggered: { console.log("TIMEOUT"); Qt.quit() } }
}

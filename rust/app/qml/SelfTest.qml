import QtQuick
import io.bamboo.store

// Headless check: `BAMBOO_QML=qml/SelfTest.qml QT_QPA_PLATFORM=offscreen cargo run -p bamboo-end-store`
Item {
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
            console.log("settings:", backend.settingsJson())
            console.log("setSetting(view_mode,list):", backend.setSetting("view_mode", '"list"'))
            console.log("setSetting(search_limit,\"x\"):", backend.setSetting("search_limit", '"x"'))
            Qt.quit()
        }
    }
    Timer { interval: 100; running: true; onTriggered: backend.search("firefox", 1) }
    Timer { interval: 25000; running: true; onTriggered: { console.log("TIMEOUT"); Qt.quit() } }
}

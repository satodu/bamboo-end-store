pragma Singleton
import QtQuick
import io.bamboo.store

// Translations. English phrases are the keys (same as the PHP app's lang/*.json); a missing
// key shows its English text. Bindings that call t() or f() re-evaluate when the language
// changes, because they read `table`.
QtObject {
    id: root

    readonly property I18nBridge bridge: I18nBridge {}
    readonly property var table: JSON.parse(bridge.table)
    /// "pt", "en", "es" or "ru"
    readonly property string lang: bridge.lang

    function t(key) {
        const v = table[key]
        return v !== undefined ? v : key
    }

    /// Like t(), replacing each %s with the next argument.
    function f(key, ...args) {
        let i = 0
        return t(key).replace(/%s/g, () => i < args.length ? args[i++] : "")
    }

    function reload() { bridge.reload() }
}

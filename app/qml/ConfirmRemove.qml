import QtQuick

// Asks before an install is removed, and shows the exact command it stands for.
// `owner` is the page's Backend: it removes the package and knows the console command.
Confirm {
    id: root

    property var owner: null
    property var pkg: null

    acceptText: Tr.t("Remove")

    function ask(p) {
        pkg = p
        command = JSON.parse(owner.consoleCommands(p.name, p.source)).remove || ""
        open(Tr.f("Remove %s?", p.display_name || p.name),
             Tr.t("It will be uninstalled from this system. The command is below."))
    }
    onAccepted: if (pkg) owner.remove(pkg.name, pkg.source)
}

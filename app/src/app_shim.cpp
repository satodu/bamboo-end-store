// Qt exposes quitOnLastWindowClosed neither to QML nor through cxx-qt-lib, and the tray needs it off:
// hiding the only window to the tray must not end the program. Quitting is done explicitly from QML.
#include <QGuiApplication>

extern "C" void bamboo_set_quit_on_last_window_closed(bool quit) {
    QGuiApplication::setQuitOnLastWindowClosed(quit);
}

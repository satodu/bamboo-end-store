// The application object lives here, not in Rust, for two reasons:
// - It must be a QApplication (Qt Widgets), not a QGuiApplication. Under KDE the platform theme (plasma-integration)
//   builds the tray icon, menus and file dialogs from QWidgets, which abort with "Cannot create a QWidget without
//   QApplication" otherwise.
// - The Qt Quick Controls style is forced to Basic: the whole UI is custom, and a user environment that names another
//   style (QT_STYLE_OVERRIDE=kvantum on KDE or Hyprland setups) makes QML fail with `module "kvantum" is not installed`.
// - quitOnLastWindowClosed must be off (the tray keeps the program alive with no window) and cxx-qt-lib does not
//   expose it. Quitting is done explicitly from QML.
#include <QApplication>
#include <QQmlApplicationEngine>
#include <QQuickStyle>
#include <QString>
#include <QUrl>

extern "C" int bamboo_run(int argc, char **argv, const char *url) {
    QApplication app(argc, argv);
    QQuickStyle::setStyle(QStringLiteral("Basic"));
    QApplication::setQuitOnLastWindowClosed(false);
    QQmlApplicationEngine engine;
    engine.load(QUrl(QString::fromUtf8(url)));
    return app.exec();
}

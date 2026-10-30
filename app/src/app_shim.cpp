// The application object lives here, not in Rust, for three reasons:
// - It must be a QApplication (Qt Widgets), not a QGuiApplication. Under KDE the platform theme (plasma-integration)
//   builds the tray icon, menus and file dialogs from QWidgets, which abort with "Cannot create a QWidget without
//   QApplication" otherwise.
// - The Qt Quick Controls style is forced to Basic: the whole UI is custom, and a user environment that names another
//   style (QT_STYLE_OVERRIDE=kvantum on KDE or Hyprland setups) makes QML fail with `module "kvantum" is not installed`.
// - quitOnLastWindowClosed must be off (the tray keeps the program alive with no window) and cxx-qt-lib does not
//   expose it. Quitting is done explicitly from QML.
//
// Single instance: a second launch finds the first one through a local socket, asks it to show its window
// (`bringToFront` in Main.qml) and exits, so there is one window and one tray icon. The socket name carries a hash of
// the config directory, so a run with its own XDG_CONFIG_HOME (tests, screenshots) never reaches the real app.
#include <QApplication>
#include <QByteArray>
#include <QLocalServer>
#include <QLocalSocket>
#include <QMetaObject>
#include <QQmlApplicationEngine>
#include <QQuickStyle>
#include <QStandardPaths>
#include <QString>
#include <QUrl>

static QString instanceName() {
    const QString dir = QStandardPaths::writableLocation(QStandardPaths::ConfigLocation);
    return QStringLiteral("bamboo-end-store-%1").arg(qHash(dir), 0, 16);
}

extern "C" int bamboo_run(int argc, char **argv, const char *url) {
    QApplication app(argc, argv);
    QQuickStyle::setStyle(QStringLiteral("Basic"));
    QApplication::setQuitOnLastWindowClosed(false);

    // BAMBOO_QML (a dev file such as the gallery) is not the app: it never takes part in single-instance.
    QLocalServer server;
    if (QByteArray(url).startsWith("qrc:")) {
        const QString name = instanceName();
        QLocalSocket probe;
        probe.connectToServer(name);
        if (probe.waitForConnected(300)) {
            probe.write("show\n");
            probe.waitForBytesWritten(300);
            return 0;
        }
        QLocalServer::removeServer(name); // a crashed run leaves the socket file behind
        server.listen(name);
    }

    QQmlApplicationEngine engine;
    QObject::connect(&server, &QLocalServer::newConnection, &app, [&]() {
        while (QLocalSocket *client = server.nextPendingConnection()) {
            client->deleteLater();
        }
        const auto roots = engine.rootObjects();
        for (QObject *root : roots) {
            QMetaObject::invokeMethod(root, "bringToFront");
        }
    });
    engine.load(QUrl(QString::fromUtf8(url)));
    return app.exec();
}

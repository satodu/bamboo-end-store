# Technical debt

Known shortcuts and open work in 2.0.0, so they are decisions and not surprises. Ordered by how much
each one would matter to a user or a maintainer. Nothing here blocks the release.

## Release and packaging

- **No 2.x package on the AUR yet.** `packaging/aur/PKGBUILD` builds 2.x from source (package `bamboo-end-store`, new AUR repo needed; it
  conflicts with `bamboo-end-store-bin`, which still ships 1.x). It was built with `makepkg` from a local tarball only. A prebuilt
  `-bin` package, an AppImage and a `.deb` do not exist.
- **Tested by hand on one machine** (CachyOS) and, next, a fresh EndeavourOS install. There is no CI: tests run locally with
  `cargo test -p bamboo-core`.
- **The QML layer has no automated tests.** The Rust core has 123 tests; the interface is checked with screenshots and by reading
  Qt warnings (`QT_FORCE_STDERR_LOGGING=1`). The screenshot harness used for the README is not in the repository.

## Performance and use

- **No cache on disk.** Searches, installed packages and details are cached in memory for the session only. A cold start repeats
  `pacman -Q`, the AUR and Flathub requests. A small on-disk cache (last installed list, categories) would make the first screen instant.
- **`JSON.parse` in bindings.** About 29 QML bindings parse the JSON strings from the bridge again on every re-evaluation
  (`JSON.parse(backend.categories)` is used in two places in `ExplorePage`). Parse once when the property changes.
- **`installed_page` clones the whole installed list** (around 1,500 packages) on every filter keystroke. Share it behind an `Arc`.
- **Not measured.** The three points above come from reading the code. Time the Installed screen, a search and memory before and after.

## Structure

- **`Backend` is a god object**: 12 properties and about 30 invokables (search, installed, AppImages, Flatpak, settings, operations, details,
  comments). Split into `SearchBackend`, `OperationsBackend`, `AppImagesBackend` and `SettingsBackend`. It touches every QML screen.
- **Singletons (`OnceLock`) for `Store` and `AppImages`.** Fine for the app, but they make the bridge harder to test; inject them instead.
- **`UserStateMachine` is a busy flag with a 30 minute expiry.** The State pattern is more than it needs; it was ported from the PHP app.
- **QML duplication.** Explore, Installed and Bamboo Installs repeat the toolbar (spinner, grid/list switch, result count) and the empty
  state. `Pager` and `LoadingBody` are already shared; a `ResultsToolbar` is next.
- **`SettingsPage.qml` has 524 lines.** It is assembled from sections, but each section could be its own file.
- **Large Rust files.** `store.rs` (about 680 lines), `appimage.rs` (about 620), `flatpak.rs` (about 430) mix logic and tests.

## Features that are partial

- **Icons.** Installed pacman and AUR apps whose icon lives in a theme other than `hicolor` show the name's initial as a rune.
  A lookup through the user's icon theme (`index.theme` inheritance) would find them.
- **Flatpak icon table.** 98 Flathub ids copied from the PHP app (`core/src/icons/flathub_ids.txt`); other Flatpaks depend on the Flathub search.
- **Installed screenshot.** `docs/imgs/app-installed.png` predates the rune stand-in icons.
- **Screenshots and the website do not show the AppImages screen.**
- **No update check for the app itself**, and no "what changed" screen.
- **Notifications and tray** only checked on Hyprland (Wayland) with the system tray host of that desktop. KDE, GNOME and X11 are not verified.
- **Terminal detection** tries `konsole`, `alacritty`, `kitty`, `foot`, `gnome-terminal`, `xterm` in that order and has no setting to choose one.
  `xdg-terminal-exec` is not used.

## Safety notes (not debt, kept visible)

- Install, remove, Update System and the Flatpak setup run `sudo` or `pkexec` in an external terminal. Never trigger them from tests or automation.
- The Bamboo Installs scripts change `/etc/pacman.conf` and build from the AUR; they run only on an explicit click.
- Registering an AppImage executes it (`--appimage-extract`). Only register files you trust.

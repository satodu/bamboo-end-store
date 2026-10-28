# AGENTS.md

Rules for anyone (human or agent) changing this repository. They record decisions already made;
do not re-litigate them without asking the maintainer.

## What this repo is

Bamboo End Store, a store for EndeavourOS / Arch (pacman, AUR, Flatpak, AppImage).

- `app/`, `resources/`, `routes/`, ... : the **legacy PHP app** (Laravel, Livewire, NativePHP). Frozen:
  only touch it to keep the old build working until the Rust app reaches parity.
- `rust/core/` : the **Rust core** (no Qt): package managers, store, settings, cache, terminal,
  AppImages, themes, i18n.
- `rust/app/` : the **Qt/QML app** (cxx-qt): QObject bridges in `src/`, QML in `qml/`, bundled fonts in `fonts/`.
- Work happens on the `rust-qml` branch.

## Commits

- Conventional Commits (`feat(rust): ...`, `fix(...)`, `docs: ...`), imperative, with a short body for the why.
- **No Claude or Anthropic attribution anywhere**: no `Co-Authored-By:` trailer, no "Generated with"
  line, in commits or PR descriptions. The author is the maintainer only.
- Run `cd rust && cargo test -p bamboo-core` before committing. All tests must pass.
- Never push, and never rewrite history that was pushed, without asking. Rewriting local, unpushed commits is fine.
- Do not commit build output (`rust/target` is ignored). Keep `.gitignore` lines separate.

## Visual language (Galdr)

The look comes from `../art-concept/galdr` (read `GALDR.md`, `docs/03`, `docs/04`, `docs/08`). Colours come from
the palette, the shapes and motion come from Galdr.

- **Shape**: panels, buttons, tags and fields have two 45 degree chamfered corners (top-right and bottom-left),
  a 1 px hairline, a 2 px "fillet" for active/focus rules. Never rounded corners, never drop shadows. Use
  `ChamferPanel`, `Btn`, `Tag`, `Field`, `Select`, `Toggle`, `Segmented`; do not draw ad-hoc rectangles for these.
  A fill inside a chamfered container (an active segment, a highlighted dropdown row) must use `SegmentFill`, cut at the
  container's chamfered corners; a plain `Rectangle` pokes out over the corner and the outline.
- **Colour**: only through the `Theme` singleton (`Theme.primary`, `Theme.surfaceContainer`, ...). Never hard-code a
  colour in QML. The palette is Material You: from the desktop file `~/.cache/ryoku/colors.json`, a preset,
  or a custom seed, with a bundled fallback. Depth comes from surface tones and line work, not shadows.
- **Runes**: real Elder Futhark characters only, each with a meaning (`Style.runes`, `Rune.qml`). Software categories
  use runes too (`Style.categoryRune`); a category the table does not know gets a stable rune from a hash, never an image. Never random
  glyphs, never emoji. One rune ruler per screen. The Noto Sans Runic font is bundled.
- **Type**: display titles use Big Shoulders Display (bundled), uppercase, with the full stop in the primary
  colour. Data and captions use the mono font in caps with letter spacing. Body text uses the system sans.
- **Motion** (`Reveal`, `TypedText`, `PanelDetails`, `RuneRing`, `RuneField`):
  - every entrance is a diagonal chamfer wipe plus a short slide, never a plain fade;
  - curve `cubic-bezier(0.3, 0, 0, 1)` (`Style.ease`), durations 140 / 260 / 420 ms (`Style.quick/medium/slow`);
  - lines draw themselves, technical text types in, runes drift or turn very slowly;
  - no bounce, no glitch, no pulsing neon;
  - respect reduced motion: use `Style.still` / the `reduce_motion` setting for anything that animates.
- The sidebar shows the machine (`SystemInfoBlock`: host, OS, kernel, app version). On the very first launch (`intro_seen` unset) its
  values start as runes and settle into text (`RuneDecode`), and the four runes beside the lines carry a slow looping wave of
  light (only a change of tone). Both stop under reduced motion. No loose rune ruler in the sidebar: it looked out of place.
- Empty states are a dashed plinth with the rune ring and a mono caption (`Plinth`, `FIG. — NO SHARD.`).

## QML rules

- **Never name a property `on<Capital>...`** (for example `onSurface`): QML reads it as a signal handler and returns
  black with no error. Material `on*` roles are exposed as `fg*` (`Theme.fgSurface`).
- **All user-visible text goes through `Tr.t("English phrase")` or `Tr.f("... %s ...", arg)`.** The English phrase is
  the key. Add every new key to all four files in `rust/core/src/i18n/` (`en`, `pt`, `es`, `ru`). Proper nouns and
  language names are not translated. A test fails if a key used in QML is missing or if the languages diverge.
- Do not mutate a JS object or array and expect bindings to update: assign a new one.
- Do not give a property the same name as the `id` it is bound to (`settingsBackend: settingsBackend` resolves to itself).
- `ChamferPanel` has no implicit size on purpose. Give panels a size, anchors or a Layout. A `Flow` inside a Layout
  needs an explicit height from `childrenRect` (see `FilterBar`).
- Lists and details cross the Rust bridge as JSON strings. Each screen owns its own `Backend` instance
  (own results and busy state); the `Store` underneath is shared, and so are its caches.
- Register every new QML file in `rust/app/build.rs`.
- **Layouts must adapt to the window, up to full screen.** No fixed page widths. Grids add columns as the window grows
  (`PackageResults`, `AppImagesPage`). List mode flows into 1, 2 or 3 columns of rows (`PackageResults`, `AppImagesPage`) so an action button stays next to the name it belongs to instead of sitting at the far edge of a wide window. Settings flows into 1, 2 or 3 columns (`SettingsPage.columnPlan`); wide controls use
  `SettingRow { stacked: true }`. Check new screens at 1280, 1400 and 1920 px wide.

## Rust rules

- `rust/core` must not depend on Qt. Parsers are pure functions tested against real captured output in
  `rust/core/tests/fixtures/`.
- **Tests never touch the user's real data.** Directories are injected (`AppImageDirs`), tests use temp dirs and a fake
  AppImage, and any run that writes settings sets `XDG_CONFIG_HOME` (and `XDG_DATA_HOME`) to a scratch directory.
- Settings keep the PHP app's key names and defaults so an old `settings.json` loads unchanged. Unknown keys are kept.
- Matugen: the app generates palettes in-process (`material-colors`) and never runs the `matugen` binary. If that ever
  changes, it must use `--dry-run` and an empty config: Matugen with the user's config re-themes the whole desktop.
- The Bamboo Installs scripts (`rust/core/src/installers/*.sh`) change `/etc/pacman.conf`, use `pkexec`, build from the AUR and
  download binaries. They are ported verbatim from the PHP app, embedded in the binary, and run only on an explicit click in a
  visible terminal. Unknown names never become commands (`CustomManager`).
- Installing and removing packages, and the Flatpak setup buttons in Settings (`installFlatpak`, `addFlathub`), open an
  external terminal and run `sudo`. Never trigger that from tests or automation; reading the status (`checkFlatpak`) is safe. Registering an AppImage executes it (`--appimage-extract`); only do that with files you trust.
- Filters follow the old app: repository (`all`, `official` = pacman, `aur`, `flatpak`) and sort (`relevance`, `name`)
  apply to the whole result before pagination; `search_limit` is the page size. Counts per repository come from the
  unfiltered result.

## Checking your work

```bash
cd rust
cargo test -p bamboo-core                      # unit tests, fixtures, i18n key checks
cargo run -p bamboo-end-store                  # the app
cargo run -p bamboo-core --example cli -- aur search yay   # core without the UI
BAMBOO_QML=app/qml/Gallery.qml cargo run -p bamboo-end-store   # component catalogue
BAMBOO_REDUCE_MOTION=1 cargo run -p bamboo-end-store           # motion off for one run
```

Headless: `QT_QPA_PLATFORM=offscreen QT_FORCE_STDERR_LOGGING=1 BAMBOO_QML=<file.qml>`; extra arguments after the
binary reach `Qt.application.arguments` (the screenshot scripts use this with `grabToImage`).

- Offscreen uses Qt's **software renderer**: `MultiEffect`/`Reveal` masks are not drawn and clipping of some Shapes
  differs. Check visual changes on a real display (`QT_QPA_PLATFORM=wayland`), not only offscreen.
- Without `QT_FORCE_STDERR_LOGGING=1`, Qt logs go to journald and QML errors seem to vanish.
- Read QML warnings in the output: a binding loop, `X is not defined` or `Cannot read property` is a bug.

## Not done yet

system notifications, brand icons for installed pacman and AUR packages (Flatpak and Bamboo Installs have them). See the git log for what is already ported.

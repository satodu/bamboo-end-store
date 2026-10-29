#!/usr/bin/env bash
# Run Bamboo End Store from source.
#
#   ./run.sh              debug build, your real settings
#   ./run.sh --release    optimized build
#   ./run.sh --clean      throwaway settings (nothing in ~/.config is read or written)
#   ./run.sh --still      motion off
#   ./run.sh --gallery    component catalogue
#   ./run.sh --test       cargo test -p bamboo-core, no app
#
# Flags combine: ./run.sh --release --clean
# Installing or removing a package from the app still opens a terminal and runs sudo.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

profile=()
scratch=""

for arg in "$@"; do
    case "$arg" in
        --release) profile=(--release) ;;
        --clean)
            scratch="$(mktemp -d)"
            export XDG_CONFIG_HOME="$scratch/config" XDG_DATA_HOME="$scratch/data"
            trap 'rm -rf "$scratch"' EXIT
            ;;
        --still) export BAMBOO_REDUCE_MOTION=1 ;;
        --gallery) export BAMBOO_QML=app/qml/Gallery.qml ;;
        --test) exec cargo test -p bamboo-core ;;
        -h|--help) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "unknown flag: $arg (try --help)" >&2; exit 2 ;;
    esac
done

# Qt logs go to journald unless this is set, hiding QML errors.
export QT_FORCE_STDERR_LOGGING=1

cargo run "${profile[@]}" -p bamboo-end-store

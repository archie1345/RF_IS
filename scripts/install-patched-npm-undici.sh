#!/bin/sh
set -eu

patched_version=6.29.1
global_modules="$(npm root -g)"
npm_package="$global_modules/npm"
target="$npm_package/node_modules/undici"

if [ ! -f "$target/package.json" ]; then
    printf '%s\n' "npm's bundled undici package was not found at $target" >&2
    exit 1
fi

installed_version="$(node -p "require('$target/package.json').version")"
if node -e '
const [major, minor, patch] = process.argv[1].split(".").map(Number);
process.exit(major > 6 || (major === 6 && (minor > 29 || (minor === 29 && patch >= 1))) ? 0 : 1);
' "$installed_version"; then
    printf 'npm bundled undici %s is at or above the patched release.\n' "$installed_version"
    exit 0
fi

if [ "$installed_version" != "6.29.0" ]; then
    printf 'Refusing to replace unexpected npm bundled undici version %s.\n' "$installed_version" >&2
    exit 1
fi

temporary_dir="$(mktemp -d)"
backup="$temporary_dir/undici-original"

restore_on_failure() {
    result=$?
    trap - EXIT
    if [ "$result" -ne 0 ] && [ -d "$backup" ]; then
        if [ -e "$target" ]; then
            mv "$target" "$temporary_dir/undici-incomplete"
        fi
        mv "$backup" "$target"
    fi
    rm -rf "$temporary_dir"
    exit "$result"
}
trap restore_on_failure EXIT

npm pack "undici@$patched_version" --pack-destination "$temporary_dir" --silent >/dev/null
mv "$target" "$backup"
mkdir -p "$target"
tar -xzf "$temporary_dir/undici-$patched_version.tgz" --strip-components=1 -C "$target"

verified_version="$(node -p "require('$target/package.json').version")"
if [ "$verified_version" != "$patched_version" ]; then
    printf 'Expected undici %s after patch, found %s.\n' "$patched_version" "$verified_version" >&2
    exit 1
fi

printf 'Replaced npm bundled undici %s with upstream undici %s.\n' "$installed_version" "$verified_version"

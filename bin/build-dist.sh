#!/usr/bin/env bash
#
# Build the distributable plugin tree (and optionally a zip) from .distignore.
#
# This is what actually ships to WordPress.org, so every check that matters --
# Plugin Check especially -- should run against THIS output rather than the
# repo. The repo contains dev files that never reach users; checking it instead
# produces both false alarms and false confidence.
#
# Equivalent to `wp dist-archive`, without requiring that wp-cli package.
#
# Usage:
#   bin/build-dist.sh              # build tree into build/<slug>/
#   bin/build-dist.sh --zip        # also produce build/<slug>-<version>.zip
#
# Outputs the built tree at  build/<slug>/
# Prints the resolved version to stdout as the last line.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="$(basename "$ROOT")"
BUILD_ROOT="$ROOT/build"
DEST="$BUILD_ROOT/$SLUG"

MAKE_ZIP=0
for arg in "$@"; do
	case "$arg" in
		--zip) MAKE_ZIP=1 ;;
		*) echo "Unknown argument: $arg" >&2; exit 1 ;;
	esac
done

if [[ ! -f "$ROOT/.distignore" ]]; then
	echo "Error: no .distignore at repo root — refusing to guess what to ship." >&2
	exit 1
fi

# The plugin header is the single source of truth for the version
# (bin/validate-config.php check 8 enforces that everything else agrees).
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$ROOT/$SLUG.php" | head -1)"
if [[ -z "$VERSION" ]]; then
	echo "Error: could not read the Version header from $SLUG.php." >&2
	exit 1
fi

# Translate .distignore into rsync excludes, skipping comments and blank lines.
EXCLUDES=()
while IFS= read -r line || [[ -n "$line" ]]; do
	line="${line%"${line##*[![:space:]]}"}"   # strip trailing whitespace
	[[ -z "$line" || "$line" == \#* ]] && continue
	EXCLUDES+=(--exclude="$line")
done < "$ROOT/.distignore"

rm -rf "$BUILD_ROOT"
mkdir -p "$DEST"
rsync -a "${EXCLUDES[@]}" "$ROOT/" "$DEST/"

# A dev file reaching the zip is a release bug, so fail loudly rather than
# trusting that .distignore stayed correct.
LEAKED=()
for f in .git .github bin vendor node_modules composer.json package.json \
         phpcs.xml.dist .wp-env.json .eslintrc.json .stylelintrc.json migration-scripts \
         .wordpress-org assets-src build; do
	[[ -e "$DEST/$f" ]] && LEAKED+=("$f")
done
if (( ${#LEAKED[@]} )); then
	echo "Error: development files leaked into the build: ${LEAKED[*]}" >&2
	exit 1
fi

# Likewise, a missing runtime file would ship a broken plugin.
MISSING=()
for f in "$SLUG.php" readme.txt uninstall.php LICENSE config includes blocks; do
	[[ -e "$DEST/$f" ]] || MISSING+=("$f")
done
if (( ${#MISSING[@]} )); then
	echo "Error: required files missing from the build: ${MISSING[*]}" >&2
	exit 1
fi

echo "Built $DEST ($(find "$DEST" -type f | wc -l | tr -d ' ') files)"

if (( MAKE_ZIP )); then
	ZIP="$BUILD_ROOT/$SLUG-$VERSION.zip"
	# Zip from BUILD_ROOT so the archive contains a single <slug>/ top-level
	# directory, which is what WordPress expects when installing from a zip.
	( cd "$BUILD_ROOT" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )
	echo "Packaged $ZIP"
fi

echo "$VERSION"

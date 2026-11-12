#!/usr/bin/env bash
# Packages the plugin into build/bb-a11y-audit/, ready to drop into wp-content/plugins/.
#
# There's no CSS/JS compilation step here (deliberately no bundler, see README) --
# this script only copies runtime plugin files and strips dev-only ones.
#
# Usage:
#   scripts/build.sh          # copy into build/bb-a11y-audit/
#   scripts/build.sh --zip    # same, plus an unversioned build/bb-a11y-audit.zip for quick testing

set -euo pipefail

PLUGIN_SLUG="bb-a11y-audit"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/build/$PLUGIN_SLUG"

rm -rf "$ROOT_DIR/build"
mkdir -p "$BUILD_DIR"

rsync -a "$ROOT_DIR/" "$BUILD_DIR/" \
	--exclude 'node_modules' \
	--exclude 'build' \
	--exclude 'dist' \
	--exclude 'scripts' \
	--exclude 'package.json' \
	--exclude 'package-lock.json' \
	--exclude 'README.md' \
	--exclude '.git' \
	--exclude '.gitignore' \
	--exclude '.DS_Store'

echo "Built plugin into $BUILD_DIR"

if [[ "${1:-}" == "--zip" ]]; then
	( cd "$ROOT_DIR/build" && zip -r -q "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG" )
	echo "Created $ROOT_DIR/build/$PLUGIN_SLUG.zip"
fi

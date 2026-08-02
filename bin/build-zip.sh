#!/usr/bin/env bash
#
# Builds a clean, upload-ready theme ZIP for WordPress's
# "Appearance -> Themes -> Add New -> Upload Theme" dialog.
#
# Uses `git archive`, so only committed, tracked files are included, and
# whatever is marked `export-ignore` in .gitattributes (dev tooling,
# internal docs) is left out automatically. Uncommitted changes are NOT
# included – commit first.
#
# Usage: bin/build-zip.sh [output-dir]  (default: dist)

set -euo pipefail

THEME_SLUG="kanzlei-theme"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-"${REPO_ROOT}/dist"}"
OUT_FILE="${OUT_DIR}/${THEME_SLUG}.zip"

if [ -n "$(cd "${REPO_ROOT}" && git status --porcelain)" ]; then
	echo "Warning: there are uncommitted changes. They will NOT be included in the zip." >&2
fi

mkdir -p "${OUT_DIR}"
rm -f "${OUT_FILE}"

git -C "${REPO_ROOT}" archive --format=zip --prefix="${THEME_SLUG}/" --output="${OUT_FILE}" HEAD

echo "Built: ${OUT_FILE}"

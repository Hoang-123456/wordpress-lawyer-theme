#!/usr/bin/env bash
#
# Builds a clean, upload-ready theme ZIP for WordPress's
# "Appearance -> Themes -> Add New -> Upload Theme" dialog.
#
# The base repo keeps BOTH contact approaches (variant A: custom form,
# variant B: FluentBooking) as the shared source of truth – see README
# "Note for follow-up projects". A client only ever gets one; pass which
# one to strip the other out of the build. Without an argument, both stay
# in (useful for testing/archiving the base repo itself).
#
# Uses `git archive`, so only committed, tracked files are included, and
# whatever is marked `export-ignore` in .gitattributes (dev tooling,
# internal docs) is left out automatically. Uncommitted changes are NOT
# included – commit first.
#
# Usage: bin/build-zip.sh [variant-a|variant-b]

set -euo pipefail

THEME_SLUG="kanzlei-theme"
VARIANT="${1:-both}"

case "${VARIANT}" in
	both|variant-a|variant-b) ;;
	*)
		echo "Usage: $(basename "$0") [variant-a|variant-b]" >&2
		echo "  variant-a = keep the custom form, drop FluentBooking" >&2
		echo "  variant-b = keep FluentBooking, drop the custom form" >&2
		echo "  (no argument) = keep both, as in the base repo" >&2
		exit 1
		;;
esac

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${REPO_ROOT}/dist"
if [ "${VARIANT}" = "both" ]; then
	OUT_FILE="${OUT_DIR}/${THEME_SLUG}.zip"
else
	OUT_FILE="${OUT_DIR}/${THEME_SLUG}-${VARIANT}.zip"
fi

if [ -n "$(cd "${REPO_ROOT}" && git status --porcelain)" ]; then
	echo "Warning: there are uncommitted changes. They will NOT be included in the zip." >&2
fi

mkdir -p "${OUT_DIR}"
rm -f "${OUT_FILE}"

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

git -C "${REPO_ROOT}" archive --format=tar --prefix="${THEME_SLUG}/" HEAD | tar -x -C "${WORK_DIR}"

THEME_DIR="${WORK_DIR}/${THEME_SLUG}"

# Paths belonging to each variant. Deleting a variant's paths without also
# removing its require_once line in functions.php would leave a require
# pointing at a now-missing file -> fatal error on the client site.
VARIANT_A_PATHS=(
	"inc/contact-form-custom.php"
	"blocks/contact-form"
	"assets/js/contact-form.js"
	"patterns/contact-form.php"
)
VARIANT_A_REQUIRE="inc/contact-form-custom.php"

VARIANT_B_PATHS=(
	"inc/contact-form-booking-hooks.php"
	"patterns/contact-booking.php"
)
VARIANT_B_REQUIRE="inc/contact-form-booking-hooks.php"

strip_variant() {
	local require_marker="$1"
	shift
	local path
	for path in "$@"; do
		rm -rf "${THEME_DIR:?}/${path}"
	done
	# Remove the require_once line for the dropped variant so functions.php
	# doesn't reference a file that's no longer in the package.
	sed -i.bak "\#${require_marker}#d" "${THEME_DIR}/functions.php"
	rm -f "${THEME_DIR}/functions.php.bak"
}

if [ "${VARIANT}" = "variant-a" ]; then
	strip_variant "${VARIANT_B_REQUIRE}" "${VARIANT_B_PATHS[@]}"
elif [ "${VARIANT}" = "variant-b" ]; then
	strip_variant "${VARIANT_A_REQUIRE}" "${VARIANT_A_PATHS[@]}"
fi

(cd "${WORK_DIR}" && zip -rq "${OUT_FILE}" "${THEME_SLUG}")

echo "Built: ${OUT_FILE} (${VARIANT})"

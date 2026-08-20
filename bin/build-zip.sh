#!/usr/bin/env bash
#
# Builds a clean, upload-ready theme ZIP for WordPress's
# "Appearance -> Themes -> Add New -> Upload Theme" dialog.
#
# The base repo keeps all three contact approaches (variant A: custom
# form, variant B: FluentBooking, variant C: static cards only, no form)
# as the shared source of truth – see README "Note for follow-up
# projects". A client only ever gets one; pass which one to strip the
# others out of the build. Without an argument, all three stay in
# (useful for testing/archiving the base repo itself).
#
# Uses `git archive`, so only committed, tracked files are included, and
# whatever is marked `export-ignore` in .gitattributes (dev tooling,
# internal docs) is left out automatically. Uncommitted changes are NOT
# included – commit first.
#
# Usage: bin/build-zip.sh [variant-a|variant-b|variant-c]

set -euo pipefail

THEME_SLUG="lawyer-theme"
VARIANT="${1:-both}"

case "${VARIANT}" in
	both|variant-a|variant-b|variant-c) ;;
	*)
		echo "Usage: $(basename "$0") [variant-a|variant-b|variant-c]" >&2
		echo "  variant-a = keep the custom form, drop FluentBooking and the cards-only pattern" >&2
		echo "  variant-b = keep FluentBooking, drop the custom form and the cards-only pattern" >&2
		echo "  variant-c = drop both A and B, keep the static contact-info cards only" >&2
		echo "  (no argument) = keep all three, as in the base repo" >&2
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

# Variant C (cards only) has no PHP backend of its own – it's a pure
# pattern file that reuses template-parts/contact-cards.php (shared,
# never stripped). No functions.php require_once line exists for it, so
# there's no "require marker" to remove via strip_variant()'s sed step.
VARIANT_C_PATH="patterns/contact-cards-only.php"

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
	rm -rf "${THEME_DIR:?}/${VARIANT_C_PATH}"
elif [ "${VARIANT}" = "variant-b" ]; then
	strip_variant "${VARIANT_A_REQUIRE}" "${VARIANT_A_PATHS[@]}"
	rm -rf "${THEME_DIR:?}/${VARIANT_C_PATH}"
elif [ "${VARIANT}" = "variant-c" ]; then
	strip_variant "${VARIANT_A_REQUIRE}" "${VARIANT_A_PATHS[@]}"
	strip_variant "${VARIANT_B_REQUIRE}" "${VARIANT_B_PATHS[@]}"
fi

(cd "${WORK_DIR}" && zip -rq "${OUT_FILE}" "${THEME_SLUG}")

echo "Built: ${OUT_FILE} (${VARIANT})"

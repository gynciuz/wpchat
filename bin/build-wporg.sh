#!/usr/bin/env bash
#
# Build the WordPress.org-compliant plugin ZIP from the current committed HEAD.
#
# Differs from the GitHub-Releases build (bin/release.sh):
#   - NO bundled update checker (vendor-puc/)   — wp.org bans bundled updaters
#   - NO `Update URI:` header                    — wp.org bans it
#   - NO GitSync (includes/class-git-sync.php)   — uses proc_open(), forbidden
#   - a wp.org-safe SLUG (no "wp")               — override with WPORG_SLUG
#
# wpchat.php loads PUC + GitSync behind file_exists() guards, so removing those
# files from the export is all that's needed for the code to no-op cleanly.
#
# Usage:  pnpm --dir app build   # ensure build/ is current, then:
#         WPORG_SLUG=chat-admin-for-woocommerce bin/build-wporg.sh
#
# Slug options (must not contain "wp"; "... for WooCommerce" is the permitted
# trademark form): chat-admin-for-woocommerce | store-chat-admin |
# chat-assistant-for-woocommerce | shopchat-admin
set -euo pipefail

SLUG="${WPORG_SLUG:-chat-admin-for-woocommerce}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
OUT="$STAGE/$SLUG"

# 1) Export the runtime tree (honours .gitattributes export-ignore).
mkdir -p "$OUT"
git -C "$ROOT" archive HEAD | tar -x -C "$OUT"

# 2) Strip what wp.org bans.
rm -rf "$OUT/vendor-puc"                    # bundled update checker
rm -f  "$OUT/includes/class-git-sync.php"   # proc_open() is forbidden

# 3) Remove the `Update URI:` header line (a comment — can't be guarded in code).
grep -v 'Update URI:' "$OUT/wpchat.php" > "$OUT/wpchat.php.tmp"
mv "$OUT/wpchat.php.tmp" "$OUT/wpchat.php"

# 4) Package.
VERSION="$(grep -oE "WPCHAT_VERSION', '[0-9.]+'" "$OUT/wpchat.php" | grep -oE '[0-9.]+' | head -1)"
mkdir -p "$DIST"
ZIP="$DIST/${SLUG}-v${VERSION}-wporg.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -rqX "$ZIP" "$SLUG" -x '*.DS_Store' )
rm -rf "$STAGE"

echo "wp.org build → $ZIP"
echo "slug: $SLUG   version: $VERSION"
echo "NOTE: Text Domain header is still 'wpchat' (≠ slug) — a Plugin Check WARNING,"
echo "      not an error. Matching it to the slug is a larger i18n rename; deferred."

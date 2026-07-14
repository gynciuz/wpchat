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
# chat-admin.php loads PUC + GitSync behind file_exists() guards, so removing those
# files from the export is all that's needed for the code to no-op cleanly.
#
# Usage:  pnpm --dir app build   # ensure build/ is current, then:
#         bin/build-wporg.sh            # slug defaults to chat-admin
#         WPORG_SLUG=other-slug bin/build-wporg.sh
#
# The default slug `chat-admin` matches the plugin text domain, so there is no
# TextDomainMismatch. Any override must not contain "wp"/"wordpress".
set -euo pipefail

SLUG="${WPORG_SLUG:-chatadmin}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
OUT="$STAGE/$SLUG"

# 1) Export the runtime tree (honours .gitattributes export-ignore).
mkdir -p "$OUT"
git -C "$ROOT" archive HEAD | tar -x -C "$OUT"

# 2) Strip what wp.org bans.
rm -rf "$OUT/vendor-puc"                    # bundled update checker
rm -f  "$OUT/includes/updater.php"          # PUC bootstrap (updater code)
rm -f  "$OUT/includes/class-git-sync.php"   # proc_open() is forbidden

# 3) Remove the `Update URI:` header line (a comment — can't be guarded in code).
grep -v 'Update URI:' "$OUT/chat-admin.php" > "$OUT/chat-admin.php.tmp"
mv "$OUT/chat-admin.php.tmp" "$OUT/chat-admin.php"

# 4) Package.
VERSION="$(grep -oE "CHATADMIN_VERSION', '[0-9.]+'" "$OUT/chat-admin.php" | grep -oE '[0-9.]+' | head -1)"
mkdir -p "$DIST"
ZIP="$DIST/${SLUG}-v${VERSION}-wporg.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -rqX "$ZIP" "$SLUG" -x '*.DS_Store' )
rm -rf "$STAGE"

echo "wp.org build → $ZIP"
echo "slug: $SLUG   version: $VERSION   (text domain 'chatadmin' matches the slug)"

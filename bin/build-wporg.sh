#!/usr/bin/env bash
#
# Build the WordPress.org-compliant plugin ZIP from the current committed HEAD.
#
# Differs from the GitHub-Releases build (bin/release.sh):
#   - NO bundled update checker (vendor-puc/)   — wp.org bans bundled updaters
#   - NO `Update URI:` header                    — wp.org bans it
#   - a wp.org-safe SLUG (no "wp")               — override with WPORG_SLUG
#   - DOES ship the React source under app/      — wp.org guideline 4 requires
#     human-readable code, and build/assets/*.js is minified Vite output
#
# chat-admin.php loads PUC behind a file_exists() guard, so removing that file
# from the export is all that's needed for the code to no-op cleanly.
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

# 2) Add back the React source. .gitattributes export-ignores /app for the
#    GitHub build (the ZIP only needs build/), but wp.org guideline 4 wants the
#    unminified source next to the compiled bundle. Read blobs straight out of
#    HEAD so export-ignore does not apply.
#    Dotfiles are skipped: the wp.org uploader rejects the whole package with
#    "hidden_files: Hidden files are not permitted."
git -C "$ROOT" ls-files -z app | while IFS= read -r -d '' f; do
    case "/$f" in */.*) continue ;; esac
    mkdir -p "$OUT/$(dirname "$f")"
    git -C "$ROOT" show "HEAD:$f" > "$OUT/$f"
done

# 3) Strip what wp.org bans.
rm -rf "$OUT/vendor-puc"                    # bundled update checker
rm -f  "$OUT/includes/updater.php"          # PUC bootstrap (updater code)

# 4) Remove the `Update URI:` header line and the whole GitHub-auto-update
#    block (comments — they can't be guarded in code). A reviewer reading the
#    file should find no trace of an update channel, not prose describing one.
python3 - "$OUT/chat-admin.php" <<'STRIP'
import re, sys
p = sys.argv[1]
s = open(p).read()
s = re.sub(r'^ \* Update URI:.*\n', '', s, flags=re.M)
s = re.sub(
    r'\n/\*\n \* Auto-update from GitHub Releases.*?\*/\n'
    r'// Optional GitHub-Releases auto-update helper\..*?\n(?://.*\n)*'
    r'if \(file_exists\(CHATADMIN_DIR \. \'includes/updater\.php\'\)\) \{\n'
    r'.*?\n\}\n',
    '\n', s, flags=re.S)
open(p, 'w').write(s)
STRIP

# 5) Package.
VERSION="$(grep -oE "CHATADMIN_VERSION', '[0-9.]+'" "$OUT/chat-admin.php" | grep -oE '[0-9.]+' | head -1)"
# Refuse to package a hidden file — the wp.org uploader rejects the upload
# outright, so catch it here rather than after a round-trip.
if find "$OUT" -name '.*' -not -name '.' -not -name '..' | grep -q .; then
    echo "ERROR: hidden files in the package — wp.org will reject it:" >&2
    find "$OUT" -name '.*' -not -name '.' -not -name '..' | sed "s|$OUT|.|" >&2
    exit 1
fi

mkdir -p "$DIST"
ZIP="$DIST/${SLUG}-v${VERSION}-wporg.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -rqX "$ZIP" "$SLUG" -x '*.DS_Store' )
rm -rf "$STAGE"

echo "wp.org build → $ZIP"
echo "slug: $SLUG   version: $VERSION   (text domain 'chatadmin' matches the slug)"

#!/usr/bin/env bash
#
# Publish an APPROVED release to the WordPress.org plugin SVN repo.
#
# Run this ONLY after the Plugins Team approves "ChatAdmin – AI chat admin"
# and the SVN repo exists at https://plugins.svn.wordpress.org/chatadmin/.
#
# What it does:
#   1. Checks out (or updates) the SVN repo under dist/svn-chatadmin/
#   2. Fills trunk/ from the reviewed wp.org ZIP (the stripped build — no PUC,
#      no updater, no GitSync, no Update URI), so trunk == what was reviewed
#   3. Copies the directory assets (icon/banner/screenshots) into assets/
#   4. svn add/delete, commits trunk + assets, then tags the version
#
# Prereqs: svn (`brew install svn`) and your wp.org login. svn will PROMPT for
# your password on commit — that is expected; do not put it in this file.
#
# Usage:
#   bin/build-wporg.sh                        # build the clean ZIP first
#   SVN_USER=chatapp bin/publish-wporg.sh      # version read from chat-admin.php
#   SVN_USER=chatapp bin/publish-wporg.sh 0.7.3
#
set -euo pipefail

SLUG="chatadmin"
SVN_USER="${SVN_USER:-chatapp}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(grep -oE "CHATADMIN_VERSION', '[0-9.]+'" "$ROOT/chat-admin.php" | grep -oE '[0-9.]+' | head -1)}"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
WORK="${ROOT}/dist/svn-${SLUG}"
ZIP="${ROOT}/dist/${SLUG}-v${VERSION}-wporg.zip"

command -v svn >/dev/null || { echo "svn not found — run: brew install svn"; exit 1; }
[[ -f "$ZIP" ]] || { echo "Missing $ZIP — run bin/build-wporg.sh first."; exit 1; }
grep -q "Stable tag: ${VERSION}" "$ROOT/readme.txt" \
  || { echo "readme.txt 'Stable tag:' must equal ${VERSION} (wp.org serves the Stable tag)."; exit 1; }

echo "Publishing ${SLUG} ${VERSION} as wp.org user '${SVN_USER}'."

# 1) Checkout (first run) or update.
if [[ -d "$WORK/.svn" ]]; then svn update "$WORK"; else svn checkout "$SVN_URL" "$WORK"; fi
mkdir -p "$WORK/trunk" "$WORK/assets"

# 2) trunk == the reviewed ZIP contents (rsync --delete mirrors adds+removals).
tmp="$(mktemp -d)"; unzip -q "$ZIP" -d "$tmp"
rsync -a --delete --exclude='.svn' "$tmp/${SLUG}/" "$WORK/trunk/"
rm -rf "$tmp"

# 3) Directory assets (icon / banner / screenshots).
cp "$ROOT"/site/wporg-assets/icon-256x256.png \
   "$ROOT"/site/wporg-assets/icon-128x128.png \
   "$ROOT"/site/wporg-assets/banner-1544x500.png \
   "$ROOT"/site/wporg-assets/banner-772x250.png \
   "$ROOT"/site/wporg-assets/screenshot-1.png \
   "$ROOT"/site/wporg-assets/screenshot-2.png \
   "$ROOT"/site/wporg-assets/screenshot-3.png \
   "$WORK/assets/"

# 4) Stage new files, then mark any removed files as svn-deleted.
svn add --force "$WORK/trunk" "$WORK/assets" >/dev/null 2>&1 || true
svn status "$WORK" | awk '/^!/{print $2}' | while read -r f; do svn delete "$f" >/dev/null || true; done

# 5) Commit trunk + assets (svn prompts for your wp.org password here).
svn commit "$WORK" -m "ChatAdmin ${VERSION}" --username "$SVN_USER"

# 6) Tag the release (server-side copy of trunk → tags/VERSION).
svn copy "$SVN_URL/trunk" "$SVN_URL/tags/${VERSION}" -m "Tag ${VERSION}" --username "$SVN_USER"

echo
echo "Done. It goes live shortly at https://wordpress.org/plugins/${SLUG}/"
echo "Sanity-check: trunk/readme.txt 'Stable tag' == ${VERSION}, and tags/${VERSION}/ now exists."

#!/usr/bin/env bash
#
# Build the distributable ChatAdmin plugin ZIP.
#
# What it does:
#   1. Reads the version from the chat-admin.php plugin header.
#   2. Asserts that version matches everywhere it must (CHATADMIN_VERSION,
#      readme.txt Stable tag, and a matching changelog heading).
#   3. Rebuilds the React app and refuses to continue if the committed
#      build/ assets are stale (the released ZIP serves them).
#   4. Packages a clean chat-admin-vX.Y.Z.zip via `git archive`, which honors
#      the export-ignore rules in .gitattributes (tests/, app/src/, etc.).
#   5. Optionally creates the GitHub Release and uploads the asset (--publish).
#
# Usage:
#   bin/release.sh            # build + verify + package
#   bin/release.sh --publish  # ...and create the GitHub release (needs gh)
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail() { echo "✗ $*" >&2; exit 1; }
ok()   { echo "✓ $*"; }

PUBLISH=0
[[ "${1:-}" == "--publish" ]] && PUBLISH=1

# --- 1. Read the canonical version from the plugin header ------------------
VERSION="$(grep -m1 -E '^\s*\*\s*Version:' chat-admin.php | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')"
[[ -n "$VERSION" ]] || fail "Could not read Version from chat-admin.php header."
echo "Releasing ChatAdmin v$VERSION"

# --- 2. Version consistency across files -----------------------------------
grep -q "define('CHATADMIN_VERSION', '$VERSION')" chat-admin.php \
  || fail "CHATADMIN_VERSION in chat-admin.php does not match header ($VERSION)."
grep -q "^Stable tag: $VERSION\$" readme.txt \
  || fail "readme.txt 'Stable tag' does not match $VERSION."
grep -q "^= $VERSION =\$" readme.txt \
  || fail "readme.txt has no changelog heading '= $VERSION ='."
ok "Version $VERSION is consistent across chat-admin.php and readme.txt."

# --- 3. Build the app and require committed build/ to be current ------------
if [[ -d app ]]; then
  ( cd app && pnpm install --frozen-lockfile && pnpm build ) || fail "App build failed."
fi
if ! git diff --quiet -- build; then
  git --no-pager diff --stat -- build || true
  fail "build/ changed after rebuild — run 'cd app && pnpm build' and commit build/ before releasing."
fi
ok "build/ is freshly compiled and committed."

# Warn (don't block) on other uncommitted changes — the ZIP uses committed tree.
if ! git diff --quiet || ! git diff --quiet --cached; then
  echo "⚠ Working tree has uncommitted changes; the ZIP is built from the committed tree (HEAD)."
fi

# --- 4. Package via git archive (honors .gitattributes export-ignore) ------
OUT="chat-admin-v$VERSION.zip"
rm -f "$OUT"
git archive --format=zip --prefix=chat-admin/ -o "$OUT" HEAD
ok "Wrote $OUT ($(du -h "$OUT" | cut -f1))."
echo "Top-level contents:"
unzip -l "$OUT" | awk '{print $4}' | sed -n 's#^chat-admin/\([^/]*\)/\?$#  \1#p' | sort -u

# Sanity: dev files must NOT be in the package.
if unzip -l "$OUT" | grep -qE 'chat-admin/(tests/|app/src/|composer\.json|phpunit)'; then
  fail "Package contains dev files that should be export-ignored — check .gitattributes."
fi
# Sanity: the built assets MUST be in the package.
unzip -l "$OUT" | grep -q 'chat-admin/build/manifest.json' \
  || fail "Package is missing build/manifest.json — the plugin would 500 on load."
ok "Package sanity checks passed."

# --- 5. Optional: publish the GitHub release -------------------------------
if [[ "$PUBLISH" == "1" ]]; then
  command -v gh >/dev/null || fail "gh CLI not found; cannot --publish."
  TAG="v$VERSION"
  git rev-parse "$TAG" >/dev/null 2>&1 && fail "Tag $TAG already exists."
  gh release create "$TAG" "$OUT" \
    --title "ChatAdmin $TAG" \
    --notes "See readme.txt changelog for $VERSION." \
    || fail "gh release create failed."
  ok "Published GitHub release $TAG."
else
  echo "Done. Run 'bin/release.sh --publish' to tag + upload to GitHub Releases."
fi

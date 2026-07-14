# Publishing ChatAdmin to WordPress.org (post-approval runbook)

Do this **after** the Plugins Team approves **ChatAdmin – AI chat admin**
(slug `chatadmin`). Approval email arrives at `tiptop_blocs_0t@icloud.com`; it
contains the SVN URL: `https://plugins.svn.wordpress.org/chatadmin/`.

wp.org distribution is **SVN**, not Git. Your Git repo stays the source of
truth; SVN is just the publish target. The reviewed ZIP already is the correct
"stripped" build (no PUC, no `Update URI`, no GitSync), so publishing = putting
that build into `trunk/` + assets, then tagging.

## One-time prerequisites
- `svn` installed — `brew install svn` (already present on this machine).
- Your wp.org login (username `chatapp`, same password as wordpress.org). SVN
  commits prompt for the password interactively — never store it in a file.

## The fast path (2 commands)
```bash
cd app && pnpm build && cd ..     # ensure build/ is current
bin/build-wporg.sh                # -> dist/chatadmin-v0.7.2-wporg.zip (Plugin-Check clean)
SVN_USER=chatapp bin/publish-wporg.sh
```
`bin/publish-wporg.sh` checks out the SVN repo, mirrors the reviewed ZIP into
`trunk/`, copies the assets, commits, and tags `0.7.2`. svn will ask for your
password twice (commit + tag). Done — live within minutes at
`https://wordpress.org/plugins/chatadmin/`.

## How wp.org decides what's "live"
wp.org serves the version named by **`Stable tag:` in `trunk/readme.txt`**, and
that tag must exist under `tags/`. So the release contract is:
- `chat-admin.php` header `Version:` **and** `CHATADMIN_VERSION` = `X.Y.Z`
- `readme.txt` `Stable tag: X.Y.Z` **and** a `= X.Y.Z =` changelog heading
- `tags/X.Y.Z/` committed in SVN

`bin/release.sh`'s guard already asserts the first two agree; `publish-wporg.sh`
refuses to run if `Stable tag` ≠ the version.

## SVN layout after publishing
```
chatadmin/
├── trunk/            ← current dev version (the reviewed build)
│   ├── chat-admin.php, includes/, build/, readme.txt, PRIVACY? (no)
├── tags/
│   └── 0.7.2/        ← immutable snapshot wp.org serves
└── assets/           ← directory listing art (NOT shipped to users)
    ├── icon-256x256.png, icon-128x128.png
    ├── banner-1544x500.png, banner-772x250.png
    └── screenshot-1.png, screenshot-2.png, screenshot-3.png
```
Screenshot captions come from `readme.txt` `== Screenshots ==`, numbered to
match `screenshot-N.png`.

## Assets mapping (source → SVN)
| Repo file (`site/wporg-assets/`) | SVN `assets/` name |
|---|---|
| `icon-256x256.png` / `icon-128x128.png` | same |
| `banner-1544x500.png` / `banner-772x250.png` | same |
| `screenshot-1.png` … `screenshot-3.png` | same |

The `publish-wporg.sh` script copies these automatically. To update just the
directory art later (no code release), edit them in `assets/` and
`svn commit` — no new tag needed.

## Manual fallback (if the script errors)
```bash
svn checkout https://plugins.svn.wordpress.org/chatadmin chatadmin-svn
cd chatadmin-svn
# fill trunk from the reviewed ZIP (folder inside is 'chatadmin/')
unzip -o ../dist/chatadmin-v0.7.2-wporg.zip -d /tmp/ca && rsync -a --delete --exclude='.svn' /tmp/ca/chatadmin/ trunk/
cp ../site/wporg-assets/{icon-*,banner-*,screenshot-*}.png assets/
svn add --force trunk assets
svn status | awk '/^!/{print $2}' | xargs -r svn delete
svn commit -m "ChatAdmin 0.7.2" --username chatapp
svn copy ^/trunk ^/tags/0.7.2 -m "Tag 0.7.2" --username chatapp
```

## Shipping a future version (e.g. 0.7.3)
1. Bump `Version:` + `CHATADMIN_VERSION` in `chat-admin.php`, `Stable tag:` +
   a `= 0.7.3 =` changelog block in `readme.txt` (all four — `bin/release.sh`
   asserts this).
2. `cd app && pnpm build && cd ..` then commit `build/`.
3. `bin/build-wporg.sh` → new ZIP.
4. `SVN_USER=chatapp bin/publish-wporg.sh 0.7.3`.

## Gotchas
- **Never commit `vendor-puc/`, `includes/updater.php`, `class-git-sync.php`, or
  the `Update URI` header to wp.org.** The ZIP already excludes them; only ever
  publish from `bin/build-wporg.sh` output, never the raw repo.
- **Main file is `chat-admin.php`** while the slug is `chatadmin` — that's fine
  (wp.org identifies the plugin by its header, not the filename).
- **Screenshots** currently show the app UI at v0.4 badge era; regenerate with
  `cd app && pnpm test:e2e:update` then re-copy to `site/wporg-assets/` if you
  want them refreshed before publishing.
- First SVN commit can take a minute (uploads `build/` assets). Subsequent ones
  only send diffs.

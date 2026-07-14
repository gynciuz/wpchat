# WordPress.org directory assets

Generated brand/marketing assets for the wp.org plugin directory listing.
These do **not** ship in the plugin ZIP (the whole `site/` tree is
`export-ignore`d). They belong in the plugin's **SVN `assets/` directory**
(a sibling of `trunk/` and `tags/`), not in the plugin code.

## Files

| File | Purpose | SVN name |
|------|---------|----------|
| `icon-256x256.png` | Plugin icon (retina) | `assets/icon-256x256.png` |
| `icon-128x128.png` | Plugin icon (1x) | `assets/icon-128x128.png` |
| `banner-1544x500.png` | Header banner (retina) | `assets/banner-1544x500.png` |
| `banner-772x250.png` | Header banner (1x) | `assets/banner-772x250.png` |
| `screenshot-1.png` | Chat empty state / outcome hero | `assets/screenshot-1.png` |
| `screenshot-2.png` | Orders table card | `assets/screenshot-2.png` |
| `screenshot-3.png` | First-run onboarding wizard | `assets/screenshot-3.png` |

Screenshot captions live in `readme.txt` under `== Screenshots ==`, numbered
to match the `screenshot-N.png` files.

## How they were made

- `icon.html`, `banner-1544.html` — source pages, rendered to exact-size PNGs
  with headless Google Chrome (`--headless --screenshot --window-size=W,H`).
  Re-render after editing the HTML. The 128px icon and 772px banner are
  downscaled from the retina versions with `sips`.
- `screenshot-*.png` — the **real** app UI, captured from the Playwright E2E
  visual baselines (`app/e2e/__screenshots__/`), which mount the actual React
  components with mock data. Regenerate with `pnpm test:e2e:update`.

## To refresh the screenshots (real UI, English, current version)

The baselines currently show the `v0.4` header badge and a Lithuanian orders
table (mock locale). To capture fresh English screenshots on the current
version, run the E2E suite and re-copy the baselines:

```bash
cd app && pnpm test:e2e:update
cp e2e/__screenshots__/chat.spec.ts/chat-empty.png        ../site/wporg-assets/screenshot-1.png
cp e2e/__screenshots__/chat.spec.ts/chat-orders-table.png ../site/wporg-assets/screenshot-2.png
cp e2e/__screenshots__/onboarding.spec.ts/onboarding-welcome.png ../site/wporg-assets/screenshot-3.png
```

# WPChat support collector (Cloudflare Worker)

`support-worker.js` is the endpoint that receives WPChat's error telemetry,
"Report a problem" submissions, and Cloud-waitlist signups. It HMAC-verifies
each request and emails them to you (via Resend), with optional KV storage and
a Slack/Discord ping. Free tier is plenty.

## Deploy

1. **Install Wrangler** and log in:
   ```sh
   npm i -g wrangler
   wrangler login
   ```

2. **Create `wrangler.toml`** next to `support-worker.js`:
   ```toml
   name = "wpchat-support"
   main = "support-worker.js"
   compatibility_date = "2026-01-01"

   # Non-secret vars:
   [vars]
   TO_EMAIL   = "you@example.com"
   FROM_EMAIL = "WPChat <reports@yourdomain.com>"   # a Resend-verified sender
   # SLACK_WEBHOOK = "https://hooks.slack.com/services/…"   # optional

   # Optional: store every payload in KV for 90 days.
   # [[kv_namespaces]]
   # binding = "REPORTS"
   # id = "<your-kv-namespace-id>"   # create with: wrangler kv namespace create REPORTS
   ```

3. **Set secrets** (never put these in `wrangler.toml`):
   ```sh
   wrangler secret put SUPPORT_SECRET     # any long random string — must match the plugin
   wrangler secret put RESEND_API_KEY     # from resend.com (free tier)
   ```

4. **Deploy:**
   ```sh
   wrangler deploy
   ```
   Note the printed URL, e.g. `https://wpchat-support.<you>.workers.dev`.

5. **Point the plugin at it** — add to the site's `wp-config.php`:
   ```php
   define('WPCHAT_SUPPORT_ENDPOINT', 'https://wpchat-support.<you>.workers.dev');
   define('WPCHAT_SUPPORT_SECRET',   'the-same-long-random-string-as-SUPPORT_SECRET');
   ```
   (You ship these in the plugin's default build or document them for self-hosters.
   `WPCHAT_SUPPORT_SECRET` only deters casual spam — pair it with a Cloudflare
   rate-limiting rule on the Worker route.)

## Test locally

```sh
wrangler dev   # serves on http://localhost:8787
# Sign a payload with your SUPPORT_SECRET and POST it:
BODY='{"kind":"support_report","site":"test.example","note":"hello"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SUPPORT_SECRET" -hex | sed 's/^.*= //')
curl -s localhost:8787 -H "X-WPChat-Signature: sha256=$SIG" -H 'content-type: application/json' -d "$BODY"
# → {"ok":true};  a wrong/absent signature → 401 {"error":"bad_signature"}
```

## What gets sent

| `kind` | When | Emailed? |
|--------|------|----------|
| `support_report` | user clicks "Report a problem" / Diagnostics → Send | yes |
| `cloud_waitlist_signup` | user joins the WPChat Cloud waitlist | yes |
| `telemetry` | a production error, only if the admin left telemetry on | no by default (stored/pinged) |

Telemetry payloads are PII-free (error message, tool name, plugin/PHP/WP
version, site host). Support reports include what the user chose to send (recent
conversation + recent error log). See the plugin's `PRIVACY.md`.

/**
 * WPChat support collector — Cloudflare Worker.
 *
 * Receives the JSON payloads WPChat sends (error telemetry, "Report a problem"
 * submissions, and Cloud-waitlist signups), verifies the HMAC signature so it
 * can reject junk, and fans out:
 *   - email to you via Resend (skips noisy telemetry by default),
 *   - optional KV storage (90-day TTL),
 *   - optional Slack/Discord ping.
 *
 * Configure via `wrangler` secrets/vars — see cloud/README.md. The plugin must
 * set WPCHAT_SUPPORT_ENDPOINT (this Worker's URL) and WPCHAT_SUPPORT_SECRET
 * (matching SUPPORT_SECRET here) in wp-config.php.
 */

const KINDS = ["support_report", "telemetry", "cloud_waitlist_signup"];

export default {
  async fetch(request, env) {
    if (request.method !== "POST") return json({ error: "method_not_allowed" }, 405);

    const raw = await request.text();

    // Verify the HMAC signature (deters spam; pair with a Cloudflare rate-limit rule).
    if (env.SUPPORT_SECRET) {
      const ok = await verifySignature(raw, request.headers.get("X-WPChat-Signature"), env.SUPPORT_SECRET);
      if (!ok) return json({ error: "bad_signature" }, 401);
    }

    let payload;
    try {
      payload = JSON.parse(raw);
    } catch {
      return json({ error: "bad_json" }, 400);
    }

    const kind = String(payload.kind || "unknown");
    if (!KINDS.includes(kind)) return json({ error: "bad_kind" }, 400);

    // Optional: store every payload in KV (bind as REPORTS in wrangler.toml).
    if (env.REPORTS) {
      const id = `${kind}:${Date.now()}:${crypto.randomUUID()}`;
      await env.REPORTS.put(id, raw, { expirationTtl: 60 * 60 * 24 * 90 }).catch(() => {});
    }

    // Email — skip telemetry by default (too noisy); change the condition to taste.
    if (env.RESEND_API_KEY && env.TO_EMAIL && kind !== "telemetry") {
      await sendEmail(env, kind, payload, raw);
    }

    // Optional chat ping.
    if (env.SLACK_WEBHOOK) {
      const note = payload.note ? ` — ${payload.note}` : "";
      await fetch(env.SLACK_WEBHOOK, {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ text: `WPChat ${kind} from ${payload.site || "?"}${note}` }),
      }).catch(() => {});
    }

    return json({ ok: true });
  },
};

function json(obj, status = 200) {
  return new Response(JSON.stringify(obj), { status, headers: { "content-type": "application/json" } });
}

async function verifySignature(raw, header, secret) {
  const m = /^sha256=([a-f0-9]+)$/i.exec(header || "");
  if (!m) return false;
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", enc.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const mac = await crypto.subtle.sign("HMAC", key, enc.encode(raw));
  const hex = [...new Uint8Array(mac)].map((b) => b.toString(16).padStart(2, "0")).join("");
  const got = m[1].toLowerCase();
  // Constant-time-ish compare.
  if (hex.length !== got.length) return false;
  let diff = 0;
  for (let i = 0; i < hex.length; i++) diff |= hex.charCodeAt(i) ^ got.charCodeAt(i);
  return diff === 0;
}

async function sendEmail(env, kind, payload, raw) {
  const subject = `[WPChat] ${kind} from ${payload.site || "unknown site"}`;
  const text = `${payload.note ? "Note: " + payload.note + "\n\n" : ""}${raw}`;
  await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { Authorization: `Bearer ${env.RESEND_API_KEY}`, "content-type": "application/json" },
    body: JSON.stringify({
      from: env.FROM_EMAIL || "WPChat <onboarding@resend.dev>",
      to: env.TO_EMAIL,
      subject,
      text,
    }),
  }).catch(() => {});
}

import type { Page } from "@playwright/test";

/**
 * Shared fixtures for the ChatAdmin E2E suite: a CHATADMIN_BOOT payload, canned
 * REST responses matching the real endpoint shapes, and helpers to inject
 * the boot + intercept chatadmin/v1/* network calls.
 */

export const BOOT = {
  mode: "chat" as const,
  restUrl: "/chatadmin/v1/",
  nonce: "test-nonce",
  userId: 1,
  userName: "Test Admin",
  firstName: "Test",
  locale: "en",
  siteName: "Gentleman's Empire",
  siteUrl: "https://example.test",
  logoutUrl: "https://example.test/wp-login.php?action=logout",
};

/** GET actions/order-statuses — drives the per-row status dropdown. */
export const ORDER_STATUSES = {
  statuses: [
    { slug: "processing", label: "Processing" },
    { slug: "completed", label: "Completed" },
    { slug: "panaudotas", label: "Panaudotas" },
  ],
};

/** POST chat response whose tool_calls include a list_orders output, so the
 *  chat renders the interactive <OrdersTable> above the assistant prose. */
export const ORDERS_CHAT_RESPONSE = {
  text: "Here are your 2 most recent orders.",
  tool_calls: [
    {
      name: "list_orders",
      input: { limit: 2 },
      output: {
        orders: [
          {
            id: 2847,
            number: 2847,
            status: "processing",
            date: "2026-06-18",
            total: 120,
            currency: "EUR",
            customer: "Jonas Jonaitis",
            email: "jonas@example.test",
            item_names: ["Haircut voucher"],
          },
          {
            id: 2846,
            number: 2846,
            status: "completed",
            date: "2026-06-17",
            total: 45,
            currency: "EUR",
            customer: "Petras Petraitis",
            email: "petras@example.test",
            item_names: ["Beard trim"],
          },
        ],
      },
    },
  ],
  conversation_id: "00000000-0000-0000-0000-000000000001",
};

/** GET onboarding/status — full OnboardingStatus shape for the wizard. */
export const ONBOARDING_STATUS = {
  apiKey: { ok: false, masked: null, source: "none", editable: true },
  model: {
    current: "claude-sonnet-4-6",
    options: [
      { id: "claude-sonnet-4-6", label: "Claude Sonnet 4.6 (recommended)" },
      { id: "claude-opus-4-7", label: "Claude Opus 4.7" },
      { id: "claude-haiku-4-5", label: "Claude Haiku 4.5" },
    ],
  },
  provider: { current: "byo", cloudAvailable: false, cloudWaitlistOpen: true },
  permissions: { ok: true, has: ["manage_woocommerce"], required: ["edit_posts"], role: "administrator" },
  wc: { active: true, version: "9.0.0", order_count: 128, install_url: "" },
  analytics: {
    detected: [{ id: "jetpack-stats", name: "Jetpack Stats" }],
    recommended: [],
  },
  backends: [
    {
      kind: "wp_post",
      description: "Posts and pages",
      fields: ["title", "content"],
      source: "core",
      requiredCap: "edit_posts",
      userCanEdit: true,
      siteDisabled: false,
    },
  ],
  integrations: {
    cf_purge: { configured: false, snippet: "" },
    git_sync: { configured: false, snippet: "" },
  },
  disabled_kinds: [],
  isAdmin: true,
  user: { id: 1, display_name: "Test Admin", first_name: "Test", locale: "en" },
  site: { name: "Gentleman's Empire", url: "https://example.test", admin: "https://example.test/wp-admin" },
};

interface RouteHandlers {
  chat?: unknown;
  onboardingStatus?: unknown;
  conversations?: unknown;
  orderStatuses?: unknown;
  upload?: unknown;
}

/**
 * Intercept every chatadmin/v1/* request and answer with a fixture. Anything
 * unrecognized returns {} so a stray call never hits the network or hangs.
 */
export async function installRoutes(page: Page, handlers: RouteHandlers = {}): Promise<void> {
  await page.route("**/chatadmin/v1/**", async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (body: unknown) =>
      route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(body) });

    if (path.endsWith("/conversations")) return send(handlers.conversations ?? { conversations: [] });
    if (path.endsWith("/actions/order-statuses")) return send(handlers.orderStatuses ?? ORDER_STATUSES);
    if (path.endsWith("/onboarding/status")) return send(handlers.onboardingStatus ?? ONBOARDING_STATUS);
    if (path.endsWith("/onboarding/complete")) return send({ ok: true });
    if (path.endsWith("/upload")) {
      return send(
        handlers.upload ?? {
          attachment_id: 1234,
          url: "https://example.test/wp-content/uploads/img.png",
          filename: "img.png",
          width: 1,
          height: 1,
          mime_type: "image/png",
        }
      );
    }
    if (path.endsWith("/chat")) return send(handlers.chat ?? { text: "ok", tool_calls: [], conversation_id: "c1" });
    return send({});
  });
}

/** Inject CHATADMIN_BOOT (before the app's module runs) and open the harness. */
export async function gotoApp(page: Page, boot: Record<string, unknown> = BOOT): Promise<void> {
  await page.addInitScript((b) => {
    (window as unknown as { CHATADMIN_BOOT: unknown }).CHATADMIN_BOOT = b;
  }, boot);
  await page.goto("/e2e/harness.html");
}

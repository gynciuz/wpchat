<?php
/**
 * ChatAdmin order tools.
 *
 * Each tool returns plain arrays that get JSON-encoded for the model.
 * Implementations call WC PHP functions directly — no REST roundtrips.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Tools {

    /**
     * Per-request context set by Rest::handle_chat on the LLM path
     * (conversation_id + user-turn index) so a mutation confirmation can be
     * bound to a real, earlier user turn (audit finding #2). Empty for
     * direct/programmatic callers (the direct-action REST routes and tests),
     * which are trusted.
     *
     * @var array{conversation_id?:string, turn?:int}
     */
    private static array $request_context = [];

    public static function set_request_context(array $ctx): void {
        self::$request_context = $ctx;
    }

    public static function clear_request_context(): void {
        self::$request_context = [];
    }

    /** Schemas exposed to the model. */
    public static function definitions(): array {
        return [
            [
                'name'        => 'list_orders',
                'description' => 'List WooCommerce orders, optionally filtered by status, search text, or date range. Returns up to `limit` orders sorted by date (newest first).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'status'     => ['type' => 'string', 'description' => 'Status slug (without wc- prefix), e.g. "pending", "processing", "completed", or a custom status like "panaudotas". Pass "any" or omit to include all.'],
                        'search'     => ['type' => 'string', 'description' => 'Free-text search across order number, customer name, email, or item names.'],
                        'limit'      => ['type' => 'integer', 'description' => 'Max number of orders to return (default 10, max 50).'],
                        'since_date' => ['type' => 'string', 'description' => 'ISO 8601 date (e.g. "2026-01-01"). Returns orders created on or after this date.'],
                    ],
                ],
            ],
            [
                'name'        => 'get_order',
                'description' => 'Get full detail of a single WooCommerce order including items, customer, status, totals, and notes.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'WooCommerce order ID.'],
                    ],
                    'required' => ['order_id'],
                ],
            ],
            [
                'name'        => 'update_order_status',
                'description' => 'Change an order\'s status. Optionally append a private note explaining the change. REQUIRES user confirmation: call once WITHOUT `confirmation` to get a `needs_confirmation` summary, show the user what will change (it may email the customer) and wait for their go-ahead, then call again passing their verbatim phrase in `confirmation`.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id'     => ['type' => 'integer'],
                        'status'       => ['type' => 'string', 'description' => 'Status slug without wc- prefix (e.g. "completed", "panaudotas").'],
                        'note'         => ['type' => 'string', 'description' => 'Optional private note to add along with the status change.'],
                        'confirmation' => ['type' => 'string', 'description' => 'Verbatim confirmation phrase the user typed (yes/taip/да/tak/ok …). Omit on the first call to trigger the confirmation prompt.'],
                    ],
                    'required' => ['order_id', 'status'],
                ],
            ],
            [
                'name'        => 'add_order_note',
                'description' => 'Append a note to an order. Private notes are visible only to admins; customer-visible notes are emailed to the customer. Private notes run immediately. A customer-visible note REQUIRES confirmation: call once without `confirmation` to get a `needs_confirmation` summary, confirm the wording with the user, then call again with their phrase in `confirmation`.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id'         => ['type' => 'integer'],
                        'note'             => ['type' => 'string'],
                        'customer_visible' => ['type' => 'boolean', 'description' => 'If true, send the note to the customer via email. Default false (private).'],
                        'confirmation'     => ['type' => 'string', 'description' => 'Verbatim confirmation phrase the user typed. Only needed for customer-visible notes; omit on the first call to trigger the prompt.'],
                    ],
                    'required' => ['order_id', 'note'],
                ],
            ],
            [
                'name'        => 'find_customer_orders',
                'description' => 'Find orders by customer email or name (partial match supported). Returns up to 20 matching orders.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Customer email, full name, or partial name.'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'list_order_actions',
                'description' => 'List the order actions available for a single order — exactly the ones in WooCommerce\'s "Order actions" box, including plugin-added actions. Use this to DISCOVER what emails/actions can be triggered before calling trigger_order_action. Covers built-ins like "Email invoice / order details to customer" and "Resend new order notification", plus plugin actions such as PW Gift Cards "Resend gift cards". Returns each action\'s machine slug and human label.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'WooCommerce order ID.'],
                    ],
                    'required' => ['order_id'],
                ],
            ],
            [
                'name'        => 'trigger_order_action',
                'description' => 'Run an order action on a single order — this is how you RESEND emails (order invoice / details, new-order notification) and run plugin actions like resending gift-card coupons. The `action` must be a slug returned by list_order_actions; if you don\'t know it, call list_order_actions first. Executes the action the same way clicking it in the WooCommerce "Order actions" box would (sends the email, fires the plugin hook) and records a note on the order. Only trigger an action the user explicitly asked for. REQUIRES confirmation: call once without `confirmation` to get a `needs_confirmation` summary, confirm with the user that the email/action should be sent, then call again with their phrase in `confirmation`.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id'     => ['type' => 'integer', 'description' => 'WooCommerce order ID.'],
                        'action'       => ['type' => 'string', 'description' => 'Action slug from list_order_actions, e.g. "send_order_details" (email invoice to customer) or a plugin slug like "send_gift_cards" / "pwgc_resend_gift_cards".'],
                        'confirmation' => ['type' => 'string', 'description' => 'Verbatim confirmation phrase the user typed. Omit on the first call to trigger the confirmation prompt.'],
                    ],
                    'required' => ['order_id', 'action'],
                ],
            ],
            [
                'name'        => 'get_admin_url',
                'description' => 'Return the WordPress admin URL for a given resource so the user can open it in a new tab and act there directly. Use this whenever a request cannot be fulfilled by the available tools (e.g. delete order, refund, bulk action) — hand the user a deep link instead of stating limitations.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'description' => 'One of: order, orders_list, post, pages_list, user, users_list, comments (comment moderation; optional id = one comment), site_health (maintenance / broken links / 404s / updates), plugins, dashboard.'],
                        'id'       => ['type' => 'integer', 'description' => 'Resource id (order id, post id, user id). Required for order/post/user; ignored for *_list and dashboard.'],
                    ],
                    'required' => ['resource'],
                ],
            ],
            [
                'name'        => 'seo_audit',
                'description' => 'Run a read-only SEO / AI-SEO (AEO/GEO) audit of this WordPress site. Returns a structured report: search-engine visibility, permalinks, HTTPS, active SEO plugin, sitemap, AI-crawler (robots.txt) access, llms.txt, site title/tagline, and PHP/MySQL versions — each with a status (ok/warn/fail/info), the current value, a recommendation, and a `fixable` flag. Items with fixable=true can be changed here via preview_content_change/apply_content_change on the `seo_setting` or `seo_meta` kinds; everything else (hosting, Core Web Vitals, schema, keyword research, Search Console submission, backlinks) is advisory — relay the recommendation and, if useful, a get_admin_url link. Call this FIRST whenever the user asks to audit/check/improve SEO.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'get_traffic_summary',
                'description' => 'Get a site traffic / analytics summary (visitors, page views, top pages, top referrers) for a date range. Reads from whichever analytics plugin is installed on the site — Jetpack Stats, WP Statistics, Koko Analytics, Google Site Kit, MonsterInsights, or Statify — auto-detected; you do NOT choose the provider. Use for questions like "how many visitors this week", "kiek lankytojų šią savaitę", "сколько посетителей вчера", "most popular pages". If the result has `integration_pending: true`, the plugin is detected but full data isn\'t wired yet — tell the user it\'s detected and full numbers ship next release (use the `note`); do NOT invent numbers. If the result has an `error` saying no provider was detected, tell the user no analytics plugin is installed.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'date_range' => [
                            'type'        => 'string',
                            'description' => 'Date range to summarize. Defaults to this_week.',
                            'enum'        => ['today', 'yesterday', 'this_week', 'last_7_days', 'last_30_days'],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'find_text',
                'description' => 'Locate a literal piece of text ANYWHERE it is stored on the site — across posts/pages/custom-post-types (title, content, excerpt), post meta, and taxonomy terms, plus a read-only check of site options. Use this FIRST whenever the user reports wrong/typo text ("it says X, should be Y") and you do not already know where X lives — instead of guessing a kind or giving up. Each hit reports WHERE the text is and whether it is `editable` from chat, so you can fix the editable ones (preview→apply on the returned `target`) and, for non-editable ones (protected theme fields, site options, static storage), tell the user exactly where it is and hand off. If a hit is `shared` (a taxonomy term), editing that one term fixes every item using it.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'The exact text to locate (e.g. the misspelled word). 2+ characters.'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'list_content_blocks',
                'description' => 'List content items of a given kind. Available kinds depend on which backends are registered on this site (see the system prompt). Common kinds: wp_post, wp_page_slug, wp_post_meta, wp_term.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'kind' => ['type' => 'string', 'description' => 'The content kind slug.'],
                        'args' => [
                            'type'        => 'object',
                            'description' => 'Optional kind-specific filters (e.g. {search: "..."} for wp_post, {taxonomy: "category"} for wp_term, {post_id: 123} for wp_post_meta).',
                        ],
                    ],
                    'required' => ['kind'],
                ],
            ],
            [
                'name'        => 'preview_content_change',
                'description' => 'PREVIEW (no write) a change to a content item. Returns the diff (old vs new) for every location that would be affected. ALWAYS call this BEFORE apply_content_change.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'target' => [
                            'type'        => 'object',
                            'description' => 'Kind-specific reference to the item. Must include `kind` and the fields the kind requires (e.g. {kind: "wp_post", id: 123}, {kind: "wp_page_slug", slug: "apie-mus"}, {kind: "wp_term", term_id: 5, taxonomy: "category"}).',
                        ],
                        'field' => ['type' => 'string', 'description' => 'Which property to change. Allowed values depend on the kind (see the system prompt).'],
                        'value' => ['description' => 'Proposed new value. Usually a string; some fields accept other types.'],
                    ],
                    'required' => ['target', 'field', 'value'],
                ],
            ],
            [
                'name'        => 'apply_content_change',
                'description' => 'APPLY the change. REQUIRES explicit user confirmation: pass the confirmation phrase the user typed into `confirmation`. Only "yes", "confirm", "apply", "do it", "taip", "patvirtinu", "да", "tak", "ok" (case-insensitive) are accepted. If the user has NOT confirmed in the chat, call preview_content_change first instead.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'target'       => ['type' => 'object', 'description' => 'Same target shape as preview_content_change.'],
                        'field'        => ['type' => 'string'],
                        'value'        => ['description' => 'New value (same as preview).'],
                        'confirmation' => ['type' => 'string', 'description' => 'Verbatim confirmation phrase from the user.'],
                    ],
                    'required' => ['target', 'field', 'value', 'confirmation'],
                ],
            ],
            [
                'name'        => 'list_taxonomy_terms',
                'description' => 'List the site\'s existing taxonomy terms — categories, tags, and any custom public taxonomies — with each term\'s name, slug, and post count, plus an `is_empty` flag per taxonomy. Use this BEFORE creating a post to suggest categories/tags the site already uses (reuse beats inventing new ones), and to detect when a site has no taxonomy yet (→ guide the user through choosing some). Omit `taxonomy` to get all; pass e.g. "category" or "post_tag" to scope.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'taxonomy' => ['type' => 'string', 'description' => 'Optional taxonomy slug (e.g. "category", "post_tag"). Omit for all public taxonomies.'],
                    ],
                ],
            ],
            [
                'name'        => 'create_content',
                'description' => 'Create a new WordPress post or page as a DRAFT (never public until published). Returns the new id, an edit link, and a preview link. Set categories/tags by name — missing ones are created (posts only; pages have no categories/tags). Attach images by attachment_id (from the chat upload markers): `featured_image` becomes the featured image; `image_ids` are appended into the body as image blocks. Optionally set `seo_title` (< 60 chars) and `seo_description` (~150–160 chars) — applied via the active SEO plugin. After creating, show the user the draft preview and ask whether to publish (then call publish_content).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'post_type'       => ['type' => 'string', 'description' => '"post" (default) or "page".'],
                        'title'           => ['type' => 'string', 'description' => 'The post/page title.'],
                        'content'         => ['type' => 'string', 'description' => 'Body content. Plain text/Markdown is wrapped into paragraph blocks; raw HTML is kept as-is.'],
                        'excerpt'         => ['type' => 'string', 'description' => 'Optional short summary.'],
                        'categories'      => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Category names (posts only). Missing ones are created.'],
                        'tags'            => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tag names (posts only). Missing ones are created.'],
                        'featured_image'  => ['type' => 'integer', 'description' => 'attachment_id to set as the featured image.'],
                        'image_ids'       => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'attachment_ids to append into the body as images.'],
                        'seo_title'       => ['type' => 'string', 'description' => 'Optional SEO meta title (< 60 chars).'],
                        'seo_description' => ['type' => 'string', 'description' => 'Optional SEO meta description (~150–160 chars).'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name'        => 'publish_content',
                'description' => 'Publish a draft post/page created with create_content. REQUIRES the user\'s confirmation phrase (yes/taip/да/tak/ok …) in `confirmation` — only publish after the user has seen the draft preview and agreed. This makes the content public.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'post_id'      => ['type' => 'integer', 'description' => 'The draft post/page id from create_content.'],
                        'confirmation' => ['type' => 'string', 'description' => 'Verbatim confirmation phrase the user typed.'],
                    ],
                    'required' => ['post_id', 'confirmation'],
                ],
            ],
        ];
    }

    /** Map name → callable. */
    public static function implementations(): array {
        return [
            'list_orders'           => [__CLASS__, 'list_orders'],
            'get_order'             => [__CLASS__, 'get_order'],
            'update_order_status'   => [__CLASS__, 'update_order_status'],
            'add_order_note'        => [__CLASS__, 'add_order_note'],
            'find_customer_orders'  => [__CLASS__, 'find_customer_orders'],
            'list_order_actions'    => [__CLASS__, 'list_order_actions'],
            'trigger_order_action'  => [__CLASS__, 'trigger_order_action'],
            'get_admin_url'         => [__CLASS__, 'get_admin_url'],
            'get_traffic_summary'   => [__CLASS__, 'get_traffic_summary'],
            'seo_audit'             => [__CLASS__, 'seo_audit'],
            // Generic content-backend dispatch (v0.4).
            // Routes to whichever registered backend claims target.kind.
            // Backends are pulled via apply_filters('chatadmin_content_backends').
            'find_text'             => [__CLASS__, 'find_text'],
            'list_content_blocks'   => [__CLASS__, 'list_content_blocks'],
            'preview_content_change' => [__CLASS__, 'preview_content_change'],
            'apply_content_change'  => [__CLASS__, 'apply_content_change'],
            'list_taxonomy_terms'   => [__CLASS__, 'list_taxonomy_terms'],
            'create_content'        => [__CLASS__, 'create_content'],
            'publish_content'       => [__CLASS__, 'publish_content'],
        ];
    }

    public static function require_wc(): void {
        if (!function_exists('wc_get_orders')) {
            throw new \RuntimeException('WooCommerce is not active.');
        }
    }

    /**
     * Whether the current user may use the order tools at all. Order data is
     * customer PII and order mutations email customers, so both reads and
     * writes require WooCommerce order-management rights — `edit_shop_orders`
     * (or the broader `manage_woocommerce`). A content-only user (e.g. an
     * editor) gets a clean refusal, mirroring how content kinds are gated by
     * `user_can_edit_kind`. Returns null when allowed, or an LLM-shaped
     * `['error' => …, 'code' => 'orders_role_restricted']` when not.
     */
    public static function require_order_access(): ?array {
        if (current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders')) {
            return null;
        }
        return [
            'error' => "Your WordPress role doesn't include WooCommerce order management, so the order tools aren't available to this account — it can edit site content only. Don't retry; if the user needs order access, tell them an administrator must grant their role the 'edit_shop_orders' capability.",
            'code'  => 'orders_role_restricted',
        ];
    }

    /**
     * Map a content `kind` to the WordPress capability the current user
     * needs in order to edit it. Built-in kinds resolve to their natural
     * caps; custom kinds may declare via the ContentBackend's optional
     * `required_cap()` method (we use method_exists so older backends
     * keep working without modification).
     *
     * The optional $target lets us resolve per-post_type caps (e.g.
     * `edit_pages` vs `edit_posts`) when we have a specific post id
     * to inspect. When no target is provided we fall back to the
     * generic "edit posts" cap which is the most permissive parent.
     */
    public static function kind_required_cap(string $kind, array $target = []): string {
        switch ($kind) {
            case 'wp_post':
            case 'wp_post_meta':
            case 'wp_page_slug':
                $post_id = (int) ($target['post_id'] ?? $target['id'] ?? 0);
                if (!$post_id && !empty($target['slug'])) {
                    $page = get_page_by_path((string) $target['slug'], OBJECT, ['post', 'page']);
                    $post_id = $page ? (int) $page->ID : 0;
                }
                if ($post_id) {
                    $type_obj = get_post_type_object(get_post_type($post_id) ?: 'post');
                    if ($type_obj && !empty($type_obj->cap->edit_post)) {
                        return (string) $type_obj->cap->edit_post;
                    }
                }
                return 'edit_posts';

            case 'wp_term':
                $taxonomy = (string) ($target['taxonomy'] ?? '');
                if ($taxonomy) {
                    $tax_obj = get_taxonomy($taxonomy);
                    if ($tax_obj && !empty($tax_obj->cap->edit_terms)) {
                        return (string) $tax_obj->cap->edit_terms;
                    }
                }
                return 'manage_categories';
        }

        // Custom kinds — let the backend declare its own required cap. The
        // target is passed so a backend can resolve an object-scoped cap
        // (e.g. seo_meta → edit_post for the specific post) rather than a
        // role-level one.
        $backend = ContentRouter::for_kind($kind);
        if ($backend && method_exists($backend, 'required_cap')) {
            $cap = (string) $backend->required_cap($kind, $target);
            if ($cap !== '') {
                return $cap;
            }
        }
        return 'edit_posts';
    }

    /**
     * True iff the current user is permitted to edit the given kind.
     * Uses kind_required_cap() to resolve which cap to check; supports
     * per-post resolution when $target carries an id/slug.
     */
    public static function user_can_edit_kind(string $kind, array $target = []): bool {
        $cap = self::kind_required_cap($kind, $target);
        // Per-post / per-term caps take object id as a second arg in WP core.
        $post_id = (int) ($target['post_id'] ?? $target['id'] ?? 0);
        if ($post_id && in_array($cap, ['edit_post', 'edit_page'], true)) {
            return current_user_can($cap, $post_id);
        }
        return current_user_can($cap);
    }

    /**
     * Strip the `wc-` prefix from a WooCommerce status key, if present.
     *
     * NOT `ltrim($slug, 'wc-')` — ltrim treats its second arg as a character
     * SET and would also strip a leading "c" from a slug like "cancelled",
     * turning it into "ancelled". That bug (present since v0.1.0, fixed
     * 2026-05-28) is the real cause of the "Unknown status: ancelled"
     * error that earlier I misdiagnosed as an LLM hallucination.
     */
    public static function unprefixed_status(string $slug): string {
        return str_starts_with($slug, 'wc-') ? substr($slug, 3) : $slug;
    }

    // ============================================================
    // Generic content-backend dispatch (v0.4)
    // Routes to whichever registered backend claims target.kind.
    // ============================================================

    /**
     * Locate a literal string wherever it is stored, so the assistant can find
     * "static-looking" text (theme custom-post-type meta, taxonomy labels,
     * page content, site options) instead of guessing or dead-ending. Every
     * surface is scoped to the current user's capabilities, and each hit is
     * flagged `editable` so the model knows what it can fix from chat vs. what
     * lives in a protected/theme/option location (→ precise handoff).
     *
     * Deliberately never returns meta or option VALUES (they can hold secrets
     * or huge theme blobs) — only WHERE the match is and whether it's editable.
     */
    public static function find_text(array $args): array {
        global $wpdb;

        $query = trim((string) ($args['query'] ?? ''));
        if (mb_strlen($query) < 2) {
            return ['error' => 'Provide at least 2 characters of the text to locate.'];
        }
        $like = '%' . $wpdb->esc_like($query) . '%';
        $hits = [];

        // Post types the current user may edit (custom post types included).
        $skip_types = ['attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'nav_menu_item', 'custom_css', 'customize_changeset', 'revision', 'oembed_cache', 'user_request', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_subscription', 'product_variation'];
        $types = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
            if (in_array($pt->name, $skip_types, true)) {
                continue;
            }
            $cap = (is_object($pt->cap) && !empty($pt->cap->edit_posts)) ? $pt->cap->edit_posts : 'edit_posts';
            if (current_user_can($cap)) {
                $types[] = $pt->name;
            }
        }

        // 1) Post title / content / excerpt across editable types.
        if ($types) {
            $q = new \WP_Query([
                'post_type'      => $types,
                'post_status'    => ['publish', 'draft', 'private', 'pending', 'future'],
                's'              => $query,
                'posts_per_page' => 20,
                'no_found_rows'  => true,
            ]);
            foreach ($q->posts as $p) {
                $fields = [];
                if (mb_stripos((string) $p->post_title, $query) !== false)   { $fields[] = 'title'; }
                if (mb_stripos((string) $p->post_content, $query) !== false) { $fields[] = 'content'; }
                if (mb_stripos((string) $p->post_excerpt, $query) !== false) { $fields[] = 'excerpt'; }
                if (!$fields) { $fields[] = 'content'; }
                $type_obj  = get_post_type_object($p->post_type);
                $editable  = current_user_can(($type_obj->cap->edit_post ?? 'edit_post'), $p->ID);
                $hits[] = [
                    'where'    => sprintf('%s #%d “%s”', $p->post_type, $p->ID, get_the_title($p)),
                    'kind'     => 'wp_post',
                    'target'   => ['kind' => 'wp_post', 'id' => (int) $p->ID],
                    'fields'   => $fields,
                    'editable' => (bool) $editable,
                ];
            }
        }

        // 2) Taxonomy terms (name/description). A shared label lives here — fix
        //    once to fix every item using it.
        $terms = get_terms([
            'taxonomy'   => array_values(get_taxonomies(['public' => true])),
            'hide_empty' => false,
            'search'     => $query,
            'number'     => 20,
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $tax_obj  = get_taxonomy($t->taxonomy);
                $editable = current_user_can($tax_obj->cap->edit_terms ?? 'manage_categories');
                $hits[] = [
                    'where'    => sprintf('term “%s” (%s)', $t->name, $t->taxonomy),
                    'kind'     => 'wp_term',
                    'target'   => ['kind' => 'wp_term', 'term_id' => (int) $t->term_id, 'taxonomy' => $t->taxonomy],
                    'fields'   => ['name', 'description'],
                    'editable' => (bool) $editable,
                    'shared'   => true,
                ];
            }
        }

        // 3) Post meta — only for posts the user can edit. Protected (_-prefixed)
        //    keys are theme/plugin-managed and NOT editable from chat. Values are
        //    never returned.
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 40", $like)
        );
        $seen_meta = [];
        foreach ((array) $meta_rows as $r) {
            $pid  = (int) $r->post_id;
            $key  = (string) $r->meta_key;
            $dedup = $pid . '|' . $key;
            if (isset($seen_meta[$dedup])) {
                continue;
            }
            $seen_meta[$dedup] = true;
            $ptype = get_post_type($pid);
            if (!$ptype) {
                continue;
            }
            $type_obj = get_post_type_object($ptype);
            if (!current_user_can(($type_obj->cap->edit_post ?? 'edit_post'), $pid)) {
                continue; // don't disclose meta of posts the user can't edit
            }
            $protected = is_protected_meta($key, 'post');
            $hits[] = [
                'where'    => sprintf('post #%d (%s) meta[%s]', $pid, $ptype, $key),
                'kind'     => 'wp_post_meta',
                'target'   => ['kind' => 'wp_post_meta', 'post_id' => $pid, 'key' => $key],
                'fields'   => ['value'],
                'editable' => !$protected,
                'note'     => $protected ? 'Protected theme/plugin field — not editable from chat; edit in wp-admin.' : null,
            ];
        }

        // 4) Site options — admins only, names only (values can hold secrets),
        //    sensitive names skipped. Reported as a LOCATION, not editable here.
        if (current_user_can('manage_options')) {
            $opt_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT LIKE %s LIMIT 20",
                    $like,
                    $wpdb->esc_like('_transient') . '%'
                )
            );
            foreach ((array) $opt_rows as $r) {
                $name = (string) $r->option_name;
                if (preg_match('/(secret|token|_key|apikey|api_key|password|passwd|auth|nonce|salt|session|_transient|cron)/i', $name)) {
                    continue;
                }
                $hits[] = [
                    'where'    => sprintf('site option “%s”', $name),
                    'kind'     => 'option',
                    'target'   => null,
                    'editable' => false,
                    'note'     => 'Stored in a site/theme setting — edit in the relevant Settings screen, not from chat.',
                ];
            }
        }

        // 5) Custom backends may store content outside WordPress's own tables
        //    (static HTML files, a page-builder blob, an external store). Any
        //    backend that implements the optional search() method participates
        //    here, so find_text stays universal as a site adds capabilities via
        //    the chatadmin_content_backends filter — no core change needed.
        foreach (ContentRouter::backends() as $backend) {
            if (!method_exists($backend, 'search')) {
                continue;
            }
            try {
                $backend_hits = $backend->search($query);
            } catch (\Throwable $e) {
                continue; // a misbehaving backend must never break the search
            }
            if (!is_array($backend_hits)) {
                continue;
            }
            foreach ($backend_hits as $h) {
                if (!is_array($h) || empty($h['kind'])) {
                    continue;
                }
                $target = is_array($h['target'] ?? null) ? $h['target'] : [];
                // Honour site-disabled kinds + the user's role: if they can't
                // edit this kind, still show WHERE the text is but never mark it
                // editable from chat.
                if (self::check_kind_access((string) $h['kind'], $target) !== null) {
                    $h['editable'] = false;
                }
                $hits[] = $h;
            }
        }

        $editable_count = count(array_filter($hits, static fn($h) => !empty($h['editable'])));

        return [
            'query'          => $query,
            'count'          => count($hits),
            'editable_count' => $editable_count,
            'hits'           => $hits,
            'guidance'       => $hits
                ? 'Fix editable hits with preview_content_change → apply_content_change on the given target. If a hit has shared:true (a taxonomy term), editing that one term fixes every item using it — prefer that. For hits with editable:false, tell the user exactly where the text lives and hand off with get_admin_url; do NOT claim you changed it.'
                : 'The text was not found in any editable content, post meta, term, or (for admins) site option. It may be hard-coded in a theme template/file or an external source — tell the user where to look and hand off with get_admin_url.',
        ];
    }

    public static function list_content_blocks(array $args): array {
        $kind = (string) ($args['kind'] ?? '');
        if ($kind === '') {
            return [
                'error'           => 'kind is required.',
                'available_kinds' => ContentRouter::all_kinds(),
            ];
        }
        $backend = ContentRouter::for_kind($kind);
        if (!$backend) {
            return [
                'error'           => "No backend registered for kind: $kind",
                'available_kinds' => ContentRouter::all_kinds(),
            ];
        }
        $sub_args = is_array($args['args'] ?? null) ? $args['args'] : [];
        // Gate reads the same way preview/apply are gated: a caller may only
        // list a kind they're allowed to edit for the given target. Without
        // this, wp_post_meta listing would disclose ALL meta (incl. protected
        // and plugin-private keys) of any post_id, even ones the user can't edit.
        if ($err = self::check_kind_access($kind, $sub_args)) {
            return $err;
        }
        return $backend->list_items($kind, $sub_args);
    }

    public static function preview_content_change(array $args): array {
        $target = is_array($args['target'] ?? null) ? $args['target'] : [];
        $field  = (string) ($args['field'] ?? '');
        $value  = $args['value'] ?? null;
        $kind   = (string) ($target['kind'] ?? '');
        if ($kind === '' || $field === '') {
            return ['error' => 'target.kind and field are required.'];
        }
        if ($err = self::check_kind_access($kind, $target)) {
            return $err;
        }
        $backend = ContentRouter::for_kind($kind);
        if (!$backend) {
            return [
                'error'           => "No backend registered for kind: $kind",
                'available_kinds' => ContentRouter::all_kinds(),
            ];
        }
        // Record that a preview ran for this target in the current user turn,
        // so a later apply can prove it followed a real preview (finding #2).
        // No-op off the LLM path (no request context).
        self::record_pending(self::content_target_key($kind, $target, $field));
        return $backend->preview($target, $field, $value);
    }

    public static function apply_content_change(array $args): array {
        $target       = is_array($args['target'] ?? null) ? $args['target'] : [];
        $field        = (string) ($args['field'] ?? '');
        $value        = $args['value'] ?? null;
        $confirmation = (string) ($args['confirmation'] ?? '');
        $kind         = (string) ($target['kind'] ?? '');
        if ($kind === '' || $field === '') {
            return ['error' => 'target.kind and field are required.'];
        }
        if ($err = self::check_kind_access($kind, $target)) {
            return $err;
        }
        $backend = ContentRouter::for_kind($kind);
        if (!$backend) {
            return [
                'error'           => "No backend registered for kind: $kind",
                'available_kinds' => ContentRouter::all_kinds(),
            ];
        }
        // On the LLM path, an apply must follow a preview from an EARLIER user
        // turn (finding #2). Off that path (trusted callers) fall through to the
        // backend's own phrase check, unchanged.
        if (self::request_conversation() !== '' && empty($args['_confirmed'])) {
            // Consent is the user's own latest message, not the model-authored
            // `confirmation` arg (which prompt injection could supply), plus a
            // preview recorded in a strictly earlier turn.
            $confirmed = ContentConfirmation::is_confirmed(self::request_user_message())
                && self::consume_pending(self::content_target_key($kind, $target, $field));
            if (!$confirmed) {
                return [
                    'needs_confirmation' => true,
                    'code'    => 'needs_preview',
                    'message' => 'Call preview_content_change to show this change and ask the user to confirm in their next message, then call apply_content_change again with their confirmation.',
                ];
            }
        }
        return $backend->apply($target, $field, $value, $confirmation);
    }

    /**
     * Two-layer access check, run before the backend dispatch on every
     * preview / apply:
     *   1. Site policy — the kind must NOT be in the chatadmin_disabled_kinds
     *      site option (set by admins via the onboarding BackendsCard).
     *   2. WP role — the current user must hold the WP capability the
     *      kind requires (resolved via kind_required_cap()).
     *
     * Returns null when the user may proceed, or an `['error' => …]`
     * array shaped for the LLM (with a `code` hint) when refused.
     */
    private static function check_kind_access(string $kind, array $target): ?array {
        $disabled = Onboarding::get_site_disabled_kinds();
        if (in_array($kind, $disabled, true)) {
            return [
                'error' => 'This content kind is disabled site-wide. Site admin can re-enable it from ChatAdmin onboarding / Settings.',
                'code'  => 'kind_disabled_site',
                'kind'  => $kind,
            ];
        }
        if (!self::user_can_edit_kind($kind, $target)) {
            $cap = self::kind_required_cap($kind, $target);
            return [
                'error' => "Your WordPress role doesn't permit editing this content kind. The kind '$kind' requires the capability '$cap'.",
                'code'  => 'kind_role_restricted',
                'kind'  => $kind,
                'required_cap' => $cap,
            ];
        }
        return null;
    }

    public static function list_orders(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $limit  = max(1, min((int) ($args['limit'] ?? 10), 50));
        $status = $args['status'] ?? '';
        $search = $args['search'] ?? '';
        $since  = $args['since_date'] ?? '';

        $query = [
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ];

        if ($status && $status !== 'any') {
            $query['status'] = ['wc-' . self::unprefixed_status((string) $status)];
        }
        if ($search) {
            $query['s'] = $search;
        }
        if ($since) {
            $query['date_created'] = '>=' . $since;
        }

        $orders = wc_get_orders($query);
        return [
            'count'  => count($orders),
            'orders' => array_map([__CLASS__, 'summarize'], $orders),
        ];
    }

    public static function get_order(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        return self::detail($order);
    }

    // ---- confirmation gating (audit finding #2, approach B) --------------
    //
    // A mutating tool proceeds only when consent is proven. On the chat/LLM
    // path (a request context is set) that means: a whitelisted confirmation
    // phrase AND a pending record for the target, minted by a preview /
    // needs_confirmation in a STRICTLY earlier user turn — which content the
    // model merely *read* (prompt injection) cannot fabricate. Direct-action
    // REST routes pass `_confirmed` (the click is consent); direct/programmatic
    // callers with no request context keep the phrase-only contract.

    private static function request_conversation(): string {
        return (string) (self::$request_context['conversation_id'] ?? '');
    }

    private static function request_turn(): int {
        return (int) (self::$request_context['turn'] ?? 0);
    }

    /**
     * The user's actual latest chat message on the LLM path (empty off it).
     * Consent for a mutating apply must be a confirmation the *user* typed
     * here — never a model-authored `confirmation` argument, which content the
     * model merely read (prompt injection) could otherwise supply.
     */
    private static function request_user_message(): string {
        return (string) (self::$request_context['user_message'] ?? '');
    }

    private static function record_pending(string $target_key): void {
        PendingConfirmation::record(self::request_conversation(), $target_key, self::request_turn());
    }

    private static function consume_pending(string $target_key): bool {
        return PendingConfirmation::consume(self::request_conversation(), $target_key, self::request_turn());
    }

    /** Stable key binding a confirmation to a specific content edit. */
    private static function content_target_key(string $kind, array $target, string $field): string {
        return 'content:' . md5((string) wp_json_encode([$kind, $target, $field]));
    }

    /**
     * Order-mutation confirmation gate. Returns true to proceed.
     *   - `_confirmed` (a direct-action REST click) is consent.
     *   - Off the LLM path (no request context) the phrase whitelist alone
     *     applies — preserving the direct/programmatic contract.
     *   - On the LLM path, require a whitelisted phrase AND a pending record
     *     for this target minted in an EARLIER user turn; otherwise (re)record
     *     the pending entry and refuse. This binds consent to a real, separate
     *     user turn that prompt-injected content cannot fabricate.
     */
    private static function mutation_confirm_gate(array $args, string $target_key): bool {
        if (!empty($args['_confirmed'])) {
            return true;
        }
        if (self::request_conversation() === '') {
            // Off the LLM path (direct/programmatic callers): the model-supplied
            // phrase is the only signal, and the caller is trusted.
            return ContentConfirmation::is_confirmed((string) ($args['confirmation'] ?? ''));
        }
        // On the LLM path, consent must be a confirmation the USER actually
        // typed this turn — not the model's `confirmation` argument — AND a
        // preview/needs_confirmation must have run in a strictly earlier turn.
        $user_confirmed = ContentConfirmation::is_confirmed(self::request_user_message());
        if ($user_confirmed && self::consume_pending($target_key)) {
            return true;
        }
        self::record_pending($target_key);
        return false;
    }

    public static function update_order_status(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        $status = self::unprefixed_status((string) ($args['status'] ?? ''));
        if (!$status) {
            return ['error' => 'Status is required.'];
        }
        $valid = array_keys(wc_get_order_statuses());
        if (!in_array('wc-' . $status, $valid, true)) {
            return ['error' => 'Unknown status: ' . $status, 'available_statuses' => $valid];
        }
        $note = isset($args['note']) ? (string) $args['note'] : '';
        // Confirm before mutating — a status change can email the customer.
        if (!self::mutation_confirm_gate($args, 'order:' . $order->get_id() . ':status')) {
            return [
                'needs_confirmation' => true,
                'order_id'    => $order->get_id(),
                'from_status' => self::unprefixed_status($order->get_status()),
                'to_status'   => $status,
                'note'        => $note !== '' ? $note : null,
                'message'     => 'Tell the user you will change order #' . $order->get_id() . ' to "' . $status . '" (this may notify the customer by email) and ask them to confirm. Then call update_order_status again with their confirmation phrase in `confirmation`.',
            ];
        }
        $order->update_status($status, $note);
        return [
            'ok'         => true,
            'order_id'   => $order->get_id(),
            'new_status' => $status,
            'note_added' => $note !== '',
        ];
    }

    public static function add_order_note(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        $note = (string) ($args['note'] ?? '');
        if (!$note) {
            return ['error' => 'Note text is required.'];
        }
        $customer_visible = !empty($args['customer_visible']);
        // A private note is internal and low-risk, so it runs straight away.
        // A customer-visible note is emailed to the customer — confirm first.
        if ($customer_visible && !self::mutation_confirm_gate($args, 'order:' . $order->get_id() . ':note')) {
            return [
                'needs_confirmation' => true,
                'order_id'         => $order->get_id(),
                'customer_visible' => true,
                'note'             => $note,
                'message'          => 'This note will be EMAILED to the customer of order #' . $order->get_id() . '. Show them the note text, ask them to confirm, then call add_order_note again with their confirmation phrase in `confirmation`.',
            ];
        }
        $note_id = $order->add_order_note($note, $customer_visible);
        return [
            'ok'               => true,
            'order_id'         => $order->get_id(),
            'note_id'          => $note_id,
            'customer_visible' => $customer_visible,
        ];
    }

    public static function get_admin_url(array $args): array {
        $resource = (string) ($args['resource'] ?? '');
        $id       = (int) ($args['id'] ?? 0);
        $admin    = admin_url();

        switch ($resource) {
            case 'order':
                if (!$id) {
                    return ['error' => 'id required for resource=order.'];
                }
                $order = function_exists('wc_get_order') ? wc_get_order($id) : null;
                if ($order && method_exists($order, 'get_edit_order_url')) {
                    // HPOS-aware.
                    return ['url' => $order->get_edit_order_url(), 'resource' => 'order', 'id' => $id];
                }
                return ['url' => admin_url('post.php?post=' . $id . '&action=edit'), 'resource' => 'order', 'id' => $id];

            case 'orders_list':
                return ['url' => admin_url('admin.php?page=wc-orders'), 'resource' => 'orders_list'];

            case 'post':
                if (!$id) {
                    return ['error' => 'id required for resource=post.'];
                }
                return ['url' => admin_url('post.php?post=' . $id . '&action=edit'), 'resource' => 'post', 'id' => $id];

            case 'pages_list':
                return ['url' => admin_url('edit.php?post_type=page'), 'resource' => 'pages_list'];

            case 'user':
                if (!$id) {
                    return ['error' => 'id required for resource=user.'];
                }
                return ['url' => admin_url('user-edit.php?user_id=' . $id), 'resource' => 'user', 'id' => $id];

            case 'users_list':
                return ['url' => admin_url('users.php'), 'resource' => 'users_list'];

            case 'comments':
                // Moderation queue. Optional id deep-links to one comment's row.
                return $id
                    ? ['url' => admin_url('comment.php?action=editcomment&c=' . $id), 'resource' => 'comments', 'id' => $id]
                    : ['url' => admin_url('edit-comments.php'), 'resource' => 'comments'];

            case 'site_health':
                // For maintenance / broken-link / 404 / update requests there is
                // no tool — hand the user the right diagnostics screen.
                return ['url' => admin_url('site-health.php'), 'resource' => 'site_health'];

            case 'plugins':
                return ['url' => admin_url('plugins.php'), 'resource' => 'plugins'];

            case 'dashboard':
                return ['url' => $admin, 'resource' => 'dashboard'];
        }

        return ['error' => "Unknown resource: $resource", 'allowed' => ['order', 'orders_list', 'post', 'pages_list', 'user', 'users_list', 'comments', 'site_health', 'plugins', 'dashboard']];
    }

    public static function find_customer_orders(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $query = trim((string) ($args['query'] ?? ''));
        if (!$query) {
            return ['error' => 'Query is required.'];
        }

        $orders = wc_get_orders([
            'limit'    => 20,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'objects',
            's'        => $query,
        ]);

        if (empty($orders) && filter_var($query, FILTER_VALIDATE_EMAIL)) {
            $orders = wc_get_orders([
                'limit'           => 20,
                'orderby'         => 'date',
                'order'           => 'DESC',
                'return'          => 'objects',
                'billing_email'   => $query,
            ]);
        }

        return [
            'query'  => $query,
            'count'  => count($orders),
            'orders' => array_map([__CLASS__, 'summarize'], $orders),
        ];
    }

    // ============================================================
    // Order actions — resend emails & run plugin order actions.
    //
    // Mirrors WooCommerce's "Order actions" meta box
    // (WC_Meta_Box_Order_Actions): the same `woocommerce_order_actions`
    // filter feeds the list, and triggering replicates core's save()
    // switch so built-in emails AND plugin actions (e.g. PW Gift Cards
    // "Resend gift cards") fire identically — without ChatAdmin needing to
    // know about any specific plugin.
    // ============================================================

    /**
     * The order-action slugs available for this order: WC's three
     * built-ins plus whatever plugins register via the same filter the
     * admin meta box uses. Returns a list of {action, label}.
     */
    public static function order_actions_for(\WC_Order $order): array {
        $defaults = [
            'send_order_details'              => __('Email invoice / order details to customer', 'chatadmin'),
            'send_order_details_admin'        => __('Resend new order notification (to admin)', 'chatadmin'),
            'regenerate_download_permissions' => __('Regenerate download permissions', 'chatadmin'),
        ];
        $actions = apply_filters('woocommerce_order_actions', $defaults, $order); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
        $out = [];
        foreach ((array) $actions as $slug => $label) {
            $out[] = [
                'action' => (string) $slug,
                'label'  => (string) (is_array($label) ? ($label['name'] ?? $slug) : $label),
            ];
        }
        return $out;
    }

    public static function list_order_actions(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        return [
            'order_id' => $order->get_id(),
            'actions'  => self::order_actions_for($order),
        ];
    }

    public static function trigger_order_action(array $args): array {
        self::require_wc();
        if ($err = self::require_order_access()) {
            return $err;
        }
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        // WC slugifies the action the same way before dispatch; match that
        // so a slug from order_actions_for() round-trips exactly.
        $action = sanitize_title((string) ($args['action'] ?? ''));
        if ($action === '') {
            return ['error' => 'action is required.'];
        }

        $available = self::order_actions_for($order);
        $slugs     = array_column($available, 'action');
        if (!in_array($action, $slugs, true)) {
            return [
                'error'             => "Unknown order action: $action",
                'available_actions' => $available,
            ];
        }

        if (!function_exists('WC') || !WC()->mailer()) {
            return ['error' => 'WooCommerce mailer is unavailable on this site.'];
        }

        $label = '';
        foreach ($available as $a) {
            if ($a['action'] === $action) {
                $label = $a['label'];
                break;
            }
        }

        // Order actions send emails / fire plugin side-effects — confirm first.
        if (!self::mutation_confirm_gate($args, 'order:' . $order->get_id() . ':action:' . $action)) {
            return [
                'needs_confirmation' => true,
                'order_id' => $order->get_id(),
                'action'   => $action,
                'label'    => $label,
                'message'  => 'This will run "' . ($label !== '' ? $label : $action) . '" on order #' . $order->get_id() . ' (it sends an email / fires the plugin action). Confirm with the user, then call trigger_order_action again with their confirmation phrase in `confirmation`.',
            ];
        }

        // Replicates WC_Meta_Box_Order_Actions::save(): built-in emails are
        // sent directly; everything else dispatches the plugin hook.
        switch ($action) {
            case 'send_order_details':
                do_action('woocommerce_before_resend_order_emails', $order, 'customer'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
                WC()->payment_gateways();
                WC()->shipping();
                WC()->mailer()->customer_invoice($order);
                $order->add_order_note(__('Order details manually re-sent to customer via ChatAdmin.', 'chatadmin'), false, true);
                do_action('woocommerce_after_resend_order_email', $order, 'customer'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
                break;

            case 'send_order_details_admin':
                do_action('woocommerce_before_resend_order_emails', $order, 'new_order'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
                WC()->payment_gateways();
                WC()->shipping();
                WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id(), $order);
                do_action('woocommerce_after_resend_order_email', $order, 'new_order'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
                break;

            case 'regenerate_download_permissions':
                $data_store = \WC_Data_Store::load('customer-download');
                $data_store->delete_by_order_id($order->get_id());
                wc_downloadable_product_permissions($order->get_id(), true);
                break;

            default:
                // Custom plugin action (e.g. PW Gift Cards resend). The plugin
                // hooked on this is responsible for sending its own email and
                // adding its own order note.
                do_action('woocommerce_order_action_' . $action, $order); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking a WooCommerce core hook, not defining ours
                break;
        }

        return [
            'ok'       => true,
            'order_id' => $order->get_id(),
            'action'   => $action,
            'label'    => $label,
        ];
    }

    /**
     * Site traffic summary. Dispatches to whichever analytics plugin is
     * detected (AnalyticsRouter::pick()). Mirrors the content-backend
     * dispatch pattern: ChatAdmin doesn't know about any specific analytics
     * plugin — the router walks the registered providers (plus any added
     * via the `chatadmin_analytics_providers` filter) and returns the first
     * available one. Returns a no-provider error (not a fatal) when none
     * is detected so the assistant can tell the user instead of dead-ending.
     */
    public static function get_traffic_summary(array $args): array {
        $provider = AnalyticsRouter::pick();
        if (!$provider) {
            return [
                'error'    => 'No analytics plugin detected on this site.',
                'detected' => AnalyticsRouter::detected(),
            ];
        }
        $summary = $provider->traffic_summary($args);
        // Hand the human-readable provider name back so the assistant can
        // attribute the numbers ("per Jetpack Stats, …") without guessing.
        if (is_array($summary) && !isset($summary['provider_label'])) {
            $summary['provider_label'] = $provider->display_name();
        }
        return $summary;
    }

    /**
     * Read-only SEO / AI-SEO audit. Delegates to ChatAdmin\Seo, which also owns
     * the robots.txt filter + /llms.txt route that the fixes rely on. The
     * fixes themselves run through the seo_setting / seo_meta content kinds
     * (preview_content_change / apply_content_change), not a tool here.
     */
    public static function seo_audit(array $args): array {
        return Seo::audit();
    }

    // ============================================================
    // Content creation — draft posts/pages with images + taxonomy.
    // ============================================================

    /** Existing taxonomy terms, so the assistant can suggest reuse. */
    public static function list_taxonomy_terms(array $args): array {
        $only = isset($args['taxonomy']) ? sanitize_key((string) $args['taxonomy']) : '';
        $taxes = $only !== ''
            ? array_filter([get_taxonomy($only) ?: null])
            : get_taxonomies(['public' => true], 'objects');

        $out = [];
        foreach ($taxes as $tax) {
            if (in_array($tax->name, ['nav_menu', 'link_category', 'post_format'], true)) {
                continue;
            }
            $terms = get_terms(['taxonomy' => $tax->name, 'hide_empty' => false, 'number' => 50, 'orderby' => 'count', 'order' => 'DESC']);
            $items = [];
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $items[] = ['name' => $t->name, 'slug' => $t->slug, 'count' => (int) $t->count];
                }
            }
            $out[$tax->name] = [
                'label'    => $tax->labels->name ?? $tax->name,
                'is_empty' => count($items) === 0,
                'terms'    => $items,
            ];
        }
        return ['taxonomies' => $out];
    }

    /** Create a draft post or page. */
    public static function create_content(array $args): array {
        $requested = (string) ($args['post_type'] ?? 'post');
        $post_type = in_array($requested, ['post', 'page'], true) ? $requested : 'post';

        $type_obj = get_post_type_object($post_type);
        $cap      = $type_obj->cap->edit_posts ?? 'edit_posts';
        if (!current_user_can($cap)) {
            return ['error' => "You don't have permission to create a {$post_type} on this site."];
        }

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            return ['error' => 'A title is required.'];
        }

        $content = self::build_post_content(
            (string) ($args['content'] ?? ''),
            is_array($args['image_ids'] ?? null) ? array_map('intval', $args['image_ids']) : []
        );

        $post_id = wp_insert_post([
            'post_type'    => $post_type,
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => (string) ($args['excerpt'] ?? ''),
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            return ['error' => is_wp_error($post_id) ? $post_id->get_error_message() : 'Could not create the draft.'];
        }

        $applied = ['categories' => [], 'tags' => [], 'created_terms' => []];

        // Categories / tags — posts only (pages are not taxonomy-bearing).
        if ($post_type === 'post') {
            if (!empty($args['categories']) && is_array($args['categories'])) {
                $ids = [];
                foreach ($args['categories'] as $name) {
                    $name = trim((string) $name);
                    if ($name === '') {
                        continue;
                    }
                    $existing = term_exists($name, 'category');
                    $id = $existing ? (int) (is_array($existing) ? $existing['term_id'] : $existing) : (int) wp_create_category($name);
                    if ($id) {
                        $ids[] = $id;
                        $applied['categories'][] = $name;
                        if (!$existing) {
                            $applied['created_terms'][] = "category:{$name}";
                        }
                    }
                }
                if ($ids) {
                    wp_set_post_categories($post_id, $ids);
                }
            }
            if (!empty($args['tags']) && is_array($args['tags'])) {
                $names = array_values(array_filter(array_map(fn($t) => trim((string) $t), $args['tags'])));
                if ($names) {
                    wp_set_post_terms($post_id, $names, 'post_tag'); // creates missing tags
                    $applied['tags'] = $names;
                }
            }
        }

        // Featured image
        $featured = (int) ($args['featured_image'] ?? 0);
        if ($featured > 0 && get_post($featured)) {
            set_post_thumbnail($post_id, $featured);
        }

        // SEO meta (best-effort; surfaces a note if no SEO plugin)
        $seo_note = null;
        foreach (['seo_title' => 'seo_title', 'seo_description' => 'meta_description'] as $arg => $field) {
            if (!empty($args[$arg])) {
                $res = Seo::set_post_seo($post_id, $field, (string) $args[$arg]);
                if (!empty($res['error'])) {
                    $seo_note = $res['error'];
                }
            }
        }

        return [
            'ok'          => true,
            'post_id'     => $post_id,
            'post_type'   => $post_type,
            'status'      => 'draft',
            'title'       => $title,
            'edit_url'    => get_edit_post_link($post_id, 'raw'),
            'preview_url' => get_preview_post_link($post_id),
            'applied'     => $applied,
            'seo_note'    => $seo_note,
            'next'        => 'Show the user the draft + preview link, then ask whether to publish. Publish only via publish_content with their confirmation.',
        ];
    }

    /** Publish a draft created via create_content. Requires confirmation. */
    public static function publish_content(array $args): array {
        $post_id = (int) ($args['post_id'] ?? 0);
        $post    = $post_id ? get_post($post_id) : null;
        if (!$post) {
            return ['error' => 'Post not found.'];
        }
        // Publishing makes a draft public — gate it like other mutations. On the
        // LLM path this also requires the confirmation to arrive in a turn AFTER
        // the draft was surfaced (finding #2); direct callers keep phrase-only.
        if (!self::mutation_confirm_gate($args, 'publish:' . $post_id)) {
            return [
                'error'              => 'Not confirmed — ask the user to confirm publishing in their next message, then call publish_content again with their phrase.',
                'needs_confirmation' => true,
            ];
        }
        $type_obj = get_post_type_object($post->post_type);
        $cap      = $type_obj->cap->publish_posts ?? 'publish_posts';
        if (!current_user_can($cap, $post_id)) {
            return ['error' => "You don't have permission to publish this {$post->post_type}."];
        }
        $res = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        if (is_wp_error($res)) {
            return ['error' => $res->get_error_message()];
        }
        return [
            'ok'      => true,
            'post_id' => $post_id,
            'status'  => 'publish',
            'url'     => get_permalink($post_id),
        ];
    }

    /**
     * Build post_content from user/LLM content plus appended images. Plain
     * text / Markdown-ish input is wrapped into paragraph blocks; content that
     * already contains HTML tags is kept verbatim. Images become image blocks.
     */
    private static function build_post_content(string $content, array $image_ids): string {
        $content = trim($content);
        $body = '';
        if ($content !== '') {
            if (strpos($content, '<') !== false) {
                $body = $content; // already HTML / block markup
            } else {
                foreach (preg_split('/\n{2,}/', $content) as $para) {
                    $para = trim($para);
                    if ($para === '') {
                        continue;
                    }
                    $para = str_replace("\n", "<br>", esc_html($para));
                    $body .= "<!-- wp:paragraph -->\n<p>{$para}</p>\n<!-- /wp:paragraph -->\n\n";
                }
            }
        }
        foreach ($image_ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || !get_post($id)) {
                continue;
            }
            $url = wp_get_attachment_url($id);
            $alt = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
            if (!$url) {
                continue;
            }
            $body .= sprintf(
                "<!-- wp:image {\"id\":%d} -->\n<figure class=\"wp-block-image\"><img src=\"%s\" alt=\"%s\" class=\"wp-image-%d\"/></figure>\n<!-- /wp:image -->\n\n",
                $id,
                esc_url($url),
                esc_attr($alt),
                $id
            );
        }
        return trim($body);
    }

    /** Compact order representation. */
    public static function summarize(\WC_Order $order): array {
        return [
            'id'         => $order->get_id(),
            'number'     => $order->get_order_number(),
            'status'     => $order->get_status(),
            'date'       => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
            'total'      => (float) $order->get_total(),
            'currency'   => $order->get_currency(),
            'customer'   => trim($order->get_formatted_billing_full_name()),
            'email'      => $order->get_billing_email(),
            'item_names' => array_values(array_map(static fn($item) => $item->get_name(), $order->get_items())),
        ];
    }

    /** Full order representation including notes. */
    public static function detail(\WC_Order $order): array {
        $base = self::summarize($order);
        $base['line_items'] = [];
        foreach ($order->get_items() as $item) {
            $base['line_items'][] = [
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total'    => (float) $item->get_total(),
                'product_id' => $item->get_product_id(),
            ];
        }
        $base['notes'] = [];
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        foreach ($notes as $note) {
            $base['notes'][] = [
                'id'               => $note->id,
                'date'             => $note->date_created ? $note->date_created->date('c') : null,
                'content'          => $note->content,
                'customer_visible' => (bool) ($note->customer_note ?? false),
                'added_by'         => $note->added_by,
            ];
        }
        return $base;
    }
}

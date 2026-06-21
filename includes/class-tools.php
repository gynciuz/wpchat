<?php
/**
 * WPChat order tools.
 *
 * Each tool returns plain arrays that get JSON-encoded for the model.
 * Implementations call WC PHP functions directly — no REST roundtrips.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Tools {

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
                'description' => 'Change an order\'s status. Optionally append a private note explaining the change.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer'],
                        'status'   => ['type' => 'string', 'description' => 'Status slug without wc- prefix (e.g. "completed", "panaudotas").'],
                        'note'     => ['type' => 'string', 'description' => 'Optional private note to add along with the status change.'],
                    ],
                    'required' => ['order_id', 'status'],
                ],
            ],
            [
                'name'        => 'add_order_note',
                'description' => 'Append a note to an order. Private notes are visible only to admins; customer-visible notes are emailed to the customer.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id'         => ['type' => 'integer'],
                        'note'             => ['type' => 'string'],
                        'customer_visible' => ['type' => 'boolean', 'description' => 'If true, send the note to the customer via email. Default false (private).'],
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
                'description' => 'Run an order action on a single order — this is how you RESEND emails (order invoice / details, new-order notification) and run plugin actions like resending gift-card coupons. The `action` must be a slug returned by list_order_actions; if you don\'t know it, call list_order_actions first. Executes the action the same way clicking it in the WooCommerce "Order actions" box would (sends the email, fires the plugin hook) and records a note on the order. Only trigger an action the user explicitly asked for.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'WooCommerce order ID.'],
                        'action'   => ['type' => 'string', 'description' => 'Action slug from list_order_actions, e.g. "send_order_details" (email invoice to customer) or a plugin slug like "send_gift_cards" / "pwgc_resend_gift_cards".'],
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
                        'resource' => ['type' => 'string', 'description' => 'One of: order, orders_list, post, pages_list, user, users_list, dashboard.'],
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
                'name'        => 'list_content_blocks',
                'description' => 'List content items of a given kind. Available kinds depend on which backends are registered on this site (see the system prompt). Common kinds: wp_post, wp_page_slug, wp_post_meta, wp_term. Sites may add custom kinds (e.g. team_member).',
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
                            'description' => 'Kind-specific reference to the item. Must include `kind` and the fields the kind requires (e.g. {kind: "wp_post", id: 123}, {kind: "wp_page_slug", slug: "apie-mus"}, {kind: "team_member", name: "Nesar"}).',
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
            // Backends are pulled via apply_filters('wpchat_content_backends').
            'list_content_blocks'   => [__CLASS__, 'list_content_blocks'],
            'preview_content_change' => [__CLASS__, 'preview_content_change'],
            'apply_content_change'  => [__CLASS__, 'apply_content_change'],
        ];
    }

    public static function require_wc(): void {
        if (!function_exists('wc_get_orders')) {
            throw new \RuntimeException('WooCommerce is not active.');
        }
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

        // Custom kinds — let the backend declare its own required cap.
        $backend = ContentRouter::for_kind($kind);
        if ($backend && method_exists($backend, 'required_cap')) {
            $cap = (string) $backend->required_cap($kind);
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
        return $backend->apply($target, $field, $value, $confirmation);
    }

    /**
     * Two-layer access check, run before the backend dispatch on every
     * preview / apply:
     *   1. Site policy — the kind must NOT be in the wpchat_disabled_kinds
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
                'error' => 'This content kind is disabled site-wide. Site admin can re-enable it from WPChat onboarding / Settings.',
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
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        return self::detail($order);
    }

    public static function update_order_status(array $args): array {
        self::require_wc();
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
        $order = wc_get_order((int) ($args['order_id'] ?? 0));
        if (!$order) {
            return ['error' => 'Order not found.'];
        }
        $note = (string) ($args['note'] ?? '');
        if (!$note) {
            return ['error' => 'Note text is required.'];
        }
        $customer_visible = !empty($args['customer_visible']);
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

            case 'dashboard':
                return ['url' => $admin, 'resource' => 'dashboard'];
        }

        return ['error' => "Unknown resource: $resource", 'allowed' => ['order', 'orders_list', 'post', 'pages_list', 'user', 'users_list', 'dashboard']];
    }

    public static function find_customer_orders(array $args): array {
        self::require_wc();
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
    // "Resend gift cards") fire identically — without WPChat needing to
    // know about any specific plugin.
    // ============================================================

    /**
     * The order-action slugs available for this order: WC's three
     * built-ins plus whatever plugins register via the same filter the
     * admin meta box uses. Returns a list of {action, label}.
     */
    public static function order_actions_for(\WC_Order $order): array {
        $defaults = [
            'send_order_details'              => __('Email invoice / order details to customer', 'wpchat'),
            'send_order_details_admin'        => __('Resend new order notification (to admin)', 'wpchat'),
            'regenerate_download_permissions' => __('Regenerate download permissions', 'wpchat'),
        ];
        $actions = apply_filters('woocommerce_order_actions', $defaults, $order);
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

        // Replicates WC_Meta_Box_Order_Actions::save(): built-in emails are
        // sent directly; everything else dispatches the plugin hook.
        switch ($action) {
            case 'send_order_details':
                do_action('woocommerce_before_resend_order_emails', $order, 'customer');
                WC()->payment_gateways();
                WC()->shipping();
                WC()->mailer()->customer_invoice($order);
                $order->add_order_note(__('Order details manually re-sent to customer via WPChat.', 'wpchat'), false, true);
                do_action('woocommerce_after_resend_order_email', $order, 'customer');
                break;

            case 'send_order_details_admin':
                do_action('woocommerce_before_resend_order_emails', $order, 'new_order');
                WC()->payment_gateways();
                WC()->shipping();
                WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id(), $order);
                do_action('woocommerce_after_resend_order_email', $order, 'new_order');
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
                do_action('woocommerce_order_action_' . $action, $order);
                break;
        }

        $label = '';
        foreach ($available as $a) {
            if ($a['action'] === $action) {
                $label = $a['label'];
                break;
            }
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
     * dispatch pattern: WPChat doesn't know about any specific analytics
     * plugin — the router walks the registered providers (plus any added
     * via the `wpchat_analytics_providers` filter) and returns the first
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
     * Read-only SEO / AI-SEO audit. Delegates to WPChat\Seo, which also owns
     * the robots.txt filter + /llms.txt route that the fixes rely on. The
     * fixes themselves run through the seo_setting / seo_meta content kinds
     * (preview_content_change / apply_content_change), not a tool here.
     */
    public static function seo_audit(array $args): array {
        return Seo::audit();
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

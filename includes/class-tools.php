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
                'name'        => 'list_team_members',
                'description' => 'List team members (barbers) across ALL static pages where they appear (the homepage AND the dedicated /musu-meistrai page). Returns each occurrence with file path, name, and current role/subtitle. Read-only.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'        => 'preview_team_member_role_change',
                'description' => 'PREVIEW (no write) a change to a team member\'s role/subtitle. Searches BOTH the homepage and the team page; returns every file/occurrence that would change. ALWAYS call this BEFORE apply_team_member_role_change. Use Lithuanian for the new role (the website is Lithuanian).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string', 'description' => 'Full or partial name of the team member.'],
                        'new_role' => ['type' => 'string', 'description' => 'The proposed new role/subtitle text in Lithuanian.'],
                    ],
                    'required' => ['name', 'new_role'],
                ],
            ],
            [
                'name'        => 'apply_team_member_role_change',
                'description' => 'APPLY the role change. Updates ALL matching occurrences across the static pages (homepage + team page). REQUIRES explicit user confirmation: pass the confirmation phrase the user typed into `confirmation`. Only "yes", "confirm", "apply", "do it", "taip", "patvirtinu", "да", "tak", "ok" (case-insensitive) are accepted. If the user has NOT confirmed in the chat, call preview first instead.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'         => ['type' => 'string'],
                        'new_role'     => ['type' => 'string'],
                        'confirmation' => ['type' => 'string', 'description' => 'Verbatim confirmation phrase from the user.'],
                    ],
                    'required' => ['name', 'new_role', 'confirmation'],
                ],
            ],
        ];
    }

    /** Map name → callable. */
    public static function implementations(): array {
        return [
            'list_orders'          => [__CLASS__, 'list_orders'],
            'get_order'            => [__CLASS__, 'get_order'],
            'update_order_status'  => [__CLASS__, 'update_order_status'],
            'add_order_note'       => [__CLASS__, 'add_order_note'],
            'find_customer_orders' => [__CLASS__, 'find_customer_orders'],
        ];
    }

    public static function require_wc(): void {
        if (!function_exists('wc_get_orders')) {
            throw new \RuntimeException('WooCommerce is not active.');
        }
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
            $query['status'] = ['wc-' . ltrim($status, 'wc-')];
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
        $status = ltrim((string) ($args['status'] ?? ''), 'wc-');
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

<?php
/**
 * ChatAdmin content-backend infrastructure.
 *
 * The plugin ships with a default WPContentBackend that handles standard
 * WordPress content (posts, pages-by-slug, post meta, terms). A site can
 * register additional backends for non-standard content via the
 * `chatadmin_content_backends` filter, each declaring which `kind` slugs it
 * handles. ChatAdmin auto-detects them: their kinds appear in the assistant's
 * system prompt, are capability-gated, and route through the standard
 * preview→apply flow, with no change to the core plugin.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A content backend handles read + write for one or more content "kinds".
 *
 * A target is a kind-specific reference, e.g.
 *   { kind: "wp_post",        id: 123 }
 *   { kind: "wp_page_slug",   slug: "apie-mus" }
 *   { kind: "wp_post_meta",   post_id: 123, key: "voucher_pdf_cy" }
 *   { kind: "wp_term",        term_id: 5 }
 *   { kind: "<custom_kind>",  <custom keys a site backend defines> }
 *
 * Optional method a backend MAY implement (checked via method_exists, so it is
 * fully back-compatible):
 *   public function search(string $query): array
 *     Return find_text-shaped hits for content this backend stores, so the
 *     `find_text` tool can locate it too. Each hit:
 *       ['where' => string, 'kind' => string, 'target' => array|null,
 *        'editable' => bool, 'note' => ?string]
 *     Scope results to the current user's capabilities.
 */
interface ContentBackend {

    /** @return string[] List of `kind` slugs this backend can handle. */
    public function handled_kinds(): array;

    /**
     * Human-readable description of what each kind represents and which
     * fields can be changed on it. Returned to the LLM via the system
     * prompt so it knows what's available. Keyed by kind slug.
     *
     * Shape: ['kind_slug' => ['description' => string, 'fields' => string[]]]
     */
    public function describe_kinds(): array;

    /**
     * List items of a given kind. Optional filters in $args (kind-specific).
     * Returns ['items' => array, ...] — each item should have enough info
     * for the user to identify it ({id, slug, title, ...}).
     */
    public function list_items(string $kind, array $args = []): array;

    /**
     * Read-only preview of a proposed change. Returns a diff structure:
     * ['matches' => [{location, old_value, new_value}, ...]].
     */
    public function preview(array $target, string $field, $value): array;

    /**
     * Apply the change. $confirmation MUST be a whitelisted affirmative
     * phrase ('yes', 'taip', 'да', ...); backends should reject otherwise.
     */
    public function apply(array $target, string $field, $value, string $confirmation): array;
}

/**
 * Confirmation-phrase whitelist for any LLM-callable write tool.
 * Centralised so all backends apply the same gate.
 */
class ContentConfirmation {

    /**
     * Accepted natural-language affirmatives across the supported languages
     * (EN / ES / FR / PT / HI / ZH / DE, plus LT / PL / RU — Russian is kept
     * supported though no longer featured in marketing).
     * Space-delimited languages are matched as exact tokens; we explicitly
     * reject negations first so a word that contains an affirmative as a
     * substring (e.g. "negerai" — "not okay") can't slip through. Mandarin
     * has no word boundaries, so its affirmatives/negations are matched as
     * substrings on short replies.
     */
    public static function is_confirmed(string $phrase): bool {
        $lc = mb_strtolower(trim($phrase), 'UTF-8');
        if ($lc === '') {
            return false;
        }

        $tokens = preg_split('/[\s,;!?.()]+/u', $lc, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Reject negations first. If any rejection word is present anywhere
        // in the input, treat as not-confirmed even if an affirmative is
        // also present — fail safe for "ne, taip" / "no, ok" / etc.
        $rejections = [
            'no', 'nope', 'cancel', 'not', "don't", 'dont',  // English
            'ne', 'negerai', 'atšaukti',        // Lithuanian
            'нет', 'не', 'отмена',              // Russian (kept)
            'nie',                              // Polish
            'non',                              // French
            'não', 'nao',                       // Portuguese
            'nein',                             // German
            'नहीं', 'रद्द',                     // Hindi (no / cancel)
        ];
        foreach ($tokens as $token) {
            if (in_array($token, $rejections, true)) {
                return false;
            }
        }
        // Mandarin negation / cancel — substring (no word boundaries).
        foreach (['取消', '不要', '否', '不'] as $needle) {
            if (mb_strpos($lc, $needle, 0, 'UTF-8') !== false) {
                return false;
            }
        }

        // Single-token affirmatives — exact word match, no substring.
        $allowed_words = [
            // English
            'yes', 'ok', 'okay', 'sure', 'confirm', 'apply',
            // Lithuanian
            'taip', 'gerai', 'sutinku', 'patvirtinu',
            // Russian (kept — still supported, just not featured)
            'да', 'хорошо', 'ок',
            // Polish
            'tak', 'dobrze',
            // Spanish
            'sí', 'si', 'vale', 'confirmar', 'confirmo', 'aplicar',
            // French
            'oui', 'confirmer', 'confirme', "d'accord",
            // Portuguese
            'sim',
            // German
            'ja', 'bestätigen', 'bestätige', 'einverstanden',
            // Hindi
            'हाँ', 'हां', 'ठीक', 'पुष्टि', 'जी',
        ];
        foreach ($tokens as $token) {
            if (in_array($token, $allowed_words, true)) {
                return true;
            }
        }

        // Mandarin affirmatives — substring on short replies (no word boundaries).
        if (mb_strlen($lc, 'UTF-8') <= 12) {
            foreach (['确认', '确定', '好的', '是的', '可以', '好', '是'] as $needle) {
                if (mb_strpos($lc, $needle, 0, 'UTF-8') !== false) {
                    return true;
                }
            }
        }

        // Multi-word affirmative phrases — substring is fine because
        // these are unambiguous.
        $allowed_phrases = ['do it', 'de acuerdo', "d'accord", 'está bem', 'esta bem'];
        foreach ($allowed_phrases as $needle) {
            if (mb_strlen($lc, 'UTF-8') <= 40 && strpos($lc, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Server-side proof that a mutating apply follows a real, *earlier* user turn
 * (audit finding #2, approach B). When a preview_* / needs_confirmation runs on
 * the LLM path, we record a pending entry keyed by conversation, stamped with
 * the current user-turn index. The matching apply may only proceed if a record
 * exists for the same target AND was created in a strictly earlier turn — which
 * prompt-injected content (which lives in tool results, not genuine user
 * messages, and cannot create a new user turn) can never satisfy. Consumed once.
 */
class PendingConfirmation {

    private const PREFIX = 'chatadmin_pending_';
    private const TTL    = 900; // 15 minutes

    public static function record(string $conversation, string $target_key, int $turn): void {
        if ($conversation === '') {
            return;
        }
        set_transient(self::PREFIX . md5($conversation), [
            'target' => $target_key,
            'turn'   => $turn,
        ], self::TTL);
    }

    public static function consume(string $conversation, string $target_key, int $current_turn): bool {
        if ($conversation === '') {
            return false;
        }
        $key     = self::PREFIX . md5($conversation);
        $pending = get_transient($key);
        if (!is_array($pending)
            || ($pending['target'] ?? null) !== $target_key
            || (int) ($pending['turn'] ?? PHP_INT_MAX) >= $current_turn) {
            return false;
        }
        delete_transient($key);
        return true;
    }
}

/**
 * Router: looks up the registered backends and dispatches calls to
 * whichever one claims the target's kind. WPContentBackend is registered
 * by default at priority 10 so user filters can prepend higher-priority
 * site-specific backends.
 */
class ContentRouter {

    /** @return ContentBackend[] */
    public static function backends(): array {
        $defaults = [new WPContentBackend()];
        $backends = apply_filters('chatadmin_content_backends', $defaults);
        // Defensive: drop anything that doesn't implement the interface.
        return array_values(array_filter($backends, static fn($b) => $b instanceof ContentBackend));
    }

    /** @return string[] All distinct kinds across all backends. */
    public static function all_kinds(): array {
        $kinds = [];
        foreach (self::backends() as $b) {
            foreach ($b->handled_kinds() as $k) {
                $kinds[$k] = true;
            }
        }
        return array_keys($kinds);
    }

    /** Merged describe_kinds() across all backends. Later wins on conflict. */
    public static function all_descriptions(): array {
        $out = [];
        foreach (self::backends() as $b) {
            foreach ($b->describe_kinds() as $kind => $desc) {
                $out[$kind] = $desc;
            }
        }
        return $out;
    }

    public static function for_kind(string $kind): ?ContentBackend {
        foreach (self::backends() as $b) {
            if (in_array($kind, $b->handled_kinds(), true)) {
                return $b;
            }
        }
        return null;
    }
}

/**
 * Default backend — works on any WordPress install.
 *
 * Handled kinds:
 *  - wp_post       : { id }                        → fields: title, content, excerpt, status
 *  - wp_page_slug  : { slug }                      → resolves to post, same fields as wp_post
 *  - wp_post_meta  : { post_id, key }              → fields: value
 *  - wp_term       : { term_id, taxonomy }         → fields: name, description
 */
class WPContentBackend implements ContentBackend {

    public function handled_kinds(): array {
        return ['wp_post', 'wp_page_slug', 'wp_post_meta', 'wp_term'];
    }

    public function describe_kinds(): array {
        return [
            'wp_post' => [
                'description' => 'Any WordPress post, page, OR custom-post-type item (e.g. a portfolio item, service, or other registered type) referenced by ID. Target shape: {kind: "wp_post", id: <int>}. To find one, list_content_blocks("wp_post", {post_type: "<type>" | "any", search: "<words>"}).',
                'fields'      => ['title', 'content', 'excerpt', 'status'],
            ],
            'wp_page_slug' => [
                'description' => 'A WordPress page or post referenced by its URL slug (e.g. "apie-mus"). Target shape: {kind: "wp_page_slug", slug: "<slug>"}.',
                'fields'      => ['title', 'content', 'excerpt', 'status'],
            ],
            'wp_post_meta' => [
                'description' => 'A single post meta value. Target shape: {kind: "wp_post_meta", post_id: <int>, key: "<meta_key>"}.',
                'fields'      => ['value'],
            ],
            'wp_term' => [
                'description' => 'A taxonomy term. Target shape: {kind: "wp_term", term_id: <int>, taxonomy: "<tax>"}.',
                'fields'      => ['name', 'description'],
            ],
        ];
    }

    /**
     * Registered post types the current user may edit — everything with an
     * admin UI, minus WordPress/WooCommerce system types (attachments, blocks,
     * templates, orders/coupons: those aren't free-text content and orders are
     * PII gated separately). Used to resolve post_type:"any" for discovery.
     *
     * @return string[]
     */
    private static function editable_post_types(): array {
        $skip = ['attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'nav_menu_item', 'custom_css', 'customize_changeset', 'revision', 'oembed_cache', 'user_request', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_subscription', 'product_variation'];
        $out  = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
            if (in_array($pt->name, $skip, true)) {
                continue;
            }
            $edit_cap = (is_object($pt->cap) && !empty($pt->cap->edit_posts)) ? $pt->cap->edit_posts : 'edit_posts';
            if (current_user_can($edit_cap)) {
                $out[] = $pt->name;
            }
        }
        return $out ?: ['post', 'page'];
    }

    public function list_items(string $kind, array $args = []): array {
        switch ($kind) {
            case 'wp_post':
            case 'wp_page_slug':
                $post_type = $args['post_type'] ?? ['post', 'page'];
                // "any" searches across every editable content type (custom post
                // types included) so theme/plugin content — team members,
                // portfolio, services — is discoverable, not just posts/pages.
                if ($post_type === 'any' || $post_type === '*') {
                    $post_type = self::editable_post_types();
                }
                $limit     = max(1, min((int) ($args['limit'] ?? 20), 100));
                $search    = (string) ($args['search'] ?? '');
                $query = new \WP_Query([
                    'post_type'      => $post_type,
                    'post_status'    => ['publish', 'draft', 'private'],
                    'posts_per_page' => $limit,
                    's'              => $search,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                ]);
                $items = [];
                foreach ($query->posts as $p) {
                    $items[] = [
                        'id'     => $p->ID,
                        'slug'   => $p->post_name,
                        'title'  => get_the_title($p),
                        'type'   => $p->post_type,
                        'status' => $p->post_status,
                        'edited' => $p->post_modified,
                    ];
                }
                return ['kind' => $kind, 'count' => count($items), 'items' => $items];

            case 'wp_post_meta':
                $post_id = (int) ($args['post_id'] ?? 0);
                if (!$post_id) {
                    return ['error' => 'post_id required to list meta.'];
                }
                $meta = get_post_meta($post_id);
                $items = [];
                foreach ($meta as $key => $values) {
                    $items[] = ['post_id' => $post_id, 'key' => $key, 'value' => maybe_unserialize($values[0] ?? '')];
                }
                return ['kind' => 'wp_post_meta', 'count' => count($items), 'items' => $items];

            case 'wp_term':
                $taxonomy = (string) ($args['taxonomy'] ?? 'category');
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'number'     => max(1, min((int) ($args['limit'] ?? 50), 200)),
                ]);
                if (is_wp_error($terms)) {
                    return ['error' => $terms->get_error_message()];
                }
                $items = [];
                foreach ($terms as $t) {
                    $items[] = ['term_id' => $t->term_id, 'taxonomy' => $taxonomy, 'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description];
                }
                return ['kind' => 'wp_term', 'count' => count($items), 'items' => $items];
        }

        return ['error' => "Unknown kind for WPContentBackend: $kind"];
    }

    public function preview(array $target, string $field, $value): array {
        $resolved = $this->resolve_target($target);
        if (isset($resolved['error'])) {
            return $resolved;
        }

        switch ($resolved['kind']) {
            case 'wp_post':
                if (!in_array($field, ['title', 'content', 'excerpt', 'status'], true)) {
                    return ['error' => "Unsupported field for wp_post: $field"];
                }
                $post = get_post($resolved['id']);
                $old  = match ($field) {
                    'title'   => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status'  => $post->post_status,
                };
                return [
                    'matches' => [[
                        'location'  => "wp_post #{$post->ID} ({$post->post_type}: {$post->post_name})",
                        'field'     => $field,
                        'old_value' => $old,
                        'new_value' => (string) $value,
                    ]],
                ];

            case 'wp_post_meta':
                $old = get_post_meta($resolved['post_id'], $resolved['key'], true);
                return [
                    'matches' => [[
                        'location'  => "post #{$resolved['post_id']} meta[{$resolved['key']}]",
                        'field'     => 'value',
                        'old_value' => $old,
                        'new_value' => $value,
                    ]],
                ];

            case 'wp_term':
                if (!in_array($field, ['name', 'description'], true)) {
                    return ['error' => "Unsupported field for wp_term: $field"];
                }
                $term = get_term($resolved['term_id'], $resolved['taxonomy']);
                if (is_wp_error($term) || !$term) {
                    return ['error' => 'Term not found.'];
                }
                $old = $field === 'name' ? $term->name : $term->description;
                return [
                    'matches' => [[
                        'location'  => "term #{$term->term_id} ({$term->taxonomy}: {$term->slug})",
                        'field'     => $field,
                        'old_value' => $old,
                        'new_value' => (string) $value,
                    ]],
                ];
        }

        return ['error' => 'Preview not implemented for kind: ' . $resolved['kind']];
    }

    public function apply(array $target, string $field, $value, string $confirmation): array {
        if (!ContentConfirmation::is_confirmed($confirmation)) {
            return [
                'error'                 => 'Confirmation required. Ask the user to type one of: yes, confirm, taip, patvirtinu, да, ok.',
                'received_confirmation' => $confirmation,
            ];
        }

        $resolved = $this->resolve_target($target);
        if (isset($resolved['error'])) {
            return $resolved;
        }

        switch ($resolved['kind']) {
            case 'wp_post':
                if (!in_array($field, ['title', 'content', 'excerpt', 'status'], true)) {
                    return ['error' => "Unsupported field for wp_post: $field"];
                }
                $update = ['ID' => $resolved['id']];
                $update[match ($field) {
                    'title'   => 'post_title',
                    'content' => 'post_content',
                    'excerpt' => 'post_excerpt',
                    'status'  => 'post_status',
                }] = $value;
                $result = wp_update_post($update, true);
                if (is_wp_error($result)) {
                    return ['error' => $result->get_error_message()];
                }
                return ['ok' => true, 'post_id' => $result, 'field' => $field];

            case 'wp_post_meta':
                $result = update_post_meta($resolved['post_id'], $resolved['key'], $value);
                return ['ok' => $result !== false, 'post_id' => $resolved['post_id'], 'key' => $resolved['key']];

            case 'wp_term':
                if (!in_array($field, ['name', 'description'], true)) {
                    return ['error' => "Unsupported field for wp_term: $field"];
                }
                $update = [$field => $value];
                $result = wp_update_term($resolved['term_id'], $resolved['taxonomy'], $update);
                if (is_wp_error($result)) {
                    return ['error' => $result->get_error_message()];
                }
                return ['ok' => true, 'term_id' => $resolved['term_id'], 'field' => $field];
        }

        return ['error' => 'Apply not implemented for kind: ' . $resolved['kind']];
    }

    /** Normalises wp_page_slug → wp_post; validates required fields. */
    private function resolve_target(array $target): array {
        $kind = $target['kind'] ?? '';
        if ($kind === 'wp_page_slug') {
            $slug = (string) ($target['slug'] ?? '');
            if (!$slug) {
                return ['error' => 'slug required for wp_page_slug.'];
            }
            $page = get_page_by_path($slug, OBJECT, ['post', 'page']);
            if (!$page) {
                return ['error' => "No post/page found with slug: $slug"];
            }
            return ['kind' => 'wp_post', 'id' => $page->ID];
        }

        if ($kind === 'wp_post') {
            $id = (int) ($target['id'] ?? 0);
            if (!$id || !get_post($id)) {
                return ['error' => 'Valid post id required for wp_post.'];
            }
            return ['kind' => 'wp_post', 'id' => $id];
        }

        if ($kind === 'wp_post_meta') {
            $post_id = (int) ($target['post_id'] ?? 0);
            $key     = (string) ($target['key'] ?? '');
            if (!$post_id || !$key) {
                return ['error' => 'post_id and key required for wp_post_meta.'];
            }
            // Refuse protected (_-prefixed) meta — WordPress hides these from the
            // custom-fields UI for the same reason: themes/plugins trust them
            // (e.g. _wp_page_template, _thumbnail_id). Chat edits are limited to
            // ordinary, user-facing meta.
            if (is_protected_meta($key, 'post')) {
                return ['error' => "Refusing to write protected meta key '$key'. Only non-protected (non-underscore) meta keys are editable."];
            }
            return ['kind' => 'wp_post_meta', 'post_id' => $post_id, 'key' => $key];
        }

        if ($kind === 'wp_term') {
            $term_id  = (int) ($target['term_id'] ?? 0);
            $taxonomy = (string) ($target['taxonomy'] ?? '');
            if (!$term_id || !$taxonomy) {
                return ['error' => 'term_id and taxonomy required for wp_term.'];
            }
            return ['kind' => 'wp_term', 'term_id' => $term_id, 'taxonomy' => $taxonomy];
        }

        return ['error' => "WPContentBackend doesn't handle kind: $kind"];
    }
}

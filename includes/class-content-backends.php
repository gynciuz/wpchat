<?php
/**
 * WPChat content-backend infrastructure.
 *
 * The plugin ships with a default WPContentBackend that handles standard
 * WordPress content (posts, pages-by-slug, post meta, terms). Sites with
 * non-standard content (e.g. Gentleman's Empire's static-HTML team blocks)
 * register additional backends via the `wpchat_content_backends` filter,
 * each declaring which `kind` slugs they handle.
 *
 * @package WPChat
 */

namespace WPChat;

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
 *   { kind: "team_member",    name: "Nesar" }
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
     * Accepted natural-language affirmatives across LT / RU / PL / EN.
     * Single-token matches; we explicitly reject negations first so words
     * that contain an affirmative as a substring (e.g. "negerai" — "not
     * okay" — would have substring-matched "gerai") can't slip through.
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
        $rejections = ['no', 'ne', 'нет', 'не', 'nie', 'cancel', 'atšaukti', 'negerai'];
        foreach ($tokens as $token) {
            if (in_array($token, $rejections, true)) {
                return false;
            }
        }

        // Single-token affirmatives — exact word match, no substring.
        $allowed_words = [
            // English
            'yes', 'ok', 'okay', 'sure', 'confirm', 'apply',
            // Lithuanian
            'taip', 'gerai', 'sutinku', 'patvirtinu',
            // Russian
            'да', 'хорошо', 'ок',
            // Polish
            'tak', 'dobrze',
        ];
        foreach ($tokens as $token) {
            if (in_array($token, $allowed_words, true)) {
                return true;
            }
        }

        // Multi-word affirmative phrases — substring is fine because
        // these are unambiguous.
        $allowed_phrases = ['do it'];
        foreach ($allowed_phrases as $needle) {
            if (mb_strlen($lc, 'UTF-8') <= 40 && strpos($lc, $needle) !== false) {
                return true;
            }
        }

        return false;
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
        $backends = apply_filters('wpchat_content_backends', $defaults);
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
                'description' => 'A WordPress post or page referenced by ID. Target shape: {kind: "wp_post", id: <int>}.',
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

    public function list_items(string $kind, array $args = []): array {
        switch ($kind) {
            case 'wp_post':
            case 'wp_page_slug':
                $post_type = $args['post_type'] ?? ['post', 'page'];
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

<?php
/**
 * SEO / AI-SEO (AEO/GEO) audit + fixes.
 *
 * Two parts:
 *  - ChatAdmin\Seo: read-only audit + the WordPress-side infrastructure the
 *    fixes rely on (a `robots_txt` filter that opens the site to AI answer-
 *    engine crawlers, and a virtual `/llms.txt` route served from an option).
 *  - ChatAdmin\SeoBackend: a ContentBackend (registered via the
 *    `chatadmin_content_backends` filter) exposing two editable kinds so SEO
 *    changes flow through the same preview → confirm tools, Confirm/Cancel UI,
 *    and capability gating as every other content edit:
 *      - `seo_setting` — site-level options (indexing, permalinks, AI crawler
 *        access, llms.txt, site title/tagline). Requires `manage_options`.
 *      - `seo_meta`    — per-post SEO title / meta description via the active
 *        SEO plugin (Yoast / Rank Math / SEOPress; AIOSEO → admin handoff).
 *
 * Only settings WordPress can actually change live are fixable here. The rest
 * of the SEO playbook (hosting, Core Web Vitals, schema beyond the plugin,
 * keyword research, GSC/GA4, backlinks) is advisory — the assistant reports
 * it from the audit and hands the user a deep link, never a dead end.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Seo {

    const LLMS_OPTION        = 'chatadmin_llms_txt';
    const AI_CRAWLERS_OPTION = 'chatadmin_seo_allow_ai_crawlers';

    /** AI answer-engine crawlers we open the site to (per the AEO guide). */
    const AI_BOTS = ['GPTBot', 'ClaudeBot', 'PerplexityBot'];

    public function __construct() {
        add_filter('robots_txt', [__CLASS__, 'filter_robots_txt'], 20, 2);
        add_action('init', [__CLASS__, 'maybe_serve_llms_txt']);
        add_filter('chatadmin_content_backends', [__CLASS__, 'register_backend']);
    }

    /** Add our SEO backend to the content-backend registry. */
    public static function register_backend(array $backends): array {
        $backends[] = new SeoBackend();
        return $backends;
    }

    // ====================================================================
    // AI crawler access (robots.txt)
    // ====================================================================

    public static function ai_crawlers_enabled(): bool {
        return (bool) get_option(self::AI_CRAWLERS_OPTION, false);
    }

    /**
     * Append Allow rules for the named AI crawlers to WordPress's virtual
     * robots.txt. Only fires when the operator has opted in via the fix.
     * Note: a *physical* robots.txt file at the site root overrides this —
     * the audit flags that case.
     */
    public static function filter_robots_txt($output, $public): string {
        if (!self::ai_crawlers_enabled()) {
            return (string) $output;
        }
        $block = "\n# ChatAdmin: allow AI answer-engine crawlers (AEO/GEO)\n";
        foreach (self::AI_BOTS as $bot) {
            $block .= "User-agent: {$bot}\nAllow: /\n\n";
        }
        return rtrim((string) $output) . "\n" . $block;
    }

    // ====================================================================
    // llms.txt — virtual route served from an option (no filesystem write)
    // ====================================================================

    public static function maybe_serve_llms_txt(): void {
        $path = trim((string) wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH), '/');
        if ($path !== 'llms.txt') {
            return;
        }
        $content = self::llms_txt();
        if ($content === '') {
            return; // Nothing published — let WordPress 404 normally.
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        // Admin-authored plain-text body served as text/plain — HTML-escaping
        // would corrupt it (turn & < > into entities). Not an HTML context.
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function llms_txt(): string {
        return (string) get_option(self::LLMS_OPTION, '');
    }

    /** Build a curated llms.txt from the site's pages + recent posts. */
    public static function generate_llms_txt(): string {
        $name = html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = html_entity_decode((string) get_bloginfo('description'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = ["# {$name}", ''];
        if ($desc !== '') {
            $lines[] = "> {$desc}";
            $lines[] = '';
        }

        $pages = get_pages(['sort_column' => 'menu_order,post_title', 'number' => 25]) ?: [];
        if ($pages) {
            $lines[] = '## Pages';
            foreach ($pages as $p) {
                $lines[] = sprintf('- [%s](%s)', wp_strip_all_tags($p->post_title), get_permalink($p));
            }
            $lines[] = '';
        }

        $posts = get_posts(['numberposts' => 25, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']) ?: [];
        if ($posts) {
            $lines[] = '## Posts';
            foreach ($posts as $p) {
                $excerpt = wp_strip_all_tags(get_the_excerpt($p));
                $excerpt = $excerpt !== '' ? ': ' . wp_trim_words($excerpt, 20, '…') : '';
                $lines[] = sprintf('- [%s](%s)%s', wp_strip_all_tags($p->post_title), get_permalink($p), $excerpt);
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    // ====================================================================
    // SEO plugin detection + per-post meta
    // ====================================================================

    /** @return array{id:string,name:string} */
    public static function detect_seo_plugin(): array {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return ['id' => 'yoast', 'name' => 'Yoast SEO'];
        }
        if (class_exists('RankMath') || defined('RANK_MATH_VERSION')) {
            return ['id' => 'rankmath', 'name' => 'Rank Math'];
        }
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
            return ['id' => 'aioseo', 'name' => 'All in One SEO'];
        }
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_init')) {
            return ['id' => 'seopress', 'name' => 'SEOPress'];
        }
        return ['id' => 'none', 'name' => '(no SEO plugin detected)'];
    }

    /** Post-meta keys for the plugins that store SEO meta as post meta. */
    private static function meta_keys(string $plugin): ?array {
        switch ($plugin) {
            case 'yoast':    return ['seo_title' => '_yoast_wpseo_title',    'meta_description' => '_yoast_wpseo_metadesc'];
            case 'rankmath': return ['seo_title' => 'rank_math_title',      'meta_description' => 'rank_math_description'];
            case 'seopress': return ['seo_title' => '_seopress_titles_title','meta_description' => '_seopress_titles_desc'];
            default:         return null; // AIOSEO uses a custom table; none = unsupported.
        }
    }

    /** @return array{plugin:string,seo_title:string,meta_description:string} */
    public static function get_post_seo(int $post_id): array {
        $plugin = self::detect_seo_plugin()['id'];
        $keys   = self::meta_keys($plugin);
        if ($keys) {
            return [
                'plugin'           => $plugin,
                'seo_title'        => (string) get_post_meta($post_id, $keys['seo_title'], true),
                'meta_description' => (string) get_post_meta($post_id, $keys['meta_description'], true),
            ];
        }
        if ($plugin === 'aioseo') {
            global $wpdb;
            $table = $wpdb->prefix . 'aioseo_posts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads AIOSEO's own table for a single post; live data.
            $row   = $wpdb->get_row($wpdb->prepare('SELECT title, description FROM %i WHERE post_id = %d', $table, $post_id), ARRAY_A);
            return [
                'plugin'           => 'aioseo',
                'seo_title'        => (string) ($row['title'] ?? ''),
                'meta_description' => (string) ($row['description'] ?? ''),
            ];
        }
        return ['plugin' => 'none', 'seo_title' => '', 'meta_description' => ''];
    }

    /**
     * Write a per-post SEO field. Supports the post-meta plugins directly;
     * AIOSEO and no-plugin cases return an admin handoff instead of a silent
     * failure.
     *
     * @param string $field 'seo_title' | 'meta_description'
     */
    public static function set_post_seo(int $post_id, string $field, string $value): array {
        if (!in_array($field, ['seo_title', 'meta_description'], true)) {
            return ['error' => "Unknown SEO meta field: {$field}"];
        }
        $plugin = self::detect_seo_plugin()['id'];
        $keys   = self::meta_keys($plugin);
        if ($keys) {
            update_post_meta($post_id, $keys[$field], $value);
            return ['ok' => true, 'plugin' => $plugin, 'field' => $field];
        }
        if ($plugin === 'aioseo') {
            return [
                'error'     => 'All in One SEO stores SEO meta in its own table — set it in the AIOSEO box on the post.',
                'admin_url' => get_edit_post_link($post_id, 'raw'),
            ];
        }
        return ['error' => 'No SEO plugin is active. Install Yoast, Rank Math, or SEOPress to set per-post SEO title / meta description.'];
    }

    // ====================================================================
    // Audit
    // ====================================================================

    /**
     * Read-only SEO/AEO audit. Each check is {status: ok|warn|fail|info,
     * value, recommendation, fixable}. `fixable` flags items the chat can
     * change live via the seo_setting / seo_meta kinds.
     */
    public static function audit(): array {
        $checks = [];

        // Indexing allowed
        $public = (int) get_option('blog_public', 1) === 1;
        $checks['search_engine_visibility'] = [
            'status'         => $public ? 'ok' : 'fail',
            'value'          => $public ? 'Search engines allowed' : 'Discouraged (blog_public = 0)',
            'recommendation' => $public ? '' : 'Allow indexing: Settings → Reading, uncheck "Discourage search engines". Fixable here.',
            'fixable'        => !$public,
        ];

        // Permalinks
        $permalink = (string) get_option('permalink_structure', '');
        $is_postname = strpos($permalink, '%postname%') !== false;
        $checks['permalinks'] = [
            'status'         => $is_postname ? 'ok' : 'warn',
            'value'          => $permalink !== '' ? $permalink : 'Plain (?p=123)',
            'recommendation' => $is_postname ? '' : 'Set permalinks to "Post name" (/%postname%/). Fixable here.',
            'fixable'        => !$is_postname,
        ];

        // SSL / HTTPS
        $https = strpos((string) home_url(), 'https://') === 0;
        $checks['https'] = [
            'status'         => $https ? 'ok' : 'fail',
            'value'          => $https ? 'HTTPS' : 'HTTP (not secure)',
            'recommendation' => $https ? '' : 'Install an SSL certificate (most hosts offer free) and serve over HTTPS — a confirmed ranking factor.',
            'fixable'        => false,
        ];

        // SEO plugin
        $plugin = self::detect_seo_plugin();
        $checks['seo_plugin'] = [
            'status'         => $plugin['id'] !== 'none' ? 'ok' : 'warn',
            'value'          => $plugin['name'],
            'recommendation' => $plugin['id'] !== 'none' ? '' : 'Install ONE SEO plugin (Yoast, Rank Math, AIOSEO, or SEOPress) for titles, meta, sitemaps, and schema.',
            'fixable'        => false,
        ];

        // XML sitemap (core or plugin)
        $sitemap_url   = home_url('/wp-sitemap.xml');
        $core_sitemaps = function_exists('wp_sitemaps_get_server') && get_option('blog_public');
        $checks['sitemap'] = [
            'status'         => $core_sitemaps ? 'ok' : 'info',
            'value'          => $core_sitemaps ? $sitemap_url . ' (or your SEO plugin\'s sitemap)' : 'Check your SEO plugin\'s sitemap',
            'recommendation' => 'Submit your sitemap to Google Search Console and Bing Webmaster Tools (the chat can\'t do this for you).',
            'fixable'        => false,
        ];

        // AI crawlers (robots.txt)
        $physical_robots = file_exists(ABSPATH . 'robots.txt');
        $ai_on           = self::ai_crawlers_enabled();
        $checks['ai_crawlers'] = [
            'status'         => $ai_on ? 'ok' : 'warn',
            'value'          => $ai_on
                ? 'GPTBot/ClaudeBot/PerplexityBot allowed via ChatAdmin'
                : 'No explicit AI-crawler allow rules',
            'recommendation' => $physical_robots
                ? 'A physical robots.txt exists at the site root and overrides WordPress\'s virtual one — edit that file to allow GPTBot/ClaudeBot/PerplexityBot.'
                : ($ai_on ? '' : 'Allow AI answer-engine crawlers so the site can be cited by ChatGPT/Claude/Perplexity. Fixable here.'),
            'fixable'        => !$ai_on && !$physical_robots,
        ];

        // llms.txt
        $has_llms = self::llms_txt() !== '';
        $checks['llms_txt'] = [
            'status'         => $has_llms ? 'ok' : 'warn',
            'value'          => $has_llms ? home_url('/llms.txt') : 'Not published (404)',
            'recommendation' => $has_llms ? '' : 'Publish an llms.txt — a curated Markdown index for AI crawlers. Fixable here (auto-generated from your pages/posts).',
            'fixable'        => !$has_llms,
        ];

        // Site identity
        $title   = (string) get_bloginfo('name');
        $tagline = (string) get_bloginfo('description');
        $default_tagline = $tagline === '' || $tagline === 'Just another WordPress site';
        $checks['site_identity'] = [
            'status'         => ($title !== '' && !$default_tagline) ? 'ok' : 'warn',
            'value'          => sprintf('Title: "%s" · Tagline: "%s"', $title, $tagline),
            'recommendation' => $default_tagline ? 'Set a real site tagline (the default "Just another WordPress site" hurts trust). Fixable here.' : '',
            'fixable'        => $title === '' || $default_tagline,
        ];

        // WP 7.0 readiness — PHP / MySQL
        global $wpdb;
        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $db_ver = $wpdb->db_version();
        $checks['php_mysql'] = [
            'status'         => $php_ok ? 'ok' : 'fail',
            'value'          => sprintf('PHP %s · DB %s', PHP_VERSION, $db_ver),
            'recommendation' => $php_ok ? '' : 'Upgrade to PHP 7.4+ (8.3+ recommended) for WordPress 7.0.',
            'fixable'        => false,
        ];

        // Summary tallies
        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
        foreach ($checks as $c) {
            $summary[$c['status']] = ($summary[$c['status']] ?? 0) + 1;
        }

        return [
            'site'       => untrailingslashit(home_url()),
            'seo_plugin' => $plugin['name'],
            'summary'    => $summary,
            'checks'     => $checks,
            'note'       => 'Items with fixable=true can be changed here via a preview → confirm. The rest (hosting, Core Web Vitals, schema, keyword research, GSC/GA4 submission, backlinks) are advisory — use the SEO admin pages.',
        ];
    }
}

/**
 * Content backend exposing SEO settings + per-post SEO meta as editable
 * kinds, so they reuse the preview_content_change / apply_content_change
 * tools and their Confirm/Cancel UI.
 */
class SeoBackend implements ContentBackend {

    const SETTING_FIELDS = ['search_engine_visibility', 'permalink_structure', 'ai_crawlers', 'llms_txt', 'site_title', 'tagline'];
    const META_FIELDS    = ['seo_title', 'meta_description'];

    public function handled_kinds(): array {
        return ['seo_setting', 'seo_meta'];
    }

    public function describe_kinds(): array {
        return [
            'seo_setting' => [
                'description' => 'Site-level SEO settings. target = {kind:"seo_setting"}. Set field to one of the listed settings. Values: search_engine_visibility=true/false (true = allow indexing); permalink_structure="/%postname%/"; ai_crawlers=true/false (allow GPTBot/ClaudeBot/PerplexityBot in robots.txt); llms_txt="generate" to auto-build from the site, or a Markdown string; site_title / tagline = strings.',
                'fields'      => self::SETTING_FIELDS,
            ],
            'seo_meta' => [
                'description' => 'Per-post SEO title / meta description via the active SEO plugin (Yoast, Rank Math, SEOPress). target = {kind:"seo_meta", post_id:123}. Keep seo_title under ~60 chars and meta_description ~150–160 chars.',
                'fields'      => self::META_FIELDS,
            ],
        ];
    }

    /** @return string capability required to edit the kind. */
    public function required_cap(string $kind, array $target = []): string {
        return $kind === 'seo_setting' ? 'manage_options' : 'edit_posts';
    }

    public function list_items(string $kind, array $args = []): array {
        if ($kind === 'seo_setting') {
            return [
                'kind'  => 'seo_setting',
                'items' => [
                    'search_engine_visibility' => (int) get_option('blog_public', 1) === 1,
                    'permalink_structure'      => (string) get_option('permalink_structure', ''),
                    'ai_crawlers'              => Seo::ai_crawlers_enabled(),
                    'llms_txt'                 => Seo::llms_txt() !== '' ? 'published' : 'not published',
                    'site_title'               => (string) get_bloginfo('name'),
                    'tagline'                  => (string) get_bloginfo('description'),
                ],
            ];
        }
        if ($kind === 'seo_meta') {
            $post_id = (int) ($args['post_id'] ?? 0);
            if ($post_id <= 0) {
                return ['error' => 'seo_meta requires a post_id in args.'];
            }
            return ['kind' => 'seo_meta', 'post_id' => $post_id] + Seo::get_post_seo($post_id);
        }
        return ['error' => "SeoBackend doesn't handle kind: {$kind}"];
    }

    public function preview(array $target, string $field, $value): array {
        $kind = (string) ($target['kind'] ?? '');
        [$old, $new, $err] = $this->resolve($kind, $target, $field, $value);
        if ($err) {
            return ['error' => $err];
        }
        return [
            'kind'    => $kind,
            'field'   => $field,
            'changes' => [[
                'location' => $this->location($kind, $target, $field),
                'old'      => $old,
                'new'      => $new,
            ]],
        ];
    }

    public function apply(array $target, string $field, $value, string $confirmation): array {
        if (!ContentConfirmation::is_confirmed($confirmation)) {
            return ['error' => 'Not confirmed. Pass the user\'s confirmation phrase (e.g. "yes" / "taip").'];
        }
        $kind = (string) ($target['kind'] ?? '');
        [$old, $new, $err] = $this->resolve($kind, $target, $field, $value);
        if ($err) {
            return ['error' => $err];
        }

        if ($kind === 'seo_setting') {
            return $this->apply_setting($field, $value, $new);
        }
        if ($kind === 'seo_meta') {
            $post_id = (int) ($target['post_id'] ?? 0);
            return Seo::set_post_seo($post_id, $field, (string) $new) + ['old' => $old, 'new' => $new];
        }
        return ['error' => "SeoBackend doesn't handle kind: {$kind}"];
    }

    /**
     * Compute the (old, new, error) tuple for a change without writing.
     * Normalizes user-supplied values to what will actually be stored.
     *
     * @return array{0:mixed,1:mixed,2:?string}
     */
    private function resolve(string $kind, array $target, string $field, $value): array {
        if ($kind === 'seo_setting') {
            if (!in_array($field, self::SETTING_FIELDS, true)) {
                return [null, null, "Unknown seo_setting field: {$field}. Allowed: " . implode(', ', self::SETTING_FIELDS)];
            }
            switch ($field) {
                case 'search_engine_visibility':
                    return [
                        (int) get_option('blog_public', 1) === 1 ? 'allowed' : 'discouraged',
                        $this->truthy($value) ? 'allowed' : 'discouraged',
                        null,
                    ];
                case 'permalink_structure':
                    $new = (string) $value;
                    if ($new === '' || strpos($new, '%') === false) {
                        $new = '/%postname%/'; // sane default for any "post name"-ish request
                    }
                    return [(string) get_option('permalink_structure', '') ?: 'plain', $new, null];
                case 'ai_crawlers':
                    return [
                        Seo::ai_crawlers_enabled() ? 'allowed' : 'not set',
                        $this->truthy($value) ? 'allowed' : 'not set',
                        null,
                    ];
                case 'llms_txt':
                    $content = (is_string($value) && strtolower(trim($value)) !== 'generate' && trim($value) !== '')
                        ? (string) $value
                        : Seo::generate_llms_txt();
                    return [
                        Seo::llms_txt() !== '' ? Seo::llms_txt() : '(none)',
                        $content,
                        null,
                    ];
                case 'site_title':
                    return [(string) get_bloginfo('name'), (string) $value, null];
                case 'tagline':
                    return [(string) get_bloginfo('description'), (string) $value, null];
            }
        }
        if ($kind === 'seo_meta') {
            if (!in_array($field, self::META_FIELDS, true)) {
                return [null, null, "Unknown seo_meta field: {$field}. Allowed: " . implode(', ', self::META_FIELDS)];
            }
            $post_id = (int) ($target['post_id'] ?? 0);
            if ($post_id <= 0 || !get_post($post_id)) {
                return [null, null, 'seo_meta requires a valid post_id in the target.'];
            }
            $current = Seo::get_post_seo($post_id);
            return [(string) ($current[$field] ?? ''), (string) $value, null];
        }
        return [null, null, "SeoBackend doesn't handle kind: {$kind}"];
    }

    private function apply_setting(string $field, $value, $new): array {
        switch ($field) {
            case 'search_engine_visibility':
                update_option('blog_public', $this->truthy($value) ? 1 : 0);
                return ['ok' => true, 'field' => $field, 'new' => $new];
            case 'permalink_structure':
                global $wp_rewrite;
                update_option('permalink_structure', (string) $new);
                if ($wp_rewrite) {
                    $wp_rewrite->set_permalink_structure((string) $new);
                    $wp_rewrite->flush_rules(false);
                }
                return ['ok' => true, 'field' => $field, 'new' => $new];
            case 'ai_crawlers':
                update_option(Seo::AI_CRAWLERS_OPTION, $this->truthy($value));
                return ['ok' => true, 'field' => $field, 'new' => $new, 'robots_url' => home_url('/robots.txt')];
            case 'llms_txt':
                update_option(Seo::LLMS_OPTION, (string) $new);
                return ['ok' => true, 'field' => $field, 'url' => home_url('/llms.txt')];
            case 'site_title':
                update_option('blogname', (string) $new);
                return ['ok' => true, 'field' => $field, 'new' => $new];
            case 'tagline':
                update_option('blogdescription', (string) $new);
                return ['ok' => true, 'field' => $field, 'new' => $new];
        }
        return ['error' => "Unknown seo_setting field: {$field}"];
    }

    private function location(string $kind, array $target, string $field): string {
        if ($kind === 'seo_meta') {
            return sprintf('post #%d → %s', (int) ($target['post_id'] ?? 0), $field);
        }
        return "site SEO setting → {$field}";
    }

    /** Coerce JSON-ish booleans ("true"/"1"/true) to bool. */
    private function truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'taip', 'да', 'tak'], true);
        }
        return (bool) $value;
    }
}

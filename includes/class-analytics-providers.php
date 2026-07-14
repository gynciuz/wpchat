<?php
/**
 * Analytics provider router.
 *
 * Mirrors the v0.4 ContentBackend pattern: ship an interface, auto-detect
 * which host plugin can answer the query, dispatch via filter. The LLM's
 * `get_traffic_summary` tool calls AnalyticsRouter::pick() which walks
 * the registered providers and returns the first one whose host plugin
 * is active + auth is ready.
 *
 * Built-in providers (in detection-priority order): Site Kit,
 * Jetpack Stats, MonsterInsights, WP Statistics, Koko Analytics, Statify.
 *
 * v0.5.14 ships REAL data fetch for Jetpack Stats + WP Statistics + Koko
 * Analytics; the others ship as detection-only stubs that return
 * `integration_pending: true` so the LLM tells the user "Detected, full
 * integration coming next release" instead of dead-ending.
 *
 * Sites can register additional providers via the
 * `chatadmin_analytics_providers` filter — same shape as
 * `chatadmin_content_backends`.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

interface AnalyticsProvider {

    /** Stable slug ('jetpack-stats', 'site-kit', etc.). */
    public function name(): string;

    /** Human-readable label for assistant replies. */
    public function display_name(): string;

    /** Host plugin active + auth ready. Cheap class_exists / option check. */
    public function is_available(): bool;

    /**
     * Return normalized traffic summary, or an error-shaped array.
     *
     * Success shape:
     *   {
     *     provider: string,
     *     range: { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD' },
     *     users: int|null,
     *     sessions: int|null,
     *     page_views: int|null,
     *     top_pages: [{ path: string, views: int }, ...]   // may be []
     *     top_sources: [{ source: string, sessions: int }, ...]  // may be []
     *     integration_pending?: true  // detection-only stub
     *     note?: string
     *   }
     */
    public function traffic_summary(array $args): array;
}

class AnalyticsRouter {

    /** @return AnalyticsProvider[] In detection-priority order. */
    public static function providers(): array {
        $defaults = [
            new SiteKitAnalyticsProvider(),
            new JetpackStatsAnalyticsProvider(),
            new MonsterInsightsAnalyticsProvider(),
            new WPStatisticsAnalyticsProvider(),
            new KokoAnalyticsAnalyticsProvider(),
            new StatifyAnalyticsProvider(),
        ];
        $providers = apply_filters('chatadmin_analytics_providers', $defaults);
        return array_values(array_filter($providers, static fn($p) => $p instanceof AnalyticsProvider));
    }

    /** First provider whose host plugin is active + ready. */
    public static function pick(): ?AnalyticsProvider {
        foreach (self::providers() as $p) {
            if ($p->is_available()) {
                return $p;
            }
        }
        return null;
    }

    /** All detected providers (for the onboarding card to surface multiple). */
    public static function detected(): array {
        $out = [];
        foreach (self::providers() as $p) {
            if ($p->is_available()) {
                $out[] = ['name' => $p->name(), 'display_name' => $p->display_name()];
            }
        }
        return $out;
    }

    /** Convenience: range slug → ISO start/end dates. */
    public static function resolve_range(string $slug): array {
        $today = new \DateTimeImmutable('today', new \DateTimeZone(wp_timezone_string() ?: 'UTC'));
        switch ($slug) {
            case 'today':
                return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
            case 'yesterday':
                $y = $today->modify('-1 day');
                return ['start' => $y->format('Y-m-d'), 'end' => $y->format('Y-m-d')];
            case 'this_week':
                // Monday → today (locale-agnostic ISO week start)
                $weekday = (int) $today->format('N'); // 1 (Mon) – 7 (Sun)
                $start = $today->modify('-' . ($weekday - 1) . ' days');
                return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
            case 'last_30_days':
                $start = $today->modify('-29 days');
                return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
            case 'last_7_days':
            default:
                $start = $today->modify('-6 days');
                return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
        }
    }
}

// -----------------------------------------------------------------------
// Built-in providers
// -----------------------------------------------------------------------

/**
 * Google Site Kit — detection-only in v0.5.14. Site Kit's data API is
 * complex (the Modules\Analytics_4 class returns async-shaped results
 * and needs OAuth token plumbing). We detect it so the assistant can
 * say "Site Kit is connected — full analytics integration ships in the
 * next release" instead of pretending it doesn't exist.
 */
class SiteKitAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'site-kit'; }
    public function display_name(): string { return 'Google Site Kit'; }
    public function is_available(): bool {
        return class_exists('\Google\Site_Kit\Plugin');
    }
    public function traffic_summary(array $args): array {
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));
        return [
            'provider'            => $this->name(),
            'range'               => $range,
            'users'               => null,
            'sessions'            => null,
            'page_views'          => null,
            'top_pages'           => [],
            'top_sources'         => [],
            'integration_pending' => true,
            'note'                => 'Site Kit detected — full Analytics 4 Data API integration ships in the next ChatAdmin release.',
        ];
    }
}

/**
 * Jetpack Stats — uses the synchronous `stats_get_csv` helper that
 * Jetpack's Stats module exposes from modules/stats.php. Requires
 * Jetpack to be connected to a WordPress.com account.
 */
class JetpackStatsAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'jetpack-stats'; }
    public function display_name(): string { return 'Jetpack Stats'; }

    public function is_available(): bool {
        if (!class_exists('Jetpack')) {
            return false;
        }
        // Stats module active + Jetpack connected (proxy for connection
        // is the `jetpack_options.id` having a blog id).
        if (function_exists('Jetpack') && method_exists('Jetpack', 'is_module_active')) {
            if (!\Jetpack::is_module_active('stats')) {
                return false;
            }
        }
        return function_exists('stats_get_csv') || file_exists(WP_PLUGIN_DIR . '/jetpack/modules/stats.php');
    }

    public function traffic_summary(array $args): array {
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));

        // Ensure the helper is loaded — Jetpack only requires modules/stats.php
        // on the admin context, not on every REST hit.
        if (!function_exists('stats_get_csv')) {
            $stats_file = WP_PLUGIN_DIR . '/jetpack/modules/stats.php';
            if (file_exists($stats_file)) {
                require_once $stats_file;
            }
        }

        if (!function_exists('stats_get_csv')) {
            return $this->error('Jetpack Stats helper not loadable.', $range);
        }

        // Diff (in days) between start + end.
        $start = new \DateTimeImmutable($range['start']);
        $end   = new \DateTimeImmutable($range['end']);
        $days  = ((int) $end->diff($start)->days) + 1;

        // stats_get_csv signature: ($table, $args)
        // 'postviews' returns top posts by views. We use 'views' for the
        // total counts.
        $views_csv = stats_get_csv('views', ['days' => $days]);
        $top_posts = stats_get_csv('postviews', ['days' => $days, 'limit' => 5]);
        $refs      = stats_get_csv('referrers', ['days' => $days, 'limit' => 5]);

        // 'views' result is a list of {date, views, visitors} dicts. Sum
        // across days for the range total. Jetpack Stats does not split
        // sessions vs users the way GA does — visitors ≈ users, views ≈
        // page_views, sessions stays null.
        $users = 0;
        $page_views = 0;
        if (is_array($views_csv)) {
            foreach ($views_csv as $row) {
                $users      += (int) ($row['visitors'] ?? $row['unique_visitors'] ?? 0);
                $page_views += (int) ($row['views'] ?? 0);
            }
        }

        $top_pages = [];
        if (is_array($top_posts)) {
            foreach ($top_posts as $row) {
                $top_pages[] = [
                    'path'  => (string) ($row['post_permalink'] ?? $row['permalink'] ?? $row['post_title'] ?? '—'),
                    'views' => (int) ($row['views'] ?? 0),
                ];
            }
        }
        $top_sources = [];
        if (is_array($refs)) {
            foreach ($refs as $row) {
                $top_sources[] = [
                    'source'   => (string) ($row['name'] ?? $row['referrer'] ?? '—'),
                    'sessions' => (int) ($row['views'] ?? 0),
                ];
            }
        }

        return [
            'provider'    => $this->name(),
            'range'       => $range,
            'users'       => $users ?: null,
            'sessions'    => null,
            'page_views'  => $page_views ?: null,
            'top_pages'   => $top_pages,
            'top_sources' => $top_sources,
        ];
    }

    private function error(string $msg, array $range): array {
        return [
            'provider'   => $this->name(),
            'range'      => $range,
            'error'      => $msg,
        ];
    }
}

/**
 * MonsterInsights — detection-only in v0.5.14. The reporting API
 * varies meaningfully between the Lite + Pro plugins and we want to
 * lock that in with fixtures before claiming support.
 */
class MonsterInsightsAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'monsterinsights'; }
    public function display_name(): string { return 'MonsterInsights'; }
    public function is_available(): bool {
        return class_exists('MonsterInsights') || class_exists('MonsterInsights_Lite');
    }
    public function traffic_summary(array $args): array {
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));
        return [
            'provider'            => $this->name(),
            'range'               => $range,
            'users'               => null,
            'sessions'            => null,
            'page_views'          => null,
            'top_pages'           => [],
            'top_sources'         => [],
            'integration_pending' => true,
            'note'                => 'MonsterInsights detected — full reports API integration ships in the next ChatAdmin release.',
        ];
    }
}

/**
 * WP Statistics — direct DB query against its visitor / pages tables.
 * The plugin keeps everything in MySQL so we don't need any API call.
 */
class WPStatisticsAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'wp-statistics'; }
    public function display_name(): string { return 'WP Statistics'; }

    public function is_available(): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'statistics_visitor';
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    public function traffic_summary(array $args): array {
        global $wpdb;
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));

        $visitor_tbl = $wpdb->prefix . 'statistics_visitor';
        $pages_tbl   = $wpdb->prefix . 'statistics_pages';

        $users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT IFNULL(`ip`, `id`)) FROM `$visitor_tbl` WHERE `last_counter` BETWEEN %s AND %s",
            $range['start'],
            $range['end']
        ));
        $page_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(`count`), 0) FROM `$pages_tbl` WHERE `date` BETWEEN %s AND %s",
            $range['start'],
            $range['end']
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT `uri`, SUM(`count`) AS views FROM `$pages_tbl` WHERE `date` BETWEEN %s AND %s GROUP BY `uri` ORDER BY views DESC LIMIT 5",
            $range['start'],
            $range['end']
        ), ARRAY_A);
        $top_pages = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $top_pages[] = ['path' => (string) $r['uri'], 'views' => (int) $r['views']];
            }
        }

        $ref_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT `referred`, COUNT(*) AS visits FROM `$visitor_tbl` WHERE `last_counter` BETWEEN %s AND %s AND `referred` <> '' GROUP BY `referred` ORDER BY visits DESC LIMIT 5",
            $range['start'],
            $range['end']
        ), ARRAY_A);
        $top_sources = [];
        if (is_array($ref_rows)) {
            foreach ($ref_rows as $r) {
                $host = wp_parse_url((string) $r['referred'], PHP_URL_HOST) ?: (string) $r['referred'];
                $top_sources[] = ['source' => (string) $host, 'sessions' => (int) $r['visits']];
            }
        }

        return [
            'provider'    => $this->name(),
            'range'       => $range,
            'users'       => $users ?: null,
            'sessions'    => null,
            'page_views'  => $page_views ?: null,
            'top_pages'   => $top_pages,
            'top_sources' => $top_sources,
        ];
    }
}

/**
 * Koko Analytics — privacy-friendly local stats. Stores hits in its
 * own table; we read directly.
 */
class KokoAnalyticsAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'koko-analytics'; }
    public function display_name(): string { return 'Koko Analytics'; }

    public function is_available(): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'koko_analytics_site_stats';
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    public function traffic_summary(array $args): array {
        global $wpdb;
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));

        $site_tbl = $wpdb->prefix . 'koko_analytics_site_stats';
        $pages_tbl = $wpdb->prefix . 'koko_analytics_post_stats';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(`visitors`), 0) AS users, COALESCE(SUM(`pageviews`), 0) AS views FROM `$site_tbl` WHERE `date` BETWEEN %s AND %s",
            $range['start'],
            $range['end']
        ), ARRAY_A);

        $users = (int) ($row['users'] ?? 0);
        $page_views = (int) ($row['views'] ?? 0);

        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID AS post_id, p.post_title AS title, COALESCE(SUM(s.pageviews), 0) AS views
             FROM `$pages_tbl` s
             LEFT JOIN {$wpdb->posts} p ON p.ID = s.id
             WHERE s.date BETWEEN %s AND %s
             GROUP BY s.id
             ORDER BY views DESC LIMIT 5",
            $range['start'],
            $range['end']
        ), ARRAY_A);
        $top_pages = [];
        if (is_array($pages)) {
            foreach ($pages as $p) {
                $top_pages[] = [
                    'path'  => (string) ($p['title'] ?? '—'),
                    'views' => (int) $p['views'],
                ];
            }
        }

        return [
            'provider'    => $this->name(),
            'range'       => $range,
            'users'       => $users ?: null,
            'sessions'    => null,
            'page_views'  => $page_views ?: null,
            'top_pages'   => $top_pages,
            'top_sources' => [], // Koko doesn't track referrers by default
        ];
    }
}

/**
 * Statify — minimal privacy-friendly stats. Detection-only in v0.5.14:
 * the plugin stores per-URL visit counts but the data shape needs
 * verifying with a real install before we ship aggregate queries.
 */
class StatifyAnalyticsProvider implements AnalyticsProvider {
    public function name(): string { return 'statify'; }
    public function display_name(): string { return 'Statify'; }
    public function is_available(): bool {
        return class_exists('Statify');
    }
    public function traffic_summary(array $args): array {
        $range = AnalyticsRouter::resolve_range((string) ($args['date_range'] ?? 'this_week'));
        return [
            'provider'            => $this->name(),
            'range'               => $range,
            'users'               => null,
            'sessions'            => null,
            'page_views'          => null,
            'top_pages'           => [],
            'top_sources'         => [],
            'integration_pending' => true,
            'note'                => 'Statify detected — aggregate query integration ships in the next ChatAdmin release.',
        ];
    }
}

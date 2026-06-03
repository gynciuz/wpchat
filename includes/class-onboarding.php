<?php
/**
 * WPChat first-run onboarding REST routes.
 *
 * Backs the capability matrix the React wizard renders + the interactive
 * cards (API key + model picker). Same permission gate as the chat route.
 *
 * Design principle #6 — "Reflect the User, Not the Product." The status
 * endpoint returns the user's actual environment (their key state, their
 * caps, their installed plugins) so each card can render something the
 * user recognises, not a generic tour.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Onboarding {

    const NAMESPACE          = 'wpchat/v1';
    const USER_META_KEY      = 'wpchat_onboarding_done';
    const DISABLED_KINDS_OPT = 'wpchat_disabled_kinds';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/onboarding/status', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_status'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/api-key', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_set_api_key'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/model', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_set_model'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/complete', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_complete'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/reset', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_reset'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/disabled-kinds', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_admin'],
            'callback'            => [$this, 'handle_set_disabled_kinds'],
        ]);
    }

    /** Stricter gate: only manage_options users (site admins) may flip
     *  the site-wide disabled-kinds list. */
    public function check_admin(): bool {
        return current_user_can('manage_options');
    }

    public function check_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
    }

    public function handle_status(\WP_REST_Request $request): \WP_REST_Response {
        $user = wp_get_current_user();

        return new \WP_REST_Response([
            'apiKey'         => $this->api_key_status(),
            'model'          => $this->model_status(),
            'permissions'    => $this->permissions_status($user),
            'wc'             => $this->wc_status(),
            'analytics'      => $this->analytics_status(),
            'backends'       => $this->backends_status(),
            'integrations'   => $this->integrations_status(),
            'disabled_kinds' => self::get_site_disabled_kinds(),
            'isAdmin'        => current_user_can('manage_options'),
            'user'           => [
                'id'           => $user->ID,
                'display_name' => html_entity_decode((string) $user->display_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'first_name'   => $user->first_name ?: '',
                'locale'       => substr(get_user_locale($user), 0, 2),
            ],
            'site'         => [
                'name'    => html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url'     => home_url(),
                'admin'   => admin_url(),
            ],
        ], 200);
    }

    public function handle_set_api_key(\WP_REST_Request $request): \WP_REST_Response {
        if (defined('WPCHAT_ANTHROPIC_API_KEY') && WPCHAT_ANTHROPIC_API_KEY) {
            return new \WP_REST_Response([
                'error'  => 'WPCHAT_ANTHROPIC_API_KEY is defined in wp-config.php and takes precedence. Edit wp-config.php to change it.',
                'apiKey' => $this->api_key_status(),
            ], 409);
        }

        $body = $request->get_json_params();
        $key  = (string) ($body['key'] ?? '');
        if ($key === '') {
            return new \WP_REST_Response(['error' => 'key is required.'], 400);
        }
        // Anthropic keys start with sk-ant-; accept anything that looks
        // close so we don't lock users out of a key format change.
        if (!preg_match('/^sk-[a-z0-9_\-]+$/i', $key)) {
            return new \WP_REST_Response(['error' => 'Key does not look like an Anthropic API key (should start with sk-).'], 400);
        }

        $options = (array) get_option(Settings::OPTION, []);
        $options['anthropic_api_key'] = sanitize_text_field($key);
        update_option(Settings::OPTION, $options);

        return new \WP_REST_Response(['apiKey' => $this->api_key_status()], 200);
    }

    public function handle_set_model(\WP_REST_Request $request): \WP_REST_Response {
        $body  = $request->get_json_params();
        $model = (string) ($body['model'] ?? '');
        $allowed = ['claude-sonnet-4-6', 'claude-opus-4-7', 'claude-haiku-4-5'];
        if (!in_array($model, $allowed, true)) {
            return new \WP_REST_Response([
                'error'   => 'Unknown model.',
                'allowed' => $allowed,
            ], 400);
        }
        $options = (array) get_option(Settings::OPTION, []);
        $options['model'] = $model;
        update_option(Settings::OPTION, $options);
        return new \WP_REST_Response(['model' => $this->model_status()], 200);
    }

    public function handle_complete(\WP_REST_Request $request): \WP_REST_Response {
        update_user_meta(get_current_user_id(), self::USER_META_KEY, '1');
        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function handle_reset(\WP_REST_Request $request): \WP_REST_Response {
        delete_user_meta(get_current_user_id(), self::USER_META_KEY);
        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function handle_set_disabled_kinds(\WP_REST_Request $request): \WP_REST_Response {
        $body = $request->get_json_params();
        $raw  = is_array($body['disabled'] ?? null) ? $body['disabled'] : [];
        $clean = [];
        foreach ($raw as $kind) {
            if (is_string($kind) && $kind !== '') {
                $clean[] = sanitize_key($kind);
            }
        }
        $clean = array_values(array_unique($clean));
        update_option(self::DISABLED_KINDS_OPT, $clean, false);
        return new \WP_REST_Response([
            'ok'             => true,
            'disabled_kinds' => $clean,
        ], 200);
    }

    /**
     * Site-level list of content kinds an admin has disabled for the
     * chat. Empty array by default (all kinds enabled). Tools dispatch
     * checks this BEFORE the backend's own gate, so a disabled kind
     * short-circuits even if the LLM tries it.
     *
     * @return string[]
     */
    public static function get_site_disabled_kinds(): array {
        $value = get_option(self::DISABLED_KINDS_OPT, []);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $value)));
    }

    // ------------------------------------------------------------------
    // Capability check helpers — each returns a small, UI-shaped dict.
    // ------------------------------------------------------------------

    private function api_key_status(): array {
        $constant_defined = defined('WPCHAT_ANTHROPIC_API_KEY') && WPCHAT_ANTHROPIC_API_KEY;
        $key = Settings::get_api_key();
        return [
            'ok'      => $key !== '',
            'masked'  => $key ? '••••' . substr($key, -4) : null,
            'source'  => $constant_defined ? 'constant' : ($key ? 'option' : 'none'),
            'editable' => !$constant_defined,
        ];
    }

    private function model_status(): array {
        return [
            'current' => Settings::get_model(),
            'options' => [
                ['id' => 'claude-sonnet-4-6', 'label' => 'Sonnet 4.6 (recommended)'],
                ['id' => 'claude-opus-4-7',   'label' => 'Opus 4.7 (highest quality, slowest)'],
                ['id' => 'claude-haiku-4-5',  'label' => 'Haiku 4.5 (fastest, cheapest)'],
            ],
        ];
    }

    private function permissions_status(\WP_User $user): array {
        $required = ['manage_woocommerce', 'edit_shop_orders'];
        $has = array_values(array_filter($required, static fn($c) => $user->has_cap($c)));
        return [
            'ok'       => !empty($has),
            'has'      => $has,
            'required' => $required,
            'role'     => $user->roles[0] ?? '',
        ];
    }

    private function wc_status(): array {
        $active = class_exists('WooCommerce') || function_exists('wc_get_orders');
        $version = $active && defined('WC_VERSION') ? WC_VERSION : null;
        $order_count = null;
        if ($active && function_exists('wc_orders_count')) {
            // wc_orders_count counts by status; we just want a rough "are there any orders" signal.
            $order_count = (int) wp_count_posts('shop_order')->publish
                        + (int) wp_count_posts('shop_order')->processing
                        + (int) wp_count_posts('shop_order')->completed;
        }
        return [
            'active'      => $active,
            'version'     => $version,
            'order_count' => $order_count,
            'install_url' => admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'),
        ];
    }

    private function analytics_status(): array {
        // Until the AnalyticsRouter ships (separate task), we detect the
        // common providers inline. This keeps the onboarding card useful
        // even without the router class.
        $providers = [];
        if (class_exists('Google\Site_Kit\Plugin')) {
            $providers[] = ['id' => 'site-kit', 'name' => 'Google Site Kit'];
        }
        if (class_exists('Jetpack')) {
            $providers[] = ['id' => 'jetpack', 'name' => 'Jetpack'];
        }
        if (class_exists('MonsterInsights_Lite') || class_exists('MonsterInsights')) {
            $providers[] = ['id' => 'monsterinsights', 'name' => 'MonsterInsights'];
        }
        if (class_exists('WP_Statistics')) {
            $providers[] = ['id' => 'wp-statistics', 'name' => 'WP Statistics'];
        }
        return [
            'detected'   => $providers,
            'recommended' => [
                ['id' => 'site-kit',    'name' => 'Google Site Kit',  'install_url' => admin_url('plugin-install.php?s=Site+Kit+by+Google&tab=search&type=term')],
                ['id' => 'jetpack',     'name' => 'Jetpack',          'install_url' => admin_url('plugin-install.php?s=Jetpack&tab=search&type=term')],
                ['id' => 'wp-statistics','name' => 'WP Statistics',   'install_url' => admin_url('plugin-install.php?s=WP+Statistics&tab=search&type=term')],
            ],
        ];
    }

    private function backends_status(): array {
        $out      = [];
        $disabled = self::get_site_disabled_kinds();
        if (class_exists('\WPChat\ContentRouter')) {
            foreach (\WPChat\ContentRouter::all_descriptions() as $kind => $desc) {
                $is_core = in_array($kind, ['wp_post', 'wp_page_slug', 'wp_post_meta', 'wp_term'], true);
                $out[] = [
                    'kind'           => $kind,
                    'description'    => $desc['description'] ?? '',
                    'fields'         => $desc['fields'] ?? [],
                    'source'         => $is_core ? 'core' : 'site',
                    'requiredCap'    => Tools::kind_required_cap($kind),
                    'userCanEdit'    => Tools::user_can_edit_kind($kind),
                    'siteDisabled'   => in_array($kind, $disabled, true),
                ];
            }
        }
        return $out;
    }

    private function integrations_status(): array {
        return [
            'cf_purge' => [
                'configured' => defined('CLOUDFLARE_API_TOKEN') && defined('CLOUDFLARE_ZONE_ID'),
                'snippet'    => "define('CLOUDFLARE_API_TOKEN', '...');\ndefine('CLOUDFLARE_ZONE_ID', '...');",
            ],
            'git_sync' => [
                'configured' => defined('WPCHAT_GIT_SYNC_ENABLED') && WPCHAT_GIT_SYNC_ENABLED === true,
                'snippet'    => "define('WPCHAT_GIT_SYNC_ENABLED', true);",
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Used by Frontend::maybe_render() to decide onboarding vs chat mode.
    // ------------------------------------------------------------------

    public static function should_show_for_user(int $user_id): bool {
        if (isset($_GET['onboarding']) && $_GET['onboarding'] === '1') {
            return true;
        }
        if ($user_id <= 0) {
            return false;
        }
        return !get_user_meta($user_id, self::USER_META_KEY, true);
    }
}

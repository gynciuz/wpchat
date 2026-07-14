<?php
/**
 * ChatAdmin first-run onboarding REST routes.
 *
 * Backs the capability matrix the React wizard renders + the interactive
 * cards (API key + model picker). Same permission gate as the chat route.
 *
 * Design principle #6 — "Reflect the User, Not the Product." The status
 * endpoint returns the user's actual environment (their key state, their
 * caps, their installed plugins) so each card can render something the
 * user recognises, not a generic tour.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Onboarding {

    const NAMESPACE          = 'chatadmin/v1';
    const USER_META_KEY      = 'chatadmin_onboarding_done';
    const DISABLED_KINDS_OPT = 'chatadmin_disabled_kinds';
    const PROVIDER_OPT       = 'chatadmin_provider_choice';
    const WAITLIST_OPT       = 'chatadmin_cloud_waitlist';

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
            // Site-wide secret + billing — admin only, matching the Settings page.
            'permission_callback' => [$this, 'check_admin'],
            'callback'            => [$this, 'handle_set_api_key'],
        ]);
        register_rest_route(self::NAMESPACE, '/onboarding/model', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_admin'],
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
        register_rest_route(self::NAMESPACE, '/onboarding/provider', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_admin'],
            'callback'            => [$this, 'handle_set_provider'],
        ]);
        // LLM-provider axis (anthropic | openai | gemini) — distinct from the
        // billing `provider` (byo vs cloud-waitlist) above.
        register_rest_route(self::NAMESPACE, '/onboarding/llm-provider', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_admin'],
            'callback'            => [$this, 'handle_set_llm_provider'],
        ]);
    }

    /** Stricter gate: only manage_options users (site admins) may change
     *  site-wide settings — the API key, model, provider(s), and the
     *  disabled-kinds list. */
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
            'provider'       => $this->provider_status(),
            'llmProvider'    => $this->llm_provider_status(),
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
        $body = $request->get_json_params();
        $key  = trim((string) ($body['key'] ?? ''));
        if ($key === '') {
            return new \WP_REST_Response(['error' => 'key is required.'], 400);
        }

        // One field, no provider picker — detect the provider from the key.
        $provider_id = LLM::detect($key);
        if (!$provider_id) {
            return new \WP_REST_Response([
                'error' => __('Couldn’t recognize this key. Supported: Anthropic (sk-ant-…), OpenAI (sk-…), or Google Gemini (AIza…).', 'chatadmin'),
            ], 400);
        }
        $provider = LLM::get($provider_id);

        if (Settings::key_source($provider_id) === 'constant') {
            return new \WP_REST_Response([
                'error'  => sprintf('CHATADMIN_%s_API_KEY is defined in wp-config.php and takes precedence. Edit wp-config.php to change it.', strtoupper($provider_id)),
                'apiKey' => $this->api_key_status(),
            ], 409);
        }

        // Live auth check — catch typo'd / revoked keys here, not at first chat.
        // Fails open on transient errors (see BaseLLMProvider::check_key).
        $check = $provider->validate_key($key);
        if (empty($check['ok'])) {
            return new \WP_REST_Response(['error' => $check['error'] ?? 'Key validation failed.'], 400);
        }

        $options = (array) get_option(Settings::OPTION, []);
        $options[$provider_id . '_api_key'] = sanitize_text_field($key);
        $options['llm_provider']            = $provider_id; // detected key sets the active provider
        // Reset the model to the detected provider's default if the current one
        // isn't valid for it.
        $valid = array_column($provider->models(), 'id');
        if ($provider_id === 'anthropic') {
            $valid[] = 'claude-opus-4-7';
        }
        if (!in_array($options['model'] ?? '', $valid, true)) {
            $options['model'] = $provider->default_model();
        }
        update_option(Settings::OPTION, $options);

        return new \WP_REST_Response([
            'apiKey' => $this->api_key_status(),
            'model'  => $this->model_status(),
        ], 200);
    }

    public function handle_set_llm_provider(\WP_REST_Request $request): \WP_REST_Response {
        if (defined('CHATADMIN_LLM_PROVIDER') && CHATADMIN_LLM_PROVIDER) {
            return new \WP_REST_Response([
                'error'       => 'CHATADMIN_LLM_PROVIDER is set in wp-config.php and takes precedence.',
                'llmProvider' => $this->llm_provider_status(),
            ], 409);
        }
        $body = $request->get_json_params();
        $id   = sanitize_key((string) ($body['provider'] ?? ''));
        $provider = LLM::get($id);
        if (!$provider) {
            return new \WP_REST_Response(['error' => 'Unknown provider.', 'allowed' => array_keys(LLM::providers())], 400);
        }

        $options = (array) get_option(Settings::OPTION, []);
        $options['llm_provider'] = $id;
        // Reset the model to the new provider's default if the current model
        // isn't one of its models.
        $valid = array_column($provider->models(), 'id');
        if (!in_array($options['model'] ?? '', $valid, true)) {
            $options['model'] = $provider->default_model();
        }
        update_option(Settings::OPTION, $options);

        return new \WP_REST_Response([
            'llmProvider' => $this->llm_provider_status(),
            'model'       => $this->model_status(),
            'apiKey'      => $this->api_key_status(),
        ], 200);
    }

    public function handle_set_model(\WP_REST_Request $request): \WP_REST_Response {
        $body     = $request->get_json_params();
        $model    = (string) ($body['model'] ?? '');
        $provider = LLM::active();
        $allowed  = array_column($provider->models(), 'id');
        // Anthropic: keep opus-4-7 accepted for back-compat with saved configs.
        if ($provider->id() === 'anthropic') {
            $allowed[] = 'claude-opus-4-7';
        }
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

    public function handle_set_provider(\WP_REST_Request $request): \WP_REST_Response {
        $body     = $request->get_json_params();
        $provider = (string) ($body['provider'] ?? '');
        $email    = isset($body['email']) ? sanitize_email((string) $body['email']) : '';
        if (!in_array($provider, ['byo', 'cloud-waitlist'], true)) {
            return new \WP_REST_Response([
                'error'   => 'Unknown provider.',
                'allowed' => ['byo', 'cloud-waitlist'],
            ], 400);
        }
        update_option(self::PROVIDER_OPT, $provider, false);

        // If they're opting into the Cloud waitlist, capture the email
        // alongside the existing list so we can ping people when the
        // tier opens. Email is optional (the choice itself is the
        // primary signal).
        if ($provider === 'cloud-waitlist' && $email && is_email($email)) {
            $waitlist = (array) get_option(self::WAITLIST_OPT, []);
            $waitlist[] = [
                'email' => $email,
                'user_id' => get_current_user_id(),
                'at'    => time(),
                'site'  => home_url(),
            ];
            update_option(self::WAITLIST_OPT, $waitlist, false);

            // Forward to the developer too — a local-only list never reaches us.
            Telemetry::send_report([
                'kind'  => 'cloud_waitlist_signup',
                'email' => $email,
                'site'  => home_url(),
            ]);
        }

        return new \WP_REST_Response(['provider' => $this->provider_status()], 200);
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
        $provider_id = Settings::get_provider();
        $provider    = LLM::get($provider_id) ?? LLM::get('anthropic');
        $source      = Settings::key_source($provider_id);
        $key         = Settings::get_api_key($provider_id);
        return [
            'ok'       => $key !== '',
            'masked'   => $key ? '••••' . substr($key, -4) : null,
            'source'   => $source,
            'editable' => $source !== 'constant',
            'provider' => $provider_id,
            'keyHelp'  => $provider->key_help(),
        ];
    }

    private function llm_provider_status(): array {
        $options = array_map(static function (LLMProvider $p) {
            return ['id' => $p->id(), 'label' => $p->label(), 'keyHelp' => $p->key_help()];
        }, array_values(LLM::providers()));
        return [
            'current' => Settings::get_provider(),
            'locked'  => defined('CHATADMIN_LLM_PROVIDER') && CHATADMIN_LLM_PROVIDER,
            'options' => $options,
        ];
    }

    private function provider_status(): array {
        $current = (string) get_option(self::PROVIDER_OPT, 'byo');
        if (!in_array($current, ['byo', 'cloud-waitlist'], true)) {
            $current = 'byo';
        }
        return [
            'current'             => $current,
            'cloudAvailable'      => false, // flip when the cloud service actually ships
            'cloudWaitlistOpen'   => true,
        ];
    }

    private function model_status(): array {
        return [
            'current' => Settings::get_model(),
            'options' => LLM::active()->models(),
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
        if ($active && function_exists('wc_orders_count') && function_exists('wc_get_order_statuses')) {
            // Sum across all registered WC order statuses for a rough "are
            // there any orders" signal. wc_orders_count() works with both
            // HPOS and legacy post storage; wp_count_posts('shop_order')
            // misses HPOS orders entirely and its properties are post-status
            // names (publish, …) that don't match WC's wc-* order statuses —
            // reading ->processing/->completed off it emits "undefined
            // property" warnings that corrupt the REST JSON response.
            $order_count = 0;
            foreach (array_keys(wc_get_order_statuses()) as $status) {
                $order_count += (int) wc_orders_count($status);
            }
        }
        return [
            'active'      => $active,
            'version'     => $version,
            'order_count' => $order_count,
            'install_url' => admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'),
        ];
    }

    private function analytics_status(): array {
        // Detection is owned by AnalyticsRouter (same providers the
        // get_traffic_summary tool uses), so the onboarding card and the
        // chat tool never disagree about what's installed. Map the router's
        // {name, display_name} to the {id, name} shape the React card reads.
        $providers = [];
        foreach (AnalyticsRouter::detected() as $p) {
            $providers[] = ['id' => $p['name'], 'name' => $p['display_name']];
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
        if (class_exists('\ChatAdmin\ContentRouter')) {
            foreach (\ChatAdmin\ContentRouter::all_descriptions() as $kind => $desc) {
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
                'configured' => defined('CHATADMIN_GIT_SYNC_ENABLED') && CHATADMIN_GIT_SYNC_ENABLED === true,
                'snippet'    => "define('CHATADMIN_GIT_SYNC_ENABLED', true);",
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Used by Frontend::maybe_render() to decide onboarding vs chat mode.
    // ------------------------------------------------------------------

    public static function should_show_for_user(int $user_id): bool {
        // Read-only view toggle (show the onboarding wizard); no state change,
        // so no nonce is required.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['onboarding']) && '1' === sanitize_text_field(wp_unslash($_GET['onboarding']))) {
            return true;
        }
        if ($user_id <= 0) {
            return false;
        }
        return !get_user_meta($user_id, self::USER_META_KEY, true);
    }
}

<?php
/**
 * WPChat settings (API key, model selection).
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {

    const OPTION = 'wpchat_settings';

    public function __construct() {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void {
        register_setting(
            'wpchat_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [
                    'llm_provider'      => 'anthropic',
                    'anthropic_api_key' => '',
                    'model'             => 'claude-sonnet-4-6',
                ],
            ]
        );

        add_settings_section(
            'wpchat_settings_section_api',
            __('AI provider', 'wpchat'),
            function () {
                echo '<p>' . esc_html__('Choose which AI provider WPChat uses, then paste that provider’s API key. You are billed by the provider directly.', 'wpchat') . '</p>';
            },
            'wpchat-settings'
        );

        add_settings_field(
            'llm_provider',
            __('Provider', 'wpchat'),
            [$this, 'field_provider'],
            'wpchat-settings',
            'wpchat_settings_section_api'
        );
        add_settings_field(
            'model',
            __('Model', 'wpchat'),
            [$this, 'field_model'],
            'wpchat-settings',
            'wpchat_settings_section_api'
        );
        // One write-only key field per registered provider.
        foreach (LLM::providers() as $provider) {
            $pid = $provider->id();
            add_settings_field(
                'key_' . $pid,
                sprintf(__('%s API key', 'wpchat'), $provider->label()),
                function () use ($pid) { $this->render_key_field($pid); },
                'wpchat-settings',
                'wpchat_settings_section_api'
            );
        }

        add_settings_section(
            'wpchat_settings_section_privacy',
            __('Privacy & diagnostics', 'wpchat'),
            function () {
                echo '<p>' . esc_html__('WPChat sends the content of your requests (which can include order and customer data) to your chosen AI provider to generate replies. See the plugin README for the full data-handling note.', 'wpchat') . '</p>';
            },
            'wpchat-settings'
        );

        add_settings_field(
            'telemetry',
            __('Error reporting', 'wpchat'),
            [$this, 'field_telemetry'],
            'wpchat-settings',
            'wpchat_settings_section_privacy'
        );
    }

    public function sanitize($input): array {
        $output = (array) get_option(self::OPTION, []);

        if (isset($input['llm_provider']) && LLM::get(sanitize_key($input['llm_provider']))) {
            $output['llm_provider'] = sanitize_key($input['llm_provider']);
        }

        // Write-only key fields: only overwrite when a new value is supplied,
        // so submitting the form with a blank field keeps the stored key.
        foreach (array_keys(LLM::providers()) as $pid) {
            if (!empty($input[$pid . '_api_key'])) {
                $output[$pid . '_api_key'] = sanitize_text_field($input[$pid . '_api_key']);
            }
        }

        if (isset($input['model'])) {
            $output['model'] = sanitize_text_field($input['model']);
        }

        // If the chosen model isn't valid for the (possibly just-changed)
        // provider, fall back to that provider's default.
        $provider = LLM::get($output['llm_provider'] ?? 'anthropic');
        if ($provider) {
            $valid = array_column($provider->models(), 'id');
            if ($provider->id() === 'anthropic') {
                $valid[] = 'claude-opus-4-7';
            }
            if (!in_array($output['model'] ?? '', $valid, true)) {
                $output['model'] = $provider->default_model();
            }
        }

        // Checkbox: present in POST = on, absent = off.
        $output['telemetry'] = !empty($input['telemetry']);
        return $output;
    }

    public function field_provider(): void {
        $current = Settings::get_provider();
        $locked  = defined('WPCHAT_LLM_PROVIDER') && WPCHAT_LLM_PROVIDER;
        if ($locked) {
            echo '<p class="description">' . esc_html__('Set via WPCHAT_LLM_PROVIDER in wp-config.php.', 'wpchat') . '</p>';
        }
        printf('<select name="%s[llm_provider]" %s>', esc_attr(self::OPTION), $locked ? 'disabled' : '');
        foreach (LLM::providers() as $provider) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($provider->id()),
                selected($current, $provider->id(), false),
                esc_html($provider->label())
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Save to refresh the model list for the selected provider.', 'wpchat') . '</p>';
    }

    private function render_key_field(string $provider_id): void {
        $provider = LLM::get($provider_id);
        if (!$provider) {
            return;
        }
        $const = 'WPCHAT_' . strtoupper($provider_id) . '_API_KEY';
        if (defined($const) && constant($const)) {
            echo '<p class="description">' . esc_html(sprintf(__('Set via the %s constant in wp-config.php (ignored here).', 'wpchat'), $const)) . '</p>';
            return;
        }
        $options = get_option(self::OPTION, []);
        $value   = $options[$provider_id . '_api_key'] ?? '';
        $masked  = $value ? str_repeat('•', 8) . substr($value, -4) : '';
        $help    = $provider->key_help();
        // Write-only: never echo the stored key back into the field.
        printf(
            '<input type="password" name="%s[%s_api_key]" value="" class="regular-text" autocomplete="off" placeholder="%s" />',
            esc_attr(self::OPTION),
            esc_attr($provider_id),
            esc_attr($value ? __('Leave blank to keep current key', 'wpchat') : ($help['placeholder'] ?? ''))
        );
        if ($masked) {
            echo '<p class="description">' . esc_html__('Current key:', 'wpchat') . ' <code>' . esc_html($masked) . '</code></p>';
        }
        printf(
            '<p class="description"><a href="%s" target="_blank" rel="noreferrer">%s</a> · %s</p>',
            esc_url($help['url'] ?? ''),
            esc_html__('Get a key', 'wpchat'),
            esc_html(sprintf(__('or set %s in wp-config.php', 'wpchat'), $const))
        );
    }

    public function field_model(): void {
        $value  = Settings::get_model();
        $models = LLM::active()->models();
        printf('<select name="%s[model]">', esc_attr(self::OPTION));
        foreach ($models as $m) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($m['id']),
                selected($value, $m['id'], false),
                esc_html($m['label'])
            );
        }
        echo '</select>';
    }

    public function field_telemetry(): void {
        $enabled = Telemetry::telemetry_enabled();
        printf(
            '<label><input type="checkbox" name="%s[telemetry]" value="1" %s /> %s</label>',
            esc_attr(self::OPTION),
            checked($enabled, true, false),
            esc_html__('Send anonymous error reports (no order or customer data) so the developer can fix failures you hit. You can turn this off any time.', 'wpchat')
        );
    }

    /**
     * The active LLM provider id ('anthropic' | 'openai' | 'gemini'), preferring
     * the WPCHAT_LLM_PROVIDER constant over the DB option.
     */
    public static function get_provider(): string {
        if (defined('WPCHAT_LLM_PROVIDER') && WPCHAT_LLM_PROVIDER) {
            return (string) WPCHAT_LLM_PROVIDER;
        }
        $options = get_option(self::OPTION, []);
        $p = (string) ($options['llm_provider'] ?? 'anthropic');
        return $p !== '' ? $p : 'anthropic';
    }

    /**
     * Read the API key for a provider (defaults to the active one), preferring
     * the WPCHAT_{PROVIDER}_API_KEY constant over the `{provider}_api_key` DB
     * option. `WPCHAT_ANTHROPIC_API_KEY` / `anthropic_api_key` keep working.
     */
    public static function get_api_key(string $provider = ''): string {
        $provider = $provider !== '' ? $provider : self::get_provider();
        $const    = 'WPCHAT_' . strtoupper($provider) . '_API_KEY';
        if (defined($const) && constant($const)) {
            return (string) constant($const);
        }
        $options = get_option(self::OPTION, []);
        return (string) ($options[$provider . '_api_key'] ?? '');
    }

    /** Where the active provider's key comes from: 'constant' | 'option' | 'none'. */
    public static function key_source(string $provider = ''): string {
        $provider = $provider !== '' ? $provider : self::get_provider();
        $const    = 'WPCHAT_' . strtoupper($provider) . '_API_KEY';
        if (defined($const) && constant($const)) {
            return 'constant';
        }
        $options = get_option(self::OPTION, []);
        return !empty($options[$provider . '_api_key']) ? 'option' : 'none';
    }

    public static function get_model(): string {
        $options = get_option(self::OPTION, []);
        return (string) ($options['model'] ?? 'claude-sonnet-4-6');
    }
}

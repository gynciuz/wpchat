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
                echo '<p>' . esc_html__('Paste your Anthropic, OpenAI, or Google Gemini API key — WPChat detects the provider automatically. You are billed by the provider directly.', 'wpchat') . '</p>';
            },
            'wpchat-settings'
        );

        add_settings_field(
            'api_key',
            __('API key', 'wpchat'),
            [$this, 'field_api_key'],
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

        // One key field — detect the provider from the key and store it under
        // that provider's slot, setting it active. Blank submit keeps current.
        if (!empty($input['api_key'])) {
            $key = trim((string) $input['api_key']);
            $pid = LLM::detect($key);
            if ($pid) {
                $output[$pid . '_api_key'] = sanitize_text_field($key);
                $output['llm_provider']    = $pid;
            } else {
                add_settings_error(self::OPTION, 'wpchat_key', __('Couldn’t recognize that API key (expected sk-ant-…, sk-…, or AIza…).', 'wpchat'));
            }
        }

        if (isset($input['model'])) {
            $output['model'] = sanitize_text_field($input['model']);
        }

        // If the chosen model isn't valid for the active provider, fall back to
        // that provider's default.
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

    public function field_api_key(): void {
        $provider = LLM::active();
        $source   = Settings::key_source();
        $value    = Settings::get_api_key();
        $masked   = $value ? str_repeat('•', 8) . substr($value, -4) : '';

        if ($source === 'constant') {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: %s = provider label */
                __('A %s key is set via a wp-config.php constant (ignored here).', 'wpchat'),
                $provider->label()
            )) . '</p>';
            return;
        }

        // Write-only single field — paste any supported key; provider is detected.
        printf(
            '<input type="password" name="%s[api_key]" value="" class="regular-text" autocomplete="off" placeholder="%s" />',
            esc_attr(self::OPTION),
            esc_attr($value ? __('Leave blank to keep current key', 'wpchat') : 'sk-ant-…  ·  sk-…  ·  AIza…')
        );
        if ($masked) {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: 1: provider label, 2: masked key */
                __('Connected to %1$s · %2$s', 'wpchat'),
                $provider->label(),
                $masked
            )) . '</p>';
        }
        echo '<p class="description">' . esc_html__('Get a key: Anthropic (console.anthropic.com) · OpenAI (platform.openai.com) · Google (aistudio.google.com).', 'wpchat') . '</p>';
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

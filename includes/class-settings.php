<?php
/**
 * ChatAdmin settings (API key, model selection).
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {

    const OPTION = 'chatadmin_settings';

    public function __construct() {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void {
        register_setting(
            'chatadmin_settings_group',
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
            'chatadmin_settings_section_api',
            __('AI provider', 'chat-admin'),
            function () {
                echo '<p>' . esc_html__('Paste your Anthropic, OpenAI, or Google Gemini API key — ChatAdmin detects the provider automatically. You are billed by the provider directly.', 'chat-admin') . '</p>';
            },
            'chatadmin-settings'
        );

        add_settings_field(
            'api_key',
            __('API key', 'chat-admin'),
            [$this, 'field_api_key'],
            'chatadmin-settings',
            'chatadmin_settings_section_api'
        );
        add_settings_field(
            'model',
            __('Model', 'chat-admin'),
            [$this, 'field_model'],
            'chatadmin-settings',
            'chatadmin_settings_section_api'
        );

        add_settings_section(
            'chatadmin_settings_section_privacy',
            __('Privacy & diagnostics', 'chat-admin'),
            function () {
                echo '<p>' . esc_html__('ChatAdmin sends the content of your requests (which can include order and customer data) to your chosen AI provider to generate replies.', 'chat-admin') . '</p>';
                echo '<p>' . esc_html__('Two channels also send data to the plugin developer: “Report a problem” sends your recent conversation (which can include customer data) plus your login/email, and error reporting (below, on by default) sends PII-free diagnostics when something fails. Turn error reporting off below. See the plugin README / PRIVACY.md for the full data-handling note.', 'chat-admin') . '</p>';
            },
            'chatadmin-settings'
        );

        add_settings_field(
            'telemetry',
            __('Error reporting', 'chat-admin'),
            [$this, 'field_telemetry'],
            'chatadmin-settings',
            'chatadmin_settings_section_privacy'
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
                add_settings_error(self::OPTION, 'chatadmin_key', __('Couldn’t recognize that API key (expected sk-ant-…, sk-…, or AIza…).', 'chat-admin'));
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
                __('A %s key is set via a wp-config.php constant (ignored here).', 'chat-admin'),
                $provider->label()
            )) . '</p>';
            return;
        }

        // Write-only single field — paste any supported key; provider is detected.
        printf(
            '<input type="password" name="%s[api_key]" value="" class="regular-text" autocomplete="off" placeholder="%s" />',
            esc_attr(self::OPTION),
            esc_attr($value ? __('Leave blank to keep current key', 'chat-admin') : 'sk-ant-…  ·  sk-…  ·  AIza…')
        );
        if ($masked) {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: 1: provider label, 2: masked key */
                __('Connected to %1$s · %2$s', 'chat-admin'),
                $provider->label(),
                $masked
            )) . '</p>';
        }
        echo '<p class="description">' . esc_html__('Get a key: Anthropic (console.anthropic.com) · OpenAI (platform.openai.com) · Google (aistudio.google.com).', 'chat-admin') . '</p>';
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
            esc_html__('Send anonymous error reports (no order or customer data) so the developer can fix failures you hit. You can turn this off any time.', 'chat-admin')
        );
    }

    /**
     * The active LLM provider id ('anthropic' | 'openai' | 'gemini'), preferring
     * the CHATADMIN_LLM_PROVIDER constant over the DB option.
     */
    public static function get_provider(): string {
        if (defined('CHATADMIN_LLM_PROVIDER') && CHATADMIN_LLM_PROVIDER) {
            return (string) CHATADMIN_LLM_PROVIDER;
        }
        $options = get_option(self::OPTION, []);
        $p = (string) ($options['llm_provider'] ?? 'anthropic');
        return $p !== '' ? $p : 'anthropic';
    }

    /**
     * Read the API key for a provider (defaults to the active one), preferring
     * the CHATADMIN_{PROVIDER}_API_KEY constant over the `{provider}_api_key` DB
     * option. `CHATADMIN_ANTHROPIC_API_KEY` / `anthropic_api_key` keep working.
     */
    public static function get_api_key(string $provider = ''): string {
        $provider = $provider !== '' ? $provider : self::get_provider();
        $const    = 'CHATADMIN_' . strtoupper($provider) . '_API_KEY';
        if (defined($const) && constant($const)) {
            return (string) constant($const);
        }
        $options = get_option(self::OPTION, []);
        return (string) ($options[$provider . '_api_key'] ?? '');
    }

    /** Where the active provider's key comes from: 'constant' | 'option' | 'none'. */
    public static function key_source(string $provider = ''): string {
        $provider = $provider !== '' ? $provider : self::get_provider();
        $const    = 'CHATADMIN_' . strtoupper($provider) . '_API_KEY';
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

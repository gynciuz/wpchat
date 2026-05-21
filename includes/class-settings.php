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
                    'anthropic_api_key' => '',
                    'model'             => 'claude-sonnet-4-6',
                ],
            ]
        );

        add_settings_section(
            'wpchat_settings_section_api',
            __('Anthropic API', 'wpchat'),
            function () {
                echo '<p>' . esc_html__('WPChat uses the Anthropic Claude API. Create an API key at console.anthropic.com.', 'wpchat') . '</p>';
                if (defined('WPCHAT_ANTHROPIC_API_KEY') && WPCHAT_ANTHROPIC_API_KEY) {
                    echo '<p><strong>' . esc_html__('Note:', 'wpchat') . '</strong> ' . esc_html__('An API key is currently set via the WPCHAT_ANTHROPIC_API_KEY constant in wp-config.php. The setting below is ignored while that constant is defined.', 'wpchat') . '</p>';
                }
            },
            'wpchat-settings'
        );

        add_settings_field(
            'anthropic_api_key',
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
    }

    public function sanitize($input): array {
        $output = (array) get_option(self::OPTION, []);
        if (isset($input['anthropic_api_key'])) {
            $output['anthropic_api_key'] = sanitize_text_field($input['anthropic_api_key']);
        }
        if (isset($input['model'])) {
            $output['model'] = sanitize_text_field($input['model']);
        }
        return $output;
    }

    public function field_api_key(): void {
        $options = get_option(self::OPTION, []);
        $value   = $options['anthropic_api_key'] ?? '';
        $masked  = $value ? str_repeat('•', 8) . substr($value, -4) : '';
        printf(
            '<input type="password" name="%s[anthropic_api_key]" value="%s" class="regular-text" autocomplete="off" placeholder="sk-ant-..." />',
            esc_attr(self::OPTION),
            esc_attr($value)
        );
        if ($masked) {
            echo '<p class="description">' . esc_html__('Current key:', 'wpchat') . ' <code>' . esc_html($masked) . '</code></p>';
        }
    }

    public function field_model(): void {
        $options = get_option(self::OPTION, []);
        $value   = $options['model'] ?? 'claude-sonnet-4-6';
        $models  = [
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (recommended)',
            'claude-opus-4-7'   => 'Claude Opus 4.7 (highest quality, slower)',
            'claude-haiku-4-5'  => 'Claude Haiku 4.5 (fastest, cheapest)',
        ];
        printf('<select name="%s[model]">', esc_attr(self::OPTION));
        foreach ($models as $id => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($id),
                selected($value, $id, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Read API key, preferring the wp-config.php constant over the DB option.
     */
    public static function get_api_key(): string {
        if (defined('WPCHAT_ANTHROPIC_API_KEY') && WPCHAT_ANTHROPIC_API_KEY) {
            return (string) WPCHAT_ANTHROPIC_API_KEY;
        }
        $options = get_option(self::OPTION, []);
        return (string) ($options['anthropic_api_key'] ?? '');
    }

    public static function get_model(): string {
        $options = get_option(self::OPTION, []);
        return (string) ($options['model'] ?? 'claude-sonnet-4-6');
    }
}

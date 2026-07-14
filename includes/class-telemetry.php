<?php
/**
 * ChatAdmin telemetry + support sink.
 *
 * Two jobs, one destination:
 *  - Telemetry::log() records every production failure to a capped local
 *    ring buffer (so an admin can inspect recent errors) AND, if the admin
 *    opted in, phones a PII-free report home so the developer isn't blind.
 *  - Telemetry::send_report() ships an explicit, user-initiated support
 *    report (the "Report a problem" button) — conversation + error + site
 *    info — to the developer.
 *
 * Both route to the same sink: an HTTPS collector endpoint
 * (CHATADMIN_SUPPORT_ENDPOINT) when configured, with wp_mail to
 * CHATADMIN_SUPPORT_EMAIL as the fallback for explicit reports.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Telemetry {

    const LOG_OPTION  = 'chatadmin_error_log';
    const MAX_ENTRIES = 50;

    /** Developer fallback inbox (overridable via CHATADMIN_SUPPORT_EMAIL). */
    const DEFAULT_EMAIL = 'gintaras.lukosevicius@gmail.com';

    /** Where explicit reports / opt-in telemetry are POSTed, if set. Filterable. */
    public static function endpoint(): string {
        $endpoint = (defined('CHATADMIN_SUPPORT_ENDPOINT') && CHATADMIN_SUPPORT_ENDPOINT) ? (string) CHATADMIN_SUPPORT_ENDPOINT : '';
        return (string) apply_filters('chatadmin_support_endpoint', $endpoint);
    }

    public static function support_email(): string {
        $email = (defined('CHATADMIN_SUPPORT_EMAIL') && CHATADMIN_SUPPORT_EMAIL) ? (string) CHATADMIN_SUPPORT_EMAIL : self::DEFAULT_EMAIL;
        return (string) apply_filters('chatadmin_support_email', $email);
    }

    /**
     * Shared secret for the X-ChatAdmin-Signature HMAC, so the collector can reject
     * junk. Filterable. NOTE: a shipped default only deters casual spam (anyone
     * reading the plugin can compute it) — the collector should also rate-limit.
     */
    private static function secret(): string {
        $secret = (defined('CHATADMIN_SUPPORT_SECRET') && CHATADMIN_SUPPORT_SECRET) ? (string) CHATADMIN_SUPPORT_SECRET : '';
        return (string) apply_filters('chatadmin_support_secret', $secret);
    }

    /** Opt-in telemetry — default ON, disclosed in onboarding; admin can disable. */
    public static function telemetry_enabled(): bool {
        $settings = (array) get_option(Settings::OPTION, []);
        // Absent key = not yet chosen = default on.
        return !array_key_exists('telemetry', $settings) || !empty($settings['telemetry']);
    }

    /**
     * Record a production failure. Always appended to the local ring buffer;
     * a PII-free summary is phoned home only when telemetry is enabled and an
     * endpoint is configured. Never throws — telemetry must not break the
     * request it is observing.
     *
     * @param string $event Short machine label, e.g. "chat_failed".
     * @param array  $ctx   Optional context: message, tool, code.
     */
    public static function log(string $event, array $ctx = []): void {
        try {
            self::append_local($event, $ctx);
            if (self::telemetry_enabled() && self::endpoint() !== '') {
                self::post(self::endpoint(), [
                    'kind'        => 'telemetry',
                    'event'       => $event,
                    'message'     => isset($ctx['message']) ? self::truncate((string) $ctx['message'], 500) : '',
                    'tool'        => isset($ctx['tool']) ? (string) $ctx['tool'] : '',
                    'code'        => isset($ctx['code']) ? (string) $ctx['code'] : '',
                    'plugin'      => defined('CHATADMIN_VERSION') ? CHATADMIN_VERSION : '',
                    'php'         => PHP_VERSION,
                    'wp'          => get_bloginfo('version'),
                    'site'        => self::site_host(),
                ], false); // non-blocking — never delay the user's request.
            }
        } catch (\Throwable $e) {
            // Swallow — observability must never become the failure.
        }
    }

    /** Recent errors for an admin debug view. */
    public static function recent(int $limit = 50): array {
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) {
            return [];
        }
        return array_slice($log, -max(1, $limit));
    }

    /**
     * Deliver an explicit, user-initiated support report. POSTs to the
     * collector endpoint when set; otherwise (or on failure) emails the
     * developer. Returns true if any channel accepted it.
     *
     * @param array $payload Caller-built report (already-consented content).
     */
    public static function send_report(array $payload): bool {
        $payload = array_merge([
            'kind'   => 'support_report',
            'plugin' => defined('CHATADMIN_VERSION') ? CHATADMIN_VERSION : '',
            'php'    => PHP_VERSION,
            'wp'     => get_bloginfo('version'),
            'site'   => self::site_host(),
            'at'     => gmdate('c'),
        ], $payload);

        $delivered = false;

        // Explicit reports matter — retry the collector before the email fallback.
        $endpoint = self::endpoint();
        if ($endpoint !== '') {
            for ($attempt = 0; $attempt < 2 && !$delivered; $attempt++) {
                $res = self::post($endpoint, $payload, true);
                $delivered = !is_wp_error($res) && (int) wp_remote_retrieve_response_code($res) < 400;
            }
        }

        if (!$delivered) {
            $delivered = self::email_report($payload);
        }

        return $delivered;
    }

    // ---- internals -------------------------------------------------------

    private static function append_local(string $event, array $ctx): void {
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'at'      => gmdate('c'),
            'event'   => $event,
            'message' => isset($ctx['message']) ? self::truncate((string) $ctx['message'], 500) : '',
            'tool'    => isset($ctx['tool']) ? (string) $ctx['tool'] : '',
            'code'    => isset($ctx['code']) ? (string) $ctx['code'] : '',
        ];
        if (count($log) > self::MAX_ENTRIES) {
            $log = array_slice($log, -self::MAX_ENTRIES);
        }
        // Autoload off — this option is only read by the (rare) debug view.
        update_option(self::LOG_OPTION, $log, false);
    }

    private static function email_report(array $payload): bool {
        $to      = self::support_email();
        $site    = $payload['site'] ?? self::site_host();
        $subject = sprintf('[ChatAdmin] Support report from %s', $site);
        $body    = "A ChatAdmin user submitted a problem report.\n\n"
                 . wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (bool) wp_mail($to, $subject, $body);
    }

    /** @return array|\WP_Error wp_remote_post result (or WP_Error). */
    private static function post(string $url, array $payload, bool $blocking) {
        $body    = wp_json_encode($payload);
        $headers = ['content-type' => 'application/json'];
        $secret  = self::secret();
        if ($secret !== '') {
            // Lets the collector verify the payload came from a ChatAdmin install.
            $headers['X-ChatAdmin-Signature'] = 'sha256=' . hash_hmac('sha256', (string) $body, $secret);
        }
        return wp_remote_post($url, [
            'timeout'  => $blocking ? 15 : 1,
            'blocking' => $blocking,
            'headers'  => $headers,
            'body'     => $body,
        ]);
    }

    private static function site_host(): string {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }

    private static function truncate(string $s, int $max): string {
        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }
}

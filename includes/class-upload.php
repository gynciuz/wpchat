<?php
/**
 * WPChat media upload endpoint.
 *
 * Routes a multipart file upload through the WP-core stack:
 *   wp_handle_upload → wp_insert_attachment → wp_generate_attachment_metadata
 *
 * Hands the resulting attachment id back to the chat UI so subsequent
 * messages can reference it (e.g. "replace Nesar's photo with attachment
 * 1234"). The LLM never sees the file bytes.
 *
 * Same permission check as /chat (manage_woocommerce OR edit_shop_orders).
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Upload {

    const ENDPOINT       = 'upload';
    const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    const ALLOWED_MIMES  = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct() {
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register(): void {
        register_rest_route(Rest::NAMESPACE, '/' . self::ENDPOINT, [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_upload'],
        ]);
    }

    public function check_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
    }

    public function handle_upload(\WP_REST_Request $request): \WP_REST_Response {
        $files = $request->get_file_params();
        $file  = $files['file'] ?? null;
        if (!is_array($file) || empty($file['tmp_name'])) {
            return new \WP_REST_Response(['error' => 'No file provided. Use multipart field name "file".'], 400);
        }

        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $msg = self::php_upload_error_message((int) $file['error']);
            return new \WP_REST_Response(['error' => $msg], 400);
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE_BYTES) {
            return new \WP_REST_Response([
                'error'    => sprintf('File too large (%d bytes). Max is %d bytes.', (int) $file['size'], self::MAX_SIZE_BYTES),
                'max_size' => self::MAX_SIZE_BYTES,
            ], 413);
        }

        $detected_mime = self::detect_mime($file['tmp_name'], $file['name'] ?? '');
        if (!in_array($detected_mime, self::ALLOWED_MIMES, true)) {
            return new \WP_REST_Response([
                'error'         => sprintf('Unsupported file type: %s. Allowed: %s', $detected_mime, implode(', ', self::ALLOWED_MIMES)),
                'allowed_mimes' => self::ALLOWED_MIMES,
            ], 415);
        }

        // Hand off to WP core. wp_handle_upload expects a $_FILES-shaped array
        // and an "overrides" array that turns off the form-action check.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = [
            'test_form'                => false,
            'mimes'                    => [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
            ],
            'unique_filename_callback' => null,
        ];

        // Allow tests to inject overrides. Production never sets this filter.
        $overrides = apply_filters('wpchat_upload_overrides', $overrides, $file);

        // Production uses wp_handle_upload (strict: requires is_uploaded_file).
        // Tests can swap to wp_handle_sideload via this filter so synthetic
        // files pass the readability check instead. Both take $file by
        // reference, so call_user_func can't be used — branch directly.
        $handler = apply_filters('wpchat_upload_handler', 'wp_handle_upload');
        if ($handler === 'wp_handle_sideload') {
            $moved = wp_handle_sideload($file, $overrides);
        } else {
            $moved = wp_handle_upload($file, $overrides);
        }
        if (isset($moved['error'])) {
            return new \WP_REST_Response(['error' => $moved['error']], 500);
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $moved['type'],
            'post_title'     => sanitize_file_name(pathinfo($moved['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $moved['url'],
        ], $moved['file']);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return new \WP_REST_Response([
                'error' => is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Could not insert attachment',
            ], 500);
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $moved['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        $width  = (int) ($metadata['width'] ?? 0);
        $height = (int) ($metadata['height'] ?? 0);

        return new \WP_REST_Response([
            'attachment_id' => (int) $attachment_id,
            'url'           => wp_get_attachment_url($attachment_id),
            'mime_type'     => $moved['type'],
            'filename'      => basename($moved['file']),
            'width'         => $width,
            'height'        => $height,
        ], 201);
    }

    /** Detect mime via finfo when available; fall back to the upload's name. */
    private static function detect_mime(string $tmp_path, string $orig_name): string {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $detected = finfo_file($f, $tmp_path) ?: '';
                finfo_close($f);
                if ($detected) {
                    return strtolower($detected);
                }
            }
        }
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            case 'png':  return 'image/png';
            case 'webp': return 'image/webp';
        }
        return 'application/octet-stream';
    }

    private static function php_upload_error_message(int $code): string {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:   return 'File exceeds the server upload size limit.';
            case UPLOAD_ERR_PARTIAL:     return 'File was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:     return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:  return 'Server has no temporary upload directory.';
            case UPLOAD_ERR_CANT_WRITE:  return 'Server could not write the uploaded file to disk.';
            case UPLOAD_ERR_EXTENSION:   return 'A PHP extension stopped the upload.';
        }
        return 'Upload failed (code ' . $code . ').';
    }
}

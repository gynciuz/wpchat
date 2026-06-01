<?php
/**
 * REST upload endpoint tests.
 *
 * Exercises POST /wpchat/v1/upload with synthetic file fixtures and
 * asserts: shape on success, 413 for too-large, 415 for unsupported
 * mime, 401/403 for users without caps.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Tests\TestCase;

class UploadTest extends TestCase {

    public function test_uploads_jpeg_and_returns_payload(): void {
        $tmp = $this->writeTmpJpeg(40, 30);
        $response = $this->dispatchUpload($tmp, 'photo.jpg', 'image/jpeg');

        $this->assertSame(201, $response['status'], 'Expected 201 for valid jpeg, got ' . wp_json_encode($response));
        $data = $response['data'];
        $this->assertArrayHasKey('attachment_id', $data);
        $this->assertGreaterThan(0, $data['attachment_id']);
        $this->assertSame('image/jpeg', $data['mime_type']);
        $this->assertSame(40, $data['width']);
        $this->assertSame(30, $data['height']);
        $this->assertStringEndsWith('.jpg', $data['filename']);
        $this->assertNotEmpty($data['url']);
    }

    public function test_uploads_png(): void {
        $tmp = $this->writeTmpPng(10, 8);
        $response = $this->dispatchUpload($tmp, 'photo.png', 'image/png');
        $this->assertSame(201, $response['status']);
        $this->assertSame('image/png', $response['data']['mime_type']);
    }

    public function test_rejects_pdf_with_415(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, "%PDF-1.4\n%fake\n");
        $response = $this->dispatchUpload($tmp, 'doc.pdf', 'application/pdf');

        $this->assertSame(415, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
        $this->assertContains('image/jpeg', $response['data']['allowed_mimes']);
    }

    public function test_rejects_oversized_file_with_413(): void {
        // Construct a "fake" file with size > MAX_SIZE_BYTES by setting
        // $_FILES.size manually. We don't actually write 10 MB to disk —
        // the endpoint reads `size` from the upload metadata first.
        $tmp = $this->writeTmpJpeg(10, 10);
        $response = $this->dispatchUploadRaw([
            'name'     => 'big.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 11 * 1024 * 1024, // 11 MB, over the 10 MB limit
        ]);

        $this->assertSame(413, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
        $this->assertSame(10 * 1024 * 1024, $response['data']['max_size']);
    }

    public function test_requires_capability(): void {
        $sub = $this->factory()->user->create(['role' => 'subscriber']);
        \wp_set_current_user($sub);

        $tmp = $this->writeTmpJpeg(5, 5);
        $response = $this->dispatchUpload($tmp, 'photo.jpg', 'image/jpeg');

        $this->assertContains($response['status'], [401, 403], 'Subscribers must not reach the upload endpoint.');
    }

    public function test_missing_file_returns_400(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/upload');
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    // -- helpers ------------------------------------------------------------

    private function dispatchUpload(string $tmp, string $name, string $mime): array {
        return $this->dispatchUploadRaw([
            'name'     => $name,
            'type'     => $mime,
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ]);
    }

    private function dispatchUploadRaw(array $file): array {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/upload');
        $request->set_file_params(['file' => $file]);
        // wp_handle_upload normally checks `is_uploaded_file($tmp_name)`.
        // We monkey-patch that check via the {action}_overrides filter is
        // already handled by Upload::handle_upload setting test_form=false.
        // But wp_handle_upload ALSO calls move_uploaded_file which only
        // works on actual HTTP uploads. Hook 'wp_handle_upload_prefilter'
        // to swap in a rename-friendly path.
        $hook = function ($file) {
            if (!is_uploaded_file($file['tmp_name']) && file_exists($file['tmp_name'])) {
                $upload_dir = wp_upload_dir();
                $dest       = trailingslashit($upload_dir['path']) . wp_unique_filename($upload_dir['path'], $file['name']);
                @rename($file['tmp_name'], $dest);
                $file['tmp_name'] = $dest;
                // Mark as uploaded so wp_handle_upload's move passes.
                add_filter('pre_move_uploaded_file', function ($null, $f) use ($dest) {
                    if (($f['tmp_name'] ?? '') === $dest) return $dest;
                    return $null;
                }, 10, 2);
            }
            return $file;
        };
        add_filter('wp_handle_upload_prefilter', $hook);
        $response = \rest_get_server()->dispatch($request);
        remove_filter('wp_handle_upload_prefilter', $hook);

        return [
            'status' => $response->get_status(),
            'data'   => $response->get_data(),
        ];
    }

    private function writeTmpJpeg(int $w, int $h): string {
        if (!function_exists('imagejpeg')) {
            $this->markTestSkipped('GD imagejpeg() not available on this runner.');
        }
        $im = imagecreatetruecolor($w, $h);
        $tmp = tempnam(sys_get_temp_dir(), 'wpchat_jpg');
        imagejpeg($im, $tmp, 80);
        imagedestroy($im);
        return $tmp;
    }

    private function writeTmpPng(int $w, int $h): string {
        if (!function_exists('imagepng')) {
            $this->markTestSkipped('GD imagepng() not available on this runner.');
        }
        $im = imagecreatetruecolor($w, $h);
        $tmp = tempnam(sys_get_temp_dir(), 'wpchat_png');
        imagepng($im, $tmp);
        imagedestroy($im);
        return $tmp;
    }
}

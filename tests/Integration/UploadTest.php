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

        // wp_handle_upload calls is_uploaded_file() + move_uploaded_file().
        // Both fail on synthetic tmp files. Disable the is_uploaded_file
        // check via the plugin's wpchat_upload_overrides filter; copy the
        // file to its destination ourselves via pre_move_uploaded_file
        // (returning a string short-circuits wp_handle_upload's move).
        $disable_test = function ($overrides) {
            $overrides['test_upload'] = false;
            return $overrides;
        };
        $move_hook = function ($null, $f, $new_file) {
            if (file_exists($f['tmp_name'])) {
                @copy($f['tmp_name'], $new_file);
                return $new_file;
            }
            return $null;
        };
        add_filter('wpchat_upload_overrides', $disable_test);
        add_filter('pre_move_uploaded_file', $move_hook, 10, 3);
        $response = \rest_get_server()->dispatch($request);
        remove_filter('pre_move_uploaded_file', $move_hook, 10);
        remove_filter('wpchat_upload_overrides', $disable_test);

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

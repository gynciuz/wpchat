<?php
/**
 * INTEGRATION — the upload endpoint reports a PHP upload error (e.g. a file
 * larger than upload_max_filesize, which arrives as INI_SIZE with an empty
 * tmp_name) accurately, instead of the misleading "No file provided".
 *
 * Regression for the "No file provided" bug hit on a 3.5 MB image vs the
 * default 2 MB upload_max_filesize.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Upload;
use WPChat\Tests\TestCase;

class UploadErrorTest extends TestCase {

    private function dispatch(array $fileParams): \WP_REST_Response {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/upload');
        $request->set_file_params($fileParams);
        return (new Upload())->handle_upload($request);
    }

    public function test_oversized_file_reports_size_not_missing(): void {
        $res = $this->dispatch(['file' => [
            'name'     => 'big.png',
            'type'     => 'image/png',
            'tmp_name' => '',                 // emptied by PHP when over the limit
            'error'    => UPLOAD_ERR_INI_SIZE,
            'size'     => 0,
        ]]);

        $this->assertSame(413, $res->get_status());
        $data = $res->get_data();
        $this->assertStringContainsStringIgnoringCase('exceeds', $data['error']);
        $this->assertStringNotContainsString('No file provided', $data['error']);
    }

    public function test_genuinely_missing_file_still_reports_no_file(): void {
        $res = $this->dispatch([]); // no "file" field at all
        $this->assertSame(400, $res->get_status());
        $this->assertStringContainsString('No file provided', $res->get_data()['error']);
    }
}

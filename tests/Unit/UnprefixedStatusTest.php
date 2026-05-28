<?php
/**
 * Locks in the fix for the v0.1.0–v0.4.9 `ltrim($slug, 'wc-')` bug.
 *
 * That call treats 'wc-' as a character SET, so the leading "c" of slugs
 * like "cancelled" / "completed" was being stripped, producing
 * "ancelled" / "ompleted" and "Unknown status: ancelled" errors at
 * dispatch time. Earlier I misdiagnosed this as an LLM hallucination;
 * it was actual code.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPChat\Tools;

class UnprefixedStatusTest extends TestCase {

    /** @dataProvider cases */
    public function test_unprefixed_status(string $input, string $expected): void {
        $this->assertSame($expected, Tools::unprefixed_status($input));
    }

    public static function cases(): array {
        return [
            'prefixed cancelled'    => ['wc-cancelled', 'cancelled'],
            'prefixed completed'    => ['wc-completed', 'completed'],
            'prefixed processing'   => ['wc-processing', 'processing'],
            'prefixed pending'      => ['wc-pending', 'pending'],
            'prefixed on-hold'      => ['wc-on-hold', 'on-hold'],
            'prefixed refunded'     => ['wc-refunded', 'refunded'],
            'prefixed failed'       => ['wc-failed', 'failed'],
            'prefixed custom panaudotas' => ['wc-panaudotas', 'panaudotas'],

            // The bug scenarios — unprefixed inputs MUST round-trip unchanged.
            'bare cancelled (regression guard)' => ['cancelled', 'cancelled'],
            'bare completed (regression guard)' => ['completed', 'completed'],
            'bare panaudotas'                   => ['panaudotas', 'panaudotas'],
            'bare on-hold'                      => ['on-hold', 'on-hold'],

            // Edge cases.
            'empty'                  => ['', ''],
            'only prefix'            => ['wc-', ''],
            'wc-wc-double prefix'    => ['wc-wc-completed', 'wc-completed'],
            'starts with single w'   => ['waiting', 'waiting'],
            'starts with single c'   => ['cancelled', 'cancelled'],
            'middle wc-'             => ['my-wc-status', 'my-wc-status'],
        ];
    }
}

<?php
/**
 * Pure-unit tests for the confirmation-phrase whitelist that gates every
 * LLM-callable write tool.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPChat\ContentConfirmation;

class ContentConfirmationTest extends TestCase {

    /** @dataProvider acceptedPhrases */
    public function test_accepts_whitelisted_phrases(string $phrase): void {
        $this->assertTrue(ContentConfirmation::is_confirmed($phrase), "Should accept: $phrase");
    }

    public static function acceptedPhrases(): array {
        return [
            'english yes'     => ['yes'],
            'lithuanian taip' => ['taip'],
            'russian да'      => ['да'],
            'polish tak'      => ['tak'],
            'confirm'         => ['confirm'],
            'apply'           => ['apply'],
            'do it'           => ['do it'],
            'patvirtinu'      => ['patvirtinu'],
            'ok'              => ['ok'],
            'case insensitive' => ['TAIP'],
            'with whitespace' => ['  yes  '],
            'sentence containing yes' => ['ok, do it'],
        ];
    }

    /** @dataProvider rejectedPhrases */
    public function test_rejects_non_whitelisted_phrases(string $phrase): void {
        $this->assertFalse(ContentConfirmation::is_confirmed($phrase), "Should reject: '$phrase'");
    }

    public static function rejectedPhrases(): array {
        return [
            'empty'              => [''],
            'whitespace only'    => ['   '],
            'no'                 => ['no'],
            'lithuanian ne'      => ['ne'],
            'russian нет'        => ['нет'],
            'cancel'             => ['cancel'],
            'maybe'              => ['maybe'],
            'unrelated word'     => ['banana'],
        ];
    }

    public function test_long_strings_are_not_substring_matched(): void {
        $long = str_repeat('a', 60) . 'yes' . str_repeat('a', 60);
        $this->assertFalse(
            ContentConfirmation::is_confirmed($long),
            'Strings longer than 40 chars should not substring-match (prevents accidental confirmation from quoted long text).'
        );
    }
}

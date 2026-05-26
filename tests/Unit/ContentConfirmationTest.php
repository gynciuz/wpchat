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
            // English
            'english yes'      => ['yes'],
            'english ok'       => ['ok'],
            'english okay'     => ['okay'],
            'english sure'     => ['sure'],
            'english confirm'  => ['confirm'],
            'english apply'    => ['apply'],
            'english do it'    => ['do it'],
            // Lithuanian
            'lithuanian taip'       => ['taip'],
            'lithuanian gerai'      => ['gerai'],
            'lithuanian sutinku'    => ['sutinku'],
            'lithuanian patvirtinu' => ['patvirtinu'],
            // Russian
            'russian да'      => ['да'],
            'russian хорошо'  => ['хорошо'],
            'russian ок'      => ['ок'],
            // Polish
            'polish tak'      => ['tak'],
            'polish dobrze'   => ['dobrze'],
            // Casing + whitespace
            'case insensitive uppercase' => ['TAIP'],
            'case insensitive mixed'     => ['Gerai'],
            'with whitespace'            => ['  yes  '],
            'with punctuation'           => ['ok!'],
            'sentence with affirmative'  => ['ok, do it'],
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
            'lithuanian negerai (negation of gerai)' => ['negerai'],
            'lithuanian atšaukti' => ['atšaukti'],
            'russian нет'        => ['нет'],
            'russian не надо'    => ['не надо'],
            'polish nie'         => ['nie'],
            'cancel'             => ['cancel'],
            'maybe'              => ['maybe'],
            'unrelated word'     => ['banana'],
            // Safety: if the user types both an affirmative AND a negation,
            // refuse — the user is uncertain and should retry cleanly.
            'mixed signals ne taip' => ['ne, taip'],
            'mixed signals no ok'   => ['no, ok'],
        ];
    }

    public function test_long_strings_with_embedded_affirmative_still_rejected(): void {
        // No word boundary around the embedded "yes" — token-match rejects.
        $long = str_repeat('a', 60) . 'yes' . str_repeat('a', 60);
        $this->assertFalse(
            ContentConfirmation::is_confirmed($long),
            'Affirmatives buried inside larger strings without word boundaries must not match.'
        );
    }
}

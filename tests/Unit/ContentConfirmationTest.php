<?php
/**
 * Pure-unit tests for the confirmation-phrase whitelist that gates every
 * LLM-callable write tool.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ChatAdmin\ContentConfirmation;

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
            // Spanish
            'spanish sí'         => ['sí'],
            'spanish si'         => ['si'],
            'spanish vale'       => ['vale'],
            'spanish confirmar'  => ['confirmar'],
            'spanish de acuerdo' => ['de acuerdo'],
            // French
            'french oui'         => ['oui'],
            'french confirmer'   => ['confirmer'],
            "french d'accord"    => ["d'accord"],
            // Portuguese
            'portuguese sim'     => ['sim'],
            'portuguese está bem'=> ['está bem'],
            // German
            'german ja'          => ['ja'],
            'german bestätigen'  => ['bestätigen'],
            'german einverstanden' => ['einverstanden'],
            // Hindi
            'hindi haan'         => ['हाँ'],
            'hindi theek hai'    => ['ठीक है'],
            'hindi pushti'       => ['पुष्टि करें'],
            // Mandarin (substring — no word boundaries)
            'mandarin queren'    => ['确认'],
            'mandarin haode'     => ['好的'],
            'mandarin shi'       => ['是'],
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
            'french non'         => ['non'],
            'portuguese não'     => ['não'],
            'german nein'        => ['nein'],
            'hindi nahi'         => ['नहीं'],
            'mandarin quxiao (cancel)' => ['取消'],
            'mandarin bu (no)'   => ['不'],
            'mandarin negated confirm' => ['我不确认'],
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

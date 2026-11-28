<?php

namespace App\Services;

class TicketClassificationPhraseExtractor
{
    /**
     * @var list<string>
     */
    private const STOP_WORDS = [
        'about',
        'after',
        'again',
        'also',
        'and',
        'are',
        'can',
        'could',
        'for',
        'from',
        'have',
        'help',
        'into',
        'issue',
        'issues',
        'please',
        'problem',
        'problems',
        'request',
        'that',
        'the',
        'their',
        'there',
        'this',
        'with',
        'would',
    ];

    /**
     * @return list<string>
     */
    public function extract(string $text): array
    {
        $words = preg_split('/[^\pL]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= 3
                && ! in_array($word, self::STOP_WORDS, true),
        ));
        $phrases = [];

        foreach ([2, 3] as $phraseLength) {
            for ($start = 0; $start <= count($words) - $phraseLength; $start++) {
                $phrase = implode(' ', array_slice($words, $start, $phraseLength));

                if (mb_strlen($phrase) <= 45) {
                    $phrases[$phrase] = $phrase;
                }
            }
        }

        return array_values($phrases);
    }
}

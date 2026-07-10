<?php

use App\Support\LegacyTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;

test('restores corrupted line breaks', function (string $input, string $expected) {
    expect(LegacyTextNormalizer::restoreLineBreaks($input))->toBe($expected);
})->with('corruptedTextProvider');

test('converts html descriptions to plain text', function (string $input, string $expected) {
    expect(LegacyTextNormalizer::normalizeImportedText($input))->toBe($expected);
})->with('htmlDescriptionProvider');

/**
 * @return array<string, array{0: string, 1: string}>
 */
dataset('htmlDescriptionProvider', function () {
    return [
        'p tags with class' => [
            '<p class="p1">First paragraph.</p><p class="p1">Second paragraph.</p>',
            "First paragraph.\nSecond paragraph.",
        ],
        'br tags' => [
            'Line one<br>Line two<br />Line three',
            "Line one\nLine two\nLine three",
        ],
        'html entities' => [
            '<p class="p1">Tom &amp; Jerry&apos;s</p>',
            "Tom & Jerry's",
        ],
        'plain text unchanged' => [
            "Already plain text.\nStill plain.",
            "Already plain text.\nStill plain.",
        ],
    ];
});

dataset('corruptedTextProvider', function () {
    return [
        'standalone rn lines' => [
            "For Sale: 2010 Toyota Camry\nrn\n- Year: 2010",
            "For Sale: 2010 Toyota Camry\n\n- Year: 2010",
        ],
        'inline after punctuation' => [
            'Contact us.rnCall today.',
            "Contact us.\nCall today.",
        ],
        'literal escaped sequences' => [
            'Line one\\r\\nLine two',
            "Line one\nLine two",
        ],
        'preserves foreign' => [
            'A foreign pattern in modern design',
            'A foreign pattern in modern design',
        ],
    ];
});

<?php

namespace Tests\Unit\Support;

use App\Support\LegacyTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyTextNormalizerTest extends TestCase
{
    #[DataProvider('corruptedTextProvider')]
    public function test_restores_corrupted_line_breaks(string $input, string $expected): void
    {
        $this->assertSame($expected, LegacyTextNormalizer::restoreLineBreaks($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    #[DataProvider('htmlDescriptionProvider')]
    public function test_converts_html_descriptions_to_plain_text(string $input, string $expected): void
    {
        $this->assertSame($expected, LegacyTextNormalizer::normalizeImportedText($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function htmlDescriptionProvider(): array
    {
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
    }

    public static function corruptedTextProvider(): array
    {
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
    }
}

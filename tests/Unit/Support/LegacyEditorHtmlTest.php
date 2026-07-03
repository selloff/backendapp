<?php

namespace Tests\Unit\Support;

use App\Support\LegacyEditorHtml;
use Tests\TestCase;

class LegacyEditorHtmlTest extends TestCase
{
    public function test_cleans_wordtune_markup_from_footer_html(): void
    {
        $raw = '<p data-private="redact" data-wt-guid="a96554f2-fcf5-4124-a07f-679e7efee4bb" data-pm-slice="1 1 []">Buy or <wt-ignore uuid="c84f583b-d720-45b5-83c5-136b7e5e542f" source="wt-feature-result">sell anything</wt-ignore> in Nigeria.</p>';

        $cleaned = LegacyEditorHtml::clean($raw);

        $this->assertStringNotContainsString('wt-ignore', $cleaned);
        $this->assertStringNotContainsString('data-wt-guid', $cleaned);
        $this->assertStringContainsString('Buy or sell anything in Nigeria.', strip_tags($cleaned));
    }
}

<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogSearchHighlightService;
use PHPUnit\Framework\TestCase;

class CatalogSearchHighlightServiceTest extends TestCase
{
    public function test_search_terms_splits_words_and_adds_compact_form(): void
    {
        $service = new CatalogSearchHighlightService();

        $terms = $service->searchTerms('AUTO RJB 8649');

        $this->assertContains('AUTO', $terms);
        $this->assertContains('RJB', $terms);
        $this->assertContains('8649', $terms);
        $this->assertContains('AUTORJB8649', $terms);
    }

    public function test_highlight_wraps_matches_in_mark_tags(): void
    {
        $service = new CatalogSearchHighlightService();

        $html = $service->highlight('Аккумулятор 12В 100 А·ч', ['Акку', '12В']);

        $this->assertStringContainsString('<mark', $html);
        $this->assertStringContainsString('Акку', $html);
        $this->assertStringContainsString('12В', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_highlight_escapes_html_in_source_text(): void
    {
        $service = new CatalogSearchHighlightService();

        $html = $service->highlight('<b>test</b>', ['test']);

        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('&lt;/b&gt;', $html);
        $this->assertStringContainsString('<mark', $html);
        $this->assertStringNotContainsString('<b>test</b>', $html);
    }
}

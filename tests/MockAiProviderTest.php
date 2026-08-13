<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use WordPressNewsBot\MockAiProvider; use PHPUnit\Framework\TestCase;
final class MockAiProviderTest extends TestCase { public function testOutputHasRequiredStructuredFields(): void { $out = (new MockAiProvider())->generate(['title'=>'Bir haber','excerpt'=>'Kısa özet']); foreach (['title','slug','excerpt','content_html','category_suggestion','tags','seo_title','meta_description','focus_keyword','source_attribution','factual_risk','verification_notes','image_prompt','publication_recommendation'] as $field) $this->assertArrayHasKey($field, $out); $this->assertSame('draft', $out['publication_recommendation']); } }

<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\MockAiProvider;

final class MockAiProviderTest extends TestCase
{
    public function testOutputHasRequiredStructuredFields():void
    {
        $out=(new MockAiProvider())->generate(['title'=>'Bir haber','excerpt'=>'Kısa özet']);
        foreach(['title','excerpt','content_html','suggested_tags','seo_title','seo_description']as$field)$this->assertArrayHasKey($field,$out);
        $this->assertNotSame('Bir haber',$out['title']);$this->assertStringNotContainsString('Source:',$out['content_html']);
    }
}

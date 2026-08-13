<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use WordPressNewsBot\FeedParser; use PHPUnit\Framework\TestCase;
final class FeedParserTest extends TestCase {
    public function testParsesRssWithoutNetwork(): void { $items = (new FeedParser())->parse('<rss><channel><item><guid>abc</guid><title>Türkçe haber</title><link>https://example.com/haber?utm_source=x</link><description>Özet</description></item></channel></rss>'); $this->assertSame('abc', $items[0]['guid']); $this->assertSame('Türkçe haber', $items[0]['title']); }
    public function testRejectsMalformedFeed(): void { $this->expectException(\RuntimeException::class); (new FeedParser())->parse('<rss>'); }
}

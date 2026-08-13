<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use WordPressNewsBot\FeedParser; use PHPUnit\Framework\TestCase;
final class FeedParserTest extends TestCase {
    public function testParsesRssWithoutNetwork(): void { $items = (new FeedParser())->parse('<rss><channel><item><guid>abc</guid><title>Türkçe haber</title><link>https://example.com/haber?utm_source=x</link><description>Özet</description></item></channel></rss>'); $this->assertSame('abc', $items[0]['guid']); $this->assertSame('Türkçe haber', $items[0]['title']); }
    public function testRejectsMalformedFeed(): void { $this->expectException(\RuntimeException::class); (new FeedParser())->parse('<rss>'); }
    public function testParsesDeclaredNonUtf8XmlEncoding():void { $xml='<?xml version="1.0" encoding="ISO-8859-1"?><rss><channel><item><guid>1</guid><title>Caf'.chr(233).'</title><link>https://example.com/n</link></item></channel></rss>'; $items=(new FeedParser())->parse($xml);$this->assertSame('Café',$items[0]['title']); }
}

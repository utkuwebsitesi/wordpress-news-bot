<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WordPressNewsBot\SourceConnectionTester;

final class SourceConnectionTesterTest extends TestCase
{
    public function testReportsSafeFeedMetadataAndFollowsAllowedRedirect(): void
    {
        $calls = [];
        $transport = static function (string $url) use (&$calls): array {
            $calls[] = $url;
            if (count($calls) === 1) return ['response'=>['code'=>302],'headers'=>['location'=>'https://feeds.example.com/final'],'body'=>''];
            return ['response'=>['code'=>200],'headers'=>[],'body'=>'<rss><channel><item><guid>1</guid><title>News</title><link>https://example.com/news</link><description>Summary</description></item></channel></rss>'];
        };
        $result = (new SourceConnectionTester($transport))->test('https://feeds.example.com/start', ['feeds.example.com']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame('RSS', $result['feed_type']);
        $this->assertSame(1, $result['item_count']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);
        $this->assertCount(2, $calls);
    }

    public function testRedirectToNonAllowlistedHostIsRejectedWithoutRawBody(): void
    {
        $tester = new SourceConnectionTester(static fn(): array => ['response'=>['code'=>302],'headers'=>['location'=>'https://evil.example/feed'],'body'=>'secret-body']);
        try {
            $tester->test('https://feeds.example.com/feed', ['feeds.example.com']);
            $this->fail('Unsafe redirect should fail.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('secret-body', $e->getMessage());
        }
    }
}

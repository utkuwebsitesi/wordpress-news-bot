<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WordPressNewsBot\SourceUrl;

final class SourceUrlTest extends TestCase
{
    public function testCanonicalNormalizationPreservesMeaningfulParts(): void
    {
        $this->assertSame('https://example.com/feed?a=1', SourceUrl::canonicalize('HTTPS://Example.COM:443/feed/?a=1#fragment'));
        $this->assertSame('http://example.com/feed', SourceUrl::canonicalize('http://EXAMPLE.com:80/feed/'));
        $this->assertNotSame(SourceUrl::hash('http://example.com/feed'), SourceUrl::hash('https://example.com/feed'));
        $this->assertNotSame(SourceUrl::hash('https://example.com/feed?a=1'), SourceUrl::hash('https://example.com/feed?a=2'));
    }

    public function testRootSlashAndIdnAreNormalized(): void
    {
        $this->assertSame('https://example.com/', SourceUrl::canonicalize('https://EXAMPLE.com/#x'));
        $this->assertSame('https://xn--rnek-4qa.example/feed', SourceUrl::canonicalize('https://örnek.example/feed/'));
    }

    public function testCredentialsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SourceUrl::canonicalize('https://user:pass@example.com/feed');
    }
}

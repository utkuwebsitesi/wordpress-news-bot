<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot\Tests;
use Neyelazim\NewsBot\Security; use PHPUnit\Framework\TestCase;
final class SecurityTest extends TestCase { public function testOnlyHttpsAllowlistedHostPasses(): void { $this->assertTrue(Security::validateFeedUrl('https://feeds.example.com/rss', ['feeds.example.com'])); $this->assertFalse(Security::validateFeedUrl('http://feeds.example.com/rss', ['feeds.example.com'])); $this->assertFalse(Security::validateFeedUrl('https://127.0.0.1/rss')); $this->assertFalse(Security::validateFeedUrl('https://evil.example/rss', ['feeds.example.com'])); } public function testSensitiveLogContextIsRemoved(): void { $clean=Security::cleanLogContext(['api_key'=>'secret','authorization'=>'Bearer secret','message'=>'ok']); $this->assertArrayNotHasKey('api_key',$clean); $this->assertArrayNotHasKey('authorization',$clean); $this->assertSame('ok',$clean['message']); } }

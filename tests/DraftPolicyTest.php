<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;
use WordPressNewsBot\ContentSanitizer; use WordPressNewsBot\DraftPolicy; use PHPUnit\Framework\TestCase;
final class DraftPolicyTest extends TestCase { public function testPostIsAlwaysPreparedAsPrivateDraftBeforeFinalStatus(): void { $args=DraftPolicy::postArgs(['title'=>'Başlık','excerpt'=>'Özet'],3,7,'<p>İçerik</p>'); $this->assertSame('draft',$args['post_status']); $this->assertTrue(DraftPolicy::allowedStatus('publish')); $this->assertTrue(DraftPolicy::allowedStatus('draft')); $this->assertFalse(DraftPolicy::allowedStatus('private')); } public function testDangerousHtmlIsRemoved(): void { $safe=ContentSanitizer::clean('<p>Merhaba</p><script>alert(1)</script><iframe src="x"></iframe><a href="javascript:bad">link</a>'); $this->assertStringNotContainsString('<script',$safe); $this->assertStringNotContainsString('<iframe',$safe); } }

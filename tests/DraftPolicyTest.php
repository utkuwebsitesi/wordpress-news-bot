<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot\Tests;
use Neyelazim\NewsBot\ContentSanitizer; use Neyelazim\NewsBot\DraftPolicy; use PHPUnit\Framework\TestCase;
final class DraftPolicyTest extends TestCase { public function testDraftIsMandatory(): void { $args=DraftPolicy::postArgs(['title'=>'Başlık','excerpt'=>'Özet'],3,7,'<p>İçerik</p>'); $this->assertSame('draft',$args['post_status']); $this->assertFalse(DraftPolicy::allowedStatus('publish')); } public function testDangerousHtmlIsRemoved(): void { $safe=ContentSanitizer::clean('<p>Merhaba</p><script>alert(1)</script><iframe src="x"></iframe><a href="javascript:bad">link</a>'); $this->assertStringNotContainsString('<script',$safe); $this->assertStringNotContainsString('<iframe',$safe); } }

<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\Admin;

require_once dirname(__DIR__).'/admin/Admin.php';

final class AdminAutomationSettingsTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=['wpnb_settings'=>array_replace(['language'=>'tr'],\WordPressNewsBot\AutomationSettings::defaults())];}

    public function testSubmittedAutomationStateIsNotReplacedByThePreviousOption():void
    {
        $saved=(new Admin())->sanitizeSettingsValue(['automation_enabled'=>1,'automation_owner_user_id'=>7]);
        $this->assertSame(1,$saved['automation_enabled']);
        $this->assertSame(7,$saved['automation_owner_user_id']);
    }

    public function testCronCommandRenderingDoesNotDependOnDisabledShellFunctions():void
    {
        $source=(string)file_get_contents(dirname(__DIR__).'/admin/Admin.php');
        $this->assertStringNotContainsString('escapeshellarg',$source);
        $this->assertStringContainsString('wp-cron.php',$source);
        $this->assertStringContainsString('wget -q -O -',$source);
        $this->assertStringContainsString('0,15,30,45',$source);
        $this->assertStringNotContainsString('curl --fail --silent',$source);
        $this->assertStringNotContainsString('No server heartbeat was received in the last five minutes',$source);
        $this->assertStringNotContainsString('strtotime($heartbeat)',$source);
    }
}

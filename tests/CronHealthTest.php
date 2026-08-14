<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\CronHealth;

require_once dirname(__DIR__).'/includes/CronHealth.php';

final class CronHealthTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];}

    public function testHeartbeatIsHealthyWithoutAutomationBeingEnabled():void
    {
        update_option('wpnb_settings',['automation_enabled'=>0],false);CronHealth::record();
        $this->assertTrue(CronHealth::isHealthy());
        $this->assertNotSame('',CronHealth::lastSuccess());
        $this->assertSame(0,get_option('wpnb_settings')['automation_enabled']);
    }

    public function testStaleHeartbeatIsNotHealthy():void
    {
        update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',time()-301),false);
        $this->assertFalse(CronHealth::isHealthy());
    }
}

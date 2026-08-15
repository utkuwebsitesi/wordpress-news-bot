<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\CronHealth;

require_once dirname(__DIR__).'/includes/CronHealth.php';

final class CronHealthTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];$GLOBALS['wpnb_test_timezone']=new \DateTimeZone('UTC');}

    public function testHeartbeatIsHealthyWithoutAutomationBeingEnabled():void
    {
        update_option('wpnb_settings',['automation_enabled'=>0],false);CronHealth::record();
        $this->assertTrue(CronHealth::isHealthy());
        $this->assertNotSame('',CronHealth::lastSuccess());
        $this->assertSame(0,get_option('wpnb_settings')['automation_enabled']);
    }

    public function testStaleHeartbeatIsNotHealthy():void
    {
        update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',time()-1201),false);
        $this->assertFalse(CronHealth::isHealthy());
    }

    public function testLiveUtcHeartbeatIsDisplayedInFixedUtcPlusThreeAndHealthy():void
    {
        $GLOBALS['wpnb_test_timezone']=new \DateTimeZone('+03:00');$now=(new \DateTimeImmutable('2026-08-15 10:01:00',new \DateTimeZone('UTC')))->getTimestamp();
        update_option('wpnb_heartbeat_last_success','2026-08-15 09:56:32',false);
        $this->assertSame('2026-08-15 12:56:32',\WordPressNewsBot\Support::localDateTime(CronHealth::lastSuccess()));
        $this->assertTrue(CronHealth::isHealthy(CronHealth::HEALTH_TOLERANCE_SECONDS,$now));
        $this->assertSame('2026-08-15 13:15',\WordPressNewsBot\Support::localDateTime(CronHealth::nextExternalTrigger($now),'Y-m-d H:i'));
    }

    public function testHeartbeatBoundaryAndIstanbulConversion():void
    {
        $now=(new \DateTimeImmutable('2026-08-15 10:01:00 UTC'))->getTimestamp();update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',$now-1199),false);
        $this->assertTrue(CronHealth::isHealthy(CronHealth::HEALTH_TOLERANCE_SECONDS,$now));update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',$now-1201),false);$this->assertFalse(CronHealth::isHealthy(CronHealth::HEALTH_TOLERANCE_SECONDS,$now));
        $GLOBALS['wpnb_test_timezone']=new \DateTimeZone('Europe/Istanbul');$this->assertSame('2026-08-15 13:00:05',\WordPressNewsBot\Support::localDateTime('2026-08-15 10:00:05'));
    }

    public function testLegacyUtcAndOffsetValuesAreParsedIdempotently():void
    {
        $sql='2026-08-15 10:00:05';$iso='2026-08-15T13:00:05+03:00';
        $this->assertSame(\WordPressNewsBot\Support::utcTimestamp($sql),\WordPressNewsBot\Support::utcTimestamp($iso));
        $this->assertNull(\WordPressNewsBot\Support::utcTimestamp('2026-08-15 10:00'));
    }

    public function testNewlyEnabledAutomationWaitsForItsFirstExternalHeartbeat():void
    {
        $now=(new \DateTimeImmutable('2026-08-15 10:01:00 UTC'))->getTimestamp();update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',$now-60),false);update_option('wpnb_automation_enabled_at',gmdate('Y-m-d H:i:s',$now-30),false);
        $this->assertSame('waiting',CronHealth::state(true,$now));update_option('wpnb_heartbeat_last_success',gmdate('Y-m-d H:i:s',$now),false);$this->assertSame('healthy',CronHealth::state(true,$now));
    }
}

<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\AutomationService;

require_once dirname(__DIR__).'/includes/CronHealth.php';
require_once dirname(__DIR__).'/includes/AutomationService.php';

final class AutomationTimeTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];$GLOBALS['wpnb_test_timezone']=new \DateTimeZone('Europe/Istanbul');}
    public function testFortyFiveMinuteIntervalIsUtcAndAlignedToCron():void{$s=array_replace(\WordPressNewsBot\AutomationSettings::defaults(),['automation_enabled'=>1]);$now=(new \DateTimeImmutable('2026-08-15 10:01:00 UTC'))->getTimestamp();$last=(new \DateTimeImmutable('2026-08-15 10:00:05 UTC'))->getTimestamp();$this->assertSame('2026-08-15 14:00',\WordPressNewsBot\Support::localDateTime(AutomationService::nextEligibleTrigger($s,$now,$last),'Y-m-d H:i'));}
    public function testWindowStartEndAndActiveDayUseWordPressTimezone():void{$s=array_replace(\WordPressNewsBot\AutomationSettings::defaults(),['automation_enabled'=>1,'automation_days'=>[1]]);$before=(new \DateTimeImmutable('2026-08-17 04:59:00 UTC'))->getTimestamp();$this->assertSame('2026-08-17 08:00',\WordPressNewsBot\Support::localDateTime(AutomationService::nextEligibleTrigger($s,$before,0),'Y-m-d H:i'));$atEnd=(new \DateTimeImmutable('2026-08-17 19:59:00 UTC'))->getTimestamp();$this->assertSame('2026-08-17 23:00',\WordPressNewsBot\Support::localDateTime(AutomationService::nextEligibleTrigger($s,$atEnd,0),'Y-m-d H:i'));$after=(new \DateTimeImmutable('2026-08-17 20:00:00 UTC'))->getTimestamp();$this->assertSame('2026-08-24 08:00',\WordPressNewsBot\Support::localDateTime(AutomationService::nextEligibleTrigger($s,$after,0),'Y-m-d H:i'));}
    public function testLocalDayUtcBoundsCrossUtcDay():void{$local=new \DateTimeImmutable('2026-08-16 00:01:00',new \DateTimeZone('Europe/Istanbul'));[$start,$end]=\WordPressNewsBot\Support::siteDayUtcBounds($local);$this->assertSame('2026-08-15 21:00:00',$start);$this->assertSame('2026-08-16 21:00:00',$end);}
}

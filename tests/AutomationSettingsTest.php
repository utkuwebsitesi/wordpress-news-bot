<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\TestCase;
use WordPressNewsBot\AutomationSettings;
use WordPressNewsBot\OptionLock;

final class AutomationSettingsTest extends TestCase
{
    protected function setUp():void{$GLOBALS['wpnb_test_options']=[];}
    public function testRecommendedProfileIsSafeAndUsesIstanbulFriendlyWindow():void{$s=AutomationSettings::defaults();$this->assertSame(0,$s['automation_enabled']);$this->assertSame(20,$s['automation_daily_limit']);$this->assertSame(10,$s['automation_source_limit']);$this->assertSame('08:00',$s['automation_start']);$this->assertSame('23:00',$s['automation_end']);$this->assertSame(45,$s['automation_min_interval']);$this->assertSame(1,$s['automation_batch_limit']);$this->assertSame(12,$s['automation_max_age_hours']);$this->assertSame('publish',$s['publication_mode']);$this->assertSame(2,$s['automation_retry_limit']);$this->assertSame(0,$s['automation_process_existing']);}
    public function testSanitizationBoundsEveryAdministratorControlledValue():void{$s=AutomationSettings::sanitize(['automation_enabled'=>1,'automation_daily_limit'=>999,'automation_source_limit'=>0,'automation_start'=>'99:99','automation_end'=>'22:30','automation_days'=>[0,1,1,7,9],'automation_min_interval'=>0,'automation_batch_limit'=>99,'automation_max_age_hours'=>999,'publication_mode'=>'invalid','automation_retry_limit'=>99,'automation_backlog_since'=>'not-a-date','automation_backlog_limit'=>9999]);$this->assertSame(1,$s['automation_enabled']);$this->assertSame(200,$s['automation_daily_limit']);$this->assertSame(1,$s['automation_source_limit']);$this->assertSame('08:00',$s['automation_start']);$this->assertSame('22:30',$s['automation_end']);$this->assertSame([1,7],$s['automation_days']);$this->assertSame(1,$s['automation_min_interval']);$this->assertSame(20,$s['automation_batch_limit']);$this->assertSame(168,$s['automation_max_age_hours']);$this->assertSame('publish',$s['publication_mode']);$this->assertSame(10,$s['automation_retry_limit']);$this->assertSame('',$s['automation_backlog_since']);$this->assertSame(500,$s['automation_backlog_limit']);}
    public function testScheduleSpreadsDailyTargetAcrossWindowWithoutViolatingMinimum():void{$this->assertSame(45,AutomationSettings::spreadMinutes(AutomationSettings::defaults()));$this->assertSame(225,AutomationSettings::spreadMinutes(['automation_start'=>'08:00','automation_end'=>'23:00','automation_daily_limit'=>4,'automation_min_interval'=>45]));}
    public function testOptionLockRejectsConcurrencyAndRecoversExpiredLock():void{$lock=new OptionLock();$this->assertTrue($lock->acquire('automation_global',60));$this->assertFalse($lock->acquire('automation_global',60));update_option('wpnb_lock_automation_global',['expires'=>time()-1]);$this->assertTrue($lock->acquire('automation_global',60));$lock->release('automation_global');$this->assertFalse(get_option('wpnb_lock_automation_global',false));}
}

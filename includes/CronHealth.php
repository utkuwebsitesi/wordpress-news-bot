<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class CronHealth
{
    public const EXTERNAL_INTERVAL_MINUTES=15;
    public const HEALTH_TOLERANCE_SECONDS=20*MINUTE_IN_SECONDS;
    public const HEARTBEAT_HOOK='wpnb_heartbeat';
    public const TEST_HOOK='wpnb_heartbeat_test';
    private const LAST_SUCCESS='wpnb_heartbeat_last_success';
    private const LAST_ERROR='wpnb_heartbeat_last_error';
    private const TEST_RESULT='wpnb_heartbeat_test_result';

    public static function record(string $testId=''):void
    {
        $now=Support::now();
        update_option(self::LAST_SUCCESS,$now,false);
        update_option('wpnb_automation_heartbeat',$now,false);
        delete_option(self::LAST_ERROR);
        if(self::validTestId($testId))update_option(self::TEST_RESULT,['test_id'=>$testId,'time'=>$now],false);
    }

    public static function lastSuccess():string{return(string)get_option(self::LAST_SUCCESS,(string)get_option('wpnb_automation_heartbeat',''));}
    public static function lastError():string{return(string)get_option(self::LAST_ERROR,'');}
    public static function lastSuccessTimestamp():?int{return Support::utcTimestamp(self::lastSuccess());}
    public static function isHealthy(int$maxAge=self::HEALTH_TOLERANCE_SECONDS,?int$now=null):bool
    {
        $timestamp=self::lastSuccessTimestamp();
        return$timestamp!==null&&max(0,($now??Support::utcNowTimestamp())-$timestamp)<=$maxAge;
    }
    public static function state(bool$automationEnabled=false,?int$now=null):string{$now=$now??Support::utcNowTimestamp();$last=self::lastSuccessTimestamp();$enabledAt=Support::utcTimestamp((string)get_option('wpnb_automation_enabled_at',''));if($automationEnabled&&$enabledAt!==null&&($last===null||$last<$enabledAt)&&max(0,$now-$enabledAt)<=self::HEALTH_TOLERANCE_SECONDS)return'waiting';if(self::isHealthy(self::HEALTH_TOLERANCE_SECONDS,$now))return'healthy';return'unhealthy';}
    public static function nextExternalTrigger(?int$now=null):int{return Support::nextQuarterHour($now);}

    /** @return array{ok:bool,test_id:string,error:string} */
    public static function testConnection():array
    {
        $testId=bin2hex(random_bytes(8));delete_option(self::TEST_RESULT);
        $scheduled=wp_schedule_single_event(time()-1,self::TEST_HOOK,[$testId]);
        if(is_wp_error($scheduled)||$scheduled===false)return self::fail($testId,'schedule_failed');
        $url=(string)apply_filters('wpnb_cron_test_url',site_url('/wp-cron.php?doing_wp_cron'));
        $response=wp_safe_remote_post($url,['timeout'=>20,'blocking'=>true,'redirection'=>0,'sslverify'=>true,'headers'=>['Cache-Control'=>'no-cache']]);
        if(is_wp_error($response))return self::fail($testId,'loopback_failed');
        $status=wp_remote_retrieve_response_code($response);
        if($status<200||$status>=300)return self::fail($testId,'http_status_invalid');
        $result=(array)get_option(self::TEST_RESULT,[]);
        if(!isset($result['test_id'])||!hash_equals($testId,(string)$result['test_id']))return self::fail($testId,'callback_not_received');
        DiagnosticStore::record($testId,'cron','success','heartbeat_test',['http_status'=>$status,'heartbeat_utc'=>self::lastSuccess(),'heartbeat_local'=>Support::localDateTime(self::lastSuccess()),'healthy'=>self::isHealthy()]);
        return['ok'=>true,'test_id'=>$testId,'error'=>''];
    }

    private static function fail(string$testId,string$code):array
    {
        update_option(self::LAST_ERROR,$code,false);
        DiagnosticStore::record($testId,'cron',$code,'heartbeat_test');
        return['ok'=>false,'test_id'=>$testId,'error'=>$code];
    }
    private static function validTestId(string$value):bool{return(bool)preg_match('/^[a-f0-9]{16,64}$/',$value);}
}

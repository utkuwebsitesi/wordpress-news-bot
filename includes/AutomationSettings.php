<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class AutomationSettings
{
    public static function defaults():array{return['automation_enabled'=>0,'automation_daily_limit'=>20,'automation_source_limit'=>10,'automation_start'=>'08:00','automation_end'=>'23:00','automation_days'=>[1,2,3,4,5,6,7],'automation_min_interval'=>45,'automation_batch_limit'=>1,'automation_max_age_hours'=>12,'publication_mode'=>'publish','automation_require_image'=>1,'automation_require_ai'=>1,'automation_retry_limit'=>2,'automation_process_existing'=>0,'automation_backlog_since'=>'','automation_backlog_limit'=>20,'automation_catchup_limit'=>2,'automation_owner_user_id'=>0];}
    public static function merge(array$settings):array{return array_replace(self::defaults(),$settings);}
    public static function sanitize(array$input,array$current=[]):array
    {
        $value=array_replace(self::merge($current),$input);$days=array_values(array_unique(array_filter(array_map('intval',(array)($value['automation_days']??[])),static fn(int$d):bool=>$d>=1&&$d<=7)));sort($days);
        $mode=sanitize_key((string)($value['publication_mode']??'publish'));$since=sanitize_text_field((string)($value['automation_backlog_since']??''));if($since!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$since))$since='';
        return['automation_enabled'=>empty($value['automation_enabled'])?0:1,'automation_daily_limit'=>min(200,max(1,absint($value['automation_daily_limit']??20))),'automation_source_limit'=>min(100,max(1,absint($value['automation_source_limit']??10))),'automation_start'=>self::time((string)($value['automation_start']??'08:00'),'08:00'),'automation_end'=>self::time((string)($value['automation_end']??'23:00'),'23:00'),'automation_days'=>$days?:[1,2,3,4,5,6,7],'automation_min_interval'=>min(1440,max(1,absint($value['automation_min_interval']??45))),'automation_batch_limit'=>min(20,max(1,absint($value['automation_batch_limit']??1))),'automation_max_age_hours'=>min(168,max(1,absint($value['automation_max_age_hours']??12))),'publication_mode'=>in_array($mode,['publish','draft'],true)?$mode:'publish','automation_require_image'=>empty($value['automation_require_image'])?0:1,'automation_require_ai'=>empty($value['automation_require_ai'])?0:1,'automation_retry_limit'=>min(10,max(0,absint($value['automation_retry_limit']??2))),'automation_process_existing'=>empty($value['automation_process_existing'])?0:1,'automation_backlog_since'=>$since,'automation_backlog_limit'=>min(500,max(1,absint($value['automation_backlog_limit']??20))),'automation_catchup_limit'=>min(10,max(1,absint($value['automation_catchup_limit']??2))),'automation_owner_user_id'=>absint($value['automation_owner_user_id']??0)];
    }
    public static function spreadMinutes(array$settings):int{$s=self::merge($settings);[$sh,$sm]=array_map('intval',explode(':',$s['automation_start']));[$eh,$em]=array_map('intval',explode(':',$s['automation_end']));$window=($eh*60+$em)-($sh*60+$sm);if($window<=0)$window+=1440;return max((int)$s['automation_min_interval'],(int)floor($window/max(1,(int)$s['automation_daily_limit'])));}
    private static function time(string$value,string$fallback):string{return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$value)?$value:$fallback;}
}

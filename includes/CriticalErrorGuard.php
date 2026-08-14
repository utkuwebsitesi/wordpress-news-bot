<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class CriticalErrorGuard
{
    private static bool$registered=false;
    public static function register():void
    {
        if(self::$registered)return;self::$registered=true;register_shutdown_function([self::class,'capture']);
    }
    public static function capture():void
    {
        $error=error_get_last();if(!$error||!in_array((int)$error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true))return;
        $file=str_replace('\\','/',(string)($error['file']??''));$root=str_replace('\\','/',defined('WPNB_DIR')?WPNB_DIR:'');if($root===''||!str_starts_with($file,$root))return;
        $testId=bin2hex(random_bytes(8));$fingerprint=hash('sha256',basename($file).':'.(int)($error['line']??0).':'.(int)$error['type']);
        $settings=(array)get_option('wpnb_settings',[]);$settings['automation_enabled']=0;update_option('wpnb_settings',$settings,false);
        update_option('wpnb_last_critical_test_id',$testId,false);update_option('wpnb_heartbeat_last_error','plugin_critical_error',false);
        DiagnosticStore::record($testId,'runtime','plugin_critical_error','critical_shutdown',['error_fingerprint'=>$fingerprint]);
    }
}

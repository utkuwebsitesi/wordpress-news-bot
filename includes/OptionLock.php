<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class OptionLock
{
    public function acquire(string$key,int$ttl):bool{$name='wpnb_lock_'.sanitize_key($key);$token=['created'=>time(),'expires'=>time()+max(30,$ttl)];if(add_option($name,$token,'','no'))return true;$current=(array)get_option($name,[]);if((int)($current['expires']??0)>=time())return false;delete_option($name);return add_option($name,$token,'','no');}
    public function release(string$key):void{delete_option('wpnb_lock_'.sanitize_key($key));}
}

<?php
if(!defined('ABSPATH'))exit(1);
$hooks=[];foreach((array)_get_cron_array()as$events)foreach((array)$events as$hook=>$entries)$hooks[$hook]=($hooks[$hook]??0)+count((array)$entries);
$errors=[];foreach(['wpnb_poll_sources','wpnb_automation_tick','wpnb_heartbeat','wpnb_heartbeat_test']as$hook)if(!empty($hooks[$hook]))$errors[]='plugin-hook-remained:'.$hook;
if(($hooks['third_party_sentinel']??0)!==1)$errors[]='unrelated-hook-removed';
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);}echo json_encode($hooks,JSON_UNESCAPED_SLASHES)."\n";

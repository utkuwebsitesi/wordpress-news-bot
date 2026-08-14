<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DiagnosticStore
{
    private const OPTION='wpnb_diagnostics';
    public static function record(string $testId,string $stage,string $code,string $operation,array $context=[]):void
    {
        $allowed=['affected_rows','schema_fingerprint','db_errno','error_fingerprint','suggestion','table','from_engine','to_engine','rows_before','rows_after','checksum_before','checksum_after','total_rows','total_bytes','http_status'];$safe=[];foreach($allowed as$key)if(isset($context[$key]))$safe[$key]=is_int($context[$key])?(int)$context[$key]:sanitize_text_field((string)$context[$key]);
        $record=['test_id'=>sanitize_text_field($testId),'stage'=>sanitize_key($stage),'db_code'=>sanitize_key($code),'operation'=>sanitize_key($operation),'plugin_version'=>WPNB_VERSION,'schema_version'=>WPNB_SCHEMA_VERSION,'time'=>Support::now()]+$safe;
        $records=array_values(array_filter((array)get_option(self::OPTION,[]),static fn($row):bool=>is_array($row)&&isset($row['test_id'])));array_unshift($records,$record);update_option(self::OPTION,array_slice($records,0,100),false);
    }
    public static function find(string $testId):?array { $testId=strtolower($testId);foreach((array)get_option(self::OPTION,[])as$record)if(is_array($record)&&hash_equals((string)($record['test_id']??''),$testId))return$record;return null; }
    public static function recent(int $limit=20):array { return array_slice((array)get_option(self::OPTION,[]),0,max(1,min(100,$limit))); }
}

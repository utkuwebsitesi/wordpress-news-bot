<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseSchema
{
    /** @return array<string,array{columns:array<string,string>,indexes:array<string,array{unique:bool,columns:list<string>}>}> */
    public static function tables(): array
    {
        return [
            'sources'=>['columns'=>[
                'id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','name'=>'varchar(190) NOT NULL','feed_url'=>'text NOT NULL','canonical_hash'=>"char(64) NOT NULL DEFAULT ''",'active'=>'tinyint(1) NOT NULL DEFAULT 1','category_id'=>'bigint(20) unsigned NOT NULL DEFAULT 0','daily_quota'=>'int unsigned NOT NULL DEFAULT 10','interval_minutes'=>'int unsigned NOT NULL DEFAULT 60','reliability'=>"varchar(20) NOT NULL DEFAULT 'medium'",'author_id'=>'bigint(20) unsigned NOT NULL DEFAULT 0','post_status'=>"varchar(20) NOT NULL DEFAULT 'draft'",'show_attribution'=>'tinyint(1) NOT NULL DEFAULT 1','allowed_domains'=>'text NULL','last_success'=>'datetime NULL','last_checked_at'=>'datetime NULL','last_result'=>'varchar(255) NULL','last_error'=>'text NULL','created_at'=>'datetime NOT NULL','updated_at'=>'datetime NOT NULL',
            ],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'active'=>['unique'=>false,'columns'=>['active']],'canonical_hash'=>['unique'=>false,'columns'=>['canonical_hash']],'canonical_hash_unique'=>['unique'=>true,'columns'=>['canonical_hash']]]],
            'feed_items'=>['columns'=>[
                'id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','source_id'=>'bigint(20) unsigned NOT NULL','source_name'=>"varchar(190) NOT NULL DEFAULT ''",'source_feed_url'=>'text NULL','guid'=>"varchar(255) NOT NULL DEFAULT ''",'source_url'=>'text NOT NULL','normalized_url'=>"varchar(512) NOT NULL DEFAULT ''",'title'=>'text NOT NULL','excerpt'=>'text NULL','content_hash'=>"char(64) NOT NULL DEFAULT ''",'published_at'=>'datetime NULL','status'=>"varchar(30) NOT NULL DEFAULT 'new'",'raw_data'=>'longtext NULL','created_at'=>'datetime NOT NULL','updated_at'=>'datetime NOT NULL',
            ],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'source_guid'=>['unique'=>true,'columns'=>['source_id','guid']],'source_id'=>['unique'=>false,'columns'=>['source_id']],'normalized_url'=>['unique'=>false,'columns'=>['normalized_url']],'content_hash'=>['unique'=>false,'columns'=>['content_hash']]]],
            'jobs'=>['columns'=>['id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','feed_item_id'=>'bigint(20) unsigned NOT NULL','type'=>'varchar(30) NOT NULL','status'=>"varchar(20) NOT NULL DEFAULT 'queued'",'attempts'=>'tinyint unsigned NOT NULL DEFAULT 0','locked_at'=>'datetime NULL','error_message'=>'text NULL','created_at'=>'datetime NOT NULL','updated_at'=>'datetime NOT NULL'],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'feed_item_id'=>['unique'=>false,'columns'=>['feed_item_id']],'status'=>['unique'=>false,'columns'=>['status']]]],
            'ai_generations'=>['columns'=>['id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','feed_item_id'=>'bigint(20) unsigned NOT NULL','provider'=>'varchar(50) NOT NULL','model'=>'varchar(100) NOT NULL','output_json'=>'longtext NOT NULL','input_tokens'=>'int unsigned NOT NULL DEFAULT 0','output_tokens'=>'int unsigned NOT NULL DEFAULT 0','estimated_cost'=>'decimal(12,6) NOT NULL DEFAULT 0','created_at'=>'datetime NOT NULL'],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']]]],
            'logs'=>['columns'=>['id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','level'=>'varchar(20) NOT NULL','event'=>'varchar(100) NOT NULL','message'=>'text NOT NULL','context_json'=>'longtext NULL','created_at'=>'datetime NOT NULL'],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'level'=>['unique'=>false,'columns'=>['level']],'event'=>['unique'=>false,'columns'=>['event']]]],
            'daily_usage'=>['columns'=>['id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','usage_date'=>'date NOT NULL','ai_requests'=>'int unsigned NOT NULL DEFAULT 0','input_tokens'=>'int unsigned NOT NULL DEFAULT 0','output_tokens'=>'int unsigned NOT NULL DEFAULT 0','estimated_cost'=>'decimal(12,6) NOT NULL DEFAULT 0'],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'usage_date'=>['unique'=>true,'columns'=>['usage_date']]]],
            'migration_journal'=>['columns'=>['id'=>'bigint(20) unsigned NOT NULL AUTO_INCREMENT','migration'=>'varchar(100) NOT NULL','status'=>'varchar(30) NOT NULL','source_count'=>'int unsigned NOT NULL DEFAULT 0','snapshot_json'=>'longtext NOT NULL','report_json'=>'longtext NULL','created_at'=>'datetime NOT NULL','completed_at'=>'datetime NULL'],'indexes'=>['PRIMARY'=>['unique'=>true,'columns'=>['id']],'migration_status'=>['unique'=>false,'columns'=>['migration','status']]]],
        ];
    }

    public static function createSql(string $logical, string $charsetCollate): string
    {
        $spec=self::tables()[$logical]??throw new \InvalidArgumentException('Unknown table schema.');
        $parts=[];foreach($spec['columns']as$name=>$ddl)$parts[]="`$name` $ddl";
        foreach($spec['indexes']as$name=>$index){$columns=implode(',',array_map(static fn(string$c):string=>"`$c`",$index['columns']));if($name==='PRIMARY')$parts[]="PRIMARY KEY ($columns)";else$parts[]=($index['unique']?'UNIQUE ':'')."KEY `$name` ($columns)";}
        return 'CREATE TABLE IF NOT EXISTS '.self::identifier(Support::table($logical)).' ('.implode(', ',$parts).') ENGINE=InnoDB '.$charsetCollate;
    }

    public static function identifier(string $name): string { return '`'.str_replace('`','``',$name).'`'; }

    /** @param array<string,mixed> $data @return list<string> */
    public static function formatsFor(string $logical, array $data): array
    {
        $columns=self::tables()[$logical]['columns']??throw new \InvalidArgumentException('Unknown table schema.');$formats=[];
        foreach(array_keys($data)as$column){$ddl=$columns[$column]??throw new \InvalidArgumentException('Unknown table column.');$type=strtolower(strtok($ddl,' '));$formats[]=preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)/',$type)?'%d':(str_starts_with($type,'decimal')?'%f':'%s');}
        return$formats;
    }
}

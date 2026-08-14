<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PDO;

final class SqliteWpdb
{
    public string $prefix='wp_'; public int $insert_id=0; public string $last_error=''; public bool $failRelationMove=false; public bool $failIndex=false; public ?int $forcedSourceDeleteCount=null;
    private PDO $pdo;
    public function __construct(){
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        foreach([
            'CREATE TABLE wp_wpnb_sources (id INTEGER PRIMARY KEY,name TEXT,feed_url TEXT,canonical_hash TEXT DEFAULT "",active INTEGER DEFAULT 1,category_id INTEGER DEFAULT 0,allowed_domains TEXT,import_images INTEGER DEFAULT 1,draft_without_image INTEGER DEFAULT 1,use_og_image INTEGER DEFAULT 0,last_checked_at TEXT,last_result TEXT,last_error TEXT,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE wp_wpnb_feed_items (id INTEGER PRIMARY KEY,source_id INTEGER,guid TEXT,normalized_url TEXT,content_hash TEXT,source_url TEXT,title TEXT,wordpress_post_id INTEGER DEFAULT 0,status TEXT,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE wp_wpnb_jobs (id INTEGER PRIMARY KEY,feed_item_id INTEGER,status TEXT)',
            'CREATE TABLE wp_wpnb_ai_generations (id INTEGER PRIMARY KEY,feed_item_id INTEGER)',
            'CREATE TABLE wp_wpnb_logs (id INTEGER PRIMARY KEY AUTOINCREMENT,level TEXT,event TEXT,message TEXT,context_json TEXT,created_at TEXT)',
            'CREATE TABLE wp_wpnb_migration_journal (id INTEGER PRIMARY KEY AUTOINCREMENT,migration TEXT,status TEXT,source_count INTEGER,snapshot_json TEXT,report_json TEXT,created_at TEXT,completed_at TEXT)'
        ]as$sql)$this->pdo->exec($sql);
    }
    public function seed(string$table,array$row):void{$this->insert($table,$row);}
    public function prepare(string$sql,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:$this->pdo->quote((string)$value);$sql=preg_replace('/%[ds]/',$replacement,$sql,1)??$sql;}return$sql;}
    public function get_results(string$sql,mixed$format=null):array{$sql=str_replace(' FOR UPDATE','',$sql);return$this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
    public function get_row(string$sql,mixed$format=null):?array{$row=$this->get_results($sql,$format)[0]??null;return$row?:null;}
    public function get_var(string$sql):mixed{if(str_starts_with($sql,'SHOW INDEX'))return$this->hasIndex()?'canonical_hash_unique':null;$value=$this->pdo->query(str_replace(' FOR UPDATE','',$sql))->fetchColumn();return$value===false?null:$value;}
    public function query(string$sql):int|false{
        if($sql==='START TRANSACTION'){return$this->pdo->beginTransaction()?0:false;}if($sql==='COMMIT'){return$this->pdo->commit()?0:false;}if($sql==='ROLLBACK'){return$this->pdo->inTransaction()&&$this->pdo->rollBack()?0:false;}
        if(str_contains($sql,'ADD UNIQUE KEY canonical_hash_unique')){if($this->failIndex)return false;return$this->pdo->exec('CREATE UNIQUE INDEX canonical_hash_unique ON wp_wpnb_sources(canonical_hash)');}
        if(str_contains($sql,'DROP INDEX canonical_hash_unique'))return$this->pdo->exec('DROP INDEX canonical_hash_unique');
        if($this->failRelationMove&&str_starts_with($sql,'UPDATE wp_wpnb_jobs SET feed_item_id'))return false;
        return$this->pdo->exec(str_replace(' FOR UPDATE','',$sql));
    }
    public function insert(string$table,array$data):bool{$ok=$this->write('INSERT',$table,$data);if($ok)$this->insert_id=(int)$this->pdo->lastInsertId();return$ok;}
    public function replace(string$table,array$data):bool{return$this->write('INSERT OR REPLACE',$table,$data);}
    public function update(string$table,array$data,array$where,array$formats=[],array$whereFormats=[]):int|false{$set=[];$params=[];foreach($data as$k=>$v){$set[]="$k=?";$params[]=$v;}$conditions=[];foreach($where as$k=>$v){$conditions[]="$k=?";$params[]=$v;}try{$stmt=$this->pdo->prepare("UPDATE $table SET ".implode(',',$set).' WHERE '.implode(' AND ',$conditions));$stmt->execute($params);return$stmt->rowCount();}catch(\Throwable$e){$this->last_error=$e->getMessage();return false;}}
    public function delete(string$table,array$where,array$formats=[]):int|false{if($table==='wp_wpnb_sources'&&$this->forcedSourceDeleteCount!==null)return$this->forcedSourceDeleteCount;$conditions=[];$params=[];foreach($where as$k=>$v){$conditions[]="$k=?";$params[]=$v;}$stmt=$this->pdo->prepare("DELETE FROM $table WHERE ".implode(' AND ',$conditions));$stmt->execute($params);return$stmt->rowCount();}
    private function write(string$verb,string$table,array$data):bool{try{$cols=array_keys($data);$stmt=$this->pdo->prepare("$verb INTO $table (".implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');return$stmt->execute(array_values($data));}catch(\Throwable$e){$this->last_error=$e->getMessage();return false;}}
    private function hasIndex():bool{$rows=$this->pdo->query("PRAGMA index_list('wp_wpnb_sources')")->fetchAll(PDO::FETCH_ASSOC);foreach($rows as$row)if($row['name']==='canonical_hash_unique')return true;return false;}
}

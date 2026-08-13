<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PDO;

final class MariaDbWpdb
{
    public string $prefix='wpnb_it_';public int $insert_id=0;public string $last_error='';public int $last_errno=0;public string $charset='utf8mb4';public string $collate='utf8mb4_unicode_ci';public bool$simulateInnoDbUnavailable=false;public bool$simulateAlterDenied=false;public string$failAlterTable='';public array$queries=[];
    public function __construct(private readonly PDO $pdo){}
    public static function connectFromEnvironment():?self{$dsn=getenv('WPNB_TEST_DB_DSN');if(!$dsn)return null;$pdo=new PDO($dsn,(string)getenv('WPNB_TEST_DB_USER'),(string)getenv('WPNB_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return new self($pdo);}
    public function get_charset_collate():string{return'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';}
    public function prepare(string$sql,mixed ...$values):string{foreach($values as$value){$replacement=is_int($value)?(string)$value:$this->pdo->quote((string)$value);$sql=preg_replace('/%[dfs]/',$replacement,$sql,1)??$sql;}return$sql;}
    public function get_results(string$sql,mixed$format=null):array{if($this->simulateInnoDbUnavailable&&$sql==='SHOW ENGINES')return[['Engine'=>'InnoDB','Support'=>'NO']];if($this->simulateAlterDenied&&$sql==='SHOW GRANTS FOR CURRENT_USER')return[['Grant'=>'GRANT SELECT, INSERT, UPDATE, DELETE ON `wpnb_test`.* TO user']];try{return$this->pdo->query($sql)->fetchAll();}catch(\PDOException$e){$this->capture($e);return[];}}
    public function get_row(string$sql,mixed$format=null):?array{$rows=$this->get_results($sql,$format);return$rows[0]??null;}
    public function get_var(string$sql):mixed{try{$value=$this->pdo->query($sql)->fetchColumn();return$value===false?null:$value;}catch(\PDOException$e){$this->capture($e);return null;}}
    public function get_col(string$sql):array{try{return$this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);}catch(\PDOException$e){$this->capture($e);return[];}}
    public function query(string$sql):int|false{$this->queries[]=$sql;if($this->failAlterTable!==''&&str_starts_with($sql,'ALTER TABLE')&&str_contains($sql,'`'.$this->prefix.'wpnb_'.$this->failAlterTable.'`')){$this->last_error='Command denied to user for ALTER TABLE';$this->last_errno=1142;return false;}try{$this->clear();return$this->pdo->exec($sql);}catch(\PDOException$e){$this->capture($e);return false;}}
    public function insert(string$table,array$data,array$formats=[]):bool{$result=$this->write('INSERT',$table,$data);if($result!==false)$this->insert_id=(int)$this->pdo->lastInsertId();return$result!==false;}
    public function replace(string$table,array$data,array$formats=[]):bool{return$this->write('REPLACE',$table,$data)!==false;}
    public function update(string$table,array$data,array$where,array$formats=[],array$whereFormats=[]):int|false{$set=[];$conditions=[];$params=[];foreach($data as$key=>$value){$set[]="`$key`=?";$params[]=$value;}foreach($where as$key=>$value){$conditions[]="`$key`=?";$params[]=$value;}return$this->statement("UPDATE `$table` SET ".implode(',',$set).' WHERE '.implode(' AND ',$conditions),$params);}
    public function delete(string$table,array$where,array$formats=[]):int|false{$conditions=[];$params=[];foreach($where as$key=>$value){$conditions[]="`$key`=?";$params[]=$value;}return$this->statement("DELETE FROM `$table` WHERE ".implode(' AND ',$conditions),$params);}
    public function reset():void{foreach(array_reverse(array_keys(\WordPressNewsBot\DatabaseSchema::tables()))as$logical)$this->pdo->exec('DROP TABLE IF EXISTS `'.$this->prefix.'wpnb_'.$logical.'`');$this->pdo->exec('DROP TABLE IF EXISTS `'.$this->prefix.'unrelated`');$this->simulateInnoDbUnavailable=false;$this->simulateAlterDenied=false;$this->failAlterTable='';$this->queries=[];$this->clear();}
    private function write(string$verb,string$table,array$data):int|false{$columns=array_keys($data);return$this->statement("$verb INTO `$table` (`".implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($data));}
    private function statement(string$sql,array$params):int|false{try{$this->clear();$statement=$this->pdo->prepare($sql);$statement->execute($params);return$statement->rowCount();}catch(\PDOException$e){$this->capture($e);return false;}}
    private function capture(\PDOException$e):void{$this->last_error=$e->getMessage();$info=$e->errorInfo;$this->last_errno=(int)($info[1]??$e->getCode());}
    private function clear():void{$this->last_error='';$this->last_errno=0;}
}

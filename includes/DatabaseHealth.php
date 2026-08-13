<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseHealth
{
    public function __construct(private readonly object $db) {}

    /** @return array{status:string,issues:list<array<string,mixed>>,fingerprint:string,tables:array<string,array<string,mixed>>,stored_schema:string,expected_schema:string} */
    public function inspect(): array
    {
        $issues=[];$safeTables=[];
        foreach(DatabaseSchema::tables()as$logical=>$spec){$table=Support::table($logical);$exists=$this->tableExists($table);$tableInfo=['exists'=>$exists,'columns'=>[],'indexes'=>[],'engine'=>'','collation'=>''];
            if(!$exists){$issues[]=['code'=>'table_missing','table'=>$logical];$safeTables[$logical]=$tableInfo;continue;}
            try{
                $columns=(array)$this->db->get_results('SHOW FULL COLUMNS FROM '.DatabaseSchema::identifier($table),ARRAY_A);$columnRows=[];foreach($columns as$row){$name=(string)($row['Field']??'');$columnRows[$name]=$row;$tableInfo['columns'][$name]=['type'=>strtolower((string)($row['Type']??'')),'nullable'=>strtoupper((string)($row['Null']??''))==='YES','default'=>$row['Default']??null,'extra'=>strtolower((string)($row['Extra']??''))];}
                foreach($spec['columns']as$name=>$ddl){if(!isset($columnRows[$name])){$issues[]=['code'=>'column_missing','table'=>$logical,'column'=>$name];continue;}$row=$columnRows[$name];$actualDefinition=(string)($row['Type']??'');$expected=self::baseType($ddl);$actual=self::baseType($actualDefinition);$unsignedExpected=str_contains(strtolower($ddl),'unsigned');$unsignedActual=str_contains(strtolower($actualDefinition),'unsigned');if($actual!==$expected||$unsignedActual!==$unsignedExpected)$issues[]=['code'=>'column_type_mismatch','table'=>$logical,'column'=>$name,'expected'=>$expected.($unsignedExpected?' unsigned':''),'actual'=>$actual.($unsignedActual?' unsigned':'')];$nullable=!str_contains(strtoupper($ddl),'NOT NULL');if((strtoupper((string)($row['Null']??''))==='YES')!==$nullable)$issues[]=['code'=>'column_nullability_mismatch','table'=>$logical,'column'=>$name];$auto=str_contains(strtoupper($ddl),'AUTO_INCREMENT');if(str_contains(strtolower((string)($row['Extra']??'')),'auto_increment')!==$auto)$issues[]=['code'=>'column_extra_mismatch','table'=>$logical,'column'=>$name];$expectedDefault=self::expectedDefault($ddl);$actualDefault=(string)($row['Default']??'');if($expectedDefault['defined']&&!self::defaultsEqual($expectedDefault['value'],$actualDefault))$issues[]=['code'=>'column_default_mismatch','table'=>$logical,'column'=>$name,'expected'=>$expectedDefault['value'],'actual'=>$actualDefault];}
                $indexes=(array)$this->db->get_results('SHOW INDEX FROM '.DatabaseSchema::identifier($table),ARRAY_A);$grouped=[];foreach($indexes as$row){$key=(string)($row['Key_name']??'');$seq=(int)($row['Seq_in_index']??1);$grouped[$key]['unique']=((int)($row['Non_unique']??1)===0);$grouped[$key]['columns'][$seq]=(string)($row['Column_name']??'');$grouped[$key]['sub_parts'][$seq]=isset($row['Sub_part'])?(int)$row['Sub_part']:null;}foreach($grouped as$key=>$index){ksort($index['columns']);ksort($index['sub_parts']);$tableInfo['indexes'][$key]=['unique'=>$index['unique'],'columns'=>array_values($index['columns']),'sub_parts'=>array_values($index['sub_parts'])];}
                foreach($spec['indexes']as$name=>$expected){$actual=$tableInfo['indexes'][$name]??null;if(!$actual){$issues[]=['code'=>'index_missing','table'=>$logical,'index'=>$name];}elseif($actual['unique']!==$expected['unique']||$actual['columns']!==$expected['columns']||array_filter($actual['sub_parts'],static fn($part):bool=>$part!==null)!==[]){$issues[]=['code'=>'index_mismatch','table'=>$logical,'index'=>$name];}}
                $status=(array)$this->db->get_row($this->db->prepare('SHOW TABLE STATUS LIKE %s',$table),ARRAY_A);$tableInfo['engine']=(string)($status['Engine']??'');$tableInfo['collation']=(string)($status['Collation']??'');$tableInfo['rows']=(int)($status['Rows']??0);$tableInfo['bytes']=(int)($status['Data_length']??0)+(int)($status['Index_length']??0);if($tableInfo['engine']!==''&&strtolower($tableInfo['engine'])!=='innodb')$issues[]=['code'=>'engine_mismatch','table'=>$logical,'expected'=>'InnoDB','actual'=>$tableInfo['engine']];
                $expectedCharset=strtolower((string)($this->db->charset??''));$expectedCollation=strtolower((string)($this->db->collate??''));$actualCollation=strtolower($tableInfo['collation']);if($actualCollation!==''&&(($expectedCollation!==''&&$actualCollation!==$expectedCollation)||($expectedCollation===''&&$expectedCharset!==''&&!str_starts_with($actualCollation,$expectedCharset.'_'))))$issues[]=['code'=>'collation_mismatch','table'=>$logical,'expected'=>$expectedCollation?:$expectedCharset,'actual'=>$tableInfo['collation']];
            }catch(\Throwable){$issues[]=['code'=>'metadata_query_failed','table'=>$logical];}
            $safeTables[$logical]=$tableInfo;
        }
        $physicalHealthy=$issues===[];$stored=(string)get_option('wpnb_schema_version','');if($physicalHealthy&&$stored!==WPNB_SCHEMA_VERSION)$issues[]=['code'=>'schema_version_mismatch','stored'=>$stored,'expected'=>WPNB_SCHEMA_VERSION];
        $schemaOnly=[];foreach($safeTables as$logical=>$table){$schemaOnly[$logical]=array_diff_key($table,['rows'=>true,'bytes'=>true]);}
        $fingerprint=substr(hash('sha256',Support::json($schemaOnly)),0,24);
        return ['status'=>$issues===[]?'healthy':'repair_required','issues'=>$issues,'fingerprint'=>$fingerprint,'tables'=>$safeTables,'stored_schema'=>$stored,'expected_schema'=>WPNB_SCHEMA_VERSION];
    }

    public function sourceReady(): array
    {
        $report=$this->inspect();$sourceIssues=array_values(array_filter($report['issues'],static fn(array$i):bool=>($i['table']??'sources')==='sources'||$i['code']==='schema_version_mismatch'));
        return ['ready'=>$sourceIssues===[],'issues'=>$sourceIssues,'fingerprint'=>$report['fingerprint']];
    }

    private function tableExists(string $table): bool { try{return (string)$this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s',$table))===$table;}catch(\Throwable){return false;} }
    private static function baseType(string $definition): string { $type=strtolower(trim(explode(' ',trim($definition))[0]));return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)$/','$1',$type)??$type; }
    private static function expectedDefault(string$definition):array{if(!preg_match('/\bDEFAULT\s+(?:\'([^\']*)\'|([^\s,]+))/i',$definition,$match))return['defined'=>false,'value'=>''];return['defined'=>true,'value'=>(string)($match[1]!==''?$match[1]:($match[2]??''))];}
    private static function defaultsEqual(string$expected,string$actual):bool{return is_numeric($expected)&&is_numeric($actual)?(float)$expected===(float)$actual:$expected===$actual;}
}

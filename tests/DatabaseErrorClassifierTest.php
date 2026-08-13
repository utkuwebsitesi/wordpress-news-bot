<?php
declare(strict_types=1);
namespace WordPressNewsBot\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordPressNewsBot\DatabaseErrorClassifier;

final class DatabaseErrorClassifierTest extends TestCase
{
    public static function errors():array{return[
        ['db_table_missing',1146,'Table does not exist'],['db_column_missing',1054,'Unknown column'],['db_required_value_missing',1364,'Field has no default'],['db_data_mismatch',1406,'Data too long'],['db_duplicate',1062,'Duplicate entry'],['db_index_missing',1091,'Key does not exist'],['db_invalid_default',1067,'Invalid default'],['db_collation_mismatch',1267,'Illegal mix of collations'],['db_permission_denied',1142,'command denied'],['db_unknown',0,'Unclassified engine failure'],
    ];}
    #[DataProvider('errors')]
    public function testErrorsAreMappedWithoutExposingRawMessage(string$expected,int$errno,string$message):void{$result=DatabaseErrorClassifier::classify($message,$errno);$this->assertSame($expected,$result['code']);$this->assertSame($errno,$result['errno']);$this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/',$result['error_fingerprint']);$this->assertArrayNotHasKey('message',$result);}
}

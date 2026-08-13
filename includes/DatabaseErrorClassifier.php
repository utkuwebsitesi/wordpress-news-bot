<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseErrorClassifier
{
    /** @return array{code:string,suggestion:string,errno:int,error_fingerprint:string} */
    public static function classify(string $error, int $errno = 0): array
    {
        $message = strtolower($error);
        $code = match (true) {
            in_array($errno, [1146], true) || str_contains($message, "doesn't exist") => 'db_table_missing',
            in_array($errno, [1054], true) || str_contains($message, 'unknown column') => 'db_column_missing',
            in_array($errno, [1364], true) || str_contains($message, 'doesn\'t have a default value') => 'db_required_value_missing',
            in_array($errno, [1264, 1406], true) || str_contains($message, 'data too long') || str_contains($message, 'out of range') => 'db_data_mismatch',
            $errno === 1062 || str_contains($message, 'duplicate entry') => 'db_duplicate',
            in_array($errno, [1091, 1176], true) || str_contains($message, 'key does not exist') => 'db_index_missing',
            in_array($errno, [1067, 1292], true) || str_contains($message, 'invalid default') => 'db_invalid_default',
            $errno === 1267 || str_contains($message, 'collation') || str_contains($message, 'character set') => 'db_collation_mismatch',
            in_array($errno, [1044, 1045, 1142, 1227], true) || str_contains($message, 'access denied') || str_contains($message, 'command denied') => 'db_permission_denied',
            default => 'db_unknown',
        };
        $suggestions = [
            'db_table_missing'=>'Run the safe database repair from System Health.',
            'db_column_missing'=>'Run the safe database repair from System Health.',
            'db_required_value_missing'=>'Review required source columns in Database Health.',
            'db_data_mismatch'=>'Review column types and lengths before changing existing data.',
            'db_duplicate'=>'Remove or merge duplicate canonical sources before creating the unique index.',
            'db_index_missing'=>'Run the safe database repair after duplicate validation.',
            'db_invalid_default'=>'Review the physical column default before repair.',
            'db_collation_mismatch'=>'Align the table charset and collation with WordPress after taking a backup.',
            'db_permission_denied'=>'Grant the WordPress database user the required CREATE and ALTER privileges.',
            'db_unknown'=>'Use the Test ID in Diagnostics and review the database health report.',
        ];
        return ['code'=>$code,'suggestion'=>$suggestions[$code],'errno'=>$errno,'error_fingerprint'=>substr(hash('sha256',$message),0,16)];
    }
}

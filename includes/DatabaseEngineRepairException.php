<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DatabaseEngineRepairException extends \RuntimeException
{
    public function __construct(public readonly string $resultCode,public readonly array $diagnostics=[]){parent::__construct($resultCode);}
}

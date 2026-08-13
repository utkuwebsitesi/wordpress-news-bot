<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceTestException extends \RuntimeException
{
    public readonly string $resultCode;
    public readonly string $testId;
    public readonly array $diagnostics;

    public function __construct(string $resultCode, string $testId, array $diagnostics = [], ?\Throwable $previous = null)
    {
        $this->resultCode=$resultCode;$this->testId=$testId;
        if(isset($diagnostics['_started'])){$diagnostics['duration_ms']=max(0,(int)round((microtime(true)-(float)$diagnostics['_started'])*1000));unset($diagnostics['_started']);}
        $this->diagnostics=$diagnostics;
        parent::__construct($resultCode,0,$previous);
    }
}

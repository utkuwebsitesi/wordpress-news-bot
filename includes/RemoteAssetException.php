<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class RemoteAssetException extends \RuntimeException
{
    public function __construct(public readonly string$resultCode,?\Throwable$previous=null){parent::__construct('Remote asset request failed.',0,$previous);}
}

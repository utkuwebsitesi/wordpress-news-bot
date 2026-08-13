<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class Credentials
{
    public static function openAiKey(): string
    {
        if (defined('NYB_OPENAI_API_KEY') && is_string(NYB_OPENAI_API_KEY)) return trim(NYB_OPENAI_API_KEY);
        return '';
    }
}

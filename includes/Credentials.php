<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class Credentials
{
    public static function openAiKey(): string
    {
        if (defined('WPNB_OPENAI_API_KEY') && is_string(WPNB_OPENAI_API_KEY)) return trim(WPNB_OPENAI_API_KEY);
        return '';
    }
}

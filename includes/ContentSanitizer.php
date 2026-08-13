<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class ContentSanitizer
{
    public static function clean(string $html): string
    {
        $allowed = ['p'=>[],'br'=>[],'strong'=>[],'em'=>[],'ul'=>[],'ol'=>[],'li'=>[],'blockquote'=>[],'a'=>['href'=>true,'title'=>true,'rel'=>true,'target'=>true]];
        return wp_kses($html, $allowed);
    }
}

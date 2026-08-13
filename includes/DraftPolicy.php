<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DraftPolicy
{
    public static function postArgs(array $output, int $author, int $category, string $content): array { return ['post_title'=>$output['title'],'post_excerpt'=>$output['excerpt'],'post_content'=>$content,'post_status'=>'draft','post_type'=>'post','post_author'=>$author,'post_category'=>[$category]]; }
    public static function allowedStatus(string $status): bool { return $status === 'draft'; }
}

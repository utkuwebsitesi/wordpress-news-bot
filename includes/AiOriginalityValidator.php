<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class AiOriginalityValidator
{
    public static function assertValid(array$output,array$item):void
    {
        $title=self::normalize((string)($output['title']??''));$sourceTitle=self::normalize((string)($item['title']??''));
        if($title===''||($sourceTitle!==''&&hash_equals($sourceTitle,$title)))throw new AiOutputRejectedException(__('The generated headline was not sufficiently original.','wordpress-news-bot'));
        $plain=trim(wp_strip_all_tags((string)($output['content_html']??'')));$normalized=self::normalize($plain);$words=self::words($normalized);
        if(mb_strlen($plain,'UTF-8')<180||count($words)<25)throw new AiOutputRejectedException(__('The generated article was too short to use safely.','wordpress-news-bot'));
        $source=self::normalize((string)($item['excerpt']??''));$sourceWords=self::words($source);
        if(count($sourceWords)>=12&&self::containsSequence($words,$sourceWords,12))throw new AiOutputRejectedException(__('The generated article copied a long source passage.','wordpress-news-bot'));
        $sourceUrl=trim((string)($item['source_url']??''));if($sourceUrl!==''&&str_contains(html_entity_decode((string)$output['content_html'],ENT_QUOTES|ENT_HTML5,'UTF-8'),$sourceUrl))throw new AiOutputRejectedException(__('The generated article included the original source URL.','wordpress-news-bot'));
    }
    public static function normalize(string$value):string{$value=html_entity_decode(wp_strip_all_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');$value=mb_strtolower($value,'UTF-8');return trim((string)preg_replace('/[^\p{L}\p{N}]+/u',' ',$value));}
    private static function words(string$value):array{return array_values(array_filter(preg_split('/\s+/u',$value)?:[]));}
    private static function containsSequence(array$output,array$source,int$length):bool{$need=implode(' ',array_slice($source,0,$length));if(str_contains(implode(' ',$output),$need))return true;for($i=1,$max=count($source)-$length;$i<=$max;$i++)if(str_contains(implode(' ',$output),implode(' ',array_slice($source,$i,$length))))return true;return false;}
}

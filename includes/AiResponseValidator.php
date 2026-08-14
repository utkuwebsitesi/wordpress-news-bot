<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class AiResponseValidator
{
    public const REQUIRED = ['title','excerpt','content_html','suggested_tags','seo_title','seo_description'];
    public static function schema(): array { return ['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string','minLength'=>1,'maxLength'=>180],'excerpt'=>['type'=>'string','minLength'=>40,'maxLength'=>500],'content_html'=>['type'=>'string','minLength'=>180],'suggested_tags'=>['type'=>'array','items'=>['type'=>'string','maxLength'=>50],'maxItems'=>8],'seo_title'=>['type'=>'string','maxLength'=>180],'seo_description'=>['type'=>'string','maxLength'=>160]],'required'=>self::REQUIRED]; }
    /** @return array<string,mixed> */
    public static function validate(mixed $decoded): array
    {
        if (!is_array($decoded)) throw new AiOutputRejectedException(__('The AI response was not a JSON object.','wordpress-news-bot'));
        foreach (self::REQUIRED as $field) if (!array_key_exists($field, $decoded)) throw new AiOutputRejectedException(__('The AI response was missing a required field.','wordpress-news-bot'));
        if (!is_string($decoded['title']) || trim($decoded['title']) === '' || !is_string($decoded['excerpt']) || !is_string($decoded['content_html']) || !is_string($decoded['seo_title']) || !is_string($decoded['seo_description']) || !is_array($decoded['suggested_tags'])) throw new AiOutputRejectedException(__('The AI response did not match the required schema.','wordpress-news-bot'));
        $title=sanitize_text_field($decoded['title']);$excerpt=sanitize_textarea_field($decoded['excerpt']);$content=ContentSanitizer::clean($decoded['content_html']);$seoTitle=sanitize_text_field($decoded['seo_title']);$seoDescription=sanitize_textarea_field($decoded['seo_description']);
        if(mb_strlen($title,'UTF-8')>180||mb_strlen($excerpt,'UTF-8')<40||mb_strlen($excerpt,'UTF-8')>500||mb_strlen($content,'UTF-8')<180||mb_strlen($seoTitle,'UTF-8')>180||mb_strlen($seoDescription,'UTF-8')>160||count($decoded['suggested_tags'])>8)throw new AiOutputRejectedException(__('The AI response did not match the required schema.','wordpress-news-bot'));
        return ['title'=>$title,'excerpt'=>$excerpt,'content_html'=>$content,'suggested_tags'=>array_values(array_filter(array_map(static fn($tag) => sanitize_text_field((string) $tag), $decoded['suggested_tags']))),'seo_title'=>$seoTitle,'seo_description'=>$seoDescription];
    }
}

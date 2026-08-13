<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class AiResponseValidator
{
    public const REQUIRED = ['title','excerpt','content_html','suggested_tags','seo_title','seo_description'];
    public static function schema(): array { return ['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string','minLength'=>1,'maxLength'=>180],'excerpt'=>['type'=>'string','maxLength'=>500],'content_html'=>['type'=>'string','minLength'=>1],'suggested_tags'=>['type'=>'array','items'=>['type'=>'string','maxLength'=>50],'maxItems'=>8],'seo_title'=>['type'=>'string','maxLength'=>180],'seo_description'=>['type'=>'string','maxLength'=>160]],'required'=>self::REQUIRED]; }
    /** @return array<string,mixed> */
    public static function validate(mixed $decoded): array
    {
        if (!is_array($decoded)) throw new \RuntimeException('AI yanıtı JSON nesnesi değil.');
        foreach (self::REQUIRED as $field) if (!array_key_exists($field, $decoded)) throw new \RuntimeException('AI yanıtında zorunlu alan eksik.');
        if (!is_string($decoded['title']) || trim($decoded['title']) === '' || !is_string($decoded['excerpt']) || !is_string($decoded['content_html']) || !is_string($decoded['seo_title']) || !is_string($decoded['seo_description']) || !is_array($decoded['suggested_tags'])) throw new \RuntimeException('AI yanıtı şema doğrulamasından geçmedi.');
        return ['title'=>sanitize_text_field($decoded['title']),'excerpt'=>sanitize_textarea_field($decoded['excerpt']),'content_html'=>ContentSanitizer::clean($decoded['content_html']),'suggested_tags'=>array_values(array_filter(array_map(static fn($tag) => sanitize_text_field((string) $tag), $decoded['suggested_tags']))),'seo_title'=>sanitize_text_field($decoded['seo_title']),'seo_description'=>sanitize_textarea_field($decoded['seo_description'])];
    }
}

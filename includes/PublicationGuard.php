<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class PublicationGuard
{
    /** @return array{valid:bool,reason:string} */
    public function validate(int$postId,array$item,bool$requireImage=true):array
    {
        $post=get_post($postId);
        if(!$post||get_post_type($postId)!=='post')return['valid'=>false,'reason'=>'post_missing'];
        $title=trim((string)get_the_title($postId));$content=trim((string)get_post_field('post_content',$postId));
        if($title===''||mb_strlen(wp_strip_all_tags($content),'UTF-8')<120)return['valid'=>false,'reason'=>'ai_invalid'];try{AiOriginalityValidator::assertValid(['title'=>$title,'content_html'=>$content],$item);}catch(AiOutputRejectedException){return['valid'=>false,'reason'=>'ai_invalid'];}
        $sourceUrl=trim((string)($item['source_url']??''));$sourceName=trim((string)($item['source_name']??''));$visible=mb_strtolower(wp_strip_all_tags($title.' '.$content),'UTF-8');
        if(($sourceUrl!==''&&str_contains($content,$sourceUrl))||preg_match('~https?://~i',$content)||str_contains($visible,'kaynak:')||str_contains($visible,'source:'))return['valid'=>false,'reason'=>'source_visible'];
        if($sourceName!==''&&mb_strlen($sourceName,'UTF-8')>3&&preg_match('/(?:^|\s)'.preg_quote(mb_strtolower($sourceName,'UTF-8'),'/').'(?:\s|$)/u',$visible))return['valid'=>false,'reason'=>'source_visible'];
        $categories=wp_get_post_categories($postId);if(!$categories||!in_array((int)($item['category_id']??0),array_map('intval',$categories),true))return['valid'=>false,'reason'=>'category_missing'];
        foreach(['_wpnb_source_id','_wpnb_source_url','_wpnb_feed_item_id','_wpnb_content_hash','_wpnb_ai_provider','_wpnb_ai_model','_wpnb_generated_at']as$key)if((string)get_post_meta($postId,$key,true)==='')return['valid'=>false,'reason'=>'meta_missing'];
        if($requireImage){$thumbnail=(int)get_post_thumbnail_id($postId);if($thumbnail<1||get_post_type($thumbnail)!=='attachment'||!in_array((string)get_post_mime_type($thumbnail),['image/jpeg','image/png','image/webp'],true))return['valid'=>false,'reason'=>'image_missing'];}
        return['valid'=>true,'reason'=>''];
    }
}

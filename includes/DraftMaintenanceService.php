<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class DraftMaintenanceService
{
    public function affected():array{$ids=get_posts(['post_type'=>'post','post_status'=>'draft','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_wpnb_feed_item_id']);return array_values(array_filter(array_map('intval',$ids),fn(int$id):bool=>$this->cleaned((string)get_post_field('post_content',$id))!==null));}
    /** @return array{updated:int,skipped:int} */
    public function clean():array{$updated=0;$skipped=0;foreach($this->affected()as$id){if(get_post_status($id)!=='draft'){$skipped++;continue;}$content=(string)get_post_field('post_content',$id);$clean=$this->cleaned($content);if($clean===null){$skipped++;continue;}$result=wp_update_post(['ID'=>$id,'post_content'=>$clean],true);is_wp_error($result)?$skipped++:$updated++;}return['updated'=>$updated,'skipped'=>$skipped];}
    private function cleaned(string$content):?string{$pattern='~\s*<p><strong>(?:Source:|Kaynak:)</strong>\s+[^<]*(?:-|&ndash;|–)\s*<a\b[^>]*>(?:Source link|Kaynak bağlantısı)</a></p>\s*$~iu';$clean=preg_replace($pattern,'',$content,-1,$count);return$count===1?trim((string)$clean):null;}
}

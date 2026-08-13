<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceImporter
{
    public function import(int $sourceId): int
    {
        global $wpdb; $source=$wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Support::table('sources') . ' WHERE id=%d LIMIT 1',$sourceId),ARRAY_A); if (!$source || !(int)$source['active']) throw new \RuntimeException('Aktif kaynak bulunamadı.');
        $allowed=array_filter(array_map('trim',preg_split('/[\r\n,]+/',(string)($source['allowed_domains'] ?? '')) ?: [])); if (!Security::validateFeedUrl((string)$source['feed_url'],$allowed)) throw new \RuntimeException('Kaynak URL güvenlik doğrulamasından geçmedi.');
        $response=wp_safe_remote_get((string)$source['feed_url'],['timeout'=>20,'redirection'=>0,'headers'=>['Accept'=>'application/rss+xml, application/atom+xml, application/xml']]); if (is_wp_error($response)) throw new \RuntimeException('RSS kaynağına bağlanılamadı.'); $code=(int)wp_remote_retrieve_response_code($response); if ($code<200||$code>=300) throw new \RuntimeException('RSS kaynağı HTTP hatası döndürdü.');
        $items=(new FeedParser())->parse(wp_remote_retrieve_body($response)); $count=0; $detector=new DuplicateDetector($wpdb); foreach($items as $item){ if($detector->isDuplicate($item,$sourceId)) continue; $now=Support::now(); $wpdb->insert(Support::table('feed_items'),['source_id'=>$sourceId,'guid'=>sanitize_text_field($item['guid']),'source_url'=>esc_url_raw($item['source_url']),'normalized_url'=>Support::normalizeUrl($item['source_url']),'title'=>sanitize_text_field($item['title']),'excerpt'=>sanitize_textarea_field($item['excerpt']),'content_hash'=>$item['content_hash'],'status'=>'new','raw_data'=>null,'created_at'=>$now,'updated_at'=>$now],['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']); $count++; }
        $wpdb->update(Support::table('sources'),['last_success'=>Support::now(),'last_error'=>null,'updated_at'=>Support::now()],['id'=>$sourceId],['%s','%s','%s'],['%d']); return $count;
    }
}

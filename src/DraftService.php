<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class DraftService
{
    public function create(int $itemId): int
    {
        if (!Security::canReview()) throw new \RuntimeException('Bu işlem için yetkiniz yok.');
        $lock = 'nyb_draft_lock_' . $itemId; if (get_transient($lock)) throw new \RuntimeException('Bu haber başka bir işlem tarafından işleniyor.'); set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);
        global $wpdb; try {
            $item = $wpdb->get_row($wpdb->prepare('SELECT f.*, s.name AS source_name, s.category_id, s.post_status FROM ' . Support::table('feed_items') . ' f JOIN ' . Support::table('sources') . ' s ON s.id=f.source_id WHERE f.id=%d LIMIT 1', $itemId), ARRAY_A); if (!$item) throw new \RuntimeException('Haber kaydı bulunamadı.');
            $existing = get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','future'],'numberposts'=>1,'fields'=>'ids','meta_query'=>['relation'=>'OR',['key'=>'_nyb_feed_item_id','value'=>(string)$itemId],['key'=>'_nyb_source_url','value'=>esc_url_raw($item['source_url'])],['key'=>'_nyb_content_hash','value'=>sanitize_text_field($item['content_hash'])]]]); if ($existing) { $wpdb->update(Support::table('feed_items'), ['status'=>'duplicate','updated_at'=>Support::now()], ['id'=>$itemId], ['%s','%s'], ['%d']); return (int) $existing[0]; }
            $settings = get_option('nyb_settings', []); if ((int) ($settings['daily_ai_quota'] ?? 100) <= $this->todayUsage()) throw new \RuntimeException('Günlük AI kotası doldu.');
            $provider = (($settings['ai_provider'] ?? 'mock') === 'openai') ? new OpenAiProvider(Credentials::openAiKey(), (string) ($settings['ai_model'] ?? 'gpt-4o-mini')) : new MockAiProvider();
            $output = $provider->generate($item); $content = ContentSanitizer::clean((string) $output['content_html']) . '<p><strong>Kaynak:</strong> ' . esc_html($item['source_name']) . ' - <a rel="nofollow noopener" target="_blank" href="' . esc_url($item['source_url']) . '">Kaynak bağlantısı</a></p>';
            $postId = wp_insert_post(DraftPolicy::postArgs($output, get_current_user_id(), (int)$item['category_id'], $content), true); if (is_wp_error($postId)) throw new \RuntimeException('WordPress taslağı oluşturulamadı.');
            update_post_meta($postId, '_nyb_feed_item_id', $itemId); update_post_meta($postId, '_nyb_source_url', esc_url_raw($item['source_url'])); update_post_meta($postId, '_nyb_source_name', sanitize_text_field($item['source_name'])); update_post_meta($postId, '_nyb_content_hash', sanitize_text_field($item['content_hash'])); update_post_meta($postId, '_nyb_ai_provider', $provider instanceof OpenAiProvider ? 'openai' : 'mock'); update_post_meta($postId, '_nyb_ai_model', $provider->model()); update_post_meta($postId, '_nyb_generated_at', Support::now()); wp_set_post_tags($postId, $output['suggested_tags']);
            $wpdb->update(Support::table('feed_items'), ['status'=>'draft_created','updated_at'=>Support::now()], ['id'=>$itemId], ['%s','%s'], ['%d']); $this->incrementUsage(); return (int) $postId;
        } catch (\Throwable $e) { $wpdb->update(Support::table('feed_items'), ['status'=>'error','updated_at'=>Support::now()], ['id'=>$itemId], ['%s','%s'], ['%d']); throw $e; } finally { delete_transient($lock); }
    }
    private function todayUsage(): int { global $wpdb; return (int) $wpdb->get_var($wpdb->prepare('SELECT COALESCE(ai_requests,0) FROM ' . Support::table('daily_usage') . ' WHERE usage_date=%s', gmdate('Y-m-d'))); }
    private function incrementUsage(): void { global $wpdb; $date=gmdate('Y-m-d'); $table=Support::table('daily_usage'); $wpdb->query($wpdb->prepare("INSERT INTO $table (usage_date,ai_requests) VALUES (%s,1) ON DUPLICATE KEY UPDATE ai_requests=ai_requests+1", $date)); }
}

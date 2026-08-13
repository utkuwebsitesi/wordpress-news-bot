<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

final class MockAiProvider implements AiProvider
{
    public function model(): string { return 'mock-turkish-v1'; }
    public function testConnection(): void {}
    public function generate(array $item): array
    {
        $title = trim((string) ($item['title'] ?? 'Yeni gelişme'));
        return ['title' => $title, 'slug' => sanitize_title($title), 'excerpt' => (string) ($item['excerpt'] ?? ''), 'content_html' => '<p>Bu içerik Phase 1 mock sağlayıcısı ile editör incelemesi için hazırlanmıştır.</p>', 'category_suggestion' => '', 'tags' => [], 'seo_title' => $title, 'meta_description' => wp_trim_words((string) ($item['excerpt'] ?? ''), 25), 'focus_keyword' => '', 'source_attribution' => '', 'factual_risk' => 'medium', 'verification_notes' => 'Yayın öncesi editör doğrulaması gerekir.', 'image_prompt' => '', 'publication_recommendation' => 'draft'];
    }
}

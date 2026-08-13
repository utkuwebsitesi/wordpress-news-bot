<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class FeedParser
{
    public function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if (!$feed) throw new \RuntimeException('RSS/Atom XML okunamadı.');
        $items = [];
        $nodes = isset($feed->channel->item) ? $feed->channel->item : ($feed->entry ?? []);
        foreach ($nodes as $item) {
            $link = (string) ($item->link['href'] ?? $item->link ?? '');
            $title = trim(wp_strip_all_tags((string) ($item->title ?? '')));
            if ($title === '' || $link === '') continue;
            $description = (string) ($item->description ?? $item->summary ?? $item->content ?? '');
            $guid = trim((string) ($item->guid ?? $item->id ?? $link));
            $items[] = ['guid' => $guid, 'source_url' => esc_url_raw($link), 'title' => $title, 'excerpt' => wp_trim_words(wp_strip_all_tags($description), 55), 'content_hash' => hash('sha256', preg_replace('/\s+/u', ' ', wp_strip_all_tags($description)) ?: $title)];
        }
        return $items;
    }
}

<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SourceConnectionTester
{
    /** @var callable */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? static fn(string $url, array $args): mixed => wp_safe_remote_get($url, $args);
    }

    /** @return array{http_status:int,feed_type:string,item_count:int,duration_ms:int} */
    public function test(string $url, array $allowedDomains): array
    {
        $started = microtime(true);
        $current = SourceUrl::canonicalize($url);
        $allowed = array_values(array_filter(array_map([SourceUrl::class, 'normalizeHost'], $allowedDomains)));

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            if (!Security::validateFeedUrl($current, $allowed)) {
                throw new \RuntimeException(__('The source URL did not pass SSRF and allowed-host validation.', 'wordpress-news-bot'));
            }
            $response = ($this->transport)($current, [
                'timeout' => 20,
                'redirection' => 0,
                'reject_unsafe_urls' => true,
                'limit_response_size' => 2 * 1024 * 1024,
                'headers' => ['Accept' => 'application/rss+xml, application/atom+xml, application/xml'],
            ]);
            if (is_wp_error($response)) {
                throw new \RuntimeException(__('The RSS/Atom source could not be reached.', 'wordpress-news-bot'));
            }
            $status = (int) wp_remote_retrieve_response_code($response);
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = trim((string) wp_remote_retrieve_header($response, 'location'));
                if ($location === '' || $redirects === 3) {
                    throw new \RuntimeException(__('The source returned an invalid or excessive redirect.', 'wordpress-news-bot'));
                }
                $current = $this->resolveRedirect($current, $location);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(sprintf(__('The source returned HTTP status %d.', 'wordpress-news-bot'), $status));
            }

            $body = (string) wp_remote_retrieve_body($response);
            $type = preg_match('/<feed(?:\s|>)/i', $body) ? 'Atom' : (preg_match('/<(?:rss|rdf:RDF)(?:\s|>)/i', $body) ? 'RSS' : 'Unknown');
            if ($type === 'Unknown') {
                throw new \RuntimeException(__('The response is not a valid RSS or Atom feed.', 'wordpress-news-bot'));
            }
            $items = (new FeedParser())->parse($body);
            return [
                'http_status' => $status,
                'feed_type' => $type,
                'item_count' => count($items),
                'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)),
            ];
        }
        throw new \RuntimeException(__('The source connection test failed.', 'wordpress-news-bot'));
    }

    private function resolveRedirect(string $base, string $location): string
    {
        if (preg_match('~^https?://~i', $location)) {
            return SourceUrl::canonicalize($location);
        }
        $parts = wp_parse_url($base);
        if (!is_array($parts) || !str_starts_with($location, '/')) {
            throw new \RuntimeException(__('The source returned an unsafe redirect.', 'wordpress-news-bot'));
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return SourceUrl::canonicalize($parts['scheme'] . '://' . $parts['host'] . $port . $location);
    }
}

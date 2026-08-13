<?php
declare(strict_types=1);

require dirname(__DIR__) . '/tests/bootstrap.php';

foreach (array_slice($argv, 1) as $url) {
    $started = microtime(true);
    $redirects = [];
    $current = $url;
    $body = '';
    $contentType = '';
    $status = 0;
    for ($hop = 0; $hop <= 3; $hop++) {
        $headers = [];
        $curl = curl_init($current);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'WordPress-News-Bot/0.4.0-rc.1',
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                return strlen($line);
            },
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = strtolower(trim(explode(';', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
        if ($response === false) {
            echo json_encode(['url_host'=>parse_url($url, PHP_URL_HOST),'stage'=>'http_failed','curl_errno'=>curl_errno($curl)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
            continue 2;
        }
        $body = (string) $response;
        if (in_array($status, [301,302,303,307,308], true) && isset($headers['location'])) {
            $redirects[] = parse_url($headers['location'], PHP_URL_HOST);
            $current = $headers['location'];
            continue;
        }
        break;
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    $root = $xml === false ? '' : strtolower($xml->getName());
    $rssItems = $xml === false ? 0 : count($xml->channel->item ?? []);
    $atomItems = $xml === false ? 0 : count($xml->entry ?? []);
    $pluginItems = null;
    $pluginParserError = null;
    try {
        $pluginItems = count((new WordPressNewsBot\FeedParser())->parse($body));
    } catch (Throwable $e) {
        $pluginParserError = get_class($e);
    }
    echo json_encode([
        'url_host' => parse_url($url, PHP_URL_HOST),
        'final_host' => parse_url($current, PHP_URL_HOST),
        'status' => $status,
        'content_type' => $contentType,
        'response_bytes' => strlen($body),
        'redirect_hosts' => $redirects,
        'xml_valid' => $xml !== false,
        'xml_root' => $root,
        'rss_items' => $rssItems,
        'atom_items' => $atomItems,
        'plugin_parser_items' => $pluginItems,
        'plugin_parser_error_class' => $pluginParserError,
        'libxml_error_codes' => array_values(array_unique(array_map(static fn($error): int => $error->code, $errors))),
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

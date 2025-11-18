<?php

// Wczytujemy konfigurację
$config = require __DIR__ . '/config.php';

$rssUrl   = $config['facebook_rss_url']  ?? '';
$pageUrl  = $config['facebook_page_url'] ?? '';
$limit    = (int)($config['posts_limit'] ?? 3);

// Ustawienia cache
$cacheDir  = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/facebook-news.json';
// ważność cache, np. 1 godzina
$cacheTtl  = 3600;

header('Content-Type: application/json; charset=utf-8');

// Tworzymy katalog cache, jeśli nie istnieje
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

// 1. Jeśli jest świeży cache → zwróć go i zakończ
if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTtl) {
        $data = file_get_contents($cacheFile);
        if ($data !== false && $data !== '') {
            echo $data;
            exit;
        }
    }
}

// Jeśli nie ma URL-a RSS w configu
if (!$rssUrl) {
    echo json_encode([
        'error' => 'no_rss_url',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. Próba pobrania RSS
$context = stream_context_create([
    'http' => [
        'ignore_errors' => true,
        'header'        => "User-Agent: CappellaMarialisSite/1.0\r\n",
        'timeout'       => 10,
    ]
]);

$rssContent = @file_get_contents($rssUrl, false, $context);

if ($rssContent === false || trim($rssContent) === '') {
    // 3a. Jak pobranie RSS padło, ale mamy stary cache → zwróć cache
    if (file_exists($cacheFile)) {
        $data = file_get_contents($cacheFile);
        if ($data !== false && $data !== '') {
            echo $data;
            exit;
        }
    }

    // 3b. Nie ma RSS i nie ma cache → błąd (front zrobi fallback na FB plugin)
    echo json_encode([
        'error' => 'rss_fetch_failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 4. Parsowanie RSS
$xml = @simplexml_load_string($rssContent);

if ($xml === false || !isset($xml->channel->item)) {
    // znów próba użycia starego cache
    if (file_exists($cacheFile)) {
        $data = file_get_contents($cacheFile);
        if ($data !== false && $data !== '') {
            echo $data;
            exit;
        }
    }

    echo json_encode([
        'error' => 'rss_parse_failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$posts = [];
$count = 0;

foreach ($xml->channel->item as $item) {
    if ($count >= $limit) break;

    $title = trim((string)$item->title);
    $body  = (string)$item->description;
    $date  = (string)$item->pubDate;
    $link  = (string)$item->link;

    // Szukanie obrazka
    $image = '';

    // <media:content>
    $media = $item->children('media', true);
    if ($media && isset($media->content)) {
        $attrs = $media->content->attributes();
        if (isset($attrs['url'])) {
            $image = (string)$attrs['url'];
        }
    }

    // <enclosure>
    if (!$image && isset($item->enclosure)) {
        $image = (string)$item->enclosure['url'];
    }

    // <img> w opisie
    if (!$image && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $body, $m)) {
        $image = $m[1];
    }

    // Tekst bez HTML
    $bodyText = trim(strip_tags($body));

    // Jeśli brak tytułu, spróbuj wziąć pierwszą linijkę tekstu
    if (!$title && $bodyText) {
        $lines = preg_split("/\r\n|\n|\r/", $bodyText);
        $title = trim($lines[0] ?? '');
    }

    // Przycięcie długości
    if (mb_strlen($title) > 80) {
        $title = mb_substr($title, 0, 77) . '...';
    }
    if (mb_strlen($bodyText) > 300) {
        $bodyText = mb_substr($bodyText, 0, 297) . '...';
    }

    $posts[] = [
        'title' => $title,
        'body'  => $bodyText,
        'date'  => $date,
        'image' => $image,
        'link'  => $link ?: $pageUrl,
    ];

    $count++;
}

// 5. Zapis do cache + odpowiedź
$json = json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@file_put_contents($cacheFile, $json);

echo $json;

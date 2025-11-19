<?php

header('Content-Type: application/json; charset=utf-8');

// Wczytujemy dane z pliku config.php
$config = require __DIR__ . '/config.php';

// Ustawiamy zmienne na podstawie configu
$pageId      = $config['facebook_page_id']      ?? null;
$accessToken = $config['facebook_access_token'] ?? null;
$limit       = (int)($config['posts_limit']     ?? 3);

// Plik cache (w tym samym katalogu)
$cacheFile   = __DIR__ . '/facebook-news-cache.json';

// Ile godzin cache ma być uznany za "świeży"
$maxAgeHours = $config['cache_refresh_hours'] ?? 1;
$maxAge      = $maxAgeHours * 3600; // w sekundach

// =========================
// FUNKCJE CACHE
// =========================

function save_cache(string $file, array $posts): void
{
    $payload = [
        'cached_at' => time(),
        'posts'     => $posts,
    ];

    @file_put_contents(
        $file,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

function load_cache(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['posts']) || !is_array($data['posts'])) {
        return null;
    }

    return $data['posts'];
}

// =========================
// WALIDACJA KONFIGU
// =========================

if (!$pageId || !$accessToken) {
    echo json_encode([
        'error'   => 'config_error',
        'message' => 'Brak pageId lub accessToken w config.php'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =========================
// 1. JEŚLI CACHE JEST ŚWIEŻY → ZWRÓĆ GO I NIE PYTAJ FACEBOOKA
// =========================

if (is_file($cacheFile)) {
    $fileAge = time() - filemtime($cacheFile);

    if ($fileAge < $maxAge) {
        $cached = load_cache($cacheFile);
        if ($cached !== null) {
            echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// =========================
// 2. CACHE STARY / BRAK CACHE → PROBUJEMY POBRAĆ Z API
// =========================

$url = "https://graph.facebook.com/v21.0/{$pageId}/posts" .
       "?fields=message,story,created_time,full_picture,permalink_url" .
       "&limit={$limit}&access_token={$accessToken}";

// KONTEKST z ignore_errors, żeby zobaczyć odpowiedź nawet przy 400
$context = stream_context_create([
    'http' => [
        'ignore_errors' => true,
    ]
]);

$response = @file_get_contents($url, false, $context);

// --- request w ogóle się nie udał (np. brak internetu, DNS, itp.)
if ($response === false) {
    $cached = load_cache($cacheFile);
    if ($cached !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'error'   => 'request_failed',
        'message' => 'Nie udało się pobrać danych z Facebooka i brak cache.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode($response, true);

// --- Facebook zwrócił błąd (np. token wywalony)
if (isset($data['error'])) {
    $cached = load_cache($cacheFile);
    if ($cached !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'error'   => 'fb_error',
        'details' => $data['error'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- Brak pola "data" w odpowiedzi
if (!isset($data['data']) || !is_array($data['data'])) {
    $cached = load_cache($cacheFile);
    if ($cached !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'error' => 'no_data_field',
        'raw'   => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =========================
// 3. NORMALNE PRZETWARZANIE POSTÓW
// (LOGIKA TYTUŁÓW I TREŚCI JAK OPISYWAŁEŚ: 1. linia, 80 znaków, body 300)
// =========================

$posts = [];

foreach ($data['data'] as $post) {
    // Tekst posta: message albo story
    $text = '';
    if (!empty($post['message'])) {
        $text = $post['message'];
    } elseif (!empty($post['story'])) {
        $text = $post['story'];
    } else {
        // jeśli nie ma żadnego tekstu, pomijamy post
        continue;
    }

    // Tytuł = pierwsza linia tekstu
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $title = trim($lines[0]);

    // Przytnij tytuł do 80 znaków
    if (mb_strlen($title) > 80) {
        $title = mb_substr($title, 0, 77) . '...';
    }

    // Treść = cały tekst, przycięty do 300 znaków
    $body = trim($text);
    if (mb_strlen($body) > 300) {
        $body = mb_substr($body, 0, 297) . '...';
    }

    $posts[] = [
        'title' => $title,
        'body'  => $body,
        'date'  => $post['created_time'] ?? '',
        'image' => $post['full_picture'] ?? '',
        'link'  => $post['permalink_url'] ?? '',
    ];
}

// =========================
// 4. ZAPIS DO CACHE + ODPOWIEDŹ
// =========================

if (!empty($posts)) {
    save_cache($cacheFile, $posts);
}

echo json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;


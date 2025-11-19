<?php
// fb-callback.php

session_start();

$config = require __DIR__ . '/config.php';

$appId       = $config['facebook_app_id'] ?? null;
$appSecret   = $config['facebook_app_secret'] ?? null;
$pageId      = $config['facebook_page_id'] ?? null;

if (!$appId || !$appSecret || !$pageId) {
    http_response_code(500);
    echo "Brakuje facebook_app_id, facebook_app_secret lub facebook_page_id w config.php";
    exit;
}

// 
// 
// 
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// 
// 
// To musi być ten sam adres, co w fb-login.php i w ustawieniach aplikacji
$redirectUri = 'http://localhost:8000/fb-callback.php';

// Sprawdzenie state (CSRF)
if (!isset($_GET['state']) || !isset($_SESSION['fb_oauth_state']) || $_GET['state'] !== $_SESSION['fb_oauth_state']) {
    http_response_code(400);
    echo "Błędny parametr state.";
    exit;
}

// Musi być parametr "code" od Facebooka
if (!isset($_GET['code'])) {
    http_response_code(400);
    echo "Brak parametru code.";
    exit;
}

$code = $_GET['code'];

// 1. Zamiana CODE -> krótkotrwały user access token
$tokenUrl = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
    'client_id'     => $appId,
    'redirect_uri'  => $redirectUri,
    'client_secret' => $appSecret,
    'code'          => $code,
]);

$shortTokenResponse = file_get_contents($tokenUrl);
if ($shortTokenResponse === false) {
    echo "Błąd pobierania krótkiego access tokena.";
    exit;
}

$shortData = json_decode($shortTokenResponse, true);
if (!isset($shortData['access_token'])) {
    echo "Brak access_token w odpowiedzi:\n";
    echo htmlspecialchars($shortTokenResponse);
    exit;
}

$shortUserToken = $shortData['access_token'];

// 2. Zamiana short-lived -> long-lived user token
$longTokenUrl = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
    'grant_type'        => 'fb_exchange_token',
    'client_id'         => $appId,
    'client_secret'     => $appSecret,
    'fb_exchange_token' => $shortUserToken,
]);

$longTokenResponse = file_get_contents($longTokenUrl);
if ($longTokenResponse === false) {
    echo "Błąd pobierania long-lived tokena.";
    exit;
}

$longData = json_decode($longTokenResponse, true);
if (!isset($longData['access_token'])) {
    echo "Brak long-lived access_token w odpowiedzi:\n";
    echo htmlspecialchars($longTokenResponse);
    exit;
}

$longUserToken = $longData['access_token'];

// 3. Pobranie listy stron użytkownika i odczytanie tokena strony
$accountsUrl = 'https://graph.facebook.com/v21.0/me/accounts?' . http_build_query([
    'access_token' => $longUserToken,
]);

$accountsResponse = file_get_contents($accountsUrl);
if ($accountsResponse === false) {
    echo "Błąd pobierania listy stron (me/accounts).";
    exit;
}

$accountsData = json_decode($accountsResponse, true);
if (!isset($accountsData['data']) || !is_array($accountsData['data'])) {
    echo "Nie udało się pobrać listy stron:\n";
    echo htmlspecialchars($accountsResponse);
    exit;
}

$pageAccessToken = null;

foreach ($accountsData['data'] as $page) {
    if (!empty($page['id']) && $page['id'] == $pageId && !empty($page['access_token'])) {
        $pageAccessToken = $page['access_token'];
        break;
    }
}

if (!$pageAccessToken) {
    echo "Nie znaleziono strony o ID {$pageId} w me/accounts.\n";
    echo "Sprawdź, czy zalogowałeś się kontem, które administruje tą stroną.";
    exit;
}

// 4. Zapis nowego Page Access Tokenu do config.php
$config['facebook_access_token'] = $pageAccessToken;

// Funkcja zapisująca config.php
$export = var_export($config, true);
$php   = "<?php\n\nreturn " . $export . ";\n";

if (file_put_contents(__DIR__ . '/config.php', $php) === false) {
    echo "Nie udało się zapisać nowego tokena do config.php";
    exit;
}

// 5. Prosty komunikat sukcesu
echo "<h1>Token zaktualizowany 🎉</h1>";
echo "<p>Nowy Page Access Token został zapisany do <code>config.php</code>.</p>";
echo "<p>Teraz <code>facebook-news.php</code> będzie go używać automatycznie.</p>";

<?php
// fb-login.php

session_start();

$config = require __DIR__ . '/../config.php';

$appId       = $config['facebook_app_id'] ?? null;
$adminSecret = $config['admin_secret'] ?? null;

// Minimalna walidacja
if (!$appId || !$adminSecret) {
    http_response_code(500);
    echo "Brakuje konfiguracji facebook_app_id albo admin_secret w config.php";
    exit;
}

// Proste zabezpieczenie: trzeba podać ?s=admin_secret
if (!isset($_GET['s']) || $_GET['s'] !== $adminSecret) {
    http_response_code(403);
    echo "Dostęp zabroniony.";
    exit;
}

// 
// 
// 
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// https://developers.facebook.com/apps/1519385729118488/business-login/settings/ zmień później to redirectUri w Valid OAuth Redirect URIs
// Zmień później localhost:8000 na adres hosta i przerób jeszcze żeby config było poza public
// To musi być dokładnie taki sam adres, jak wpisany w ustawieniach aplikacji Facebook
$redirectUri = 'http://localhost:8000/fb-callback.php';

// CSRF token
$state = bin2hex(random_bytes(16));
$_SESSION['fb_oauth_state'] = $state;

// Uprawnienia potrzebne, żeby pobrać listę stron i ich tokeny
$scope = [
    'public_profile',
    'pages_show_list',
    'pages_read_engagement',
    'pages_read_user_content',
    // jeśli chcesz później publikować posty:
    // 'pages_manage_posts',
];

$params = [
    'client_id'     => $appId,
    'redirect_uri'  => $redirectUri,
    'state'         => $state,
    'scope'         => implode(',', $scope),
];

// Przekierowanie do Facebooka
$url = 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query($params);

header('Location: ' . $url);
exit;

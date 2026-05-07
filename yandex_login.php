<?php
/**
 * Стартовая точка входа через Яндекс ID.
 * Генерирует state, складывает в сессию, редиректит на oauth.yandex.ru.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/oauth/OAuthConfig.php';
require_once __DIR__ . '/oauth/OAuthHelper.php';
require_once __DIR__ . '/oauth/YandexClient.php';

if (!OAuthConfig::yandexEnabled()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Яндекс ID не настроен</h1>';
    echo '<p>Администратор не задал YANDEX_CLIENT_ID / YANDEX_CLIENT_SECRET / YANDEX_REDIRECT_URI.</p>';
    echo '<p><a href="/">На главную</a></p>';
    exit;
}

$state = OAuthHelper::randomState();
$_SESSION['yandex_state']     = $state;
$_SESSION['yandex_return_to'] = (string)($_GET['return_to'] ?? '/profile.php');

$client = new YandexClient();
header('Location: ' . $client->buildAuthUrl($state));
exit;

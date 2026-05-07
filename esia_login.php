<?php
/**
 * Старт OAuth/OIDC flow ЕСИА.
 * Генерит state+nonce, кладёт в сессию, редиректит пользователя на Госуслуги.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/esia/EsiaConfig.php';
require_once __DIR__ . '/esia/EsiaClient.php';

if (!EsiaConfig::isEnabled()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>ЕСИА-интеграция не настроена</h1>';
    echo '<p>Установите переменные окружения <code>ESIA_CLIENT_ID</code> и <code>ESIA_REDIRECT_URI</code>.</p>';
    echo '<p>См. <a href="/README_ESIA.md">README_ESIA.md</a>.</p>';
    exit;
}

$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['esia_state'] = $state;
$_SESSION['esia_nonce'] = $nonce;
$_SESSION['esia_started_at'] = time();

// Куда вернуть пользователя после успешной авторизации (RelayState).
$_SESSION['esia_return_to'] = $_GET['return_to'] ?? '/profile.php';

try {
    $client = new EsiaClient();
    $url = $client->buildAuthorizeUrl($state, $nonce);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    error_log('esia_login error: ' . $e->getMessage());
    http_response_code(500);
    echo '<h1>Не удалось начать вход через Госуслуги</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/">На главную</a></p>';
}

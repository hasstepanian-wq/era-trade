<?php
/**
 * Callback от Яндекс ID. Обмен code → access_token → профиль → создаём/линкуем
 * пользователя в social_accounts → выставляем сессию → редирект на return_to.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/oauth/OAuthConfig.php';
require_once __DIR__ . '/oauth/OAuthHelper.php';
require_once __DIR__ . '/oauth/OAuthSchema.php';
require_once __DIR__ . '/oauth/YandexClient.php';

$err = function (string $msg, int $code = 400) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Ошибка входа через Яндекс</h1>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/">На главную</a></p>';
    exit;
};

if (!OAuthConfig::yandexEnabled())                         $err('Яндекс ID не настроен.', 503);
if (isset($_GET['error']))                                 $err('Яндекс вернул ошибку: ' . (string)$_GET['error']);
if (empty($_GET['code']) || empty($_GET['state']))         $err('В ответе нет code/state.');
if (!hash_equals((string)($_SESSION['yandex_state'] ?? ''), (string)$_GET['state'])) $err('state не совпадает (CSRF).');

try {
    $client = new YandexClient();
    $token  = $client->exchangeCode((string)$_GET['code']);
    $profile = $client->fetchProfile((string)$token['access_token']);

    $extId       = (string)($profile['id'] ?? '');
    if ($extId === '') $err('В профиле Яндекса нет id.');

    $email       = (string)($profile['default_email'] ?? '') ?: null;
    $displayName = trim((string)($profile['real_name'] ?? $profile['display_name'] ?? $profile['login'] ?? ''));
    $avatar      = !empty($profile['default_avatar_id'])
        ? 'https://avatars.yandex.net/get-yapic/' . rawurlencode((string)$profile['default_avatar_id']) . '/islands-200'
        : null;

    $user = OAuthHelper::findOrCreateUser(
        $pdo,
        'yandex',
        $extId,
        $email,
        $displayName,
        $avatar,
        $profile
    );

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['full_name'];
    $_SESSION['user_balance'] = $user['balance'];
    $_SESSION['usertype']     = $user['user_type'];
    $_SESSION['username']     = $user['username'];

    $returnTo = OAuthHelper::safeReturnTo($_SESSION['yandex_return_to'] ?? null);
    unset($_SESSION['yandex_state'], $_SESSION['yandex_return_to']);

    header('Location: ' . $returnTo);
    exit;
} catch (Throwable $e) {
    error_log('yandex_callback error: ' . $e->getMessage());
    $err($e->getMessage(), 500);
}

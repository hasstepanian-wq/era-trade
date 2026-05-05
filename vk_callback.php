<?php
/**
 * Callback от VK ID. Обмен code → access_token+user_id+email → users.get → профиль.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/oauth/OAuthConfig.php';
require_once __DIR__ . '/oauth/OAuthHelper.php';
require_once __DIR__ . '/oauth/OAuthSchema.php';
require_once __DIR__ . '/oauth/VkClient.php';

$err = function (string $msg, int $code = 400) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Ошибка входа через VK</h1>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/">На главную</a></p>';
    exit;
};

if (!OAuthConfig::vkEnabled())                             $err('VK ID не настроен.', 503);
if (isset($_GET['error']))                                 $err('VK вернул ошибку: ' . (string)$_GET['error']);
if (empty($_GET['code']) || empty($_GET['state']))         $err('В ответе нет code/state.');
if (!hash_equals((string)($_SESSION['vk_state'] ?? ''), (string)$_GET['state'])) $err('state не совпадает (CSRF).');

try {
    $client = new VkClient();
    $token  = $client->exchangeCode((string)$_GET['code']);
    $userId = (string)$token['user_id'];
    $email  = !empty($token['email']) ? (string)$token['email'] : null;

    $profile = $client->fetchProfile((string)$token['access_token'], $userId);

    $displayName = trim(((string)($profile['first_name'] ?? '')) . ' ' . ((string)($profile['last_name'] ?? '')));
    $avatar      = (string)($profile['photo_200'] ?? '') ?: null;

    $merged = $profile + ['email' => $email];

    $user = OAuthHelper::findOrCreateUser(
        $pdo,
        'vk',
        $userId,
        $email,
        $displayName,
        $avatar,
        $merged
    );

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['full_name'];
    $_SESSION['user_balance'] = $user['balance'];
    $_SESSION['usertype']     = $user['user_type'];
    $_SESSION['username']     = $user['username'];

    $returnTo = OAuthHelper::safeReturnTo($_SESSION['vk_return_to'] ?? null);
    unset($_SESSION['vk_state'], $_SESSION['vk_return_to']);

    header('Location: ' . $returnTo);
    exit;
} catch (Throwable $e) {
    error_log('vk_callback error: ' . $e->getMessage());
    $err($e->getMessage(), 500);
}

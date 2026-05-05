<?php
/**
 * Стартовая точка входа через VK ID. Генерирует state, редиректит на oauth.vk.com.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/oauth/OAuthConfig.php';
require_once __DIR__ . '/oauth/OAuthHelper.php';
require_once __DIR__ . '/oauth/VkClient.php';

if (!OAuthConfig::vkEnabled()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>VK ID не настроен</h1>';
    echo '<p>Администратор не задал VK_CLIENT_ID / VK_CLIENT_SECRET / VK_REDIRECT_URI.</p>';
    echo '<p><a href="/">На главную</a></p>';
    exit;
}

$state = OAuthHelper::randomState();
$_SESSION['vk_state']     = $state;
$_SESSION['vk_return_to'] = (string)($_GET['return_to'] ?? '/profile.php');

$client = new VkClient();
header('Location: ' . $client->buildAuthUrl($state));
exit;

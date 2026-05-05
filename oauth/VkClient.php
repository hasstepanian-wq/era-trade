<?php
/**
 * OAuth 2.0 клиент для VK ID.
 *
 * Документация: https://id.vk.com/about/business/go/docs
 * Используем «классический» VK OAuth (Authorization Code Flow):
 *  - GET https://oauth.vk.com/authorize?response_type=code&client_id=…&display=page&scope=email&redirect_uri=…&v=…&state=…
 *  - POST https://oauth.vk.com/access_token (grant_type=authorization_code, code, client_id, client_secret, redirect_uri)
 *      → возвращает access_token + user_id [+ email если scope=email]
 *  - GET https://api.vk.com/method/users.get?fields=photo_200,screen_name&access_token=…&v=… → ФИО, аватар
 *
 * Scope-ы: email (минимум), при необходимости — wall, friends и т.д.
 */

require_once __DIR__ . '/OAuthConfig.php';
require_once __DIR__ . '/OAuthHelper.php';

class VkClient
{
    public const AUTHORIZE_URL = 'https://oauth.vk.com/authorize';
    public const TOKEN_URL     = 'https://oauth.vk.com/access_token';
    public const USERS_GET_URL = 'https://api.vk.com/method/users.get';

    public function buildAuthUrl(string $state): string
    {
        $params = [
            'client_id'     => OAuthConfig::vkClientId(),
            'redirect_uri'  => OAuthConfig::vkRedirectUri(),
            'display'       => 'page',
            'scope'         => 'email',
            'response_type' => 'code',
            'v'             => OAuthConfig::vkApiVersion(),
            'state'         => $state,
        ];
        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /** Меняет code на access_token + user_id [+ email]. */
    public function exchangeCode(string $code): array
    {
        $url = self::TOKEN_URL . '?' . http_build_query([
            'client_id'     => OAuthConfig::vkClientId(),
            'client_secret' => OAuthConfig::vkClientSecret(),
            'redirect_uri'  => OAuthConfig::vkRedirectUri(),
            'code'          => $code,
        ]);
        $data = OAuthHelper::httpGetJson($url);
        if (empty($data['access_token']) || empty($data['user_id'])) {
            throw new RuntimeException('VK token exchange failed: ' . json_encode($data));
        }
        return $data;
    }

    /** Тянет ФИО, аватар, screen_name. */
    public function fetchProfile(string $accessToken, string $userId): array
    {
        $url = self::USERS_GET_URL . '?' . http_build_query([
            'user_ids'     => $userId,
            'fields'       => 'photo_200,screen_name,bdate,city',
            'access_token' => $accessToken,
            'v'            => OAuthConfig::vkApiVersion(),
        ]);
        $data = OAuthHelper::httpGetJson($url);
        if (!isset($data['response'][0])) {
            throw new RuntimeException('VK users.get failed: ' . json_encode($data));
        }
        return $data['response'][0];
    }
}

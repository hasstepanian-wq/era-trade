<?php
/**
 * OAuth 2.0 клиент для Яндекс ID.
 *
 * Документация: https://yandex.ru/dev/id/doc/ru/
 *  - GET https://oauth.yandex.ru/authorize?response_type=code&client_id=…&state=…&redirect_uri=…
 *  - POST https://oauth.yandex.ru/token  (grant_type=authorization_code, code, client_id, client_secret)
 *  - GET https://login.yandex.ru/info?format=json (Authorization: OAuth <token>)
 *
 * Стандартные scope-ы для нашего use-case (галочки прав в кабинете oauth.yandex.ru):
 *   login:info, login:email, login:birthday, login:avatar
 */

require_once __DIR__ . '/OAuthConfig.php';
require_once __DIR__ . '/OAuthHelper.php';

class YandexClient
{
    public const AUTHORIZE_URL = 'https://oauth.yandex.ru/authorize';
    public const TOKEN_URL     = 'https://oauth.yandex.ru/token';
    public const PROFILE_URL   = 'https://login.yandex.ru/info?format=json';

    public function buildAuthUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id'     => OAuthConfig::yandexClientId(),
            'redirect_uri'  => OAuthConfig::yandexRedirectUri(),
            'state'         => $state,
            'force_confirm' => 'no',
        ];
        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /** Меняет authorization_code на access_token. Возвращает массив с access_token. */
    public function exchangeCode(string $code): array
    {
        [$status, $body] = OAuthHelper::httpPostForm(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => OAuthConfig::yandexClientId(),
            'client_secret' => OAuthConfig::yandexClientSecret(),
        ]);
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Yandex token exchange failed: HTTP ' . $status . ' ' . $body);
        }
        return $data;
    }

    /** Возвращает профиль пользователя по access_token. */
    public function fetchProfile(string $accessToken): array
    {
        return OAuthHelper::httpGetJson(self::PROFILE_URL, [
            'Authorization: OAuth ' . $accessToken,
        ]);
    }
}

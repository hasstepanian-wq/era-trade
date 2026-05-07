<?php
/**
 * OAuth 2.1 клиент для VK ID (id.vk.com / id.vk.com/business).
 *
 * Документация: https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/auth-without-sdk-web
 *
 * Это «новая» площадка VK ID, отличная от legacy oauth.vk.com:
 *   - Использует OAuth 2.1 c обязательным PKCE (code_verifier / code_challenge).
 *   - В ответе от /authorize приходит дополнительный параметр device_id,
 *     который ОБЯЗАН быть передан при обмене кода на токен.
 *   - Токен и user_info живут на эндпоинтах /oauth2/auth и /oauth2/user_info.
 *
 * Flow:
 *  1. Сгенерировать code_verifier (43–128 random) и code_challenge = base64url(sha256(verifier)).
 *  2. Редирект GET https://id.vk.com/authorize?response_type=code&client_id=…
 *     &redirect_uri=…&state=…&scope=email%20phone&code_challenge=…&code_challenge_method=S256
 *  3. Callback вернёт ?code=…&state=…&device_id=…&type=code_v2&expires_in=…
 *  4. POST application/x-www-form-urlencoded на https://id.vk.com/oauth2/auth :
 *       grant_type=authorization_code, code, code_verifier, redirect_uri,
 *       client_id, device_id, state
 *     Ответ: {access_token, refresh_token, id_token, token_type, expires_in,
 *             user_id, scope, state}
 *  5. POST на https://id.vk.com/oauth2/user_info с access_token и client_id —
 *     отдаёт {user: {first_name, last_name, phone, email, avatar, user_id, ...}}
 */

require_once __DIR__ . '/OAuthConfig.php';
require_once __DIR__ . '/OAuthHelper.php';

class VkClient
{
    public const AUTHORIZE_URL = 'https://id.vk.com/authorize';
    public const TOKEN_URL     = 'https://id.vk.com/oauth2/auth';
    public const USER_INFO_URL = 'https://id.vk.com/oauth2/user_info';

    /** Генерирует code_verifier (43–128 url-safe символов). */
    public static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    /** S256: code_challenge = base64url(sha256(verifier)). */
    public static function codeChallengeFromVerifier(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function buildAuthUrl(string $state, string $codeChallenge, string $scope = 'email phone'): string
    {
        $params = [
            'response_type'         => 'code',
            'client_id'             => OAuthConfig::vkClientId(),
            'redirect_uri'          => OAuthConfig::vkRedirectUri(),
            'state'                 => $state,
            'scope'                 => $scope,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Обмен code → access_token. Требует device_id из callback'а и
     * code_verifier, сохранённый в сессии перед редиректом.
     *
     * @return array {access_token, refresh_token, expires_in, user_id, scope, ...}
     */
    public function exchangeCode(string $code, string $deviceId, string $codeVerifier, string $state): array
    {
        $body = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri'  => OAuthConfig::vkRedirectUri(),
            'client_id'     => OAuthConfig::vkClientId(),
            'device_id'     => $deviceId,
            'state'         => $state,
        ];
        [$status, $resp] = OAuthHelper::httpPostForm(self::TOKEN_URL, $body);
        $data = json_decode((string)$resp, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token']) || empty($data['user_id'])) {
            throw new RuntimeException('VK ID token exchange failed (HTTP ' . $status . '): ' . (string)$resp);
        }
        return $data;
    }

    /**
     * Тянет профиль пользователя VK ID.
     *
     * @return array — минимум {user_id, first_name, last_name}, опционально
     *                 {email, phone, avatar, sex, birthday, ...}.
     */
    public function fetchProfile(string $accessToken): array
    {
        $body = [
            'access_token' => $accessToken,
            'client_id'    => OAuthConfig::vkClientId(),
        ];
        [$status, $resp] = OAuthHelper::httpPostForm(self::USER_INFO_URL, $body);
        $data = json_decode((string)$resp, true);
        if ($status !== 200 || !is_array($data) || empty($data['user'])) {
            throw new RuntimeException('VK ID user_info failed (HTTP ' . $status . '): ' . (string)$resp);
        }
        return $data['user'];
    }
}

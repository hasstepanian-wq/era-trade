<?php
require_once __DIR__ . '/EsiaConfig.php';
require_once __DIR__ . '/EsiaSignerInterface.php';
require_once __DIR__ . '/MockSigner.php';
require_once __DIR__ . '/RemoteGostSigner.php';

/**
 * Тонкий клиент ЕСИА. Выполняет:
 *   - построение URL авторизации (/aas/oauth2/v3/ac);
 *   - обмен authorization_code → access_token (/aas/oauth2/v3/te);
 *   - получение профиля пользователя (/rs/prns/{oid});
 *   - запуск flow подписи документа (/rs/oauth2/te + /aas/oauth2/te для УНЭП).
 *
 * Подпись запросов делегируется EsiaSignerInterface (см. MockSigner /
 * RemoteGostSigner).
 */
class EsiaClient
{
    private EsiaSignerInterface $signer;

    public function __construct(?EsiaSignerInterface $signer = null)
    {
        $this->signer = $signer ?? self::resolveSigner();
    }

    public static function resolveSigner(): EsiaSignerInterface
    {
        return EsiaConfig::isMockMode() ? new MockSigner() : new RemoteGostSigner();
    }

    /**
     * Построить URL для редиректа пользователя на ЕСИА.
     *
     * @param string $state  криптослучайный токен из сессии (CSRF)
     * @param string $nonce  криптослучайный токен (OIDC nonce)
     * @return string полный URL для GET-редиректа
     */
    public function buildAuthorizeUrl(string $state, string $nonce): string
    {
        $clientId  = EsiaConfig::clientId();
        $redirect  = EsiaConfig::redirectUri();
        $scopes    = EsiaConfig::scopes();
        $timestamp = self::esiaTimestamp();

        // Строка, над которой считается client_secret для /ac.
        // Порядок полей зафиксирован спецификацией ЕСИА v3.
        $payload = $scopes . $timestamp . $clientId . $state;
        $signature = $this->signer->sign($payload);

        $params = [
            'client_id'     => $clientId,
            'client_secret' => $signature,
            'redirect_uri'  => $redirect,
            'scope'         => $scopes,
            'response_type' => 'code',
            'state'         => $state,
            'timestamp'     => $timestamp,
            'access_type'   => 'online',
            'nonce'         => $nonce,
        ];
        return EsiaConfig::authUrl() . '?' . http_build_query($params);
    }

    /**
     * Обменять authorization_code на access_token + id_token.
     *
     * @return array{access_token:string,id_token:string,oid:?string,raw:array}
     * @throws RuntimeException при ошибке от ЕСИА
     */
    public function exchangeCode(string $code, string $state): array
    {
        if (EsiaConfig::isMockMode()) {
            // В mock-режиме не ходим в реальный ЕСИА. Возвращаем синтетический
            // токен с детерминированным oid для отладки UI.
            return $this->mockExchangeResponse($code);
        }

        $clientId  = EsiaConfig::clientId();
        $redirect  = EsiaConfig::redirectUri();
        $scopes    = EsiaConfig::scopes();
        $timestamp = self::esiaTimestamp();

        $payload = $clientId . $scopes . $timestamp . $state . $redirect;
        $signature = $this->signer->sign($payload);

        $body = [
            'client_id'     => $clientId,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'client_secret' => $signature,
            'state'         => $state,
            'redirect_uri'  => $redirect,
            'scope'         => $scopes,
            'timestamp'     => $timestamp,
            'token_type'    => 'Bearer',
        ];

        $resp = $this->http('POST', EsiaConfig::tokenUrl(), $body);
        if (empty($resp['access_token'])) {
            throw new RuntimeException('ESIA token endpoint returned no access_token: ' . json_encode($resp));
        }
        $oid = self::extractOidFromIdToken((string)($resp['id_token'] ?? ''));
        return [
            'access_token' => (string)$resp['access_token'],
            'id_token'     => (string)($resp['id_token'] ?? ''),
            'oid'          => $oid,
            'raw'          => $resp,
        ];
    }

    /**
     * Получить профиль гражданина по OID (Object Identifier).
     * См. https://digital.gov.ru/...esia REST API: /rs/prns/{oid}
     *
     * @return array поля person (firstName, lastName, middleName, snils, ...)
     */
    public function fetchProfile(string $accessToken, string $oid): array
    {
        if (EsiaConfig::isMockMode()) {
            return $this->mockProfile($oid);
        }
        $url = EsiaConfig::rsUrl() . '/prns/' . urlencode($oid);
        return $this->http('GET', $url, null, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);
    }

    /**
     * Запустить процесс подписания документа через ЕСИА (УНЭП).
     * Возвращает URL, на который надо редиректить пользователя.
     */
    public function buildSignDocumentUrl(string $documentHash, string $documentTitle, string $callbackUrl, string $state): string
    {
        $clientId  = EsiaConfig::clientId();
        $timestamp = self::esiaTimestamp();
        $payload   = $clientId . $documentHash . $timestamp . $state . $callbackUrl;
        $signature = $this->signer->sign($payload);

        $params = [
            'client_id'     => $clientId,
            'client_secret' => $signature,
            'response_type' => 'sign',
            'document_hash' => $documentHash,
            'document_name' => $documentTitle,
            'redirect_uri'  => $callbackUrl,
            'state'         => $state,
            'timestamp'     => $timestamp,
        ];
        // У ЕСИА несколько способов подписи, реальный URL зависит от соглашения с
        // оператором. Здесь — самый общий путь через AAS, как в /aas/oauth2/te
        // с типом sign. Точная схема может уточняться при подключении.
        return EsiaConfig::authUrl() . '?' . http_build_query($params);
    }

    // ---------- mock helpers ----------

    private function mockExchangeResponse(string $code): array
    {
        $oid = '1000' . substr(hash('sha256', $code), 0, 9);
        $idtokenPayload = [
            'iss' => 'http://esia.gosuslugi.ru/',
            'sub' => $oid,
            'aud' => EsiaConfig::clientId(),
            'urn:esia:sbj' => ['urn:esia:sbj:oid' => $oid],
        ];
        $header  = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $body    = self::b64url(json_encode($idtokenPayload));
        return [
            'access_token' => 'mock-access-' . bin2hex(random_bytes(8)),
            'id_token'     => $header . '.' . $body . '.',
            'oid'          => $oid,
            'raw'          => ['mock' => true],
        ];
    }

    private function mockProfile(string $oid): array
    {
        return [
            'firstName'  => 'Иван',
            'middleName' => 'Иванович',
            'lastName'   => 'Иванов',
            'birthDate'  => '01.01.1980',
            'gender'     => 'M',
            'snils'      => '000-000-000 00',
            'inn'        => '7728282160',
            'trusted'    => true,
            'updatedOn'  => time(),
            'oid'        => $oid,
            '_mock'      => true,
        ];
    }

    // ---------- helpers ----------

    private static function esiaTimestamp(): string
    {
        // ЕСИА требует формат: 2024.06.30 12:33:55 +0300
        return date('Y.m.d H:i:s O');
    }

    private static function extractOidFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) return null;
        $payload = json_decode((string)self::b64urlDecode($parts[1]), true);
        if (!is_array($payload)) return null;
        if (!empty($payload['urn:esia:sbj']['urn:esia:sbj:oid'])) {
            return (string)$payload['urn:esia:sbj']['urn:esia:sbj:oid'];
        }
        if (!empty($payload['sub'])) return (string)$payload['sub'];
        return null;
    }

    private function http(string $method, string $url, ?array $body, array $headers = []): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($body);
            }
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException("ESIA HTTP error: $err");
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data)) {
            throw new RuntimeException("ESIA returned non-JSON (HTTP $code): " . substr((string)$resp, 0, 300));
        }
        if ($code >= 400) {
            throw new RuntimeException("ESIA HTTP $code: " . json_encode($data));
        }
        return $data;
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return (string)base64_decode(strtr($s, '-_', '+/'));
    }
}

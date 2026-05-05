<?php
require_once __DIR__ . '/EsiaSignerInterface.php';

/**
 * Подписант, делегирующий ГОСТ-подпись внешнему микросервису.
 *
 * На InfinityFree CryptoPro CSP не поднять, поэтому планируется
 * отдельный сервис на VPS (Go/Java + jcp-bouncycastle или
 * cryptcp). Сервис принимает POST { "payload": "<строка>" }, проверяет
 * X-API-Key, возвращает { "signature": "<base64url-PKCS7>" }.
 *
 * Конфигурация:
 *   ESIA_SIGNER_REMOTE_URL — например https://signer.forsage.ct.ws/sign
 *   ESIA_SIGNER_REMOTE_KEY — общий секрет
 */
class RemoteGostSigner implements EsiaSignerInterface
{
    public function sign(string $payload): string
    {
        $url = (string)getenv('ESIA_SIGNER_REMOTE_URL');
        $key = (string)getenv('ESIA_SIGNER_REMOTE_KEY');
        if ($url === '' || $key === '') {
            throw new RuntimeException('RemoteGostSigner is not configured');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . $key,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            throw new RuntimeException("Remote signer failed (HTTP $code): $err");
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['signature'])) {
            throw new RuntimeException('Remote signer: invalid response');
        }
        return (string)$data['signature'];
    }

    public function name(): string
    {
        return 'gost_remote';
    }
}

<?php
require_once __DIR__ . '/EsiaSignerInterface.php';

/**
 * Заглушка подписанта для локальной разработки.
 *
 * Возвращает HMAC-SHA256 подпись от ESIA_CLIENT_ID — этого достаточно,
 * чтобы прогнать круг «нажал кнопку → редирект → callback → создание
 * пользователя» без боевого сертификата ГОСТ. На реальном контуре ЕСИА
 * такая подпись будет отвергнута.
 */
class MockSigner implements EsiaSignerInterface
{
    public function sign(string $payload): string
    {
        $key = (string)getenv('ESIA_CLIENT_ID') . '|mock';
        $raw = hash_hmac('sha256', $payload, $key, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function name(): string
    {
        return 'mock';
    }
}

<?php
/**
 * Интерфейс подписанта запросов к ЕСИА.
 *
 * ЕСИА требует, чтобы поле client_secret в запросах /ac и /te было
 * detached PKCS#7 подписью на ГОСТ Р 34.10-2012-256, посчитанной над
 * строкой scope+timestamp+client_id+state (для авторизации) или
 * client_id+scope+timestamp+state+redirect_uri (для обмена кода).
 *
 * Стандартный openssl без gost-engine это не умеет, поэтому подпись
 * считается либо через CryptoPro CSP на этом же сервере (драйвер
 * gost_local), либо удалённо вызовом микросервиса (gost_remote), либо
 * заглушкой для разработки (mock).
 */
interface EsiaSignerInterface
{
    /**
     * Подписать произвольную строку и вернуть base64url-результат
     * (без переносов, +/= → -_).
     *
     * @throws RuntimeException при недоступности подписанта
     */
    public function sign(string $payload): string;

    /**
     * Уникальный идентификатор драйвера, попадает в логи.
     */
    public function name(): string;
}

<?php
/**
 * Конфигурация интеграции с ЕСИА (Госуслуги).
 *
 * Все секреты читаются из переменных окружения. Если переменные не заданы —
 * интеграция выключена, кнопки «Войти через Госуслуги» и «Подписать через
 * Госуслуги» не показываются, существующие сценарии не ломаются.
 *
 * Для боевого контура нужны:
 *   ESIA_CLIENT_ID         — мнемоника ИС, выданная техпорталом ЕСИА
 *   ESIA_REDIRECT_URI      — https://forsage.ct.ws/esia_callback.php
 *   ESIA_SCOPES            — список scope через пробел
 *   ESIA_AUTH_URL          — URL endpoint авторизации
 *   ESIA_TOKEN_URL         — URL endpoint обмена кода на токен
 *   ESIA_RS_URL            — базовый URL REST API (rs/prns/{oid})
 *   ESIA_SIGNER_DRIVER     — mock | gost_remote
 *   ESIA_SIGNER_REMOTE_URL — для gost_remote: URL микросервиса-подписанта
 *   ESIA_SIGNER_REMOTE_KEY — для gost_remote: API-ключ микросервиса
 */

class EsiaConfig
{
    public static function isEnabled(): bool
    {
        return !empty(getenv('ESIA_CLIENT_ID'))
            && !empty(getenv('ESIA_REDIRECT_URI'));
    }

    public static function clientId(): string
    {
        return (string)getenv('ESIA_CLIENT_ID');
    }

    public static function redirectUri(): string
    {
        return (string)getenv('ESIA_REDIRECT_URI');
    }

    public static function scopes(): string
    {
        $defaults = 'openid fullname birthdate gender snils inn email mobile contacts';
        $env = getenv('ESIA_SCOPES');
        return $env !== false && $env !== '' ? $env : $defaults;
    }

    public static function authUrl(): string
    {
        $env = getenv('ESIA_AUTH_URL');
        return $env !== false && $env !== ''
            ? $env
            : 'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v3/ac';
    }

    public static function tokenUrl(): string
    {
        $env = getenv('ESIA_TOKEN_URL');
        return $env !== false && $env !== ''
            ? $env
            : 'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v3/te';
    }

    public static function rsUrl(): string
    {
        $env = getenv('ESIA_RS_URL');
        return $env !== false && $env !== ''
            ? $env
            : 'https://esia-portal1.test.gosuslugi.ru/rs';
    }

    public static function signerDriver(): string
    {
        $env = getenv('ESIA_SIGNER_DRIVER');
        return $env !== false && $env !== '' ? $env : 'mock';
    }

    public static function signerRemoteUrl(): string
    {
        return (string)getenv('ESIA_SIGNER_REMOTE_URL');
    }

    public static function signerRemoteKey(): string
    {
        return (string)getenv('ESIA_SIGNER_REMOTE_KEY');
    }

    public static function isMockMode(): bool
    {
        return self::signerDriver() === 'mock';
    }
}

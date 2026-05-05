<?php
/**
 * Конфиг OAuth-провайдеров (Yandex, VK).
 *
 * Все секреты — из переменных окружения. Если ключи провайдера не заданы,
 * соответствующая кнопка не показывается, callback отдаёт 503.
 *
 * Yandex (oauth.yandex.ru/client/new):
 *   YANDEX_CLIENT_ID
 *   YANDEX_CLIENT_SECRET
 *   YANDEX_REDIRECT_URI   = https://forsage.ct.ws/yandex_callback.php
 *
 * VK ID (id.vk.com / vk.com/apps?act=manage):
 *   VK_CLIENT_ID          (он же app_id)
 *   VK_CLIENT_SECRET      (secure_key)
 *   VK_REDIRECT_URI       = https://forsage.ct.ws/vk_callback.php
 *   VK_API_VERSION        = 5.199 (по умолчанию)
 */

class OAuthConfig
{
    public static function yandexEnabled(): bool
    {
        return !empty(getenv('YANDEX_CLIENT_ID'))
            && !empty(getenv('YANDEX_CLIENT_SECRET'))
            && !empty(getenv('YANDEX_REDIRECT_URI'));
    }

    public static function yandexClientId(): string     { return (string)getenv('YANDEX_CLIENT_ID'); }
    public static function yandexClientSecret(): string { return (string)getenv('YANDEX_CLIENT_SECRET'); }
    public static function yandexRedirectUri(): string  { return (string)getenv('YANDEX_REDIRECT_URI'); }

    public static function vkEnabled(): bool
    {
        return !empty(getenv('VK_CLIENT_ID'))
            && !empty(getenv('VK_CLIENT_SECRET'))
            && !empty(getenv('VK_REDIRECT_URI'));
    }

    public static function vkClientId(): string     { return (string)getenv('VK_CLIENT_ID'); }
    public static function vkClientSecret(): string { return (string)getenv('VK_CLIENT_SECRET'); }
    public static function vkRedirectUri(): string  { return (string)getenv('VK_REDIRECT_URI'); }
    public static function vkApiVersion(): string
    {
        $v = getenv('VK_API_VERSION');
        return $v !== false && $v !== '' ? $v : '5.199';
    }

    /** Хотя бы один провайдер настроен — показываем разделитель и блок «Войти через». */
    public static function anyEnabled(): bool
    {
        return self::yandexEnabled() || self::vkEnabled();
    }
}

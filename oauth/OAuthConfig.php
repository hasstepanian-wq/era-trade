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
    /** Кэш для значений из локального secrets.local.php. */
    private static ?array $localCache = null;

    /**
     * Возвращает значение по имени переменной. Источники приоритета:
     *   1. getenv()        — обычные сервера, Apache SetEnv → mod_php.
     *   2. $_SERVER        — большинство FastCGI / PHP-FPM (включая InfinityFree),
     *                        куда SetEnv пробрасывается, но getenv() их не видит.
     *   3. $_ENV           — если PHP с auto_globals_jit и enable variables_order=E.
     *   4. oauth/secrets.local.php — fallback-файл с массивом значений.
     *      Файл не коммитится в git, лежит только на сервере.
     */
    public static function get(string $name): string
    {
        $v = getenv($name);
        if ($v !== false && $v !== '') return (string)$v;

        if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return (string)$_SERVER[$name];
        }
        $redirectKey = 'REDIRECT_' . $name;
        if (isset($_SERVER[$redirectKey]) && $_SERVER[$redirectKey] !== '') {
            return (string)$_SERVER[$redirectKey];
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return (string)$_ENV[$name];
        }

        if (self::$localCache === null) {
            $localPath = __DIR__ . '/secrets.local.php';
            if (is_file($localPath)) {
                $loaded = @include $localPath;
                self::$localCache = is_array($loaded) ? $loaded : [];
            } else {
                self::$localCache = [];
            }
        }
        return (string)(self::$localCache[$name] ?? '');
    }

    public static function yandexEnabled(): bool
    {
        return self::get('YANDEX_CLIENT_ID') !== ''
            && self::get('YANDEX_CLIENT_SECRET') !== ''
            && self::get('YANDEX_REDIRECT_URI') !== '';
    }

    public static function yandexClientId(): string     { return self::get('YANDEX_CLIENT_ID'); }
    public static function yandexClientSecret(): string { return self::get('YANDEX_CLIENT_SECRET'); }
    public static function yandexRedirectUri(): string  { return self::get('YANDEX_REDIRECT_URI'); }

    public static function vkEnabled(): bool
    {
        return self::get('VK_CLIENT_ID') !== ''
            && self::get('VK_CLIENT_SECRET') !== ''
            && self::get('VK_REDIRECT_URI') !== '';
    }

    public static function vkClientId(): string     { return self::get('VK_CLIENT_ID'); }
    public static function vkClientSecret(): string { return self::get('VK_CLIENT_SECRET'); }
    public static function vkRedirectUri(): string  { return self::get('VK_REDIRECT_URI'); }
    public static function vkApiVersion(): string
    {
        $v = self::get('VK_API_VERSION');
        return $v !== '' ? $v : '5.199';
    }

    /** Хотя бы один провайдер настроен — показываем разделитель и блок «Войти через». */
    public static function anyEnabled(): bool
    {
        return self::yandexEnabled() || self::vkEnabled();
    }
}

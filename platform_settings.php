<?php
/**
 * platform_settings.php
 *
 * Простое key/value-хранилище для платформенных настроек.
 * Используется для переключателей вроде «по умолчанию требовать ЭЦП на лотах».
 *
 * Подключается через @include_once в местах, где настройка может пригодиться,
 * без жёсткой зависимости — отсутствие функции `setting_get` означает «фолбэк
 * к дефолту».
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_settings (
            setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('platform_settings table init: ' . $e->getMessage());
}

if (!function_exists('setting_get')) {
    /**
     * Получить значение настройки. Если строки нет — возвращает $default.
     * Значения читаются как строки; для bool/int приведите тип на стороне
     * вызывающего кода.
     */
    function setting_get(string $key, $default = null)
    {
        global $pdo;
        try {
            $st = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $st->execute([$key]);
            $row = $st->fetch();
            if ($row && array_key_exists('setting_value', $row) && $row['setting_value'] !== null) {
                return $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("setting_get($key): " . $e->getMessage());
        }
        return $default;
    }
}

if (!function_exists('setting_set')) {
    /**
     * Записать значение настройки. Передавайте $value как строку.
     */
    function setting_set(string $key, $value): bool
    {
        global $pdo;
        try {
            $st = $pdo->prepare(
                'INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            return $st->execute([$key, (string)$value]);
        } catch (Exception $e) {
            error_log("setting_set($key): " . $e->getMessage());
            return false;
        }
    }
}

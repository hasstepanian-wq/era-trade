<?php
/**
 * Миграция под универсальные OAuth-провайдеры.
 *
 * Создаёт таблицу social_accounts (user_id, provider, external_id, …).
 * Идемпотентно. Не трогает существующие колонки в users.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../db.php';
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS social_accounts (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id       INT UNSIGNED NOT NULL,
            provider      VARCHAR(20) NOT NULL,
            external_id   VARCHAR(64) NOT NULL,
            email         VARCHAR(255) NULL,
            display_name  VARCHAR(255) NULL,
            avatar_url    VARCHAR(512) NULL,
            raw_profile   LONGTEXT NULL,
            linked_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME NULL,
            UNIQUE KEY uk_provider_extid (provider, external_id),
            INDEX idx_user (user_id),
            INDEX idx_provider (provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('OAuthSchema error: ' . $e->getMessage());
}

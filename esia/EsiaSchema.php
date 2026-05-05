<?php
/**
 * Миграция схемы под интеграцию с ЕСИА.
 *
 *  - Добавляет в users поля: esia_oid, esia_snils, esia_inn, esia_trusted, esia_linked_at
 *  - Создаёт esia_signatures для журнала подписей документов
 *
 * Идемпотентно: использует SHOW COLUMNS / IF NOT EXISTS.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../db.php';
}

function esia_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        if ($st && !$st->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    } catch (Exception $e) {
        error_log("esia_schema (add $table.$column) error: " . $e->getMessage());
    }
}

try {
    esia_add_column_if_missing($pdo, 'users', 'esia_oid',       'VARCHAR(32) NULL');
    esia_add_column_if_missing($pdo, 'users', 'esia_snils',     'VARCHAR(20) NULL');
    esia_add_column_if_missing($pdo, 'users', 'esia_inn',       'VARCHAR(20) NULL');
    esia_add_column_if_missing($pdo, 'users', 'esia_trusted',   "TINYINT(1) NOT NULL DEFAULT 0");
    esia_add_column_if_missing($pdo, 'users', 'esia_linked_at', 'DATETIME NULL');

    // Уникальный индекс на esia_oid (если ещё нет)
    try {
        $st = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_esia_oid'");
        if ($st && !$st->fetch()) {
            $pdo->exec("CREATE UNIQUE INDEX idx_users_esia_oid ON users (esia_oid)");
        }
    } catch (Exception $e) {
        error_log('esia_schema (idx_users_esia_oid) error: ' . $e->getMessage());
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS esia_signatures (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id       INT UNSIGNED NOT NULL,
            doc_type      VARCHAR(40) NOT NULL,
            doc_id        VARCHAR(80) NOT NULL,
            doc_hash      VARCHAR(128) NOT NULL,
            signature     LONGTEXT NULL,
            esia_response LONGTEXT NULL,
            status        ENUM('pending','signed','failed') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            signed_at     DATETIME NULL,
            INDEX idx_user (user_id),
            INDEX idx_doc  (doc_type, doc_id),
            INDEX idx_stat (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('EsiaSchema error: ' . $e->getMessage());
}

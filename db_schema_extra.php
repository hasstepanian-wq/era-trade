<?php
/**
 * db_schema_extra.php
 *
 * Создаёт дополнительные таблицы для:
 *  - запроса котировок и запроса предложений (lot_offers)
 *  - закрытого аукциона (closed_participants)
 *
 * Подключается из соответствующих страниц/обработчиков. Использует
 * CREATE TABLE IF NOT EXISTS, поэтому безопасно вызывать многократно.
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/db.php';
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lot_offers (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lot_id        INT UNSIGNED NOT NULL,
            user_id       INT UNSIGNED NOT NULL,
            offer_type    ENUM('quotation','proposal') NOT NULL,
            price         DECIMAL(15,2) NOT NULL,
            comment       TEXT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY u_lot_user (lot_id, user_id),
            INDEX idx_lot (lot_id),
            INDEX idx_user (user_id),
            INDEX idx_type (offer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS closed_participants (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lot_id           INT UNSIGNED NOT NULL,
            user_id          INT UNSIGNED NOT NULL,
            application_text TEXT NULL,
            status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            decided_at       DATETIME NULL,
            decided_by       INT UNSIGNED NULL,
            decision_comment TEXT NULL,
            UNIQUE KEY u_lot_user (lot_id, user_id),
            INDEX idx_lot (lot_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('db_schema_extra error: ' . $e->getMessage());
}

<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/error_helper.php';

// Если сессия слетела — подхватываем Dealer1
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
}

$lot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if ($lot_id > 0) {
    try {
        // Простейшая вставка без лишних колонок
        // Если запись уже есть, INSERT IGNORE её просто пропустит
        $stmt = $pdo->prepare("INSERT IGNORE INTO lot_participants (lot_id, user_id) VALUES (?, ?)");
        $stmt->execute([$lot_id, $user_id]);
        
        // Сразу летим обратно в лот
        header("Location: lot_details.php?id=$lot_id");
        exit();
    } catch (Exception $e) {
        era_error_page(500, 'Ошибка базы данных', 'Не удалось подтвердить участие. Попробуйте ещё раз.');
    }
} else {
    era_error_page(400, 'ID лота не передан', 'Откройте страницу из реестра торгов.');
}
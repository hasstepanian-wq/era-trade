<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод должен быть POST']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$user_type = $_POST['user_type'] ?? 'respected';
$express = isset($_POST['express_fee']) ? (int)$_POST['express_fee'] : 0;
$agree_regulations   = !empty($_POST['agree_regulations']);
$agree_personal_data = !empty($_POST['agree_personal_data']);

if (!$username || !$full_name || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
    exit;
}

if (!$agree_regulations) {
    echo json_encode(['success' => false, 'message' => 'Необходимо принять условия Регламента площадки']);
    exit;
}
if (!$agree_personal_data) {
    echo json_encode(['success' => false, 'message' => 'Необходимо согласие на обработку персональных данных']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Неверный формат email']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Пароль минимум 6 символов']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Пользователь с таким логином или email уже существует']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (username, full_name, email, password, user_type, balance) VALUES (?, ?, ?, ?, ?, ?)');
    $balance = 0;
    if (!$stmt->execute([$username, $full_name, $email, $hash, $user_type, $balance])) {
        throw new Exception('Не удалось создать пользователя');
    }

    $user_id = $pdo->lastInsertId();

    // Сохранить прикрепленные документы (если есть)
    $uploadDir = __DIR__ . '/uploads/docs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    foreach (['file1','file2','file3'] as $index => $input) {
        if (!empty($_FILES[$input]['tmp_name']) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
            $target = $uploadDir . $user_id . '_' . ($index + 1) . '_' . basename($_FILES[$input]['name']);
            move_uploaded_file($_FILES[$input]['tmp_name'], $target);
        }
    }

    // Журнал согласий — для соблюдения 152-ФЗ. Таблица создаётся лениво.
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_consents (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id     INT UNSIGNED NOT NULL,
                consent_type VARCHAR(64) NOT NULL,
                ip_address  VARCHAR(64) NULL,
                user_agent  VARCHAR(255) NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (consent_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $consentStmt = $pdo->prepare("INSERT INTO user_consents (user_id, consent_type, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        foreach (['regulations', 'personal_data'] as $ct) {
            $consentStmt->execute([$user_id, $ct, $ip, $ua]);
        }
    } catch (Exception $consentErr) {
        error_log('register_handler consent log error: ' . $consentErr->getMessage());
    }

    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_balance'] = $balance;

    echo json_encode(['success' => true, 'message' => 'Регистрация успешна', 'user_id' => $user_id]);
} catch (Exception $e) {
    error_log('register_handler error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка регистрации. Попробуйте ещё раз.']);
}

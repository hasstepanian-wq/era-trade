<?php
/**
 * Обратный вызов от ЕСИА после авторизации.
 * Меняет authorization_code на access_token, тянет профиль, линкует/создаёт пользователя,
 * выставляет $_SESSION и редиректит обратно в return_to.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/esia/EsiaConfig.php';
require_once __DIR__ . '/esia/EsiaClient.php';
require_once __DIR__ . '/esia/EsiaSchema.php';

$err = function (string $msg, int $code = 400) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Ошибка входа через Госуслуги</h1>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/">На главную</a></p>';
    exit;
};

if (!EsiaConfig::isEnabled())                 $err('ЕСИА не настроена.', 503);
if (isset($_GET['error']))                    $err('ЕСИА вернула ошибку: ' . (string)$_GET['error']);
if (empty($_GET['code']) || empty($_GET['state'])) $err('В ответе нет code/state.');

$expected = $_SESSION['esia_state'] ?? '';
if (!hash_equals((string)$expected, (string)$_GET['state'])) $err('state не совпадает (CSRF).');

try {
    $client  = new EsiaClient();
    $token   = $client->exchangeCode((string)$_GET['code'], (string)$_GET['state']);
    $oid     = (string)($token['oid'] ?? '');
    if ($oid === '') $err('Не удалось извлечь OID из id_token.');

    $profile = $client->fetchProfile((string)$token['access_token'], $oid);

    $fullName = trim(
        ($profile['lastName'] ?? '') . ' ' .
        ($profile['firstName'] ?? '') . ' ' .
        ($profile['middleName'] ?? '')
    );
    $snils = (string)($profile['snils'] ?? '');
    $inn   = (string)($profile['inn']   ?? '');
    $trust = !empty($profile['trusted']) ? 1 : 0;

    // 1. Если уже есть пользователь с этим esia_oid — логиним.
    $st = $pdo->prepare('SELECT id, username, full_name, balance, user_type FROM users WHERE esia_oid = ? LIMIT 1');
    $st->execute([$oid]);
    $user = $st->fetch();

    if (!$user) {
        // 2. Иначе создаём нового. Username — esia_<oid>, пароль — рандом.
        $username = 'esia_' . substr($oid, 0, 12);
        $email    = 'esia+' . $oid . '@forsage.ct.ws';
        $passhash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $type     = 'respected';

        $ins = $pdo->prepare(
            'INSERT INTO users (username, full_name, email, password, user_type, balance, esia_oid, esia_snils, esia_inn, esia_trusted, esia_linked_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([$username, $fullName ?: $username, $email, $passhash, $type, $oid, $snils, $inn, $trust]);
        $userId = (int)$pdo->lastInsertId();
        $user = [
            'id'        => $userId,
            'username'  => $username,
            'full_name' => $fullName ?: $username,
            'balance'   => 0,
            'user_type' => $type,
        ];
    } else {
        // Обновляем поля ЕСИА на каждом входе (СНИЛС/ИНН могли поменяться).
        $upd = $pdo->prepare('UPDATE users SET esia_snils = ?, esia_inn = ?, esia_trusted = ?, esia_linked_at = NOW() WHERE id = ?');
        $upd->execute([$snils, $inn, $trust, (int)$user['id']]);
    }

    $_SESSION['user_id']      = (int)$user['id'];
    $_SESSION['user_name']    = $user['full_name'];
    $_SESSION['user_balance'] = (float)$user['balance'];
    $_SESSION['usertype']     = $user['user_type'];
    $_SESSION['esia_oid']     = $oid;

    unset($_SESSION['esia_state'], $_SESSION['esia_nonce']);

    $returnTo = (string)($_SESSION['esia_return_to'] ?? '/profile.php');
    unset($_SESSION['esia_return_to']);
    if (!preg_match('#^/[^/]#', $returnTo)) $returnTo = '/profile.php';

    header('Location: ' . $returnTo);
    exit;
} catch (Throwable $e) {
    error_log('esia_callback error: ' . $e->getMessage());
    $err($e->getMessage(), 500);
}

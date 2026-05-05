<?php
/**
 * Старт flow подписи документа через ЕСИА.
 * Принимает: doc_type, doc_id (например, ставка), doc_hash, doc_title.
 * Создаёт строку в esia_signatures (status=pending), редиректит пользователя в ЕСИА.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/esia/EsiaConfig.php';
require_once __DIR__ . '/esia/EsiaClient.php';
require_once __DIR__ . '/esia/EsiaSchema.php';

if (!EsiaConfig::isEnabled())            { http_response_code(503); exit('ЕСИА не настроена'); }
if (empty($_SESSION['user_id']))         { http_response_code(401); exit('Необходима авторизация'); }
if (empty($_SESSION['esia_oid']))        { http_response_code(403); exit('Сначала войдите через Госуслуги'); }

$docType  = (string)($_POST['doc_type']  ?? $_GET['doc_type']  ?? '');
$docId    = (string)($_POST['doc_id']    ?? $_GET['doc_id']    ?? '');
$docHash  = (string)($_POST['doc_hash']  ?? $_GET['doc_hash']  ?? '');
$docTitle = (string)($_POST['doc_title'] ?? $_GET['doc_title'] ?? 'Документ');

if ($docType === '' || $docId === '' || $docHash === '') {
    http_response_code(400);
    exit('Не переданы doc_type/doc_id/doc_hash');
}

$state = bin2hex(random_bytes(16));
$_SESSION['esia_sign_state']    = $state;
$_SESSION['esia_sign_doc_type'] = $docType;
$_SESSION['esia_sign_doc_id']   = $docId;

try {
    $ins = $pdo->prepare('INSERT INTO esia_signatures (user_id, doc_type, doc_id, doc_hash, status) VALUES (?, ?, ?, ?, "pending")');
    $ins->execute([(int)$_SESSION['user_id'], $docType, $docId, $docHash]);

    $client = new EsiaClient();
    $callback = (string)getenv('ESIA_SIGN_CALLBACK_URI') ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'forsage.ct.ws') . '/esia_sign_callback.php');
    $url = $client->buildSignDocumentUrl($docHash, $docTitle, $callback, $state);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    error_log('esia_sign error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Ошибка запуска подписи: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

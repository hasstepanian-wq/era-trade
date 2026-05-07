<?php
/**
 * Обратный вызов от ЕСИА после подписи документа.
 * Сохраняет подпись/ошибку в esia_signatures, делает редирект обратно
 * к странице документа (по doc_type).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/esia/EsiaConfig.php';
require_once __DIR__ . '/esia/EsiaSchema.php';

if (!EsiaConfig::isEnabled())     { http_response_code(503); exit('ЕСИА не настроена'); }
if (empty($_SESSION['user_id'])) { http_response_code(401); exit('Не авторизованы'); }

$expected = $_SESSION['esia_sign_state'] ?? '';
$state    = (string)($_GET['state'] ?? '');
$docType  = (string)($_SESSION['esia_sign_doc_type'] ?? '');
$docId    = (string)($_SESSION['esia_sign_doc_id']   ?? '');

if (!hash_equals((string)$expected, $state)) { http_response_code(400); exit('state не совпадает'); }
unset($_SESSION['esia_sign_state']);

try {
    if (isset($_GET['error'])) {
        $upd = $pdo->prepare('UPDATE esia_signatures SET status = "failed", error_message = ?, signed_at = NOW() WHERE user_id = ? AND doc_type = ? AND doc_id = ? AND status = "pending"');
        $upd->execute([(string)$_GET['error'], (int)$_SESSION['user_id'], $docType, $docId]);
    } else {
        $signature = (string)($_GET['signature'] ?? $_POST['signature'] ?? '');
        $raw       = json_encode($_GET);
        $upd = $pdo->prepare('UPDATE esia_signatures SET status = "signed", signature = ?, esia_response = ?, signed_at = NOW() WHERE user_id = ? AND doc_type = ? AND doc_id = ? AND status = "pending"');
        $upd->execute([$signature, $raw, (int)$_SESSION['user_id'], $docType, $docId]);
    }
} catch (Throwable $e) {
    error_log('esia_sign_callback error: ' . $e->getMessage());
}

// Куда вернуть пользователя — зависит от типа документа.
$return = '/profile.php';
switch ($docType) {
    case 'lot_offer':
    case 'lot_proposal':
    case 'lot_quotation':
        $return = '/torgi_view.php?lot=' . urlencode($docId);
        break;
    case 'closed_application':
        $return = '/lot_closed.php?lot=' . urlencode($docId);
        break;
}
header('Location: ' . $return);

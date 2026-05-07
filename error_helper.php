<?php
/**
 * Minimal mobile-friendly error page renderer.
 * Use instead of `die("text")` to wrap error output in proper HTML so the
 * page is readable on mobile (viewport meta + responsive layout).
 *
 * Example:
 *   require_once __DIR__ . '/error_helper.php';
 *   if (!$lot) era_error_page(404, 'Лот не найден', 'Лот №' . $id . ' не найден или удалён.');
 */
if (!function_exists('era_error_page')) {
    function era_error_page(int $http_code, string $title, string $message = '', string $back_url = '/reestr.php', string $back_label = '← В реестр'): void
    {
        http_response_code($http_code);
        $titleSafe   = htmlspecialchars($title,   ENT_QUOTES, 'UTF-8');
        $messageSafe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $backUrlSafe = htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8');
        $backLabelSafe = htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8');
        ?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title><?= $titleSafe ?> — ФОРСАЖ</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 16px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.err-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: clamp(24px, 6vw, 40px); width: 100%; max-width: 460px; text-align: center; }
.err-code { font-size: clamp(40px, 12vw, 72px); font-weight: 900; color: #38bdf8; line-height: 1; margin-bottom: 8px; }
.err-title { font-size: clamp(18px, 4vw, 22px); font-weight: 800; color: #fff; margin-bottom: 12px; }
.err-msg { font-size: 14px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
.err-back { display: inline-block; padding: 12px 22px; background: #38bdf8; color: #0f172a; border-radius: 10px; font-weight: 700; text-decoration: none; }
.err-back:hover { background: #0ea5e9; }
</style>
</head>
<body>
<div class="err-card">
    <div class="err-code"><?= $http_code ?></div>
    <div class="err-title"><?= $titleSafe ?></div>
    <?php if ($messageSafe !== ''): ?><div class="err-msg"><?= $messageSafe ?></div><?php endif; ?>
    <a class="err-back" href="<?= $backUrlSafe ?>"><?= $backLabelSafe ?></a>
</div>
</body>
</html><?php
        exit;
    }
}

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$num = time();
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Счёт на оплату №<?= $num ?> — ФОРСАЖ</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
* { box-sizing: border-box; }
body { background: #0f172a; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 16px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.invoice { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: clamp(24px, 6vw, 40px); width: 100%; max-width: 460px; }
.invoice h1 { color: #fff; font-size: clamp(22px, 5vw, 28px); margin: 0 0 12px; }
.invoice p { color: #94a3b8; line-height: 1.6; margin: 0 0 16px; }
.invoice a.btn { display: inline-block; padding: 12px 22px; background: #38bdf8; color: #0f172a; border-radius: 10px; font-weight: 700; text-decoration: none; margin-top: 8px; }
</style>
</head>
<body>
<div class="invoice">
    <h1>Счёт на оплату №<?= $num ?></h1>
    <p>Оплатите по реквизитам и загрузите чек в разделе «Оплата».</p>
    <a class="btn" href="payment.php">Перейти к загрузке чека</a>
</div>
</body>
</html>

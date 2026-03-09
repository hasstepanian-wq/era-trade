<?php
$lotId = $_GET['id'] ?? null;
$lots = json_decode(file_get_contents('database.json'), true);
$currentLot = null;
if ($lots) {
    foreach ($lots as $l) { if ($l['id'] == $lotId) { $currentLot = $l; break; } }
}
if (!$currentLot) { die("Лот не найден. <a href='reestr.php'>Назад</a>"); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($currentLot['title']) ?></title>
    <style>
        body { margin: 0; background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        .lot-container { max-width: 1000px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .price-tag { font-size: 32px; font-weight: 800; color: #0099d5; }
        .btn-bid { display: inline-block; padding: 16px 40px; background: #0099d5; color: #fff; border-radius: 10px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; }
        .char-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .char-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="lot-container">
        <a href="reestr.php" style="color: #0099d5; text-decoration: none;">← Вернуться в реестр</a>
        <h1><?= htmlspecialchars($currentLot['title']) ?></h1>
        <p>ID лота: <?= $currentLot['id'] ?></p>
        <div class="price-tag"><?= number_format($currentLot['price'], 0, '', ' ') ?> ₽</div>
        <br>
        <button class="btn-bid" onclick="location.href='finances.html'">ПОПОЛНИТЬ БАЛАНС И ПОДАТЬ ЗАЯВКУ</button>
        <table class="char-table">
            <tr><td>Тип процедуры</td><td><b><?= $currentLot['type'] ?></b></td></tr>
            <tr><td>Статус</td><td><b><?= $currentLot['status'] ?></b></td></tr>
        </table>
    </div>
</body>
</html>
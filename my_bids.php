<?php
error_reporting(0);
ini_set('display_errors', 0);

$lots_file = __DIR__ . '/database.json';
$bids_file = __DIR__ . '/bids.json';

$lots_data = is_file($lots_file) ? (json_decode(@file_get_contents($lots_file), true) ?: []) : [];
$bids_data = is_file($bids_file) ? (json_decode(@file_get_contents($bids_file), true) ?: []) : [];

$current_id = "2"; // Наш юзер
$my_bids = [];

// Собираем только те заявки, которые подал текущий юзер
foreach ($bids_data as $bid) {
    if ($bid['user_id'] == $current_id) {
        // Ищем инфу о лоте для этой заявки
        foreach ($lots_data as $lot) {
            if ($lot['id'] == $bid['lot_id']) {
                $my_bids[] = [
                    "lot_id" => $lot['id'],
                    "title" => $lot['title'],
                    "my_price" => $bid['price'],
                    "deadline" => $lot['deadline'],
                    "type" => $lot['type']
                ];
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои заявки - ЕРА</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <style>
        * { box-sizing: border-box; }
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: auto; }
        .header { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .bid-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; border-left: 6px solid #28a745; }
        .bid-card > div { min-width: 0; flex: 1 1 240px; word-break: break-word; }
        .bid-card > div:last-child { text-align: right; flex: 0 0 auto; }
        .expired { border-left-color: #dc3545; opacity: 0.8; }
        .btn-action { display: inline-block; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px; }
        .btn-edit { background: #e3f2fd; color: #1976d2; border: 1px solid #1976d2; }
        .btn-wait { background: #f8f9fa; color: #666; border: 1px solid #ddd; cursor: default; }
        .price-tag { font-size: 18px; font-weight: bold; color: #28a745; }
        @media (max-width: 480px) {
            body { padding: 12px; }
            .bid-card { padding: 16px; gap: 12px; }
            .bid-card > div:last-child { text-align: left; width: 100%; }
            .btn-action { width: 100%; text-align: center; padding: 12px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0;">МОИ ЗАЯВКИ</h1>
            <p><a href="reestr.php" style="color:#007bff;">← Вернуться в реестр процедур</a></p>
        </div>

        <?php if (empty($my_bids)): ?>
            <div style="text-align:center; padding:50px; background:#fff; border-radius:12px;">
                У вас пока нет активных заявок. <br>
                <a href="reestr.php" style="color:#007bff;">Перейти в реестр и выбрать лот</a>
            </div>
        <?php else: ?>
            <?php foreach ($my_bids as $item): 
                $is_expired = strtotime($item['deadline']) < time();
            ?>
            <div class="bid-card <?= $is_expired ? 'expired' : '' ?>">
                <div>
                    <div style="font-weight:bold; font-size:16px;"><?= $item['title'] ?></div>
                    <div style="font-size:12px; color:#888; margin-top:5px;">
                        Тип: <?= ($item['type'] == 'quotes' ? 'Запрос котировок' : 'Закрытый аукцион') ?>
                    </div>
                    <div style="margin-top:10px;">
                        Ваше предложение: <span class="price-tag"><?= number_format($item['my_price'], 0, '.', ' ') ?> ₽</span>
                    </div>
                </div>

                <div style="text-align:right;">
                    <div style="font-size:13px; margin-bottom:10px; color: <?= $is_expired ? '#dc3545' : '#666' ?>;">
                        <?= $is_expired ? '🛑 Торги завершены' : '⏱ До: ' . $item['deadline'] ?>
                    </div>
                    
                    <?php if (!$is_expired): ?>
                        <a href="lot.php?id=<?= $item['lot_id'] ?>" class="btn-action btn-edit">ПРАВИТЬ / ОТОЗВАТЬ</a>
                    <?php else: ?>
                        <span class="btn-action btn-wait">ОЖИДАНИЕ ИТОГОВ</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// 1. Загружаем данные из базы (теперь без ../ - файл должен лежать в той же папке)
$lots = json_decode(file_get_contents('database.json'), true);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Реестр процедур — ЭТП ЭРА</title>
    <style>
        body { margin: 0; background: #f0f4f8; font-family: 'Segoe UI', Tahoma, sans-serif; color: #1e293b; }
        .page-container { padding: 40px; max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 28px; margin-bottom: 25px; color: #0f172a; }
        .filter-bar { 
            background: #fff; padding: 20px; border-radius: 16px; 
            display: flex; gap: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 30px; align-items: flex-end; border: 1px solid #e2e8f0;
        }
        .filter-group { flex: 1; }
        .filter-group label { display: block; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; font-weight: 700; }
        input, select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; }
        .lots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .lot-card { 
            background: #fff; border-radius: 16px; border: 1px solid #e2e8f0;
            padding: 24px; display: flex; flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s; position: relative; min-height: 280px;
        }
        .lot-card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px rgba(0,0,0,0.08); cursor: pointer; }
        .lot-id { font-size: 12px; color: #94a3b8; margin-bottom: 8px; font-weight: 600; }
        .lot-title { font-size: 18px; font-weight: 700; color: #0f172a; line-height: 1.4; margin-bottom: 15px; flex-grow: 1; }
        .lot-price { font-size: 22px; font-weight: 800; color: #0099d5; margin-bottom: 20px; }
        .lot-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #f1f5f9; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .badge-open { background: #e0f2fe; color: #0369a1; }
        .badge-comm { background: #dcfce7; color: #15803d; }
    </style>
</head>
<body>
    <div class="page-container">
        <h1>Реестр торговых процедур</h1>
        <div class="filter-bar">
            <div class="filter-group">
                <label>Поиск по названию</label>
                <input type="text" id="searchInput" onkeyup="filterLots()" placeholder="Введите название...">
            </div>
            <div class="filter-group" style="max-width: 250px;">
                <label>Тип процедуры</label>
                <select id="typeFilter" onchange="filterLots()">
                    <option value="all">Все типы</option>
                    <option value="open">Открытый аукцион</option>
                    <option value="commission">Комиссионная продажа</option>
                </select>
            </div>
        </div>
        <div class="lots-grid" id="lotsContainer">
            <?php if($lots): foreach($lots as $l): ?>
            <div class="lot-card" data-type="<?= $l['type'] ?>" onclick="location.href='lot.php?id=<?= $l['id'] ?>'">
                <div class="lot-id">№ <?= $l['id'] ?></div>
                <div class="lot-title"><?= htmlspecialchars($l['title']) ?></div>
                <div class="lot-price"><?= number_format($l['price'], 0, '', ' ') ?> ₽</div>
                <div class="lot-footer">
                    <span class="badge <?= $l['type'] == 'commission' ? 'badge-comm' : 'badge-open' ?>">
                        <?= $l['type'] == 'commission' ? 'Комиссия' : 'Аукцион' ?>
                    </span>
                    <span style="color: #0099d5; font-weight: bold;">ПОДРОБНЕЕ →</span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <script>
    function filterLots() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;
        document.querySelectorAll('.lot-card').forEach(card => {
            const title = card.querySelector('.lot-title').innerText.toLowerCase();
            const matchesSearch = title.includes(query);
            const matchesType = (type === 'all' || card.getAttribute('data-type') === type);
            card.style.display = (matchesSearch && matchesType) ? 'flex' : 'none';
        });
    }
    </script>
</body>
</html>
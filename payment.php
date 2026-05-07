<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Загрузка чека — ФОРСАЖ</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
* { box-sizing: border-box; }
body { background:#0f172a; color:#fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 16px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.pay-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: clamp(24px, 6vw, 40px); width: 100%; max-width: 460px; text-align: center; }
.pay-card h2 { color: #fff; margin: 0 0 16px; font-size: clamp(20px, 4.5vw, 24px); }
.pay-card input[type="file"] { width: 100%; padding: 12px; background: #0f172a; border: 1px dashed #334155; border-radius: 10px; color: #94a3b8; margin-bottom: 16px; }
.pay-card button { width: 100%; padding: 14px; background: #38bdf8; color: #0f172a; border: none; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; }
.pay-card button:hover { background: #0ea5e9; }
</style>
</head>
<body>
<div class="pay-card">
    <h2>Загрузите чек об оплате</h2>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="chek" accept="image/*,application/pdf">
        <button type="submit">Отправить</button>
    </form>
</div>
</body>
</html>

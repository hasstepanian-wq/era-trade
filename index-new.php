<?php
/* index-new.php — пилотный «3D-главный» сайта ЭРА ЭТП.
   Открывается по адресу forsage.ct.ws/index-new.php, существует параллельно с
   основной index.php — её мы НЕ ломаем. Если этот вариант устроит — заменим.

   Стек (всё с CDN, без сборки):
     - Three.js          — 3D-сцена (логотип-метеор, частицы, звёзды).
     - GSAP + ScrollTrig — скролл-сторителлинг по «актам».
     - Lenis             — плавный скролл.

   Адаптив:
     - На устройствах <=640px Three.js НЕ грузится (#hero-bg-canvas остаётся
       пустым, его заменяет CSS-параллакс с PNG-логотипом).
     - prefers-reduced-motion: reduce — анимации полностью выключаются.
     - deviceMemory<4 или hardwareConcurrency<4 — тоже фолбэк на статику. */
session_start();
$lang = $_SESSION['lang'] ?? 'ru';
$is_auth   = !empty($_SESSION['user']);
$user_name = $is_auth ? ($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) : '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>ЭРА ЭТП — следующее поколение торгов</title>
<meta name="description" content="Открытые, скандинавские, закрытые аукционы, запрос предложений и котировок. Прозрачные торги под защитой 152-ФЗ.">

<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0f172a">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">

<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
html { scroll-behavior: auto; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #050913;
    color: #e2e8f0;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

/* --- Шапка ---------------------------------------------------------------- */
.nav-shell {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 5%;
    background: rgba(5, 9, 19, 0); transition: background .35s ease, backdrop-filter .35s ease;
}
.nav-shell.solid { background: rgba(5, 9, 19, .82); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
.nav-shell .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 900; }
.nav-shell .brand img { height: 36px; filter: drop-shadow(0 0 12px rgba(56,189,248,.55)); }
.nav-shell .links { display: flex; gap: 28px; align-items: center; }
.nav-shell .links a { color: #cbd5e1; text-decoration: none; font-weight: 700; font-size: 13px; letter-spacing: .04em; text-transform: uppercase; transition: color .2s; }
.nav-shell .links a:hover { color: #38bdf8; }
.btn-cta { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 10px; background: linear-gradient(135deg, #0088cc, #38bdf8); color: #fff; font-weight: 800; text-decoration: none; font-size: 13px; letter-spacing: .05em; text-transform: uppercase; box-shadow: 0 8px 24px rgba(0,136,204,.35); transition: transform .2s, box-shadow .2s; border: none; cursor: pointer; }
.btn-cta:hover { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(0,136,204,.5); }
.btn-cta.outline { background: transparent; border: 1.5px solid rgba(56,189,248,.5); box-shadow: none; color: #38bdf8; }
.btn-cta.outline:hover { border-color: #38bdf8; background: rgba(56,189,248,.08); }
@media (max-width: 768px) { .nav-shell .links { display: none; } .nav-shell { padding: 14px 5%; } }

/* --- 3D холст ------------------------------------------------------------- */
#hero-bg-canvas {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    width: 100vw; height: 100vh;
}
.starfield {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,.6), transparent 50%),
        radial-gradient(1.5px 1.5px at 70% 60%, rgba(255,255,255,.5), transparent 50%),
        radial-gradient(2px 2px at 40% 80%, rgba(255,255,255,.4), transparent 50%),
        radial-gradient(1.5px 1.5px at 90% 25%, rgba(255,255,255,.5), transparent 50%),
        radial-gradient(2px 2px at 15% 70%, rgba(255,255,255,.5), transparent 50%),
        radial-gradient(1.2px 1.2px at 60% 15%, rgba(255,255,255,.45), transparent 50%),
        radial-gradient(1.5px 1.5px at 35% 50%, rgba(255,255,255,.35), transparent 50%),
        radial-gradient(1px 1px at 80% 85%, rgba(255,255,255,.5), transparent 50%),
        radial-gradient(circle at center, #0c1426 0%, #050913 70%);
    background-size: 700px 700px, 700px 700px, 700px 700px, 700px 700px, 700px 700px, 900px 900px, 900px 900px, 900px 900px, 100% 100%;
    animation: starDrift 90s linear infinite;
}
@keyframes starDrift {
    0%   { background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, center; }
    100% { background-position: 700px 350px, -700px 350px, 350px 700px, -350px -700px, 700px -350px, 900px 450px, -900px 450px, 450px 900px, center; }
}

/* CSS-«ядро» — ВСЕГДА видно, даже без WebGL. На него Three.js накладывается сверху. */
.hero-orb-css {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: min(75vw, 720px); height: min(75vw, 720px);
    z-index: 0; pointer-events: none;
    display: flex; align-items: center; justify-content: center;
}
.hero-orb-css .core {
    position: absolute; width: 32%; height: 32%; border-radius: 50%;
    background: radial-gradient(circle at 35% 35%, #ffffff 0%, #38bdf8 28%, #0088cc 60%, transparent 80%);
    box-shadow: 0 0 90px 30px rgba(56,189,248,.55), 0 0 180px 60px rgba(0,136,204,.35);
    animation: corePulse 3.4s ease-in-out infinite;
}
.hero-orb-css .ring {
    position: absolute; border-radius: 50%; border: 1px solid rgba(56,189,248,.55);
    box-shadow: 0 0 30px rgba(56,189,248,.25) inset;
}
.hero-orb-css .ring.r1 { width: 55%; height: 55%; animation: ringSpin 22s linear infinite; border-style: dashed; }
.hero-orb-css .ring.r2 { width: 75%; height: 75%; animation: ringSpin 36s linear infinite reverse; border-color: rgba(255,255,255,.25); }
.hero-orb-css .ring.r3 { width: 95%; height: 95%; animation: ringSpin 60s linear infinite; border-color: rgba(0,136,204,.45); border-style: dotted; }
.hero-orb-css .ring.r4 { width: 115%; height: 115%; animation: ringSpin 90s linear infinite reverse; border-color: rgba(56,189,248,.18); }
.hero-orb-css .ring::before {
    content: ''; position: absolute; top: -4px; left: 50%; transform: translateX(-50%);
    width: 8px; height: 8px; border-radius: 50%; background: #38bdf8; box-shadow: 0 0 14px #38bdf8;
}
.hero-orb-css .ring.r2::before { background: #ffffff; box-shadow: 0 0 16px #ffffff; }
.hero-orb-css .ring.r3::before { background: #0088cc; box-shadow: 0 0 18px #0088cc; }
.hero-orb-css .ring.r4::before { background: #38bdf8; box-shadow: 0 0 12px #38bdf8; opacity: .7; }
@keyframes corePulse {
    0%, 100% { transform: scale(1); filter: brightness(1); }
    50%      { transform: scale(1.07); filter: brightness(1.18); }
}
@keyframes ringSpin {
    from { transform: rotate(0deg); } to { transform: rotate(360deg); }
}
/* Когда WebGL включён — приглушаем CSS-ядро (Three.js рисует поверх). */
body.webgl-on .hero-orb-css { opacity: 0.55; transition: opacity .8s ease; }

.fallback-logo { display: none; } /* больше не нужен — есть hero-orb-css */

/* --- Секции / акты -------------------------------------------------------- */
section {
    position: relative; z-index: 2;
    padding: 0 5%;
}
.act-1 { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding-top: 80px; }
.eyebrow { font-size: 12px; letter-spacing: .35em; text-transform: uppercase; color: #38bdf8; font-weight: 800; margin-bottom: 18px; opacity: 0; transform: translateY(20px); }
.title-mega {
    font-size: clamp(40px, 7vw, 96px); font-weight: 900; line-height: 1; letter-spacing: -.02em; color: #fff;
    background: linear-gradient(180deg, #ffffff 0%, #94a3b8 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    margin-bottom: 24px; max-width: 1100px;
}
.subtitle { font-size: clamp(15px, 1.5vw, 18px); color: #94a3b8; max-width: 640px; line-height: 1.6; margin-bottom: 38px; }
.hero-actions { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; margin-bottom: 60px; }
.scroll-hint { position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; gap: 8px; color: #475569; font-size: 11px; letter-spacing: .25em; text-transform: uppercase; }
.scroll-hint .mouse { width: 22px; height: 36px; border: 1.5px solid #475569; border-radius: 14px; position: relative; }
.scroll-hint .mouse::before { content: ''; position: absolute; left: 50%; top: 7px; width: 3px; height: 8px; border-radius: 2px; background: #38bdf8; transform: translateX(-50%); animation: scrollDot 1.6s ease-in-out infinite; }
@keyframes scrollDot { 0%{opacity:1; top:7px;} 70%{opacity:0; top:18px;} 100%{opacity:0;top:18px;} }

/* --- Акт 2: плитки -------------------------------------------------------- */
.act-2 { padding: 140px 5%; }
.section-head { text-align: center; margin-bottom: 70px; }
.section-eyebrow { font-size: 11px; letter-spacing: .4em; text-transform: uppercase; color: #38bdf8; font-weight: 900; margin-bottom: 14px; }
.section-title { font-size: clamp(30px, 4vw, 52px); font-weight: 900; color: #fff; letter-spacing: -.015em; }
.tiles-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; max-width: 1200px; margin: 0 auto; }
.tile {
    position: relative; padding: 38px 28px; border-radius: 22px;
    background: linear-gradient(180deg, rgba(15,23,42,.75) 0%, rgba(15,23,42,.4) 100%);
    border: 1px solid rgba(148,163,184,.12);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    overflow: hidden; cursor: pointer;
    transform-style: preserve-3d; transition: transform .25s cubic-bezier(.2,.7,.2,1), border-color .25s;
}
.tile::before { content: ''; position: absolute; top: -120%; left: -120%; width: 240%; height: 240%; background: radial-gradient(circle at 30% 30%, rgba(56,189,248,.18), transparent 50%); opacity: 0; transition: opacity .35s; }
.tile:hover { border-color: rgba(56,189,248,.45); transform: translateY(-6px); }
.tile:hover::before { opacity: 1; }
.tile .tile-icon { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #0088cc, #38bdf8); display: flex; align-items: center; justify-content: center; margin-bottom: 22px; font-size: 28px; box-shadow: 0 12px 30px rgba(0,136,204,.35); }
.tile h3 { font-size: 22px; font-weight: 900; color: #fff; margin-bottom: 10px; }
.tile p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin-bottom: 22px; }
.tile .tile-cta { display: inline-flex; align-items: center; gap: 6px; color: #38bdf8; font-weight: 800; font-size: 13px; text-decoration: none; }
@media (max-width: 900px) { .tiles-grid { grid-template-columns: 1fr; } }

/* --- Акт 3: типы аукционов (горизонтальный скролл) ------------------------ */
.act-3 { padding: 140px 0; }
.act-3 .section-head { padding: 0 5%; }
.h-scroll-track { display: flex; gap: 22px; padding: 0 5% 30px; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.h-scroll-track::-webkit-scrollbar { display: none; }
.auc-card {
    flex: 0 0 320px; scroll-snap-align: start;
    padding: 30px 26px; border-radius: 22px;
    background: linear-gradient(160deg, var(--c1, #0f172a), var(--c2, #1e293b));
    border: 1px solid rgba(148,163,184,.15);
    box-shadow: 0 18px 48px rgba(0,0,0,.45);
}
.auc-card .ac-icon { font-size: 38px; margin-bottom: 18px; }
.auc-card h4 { font-size: 19px; font-weight: 900; color: #fff; margin-bottom: 12px; }
.auc-card p { font-size: 13.5px; color: rgba(255,255,255,.78); line-height: 1.55; min-height: 64px; }
.auc-card .badge { display: inline-block; margin-top: 16px; padding: 5px 11px; border-radius: 999px; background: rgba(255,255,255,.16); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }

/* --- Акт 4: преимущества -------------------------------------------------- */
.act-4 { padding: 140px 5%; }
.adv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; max-width: 1200px; margin: 0 auto; }
.adv {
    padding: 38px 28px; border-radius: 22px; text-align: center;
    background: rgba(15,23,42,.5); border: 1px solid rgba(148,163,184,.12);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
}
.adv .adv-num { font-size: 56px; font-weight: 900; line-height: 1; background: linear-gradient(135deg, #0088cc, #38bdf8); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px; }
.adv h4 { font-size: 19px; font-weight: 900; color: #fff; margin-bottom: 8px; }
.adv p { font-size: 14px; color: #94a3b8; line-height: 1.55; }
@media (max-width: 900px) { .adv-grid { grid-template-columns: 1fr; } }

/* --- Акт 5: финал --------------------------------------------------------- */
.act-5 { padding: 160px 5%; text-align: center; }
.act-5 .title-mega { margin-left: auto; margin-right: auto; max-width: 900px; }
.act-5 .subtitle { margin: 0 auto 40px; }

/* --- Подвал --------------------------------------------------------------- */
footer.f-foot {
    position: relative; z-index: 5;
    background: #050913;
    border-top: 1px solid rgba(148,163,184,.1);
    padding: 50px 5% 32px; color: #64748b; font-size: 13px; line-height: 1.7;
    text-align: center;
}
footer.f-foot .foot-brand { display: flex; justify-content: center; align-items: center; gap: 12px; margin-bottom: 22px; }
footer.f-foot .foot-brand img { height: 38px; filter: drop-shadow(0 0 14px rgba(56,189,248,.4)); }
footer.f-foot .foot-brand span { font-weight: 900; color: #cbd5e1; letter-spacing: .15em; font-size: 13px; text-transform: uppercase; }
footer.f-foot a { color: #94a3b8; text-decoration: none; margin: 0 10px; }
footer.f-foot a:hover { color: #38bdf8; }
.f-foot .req { margin-bottom: 10px; }
.f-foot .legal { margin-top: 16px; font-size: 11px; color: #475569; letter-spacing: .04em; }

@media (prefers-reduced-motion: reduce) {
    *,*::before,*::after { animation: none !important; transition: none !important; }
}
</style>
</head>
<body>

<canvas id="hero-bg-canvas"></canvas>
<div class="starfield" aria-hidden="true"></div>
<div class="hero-orb-css" aria-hidden="true">
    <div class="ring r4"></div>
    <div class="ring r3"></div>
    <div class="ring r2"></div>
    <div class="ring r1"></div>
    <div class="core"></div>
</div>

<header class="nav-shell" id="navShell">
    <a href="/index-new.php" class="brand">
        <img src="/logo-forsage-white.png" alt="ЭРА ЭТП">
    </a>
    <nav class="links">
        <a href="/reestr.php">Реестр торгов</a>
        <a href="/torgi_list.php">Комиссионная продажа</a>
        <a href="/tariffs.php">Тарифы</a>
        <a href="/regulations.php">Регламент</a>
    </nav>
    <?php if ($is_auth): ?>
        <a class="btn-cta" href="/profile.php">Кабинет</a>
    <?php else: ?>
        <button class="btn-cta" onclick="openAuth && openAuth('login')">Войти</button>
    <?php endif; ?>
</header>

<section class="act-1" id="act1">
    <div class="eyebrow" data-anim="eyebrow">Электронная торговая площадка нового поколения</div>
    <h1 class="title-mega" data-anim="title">Торгуйте<br>прозрачно и быстро</h1>
    <p class="subtitle" data-anim="subtitle">
        Открытые, закрытые, скандинавские аукционы, запрос предложений и котировок —
        в одном кабинете, под защитой 152-ФЗ. Регистрация за пять минут, первая ставка — за минуту.
    </p>
    <div class="hero-actions" data-anim="cta">
        <button class="btn-cta" onclick="openAuth && openAuth('register')">Зарегистрироваться</button>
        <a class="btn-cta outline" href="/reestr.php">Смотреть торги →</a>
    </div>
    <div class="scroll-hint"><div class="mouse"></div>Скролл</div>
</section>

<section class="act-2" id="act2">
    <div class="section-head">
        <div class="section-eyebrow">Кто на площадке</div>
        <h2 class="section-title">Три роли — один кабинет</h2>
    </div>
    <div class="tiles-grid">
        <a class="tile" href="/reestr.php" data-tile>
            <div class="tile-icon">🎯</div>
            <h3>Участник</h3>
            <p>Ищите подходящие лоты, подавайте заявки, делайте ставки. Доступны все шесть типов торгов и реестр в реальном времени.</p>
            <span class="tile-cta">К реестру →</span>
        </a>
        <a class="tile" href="#" onclick="openAuth && openAuth('register'); return false;" data-tile>
            <div class="tile-icon">⚡</div>
            <h3>Регистрация</h3>
            <p>Уважаемый — бесплатно. Ответственный — 8 000 ₽. Организатор — бесплатно на 12 месяцев. Аккредитация в системе за 24 часа.</p>
            <span class="tile-cta">Создать аккаунт →</span>
        </a>
        <a class="tile" href="/add_lot.php" data-tile>
            <div class="tile-icon">📊</div>
            <h3>Организатор</h3>
            <p>Размещайте лоты, выбирайте формат торгов, управляйте допуском участников и итогами. Полный аудит-трейл по каждой сделке.</p>
            <span class="tile-cta">Создать лот →</span>
        </a>
    </div>
</section>

<section class="act-3" id="act3">
    <div class="section-head">
        <div class="section-eyebrow">Шесть форматов</div>
        <h2 class="section-title">Любая логика торгов</h2>
    </div>
    <div class="h-scroll-track">
        <div class="auc-card" style="--c1:#0f172a;--c2:#1e3a8a;">
            <div class="ac-icon">🔨</div>
            <h4>Открытый аукцион</h4>
            <p>Классика на повышение. Все ставки видны участникам, побеждает наибольшая цена.</p>
            <div class="badge">Прозрачно</div>
        </div>
        <div class="auc-card" style="--c1:#1e1b4b;--c2:#7c2d12;">
            <div class="ac-icon">🔥</div>
            <h4>Скандинавский</h4>
            <p>Каждая ставка стоит фиксированный тариф и продлевает таймер. Драйв и доход для организатора.</p>
            <div class="badge">Драйв</div>
        </div>
        <div class="auc-card" style="--c1:#052e16;--c2:#14532d;">
            <div class="ac-icon">📉</div>
            <h4>На понижение</h4>
            <p>Цена снижается с шагом. Кто первый нажмёт «купить» — тот и забирает лот.</p>
            <div class="badge">Скорость</div>
        </div>
        <div class="auc-card" style="--c1:#1e293b;--c2:#475569;">
            <div class="ac-icon">🔒</div>
            <h4>Закрытый</h4>
            <p>Только допущенные участники видят торги и ставят. Имена скрыты, выигрыш — только цена.</p>
            <div class="badge">Конфиденциально</div>
        </div>
        <div class="auc-card" style="--c1:#0c4a6e;--c2:#0e7490;">
            <div class="ac-icon">📨</div>
            <h4>Запрос предложений</h4>
            <p>Участники подают единое предложение, видят его в любой момент и могут поднять цену.</p>
            <div class="badge">Гибко</div>
        </div>
        <div class="auc-card" style="--c1:#581c87;--c2:#a21caf;">
            <div class="ac-icon">📑</div>
            <h4>Запрос котировок</h4>
            <p>Зеркально к запросу предложений: побеждает минимальная цена. Удобно для закупок.</p>
            <div class="badge">Закупки</div>
        </div>
    </div>
</section>

<section class="act-4" id="act4">
    <div class="section-head">
        <div class="section-eyebrow">Почему ЭРА</div>
        <h2 class="section-title">Тонкости, которые меняют всё</h2>
    </div>
    <div class="adv-grid">
        <div class="adv">
            <div class="adv-num">152-ФЗ</div>
            <h4>Защита персональных данных</h4>
            <p>Журнал согласий с IP, временем и User-Agent. Политики и реквизиты оператора публичны.</p>
        </div>
        <div class="adv">
            <div class="adv-num">&lt;1c</div>
            <h4>Реальное время</h4>
            <p>Ставки и предложения видны мгновенно. Никаких F5 — лента обновляется сама.</p>
        </div>
        <div class="adv">
            <div class="adv-num">QR</div>
            <h4>СБП и квитанции</h4>
            <p>Оплата статусов и отчётов по QR-коду или печатной квитанции с реквизитами оператора.</p>
        </div>
    </div>
</section>

<section class="act-5">
    <div class="section-eyebrow" style="color:#38bdf8;margin-bottom:18px;">Готовы начать?</div>
    <h2 class="title-mega">Первый лот — через пять минут.</h2>
    <p class="subtitle">Регистрация бесплатна. Ответственный статус активируется после оплаты. Организатор — на 12 месяцев в подарок.</p>
    <div class="hero-actions">
        <button class="btn-cta" onclick="openAuth && openAuth('register')">Создать аккаунт</button>
        <a class="btn-cta outline" href="/reestr.php">Посмотреть торги →</a>
    </div>
</section>

<footer class="f-foot">
    <div class="foot-brand">
        <img src="/logo-forsage-white.png" alt="ЭРА ЭТП">
        <span>Электронная торговая площадка</span>
    </div>
    <div class="req">
        ООО «Форсаж» · ИНН 7728282160 · ОГРН 1037728010396 · 121059, г. Москва, ул. Киевская, д. 14, оф. 2а
    </div>
    <div>
        <a href="/user_agreement.php">Пользовательское соглашение</a>
        <a href="/personal_data.php">Обработка ПДн</a>
        <a href="/cookie_policy.php">Cookie</a>
        <a href="/regulations.php">Регламент</a>
    </div>
    <div class="legal">© 2024–2026 ООО «Форсаж» · ERA ETP · ФЗ-152 · Все права защищены</div>
</footer>

<?php include 'auth_modal.php'; ?>

<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
    "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
  }
}
</script>
<script src="https://unpkg.com/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://unpkg.com/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script src="https://unpkg.com/lenis@1.1.13/dist/lenis.min.js"></script>

<script>
/* Шапка темнеет после прокрутки на ~80px. */
const navShell = document.getElementById('navShell');
window.addEventListener('scroll', () => {
    navShell.classList.toggle('solid', window.scrollY > 80);
}, { passive: true });

/* Решаем, нужно ли вообще пытаться рисовать 3D. */
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
const tooSmall     = window.innerWidth <= 480;
const lowDevice    = (navigator.deviceMemory && navigator.deviceMemory < 2) ||
                     (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 2);
const skip3D       = reduceMotion || tooSmall || lowDevice;

/* CSS-ядро (.hero-orb-css) видно ВСЕГДА. Three.js накладывается поверх как
   усиление, но и без него страница уже визуально 3D. */
if (!skip3D) {
    import('three').then(THREE => {
        try {
            initThreeScene(THREE);
            document.body.classList.add('webgl-on');
        } catch (err) {
            console.warn('Three.js init failed, CSS orb остаётся.', err);
        }
    }).catch(err => {
        console.warn('Three.js failed to load, CSS orb остаётся.', err);
    });
} else {
    /* На очень слабых устройствах ничего больше не делаем — CSS-ядро уже на месте. */
    startScrollTimeline();
}

function initThreeScene(THREE) {
    const canvas = document.getElementById('hero-bg-canvas');
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(window.innerWidth, window.innerHeight, false);
    renderer.setClearColor(0x000000, 0);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 0.1, 200);
    camera.position.set(0, 0, 6);

    /* --- Звёзды (фон) --- */
    const starGeo = new THREE.BufferGeometry();
    const STAR_COUNT = 1800;
    const starPos = new Float32Array(STAR_COUNT * 3);
    for (let i = 0; i < STAR_COUNT; i++) {
        starPos[i*3+0] = (Math.random() - 0.5) * 80;
        starPos[i*3+1] = (Math.random() - 0.5) * 80;
        starPos[i*3+2] = (Math.random() - 0.5) * 80;
    }
    starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
    const stars = new THREE.Points(starGeo, new THREE.PointsMaterial({
        color: 0xffffff, size: 0.04, sizeAttenuation: true, transparent: true, opacity: 0.9
    }));
    scene.add(stars);

    /* --- Главный 3D-объект: светящаяся сфера-«ядро» ЭРА --- */
    const core = new THREE.Group();
    scene.add(core);

    // Внутренняя яркая сфера
    const innerSphere = new THREE.Mesh(
        new THREE.SphereGeometry(0.55, 64, 64),
        new THREE.MeshBasicMaterial({ color: 0x38bdf8 })
    );
    core.add(innerSphere);

    // Каркасная сфера — wireframe со знакомой синей айдентикой
    const wire = new THREE.Mesh(
        new THREE.SphereGeometry(0.85, 32, 24),
        new THREE.MeshBasicMaterial({ color: 0x38bdf8, wireframe: true, transparent: true, opacity: 0.55 })
    );
    core.add(wire);

    // Светящееся гало (3 слоя для мягкого bloom-эффекта без постпроцессинга)
    [{r:1.05,o:0.18},{r:1.4,o:0.10},{r:1.85,o:0.05}].forEach(({r,o}) => {
        const halo = new THREE.Mesh(
            new THREE.SphereGeometry(r, 32, 32),
            new THREE.MeshBasicMaterial({ color: 0x0088cc, transparent: true, opacity: o,
                blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.BackSide })
        );
        core.add(halo);
    });

    // Орбитальные кольца — намёк на «торги» / связи
    const ring1 = new THREE.Mesh(
        new THREE.TorusGeometry(1.5, 0.012, 16, 96),
        new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.65 })
    );
    ring1.rotation.x = Math.PI/2.2;
    core.add(ring1);

    const ring2 = new THREE.Mesh(
        new THREE.TorusGeometry(1.85, 0.008, 16, 96),
        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.35 })
    );
    ring2.rotation.x = Math.PI/3;
    ring2.rotation.y = Math.PI/4;
    core.add(ring2);

    const ring3 = new THREE.Mesh(
        new THREE.TorusGeometry(2.2, 0.006, 16, 96),
        new THREE.MeshBasicMaterial({ color: 0x0088cc, transparent: true, opacity: 0.45 })
    );
    ring3.rotation.x = Math.PI/4;
    ring3.rotation.z = Math.PI/3;
    core.add(ring3);

    /* --- Хвост из частиц вокруг ядра --- */
    const trailCount = 220;
    const trailGeo = new THREE.BufferGeometry();
    const trailPos = new Float32Array(trailCount * 3);
    const trailRadius = new Float32Array(trailCount);
    const trailAngle = new Float32Array(trailCount);
    const trailSpeed = new Float32Array(trailCount);
    for (let i = 0; i < trailCount; i++) {
        trailRadius[i] = 1.1 + Math.random() * 2.2;
        trailAngle[i]  = Math.random() * Math.PI * 2;
        trailSpeed[i]  = 0.002 + Math.random() * 0.01;
        const a = trailAngle[i], r = trailRadius[i];
        trailPos[i*3+0] = Math.cos(a) * r;
        trailPos[i*3+1] = (Math.random() - 0.5) * 0.6;
        trailPos[i*3+2] = Math.sin(a) * r;
    }
    trailGeo.setAttribute('position', new THREE.BufferAttribute(trailPos, 3));
    const trail = new THREE.Points(trailGeo, new THREE.PointsMaterial({
        color: 0x38bdf8, size: 0.06, sizeAttenuation: true, transparent: true, opacity: 0.85,
        blending: THREE.AdditiveBlending, depthWrite: false
    }));
    core.add(trail);

    /* --- Реакция на курсор --- */
    const mouse = { x: 0, y: 0 };
    window.addEventListener('mousemove', (e) => {
        mouse.x = (e.clientX / window.innerWidth  - 0.5) * 0.5;
        mouse.y = (e.clientY / window.innerHeight - 0.5) * 0.5;
    });

    startScrollTimeline();

    let scrollProgress = 0; // 0..1 от всей длины страницы
    const docHeight = () => document.documentElement.scrollHeight - window.innerHeight;
    const clock = new THREE.Clock();

    function tick() {
        const dt = clock.getDelta();
        const tt = clock.getElapsedTime();
        scrollProgress = Math.min(1, Math.max(0, window.scrollY / Math.max(1, docHeight())));

        /* Звёзды плавно дрейфуют. */
        stars.rotation.y += 0.0006;
        stars.rotation.x += 0.0002;

        /* Кольца вращаются с разной скоростью. */
        ring1.rotation.z += 0.004;
        ring2.rotation.z -= 0.0028;
        ring3.rotation.x += 0.0022;

        /* Внутренняя сфера пульсирует. */
        const pulse = 1 + Math.sin(tt * 1.2) * 0.04;
        innerSphere.scale.setScalar(pulse);
        wire.rotation.y += 0.003;
        wire.rotation.x += 0.0015;

        /* Орбитальные частицы. */
        const arr = trailGeo.attributes.position.array;
        for (let i = 0; i < trailCount; i++) {
            trailAngle[i] += trailSpeed[i];
            const a = trailAngle[i], r = trailRadius[i];
            arr[i*3+0] = Math.cos(a) * r;
            arr[i*3+2] = Math.sin(a) * r;
        }
        trailGeo.attributes.position.needsUpdate = true;

        /* Скролл-анимация: ядро уходит вверх и в глубину, потом возвращается. */
        const t = scrollProgress;
        const heroFactor = Math.max(0, 1 - t * 1.6);
        const finalFactor = Math.max(0, t * 1.6 - 0.6);
        core.position.x = mouse.x * 1.2 * heroFactor;
        core.position.y = -mouse.y * 0.8 * heroFactor + Math.sin(t * Math.PI) * -1.2 + finalFactor * 0.4;
        core.position.z = -t * 4 + finalFactor * 4.5;
        core.scale.setScalar(0.9 + heroFactor * 0.4 + finalFactor * 0.7);
        core.rotation.y = t * Math.PI * 0.6;

        camera.position.x = mouse.x * 0.4;
        camera.position.y = -mouse.y * 0.3;
        camera.lookAt(0, 0, 0);

        renderer.render(scene, camera);
        requestAnimationFrame(tick);
    }
    tick();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight, false);
    });
}

/* GSAP-таймлайн на появление элементов hero. */
function startScrollTimeline() {
    if (typeof gsap === 'undefined') return;
    gsap.registerPlugin(ScrollTrigger);

    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .to('[data-anim="eyebrow"]',  { opacity: 1, y: 0, duration: 0.7 }, 0.2)
        .to('[data-anim="title"]',    { opacity: 1, y: 0, duration: 0.9 }, 0.35)
        .to('[data-anim="subtitle"]', { opacity: 1, y: 0, duration: 0.8 }, 0.55)
        .to('[data-anim="cta"]',      { opacity: 1, y: 0, duration: 0.7 }, 0.7);

    /* Появление плиток / преимуществ при скролле. */
    gsap.utils.toArray('.tile, .adv, .auc-card').forEach((el, i) => {
        gsap.from(el, {
            opacity: 0, y: 40, duration: 0.7, ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 80%', once: true }
        });
    });
}

/* Если 3D отключён — анимации hero всё равно нужно показать. */
if (skip3D) {
    document.querySelectorAll('[data-anim]').forEach(el => { el.style.opacity = 1; el.style.transform = 'none'; });
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
        gsap.utils.toArray('.tile, .adv, .auc-card').forEach((el) => {
            gsap.from(el, {
                opacity: 0, y: 30, duration: 0.6, ease: 'power3.out',
                scrollTrigger: { trigger: el, start: 'top 85%', once: true }
            });
        });
    }
}

/* Плавный скролл (Lenis) — только на десктопе с включённой анимацией. */
if (!skip3D && typeof Lenis !== 'undefined') {
    const lenis = new Lenis({ duration: 1.1, easing: t => 1 - Math.pow(1 - t, 3), smoothWheel: true });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
}
</script>

<style>
[data-anim] { opacity: 0; transform: translateY(24px); }
</style>

</body>
</html>

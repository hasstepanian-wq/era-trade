<?php
/**
 * pwa_install.php — единый PWA install-promo для всех страниц.
 * Подключается из header.php.
 *
 * Поведение:
 *  - На Android/Chrome ловит beforeinstallprompt → показывает нативный prompt при клике «Установить».
 *  - На iOS Safari (нет beforeinstallprompt) → открывает графическую инструкцию + share-sheet (если поддерживается).
 *  - Не показывается, если приложение уже установлено (display-mode: standalone | navigator.standalone).
 *  - Скрывается при клике × и не показывается повторно ~24 часа.
 */
$_lang_pwa = $_SESSION['lang'] ?? 'ru';
?>

<style>
#pwa-promo {
    position: fixed; left: 16px; right: 16px; bottom: 16px;
    z-index: 9000; display: none;
    background: rgba(15,23,42,0.96); color: #e2e8f0;
    border: 1px solid rgba(56,189,248,0.35); border-radius: 14px;
    padding: 12px 14px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.45);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    font-size: 13px; line-height: 1.45;
    max-width: 540px; margin: 0 auto;
}
#pwa-promo.show { display: flex; align-items: center; gap: 12px; }
#pwa-promo .pwa-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, #0088cc, #38bdf8);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,136,204,.4);
}
#pwa-promo .pwa-body { flex: 1; min-width: 0; }
#pwa-promo .pwa-title { font-weight: 800; font-size: 13px; margin-bottom: 2px; }
#pwa-promo .pwa-sub { font-size: 11px; color: #94a3b8; }
#pwa-promo .pwa-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
#pwa-promo .pwa-btn-primary {
    background: linear-gradient(135deg, #0088cc, #38bdf8);
    color: #fff; border: 0; border-radius: 8px;
    padding: 8px 14px; font-weight: 700; font-size: 12px;
    cursor: pointer; white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,136,204,.4);
}
#pwa-promo .pwa-btn-close {
    background: rgba(148, 163, 184, 0.15);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #e2e8f0;
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 0;
}
#pwa-promo .pwa-btn-close:hover,
#pwa-promo .pwa-btn-close:active {
    background: rgba(239, 68, 68, 0.20);
    color: #fff;
    border-color: rgba(239, 68, 68, 0.35);
}

/* Модалка инструкции (iOS / fallback) */
#pwa-help-modal {
    position: fixed; inset: 0; z-index: 9500;
    background: rgba(0,0,0,0.55); display: none;
    align-items: center; justify-content: center; padding: 16px;
    backdrop-filter: blur(2px);
}
#pwa-help-modal.show { display: flex; }
#pwa-help-modal .pwa-help-card {
    background: #fff; color: #0f172a; border-radius: 16px;
    padding: 22px 22px 18px; max-width: 380px; width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    max-height: 90vh; overflow-y: auto;
}
#pwa-help-modal h3 { margin: 0 0 4px; font-size: 17px; font-weight: 800; }
#pwa-help-modal .pwa-help-sub { color: #64748b; font-size: 13px; margin-bottom: 14px; }
#pwa-help-modal .pwa-step {
    display: flex; gap: 10px; align-items: flex-start;
    margin-bottom: 12px; font-size: 13px; line-height: 1.5;
}
#pwa-help-modal .pwa-step-num {
    flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
    background: linear-gradient(135deg, #0088cc, #38bdf8);
    color: #fff; font-weight: 800; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
}
#pwa-help-modal .pwa-step-icon { font-size: 18px; vertical-align: -2px; }
#pwa-help-modal .pwa-help-share {
    width: 100%; margin-top: 8px;
    background: linear-gradient(135deg, #0088cc, #38bdf8);
    color: #fff; border: 0; border-radius: 10px;
    padding: 10px 14px; font-weight: 700; font-size: 13px; cursor: pointer;
}
#pwa-help-modal .pwa-help-close {
    width: 100%; margin-top: 6px;
    background: #f1f5f9; color: #475569; border: 0; border-radius: 10px;
    padding: 9px 14px; font-weight: 600; font-size: 12px; cursor: pointer;
}

@media (min-width: 768px) {
    /* На десктопе компактный баннер не показываем — там достаточно ссылки в футере. */
    #pwa-promo.show.desktop-hide { display: none; }
}
</style>

<div id="pwa-promo" class="desktop-hide" role="dialog" aria-live="polite">
    <div class="pwa-icon">📲</div>
    <div class="pwa-body">
        <div class="pwa-title"><?= $_lang_pwa === 'en' ? 'Install ERA ETP' : 'Установить ERA ETP' ?></div>
        <div class="pwa-sub"><?= $_lang_pwa === 'en' ? 'Open instantly from your home screen' : 'Откроется с главного экрана как обычное приложение' ?></div>
    </div>
    <div class="pwa-actions">
        <button type="button" class="pwa-btn-primary" id="pwa-promo-install"><?= $_lang_pwa === 'en' ? 'Install' : 'Установить' ?></button>
        <button type="button" class="pwa-btn-close" id="pwa-promo-close" aria-label="<?= $_lang_pwa === 'en' ? 'Close' : 'Закрыть' ?>">×</button>
    </div>
</div>

<div id="pwa-help-modal" role="dialog" aria-modal="true">
    <div class="pwa-help-card">
        <h3 id="pwa-help-title"><?= $_lang_pwa === 'en' ? 'Add to Home Screen' : 'Добавить на главный экран' ?></h3>
        <div class="pwa-help-sub" id="pwa-help-sub"><?= $_lang_pwa === 'en' ? 'Two quick steps:' : 'Два быстрых шага:' ?></div>
        <div id="pwa-help-steps">
            <div class="pwa-step">
                <div class="pwa-step-num">1</div>
                <div><?= $_lang_pwa === 'en' ? 'Tap <span class="pwa-step-icon">⬆️</span> <b>Share</b> at the bottom of Safari' : 'Нажмите <span class="pwa-step-icon">⬆️</span> <b>Поделиться</b> внизу Safari' ?></div>
            </div>
            <div class="pwa-step">
                <div class="pwa-step-num">2</div>
                <div><?= $_lang_pwa === 'en' ? 'Choose <b>Add to Home Screen</b> <span class="pwa-step-icon">➕</span>' : 'Выберите <b>На экран «Домой»</b> <span class="pwa-step-icon">➕</span>' ?></div>
            </div>
            <div class="pwa-step">
                <div class="pwa-step-num">3</div>
                <div><?= $_lang_pwa === 'en' ? 'Tap <b>Add</b> — the icon will appear on your home screen' : 'Нажмите <b>Добавить</b> — иконка появится на главном экране' ?></div>
            </div>
        </div>
        <button type="button" class="pwa-help-share" id="pwa-help-share-btn">
            <?= $_lang_pwa === 'en' ? 'Open Share menu' : 'Открыть меню «Поделиться»' ?>
        </button>
        <button type="button" class="pwa-help-close" id="pwa-help-close-btn">
            <?= $_lang_pwa === 'en' ? 'Close' : 'Закрыть' ?>
        </button>
    </div>
</div>

<script>
(function () {
    'use strict';

    var promo       = document.getElementById('pwa-promo');
    var btnInstall  = document.getElementById('pwa-promo-install');
    var btnClose    = document.getElementById('pwa-promo-close');
    var helpModal   = document.getElementById('pwa-help-modal');
    var btnHelpShare = document.getElementById('pwa-help-share-btn');
    var btnHelpClose = document.getElementById('pwa-help-close-btn');

    var deferredPrompt = null;
    var DISMISS_KEY = 'pwa_promo_dismissed_ts';
    var INSTALLED_KEY = 'pwa_installed';
    var DAY_MS = 24 * 60 * 60 * 1000;
    var DISMISS_DAYS = 3; // не показывать баннер 3 дня после закрытия

    function isStandalone() {
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return true;
        if ('standalone' in window.navigator && window.navigator.standalone) return true;
        return localStorage.getItem(INSTALLED_KEY) === 'true';
    }
    function isMobile() {
        return /Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile/i.test(navigator.userAgent);
    }
    function isIOS() {
        return /iPhone|iPad|iPod/i.test(navigator.userAgent) ||
               (/Macintosh/i.test(navigator.userAgent) && 'ontouchend' in document);
    }
    function shouldShow() {
        if (isStandalone()) return false;
        if (!isMobile()) return false;
        var t = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        if (t && (Date.now() - t) < DAY_MS * DISMISS_DAYS) return false;
        return true;
    }
    function show() { if (promo) promo.classList.add('show'); }
    function hide() {
        if (promo) promo.classList.remove('show');
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    }
    function showHelp() { if (helpModal) helpModal.classList.add('show'); }
    function hideHelp() { if (helpModal) helpModal.classList.remove('show'); }

    /* Android / Chrome: native install prompt */
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (shouldShow()) show();
    });
    window.addEventListener('appinstalled', function () {
        try { localStorage.setItem(INSTALLED_KEY, 'true'); } catch (e) {}
        hide();
    });

    /* iOS — beforeinstallprompt не сработает, но мы всё равно показываем баннер
       (с кнопкой «Установить», которая откроет инструкцию + share-sheet). */
    if (shouldShow()) {
        /* Небольшая задержка, чтобы не мешать первому впечатлению. */
        setTimeout(show, 1200);
    }

    /* Органичная кнопка «Установить как приложение» на главной (act-5).
       Показывается, когда установка в принципе возможна (мобильное устройство
       или Chrome с beforeinstallprompt) и пока приложение ещё не установлено.
       Не зависит от DISMISS_KEY — она остаётся доступной даже после закрытия
       плавающего баннера. */
    function showOrganicBtn() {
        var btn = document.getElementById('pwa-organic-install');
        if (!btn) return;
        if (isStandalone()) { btn.style.display = 'none'; return; }
        if (isMobile() || deferredPrompt || isIOS()) {
            btn.style.display = '';
        }
    }
    showOrganicBtn();
    window.addEventListener('beforeinstallprompt', showOrganicBtn);
    window.addEventListener('appinstalled', function () {
        var btn = document.getElementById('pwa-organic-install');
        if (btn) btn.style.display = 'none';
    });

    function tryNativeInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (res) {
                if (res && res.outcome === 'accepted') {
                    try { localStorage.setItem(INSTALLED_KEY, 'true'); } catch (e) {}
                    hide();
                }
                deferredPrompt = null;
            });
            return true;
        }
        return false;
    }

    function tryShareSheet() {
        /* Пытаемся открыть нативный share-sheet (как кнопка «Поделиться» в Safari).
           На iOS откроет шторку, в которой есть «На экран Домой». */
        if (navigator.share) {
            navigator.share({
                title: 'ERA ETP',
                text: '<?= $_lang_pwa === 'en' ? 'Add ERA ETP to your home screen' : 'Добавьте ERA ETP на главный экран' ?>',
                url: location.origin + '/'
            }).catch(function () {});
            return true;
        }
        return false;
    }

    if (btnInstall) btnInstall.addEventListener('click', function () {
        if (tryNativeInstall()) return;
        /* Показываем графическую инструкцию + кнопку для share-sheet. */
        showHelp();
    });
    if (btnClose)   btnClose.addEventListener('click', hide);
    if (btnHelpShare) btnHelpShare.addEventListener('click', function () {
        if (!tryShareSheet()) {
            /* Нет navigator.share — оставляем инструкцию открытой. */
        }
    });
    if (btnHelpClose) btnHelpClose.addEventListener('click', hideHelp);

    /* Глобальный API: window.pwaInstallShow() / window.pwaInstallHelp()
       можно дёрнуть из других кнопок на странице («Скачать приложение»). */
    window.pwaInstallShow = function () {
        if (tryNativeInstall()) return;
        showHelp();
    };
    window.pwaInstallHelp = showHelp;
}());
</script>

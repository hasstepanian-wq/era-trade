const dict = {
    ru: { n_reg: "РЕЕСТР ТОРГОВ", n_comm: "КОМИССИОННАЯ ПРОДАЖА", b_login: "ВХОД", c1_t: "Участник", c1_p: "Поиск актуальных процедур и подача заявок онлайн.", c2_t: "Экспресс-регистрация", c2_p: "Получите аккредитацию в системе всего за 24 часа.", c3_t: "Организатор", c3_p: "Размещение лотов и управление торгами.", f_about_p: "ЭТП ЭРА — цифровая экосистема для проведения торгов.", s_home: "Рабочий стол", s_reg: "Реестр торгов", s_fin: "Финансы", welcome: "Добро пожаловать, Артур!" },
    en: { n_reg: "REGISTRY", n_comm: "COMMISSION SALES", b_login: "LOGIN", c1_t: "Bidder", c1_p: "Search lots and submit bids online instantly.", c2_t: "Express Registration", c2_p: "Get system accreditation in just 24 hours.", c3_t: "Organizer", c3_p: "Post lots and manage your auctions.", f_about_p: "ERA ETP is a digital ecosystem for trading.", s_home: "Dashboard", s_reg: "Registry", s_fin: "Finance", welcome: "Welcome, Arthur!" }
};

function applyTranslations(lang) {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[lang][key]) el.innerText = dict[lang][key];
    });
    document.getElementById('btn-ru').classList.toggle('active', lang === 'ru');
    document.getElementById('btn-en').classList.toggle('active', lang === 'en');
}

function setUserLang(lang) {
    localStorage.setItem('era_lang', lang);
    applyTranslations(lang);
}

function fastNavigate(id) {
    document.getElementById('landing-page').style.display = 'none';
    document.getElementById('dashboard').style.display = 'grid';
    switchSection(id);
}

function switchSection(id) {
    document.querySelectorAll('.tab').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.s-link').forEach(l => l.classList.remove('active'));
    document.getElementById('s-' + id)?.classList.add('active');
    document.getElementById('l-' + id)?.classList.add('active');
}

function updateClock() {
    const clock = document.getElementById('clock');
    if (clock) {
        clock.innerText = new Date().toLocaleTimeString('ru-RU', { timeZone: 'Europe/Moscow' }) + ' МСК';
    }
}

window.onload = () => {
    const saved = localStorage.getItem('era_lang') || 'ru';
    applyTranslations(saved);
    setInterval(updateClock, 1000);
    updateClock();
};

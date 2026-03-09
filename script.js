const dict = {
    ru: {
        n_reg: "Реестр торгов", n_comm: "Комиссионная продажа", b_login: "Вход",
        c1_t: "Участник", c1_p: "Поиск актуальных процедур и подача заявок онлайн.",
        c2_t: "Экспресс-регистрация", c2_p: "Получите аккредитацию в системе всего за 24 часа.",
        c3_t: "Организатор", c3_p: "Размещение лотов и управление торгами.",
        f_about: "О платформе", f_about_p: "ЭТП ЭРА — цифровая экосистема для проведения торгов.",
        f_docs: "Документы", f_supp: "Поддержка",
        s_home: "Рабочий стол", s_reg: "Реестр торгов", s_fin: "Финансы", welcome: "Добро пожаловать, Артур!"
    },
    en: {
        n_reg: "Auction Registry", n_comm: "Commission Sales", b_login: "Login",
        c1_t: "Bidder", c1_p: "Search lots and submit bids online instantly.",
        c2_t: "Express Registration", c2_p: "Get system accreditation in just 24 hours.",
        c3_t: "Organizer", c3_p: "Post lots and manage your auctions.",
        f_about: "Platform", f_about_p: "ERA ETP is a digital ecosystem for electronic trading.",
        f_docs: "Documents", f_supp: "Support",
        s_home: "Dashboard", s_reg: "Registry", s_fin: "Finance", welcome: "Welcome, Arthur!"
    }
};

function applyTranslations(lang) {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dict[lang][key]) {
            el.innerText = dict[lang][key];
        }
    });
    document.getElementById('btn-ru').classList.toggle('active', lang === 'ru');
    document.getElementById('btn-en').classList.toggle('active', lang === 'en');
    document.documentElement.lang = lang;
}

function setUserLang(lang) {
    localStorage.setItem('user_lang_pref', lang);
    applyTranslations(lang);
}

function initLang() {
    const saved = localStorage.getItem('user_lang_pref');
    if (saved) {
        applyTranslations(saved);
    } else {
        const systemLang = (navigator.language || navigator.userLanguage).toLowerCase();
        const defaultLang = systemLang.includes('ru') ? 'ru' : 'en';
        applyTranslations(defaultLang);
    }
}

function toggleMobileMenu() {
    document.getElementById('mob-dropdown').classList.toggle('show');
}

function updateTime() {
    const clockEl = document.getElementById('clock');
    if (clockEl) {
        const str = new Date().toLocaleTimeString('ru-RU', { timeZone: 'Europe/Moscow', hour12: false });
        clockEl.innerText = str + ' МСК';
    }
}

function fastNavigate(id) {
    document.getElementById('landing-page').style.display = 'none';
    document.getElementById('dashboard').style.display = 'grid';
    switchSection(id);
    window.scrollTo(0,0);
}

function switchSection(id) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.side-link').forEach(l => l.classList.remove('active'));
    
    const target = document.getElementById('s-' + id);
    if(target) target.classList.add('active');
    
    const link = document.getElementById('l-' + id);
    if(link) link.classList.add('active');
}

window.onload = () => {
    initLang();
    updateTime();
    setInterval(updateTime, 1000);
};

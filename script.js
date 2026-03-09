// Данные пользователя
const userState = {
    balance: 750000,
    id: "ERA-77412",
    name: "Владелец кабинета"
};

// 1. ЖИВЫЕ ЧАСЫ
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if(clock) clock.innerText = new Date().toLocaleTimeString('ru-RU');
}, 1000);

// 2. НАВИГАЦИЯ МЕЖДУ СТРАНИЦАМИ
function nav(pageId, element) {
    // Скрываем все страницы
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    // Убираем активный класс у кнопок меню
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    // Активируем нужную
    document.getElementById('p-' + pageId).classList.add('active');
    element.classList.add('active');
}

// 3. ВХОД В ПРИЛОЖЕНИЕ
function openApp() {
    triggerModal('Авторизация', 'Вход в защищенную зону ID: ' + userState.id);
    setTimeout(() => {
        closeModal();
        document.getElementById('landing-view').style.display = 'none';
        document.getElementById('app-view').style.display = 'grid';
    }, 1000);
}

// 4. МОДАЛЬНОЕ ОКНО
function triggerModal(title, body) {
    document.getElementById('m-title').innerText = title;
    document.getElementById('m-body').innerText = body;
    document.getElementById('modal-overlay').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal-overlay').style.display = 'none';
}

// 5. ОПЛАТА И НДС (22% ИЗНУТРИ)
function doPayment() {
    const amt = parseFloat(document.getElementById('f-amount').value);
    const method = document.getElementById('f-method').value;

    if (method === 'bank') {
        generateInvoice(amt);
    } else {
        triggerModal('Оплата', 'Переход к защищенному шлюзу оплаты...');
        setTimeout(() => {
            userState.balance += amt;
            document.getElementById('user-balance').innerText = userState.balance.toLocaleString('ru-RU') + ".00 ₽";
            closeModal();
        }, 1500);
    }
}

function generateInvoice(amt) {
    const invNum = Math.floor(Math.random() * 90000 + 10000);
    // Расчет НДС 22% (Формула: Сумма * 22 / 122)
    const vat = (amt * 22 / 122).toFixed(2);
    const date = new Date().toLocaleDateString('ru-RU');

    const invoiceContent = `
        <html>
        <head><meta charset="UTF-8"><title>Счет №${invNum}</title></head>
        <body style="font-family: Arial; padding: 40px; color: #333;">
            <h1 style="text-align:center;">СЧЕТ НА ОПЛАТУ №${invNum}</h1>
            <p style="text-align:center;">от ${date} г.</p>
            <hr>
            <p><strong>Поставщик:</strong> ООО «Форсаж», ИНН 7728282160</p>
            <p><strong>Банк:</strong> ООО «Банк Точка», БИК 044525104, Р/С 40702810101500033019</p>
            <p><strong>Плательщик:</strong> ${userState.name} (ID: ${userState.id})</p>
            <table style="width:100%; border-collapse: collapse; margin-top:20px;">
                <tr style="background:#eee;">
                    <th style="border:1px solid #000; padding:10px;">Наименование</th>
                    <th style="border:1px solid #000; padding:10px;">Сумма</th>
                </tr>
                <tr>
                    <td style="border:1px solid #000; padding:10px;">Пополнение баланса (ID: ${userState.id})</td>
                    <td style="border:1px solid #000; padding:10px;">${amt.toLocaleString()}.00 руб.</td>
                </tr>
            </table>
            <p style="text-align:right; font-size: 18px; font-weight:bold;">Итого: ${amt.toLocaleString()}.00 руб.</p>
            <p style="text-align:right;">В т.ч. НДС (22%): ${vat.toLocaleString()} руб.</p>
            <p><strong>Назначение платежа:</strong> Пополнение баланса лицевого счета ЭТП-Эра, ID пользователя ${userState.id}. В т.ч. НДС (22%) — ${vat} руб.</p>
        </body>
        </html>
    `;
    
    const blob = new Blob([invoiceContent], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
}

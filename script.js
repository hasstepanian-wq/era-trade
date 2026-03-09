const userState = { 
    balance: 750000, 
    id: "ERA-77412", 
    name: "Артур Степанян" 
};

// 1. ЖИВЫЕ ЧАСЫ В ШАПКЕ
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if (clock) {
        clock.innerText = new Date().toLocaleTimeString('ru-RU');
    }
}, 1000);

// 2. НАВИГАЦИЯ МЕЖДУ РАЗДЕЛАМИ
function nav(pageId, element) {
    // Скрываем все страницы
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    // Убираем активный класс у кнопок меню
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    // Активируем нужную страницу и кнопку
    const targetPage = document.getElementById('p-' + pageId);
    if (targetPage) {
        targetPage.classList.add('active');
        element.classList.add('active');
    }
}

// 3. ВХОД В ЛИЧНЫЙ КАБИНЕТ
function openApp() {
    triggerModal('Авторизация', 'Вход в защищенную зону ID: ' + userState.id);
    setTimeout(() => {
        closeModal();
        document.getElementById('landing-view').style.display = 'none';
        document.getElementById('app-view').style.display = 'grid';
    }, 1000);
}

// 4. УНИВЕРСАЛЬНОЕ МОДАЛЬНОЕ ОКНО
function triggerModal(title, body) {
    document.getElementById('m-title').innerText = title;
    document.getElementById('m-body').innerText = body;
    document.getElementById('modal-overlay').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal-overlay').style.display = 'none';
}

// 5. ЛОГИКА ОПЛАТЫ
function doPayment() {
    const amtInput = document.getElementById('f-amount');
    const amt = parseFloat(amtInput.value);
    const method = document.getElementById('f-method').value;

    if (isNaN(amt) || amt <= 0) {
        triggerModal('Ошибка', 'Введите корректную сумму для пополнения.');
        return;
    }

    if (method === 'bank') {
        generateInvoice(amt);
    } else {
        triggerModal('Оплата', 'Переход к защищенному шлюзу СБП/Карты...');
        setTimeout(() => {
            userState.balance += amt;
            document.getElementById('user-balance').innerText = userState.balance.toLocaleString('ru-RU') + ".00 ₽";
            closeModal();
        }, 1500);
    }
}

// 6. ГЕНЕРАЦИЯ СЧЕТА С QR-КОДОМ (ГОСТ Р 56042-2014)
function generateInvoice(amt) {
    const invNum = Math.floor(Math.random() * 90000 + 10000);
    const vat = (amt * 22 / 122).toFixed(2); // НДС 22% изнутри суммы
    const date = new Date().toLocaleDateString('ru-RU');
    
    // Данные для QR (Сумма в копейках, реквизиты ООО "Форсаж")
    const sumKopeeks = Math.round(amt * 100);
    const qrData = `ST00012|Name=ООО ФОРСАЖ|PersonalAcc=40702810101500033019|BankName=ООО БАНК ТОЧКА|BIC=044525104|CorrespAcc=30101810745374525104|PayeeINN=7728282160|KPP=773001001|Sum=${sumKopeeks}|Purpose=Popolnenie balansa ID ${userState.id} v t.ch. NDS 22% ${vat} rub.`;
    
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrData)}`;

    const invoiceHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Счет №${invNum}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; color: #333; line-height: 1.5; }
                .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .qr-box { text-align: center; border: 1px solid #ccc; padding: 10px; border-radius: 8px; width: 170px; }
                .data-table { width: 100%; border: 1px solid #000; border-collapse: collapse; margin: 20px 0; }
                .data-table th, .data-table td { border: 1px solid #000; padding: 10px; text-align: left; }
                .totals { text-align: right; font-size: 18px; margin-top: 10px; }
                .footer-sign { margin-top: 50px; display: flex; gap: 100px; }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td>
                        <h1 style="margin:0;">СЧЕТ №${invNum}</h1>
                        <p>от ${date} г.</p>
                    </td>
                    <td style="width: 180px;">
                        <div class="qr-box">
                            <img src="${qrUrl}" width="150" height="150" alt="QR">
                            <div style="font-size: 10px; margin-top:5px;">Оплата по QR (ГОСТ)</div>
                        </div>
                    </td>
                </tr>
            </table>

            <hr>
            
            <p><strong>Поставщик:</strong> ООО «Форсаж», ИНН 7728282160, КПП 773001001</p>
            <p><strong>Банк:</strong> ООО «Банк Точка», БИК 044525104, Р/С 40702810101500033019</p>
            <p><strong>Плательщик:</strong> ${userState.name} (ID: ${userState.id})</p>

            <table class="data-table">
                <tr style="background: #f0f0f0;">
                    <th>Наименование услуги</th>
                    <th>Сумма (руб.)</th>
                </tr>
                <tr>
                    <td>Пополнение лицевого счета ЭТП ЭРА (ID: ${userState.id})</td>
                    <td style="text-align: right;">${amt.toLocaleString('ru-RU')}.00</td>
                </tr>
            </table>

            <div class="totals">
                <p><strong>Итого к оплате: ${amt.toLocaleString('ru-RU')}.00 руб.</strong></p>
                <p style="font-size: 14px; color: #555;">В т.ч. НДС (22%): ${vat.toLocaleString('ru-RU')} руб.</p>
            </div>

            <div style="margin-top: 30px; padding: 15px; border: 1px dashed #666; font-size: 13px;">
                <strong>Назначение платежа:</strong> Пополнение баланса лицевого счета ЭТП-Эра, ID пользователя ${userState.id}. В т.ч. НДС (22%) — ${vat} руб.
            </div>

            <div class="footer-sign">
                <div>М.П. ________________ / Руководитель</div>
                <div>________________ / Бухгалтер</div>
            </div>
        </body>
        </html>
    `;

    const printWin = window.open('', '_blank');
    printWin.document.write(invoiceHTML);
    printWin.document.close();
}

const userState = { balance: 750000, id: "ERA-77412", name: "Артур Степанян" };

// Часы
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if(clock) clock.innerText = new Date().toLocaleTimeString('ru-RU');
}, 1000);

// Навигация
function nav(pageId, element) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('p-' + pageId).classList.add('active');
    element.classList.add('active');
}

function openApp() {
    triggerModal('Вход', 'Авторизация пользователя ' + userState.id);
    setTimeout(() => {
        closeModal();
        document.getElementById('landing-view').style.display = 'none';
        document.getElementById('app-view').style.display = 'grid';
    }, 1000);
}

function triggerModal(title, body) {
    document.getElementById('m-title').innerText = title;
    document.getElementById('m-body').innerText = body;
    document.getElementById('modal-overlay').style.display = 'flex';
}

function closeModal() { document.getElementById('modal-overlay').style.display = 'none'; }

// Финансы и НДС
function doPayment() {
    const amt = parseFloat(document.getElementById('f-amount').value);
    const method = document.getElementById('f-method').value;

    if (method === 'bank') {
        generateInvoice(amt);
    } else {
        triggerModal('Оплата', 'Переход к оплате картой...');
        setTimeout(() => {
            userState.balance += amt;
            document.getElementById('user-balance').innerText = userState.balance.toLocaleString('ru-RU') + ".00 ₽";
            closeModal();
        }, 1500);
    }
}

function generateInvoice(amt) {
    const invNum = Math.floor(Math.random() * 90000 + 10000);
    const vat = (amt * 22 / 122).toFixed(2); // НДС 22% ИЗНУТРИ
    const date = new Date().toLocaleDateString('ru-RU');

    const html = `
        <html>
        <body style="font-family: sans-serif; padding: 40px;">
            <h1 style="text-align:center;">СЧЕТ №${invNum} от ${date}</h1>
            <hr>
            <p><strong>Поставщик:</strong> ООО «Форсаж», ИНН 7728282160</p>
            <p><strong>Банк:</strong> ООО «Банк Точка», БИК 044525104, Р/С 40702810101500033019</p>
            <p><strong>Плательщик:</strong> ${userState.name} (ID: ${userState.id})</p>
            <table style="width:100%; border:1px solid #000; border-collapse:collapse; margin:20px 0;">
                <tr style="background:#eee;">
                    <th style="border:1px solid #000; padding:10px;">Услуга</th>
                    <th style="border:1px solid #000; padding:10px;">Сумма</th>
                </tr>
                <tr>
                    <td style="border:1px solid #000; padding:10px;">Пополнение баланса ЭТП ЭРА (ID: ${userState.id})</td>
                    <td style="border:1px solid #000; padding:10px;">${amt.toLocaleString()}.00 руб.</td>
                </tr>
            </table>
            <p style="text-align:right;"><strong>Итого к оплате: ${amt.toLocaleString()}.00 руб.</strong></p>
            <p style="text-align:right;">В том числе НДС (22%): ${vat} руб.</p>
            <p style="margin-top:40px;"><strong>Назначение платежа:</strong> Пополнение баланса лицевого счета ЭТП-Эра, ID пользователя ${userState.id}. В т.ч. НДС (22%) — ${vat} руб.</p>
        </body>
        </html>
    `;
    const win = window.open('', '_blank');
    win.document.write(html);
}

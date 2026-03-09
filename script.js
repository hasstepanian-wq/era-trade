const userState = { balance: 750000, id: "ERA-77412", name: "Артур Степанян" };

// ЧАСЫ
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if(clock) clock.innerText = new Date().toLocaleTimeString('ru-RU');
}, 1000);

// НАВИГАЦИЯ
function nav(pageId, element) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    const target = document.getElementById('p-' + pageId);
    if(target) {
        target.classList.add('active');
        if(element) element.classList.add('active');
    }
}

function openApp() {
    triggerModal('Вход', 'Загрузка системы ЭТП ЭРА...');
    setTimeout(() => {
        closeModal();
        document.getElementById('landing-view').style.display = 'none';
        document.getElementById('app-view').style.display = 'grid';
    }, 800);
}

// МОДАЛКИ
function triggerModal(title, body) {
    document.getElementById('m-title').innerText = title;
    document.getElementById('m-body').innerText = body;
    document.getElementById('modal-overlay').style.display = 'flex';
}
function closeModal() { document.getElementById('modal-overlay').style.display = 'none'; }

// ПЛАТЕЖИ
function doPayment() {
    const amt = parseFloat(document.getElementById('f-amount').value);
    const method = document.getElementById('f-method').value;

    if(isNaN(amt) || amt <= 0) {
        triggerModal('Ошибка', 'Укажите сумму пополнения');
        return;
    }

    if(method === 'bank') {
        generateInvoice(amt);
    } else {
        triggerModal('Оплата', 'Переход к СБП...');
        setTimeout(() => {
            userState.balance += amt;
            document.getElementById('user-balance').innerText = userState.balance.toLocaleString('ru-RU') + ".00 ₽";
            closeModal();
            triggerModal('Успешно', 'Баланс пополнен!');
        }, 1500);
    }
}

// ГЕНЕРАЦИЯ СЧЕТА С QR (ТВОЯ ЛЮБИМАЯ ФУНКЦИЯ)
function generateInvoice(amt) {
    const invNum = Math.floor(Math.random() * 90000 + 10000);
    const vat = (amt * 22 / 122).toFixed(2);
    const date = new Date().toLocaleDateString('ru-RU');
    const sumKopeeks = Math.round(amt * 100);
    
    const qrData = `ST00012|Name=ООО ФОРСАЖ|PersonalAcc=40702810101500033019|BankName=ООО БАНК ТОЧКА|BIC=044525104|CorrespAcc=30101810745374525104|PayeeINN=7728282160|KPP=773001001|Sum=${sumKopeeks}|Purpose=Popolnenie balansa ID ${userState.id} v t.ch. NDS 22% ${vat} rub.`;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrData)}`;

    const html = `<html><body style="font-family:sans-serif;padding:40px;">
        <div style="display:flex;justify-content:space-between;">
            <div><h1>СЧЕТ №${invNum}</h1><p>от ${date}</p></div>
            <img src="${qrUrl}" width="150">
        </div>
        <hr><p><strong>Поставщик:</strong> ООО «Форсаж», ИНН 7728282160</p>
        <p><strong>Плательщик:</strong> ${userState.name} (ID: ${userState.id})</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr style="background:#eee;"><th style="border:1px solid #000;padding:10px;">Услуга</th><th style="border:1px solid #000;padding:10px;">Сумма</th></tr>
            <tr><td style="border:1px solid #000;padding:10px;">Пополнение баланса (ID: ${userState.id})</td><td style="border:1px solid #000;padding:10px;text-align:right;">${amt.toLocaleString()}.00 ₽</td></tr>
        </table>
        <p style="text-align:right;"><strong>Итого: ${amt.toLocaleString()}.00 ₽</strong><br><small>В т.ч. НДС (22%): ${vat} ₽</small></p>
        <p style="margin-top:40px;border:1px dashed #ccc;padding:10px;"><strong>Назначение:</strong> Пополнение ID ${userState.id}. В т.ч. НДС (22%) — ${vat} руб.</p>
    </body></html>`;
    
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
}

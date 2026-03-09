// Функция входа
function enterSite() {
    // Ищем окно по ID
    const modal = document.getElementById('modal-overlay');
    if (modal) {
        modal.style.display = 'none'; // Скрываем темный фон
        console.log("Вход выполнен успешно");
    } else {
        // Если ID другой, пробуем найти по классу
        const overlay = document.querySelector('.modal-overlay');
        if (overlay) overlay.style.display = 'none';
    }
}

// Живые часы (чтобы время в шапке шло)
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if (clock) {
        clock.innerText = new Date().toLocaleTimeString('ru-RU');
    }
}, 1000);

// Живые часы
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if (clock) {
        clock.innerText = new Date().toLocaleTimeString('ru-RU');
    }
}, 1000);

// Логика перехода (пока просто консоль, чтобы не ломать ссылки)
console.log("ЭТП ЭРА: Морда сайта загружена успешно.");

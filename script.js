function enterSite() {
    // Просто скрываем окно при нажатии
    document.getElementById('modal-overlay').style.display = 'none';
}

setInterval(() => {
    const clock = document.getElementById('live-clock');
    if(clock) clock.innerText = new Date().toLocaleTimeString('ru-RU');
}, 1000);

setInterval(() => {
    const clock = document.getElementById('live-clock');
    if (clock) clock.innerText = new Date().toLocaleTimeString('ru-RU');
}, 1000);

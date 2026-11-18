// Универсальный скрипт для плавного появления скроллбара
document.addEventListener('DOMContentLoaded', () => {
    // Ищем все элементы, у которых должен быть скрытый скроллбар
    const scrollables = document.querySelectorAll('.discussed-list, .outline-list');
    
    scrollables.forEach(el => {
        let scrollTimer;
        el.addEventListener('scroll', () => {
            el.classList.add('is-scrolling');
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                el.classList.remove('is-scrolling');
            }, 1000); // 1000 мс = 1 секунда после остановки
        });
    });
});
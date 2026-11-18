<section class="editor" style="max-width: 800px; margin: 0 auto; padding-bottom: 80px;">
    <header class="page-head" style="margin-bottom: 32px;">
        <h1>Как писать статьи</h1>
        <p class="lead">Наш редактор использует Markdown — простой способ форматирования текста с помощью символов. Вот основные примеры.</p>
    </header>

    <div class="md-help-list">
        
        <div class="md-help-item">
            <h3>Заголовки</h3>
            <p>Поставьте решётку и пробел в начале строки. Чем больше решёток, тем меньше заголовок.</p>
            <div class="md-help-code">
                <code># Заголовок 1</code>
                <code>## Заголовок 2</code>
                <code>### Заголовок 3</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Форматирование текста</h3>
            <p>Оборачивайте текст в звёздочки или подчёркивания.</p>
            <div class="md-help-code">
                <code>**Жирный текст**</code>
                <code>*Курсивный текст*</code>
                <code>__Подчёркнутый__</code>
                <code>~~Зачёркнутый~~</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Цитаты</h3>
            <p>Поставьте угловую скобку и пробел в начале строки.</p>
            <div class="md-help-code">
                <code>&gt; Это цитата</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Код</h3>
            <p>Для коротких фраз используйте обратные кавычки. Для блоков кода — три кавычки с указанием языка.</p>
            <div class="md-help-code">
                <code>`инлайн-код`</code>
                <code>```php<br>echo "Привет";<br>```</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Списки</h3>
            <p>Начните строку с дефиса для маркированного списка, или с цифры и точки — для нумерованного.</p>
            <div class="md-help-code">
                <code>- Первый пункт<br>- Второй пункт</code>
                <code>1. Первый<br>2. Второй</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Ссылки</h3>
            <p>Текст ссылки в квадратных скобках, URL — в круглых.</p>
            <div class="md-help-code">
                <code>[Текст ссылки](https://example.com)</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Спойлеры</h3>
            <p>Скрытый блок, который разворачивается по клику.</p>
            <div class="md-help-code">
                <code>[!spoiler]Скрытый текст[/spoiler]</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Якоря (оглавление)</h3>
            <p>Создают невидимую метку, на которую можно сослаться. Это удобно для создания оглавления внутри длинной статьи.</p>
            <div class="md-help-code">
                <code>&lt;anchor&gt;название-раздела&lt;/anchor&gt;</code>
                <code>[Перейти к разделу](#название-раздела)</code>
            </div>
        </div>

        <div class="md-help-item">
            <h3>Картинки</h3>
            <p>Работает так же, как ссылка, но с восклицательным знаком в начале. Чтобы управлять размером и выравниванием, добавьте параметры в фигурных скобках.</p>
            <div class="md-help-code">
                <code>![Описание](https://example.com/img.jpg){width=300 align=right}</code>
            </div>
            <p class="hint" style="margin-top: 8px;">Доступные параметры: <code>width=300</code> (или 50%), <code>align=left</code>, <code>align=right</code>, <code>align=center</code>.</p>
        </div>

    </div>
    
    <div style="margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--border);">
        <a href="javascript:history.back()" class="btn-secondary">← Вернуться назад</a>
    </div>
</section>
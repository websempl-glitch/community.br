# Community Blog

Минималистичный блог с продвинутой системой комментариев. Чистый PHP 8, MySQL 8.4, без тяжёлых фреймворков и сборщиков.

## Возможности

- Публикация статей с Markdown, спойлерами, подсветкой кода
- Тредовые комментарии с реакциями, лайтбоксом, поллингом обновлений
- Уведомления, подписки на авторов и статьи, закладки, черновики
- Личные сообщения с блокировками и жалобами
- Достижения, карма, роли (user / admin / owner)
- Админ-панель: категории, пользователи, жалобы, предложения категорий, настройки

## Технологии

| Слой | Что |
|------|-----|
| Backend | PHP 8.x, самописный мини-MVC (Router, Controllers, Views) |
| БД | MySQL 8.4 (InnoDB, utf8mb4), только PDO, без ORM |
| Frontend | Vanilla JS + Alpine.js + Highlight.js |
| Стили | Чистый CSS с переменными |
| Хостинг | Shared-хостинг (Beget и т.п.), без Node/Redis/WebSockets |

## Быстрый старт

1. **Клонировать**
   ```bash
   git clone <url>
   cd community.br
   ```

2. **Настроить конфиг**
   ```bash
   cp config/config.sample.php config/config.php
   ```
   Отредактировать `config/config.php` — указать URL и доступы к БД.

3. **Создать базу**
   ```bash
   mysql -u root -p < sql/schema.sql
   ```

4. **Права на запись**
   ```
   uploads/      0775
   storage/logs/ 0775
   ```

5. **Открыть сайт** → `/register` → создать первого пользователя → вручную назначить `role='admin'` в таблице `users`.

## Структура

```
/
├── .htaccess                 # Роутинг и защита
├── index.php                 # Единая точка входа
├── config/
│   ├── config.sample.php     # Шаблон конфига (в git)
│   └── config.php            # Реальный конфиг (в .gitignore)
├── app/
│   ├── bootstrap.php         # Автозагрузка, сессия
│   ├── helpers.php           # view(), e(), csrf(), slugify() и др.
│   ├── Database.php          # PDO-обёртка
│   ├── Auth.php              # Аутентификация + remember-me
│   ├── Router.php            # Маршрутизация /{param}
│   ├── Mailer.php            # Отправка писем (log/smtp)
│   ├── Markdown.php          # Парсер Markdown
│   ├── UserStatus.php        # Статусы (Новичок/Участник/Ветеран)
│   ├── Karma.php             # Карма
│   ├── Achievement.php       # Достижения
│   ├── CommentFeed.php       # Лента комментариев
│   ├── RateLimit.php         # Rate limiting через БД
│   ├── OrphanCleaner.php     # Фоновая чистка картинок-сирот
│   ├── Controllers/          # 10 контроллеров
│   └── Views/                # Шаблоны (layout, articles, comments, admin, settings…)
├── public/assets/            # style.css, comments.js, editor.js, notifications.js, ui.js
├── sql/
│   ├── schema.sql            # Чистая DDL-схема (в git)
│   └── *.sql                 # Дампы с данными (в .gitignore)
├── uploads/                  # Файлы пользователей (в .gitignore)
└── storage/logs/             # Логи (в .gitignore)
```

## Безопасность

- CSRF-токены (`hash_equals`) для форм и AJAX (`X-CSRF-Token`)
- Пароли: `password_hash` (bcrypt)
- Remember-me: selector/validator-схема (парольные токены в `user_tokens`)
- Сессии: `HttpOnly`, `SameSite=Lax`, `Secure` при HTTPS
- SQL: только PDO prepared statements, `EMULATE_PREPARES=false`
- Загрузка файлов: проверка MIME через `finfo`, `getimagesize`
- Rate limiting через таблицу `rate_limits`
- `.htaccess` закрывает `.sql`, `.log`, `.env`, `.md`

## Лицензия

MIT

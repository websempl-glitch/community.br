# Минималистичный блог с продвинутой системой комментариев

Веб-приложение для публикации статей с фокусом на глубокое и удобное обсуждение. 
Спроектировано в минималистичном стиле, без тяжелых фреймворков и сборщиков.

## 🛠 Технологический стек

*   **Backend:** PHP 8.x (чистый, без фреймворков, самописный мини-MVC)
*   **База данных:** MySQL 8.4 (InnoDB, utf8mb4)
*   **Frontend:** Vanilla JS, Alpine.js (реактивность без сборки), Highlight.js (подсветка кода)
*   **Стили:** Чистый CSS (с CSS-переменными)
*   **Хостинг:** Рассчитан на обычный shared-хостинг (например, Beget), без Node.js, Redis и WebSockets.

---

## 📂 Структура проекта

```text
/ (корень сайта)
├── .htaccess                 # Роутинг и защита
├── index.php                 # Единая точка входа
├── config/
│   ├── config.sample.php     # Шаблон конфига (в git)
│   └── config.php            # Реальный конфиг (в .gitignore)
├── app/
│   ├── bootstrap.php         # Инициализация, сессии, автозагрузка
│   ├── helpers.php           # Глобальные хелперы (view, e, csrf, plural)
│   ├── Database.php          # Обертка над PDO
│   ├── Auth.php              # Аутентификация (включая Remember Me)
│   ├── Router.php            # Роутер с поддержкой параметров /{id}
│   ├── Mailer.php            # Отправка писем (log/smtp)
│   ├── Markdown.php          # Парсер Markdown (спойлеры, код, форматирование)
│   ├── UserStatus.php        # Логика статусов (Новичок/Участник/Ветеран)
│   ├── Karma.php             # Карма пользователей
│   ├── Achievement.php       # Достижения
│   ├── CommentFeed.php       # Лента уведомлений (новые комментарии, ответы)
│   ├── RateLimit.php         # Rate limiting через БД
│   ├── OrphanCleaner.php     # Фоновая чистка "осиротевших" картинок
│   ├── Controllers/          # Контроллеры (Auth, Article, Comment, Category, User, Upload, Settings, Admin, Notification, Message)
│   └── Views/                # Шаблоны (layout, articles, categories, users, settings, admin, auth, errors)
├── public/
│   └── assets/
│       ├── style.css         # Все стили проекта
│       ├── comments.js       # Логика комментариев, поллинга, лайтбокса
│       ├── editor.js         # Markdown-редактор (панель + превью)
│       ├── notifications.js  # Счётчик уведомлений
│       └── ui.js             # Общие UI-хелперы
├── sql/
│   ├── schema.sql            # Чистая DDL-схема (в git)
│   └── *.sql                 # Дампы с данными (в .gitignore)
├── uploads/                  # Загруженные файлы (аватары, картинки из комментов) — в .gitignore
└── storage/
    └── logs/                 # Логи писем в dev-режиме, ошибки — в .gitignore
<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ArticleController;
use App\Controllers\CommentController;
use App\Controllers\UploadController;
use App\Controllers\UserController;
use App\Controllers\SettingsController;
use App\Controllers\AdminController;
use App\Controllers\NotificationController;
use App\Controllers\MessageController;

$router = new Router();

// Главная (пока заглушка)
$router->get('/', [ArticleController::class, 'index']);

// Админка
$router->get('/admin',                           [AdminController::class, 'dashboard']);
$router->get('/admin/categories',                [AdminController::class, 'categories']);
$router->post('/admin/categories',               [AdminController::class, 'createCategory']);
$router->post('/admin/categories/{id}/update',   [AdminController::class, 'updateCategory']);
$router->post('/admin/categories/{id}/delete',   [AdminController::class, 'deleteCategory']);
$router->get('/admin/users',                     [AdminController::class, 'users']);
$router->post('/admin/users/{id}/role',          [AdminController::class, 'updateUserRole']);
$router->post('/admin/users/{id}/ban',           [AdminController::class, 'toggleUserBan']);
$router->post('/admin/users/{id}/delete',        [AdminController::class, 'deleteUser']);
$router->get('/admin/settings',                  [AdminController::class, 'settings']);
$router->post('/admin/settings',                 [AdminController::class, 'settingsUpdate']);
$router->get('/admin/suggestions',                  [AdminController::class, 'suggestions']);
$router->post('/api/admin/suggestions/{id}/approve', [AdminController::class, 'approveSuggestion']);
$router->post('/api/admin/suggestions/{id}/reject',  [AdminController::class, 'rejectSuggestion']);

$router->get('/admin/reports',                   [AdminController::class, 'reports']);
$router->post('/api/admin/reports/{id}/resolve', [AdminController::class, 'resolveReport']);

// Жалобы
$router->post('/api/comments/{id}/report', [CommentController::class, 'report']);

// Аутентификация
$router->get('/register',         [AuthController::class, 'showRegister']);
$router->post('/register',        [AuthController::class, 'register']);
$router->get('/login',            [AuthController::class, 'showLogin']);
$router->post('/login',           [AuthController::class, 'login']);
$router->post('/logout',          [AuthController::class, 'logout']);
$router->get('/verify',           [AuthController::class, 'verify']);
$router->get('/verify-pending',   [AuthController::class, 'showVerifyPending']);
$router->get('/forgot-password',  [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/reset-password',   [AuthController::class, 'showResetPassword']);
$router->post('/reset-password',  [AuthController::class, 'resetPassword']);

// Уведомления
$router->get('/api/notifications/count', [NotificationController::class, 'count']);
$router->get('/api/notifications',       [NotificationController::class, 'index']);
$router->post('/api/notifications/read', [NotificationController::class, 'markRead']);
$router->get('/u/{username}/notifications', [UserController::class, 'notifications']);
$router->post('/api/notifications/{id}/read', [NotificationController::class, 'markReadSingle']);
$router->post('/api/notifications/read-all',  [NotificationController::class, 'markAllRead']);

// Профиль
$router->get('/u/{username}',                 [UserController::class, 'show']);
$router->get('/u/{username}/articles',        [UserController::class, 'articles']);
$router->get('/u/{username}/comments',        [UserController::class, 'comments']);
$router->get('/u/{username}/drafts',          [UserController::class, 'drafts']);
$router->get('/u/{username}/bookmarks',       [UserController::class, 'bookmarks']);
$router->get('/u/{username}/subscriptions',   [UserController::class, 'subscriptions']);
// Внутренние сообщения
$router->get('/u/{username}/messages',           [MessageController::class, 'inbox']);
$router->get('/u/{username}/messages/view/{id}', [MessageController::class, 'view']);
$router->get('/u/{username}/message',            [MessageController::class, 'create']);
$router->post('/u/{username}/message',           [MessageController::class, 'store']);
$router->post('/api/messages/{id}/delete',       [MessageController::class, 'delete']);
$router->post('/api/messages/bulk-delete',     [MessageController::class, 'bulkDelete']);
$router->post('/api/users/{id}/toggle-block',  [MessageController::class, 'toggleBlock']);
$router->post('/api/messages/{id}/report', [MessageController::class, 'report']);

// Профиль API
$router->get('/api/users/{username}/comments', [UserController::class, 'apiComments']);
$router->post('/api/users/{id}/follow',       [UserController::class, 'follow']);

// Настройки профиля
$router->get('/settings',                 [SettingsController::class, 'index']);
$router->post('/settings',                [SettingsController::class, 'update']);
$router->post('/settings/avatar/delete',  [SettingsController::class, 'deleteAvatar']);
$router->get('/settings/password',        [SettingsController::class, 'passwordForm']);
$router->post('/settings/password',       [SettingsController::class, 'passwordUpdate']);
$router->get('/settings/email',           [SettingsController::class, 'emailForm']);
$router->post('/settings/email',          [SettingsController::class, 'emailRequest']);
$router->get('/settings/email/confirm',   [SettingsController::class, 'emailConfirm']);
$router->post('/settings/email/cancel',   [SettingsController::class, 'emailCancel']);
$router->get('/settings/notifications',  [SettingsController::class, 'notificationsForm']);
$router->post('/settings/notifications', [SettingsController::class, 'notificationsUpdate']);
$router->get('/settings/delete',          [SettingsController::class, 'deleteForm']);
$router->post('/settings/delete',         [SettingsController::class, 'deleteAccount']);

// Категории
$router->get('/category/{slug}', [CategoryController::class, 'showParent']);
$router->get('/category/{parent_slug}/{sub_slug}', [CategoryController::class, 'showSub']);

// Статьи
$router->get('/article/create',                [ArticleController::class, 'create']);
$router->post('/article/create',               [ArticleController::class, 'store']);
$router->get('/article/{slug}/edit',           [ArticleController::class, 'edit']);
$router->post('/article/{slug}/edit',          [ArticleController::class, 'update']);
$router->get('/article/{slug}',                [ArticleController::class, 'show']);
$router->post('/api/articles/{id}/update-pin', [ArticleController::class, 'updatePin']);
// Поиск
$router->get('/search',               [ArticleController::class, 'search']);

// Комментарии (API)
$router->get('/api/articles/{slug}/comments', [CommentController::class, 'index']);
$router->get('/api/articles/{slug}/updates',  [CommentController::class, 'updates']);
$router->get('/api/comments/{id}/replies',    [CommentController::class, 'replies']);
$router->post('/api/comments',                [CommentController::class, 'store']);
$router->post('/api/comments/{id}/update',    [CommentController::class, 'update']);
$router->post('/api/comments/{id}/read',      [CommentController::class, 'markReadComment']);
$router->post('/api/comments/{id}/react',     [CommentController::class, 'react']);
$router->post('/api/comments/{id}/delete',    [CommentController::class, 'destroy']);
$router->post('/api/articles/{id}/follow',    [ArticleController::class, 'follow']);
$router->post('/api/articles/{id}/unfollow',  [ArticleController::class, 'unfollow']);
$router->post('/api/articles/{id}/mark-read', [ArticleController::class, 'markRead']);
$router->post('/api/articles/{id}/bookmark',  [ArticleController::class, 'bookmark']);
$router->post('/api/articles/{id}/publish',   [ArticleController::class, 'publish']);
$router->post('/api/articles/{id}/delete',    [ArticleController::class, 'destroy']);
$router->post('/api/markdown/preview',        [ArticleController::class, 'preview']);
// Загрузка изображений
$router->post('/api/upload', [UploadController::class, 'image']);

// Модерация
$router->post('/api/comments/{id}/hide',      [CommentController::class, 'hide']);
$router->post('/api/comments/{id}/unhide',    [CommentController::class, 'unhide']);
$router->post('/api/comments/{id}/pin',       [CommentController::class, 'pin']);
$router->post('/api/comments/{id}/unpin',     [CommentController::class, 'unpin']);
$router->post('/api/articles/{id}/restrict-user', [ArticleController::class, 'restrictUser']);

// Ссылки на комментарии — самый широкий маршрут, идёт последним
$router->get('/api/comments/{id}',            [CommentController::class, 'show']);

// Инструкция Markdown
$router->get('/help/markdown', [ArticleController::class, 'markdownHelp']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);
<?php
declare(strict_types=1);

// --- Автозагрузка PSR-4 для App\ ---
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

require __DIR__ . '/helpers.php';

// --- Сессия ---
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);

session_start();

// Восстанавливаем сессию из remember-cookie до любого вывода HTML
\App\Auth::restoreFromCookieIfNeeded();

// --- Ошибки в dev-режиме ---
if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
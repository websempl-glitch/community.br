<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;

class UploadController
{
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 МБ
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public function image(): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        // Rate limit: 50 загрузок в час
        $rlKey  = 'upload_user:' . (int)$user['id'];
        $wait   = \App\RateLimit::check($rlKey);
        if ($wait !== null) {
            json_response([
                'ok'    => false,
                'error' => 'Слишком много загрузок. ' . \App\RateLimit::humanWait($wait) . '.',
            ], 429);
        }

        if (empty($_FILES['file'])) {
            json_response(['ok' => false, 'error' => 'Файл не получен'], 400);
        }

        $f = $_FILES['file'];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            json_response(['ok' => false, 'error' => 'Ошибка загрузки: код ' . $f['error']], 400);
        }
        if ($f['size'] > self::MAX_SIZE) {
            json_response(['ok' => false, 'error' => 'Файл больше 5 МБ'], 400);
        }

        // Определяем MIME по содержимому, а не по расширению
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            json_response(['ok' => false, 'error' => 'Разрешены только JPEG, PNG, WebP, GIF'], 400);
        }

        $info = @getimagesize($f['tmp_name']);
        if (!$info) {
            json_response(['ok' => false, 'error' => 'Не удалось прочитать изображение'], 400);
        }
        [$width, $height] = $info;

        // Проверим адекватность размеров
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            json_response(['ok' => false, 'error' => 'Некорректный размер изображения'], 400);
        }

        $ext  = self::ALLOWED_MIME[$mime];
        $dir  = 'uploads/' . date('Y/m');
        $absDir = __DIR__ . '/../../' . $dir;

        if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
            json_response(['ok' => false, 'error' => 'Не удалось создать папку'], 500);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $relPath = $dir . '/' . $name;
        $absPath = $absDir . '/' . $name;

        // Фиксируем попытку ДО сохранения — блок на 51-й, а не на 52-й
        if (\App\RateLimit::hit($rlKey, 50, 3600, 3600)) {
            json_response([
                'ok'    => false,
                'error' => 'Слишком много загрузок. 60 минут подождите.',
            ], 429);
        }
		
		if (!move_uploaded_file($f['tmp_name'], $absPath)) {
            json_response(['ok' => false, 'error' => 'Не удалось сохранить файл'], 500);
        }

        @chmod($absPath, 0644);

        // Запись в БД — пока без комментария
        $db = Database::conn();
        $db->prepare(
            'INSERT INTO comment_images (comment_id, user_id, path, mime, width, height, size)
             VALUES (NULL, ?, ?, ?, ?, ?, ?)'
        )->execute([$user['id'], $relPath, $mime, $width, $height, $f['size']]);

        $id = (int)$db->lastInsertId();

        json_response([
            'ok' => true,
            'image' => [
                'id'     => $id,
                'url'    => '/' . $relPath,
                'width'  => $width,
                'height' => $height,
                'size'   => (int)$f['size'],
            ],
        ]);
    }
}
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;

class SettingsController
{
    private const AVATAR_SIZE   = 400;
    private const AVATAR_MAX    = 5 * 1024 * 1024;
    private const BIO_MAX       = 300;
    private const ALLOWED_MIME  = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function index(): void
    {
        $user = $this->requireUser();

        $this->render('index', 'profile', [
            'pageTitle' => 'Настройки профиля',
            'user'      => $user,
        ]);
    }

    public function update(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        $bio    = trim((string)($_POST['bio'] ?? ''));
        $errors = [];

        if (mb_strlen($bio) > self::BIO_MAX) {
            $errors['bio'] = 'Bio — не более ' . self::BIO_MAX . ' символов.';
        }

        // Обработка аватара (если загружен)
        $newAvatarPath = null;
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $newAvatarPath = $this->processAvatar((int)$user['id'], $_FILES['avatar']);
            } catch (\RuntimeException $e) {
                $errors['avatar'] = $e->getMessage();
            }
        }

        if ($errors) {
            // Перечитаем пользователя, чтобы отдать свежие данные во view
            $fresh = $this->requireUser();
            $this->render('index', 'profile', [
                'pageTitle' => 'Настройки профиля',
                'user'      => $fresh,
                'errors'    => $errors,
                'old'       => ['bio' => $bio],
                'toast'     => null,
            ]);
            return;
        }

        $db = Database::conn();
        $db->prepare('UPDATE users SET bio = ? WHERE id = ?')
           ->execute([$bio !== '' ? $bio : null, $user['id']]);

        // Аватар уже сохранён в processAvatar и записан в БД
        // (см. processAvatar — там update сразу)

        $fresh = $this->requireUser();

        $this->render('index', 'profile', [
            'pageTitle' => 'Настройки профиля',
            'user'      => $fresh,
            'errors'    => [],
            'old'       => ['bio' => $bio],
            'toast'     => 'Профиль обновлён',
        ]);
    }

    public function deleteAvatar(): void
    {
        $user = $this->requireUser();
        csrf_check_any();

        if (!empty($user['avatar_path'])) {
            $this->removeAvatarFile($user['avatar_path']);
        }

        Database::conn()->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')
            ->execute([$user['id']]);

        json_response(['ok' => true]);
    }
	
	/* ─── Смена пароля ────────────────────────────────── */

    public function passwordForm(): void
    {
        $user = $this->requireUser();

        $this->render('password', 'password', [
            'pageTitle' => 'Смена пароля',
            'user'      => $user,
            'errors'    => [],
            'toast'     => null,
        ]);
    }

    public function passwordUpdate(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        $current = (string)($_POST['current'] ?? '');
        $new     = (string)($_POST['new'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        $errors = [];

        if ($current === '') {
            $errors['current'] = 'Введите текущий пароль.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $errors['current'] = 'Текущий пароль неверный.';
        }

        if (mb_strlen($new) < 8) {
            $errors['new'] = 'Новый пароль — минимум 8 символов.';
        } elseif ($new === $current) {
            $errors['new'] = 'Новый пароль не должен совпадать с текущим.';
        }

        if ($confirm !== $new) {
            $errors['confirm'] = 'Пароли не совпадают.';
        }

        if ($errors) {
            $this->render('password', 'password', [
                'pageTitle' => 'Смена пароля',
                'user'      => $user,
                'errors'    => $errors,
                'toast'     => null,
            ]);
            return;
        }

        $db = Database::conn();

        // Обновляем пароль
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

        // Удаляем все remember-токены пользователя
        $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')
           ->execute([$user['id']]);

        // Пересоздаём сессию (защита от session fixation)
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf']    = bin2hex(random_bytes(32));

        // Чистим remember-куку, если была
        if (!empty($_COOKIE['remember'])) {
            setcookie('remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE['remember']);
        }

        $fresh = $this->requireUser();

        $this->render('password', 'password', [
            'pageTitle' => 'Смена пароля',
            'user'      => $fresh,
            'errors'    => [],
            'toast'     => 'Пароль изменён. Все другие устройства разлогинены.',
        ]);
    }

    /* ─── Вспомогательные ─────────────────────────────── */
	
	private function render(string $view, string $tab, array $data = []): void
    {
        view('settings/layout', array_merge([
            'contentView' => $view,
            'activeTab'   => $tab,
        ], $data));
    }
	
    private function requireUser(): array
    {
        $u = Auth::user();
        if (!$u) {
            redirect('/login');
        }
        return $u;
    }

    /**
     * Обрабатывает загруженный аватар: валидация, обрез по центру, сохранение,
     * удаление старого файла, обновление users.avatar_path.
     * Возвращает относительный путь нового файла.
     */
    private function processAvatar(int $userId, array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Ошибка загрузки файла.');
        }
        if ($file['size'] > self::AVATAR_MAX) {
            throw new \RuntimeException('Файл больше 5 МБ.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new \RuntimeException('Разрешены только JPEG, PNG, WebP.');
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info) {
            throw new \RuntimeException('Не удалось прочитать изображение.');
        }
        [$width, $height] = $info;

        // Открываем источник
        $src = $this->createImageFromFile($file['tmp_name'], $mime);
        if (!$src) {
            throw new \RuntimeException('Не удалось открыть изображение.');
        }

        // Сторона квадрата и координаты центрального кропа
        $side = min($width, $height);
        $srcX = (int)(($width  - $side) / 2);
        $srcY = (int)(($height - $side) / 2);

        // Результат 400×400
        $dst = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        // Белый фон — чтобы прозрачные PNG не стали чёрными
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE, $white);

        imagecopyresampled(
            $dst, $src,
            0, 0, $srcX, $srcY,
            self::AVATAR_SIZE, self::AVATAR_SIZE,
            $side, $side
        );

        // Куда сохраняем
        $dir = __DIR__ . '/../../uploads/avatars';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new \RuntimeException('Не удалось создать папку для аватаров.');
        }

        $filename = $userId . '_' . time() . '.jpg';
        $absPath  = $dir . '/' . $filename;
        $relPath  = 'uploads/avatars/' . $filename;

        imagejpeg($dst, $absPath, 88);
        imagedestroy($src);
        imagedestroy($dst);

        @chmod($absPath, 0644);

        // Удаляем старый файл (если был) и его родственников с прежними timestamp
        $db = Database::conn();
        $stmt = $db->prepare('SELECT avatar_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $oldPath = (string)$stmt->fetchColumn();
        if ($oldPath !== '') {
            $this->removeAvatarFile($oldPath);
        }

        // Записываем новый путь
        $db->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')
           ->execute([$relPath, $userId]);

        return $relPath;
    }

    private function createImageFromFile(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => null,
        };
    }

    private function removeAvatarFile(string $relPath): void
    {
        // Защита от подстановки путей
        if (!str_starts_with($relPath, 'uploads/avatars/')) return;

        $abs = __DIR__ . '/../../' . $relPath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
	
	    /* ─── Смена email ─────────────────────────────────── */

    public function emailForm(): void
    {
        $user = $this->requireUser();
        $pending = $this->getPendingEmailChange((int)$user['id']);

        $this->render('email', 'email', [
            'pageTitle' => 'Смена email',
            'user'      => $user,
            'pending'   => $pending,
            'errors'    => [],
            'toast'     => null,
        ]);
    }

    public function emailRequest(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        $newEmail = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $errors = [];

        if (!password_verify($password, $user['password_hash'])) {
            $errors['password'] = 'Пароль неверный.';
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email.';
        } elseif (mb_strtolower($newEmail) === mb_strtolower($user['email'])) {
            $errors['email'] = 'Это ваш текущий email.';
        } else {
            $stmt = Database::conn()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$newEmail]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Этот email уже занят.';
            }
        }

        if ($errors) {
            $this->render('email', 'email', [
                'pageTitle' => 'Смена email',
                'user'      => $user,
                'pending'   => $this->getPendingEmailChange((int)$user['id']),
                'errors'    => $errors,
                'toast'     => null,
            ]);
            return;
        }

        $db = Database::conn();

        // Удаляем предыдущие незавершённые попытки
        $db->prepare('DELETE FROM email_changes WHERE user_id = ?')->execute([$user['id']]);

        $token = bin2hex(random_bytes(32));

        $db->prepare(
            'INSERT INTO email_changes (user_id, new_email, token, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        )->execute([$user['id'], $newEmail, $token]);

        $confirmUrl = rtrim(config('app.url'), '/') . '/settings/email/confirm?token=' . $token;

        // На новый — с подтверждением; на старый — уведомление
        \App\Mailer::sendEmailChangeConfirmation($newEmail, $user['username'], $confirmUrl);
        \App\Mailer::sendEmailChangeNotice($user['email'], $user['username'], $newEmail);

        $this->render('email', 'email', [
            'pageTitle' => 'Смена email',
            'user'      => $user,
            'pending'   => $this->getPendingEmailChange((int)$user['id']),
            'errors'    => [],
            'toast'     => 'Письмо с подтверждением отправлено на ' . $newEmail,
        ]);
    }

    public function emailConfirm(): void
    {
        $token = $_GET['token'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            view('auth/email-change-fail', ['pageTitle' => 'Смена email']);
            return;
        }

        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT ec.id, ec.user_id, ec.new_email
             FROM email_changes ec
             WHERE ec.token = ? AND ec.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            view('auth/email-change-fail', ['pageTitle' => 'Смена email']);
            return;
        }

        // Проверим, что новый email не заняли, пока шло подтверждение
        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$row['new_email']]);
        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM email_changes WHERE id = ?')->execute([(int)$row['id']]);
            view('auth/email-change-fail', [
                'pageTitle' => 'Смена email',
                'reason'    => 'Этот email уже занят другим пользователем.',
            ]);
            return;
        }

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?')
               ->execute([$row['new_email'], (int)$row['user_id']]);

            $db->prepare('DELETE FROM email_changes WHERE user_id = ?')
               ->execute([(int)$row['user_id']]);

            $db->commit();
        } catch (\Throwable) {
            $db->rollBack();
            view('auth/email-change-fail', ['pageTitle' => 'Смена email']);
            return;
        }

        view('auth/email-change-success', ['pageTitle' => 'Email обновлён']);
    }

    public function emailCancel(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        Database::conn()->prepare('DELETE FROM email_changes WHERE user_id = ?')
            ->execute([$user['id']]);

        redirect('/settings/email');
    }

    private function getPendingEmailChange(int $userId): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT new_email, created_at FROM email_changes
             WHERE user_id = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
	
	/* ─── Удаление аккаунта ───────────────────────────── */

    public function deleteForm(): void
    {
        $user = $this->requireUser();

        if (in_array($user['role'], ['admin', 'owner'], true)) {
            redirect('/settings');
        }

        $this->render('delete', 'delete', [
            'pageTitle' => 'Удаление аккаунта',
            'user'      => $user,
            'errors'    => [],
        ]);
    }

    public function deleteAccount(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        // Админа и владельца удалить через UI нельзя — во избежание блокировки управления сайтом
        if (in_array($user['role'], ['admin', 'owner'], true)) {
            http_response_code(403);
            exit('Аккаунт администратора или владельца удалить нельзя.');
        }

        $confirm  = trim((string)($_POST['confirm_username'] ?? ''));
        $agree    = !empty($_POST['agree']);
        $password = (string)($_POST['password'] ?? '');

        $errors = [];

        if ($confirm !== $user['username']) {
            $errors['confirm'] = 'Имя пользователя введено неверно.';
        }
        if (!$agree) {
            $errors['agree'] = 'Нужно подтвердить, что действие необратимо.';
        }
        if (!password_verify($password, $user['password_hash'])) {
            $errors['password'] = 'Пароль неверный.';
        }

        if ($errors) {
            $this->render('delete', 'delete', [
                'pageTitle' => 'Удаление аккаунта',
                'user'      => $user,
                'errors'    => $errors,
            ]);
            return;
        }

        $db = Database::conn();
        $userId = (int)$user['id'];

        // Собираем картинки из его комментариев — их будем удалять с диска
        $stmt = $db->prepare(
            'SELECT ci.path FROM comment_images ci
             JOIN comments c ON c.id = ci.comment_id
             WHERE c.user_id = ?'
        );
        $stmt->execute([$userId]);
        $paths = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $db->beginTransaction();
        try {
            // Личные данные обнуляем
            $db->prepare(
                'UPDATE users
                 SET deleted_at = NOW(),
                     email = ?,
                     password_hash = "",
                     bio = NULL,
                     avatar_path = NULL
                 WHERE id = ?'
            )->execute([
                'deleted_' . $userId . '_' . time() . '@deleted.local',
                $userId,
            ]);

            // Чистим личные связи
            $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM bookmarks WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM article_visits WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM article_follows WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM article_unfollows WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM user_follows WHERE follower_id = ? OR followed_id = ?')
               ->execute([$userId, $userId]);
            $db->prepare('DELETE FROM article_restrictions WHERE user_id = ?')->execute([$userId]);
            $db->prepare('DELETE FROM email_changes WHERE user_id = ?')->execute([$userId]);

            // Картинки его комментариев убираем из БД
            $db->prepare(
                'DELETE ci FROM comment_images ci
                 JOIN comments c ON c.id = ci.comment_id
                 WHERE c.user_id = ?'
            )->execute([$userId]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $errors['general'] = 'Не удалось удалить аккаунт. Попробуйте позже.';
            $this->render('delete', 'delete', [
                'pageTitle' => 'Удаление аккаунта',
                'user'      => $user,
                'errors'    => $errors,
            ]);
            return;
        }

        // Файлы — после коммита
        foreach ($paths as $path) {
            $abs = __DIR__ . '/../../' . $path;
            if (is_file($abs)) @unlink($abs);
        }

        // Разлогиниваем
        \App\Auth::logout();

        redirect('/?deleted=1');
    }
	
	public function notificationsForm(): void
    {
        $user = $this->requireUser();
        $db = Database::conn();
        
        // Получаем настройки или создаем дефолтные
        $stmt = $db->prepare('SELECT * FROM user_settings WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $settings = $stmt->fetch();
        
        if (!$settings) {
            $db->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$user['id']]);
            $settings = ['notify_replies' => 1, 'notify_mentions' => 1, 'notify_new_articles' => 1];
        }

        $this->render('notifications', 'notifications', [
            'pageTitle' => 'Настройки уведомлений',
            'user' => $user,
            'settings' => $settings,
            'toast' => null,
        ]);
    }

    public function notificationsUpdate(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);

        $notifyReplies  = isset($_POST['notify_replies']) ? 1 : 0;
        $notifyMentions = isset($_POST['notify_mentions']) ? 1 : 0;
		$notifyNewArt   = isset($_POST['notify_new_articles']) ? 1 : 0;

        $db = Database::conn();
        $db->prepare(
            'INSERT INTO user_settings (user_id, notify_replies, notify_mentions, notify_new_articles)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE notify_replies = ?, notify_mentions = ?, notify_new_articles = ?'
        )->execute([$user['id'], $notifyReplies, $notifyMentions, $notifyNewArt, $notifyReplies, $notifyMentions, $notifyNewArt]);

        $this->render('notifications', 'notifications', [
            'pageTitle' => 'Настройки уведомлений',
            'user' => $user,
            'settings' => ['notify_replies' => $notifyReplies, 'notify_mentions' => $notifyMentions, 'notify_new_articles' => $notifyNewArt],
            'toast' => 'Настройки уведомлений сохранены',
        ]);
    }
}
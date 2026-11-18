<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Mailer;

class AuthController
{
    /* ─── Регистрация ─────────────────────────────────── */

    public function showRegister(): void
    {
		if (setting('registration_enabled', '1') === '0') {
            view('auth/register', [
                'pageTitle' => 'Регистрация закрыта',
                'errors'    => ['general' => 'Регистрация временно приостановлена администрацией.'],
                'old'       => []
            ]);
            return;
        }
		
        if (Auth::check()) redirect('/');
        view('auth/register', ['pageTitle' => 'Регистрация']);
    }

    public function register(): void
    {
		if (setting('registration_enabled', '1') === '0') {
            view('auth/register', [
                'pageTitle' => 'Регистрация закрыта',
                'errors'    => ['general' => 'Регистрация временно приостановлена администрацией.'],
                'old'       => []
            ]);
            return;
        }
		
        csrf_check($_POST['_csrf'] ?? null);

        $ip = client_ip();

        // 3 регистрации за час → блок на час
        $wait = \App\RateLimit::check('register_ip:' . $ip);
        if ($wait !== null) {
            view('auth/register', [
                'pageTitle' => 'Регистрация',
                'errors'    => ['general' => 'Слишком много регистраций с вашего адреса. ' . \App\RateLimit::humanWait($wait) . '.'],
                'old'       => [],
            ]);
            return;
        }

        $email    = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $errors['username'] = 'Имя: 3–30 символов — латиница, цифры, подчёркивание.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Пароль — минимум 8 символов.';
        }

        if (!$errors) {
            $stmt = Database::conn()->prepare(
                'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1'
            );
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $errors['general'] = 'Такой email или имя уже заняты.';
            }
        }

        if ($errors) {
            view('auth/register', [
                'pageTitle' => 'Регистрация',
                'errors'    => $errors,
                'old'       => ['email' => $email, 'username' => $username],
            ]);
            return;
        }

        $db = Database::conn();
        $db->prepare('INSERT INTO users (email, username, password_hash) VALUES (?, ?, ?)')
           ->execute([$email, $username, password_hash($password, PASSWORD_DEFAULT)]);

        $userId = (int)$db->lastInsertId();
        $token  = bin2hex(random_bytes(32));

        $db->prepare('INSERT INTO email_verifications (user_id, token, expires_at)
                      VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
           ->execute([$userId, $token]);

        $verifyUrl = rtrim(config('app.url'), '/') . '/verify?token=' . $token;
        try {
            \App\Mailer::sendVerification($email, $username, $verifyUrl);
        } catch (\Throwable $e) {
            // Логируем ошибку, но не рушим регистрацию
            error_log('Mail error: ' . $e->getMessage());
            
            // Отдаем страницу с сообщением
            view('auth/register', [
                'pageTitle' => 'Регистрация',
                'errors'    => ['general' => 'Аккаунт создан, но не удалось отправить письмо. Обратитесь к администратору.'],
                'old'       => ['email' => $email, 'username' => $username],
            ]);
            return;
        }
		
		// Регистрация успешна — фиксируем попытку
		\App\RateLimit::hit('register_ip:' . $ip, 3, 3600, 3600);
		
        redirect('/verify-pending');
    }

    public function showVerifyPending(): void
    {
        view('auth/verify-pending', ['pageTitle' => 'Проверьте почту']);
    }

    public function verify(): void
    {
        $token = $_GET['token'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            view('auth/verify-fail', ['pageTitle' => 'Подтверждение']);
            return;
        }

        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT user_id FROM email_verifications
             WHERE token = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            view('auth/verify-fail', ['pageTitle' => 'Подтверждение']);
            return;
        }

        $userId = (int)$row['user_id'];
        $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')
           ->execute([$userId]);
        $db->prepare('DELETE FROM email_verifications WHERE user_id = ?')
           ->execute([$userId]);

        view('auth/verify-success', ['pageTitle' => 'Email подтверждён']);
    }

    /* ─── Вход ────────────────────────────────────────── */

    public function showLogin(): void
    {
        if (Auth::check()) redirect('/');
        view('auth/login', ['pageTitle' => 'Вход']);
    }

    public function login(): void
    {
        csrf_check($_POST['_csrf'] ?? null);

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $fail = function (string $msg) use ($email): void {
            view('auth/login', [
                'pageTitle' => 'Вход',
                'error'     => $msg,
                'old'       => ['email' => $email],
            ]);
        };

        $ip     = client_ip();
        $ipKey  = 'login_ip:' . $ip;
        $emKey  = $email !== '' ? 'login_email:' . mb_strtolower($email) : null;

        // Проверка блокировок
        $waitIp = \App\RateLimit::check($ipKey);
        if ($waitIp !== null) {
            $fail('Слишком много попыток с вашего адреса. ' . \App\RateLimit::humanWait($waitIp) . '.');
            return;
        }
        if ($emKey !== null) {
            $waitEm = \App\RateLimit::check($emKey);
            if ($waitEm !== null) {
                $fail('Слишком много попыток входа. ' . \App\RateLimit::humanWait($waitEm) . '.');
                return;
            }
        }

        $stmt = Database::conn()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Неудачная попытка — считаем в обоих ключах
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // 10 попыток за 15 минут → блок 15 минут
            \App\RateLimit::hit($ipKey, 20, 900, 900);
            if ($emKey !== null) {
                \App\RateLimit::hit($emKey, 10, 900, 900);
            }
            $fail('Неверный email или пароль.');
            return;
        }

        if (!$user['email_verified_at']) {
            $fail('Email не подтверждён. Проверьте почту.');
            return;
        }
		
		if (!empty($user['banned_at'])) {
            $fail('Ваш аккаунт заблокирован администратором.');
            return;
        }

        if (!empty($user['deleted_at'])) {
            $fail('Этот аккаунт был удалён.');
            return;
        }

        // Успех — сбрасываем счётчик по email (не по IP — там могут быть другие)
        if ($emKey !== null) \App\RateLimit::clear($emKey);

        Auth::login((int)$user['id'], $remember);
        redirect('/');
    }

    public function logout(): void
    {
        csrf_check($_POST['_csrf'] ?? null);
        Auth::logout();
        redirect('/');
    }
	
	/* ─── Восстановление пароля ───────────────────────── */

    public function showForgotPassword(): void
    {
        view('auth/forgot-password', ['pageTitle' => 'Восстановление пароля']);
    }

    public function sendResetLink(): void
    {
        csrf_check($_POST['_csrf'] ?? null);
        $email = trim($_POST['email'] ?? '');

        // Rate limit: 3 попытки в 15 минут на IP
        $ip = client_ip();
        $rlKey = 'reset_pass_ip:' . $ip;
        $wait = \App\RateLimit::check($rlKey);
        if ($wait !== null) {
            view('auth/forgot-password', [
                'pageTitle' => 'Восстановление пароля',
                'error' => 'Слишком много попыток. ' . \App\RateLimit::humanWait($wait) . '.'
            ]);
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db = Database::conn();
            $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ? AND deleted_at IS NULL AND banned_at IS NULL LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
                   ->execute([$user['id'], $token]);

                $url = rtrim(config('app.url'), '/') . '/reset-password?token=' . $token;
                
                try {
                    \App\Mailer::sendPasswordReset($email, $user['username'], $url);
                } catch (\Throwable $e) {
                    error_log('Password reset mail error: ' . $e->getMessage());
                }
            }
        }

        \App\RateLimit::hit($rlKey, 3, 900, 900);
        
        // Всегда показываем успех, чтобы не раскрывать, есть ли такой email
        redirect('/forgot-password?sent=1');
    }

    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            redirect('/login');
        }

        $stmt = Database::conn()->prepare('SELECT id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$token]);
        if (!$stmt->fetch()) {
            view('auth/reset-password', [
                'pageTitle' => 'Сброс пароля',
                'error' => 'Ссылка недействительна или истекла.',
                'token' => ''
            ]);
            return;
        }

        view('auth/reset-password', ['pageTitle' => 'Сброс пароля', 'token' => $token]);
    }

    public function resetPassword(): void
    {
        csrf_check($_POST['_csrf'] ?? null);
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            redirect('/login');
        }

        $db = Database::conn();
        $stmt = $db->prepare('SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            view('auth/reset-password', [
                'pageTitle' => 'Сброс пароля',
                'error' => 'Ссылка недействительна или истекла.',
                'token' => ''
            ]);
            return;
        }

        if (strlen($password) < 8) {
            view('auth/reset-password', [
                'pageTitle' => 'Сброс пароля',
                'error' => 'Пароль должен быть не менее 8 символов.',
                'token' => $token
            ]);
            return;
        }

        if ($password !== $confirm) {
            view('auth/reset-password', [
                'pageTitle' => 'Сброс пароля',
                'error' => 'Пароли не совпадают.',
                'token' => $token
            ]);
            return;
        }

        $userId = (int)$reset['user_id'];

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            
            // Удаляем этот токен и все остальные для этого пользователя
            $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
            
            // Разлогиниваем со всех устройств на всякий случай
            $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$userId]);
            
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            view('auth/reset-password', [
                'pageTitle' => 'Сброс пароля',
                'error' => 'Ошибка базы данных. Попробуйте позже.',
                'token' => $token
            ]);
            return;
        }

        redirect('/login?reset=1');
    }
}
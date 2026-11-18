<?php
declare(strict_types=1);

namespace App;

class Mailer
{
    public static function send(string $email, string $subject, string $body): void
    {
        $driver = setting('mail_driver', 'log');

        if ($driver === 'log') {
            $dir = __DIR__ . '/../storage/logs';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $line = sprintf(
                "[%s] To: %s\nSubject: %s\n%s\n%s\n\n",
                date('Y-m-d H:i:s'), $email, $subject, str_repeat('-', 60), $body
            );
            file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
            return;
        }

        if ($driver === 'smtp') {
            self::sendSmtp($email, $subject, $body);
            return;
        }

        // Fallback для стандартной mail()
        $headers = 'From: ' . setting('smtp_from_email', 'no-reply@' . $_SERVER['HTTP_HOST']);
        @mail($email, $subject, $body, $headers);
    }

    private static function sendSmtp(string $to, string $subject, string $body): void
    {
        $host   = setting('smtp_host');
        $port   = (int)setting('smtp_port', 465);
        $user   = setting('smtp_user');
        $pass   = setting('smtp_pass');
        $secure = setting('smtp_secure', 'ssl'); // ssl или tls
        $from   = setting('smtp_from_email', $user);
        
        if (!$host || !$user || !$pass) {
            throw new \RuntimeException('SMTP не настроен.');
        }

        // Определяем префикс протокола
        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        
        if (!$socket) {
            throw new \RuntimeException("Не удалось подключиться к SMTP: $errstr ($errno)");
        }

        $read = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            return $data;
        };

        $write = function($cmd) use ($socket) {
            fwrite($socket, $cmd . "\r\n");
        };

        $read(); // Приветствие

        // STARTTLS (если выбран tls на порту 587)
        if ($secure === 'tls') {
            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $read();
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $read();
        } else {
            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $read();
        }

        // Авторизация
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $auth = $read();
        
        if (strpos($auth, '235') === false) {
            fclose($socket);
            throw new \RuntimeException('Ошибка авторизации SMTP: ' . $auth);
        }

        // Отправка
        $write('MAIL FROM: <' . $from . '>');
        $read();
        $write('RCPT TO: <' . $to . '>');
        $read();
        $write('DATA');
        $read();

        // Формируем заголовки
        $headers = "From: =?UTF-8?B?" . base64_encode(setting('site_name', 'Сайт')) . "?= <{$from}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $read();
        
        $write('QUIT');
        fclose($socket);
    }

    public static function sendVerification(string $email, string $username, string $url): void
    {
        $siteName = setting('site_name', 'Статьи');
        $subject = 'Подтверждение регистрации на ' . $siteName;
        $body = "Здравствуйте, {$username}!\n\n"
              . "Подтвердите email, перейдя по ссылке:\n{$url}\n\n"
              . "Ссылка действительна 24 часа.";

        self::send($email, $subject, $body);
    }

    public static function sendEmailChangeConfirmation(string $newEmail, string $username, string $url): void
    {
        $subject = 'Подтверждение смены email';
        $body = "Здравствуйте, {$username}!\n\n"
              . "Чтобы подтвердить смену email, перейдите по ссылке:\n{$url}\n\n"
              . "Ссылка действительна 24 часа.";
        self::send($newEmail, $subject, $body);
    }

    public static function sendEmailChangeNotice(string $oldEmail, string $username, string $newEmail): void
    {
        $subject = 'Запрос на смену email';
        $body = "Здравствуйте, {$username}!\n\n"
              . "Был запрошен перенос аккаунта на новый email: {$newEmail}\n\n"
              . "Если это были не вы — смените пароль.";
        self::send($oldEmail, $subject, $body);
    }
	
	public static function sendPasswordReset(string $email, string $username, string $url): void
    {
        $siteName = setting('site_name', 'Статьи');
        $subject = 'Восстановление пароля на ' . $siteName;
        $body = "Здравствуйте, {$username}!\n\n"
              . "Кто-то запросил сброс пароля для вашего аккаунта.\n\n"
              . "Если это были вы, перейдите по ссылке, чтобы задать новый пароль:\n{$url}\n\n"
              . "Ссылка действительна 1 час.\n\n"
              . "Если вы не запрашивали сброс, просто проигнорируйте это письмо.";
        self::send($email, $subject, $body);
    }
}
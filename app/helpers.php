<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/config.php';
    }
    $val = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($val) || !array_key_exists($part, $val)) return $default;
        $val = $val[$part];
    }
    return $val;
}

/** Экранирование для HTML */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): void
{
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

/** Flash-сообщения */
function flash(string $key, ?string $value = null)
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

/** Рендер вида с layout */
function view(string $name, array $data = []): void
{
    $file = __DIR__ . '/Views/' . $name . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        exit("View not found: {$name} ({$file})");
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $content = ob_get_clean();

    $layout = __DIR__ . '/Views/layout.php';
    if (!is_file($layout)) {
        exit('Layout not found');
    }
    require $layout;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Транслит + slug для URL */
function slugify(string $text): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
        'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e',
        'ю'=>'yu','я'=>'ya',
    ];
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'article-' . substr(md5(uniqid('', true)), 0, 8);
}

/** JSON-ответ */
function json_response(array $data, int $code = 200): void
{
    // Очищаем буфер вывода, если PHP успел что-то напечатать (warning, notice)
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * CSRF для AJAX — токен можно прислать заголовком X-CSRF-Token
 * (у обычных форм — поле _csrf в POST)
 */
function csrf_check_any(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        json_response(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
    }
}

/** Относительное время: "2 минуты назад" */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)          return 'только что';
    if ($diff < 3600)        return floor($diff / 60) . ' ' . plural((int)floor($diff / 60), 'минуту', 'минуты', 'минут') . ' назад';
    if ($diff < 86400)       return floor($diff / 3600) . ' ' . plural((int)floor($diff / 3600), 'час', 'часа', 'часов') . ' назад';
    if ($diff < 2592000)     return floor($diff / 86400) . ' ' . plural((int)floor($diff / 86400), 'день', 'дня', 'дней') . ' назад';
    return date('d.m.Y', strtotime($datetime));
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    if ($n > 10 && $n < 20) return $many;
    $n = $n % 10;
    if ($n === 1) return $one;
    if ($n >= 2 && $n <= 4) return $few;
    return $many;
}

function plural_ru(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    if ($n > 10 && $n < 20) return $many;
    $n = $n % 10;
    if ($n === 1) return $one;
    if ($n >= 2 && $n <= 4) return $few;
    return $many;
}

/** IP клиента. За обратным прокси смотрим X-Forwarded-For. */
function client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function renderPagination(int $page, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination pagination-profile">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? 'active' : '';
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $html .= "<a href=\"{$baseUrl}{$sep}page={$i}\" class=\"pagination-item {$active}\">{$i}</a>";
    }
    $html .= '</nav>';
    return $html;
}

function processImageUpload(array $file, string $prefix): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed)) return null;

    $info = @getimagesize($file['tmp_name']);
    if (!$info) return null;
    [$width, $height] = $info;

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        default      => null,
    };
    if (!$src) return null;

    // Вычисляем кроп 16:9
    $targetRatio = 16 / 9;
    if ($width / $height > $targetRatio) {
        $srcH = $height;
        $srcW = (int)($height * $targetRatio);
        $srcX = (int)(($width - $srcW) / 2);
        $srcY = 0;
    } else {
        $srcW = $width;
        $srcH = (int)($width / $targetRatio);
        $srcX = 0;
        $srcY = (int)(($height - $srcH) / 2);
    }

    // Создаем 480x270
    $dstW = 480;
    $dstH = 270;
    $dst = imagecreatetruecolor($dstW, $dstH);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);

    $dir = __DIR__ . '/../uploads/covers';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    
    $filename = $prefix . '_' . time() . '.jpg';
    $absPath = $dir . '/' . $filename;
    
    imagejpeg($dst, $absPath, 88);
    imagedestroy($src);
    imagedestroy($dst);

    return 'uploads/covers/' . $filename;
}

/** Получить настройку из БД (с кэшированием) */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = \App\Database::conn()->query('SELECT `key`, `value` FROM settings')->fetchAll();
            $cache = array_column($rows, 'value', 'key');
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}
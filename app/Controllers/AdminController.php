<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Markdown;

class AdminController
{
    private function requireAdmin(): void
    {
        $u = Auth::user();
        if (!$u || !in_array($u['role'], ['admin', 'owner'], true)) {
            http_response_code(403);
            exit('Доступ запрещён');
        }
    }

    private function render(string $view, string $tab, array $data = []): void
    {
        view('admin/layout', array_merge([
            'contentView' => $view,
            'activeTab'   => $tab,
        ], $data));
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $db = Database::conn();
        
        $stats = [
            'users' => $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'articles' => $db->query('SELECT COUNT(*) FROM articles WHERE published_at IS NOT NULL')->fetchColumn(),
            'comments' => $db->query('SELECT COUNT(*) FROM comments WHERE status = "visible"')->fetchColumn(),
            'reports' => $db->query('SELECT COUNT(*) FROM comment_reports WHERE status = "pending"')->fetchColumn(),
        ];

        $this->render('dashboard', 'dashboard', [
            'pageTitle' => 'Дашборд',
            'stats' => $stats,
        ]);
    }

    public function categories(): void
    {
        $this->requireAdmin();
        $db = Database::conn();

        // Получаем все категории
        $rows = $db->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
        
        // Строим дерево (2 уровня)
        $categories = [];
        $subcategories = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) $categories[] = $r;
            else $subcategories[$r['parent_id']][] = $r;
        }

        $this->render('categories', 'categories', [
            'pageTitle' => 'Управление разделами',
            'categories' => $categories,
            'subcategories' => $subcategories,
            'errors' => [],
        ]);
    }

    public function createCategory(): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);

        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($name === '') {
            $this->categories();
            return;
        }

        $db = Database::conn();
        
        if ($parentId !== null) {
            $stmt = $db->prepare('SELECT parent_id FROM categories WHERE id = ?');
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch();
            if ($parent === false || $parent['parent_id'] !== null) {
                exit('Нельзя создавать подразделы 3-го уровня.');
            }
        }

        $slug = $this->uniqueSlug(slugify($name), $parentId);
        
        // Обработка картинки
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = processImageUpload($_FILES['image'], 'cat');
        }

        $db->prepare('INSERT INTO categories (parent_id, name, slug, image_path) VALUES (?, ?, ?, ?)')
           ->execute([$parentId, $name, $slug, $imagePath]);

        redirect('/admin/categories');
    }

    public function updateCategory(string $id): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);
        
        $id = (int)$id;
        $name = trim($_POST['name'] ?? '');
        if ($name === '') redirect('/admin/categories');

        $db = Database::conn();
        
        // 1. Получаем старые данные
        $stmt = $db->prepare('SELECT image_path FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        $imagePath = $old['image_path'];

        // 2. Проверяем, не нажал ли пользователь "Удалить картинку"
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            if ($imagePath && is_file(__DIR__ . '/../../' . $imagePath)) {
                @unlink(__DIR__ . '/../../' . $imagePath);
            }
            $imagePath = null;
        } 
        // 3. Если загрузили новую картинку
        elseif (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $newImage = processImageUpload($_FILES['image'], 'cat');
            if ($newImage) {
                if ($imagePath && is_file(__DIR__ . '/../../' . $imagePath)) {
                    @unlink(__DIR__ . '/../../' . $imagePath);
                }
                $imagePath = $newImage;
            }
        }

        // 4. Генерируем новый slug
        $newSlug = slugify($name);
        $stmt = $db->prepare('SELECT id FROM categories WHERE slug = ? AND id != ?');
        $stmt->execute([$newSlug, $id]);
        if ($stmt->fetch()) {
            $newSlug .= '-' . rand(100, 999);
        }

        // 5. Сохраняем
        $db->prepare('UPDATE categories SET name = ?, slug = ?, image_path = ? WHERE id = ?')
           ->execute([$name, $newSlug, $imagePath, $id]);

        redirect('/admin/categories');
    }

    public function deleteCategory(string $id): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);
        
        $id = (int)$id;
        $db = Database::conn();

        // Проверка: есть ли статьи в этой категории?
        $stmt = $db->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            exit('Нельзя удалить раздел, в котором есть статьи. Сначала перенесите их.');
        }

        // Проверка: есть ли подразделы? (Защита от каскадного удаления)
        $stmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            exit('Нельзя удалить раздел, пока в нём есть подразделы.');
        }

        $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        redirect('/admin/categories');
    }

    /* ─── Жалобы (Перенесено в каркас) ─────────────────── */

    public function reports(): void
    {
        $this->requireAdmin();
        $db = Database::conn();

        // 1. Жалобы на комментарии
        $stmt = $db->query(
            'SELECT r.id, r.reason, r.note, r.created_at, "comment" as type,
                    c.id AS comment_id, c.body, c.status AS item_status,
                    a.slug AS article_slug,
                    u.username AS reporter, cu.username AS author
             FROM comment_reports r
             JOIN comments c ON c.id = r.comment_id
             JOIN articles a ON a.id = c.article_id
             JOIN users u ON u.id = r.user_id
             JOIN users cu ON cu.id = c.user_id
             WHERE r.status = "pending"'
        );
        $reports = $stmt->fetchAll();

        // 2. Жалобы на сообщения
        $stmt = $db->query(
            'SELECT r.id, r.reason, NULL as note, r.created_at, "message" as type,
                    m.id AS message_id, m.body, "visible" as item_status,
                    u.username AS reporter, su.username AS author
             FROM message_reports r
             JOIN messages m ON m.id = r.message_id
             JOIN users u ON u.id = r.user_id
             JOIN users su ON su.id = m.sender_id
             WHERE r.status = "pending"'
        );
        $msgReports = $stmt->fetchAll();

        // Объединяем и сортируем по дате
        $allReports = array_merge($reports, $msgReports);
        usort($allReports, fn($a, $b) => strtotime($a['created_at']) - strtotime($b['created_at']));

        foreach ($allReports as &$r) {
            $r['body_html'] = Markdown::toHtml($r['body']);
        }

        $this->render('reports', 'reports', [
            'pageTitle' => 'Жалобы',
            'reports'   => $allReports,
        ]);
    }

    public function resolveReport(string $id): void
    {
        $this->requireAdmin();
        csrf_check_any();
        $user = Auth::user();

        $reportId = (int)$id;
        $type     = $_POST['type'] ?? 'comment'; // 'comment' или 'message'
        $action   = (string)($_POST['action'] ?? ''); // 'delete' | 'dismiss'

        if (!in_array($action, ['delete', 'dismiss'], true)) json_response(['ok' => false], 400);

        $db = Database::conn();
        $table = $type === 'message' ? 'message_reports' : 'comment_reports';
        $itemTable = $type === 'message' ? 'messages' : 'comments';

        $stmt = $db->prepare("SELECT " . ($type === 'message' ? 'message_id' : 'comment_id') . " as item_id FROM $table WHERE id = ? AND status = 'pending'");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        if (!$report) json_response(['ok' => false], 404);

        $itemId = (int)$report['item_id'];
        $newStatus = $action === 'delete' ? 'resolved' : 'dismissed';

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE $table SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?")
               ->execute([$newStatus, $user['id'], $reportId]);
            
            if ($action === 'delete') {
                if ($type === 'message') {
                    // Удаляем сообщение из БД
                    $db->prepare("DELETE FROM messages WHERE id = ?")->execute([$itemId]);
                } else {
                    // Мягко удаляем комментарий
                    $db->prepare('UPDATE comments SET status = "deleted", body = "" WHERE id = ?')->execute([$itemId]);
                }
                // Закрываем все остальные жалобы на этот объект
                $db->prepare("UPDATE $table SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE " . ($type === 'message' ? 'message_id' : 'comment_id') . " = ? AND status = 'pending'")
                   ->execute([$user['id'], $itemId]);
            }
            $db->commit();
        } catch (\Throwable) {
            $db->rollBack();
            json_response(['ok' => false], 500);
        }
		
        // Считаем, сколько жалоб осталось (после всех действий)
        $stmt = $db->query(
            'SELECT (SELECT COUNT(*) FROM comment_reports WHERE status="pending") + 
                    (SELECT COUNT(*) FROM message_reports WHERE status="pending") AS total'
        );
        $remaining = (int)$stmt->fetchColumn();

        json_response(['ok' => true, 'remaining' => $remaining]);
    }

    private function uniqueSlug(string $base, ?int $parentId): string
    {
        $slug = $base;
        $i = 1;
        $db = Database::conn();
        // В идеале slug должен быть уникальным в рамках родителя, но для простоты делаем глобально уникальным
        $stmt = $db->prepare('SELECT 1 FROM categories WHERE slug = ?');
        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) return $slug;
            $slug = $base . '-' . (++$i);
        }
    }
	
	private function processImageUpload(array $file, string $prefix): ?string
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
            // Слишком широкое
            $srcH = $height;
            $srcW = (int)($height * $targetRatio);
            $srcX = (int)(($width - $srcW) / 2);
            $srcY = 0;
        } else {
            // Слишком высокое
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

        $dir = __DIR__ . '/../../uploads/covers';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        
        $filename = $prefix . '_' . time() . '.jpg';
        $absPath = $dir . '/' . $filename;
        
        imagejpeg($dst, $absPath, 88);
        imagedestroy($src);
        imagedestroy($dst);

        return 'uploads/covers/' . $filename;
    }
	
	/* ─── Управление пользователями ───────────────────── */

    public function users(): void
    {
        $this->requireAdmin();
        $db = Database::conn();

        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($q !== '') {
            $where .= ' AND (username LIKE ? OR email LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        // Считаем общее количество для пагинации
        $stmt = $db->prepare("SELECT COUNT(*) FROM users $where");
        $stmt->execute($params);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / $limit);

        // Получаем список
        $sql = "SELECT id, username, email, role, avatar_path, created_at, banned_at 
                FROM users $where 
                ORDER BY id DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $this->render('users', 'users', [
            'pageTitle' => 'Пользователи',
            'users' => $users,
            'query' => $q,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
			'currentRole' => Auth::user()['role'],
        ]);
    }

    public function updateUserRole(string $id): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);
        
        $id = (int)$id;
        $role = $_POST['role'] ?? 'user';
        $currentUser = Auth::user();

        if ($id === (int)$currentUser['id']) {
            exit('Вы не можете изменить свою собственную роль.');
        }

        // Получаем того, кого пытаемся изменить
        $stmt = Database::conn()->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) exit('Пользователь не найден.');

        // ЗАЩИТА: Трогать владельца может только сам владелец (но себя менять нельзя, значит никто)
        if ($target['role'] === 'owner') {
            exit('Нельзя изменить роль владельца сайта.');
        }

        // ЗАЩИТА: Обычный админ не может менять роли других админов
        if ($currentUser['role'] === 'admin' && $target['role'] === 'admin') {
            exit('Вы не можете изменить роль другого администратора.');
        }

        if (!in_array($role, ['user', 'admin'], true)) {
            exit('Некорректная роль.');
        }

        Database::conn()->prepare('UPDATE users SET role = ? WHERE id = ?')
            ->execute([$role, $id]);

        redirect('/admin/users');
    }

    public function toggleUserBan(string $id): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);
        
        $id = (int)$id;
        $action = $_POST['action'] ?? '';
        $currentUser = Auth::user();

        if ($id === (int)$currentUser['id']) {
            exit('Вы не можете забанить самого себя.');
        }

        // Получаем того, кого пытаемся забанить
        $stmt = Database::conn()->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) exit('Пользователь не найден.');

        // ЗАЩИТА: Владельца нельзя забанить
        if ($target['role'] === 'owner') {
            exit('Нельзя забанить владельца сайта.');
        }

        // ЗАЩИТА: Обычный админ не может банить другого админа
        if ($currentUser['role'] === 'admin' && $target['role'] === 'admin') {
            exit('Вы не можете забанить другого администратора.');
        }

        $db = Database::conn();

        if ($action === 'ban') {
            $db->prepare('UPDATE users SET banned_at = NOW() WHERE id = ?')->execute([$id]);
        } else {
            $db->prepare('UPDATE users SET banned_at = NULL WHERE id = ?')->execute([$id]);
        }

        redirect('/admin/users');
    }
	
	public function deleteUser(string $id): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);
        
        $id = (int)$id;
        $currentUser = Auth::user();

        // 1. Нельзя удалить себя
        if ($id === (int)$currentUser['id']) {
            exit('Вы не можете удалить свой собственный аккаунт.');
        }

        // 2. Получаем того, кого пытаемся удалить
        $stmt = Database::conn()->prepare('SELECT id, role, email FROM users WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) exit('Пользователь не найден или уже удален.');

        // 3. Защита: только владелец может удалять пользователей
        if ($currentUser['role'] !== 'owner') {
            exit('Только владелец сайта может удалять пользователей.');
        }

        // 4. Защита: нельзя удалить другого владельца
        if ($target['role'] === 'owner') {
            exit('Нельзя удалить владельца сайта.');
        }

        $db = Database::conn();

        // Собираем картинки из его комментариев
        $stmt = $db->prepare(
            'SELECT ci.path FROM comment_images ci
             JOIN comments c ON c.id = ci.comment_id
             WHERE c.user_id = ?'
        );
        $stmt->execute([$id]);
        $paths = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $db->beginTransaction();
        try {
            // Анонимизация пользователя
            $db->prepare(
                'UPDATE users
                 SET deleted_at = NOW(),
                     email = ?,
                     password_hash = "",
                     bio = NULL,
                     avatar_path = NULL,
                     banned_at = NULL
                 WHERE id = ?'
            )->execute([
                'deleted_' . $id . '_' . time() . '@deleted.local',
                $id,
            ]);

            // Чистим личные связи
            $db->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM bookmarks WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM article_visits WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM article_follows WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM article_unfollows WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM user_follows WHERE follower_id = ? OR followed_id = ?')->execute([$id, $id]);
            $db->prepare('DELETE FROM article_restrictions WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM email_changes WHERE user_id = ?')->execute([$id]);

            // Убираем картинки из его комментариев
            $db->prepare(
                'DELETE ci FROM comment_images ci
                 JOIN comments c ON c.id = ci.comment_id
                 WHERE c.user_id = ?'
            )->execute([$id]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            exit('Ошибка при удалении пользователя.');
        }

        // Файлы удаляем после коммита
        foreach ($paths as $path) {
            $abs = __DIR__ . '/../../' . $path;
            if (is_file($abs)) @unlink($abs);
        }

        redirect('/admin/users');
    }
	
	    /* ─── Настройки сайта ─────────────────────────────── */

    public function settings(): void
    {
        $this->requireAdmin();
        $db = Database::conn();
        
        $rows = $db->query('SELECT `key`, `value` FROM settings')->fetchAll();
        $settings = array_column($rows, 'value', 'key');

        $this->render('settings', 'settings', [
            'pageTitle' => 'Настройки сайта',
            'settings'  => $settings,
            'toast'     => null,
        ]);
    }

    public function settingsUpdate(): void
    {
        $this->requireAdmin();
        csrf_check($_POST['_csrf'] ?? null);

        $db = Database::conn();
        $stmt = $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?');

        // 1. Сохраняем текстовые поля
        $keys = [
            'site_name', 'site_description', 'home_welcome_text',
            'mail_driver', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from_email'
        ];
        
        foreach ($keys as $key) {
            $val = trim((string)($_POST[$key] ?? ''));
            $stmt->execute([$key, $val, $val]);
        }

        // 2. Сохраняем чекбокс регистрации (если не отмечен, придет пустота)
        $regEnabled = isset($_POST['registration_enabled']) ? '1' : '0';
        $stmt->execute(['registration_enabled', $regEnabled, $regEnabled]);

        $rows = $db->query('SELECT `key`, `value` FROM settings')->fetchAll();
        $settings = array_column($rows, 'value', 'key');

        $this->render('settings', 'settings', [
            'pageTitle' => 'Настройки сайта',
            'settings'  => $settings,
            'toast'     => 'Настройки сохранены',
        ]);
    }
	
	/* ─── Предложения разделов ─────────────────────────── */

    public function suggestions(): void
    {
        $this->requireAdmin();
        $db = Database::conn();

        // Достаем все pending-предложения + кол-во статей, которые их ждут
        $stmt = $db->query(
            'SELECT s.*, u.username AS author, p.name AS parent_name,
                    (SELECT COUNT(*) FROM articles a WHERE a.suggestion_id = s.id) AS articles_count
             FROM category_suggestions s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN categories p ON p.id = s.parent_id
             WHERE s.status = "pending"
             ORDER BY s.created_at ASC'
        );
        $suggestions = $stmt->fetchAll();

        $this->render('suggestions', 'suggestions', [
            'pageTitle' => 'Предложения разделов',
            'suggestions' => $suggestions,
        ]);
    }

    public function approveSuggestion(string $id): void
    {
        $this->requireAdmin();
        csrf_check_any();
        $user = Auth::user();

        $id = (int)$id;
        $db = Database::conn();

        // 1. Достаем предложение
        $stmt = $db->prepare('SELECT * FROM category_suggestions WHERE id = ? AND status = "pending"');
        $stmt->execute([$id]);
        $suggestion = $stmt->fetch();

        if (!$suggestion) {
            json_response(['ok' => false, 'error' => 'Предложение не найдено или уже обработано'], 404);
        }

        $db->beginTransaction();
        try {
            // 2. Создаем категорию (генерируем slug)
            $baseSlug = slugify($suggestion['name']);
            $slug = $baseSlug;
            $i = 1;
            $stmtCheck = $db->prepare('SELECT 1 FROM categories WHERE slug = ?');
            while (true) {
                $stmtCheck->execute([$slug]);
                if (!$stmtCheck->fetch()) break;
                $slug = $baseSlug . '-' . (++$i);
            }

            $db->prepare(
                'INSERT INTO categories (parent_id, name, slug) VALUES (?, ?, ?)'
            )->execute([$suggestion['parent_id'], $suggestion['name'], $slug]);

            $newCategoryId = (int)$db->lastInsertId();

            // 3. Привязываем статьи к новой категории
            // Мы берем все статьи, которые были созданы под это предложение
            $db->prepare(
                'UPDATE articles SET category_id = ?, suggestion_id = NULL 
                 WHERE suggestion_id = ?'
            )->execute([$newCategoryId, $id]);

            // 4. Обновляем статус предложения
            $db->prepare(
                'UPDATE category_suggestions SET status = "approved", resolved_at = NOW(), resolved_by = ? WHERE id = ?'
            )->execute([$user['id'], $id]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Ошибка при создании категории'], 500);
        }

        json_response(['ok' => true, 'category_id' => $newCategoryId]);
    }

    public function rejectSuggestion(string $id): void
    {
        $this->requireAdmin();
        csrf_check_any();
        $user = Auth::user();

        $id = (int)$id;
        $reason = trim($_POST['reason'] ?? '');

        $db = Database::conn();
        
        $stmt = $db->prepare('SELECT id FROM category_suggestions WHERE id = ? AND status = "pending"');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Предложение не найдено'], 404);
        }

        $db->prepare(
            'UPDATE category_suggestions 
             SET status = "rejected", admin_comment = ?, resolved_at = NOW(), resolved_by = ? 
             WHERE id = ?'
        )->execute([$reason, $user['id'], $id]);

        json_response(['ok' => true]);
    }
}
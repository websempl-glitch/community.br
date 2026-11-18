<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Markdown;

class ArticleController
{
    public function index(): void
    {
        $db = Database::conn();
        $viewer = Auth::user();
        $feed = $_GET['feed'] ?? 'all';
        $feedNewCount = 0;

        // Считаем новые статьи в подписках
        if ($viewer) {
            $stmt = $db->prepare(
                'SELECT COUNT(*)
                 FROM articles a
                 WHERE a.published_at > COALESCE((SELECT feed_last_seen_at FROM users WHERE id = ?), "1970-01-01 00:00:00")
                   AND a.author_id IN (SELECT followed_id FROM user_follows WHERE follower_id = ?)'
            );
            $stmt->execute([$viewer['id'], $viewer['id']]);
            $feedNewCount = (int)$stmt->fetchColumn();

            // Если пользователь зашел на вкладку "Мои подписки", сбрасываем счетчик
            if ($feed === 'subscriptions') {
                $db->prepare('UPDATE users SET feed_last_seen_at = ? WHERE id = ?')
                   ->execute([date('Y-m-d H:i:s'), $viewer['id']]);
                $feedNewCount = 0;
            }
        }

        // 1. Последние статьи
        $sql = 'SELECT a.id, a.slug, a.title, a.excerpt, a.published_at, a.views, a.pinned_global, a.pinned_category,
                       COALESCE(a.cover_image, c.image_path) AS display_image,
                       c.name AS category_name, c.slug AS category_slug,
                       c_parent.name AS parent_category_name, c_parent.slug AS parent_category_slug,
                       u.username AS author, u.deleted_at AS author_deleted_at,
                       (SELECT COUNT(*) FROM comments c2 WHERE c2.article_id = a.id AND c2.status = "visible") AS comments_count
                FROM articles a
                JOIN users u ON u.id = a.author_id
                LEFT JOIN categories c ON c.id = a.category_id
                LEFT JOIN categories c_parent ON c_parent.id = c.parent_id
                WHERE a.published_at IS NOT NULL';

        $params = [];
        if ($feed === 'subscriptions' && $viewer) {
            $sql .= ' AND a.author_id IN (SELECT followed_id FROM user_follows WHERE follower_id = ?)';
            $params[] = $viewer['id'];
        }

        $sql .= ' ORDER BY a.pinned_global DESC, a.published_at DESC LIMIT 30';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();

        // 2. Топ обсуждаемых
        $discussed = $db->query(
            'SELECT a.slug, a.title, a.views, a.published_at, COUNT(c.id) AS comments_count
             FROM articles a
             JOIN comments c ON c.article_id = a.id AND c.status = "visible"
             WHERE a.published_at IS NOT NULL
             GROUP BY a.id, a.slug, a.title, a.views, a.published_at
             HAVING comments_count > 0
             ORDER BY comments_count DESC, a.published_at DESC
             LIMIT 5'
        )->fetchAll();

        // 3. Дерево разделов
        $categories = $db->query('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
        $subRows = $db->query('SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC')->fetchAll();
        $subcategories = [];
        foreach ($subRows as $r) $subcategories[(int)$r['parent_id']][] = $r;

        view('articles/index', [
            'pageTitle'     => 'Статьи',
            'articles'      => $articles,
            'discussed'     => $discussed,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'feed'          => $feed,
            'feedNewCount'  => $feedNewCount,
        ]);
    }

    public function show(string $slug): void
    {
        $stmt = Database::conn()->prepare(
            'SELECT a.*, u.username AS author, u.deleted_at AS author_deleted_at
             FROM articles a
             JOIN users u ON u.id = a.author_id
             WHERE a.slug = ?
             LIMIT 1'
        );
        $stmt->execute([$slug]);
        $article = $stmt->fetch();

        $viewer = Auth::user();

        if (!$article) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        // Черновик — только автор и админ
        if ($article['published_at'] === null) {
            $isOwner = $viewer && (
                $viewer['role'] === 'admin'
                || (int)$viewer['id'] === (int)$article['author_id']
            );
            if (!$isOwner) {
                http_response_code(404);
                view('errors/404', ['pageTitle' => '404']);
                return;
            }
        }
		
		// Статистика автора для карточки
        $authorStats = $this->getAuthorStats((int)$article['author_id']);
		
		// Считаем просмотры (1 раз за сессию)
        if (empty($_SESSION['viewed_article_' . $article['id']])) {
            Database::conn()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')
                ->execute([(int)$article['id']]);
            $article['views'] = (int)$article['views'] + 1; // Обновляем для текущего вывода
            $_SESSION['viewed_article_' . $article['id']] = true;
        }
		
        $canModerate  = $this->canModerateArticle($article);
        $canEdit      = $canModerate;

        // Комментарии открыты = статья опубликована И разрешены
        $commentsOpen = ($article['published_at'] !== null)
                     && ((int)$article['comments_enabled'] === 1);

        $commentsClosedReason = null;
        if (!$commentsOpen) {
            $commentsClosedReason = ($article['published_at'] === null) ? 'draft' : 'disabled';
        }

        $isSubscribed = false;
        $isBookmarked = false;
        $newCount     = 0;
        $newItems     = [];
        $serverNow    = date('Y-m-d H:i:s');

        $lastSeenAt = null;
        if ($viewer) {
            $userId    = (int)$viewer['id'];
            $articleId = (int)$article['id'];

            $this->ensureVisit($userId, $articleId);
            $isSubscribed = $this->isSubscribed($userId, $articleId);
            $isBookmarked = $this->isBookmarked($userId, $articleId);
            [$newCount, $newItems] = $this->getNewItems($userId, $articleId);

            // Получаем время последнего визита из БД
            $stmt = Database::conn()->prepare('SELECT last_seen_at FROM article_visits WHERE user_id = ? AND article_id = ? LIMIT 1');
            $stmt->execute([$userId, $articleId]);
            $lastSeenAt = $stmt->fetchColumn();
        }
		
		$breadcrumbs = [];
        if ($article['category_id']) {
            $stmt = Database::conn()->prepare('SELECT c1.name AS parent_name, c1.slug AS parent_slug, c2.name AS sub_name, c2.slug AS sub_slug 
                FROM categories c1 
                LEFT JOIN categories c2 ON c2.parent_id = c1.id AND c2.id = ? 
                WHERE c1.id = (SELECT COALESCE(parent_id, id) FROM categories WHERE id = ?)
            ');
            // Сложный запрос, сделаем проще:
            $stmt = Database::conn()->prepare('SELECT id, parent_id, name, slug FROM categories WHERE id = ?');
            $stmt->execute([$article['category_id']]);
            $cat = $stmt->fetch();
            if ($cat) {
                if ($cat['parent_id'] !== null) {
                    $stmtParent = Database::conn()->prepare('SELECT name, slug FROM categories WHERE id = ?');
                    $stmtParent->execute([$cat['parent_id']]);
                    $parent = $stmtParent->fetch();
                    $breadcrumbs[] = ['name' => $parent['name'], 'slug' => $parent['slug'], 'url' => '/category/' . $parent['slug']];
                    $breadcrumbs[] = ['name' => $cat['name'], 'slug' => $cat['slug'], 'url' => '/category/' . $parent['slug'] . '/' . $cat['slug']];
                } else {
                    $breadcrumbs[] = ['name' => $cat['name'], 'slug' => $cat['slug'], 'url' => '/category/' . $cat['slug']];
                }
            }
        }

        view('articles/show', [
			'breadcrumbs'          => $breadcrumbs,
            'pageTitle'            => $article['title'],
            'article'              => $article,
            'bodyHtml'             => Markdown::toHtml($article['body']),
			'lastSeenAt'           => $lastSeenAt,
            'canModerate'          => $canModerate,
            'canEdit'              => $canEdit,
            'commentsOpen'         => $commentsOpen,
            'commentsClosedReason' => $commentsClosedReason,
			'authorStats'          => $authorStats,
            'isSubscribed'         => $isSubscribed,
            'isBookmarked'         => $isBookmarked,
            'newCount'             => $newCount,
            'newItems'             => $newItems,
            'serverNow'            => $serverNow,
			'canPin'               => \App\Auth::check() && in_array(\App\Auth::user()['role'], ['admin', 'owner']),
        ]);
    }

    public function create(): void
    {
        $this->requireUser();
        [$categories, $subcategories] = $this->getCategoriesTree();
        view('articles/create', [
            'pageTitle' => 'Новая статья',
			'old' => [],
			'errors' => [],
            'categories' => $categories,
			'subcategories' => $subcategories,
        ]);
    }
	
	public function markdownHelp(): void
    {
        view('articles/markdown-help', [
            'pageTitle' => 'Как писать статьи',
        ]);
    }

    public function store(): void
    {
        $user = $this->requireUser();
        csrf_check($_POST['_csrf'] ?? null);
		
		$errors = [];

        // Rate limit: 3 в час, 10 в день
        $hourKey = 'article_user_hour:' . (int)$user['id'];
        $dayKey  = 'article_user_day:'  . (int)$user['id'];

        $waitH = \App\RateLimit::check($hourKey);
        if ($waitH !== null) {
            view('articles/create', [
                'pageTitle' => 'Новая статья',
                'errors'    => ['general' => 'Слишком много статей. ' . \App\RateLimit::humanWait($waitH) . '.'],
                'old'       => ['title' => trim($_POST['title'] ?? ''), 'excerpt' => trim($_POST['excerpt'] ?? ''), 'body' => (string)($_POST['body'] ?? ''), 'publish' => isset($_POST['publish'])],
            ]);
            return;
        }
        $waitD = \App\RateLimit::check($dayKey);
        if ($waitD !== null) {
            view('articles/create', [
                'pageTitle' => 'Новая статья',
                'errors'    => ['general' => 'Достигнут дневной лимит публикаций. ' . \App\RateLimit::humanWait($waitD) . '.'],
                'old'       => ['title' => trim($_POST['title'] ?? ''), 'excerpt' => trim($_POST['excerpt'] ?? ''), 'body' => (string)($_POST['body'] ?? ''), 'publish' => isset($_POST['publish'])],
            ]);
            return;
        }

        $title   = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $body    = trim(str_replace(["\r\n", "\r"], "\n", $_POST['body'] ?? ''));
        $publish = isset($_POST['publish']);
        $commentsEnabled = $publish
            ? (isset($_POST['comments_enabled']) ? 1 : 0)
            : 1;
			
		// Лоика предложения раздела ---
        $suggestName = trim($_POST['suggest_name'] ?? '');
        $suggestParentId = !empty($_POST['suggest_parent_id']) ? (int)$_POST['suggest_parent_id'] : null;
        $suggestDesc = trim($_POST['suggest_desc'] ?? '');
        $suggestionId = null;
        $isSuggesting = ($suggestName !== '');

        if ($isSuggesting) {
            $db = Database::conn();
            
            // 1. Проверка лимита (макс 3 активных предложения)
            $stmt = $db->prepare('SELECT COUNT(*) FROM category_suggestions WHERE user_id = ? AND status = "pending"');
            $stmt->execute([$user['id']]);
            if ((int)$stmt->fetchColumn() >= 3) {
                $errors['general'] = 'У вас уже 3 активных предложения. Дождитесь их рассмотрения.';
            }

            // 2. Проверка на дубликат
            $stmt = $db->prepare('SELECT COUNT(*) FROM category_suggestions WHERE name = ? AND parent_id <=> ? AND status = "pending"');
            $stmt->execute([$suggestName, $suggestParentId]);
            $duplicates = (int)$stmt->fetchColumn();
            
            if ($duplicates > 0) {
                $errors['general'] = "Раздел «{$suggestName}» уже предложен. Сейчас его ждут {$duplicates} чел.";
            }
			
			// 2.5. Проверка: может, такой раздел уже создан?
            if ($suggestParentId === null) {
                // Если предлагают корневой раздел — ищем только среди корневых
                $stmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE name = ? AND parent_id IS NULL');
                $stmt->execute([$suggestName]);
            } else {
                // Если предлагают подраздел — ищем внутри конкретного родителя
                $stmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE name = ? AND parent_id = ?');
                $stmt->execute([$suggestName, $suggestParentId]);
            }
            
            if ((int)$stmt->fetchColumn() > 0) {
                $errors['general'] = "Раздел «{$suggestName}» уже существует. Выберите его из списка при создании статьи.";
            }

            // 3. Создаем предложение
            if (empty($errors)) {
                $db->prepare('INSERT INTO category_suggestions (user_id, parent_id, name, description) VALUES (?, ?, ?, ?)')
                   ->execute([$user['id'], $suggestParentId, $suggestName, $suggestDesc]);
                $suggestionId = (int)$db->lastInsertId();
                
                // Статья автоматически уходит в черновики
                $publish = false;
            }
        }
			
		$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $subId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $finalCategoryId = $subId ?: $parentId;

        if (mb_strlen($title) < 3) $errors['title'] = 'Заголовок — минимум 3 символа.';
        if (mb_strlen($body) < 10) $errors['body']  = 'Текст статьи пустой.';

        if ($errors) {
            // Возврат формы с ошибками (нужно передать категории снова)
            [$categories, $subcategories] = $this->getCategoriesTree();
            view('articles/create', [
                'pageTitle' => 'Новая статья',
                'errors'    => $errors,
                'old'       => array_merge($_POST, ['publish' => $publish]),
                'categories' => $categories,
                'subcategories' => $subcategories,
            ]);
            return;
        }

        $db = Database::conn();
        $slug = $this->uniqueSlug(slugify($title));
		
		// Обработка обложки
        $coverImage = null;
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverImage = processImageUpload($_FILES['cover'], 'art');
        }
		
        $db->prepare(
            'INSERT INTO articles (slug, title, excerpt, body, author_id, category_id, suggestion_id, cover_image, published_at, comments_enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $slug,
			$title,
			$excerpt !== '' ? $excerpt : null,
			$body,
            (int)$user['id'],
			$isSuggesting ? null : $finalCategoryId,
			$suggestionId,
            $coverImage,
			$publish ? date('Y-m-d H:i:s') : null,
			$commentsEnabled
        ]);
		
		// 1. Забираем ID статьи
        $newArticleId = (int)$db->lastInsertId();
		
		// 2. Проверяем достижения
        \App\Achievement::check((int)$user['id']);

        // Фиксируем rate limit
        \App\RateLimit::hit($hourKey, 3,  3600,  3600);
        \App\RateLimit::hit($dayKey,  10, 86400, 3600);
		
		 // 4. Автоподписка и уведомления
        if ($publish && $newArticleId > 0) {
            $this->autoSubscribeAuthor((int)$user['id'], $newArticleId);
            $this->notifySubscribers((int)$user['id'], $newArticleId);
        }
		
		if ($isSuggesting) {
            // Если предложили раздел — редирект в черновики с сообщением
            flash('draft_created_with_suggestion', 'Статья сохранена в черновики. Раздел отправлен на проверку.');
            redirect('/u/' . $user['username'] . '/drafts');
        }

        redirect('/article/' . $slug);
    }
	
    /* ─── Редактирование статьи ───────────────────────── */

    public function edit(string $slug): void
    {
        $article = $this->findArticleBySlug($slug);
        if (!$article) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        if (!$this->canModerateArticle($article)) {
            http_response_code(403);
            exit('Редактировать статью может только автор или администратор');
        }
		
		[$categories, $subcategories] = $this->getCategoriesTree();
        
        // Определяем, что выбрано сейчас (раздел или подраздел)
        $currentParentId = null;
        $currentSubId = null;
        
        if ($article['category_id']) {
            $stmt = Database::conn()->prepare('SELECT id, parent_id FROM categories WHERE id = ?');
            $stmt->execute([$article['category_id']]);
            $cat = $stmt->fetch();
            if ($cat) {
                if ($cat['parent_id'] === null) {
                    $currentParentId = $cat['id']; // Это корневой раздел
                } else {
                    $currentParentId = $cat['parent_id']; // Это подраздел, запоминаем родителя
                    $currentSubId = $cat['id'];
                }
            }
        }

        view('articles/edit', [
            'pageTitle' => 'Редактирование: ' . $article['title'],
            'article'   => $article,
            'errors'    => [],
            'categories' => $categories,
            'subcategories' => $subcategories,
            'currentParentId' => $currentParentId,
            'currentSubId' => $currentSubId,
        ]);
    }

    public function update(string $slug): void
    {
        $article = $this->findArticleBySlug($slug);
        if (!$article) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        if (!$this->canModerateArticle($article)) {
            http_response_code(403);
            exit('Редактировать статью может только автор или администратор');
        }

        csrf_check($_POST['_csrf'] ?? null);

        $title   = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $body    = trim(str_replace(["\r\n", "\r"], "\n", $_POST['body'] ?? ''));
        $publish = isset($_POST['publish']);
		$commentsEnabled = $publish
            ? (isset($_POST['comments_enabled']) ? 1 : 0)
            : (int)$article['comments_enabled']; // черновик — не меняем
		
		
		$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $subId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $finalCategoryId = $subId ?: $parentId;
		
		// Обработка обложки
        $coverImage = $article['cover_image'];
        
        // Если нажали "удалить"
        if (isset($_POST['delete_cover']) && $_POST['delete_cover'] === '1') {
            if ($coverImage && is_file(__DIR__ . '/../../' . $coverImage)) {
                @unlink(__DIR__ . '/../../' . $coverImage);
            }
            $coverImage = null;
        } 
        // Иначе, если загрузили новую
        elseif (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $newImage = processImageUpload($_FILES['cover'], 'art');
            if ($newImage) {
                if ($coverImage && is_file(__DIR__ . '/../../' . $coverImage)) {
                    @unlink(__DIR__ . '/../../' . $coverImage);
                }
                $coverImage = $newImage;
            }
        }
		
        $errors = [];
        if (mb_strlen($title) < 3) $errors['title'] = 'Заголовок — минимум 3 символа.';
        if (mb_strlen($body) < 10) $errors['body']  = 'Текст статьи пустой.';

        if ($errors) {
            view('articles/edit', [
                'pageTitle' => 'Редактирование: ' . $article['title'],
                'article'   => array_merge($article, [
                    'title'   => $title,
                    'excerpt' => $excerpt,
                    'body'    => $body,
                ]),
                'errors'    => $errors,
            ]);
            return;
        }

        // Slug НЕ меняется. published_at устанавливается только при первой публикации.
        $wasPublished   = $article['published_at'] !== null;
        $newPublishedAt = $article['published_at'];

        if ($publish && $newPublishedAt === null) {
            $newPublishedAt = date('Y-m-d H:i:s');
        } elseif (!$publish) {
            $newPublishedAt = null;
        }

        Database::conn()->prepare(
            'UPDATE articles SET title = ?, excerpt = ?, body = ?, published_at = ?, comments_enabled = ?, category_id = ?, cover_image = ? WHERE id = ?'
        )->execute([
            $title,
			$excerpt !== '' ? $excerpt : null,
			$body, $newPublishedAt, 
            $commentsEnabled,
			$finalCategoryId,
			$coverImage,
			(int)$article['id']
        ]);
		
		\App\Achievement::check((int)$article['author_id']);

        // Автоподписка автора при первой публикации
        if (!$wasPublished && $newPublishedAt !== null) {
            $this->autoSubscribeAuthor((int)$article['author_id'], (int)$article['id']);
			$this->notifySubscribers((int)$article['author_id'], (int)$article['id']);
        }

        redirect('/article/' . $article['slug']);
    }
	
	/** POST /api/articles/{id}/publish — быстрое «Опубликовать» из профиля */
    public function publish(string $id): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT id, slug, author_id, published_at FROM articles WHERE id = ? LIMIT 1');
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();

        if (!$article) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$article['author_id']) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        if ($article['published_at'] === null) {
            $db->prepare('UPDATE articles SET published_at = NOW() WHERE id = ?')
               ->execute([$articleId]);

            // Автоподписка автора
            $this->autoSubscribeAuthor((int)$article['author_id'], $articleId);
			$this->notifySubscribers((int)$article['author_id'], $articleId);
        }

        json_response(['ok' => true, 'slug' => $article['slug']]);
    }
	
	/** POST /api/articles/{id}/delete — безвозвратное удаление */
    public function destroy(string $id): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT id, author_id FROM articles WHERE id = ? LIMIT 1');
        $stmt->execute([$articleId]);
        $article = $stmt->fetch();

        if (!$article) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$article['author_id']) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        // Собираем пути картинок комментариев ДО удаления
        $stmt = $db->prepare(
            'SELECT ci.path
             FROM comment_images ci
             JOIN comments c ON c.id = ci.comment_id
             WHERE c.article_id = ?'
        );
        $stmt->execute([$articleId]);
        $paths = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Удаляем статью — БД каскадно снесёт комментарии, картинки, закладки, подписки, посещения
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM articles WHERE id = ?')->execute([$articleId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Ошибка удаления'], 500);
        }

        // Файлы удаляем ПОСЛЕ успешного DELETE — если что-то упадёт, получим сирот,
        // а не битые записи в БД
        foreach ($paths as $path) {
            $abs = __DIR__ . '/../../' . $path;
            if (is_file($abs)) @unlink($abs);
        }

        json_response(['ok' => true]);
    }

    private function findArticleBySlug(string $slug): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT * FROM articles WHERE slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ─── Подписка / отписка / отметка прочтения ──────── */

    public function follow(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT 1 FROM articles WHERE id = ? AND published_at IS NOT NULL');
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        $db->prepare(
            'INSERT IGNORE INTO article_follows (user_id, article_id) VALUES (?, ?)'
        )->execute([$user['id'], $articleId]);

        $db->prepare(
            'DELETE FROM article_unfollows WHERE user_id = ? AND article_id = ?'
        )->execute([$user['id'], $articleId]);

        json_response(['ok' => true, 'subscribed' => true]);
    }

    public function unfollow(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT 1 FROM articles WHERE id = ? LIMIT 1');
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        $db->prepare(
            'DELETE FROM article_follows WHERE user_id = ? AND article_id = ?'
        )->execute([$user['id'], $articleId]);

        $db->prepare(
            'INSERT IGNORE INTO article_unfollows (user_id, article_id) VALUES (?, ?)'
        )->execute([$user['id'], $articleId]);

        json_response(['ok' => true, 'subscribed' => false]);
    }
	
	/** POST /api/articles/{id}/bookmark — toggle */
    public function bookmark(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $userId    = (int)$user['id'];

        $db = Database::conn();

        $stmt = $db->prepare('SELECT 1 FROM articles WHERE id = ? AND published_at IS NOT NULL');
        $stmt->execute([$articleId]);
        if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        // Есть закладка? — убираем. Нет — добавляем.
        $stmt = $db->prepare('SELECT 1 FROM bookmarks WHERE user_id = ? AND article_id = ? LIMIT 1');
        $stmt->execute([$userId, $articleId]);

        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM bookmarks WHERE user_id = ? AND article_id = ?')
               ->execute([$userId, $articleId]);
            json_response(['ok' => true, 'bookmarked' => false]);
        } else {
            $db->prepare('INSERT INTO bookmarks (user_id, article_id) VALUES (?, ?)')
               ->execute([$userId, $articleId]);
            json_response(['ok' => true, 'bookmarked' => true]);
        }
    }
	
	/** POST /api/articles/{id}/update-pin */
    public function updatePin(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        
        if (!$user || !in_array($user['role'], ['admin', 'owner'], true)) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        $articleId = (int)$id;
        $pinGlobal = isset($_POST['pinned_global']) && $_POST['pinned_global'] === '1' ? 1 : 0;
        $pinCategory = isset($_POST['pinned_category']) && $_POST['pinned_category'] === '1' ? 1 : 0;

        $db = Database::conn();
        $stmt = $db->prepare('UPDATE articles SET pinned_global = ?, pinned_category = ? WHERE id = ?');
        $stmt->execute([$pinGlobal, $pinCategory, $articleId]);

        json_response(['ok' => true, 'pinned_global' => (bool)$pinGlobal, 'pinned_category' => (bool)$pinCategory]);
    }
	
	/** POST /api/markdown/preview */
    public function preview(): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $text = (string)($_POST['text'] ?? '');
        if (mb_strlen($text) > 20000) {
            json_response(['ok' => false, 'error' => 'Слишком большой текст'], 400);
        }

        $html = Markdown::toHtml($text);

        json_response(['ok' => true, 'html' => $html]);
    }

    public function markRead(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId      = (int)$id;
        $untilCommentId = isset($_POST['until_comment_id']) ? (int)$_POST['until_comment_id'] : 0;

        $db = Database::conn();

        $until = date('Y-m-d H:i:s');
        if ($untilCommentId > 0) {
            $stmt = $db->prepare(
                'SELECT created_at FROM comments WHERE id = ? AND article_id = ? LIMIT 1'
            );
            $stmt->execute([$untilCommentId, $articleId]);
            $row = $stmt->fetchColumn();
            if ($row) $until = $row;
        }

        $db->prepare(
            'INSERT INTO article_visits (user_id, article_id, last_seen_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at))'
        )->execute([$user['id'], $articleId, $until]);

        json_response(['ok' => true, 'last_seen_at' => $until]);
    }

    public function restrictUser(string $id): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $articleId = (int)$id;
        $targetId  = (int)($_POST['user_id'] ?? 0);

        if ($targetId <= 0) json_response(['ok' => false, 'error' => 'Некорректный пользователь'], 400);
        if ($targetId === (int)$user['id']) {
            json_response(['ok' => false, 'error' => 'Нельзя ограничить себя'], 400);
        }

        $db = Database::conn();

        $stmt = $db->prepare('SELECT author_id FROM articles WHERE id = ? LIMIT 1');
        $stmt->execute([$articleId]);
        $authorId = (int)$stmt->fetchColumn();

        if (!$authorId) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        if ($user['role'] !== 'admin' && (int)$user['id'] !== $authorId) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        $db->prepare(
            'INSERT IGNORE INTO article_restrictions (article_id, user_id, restricted_by)
             VALUES (?, ?, ?)'
        )->execute([$articleId, $targetId, $user['id']]);

        $db->prepare(
            'INSERT INTO moderation_log (actor_id, target_user_id, article_id, action)
             VALUES (?, ?, ?, "restrict")'
        )->execute([$user['id'], $targetId, $articleId]);

        json_response(['ok' => true]);
    }
	
	public function search(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $tab   = (string)($_GET['tab'] ?? 'articles');
        if (!in_array($tab, ['articles', 'comments', 'users'], true)) {
            $tab = 'articles';
        }

        $articles = [];
        $comments = [];
        $users = [];

        if (mb_strlen($query) >= 2) {
            $like = '%' . $query . '%';

            // 1. Статьи (добавили категории)
            $stmt = Database::conn()->prepare(
                'SELECT a.id, a.slug, a.title, a.excerpt, a.body, a.published_at,
                        c.name AS category_name, c.slug AS category_slug,
                        c_parent.name AS parent_category_name, c_parent.slug AS parent_category_slug,
                        u.username AS author, u.deleted_at AS author_deleted_at
                 FROM articles a
                 JOIN users u ON u.id = a.author_id
                 LEFT JOIN categories c ON c.id = a.category_id
                 LEFT JOIN categories c_parent ON c_parent.id = c.parent_id
                 WHERE a.published_at IS NOT NULL
                   AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ?)
                 ORDER BY a.published_at DESC
                 LIMIT 50'
            );
            $stmt->execute([$like, $like, $like]);
            $articles = $stmt->fetchAll();

            // 2. Комментарии
            $stmt = Database::conn()->prepare(
                'SELECT c.id, c.body, c.created_at,
                        u.username AS author, u.deleted_at AS user_deleted_at,
                        a.slug AS article_slug, a.title AS article_title,
                        COALESCE(c.parent_id, c.id) AS root_id
                 FROM comments c
                 JOIN users u ON u.id = c.user_id
                 JOIN articles a ON a.id = c.article_id
                 WHERE c.status = "visible"
                   AND a.published_at IS NOT NULL
                   AND c.body LIKE ?
                 ORDER BY c.created_at DESC
                 LIMIT 100'
            );
            $stmt->execute([$like]);
            $comments = $stmt->fetchAll();

            // 3. Пользователи
            $stmt = Database::conn()->prepare(
                'SELECT id, username, avatar_path, bio, created_at
                 FROM users
                 WHERE (username LIKE ? OR bio LIKE ?)
                   AND deleted_at IS NULL
                   AND banned_at IS NULL
                 ORDER BY username ASC
                 LIMIT 50'
            );
            $stmt->execute([$like, $like]);
            $users = $stmt->fetchAll();
        }

        view('articles/search', [
            'pageTitle' => $query !== '' ? 'Поиск: ' . $query : 'Поиск',
            'query'     => $query,
            'tab'       => $tab,
            'articles'  => $articles,
            'comments'  => $comments,
            'users'     => $users,
        ]);
    }

    /* ─── Вспомогательные ─────────────────────────────── */

    private function ensureVisit(int $userId, int $articleId): void
    {
        Database::conn()->prepare(
            'INSERT IGNORE INTO article_visits (user_id, article_id, last_seen_at)
             VALUES (?, ?, NOW())'
        )->execute([$userId, $articleId]);
    }

    private function isSubscribed(int $userId, int $articleId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM article_follows WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        return (bool)$stmt->fetchColumn();
    }
	
	private function isBookmarked(int $userId, int $articleId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM bookmarks WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Возвращает [count, items].
     * items — плоский список новых комментариев с пометкой типа:
     * 'reply' — ответ на мой комментарий, 'mention' — упоминание @меня, 'new' — остальное.
     */
    private function getNewItems(int $userId, int $articleId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT last_seen_at FROM article_visits WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        $lastSeen = $stmt->fetchColumn();
        if (!$lastSeen) return [0, []];

        $subscribed = $this->isSubscribed($userId, $articleId);
        return \App\CommentFeed::forUser($userId, $articleId, $lastSeen, $subscribed);
    }

    private function canModerateArticle(array $article): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        if (in_array($u['role'], ['admin', 'owner'], true)) return true; 
        return (int)$u['id'] === (int)$article['author_id'];
    }
	
	private function getAuthorStats(int $authorId): array
    {
        $db = Database::conn();
        
        $stmt = $db->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id = ?');
        $stmt->execute([$authorId]);
        $followers = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NOT NULL');
        $stmt->execute([$authorId]);
        $articles = (int)$stmt->fetchColumn();

        $statusKey = \App\UserStatus::forUser($authorId);
        $status = ['key' => $statusKey, 'label' => \App\UserStatus::label($statusKey)];
		
		$karma = \App\Karma::forUser($authorId);

        // Проверяем, подписан ли текущий зритель
        $isFollowing = false;
        $viewer = \App\Auth::user();
        if ($viewer && (int)$viewer['id'] !== $authorId) {
            $stmt = $db->prepare('SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ? LIMIT 1');
            $stmt->execute([$viewer['id'], $authorId]);
            $isFollowing = (bool)$stmt->fetchColumn();
        }

        return [
            'followers' => $followers,
            'articles' => $articles,
            'status' => $status,
            'is_following' => $isFollowing,
			'karma' => $karma,
        ];
    }

    private function requireAdmin(): void
    {
        $u = Auth::user();
        if (!$u || $u['role'] !== 'admin') {
            http_response_code(403);
            exit('Доступ только для администратора');
        }
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
     * Автоподписка автора на обсуждение своей статьи.
     * Не подписывает, если автор уже отписался явно.
     */
    private function autoSubscribeAuthor(int $userId, int $articleId): void
    {
        $db = Database::conn();

        // Явно отписался — уважаем
        $stmt = $db->prepare(
            'SELECT 1 FROM article_unfollows WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        if ($stmt->fetch()) return;

        $db->prepare(
            'INSERT IGNORE INTO article_follows (user_id, article_id) VALUES (?, ?)'
        )->execute([$userId, $articleId]);
    }
	
	/**
     * Отправляет уведомление о новой статье всем подписчикам автора.
     */
    private function notifySubscribers(int $authorId, int $articleId): void
    {
        $db = Database::conn();

        // Находим всех подписчиков, которым НЕ отключены уведомления о новых статьях
        // LEFT JOIN нужен, если записи в user_settings нет (тогда по умолчанию включено)
        $stmt = $db->prepare(
            'SELECT uf.follower_id 
             FROM user_follows uf
             LEFT JOIN user_settings us ON us.user_id = uf.follower_id
             WHERE uf.followed_id = ? 
               AND uf.follower_id != ? 
               AND (us.notify_new_articles IS NULL OR us.notify_new_articles = 1)'
        );
        $stmt->execute([$authorId, $authorId]);
        $followers = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!$followers) return;

        $values = [];
        $params = [];
        foreach ($followers as $followerId) {
            $values[] = "(?, ?, 'new_article', ?, NULL)";
            $params[] = $followerId;
            $params[] = $authorId;
            $params[] = $articleId;
        }

        if ($values) {
            $sql = 'INSERT INTO notifications (user_id, actor_id, type, article_id, comment_id) VALUES ' . implode(', ', $values);
            $db->prepare($sql)->execute($params);
        }
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        $stmt = Database::conn()->prepare('SELECT 1 FROM articles WHERE slug = ? LIMIT 1');
        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) return $slug;
            $slug = $base . '-' . (++$i);
        }
    }
	
	private function getCategoriesTree(): array
    {
        $db = Database::conn();
        $categories = $db->query('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
        $subRows = $db->query('SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC')->fetchAll();
        
        $subcategories = [];
        foreach ($subRows as $r) {
            $subcategories[(int)$r['parent_id']][] = $r;
        }
        return [$categories, $subcategories];
    }
}
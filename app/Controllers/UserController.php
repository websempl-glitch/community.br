<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Markdown;
use App\UserStatus;

class UserController
{
    private const PER_PAGE = 20;

    public function show(string $username): void
    {
        $this->renderProfile($username, 'overview');
    }

    public function articles(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $db = Database::conn();

        // Подсчет для пагинации
        $stmt = $db->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NOT NULL');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / self::PER_PAGE);

        // Сами статьи с обложкой
        $stmt = $db->prepare(
            'SELECT a.slug, a.title, a.excerpt, a.published_at, a.views,
                    COALESCE(a.cover_image, c.image_path) AS display_image,
                    c.name AS category_name, c.slug AS category_slug,
                    c_parent.name AS parent_category_name, c_parent.slug AS parent_category_slug,
                    u.username AS author, u.deleted_at AS author_deleted_at,
                    (SELECT COUNT(*) FROM comments c2 WHERE c2.article_id = a.id AND c2.status = "visible") AS comments_count
             FROM articles a
             JOIN users u ON u.id = a.author_id
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN categories c_parent ON c_parent.id = c.parent_id
             WHERE a.author_id = ? AND a.published_at IS NOT NULL
             ORDER BY a.published_at DESC
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();

        $this->renderProfile($username, 'articles', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function comments(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND status = "visible"');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / self::PER_PAGE);

        $stmt = $db->prepare(
            'SELECT c.id, c.body, c.created_at, a.slug AS article_slug, a.title AS article_title
             FROM comments c
             JOIN articles a ON a.id = c.article_id
             WHERE c.user_id = ? AND c.status = "visible"
             ORDER BY c.created_at DESC
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int)$r['id'],
                'preview_html' => e($this->previewText($r['body'])),
                'time_ago' => time_ago($r['created_at']),
                'article_slug' => $r['article_slug'],
                'article_title' => $r['article_title'],
            ];
        }

        $this->renderProfile($username, 'comments', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    public function drafts(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();
        
        $viewer = Auth::user();
        if (!$viewer || (int)$viewer['id'] !== (int)$user['id']) {
            redirect('/u/' . $username); // Черновики видит только владелец
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NULL');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / self::PER_PAGE);

        $stmt = $db->prepare(
            'SELECT id, slug, title, excerpt, created_at, updated_at
             FROM articles
             WHERE author_id = ? AND published_at IS NULL
             ORDER BY updated_at DESC
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();

        $this->renderProfile($username, 'drafts', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function bookmarks(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();
        
        $viewer = Auth::user();
        if (!$viewer || (int)$viewer['id'] !== (int)$user['id']) {
            redirect('/u/' . $username);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM bookmarks b JOIN articles a ON a.id = b.article_id WHERE b.user_id = ? AND a.published_at IS NOT NULL');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / self::PER_PAGE);

        $stmt = $db->prepare(
            'SELECT a.slug, a.title, a.excerpt, a.published_at, u.username AS author, u.deleted_at AS author_deleted_at
             FROM bookmarks b
             JOIN articles a ON a.id = b.article_id
             JOIN users u ON u.id = a.author_id
             WHERE b.user_id = ? AND a.published_at IS NOT NULL
             ORDER BY b.created_at DESC
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();

        $this->renderProfile($username, 'bookmarks', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function subscriptions(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();
        
        $viewer = Auth::user();
        if (!$viewer || (int)$viewer['id'] !== (int)$user['id']) {
            redirect('/u/' . $username);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $db = Database::conn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = ?');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / self::PER_PAGE);

        // Считаем количество статей, вышедших после последнего визита в профиль
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.avatar_path, u.bio, uf.created_at,
                    (SELECT COUNT(*) FROM articles a 
                     WHERE a.author_id = u.id AND a.published_at IS NOT NULL 
                       AND a.published_at > COALESCE(uf.last_seen_at, uf.created_at)) AS new_articles_count
             FROM user_follows uf
             JOIN users u ON u.id = uf.followed_id
             WHERE uf.follower_id = ?
             ORDER BY uf.created_at DESC
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();

        $this->renderProfile($username, 'subscriptions', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
	
	public function follow(string $id): void
    {
        csrf_check_any();

        $viewer = Auth::user();
        if (!$viewer) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $targetId = (int)$id;
        $viewerId = (int)$viewer['id'];

        if ($targetId <= 0 || $targetId === $viewerId) {
            json_response(['ok' => false, 'error' => 'Нельзя подписаться на себя'], 400);
        }

        $db = Database::conn();

        $stmt = $db->prepare('SELECT 1 FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$targetId]);
        if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'Пользователь не найден'], 404);

        $stmt = $db->prepare(
            'SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ? LIMIT 1'
        );
        $stmt->execute([$viewerId, $targetId]);

        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?')
               ->execute([$viewerId, $targetId]);
            json_response(['ok' => true, 'following' => false]);
        } else {
            $db->prepare('INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)')
               ->execute([$viewerId, $targetId]);
            json_response(['ok' => true, 'following' => true]);
        }
    }
	
	public function notifications(string $username): void
    {
        $user = $this->findByUsername($username);
        if (!$user || $user['deleted_at']) $this->notFound();

        $viewer = Auth::user();
        if (!$viewer || (int)$viewer['id'] !== (int)$user['id']) {
            redirect('/u/' . $username);
        }

        $db = Database::conn();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // 1. СНАЧАЛА помечаем все как прочитанные
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
           ->execute([$user['id']]);

        // 2. Затем делаем выборку
        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / $limit);

        $stmt = $db->prepare(
            'SELECT n.id, n.type, n.is_read, n.created_at, n.article_id, n.comment_id, n.message_id,
                    u.username AS actor_username, u.avatar_path AS actor_avatar,
                    a.slug AS article_slug, a.title AS article_title, c.body AS comment_body
             FROM notifications n
             JOIN users u ON u.id = n.actor_id
             LEFT JOIN articles a ON a.id = n.article_id
             LEFT JOIN comments c ON c.id = n.comment_id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $preview = '';
            $url = '';

            if ($r['type'] === 'new_article') {
                $preview = 'Новая статья: ' . $r['article_title'];
                $url = '/article/' . $r['article_slug'];
            } elseif ($r['type'] === 'message') {
                $preview = 'Новое сообщение';
                $url = '/u/' . $user['username'] . '/messages/view/' . $r['message_id'];
            } else {
                $preview = mb_substr(strip_tags($r['comment_body'] ?? ''), 0, 60) . '…';
                $url = '/article/' . $r['article_slug'] . '#c-' . $r['comment_id'];
            }

            $items[] = [
                'id' => (int)$r['id'],
                'type' => $r['type'],
                'is_read' => (bool)$r['is_read'],
                'time_ago' => time_ago($r['created_at']),
                'actor_username' => $r['actor_username'],
                'actor_initial' => mb_strtoupper(mb_substr($r['actor_username'], 0, 1)),
                'actor_avatar' => $r['actor_avatar'] ? '/' . $r['actor_avatar'] : null,
                'url' => $url,
                'preview' => $preview,
            ];
        }

        $this->renderProfile($username, 'notifications', [
            'items' => $items,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    /* ─── Вспомогательные ─────────────────────────────── */

    private function renderProfile(string $username, string $tab, array $data = []): void
    {
        $user = $this->findByUsername($username);
        if (!$user) $this->notFound();

        if (!empty($user['deleted_at'])) {
            view('users/deleted', ['pageTitle' => 'Пользователь удалён', 'username' => $user['username']]);
            exit;
        }

        $viewer = Auth::user();
        $isOwnProfile = $viewer && (int)$viewer['id'] === (int)$user['id'];

        $stats = [
            'comments' => $this->countComments((int)$user['id']),
            'articles' => $this->countArticles((int)$user['id']),
            'drafts'   => $isOwnProfile ? $this->countDrafts((int)$user['id']) : 0,
            'bookmarks'=> $isOwnProfile ? $this->countBookmarks((int)$user['id']) : 0,
            'subs'     => $isOwnProfile ? $this->countSubscriptions((int)$user['id']) : 0,
			'followers'=> $this->countFollowers((int)$user['id']),
			'karma'    => \App\Karma::forUser((int)$user['id']),
        ];

        $statusKey = UserStatus::keyByCount($stats['comments']);

        $isFollowing = false;
        if ($viewer && !$isOwnProfile) {
            $isFollowing = $this->isFollowing((int)$viewer['id'], (int)$user['id']);
            
            // Если я подписан — отмечаю, что зашел в профиль (сбрасываю счетчик "новых")
            if ($isFollowing) {
                Database::conn()->prepare('UPDATE user_follows SET last_seen_at = ? WHERE follower_id = ? AND followed_id = ?')
                    ->execute([date('Y-m-d H:i:s'), $viewer['id'], $user['id']]);
            }
        }
		
		$achievements = \App\Achievement::getForUser((int)$user['id']);

        view('users/layout', array_merge([
            'pageTitle'      => $user['username'] . ' — ' . $tab,
            'profileUser'    => $user,
            'stats'          => $stats,
            'statusKey'      => $statusKey,
            'isOwnProfile'   => $isOwnProfile,
            'isFollowing'    => $isFollowing,
            'activeTab'      => $tab,
            'tabData'        => $data,
			'achievements' => $achievements,
        ], $data));
    }

    private function findByUsername(string $username): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) return null;
        $stmt = Database::conn()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    private function notFound(): void
    {
        http_response_code(404);
        view('errors/404', ['pageTitle' => 'Профиль не найден']);
        exit;
    }
	
    private function countComments(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND status = "visible"');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
    private function countArticles(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NOT NULL');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
    private function countDrafts(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NULL');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
	private function countFollowers(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id = ?');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
    private function countBookmarks(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
    private function countSubscriptions(int $id): int {
        $stmt = Database::conn()->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = ?');
        $stmt->execute([$id]); return (int)$stmt->fetchColumn();
    }
    private function isFollowing(int $v, int $t): bool {
        $stmt = Database::conn()->prepare('SELECT 1 FROM user_follows WHERE follower_id = ? AND followed_id = ?');
        $stmt->execute([$v, $t]); return (bool)$stmt->fetchColumn();
    }
    private function previewText(string $body): string {
        $text = preg_replace('/```.*?```/s', ' [код] ', $body);
        $text = preg_replace('/[`*#>_\[\]()]/', '', $text);
        $text = preg_replace('/\s+/', ' ', trim((string)$text));
        if (mb_strlen($text) > 220) $text = mb_substr($text, 0, 220) . '…';
        return $text;
    }
}
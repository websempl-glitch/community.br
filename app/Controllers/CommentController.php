<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Markdown;

class CommentController
{
    /** GET /api/articles/{slug}/comments */
    public function index(string $slug): void
    {
        $article = $this->articleBySlug($slug);
        if (!$article || !$this->canReadComments($article)) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }

        $articleId = (int)$article['id'];
        $viewer = $this->viewerContext($articleId);

        $stmt = Database::conn()->prepare(
            'SELECT c.id, c.body, c.status, c.is_pinned, c.hidden_reason,
                    c.created_at, c.edited_at,
                    u.id AS user_id, u.username, u.avatar_path, u.deleted_at,
                    (SELECT COUNT(*) FROM comments r
                     WHERE r.parent_id = c.id AND r.status != "deleted") AS reply_count
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.article_id = ? AND c.parent_id IS NULL
               AND (c.status != "hidden" OR c.status = "hidden" AND (
                     c.user_id = ? OR ? = 1
               ))
             ORDER BY c.is_pinned DESC, c.created_at DESC'
        );
        $stmt->execute([$articleId, $viewer['id'] ?? 0, $viewer['can_moderate'] ? 1 : 0]);
        $rows = $stmt->fetchAll();

        $rows = $this->attachImages($rows);
        $rows = $this->attachReactions($rows, $viewer['id'] ?? 0);
        $statusMap = \App\UserStatus::mapFor(array_column($rows, 'user_id'));
		$karmaMap = \App\Karma::mapFor(array_column($rows, 'user_id'));
        $comments = array_map(fn($r) => $this->format($r, $viewer, $statusMap, $karmaMap), $rows);

        json_response([
            'ok'          => true,
            'comments'    => $comments,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** GET /api/comments/{id}/replies */
    public function replies(string $id): void
    {
        $stmt = Database::conn()->prepare(
            'SELECT c.article_id, a.author_id, a.published_at
             FROM comments c
             JOIN articles a ON a.id = c.article_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['ok' => false, 'error' => 'not_found'], 404);

        $articleId = (int)$row['article_id'];
        $article = [
            'id'          => $articleId,
            'author_id'   => (int)$row['author_id'],
            'published_at'=> $row['published_at'],
        ];
        if (!$this->canReadComments($article)) {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }

        $viewer = $this->viewerContext($articleId);

        $stmt = Database::conn()->prepare(
            'SELECT c.id, c.body, c.status, c.is_pinned, c.hidden_reason,
                    c.created_at, c.edited_at,
                    u.id AS user_id, u.username, u.avatar_path, u.deleted_at
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.parent_id = ?
               AND (c.status != "hidden" OR c.status = "hidden" AND (
                     c.user_id = ? OR ? = 1
               ))
             ORDER BY c.created_at ASC'
        );
        $stmt->execute([(int)$id, $viewer['id'] ?? 0, $viewer['can_moderate'] ? 1 : 0]);
        $rows = $stmt->fetchAll();

        $rows = $this->attachImages($rows);
        $rows = $this->attachReactions($rows, $viewer['id'] ?? 0);
        $statusMap = \App\UserStatus::mapFor(array_column($rows, 'user_id'));
		$karmaMap = \App\Karma::mapFor(array_column($rows, 'user_id'));
        $replies = array_map(fn($r) => $this->format($r, $viewer, $statusMap, $karmaMap), $rows);

        json_response([
            'ok'          => true,
            'replies'     => $replies,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** GET /api/comments/{id} */
    public function show(string $id): void
    {
        $stmt = Database::conn()->prepare(
            'SELECT c.id, c.parent_id, c.article_id, c.status,
                    u.username
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();

        if (!$row || $row['status'] === 'hidden') {
            json_response(['ok' => false, 'error' => 'not_found'], 404);
        }

        $rootId = $row['parent_id'] !== null ? (int)$row['parent_id'] : (int)$row['id'];

        json_response([
            'ok' => true,
            'comment' => [
                'id'         => (int)$row['id'],
                'is_root'    => $row['parent_id'] === null,
                'root_id'    => $rootId,
                'article_id' => (int)$row['article_id'],
            ],
        ]);
    }
	
	/** GET /api/articles/{slug}/updates */
    public function updates(string $slug): void
    {
        $articleId = $this->articleIdBySlug($slug);
        if (!$articleId) json_response(['ok' => false, 'error' => 'not_found'], 404);

        $user   = Auth::user();
        $userId = $user ? (int)$user['id'] : 0;

        if ($userId > 0) {
            // Авторизованные — по серверному last_seen_at
            $stmt = Database::conn()->prepare(
                'SELECT last_seen_at FROM article_visits
                 WHERE user_id = ? AND article_id = ? LIMIT 1'
            );
            $stmt->execute([$userId, $articleId]);
            $since = $stmt->fetchColumn();
            if (!$since) {
                json_response([
                    'ok' => true, 'count' => 0, 'items' => [],
                    'server_time' => date('Y-m-d H:i:s'),
                ]);
                return;
            }

            $subscribed = $this->isSubscribed($userId, $articleId);
            [$count, $items] = \App\CommentFeed::forUser($userId, $articleId, $since, $subscribed);
        } else {
            // Гости — по since из запроса
            $since = $_GET['since'] ?? null;
            if (!$since || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
                json_response([
                    'ok' => true, 'count' => 0, 'items' => [],
                    'server_time' => date('Y-m-d H:i:s'),
                ]);
                return;
            }

            // Для гостей items не собираем — экономим ресурсы
            $stmt = Database::conn()->prepare(
                'SELECT COUNT(*) FROM comments
                 WHERE article_id = ? AND status = "visible" AND created_at > ?'
            );
            $stmt->execute([$articleId, $since]);
            $count = (int)$stmt->fetchColumn();
            $items = [];
        }

        json_response([
            'ok'          => true,
            'count'       => $count,
            'items'       => $items,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /** POST /api/comments */
    public function store(): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        // Rate limit: 10 комментариев за 10 минут
        $rlKey  = 'comment_user:' . (int)$user['id'];
        $wait   = \App\RateLimit::check($rlKey);
        if ($wait !== null) {
            json_response([
                'ok'    => false,
                'error' => 'Слишком часто. ' . \App\RateLimit::humanWait($wait) . '.',
            ], 429);
        }

        $articleId = (int)($_POST['article_id'] ?? 0);
        $parentId  = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
                        ? (int)$_POST['parent_id'] : null;
        $body      = trim(str_replace(["\r\n", "\r"], "\n", $_POST['body'] ?? ''));

        $imageIds = [];
        if (!empty($_POST['image_ids'])) {
            $raw = is_array($_POST['image_ids']) ? $_POST['image_ids'] : explode(',', (string)$_POST['image_ids']);
            foreach ($raw as $v) {
                $v = (int)$v;
                if ($v > 0) $imageIds[] = $v;
            }
        }
        $imageIds = array_slice(array_unique($imageIds), 0, 10);

        if ($articleId <= 0)  json_response(['ok' => false, 'error' => 'Некорректная статья'], 400);
        if ($body === '' && !$imageIds) {
            json_response(['ok' => false, 'error' => 'Комментарий пустой'], 400);
        }
        if (mb_strlen($body) > 5000) json_response(['ok' => false, 'error' => 'Слишком длинный комментарий'], 400);

        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT published_at, comments_enabled FROM articles WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$articleId]);
        $a = $stmt->fetch();

        if (!$a) json_response(['ok' => false, 'error' => 'Статья не найдена'], 404);

        if ($a['published_at'] === null) {
            json_response(['ok' => false, 'error' => 'Статья в черновике — комментирование недоступно'], 403);
        }
        if ((int)$a['comments_enabled'] === 0) {
            json_response(['ok' => false, 'error' => 'Автор отключил комментарии'], 403);
        }

        $stmt = $db->prepare('SELECT 1 FROM article_restrictions WHERE article_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$articleId, $user['id']]);
        if ($stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Автор статьи ограничил вам комментирование здесь'], 403);
        }

        $replyToId = $parentId;

        if ($parentId !== null) {
            $stmt = $db->prepare(
                'SELECT id, parent_id, user_id FROM comments
                 WHERE id = ? AND article_id = ? AND status = "visible"'
            );
            $stmt->execute([$parentId, $articleId]);
            $parent = $stmt->fetch();
            if (!$parent) {
                json_response(['ok' => false, 'error' => 'Родительский комментарий не найден'], 404);
            }

            // Ответ на ответ — прикрепляем к корню + @упоминание
            if ($parent['parent_id'] !== null) {
                $rootId = (int)$parent['parent_id'];
                $rootUser = $this->usernameOf((int)$parent['user_id']);
                if ($body !== '' && !str_starts_with($body, '@' . $rootUser)) {
                    $body = '@' . $rootUser . ', ' . $body;
                }
                $parentId = $rootId; // Перезаписываем для БД
            }
        }

        $db->beginTransaction();
        try {
            // Фиксируем попытку ДО вставки — чтобы блок срабатывал на 11-й, а не на 12-й
			if (\App\RateLimit::hit($rlKey, 10, 600, 600)) {
				json_response([
					'ok'    => false,
					'error' => 'Слишком часто. 10 минут подождите.',
				], 429);
			}

			$db->prepare(
				'INSERT INTO comments (article_id, user_id, parent_id, body)
				 VALUES (?, ?, ?, ?)'
			)->execute([$articleId, $user['id'], $parentId, $body]);

            $newId = (int)$db->lastInsertId();
			
			// Проверяем достижения (первый коммент, 10 комментов и т.д.)
			\App\Achievement::check((int)$user['id']);
			
			// --- ЛОГИКА УВЕДОМЛЕНИЙ ---
			$actorId = (int)$user['id'];
			$notifiedUserIds = []; 

			// Функция для проверки, хочет ли юзер получать этот тип уведомлений
			$canNotify = function(int $targetUserId, string $type) use ($db): bool {
				$stmt = $db->prepare('SELECT notify_replies, notify_mentions FROM user_settings WHERE user_id = ?');
				$stmt->execute([$targetUserId]);
				$s = $stmt->fetch();
				if (!$s) return true; // Если настроек нет — по умолчанию всё включено
				if ($type === 'reply') return (bool)$s['notify_replies'];
				if ($type === 'mention') return (bool)$s['notify_mentions'];
				return true;
			};

			// 1. Уведомление об ответе
			if ($replyToId  !== null) {
				$stmtParent = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
				$stmtParent->execute([$replyToId ]);
				$parentUserId = (int)$stmtParent->fetchColumn();
				
				if ($parentUserId > 0 && $parentUserId !== $actorId) {
					if ($canNotify($parentUserId, 'reply')) {
						$db->prepare(
							'INSERT INTO notifications (user_id, actor_id, type, article_id, comment_id) 
							 VALUES (?, ?, "reply", ?, ?)'
						)->execute([$parentUserId, $actorId, $articleId, $newId]);
						$notifiedUserIds[] = $parentUserId;
					}
				}
			}

			// 2. Уведомления об упоминаниях
			if (preg_match_all('/(?:^|\s)@([a-zA-Z0-9_]{3,30})/', $body, $matches)) {
				$usernames = array_unique($matches[1]);
				foreach ($usernames as $uname) {
					$stmtU = $db->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL AND banned_at IS NULL LIMIT 1');
					$stmtU->execute([$uname]);
					$mentionedId = (int)$stmtU->fetchColumn();

					if ($mentionedId > 0 && $mentionedId !== $actorId && !in_array($mentionedId, $notifiedUserIds)) {
						if ($canNotify($mentionedId, 'mention')) {
							$db->prepare(
								'INSERT INTO notifications (user_id, actor_id, type, article_id, comment_id) 
								 VALUES (?, ?, "mention", ?, ?)'
							)->execute([$mentionedId, $actorId, $articleId, $newId]);
							$notifiedUserIds[] = $mentionedId;
						}
					}
				}
			}
			
			if ($imageIds) {
                // Привязываем только те, что загрузил сам пользователь и ещё ни к чему не привязаны
                $in = implode(',', array_fill(0, count($imageIds), '?'));
                $params = $imageIds;
                $params[] = (int)$user['id'];
                $db->prepare(
                    "UPDATE comment_images SET comment_id = ?
                     WHERE id IN ($in) AND comment_id IS NULL AND user_id = ?"
                )->execute(array_merge([$newId], $params));
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Ошибка сохранения'], 500);
        }

        // Собираем результат
        $stmt = $db->prepare(
            'SELECT c.id, c.body, c.status, c.is_pinned, c.hidden_reason,
                    c.created_at, c.edited_at,
                    u.id AS user_id, u.username, u.avatar_path, u.deleted_at,
                    0 AS reply_count
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ?'
        );
        $stmt->execute([$newId]);
        $row = $stmt->fetch();

        $rows = $this->attachImages([$row]);
        $rows = $this->attachReactions($rows, (int)$user['id']);
        $viewer = $this->viewerContext($articleId);
        $statusMap = \App\UserStatus::mapFor([(int)$user['id']]);
		$karmaMap = \App\Karma::mapFor([(int)$user['id']]);

        $justSubscribed = $this->autoSubscribe((int)$user['id'], $articleId);

        json_response([
            'ok'              => true,
            'comment'         => $this->format($rows[0], $viewer, $statusMap),
            'server_time'     => date('Y-m-d H:i:s'),
            'subscribed'      => $this->isSubscribed((int)$user['id'], $articleId),
            'just_subscribed' => $justSubscribed,
        ]);
    }

    /** POST /api/comments/{id}/update */
    public function update(string $id): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $id   = (int)$id;
        $body = trim(str_replace(["\r\n", "\r"], "\n", $_POST['body'] ?? ''));

        if ($body === '') json_response(['ok' => false, 'error' => 'Комментарий пустой'], 400);
        if (mb_strlen($body) > 5000) json_response(['ok' => false, 'error' => 'Слишком длинный'], 400);

        $keepIds = $this->intArray($_POST['keep_image_ids'] ?? []);
        $newIds  = $this->intArray($_POST['new_image_ids'] ?? []);

        // Всего картинок после сохранения не должно быть больше 10
        if (count($keepIds) + count($newIds) > 10) {
            json_response(['ok' => false, 'error' => 'Не более 10 изображений'], 400);
        }

        $db = Database::conn();
        $stmt = $db->prepare('SELECT user_id, status FROM comments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $c = $stmt->fetch();

        if (!$c) json_response(['ok' => false, 'error' => 'Не найдено'], 404);
        if ((int)$c['user_id'] !== (int)$user['id']) {
            json_response(['ok' => false, 'error' => 'Только свои комментарии'], 403);
        }
        if ($c['status'] !== 'visible') {
            json_response(['ok' => false, 'error' => 'Комментарий недоступен для редактирования'], 400);
        }

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE comments SET body = ?, edited_at = NOW() WHERE id = ?')
               ->execute([$body, $id]);

            // Существующие картинки: удаляем то, что не в keepIds
            $stmt = $db->prepare(
                'SELECT id, path FROM comment_images WHERE comment_id = ?'
            );
            $stmt->execute([$id]);
            $existing = $stmt->fetchAll();

            $keep = array_flip($keepIds);

            foreach ($existing as $img) {
                if (!isset($keep[(int)$img['id']])) {
                    $absPath = __DIR__ . '/../../' . $img['path'];
                    if (is_file($absPath)) @unlink($absPath);

                    $db->prepare('DELETE FROM comment_images WHERE id = ?')
                       ->execute([$img['id']]);
                }
            }

            // Новые картинки: привязываем только свои и только те, что ещё ни к чему не привязаны
            if ($newIds) {
                $in = implode(',', array_fill(0, count($newIds), '?'));
                $params = array_merge([$id], $newIds, [(int)$user['id']]);
                $db->prepare(
                    "UPDATE comment_images SET comment_id = ?
                     WHERE id IN ($in) AND comment_id IS NULL AND user_id = ?"
                )->execute($params);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Ошибка сохранения'], 500);
        }

        json_response(['ok' => true, 'server_time' => date('Y-m-d H:i:s')]);
    }

    /** Приводит POST-значение к массиву int, чистит мусор */
    private function intArray($raw): array
    {
        if (!is_array($raw)) $raw = $raw === '' || $raw === null ? [] : explode(',', (string)$raw);
        $out = [];
        foreach ($raw as $v) {
            $v = (int)$v;
            if ($v > 0) $out[] = $v;
        }
        return array_values(array_unique($out));
    }
	
	/** POST /api/comments/{id}/read — отметить конкретный комментарий прочитанным */
    public function markReadComment(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $commentId = (int)$id;
        if ($commentId <= 0) json_response(['ok' => false, 'error' => 'Некорректный id'], 400);

        $db = Database::conn();

        $stmt = $db->prepare('SELECT 1 FROM comments WHERE id = ? LIMIT 1');
        $stmt->execute([$commentId]);
        if (!$stmt->fetch()) json_response(['ok' => false, 'error' => 'Не найдено'], 404);

        $db->prepare(
            'INSERT IGNORE INTO comment_reads (user_id, comment_id) VALUES (?, ?)'
        )->execute([$user['id'], $commentId]);

        json_response(['ok' => true]);
    }

    /** POST /api/comments/{id}/delete */
    public function destroy(string $id): void
    {
        csrf_check_any();

        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $id = (int)$id;
        $db = Database::conn();
        
        // Добавили article_id в запрос, чтобы узнать автора статьи
        $stmt = $db->prepare('SELECT user_id, status, article_id FROM comments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $c = $stmt->fetch();

        if (!$c) json_response(['ok' => false, 'error' => 'Не найдено'], 404);
        
        $isOwn = ((int)$c['user_id'] === (int)$user['id']);
        
        // Если это не свой комментарий — проверяем, не автор ли статьи и не админ ли
        if (!$isOwn) {
            $stmtArt = $db->prepare('SELECT author_id FROM articles WHERE id = ? LIMIT 1');
            $stmtArt->execute([(int)$c['article_id']]);
            $authorId = (int)$stmtArt->fetchColumn();

            if ($user['role'] !== 'admin' && (int)$user['id'] !== $authorId) {
                json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
            }
        }

        if ($c['status'] === 'deleted') json_response(['ok' => true]);

        // Мягкое удаление
        $db->prepare('UPDATE comments SET status = "deleted", body = "" WHERE id = ?')
           ->execute([$id]);

        // Если удалил модератор (не сам автор комментария), пишем в лог
        if (!$isOwn) {
            $db->prepare(
                'INSERT INTO moderation_log (actor_id, target_comment_id, article_id, action)
                 VALUES (?, ?, ?, "delete")'
            )->execute([$user['id'], $id, (int)$c['article_id']]);
        }

        json_response(['ok' => true, 'server_time' => date('Y-m-d H:i:s')]);
    }

    /* ─── Модерация ───────────────────────────────────── */

    public function hide(string $id): void   { $this->moderateAction((int)$id, 'hidden', 'hide'); }
    public function unhide(string $id): void { $this->moderateAction((int)$id, 'visible', 'unhide'); }
    public function pin(string $id): void    { $this->pinAction((int)$id, 1); }
    public function unpin(string $id): void  { $this->pinAction((int)$id, 0); }

    private function moderateAction(int $id, string $status, string $logAction): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT c.id, c.article_id, c.status, a.author_id
             FROM comments c
             JOIN articles a ON a.id = c.article_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $c = $stmt->fetch();

        if (!$c) json_response(['ok' => false, 'error' => 'Не найдено'], 404);
        if (!$this->canModerate((int)$c['author_id'], $user)) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        $reason = $status === 'hidden'
            ? ($user['role'] === 'admin' ? 'скрыт администратором' : 'скрыт автором статьи')
            : null;

        $db->prepare(
            'UPDATE comments SET status = ?, hidden_by = ?, hidden_reason = ? WHERE id = ?'
        )->execute([$status, $status === 'hidden' ? $user['id'] : null, $reason, $id]);

        $db->prepare(
            'INSERT INTO moderation_log (actor_id, target_comment_id, article_id, action)
             VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], $id, (int)$c['article_id'], $logAction]);

        json_response(['ok' => true, 'status' => $status, 'server_time' => date('Y-m-d H:i:s')]);
    }

    private function pinAction(int $id, int $pinned): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $db = Database::conn();
        $stmt = $db->prepare(
            'SELECT c.id, c.article_id, c.parent_id, a.author_id
             FROM comments c
             JOIN articles a ON a.id = c.article_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $c = $stmt->fetch();

        if (!$c) json_response(['ok' => false, 'error' => 'Не найдено'], 404);
        if ($c['parent_id'] !== null) {
            json_response(['ok' => false, 'error' => 'Закреплять можно только корневые комментарии'], 400);
        }
        if (!$this->canModerate((int)$c['author_id'], $user)) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав'], 403);
        }

        $db->prepare('UPDATE comments SET is_pinned = ? WHERE id = ?')->execute([$pinned, $id]);
        $db->prepare(
            'INSERT INTO moderation_log (actor_id, target_comment_id, article_id, action)
             VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], $id, (int)$c['article_id'], $pinned ? 'pin' : 'unpin']);

        json_response(['ok' => true, 'is_pinned' => $pinned === 1, 'server_time' => date('Y-m-d H:i:s')]);
    }

    /* ─── Вспомогательные ─────────────────────────────── */

    /**
     * Принимает массив comments-rows, для каждой строки добавляет ключ '_images'
     * с массивом картинок этой реплики. Один запрос на всё.
     */
    private function attachImages(array $rows): array
    {
        if (!$rows) return $rows;

        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $stmt = Database::conn()->prepare(
            "SELECT id, comment_id, path, width, height, size
             FROM comment_images
             WHERE comment_id IN ($in)
             ORDER BY id ASC"
        );
        $stmt->execute($ids);
        $all = $stmt->fetchAll();

        $byComment = [];
        foreach ($all as $img) {
            $byComment[(int)$img['comment_id']][] = [
                'id'     => (int)$img['id'],
                'url'    => '/' . $img['path'],
                'width'  => (int)$img['width'],
                'height' => (int)$img['height'],
                'size'   => (int)$img['size'],
            ];
        }

        foreach ($rows as &$r) {
            $r['_images'] = $byComment[(int)$r['id']] ?? [];
        }
        return $rows;
    }
	
	// Принимает массив комментариев, добавляет к каждому _reactions (счётчики) и _my_reaction.
    private function attachReactions(array $rows, int $viewerId): array
    {
        if (!$rows) return $rows;

        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in  = implode(',', array_fill(0, count($ids), '?'));

        // Счётчики реакций по всем комментариям страницы — один запрос
        $stmt = Database::conn()->prepare(
            "SELECT comment_id, reaction, COUNT(*) AS cnt
             FROM comment_reactions
             WHERE comment_id IN ($in)
             GROUP BY comment_id, reaction"
        );
        $stmt->execute($ids);
        $counts = [];
        foreach ($stmt->fetchAll() as $r) {
            $counts[(int)$r['comment_id']][$r['reaction']] = (int)$r['cnt'];
        }

        // Мои реакции
        $mine = [];
        if ($viewerId > 0) {
            $stmt = Database::conn()->prepare(
                "SELECT comment_id, reaction
                 FROM comment_reactions
                 WHERE comment_id IN ($in) AND user_id = ?"
            );
            $stmt->execute(array_merge($ids, [$viewerId]));
            foreach ($stmt->fetchAll() as $r) {
                $mine[(int)$r['comment_id']] = $r['reaction'];
            }
        }

        foreach ($rows as &$r) {
            $cid = (int)$r['id'];
            $r['_reactions']   = $counts[$cid] ?? [];
            $r['_my_reaction'] = $mine[$cid] ?? null;
        }
        return $rows;
    }

    private function canModerate(int $articleAuthorId, array $user): bool
    {
        return $user['role'] === 'admin' || (int)$user['id'] === $articleAuthorId;
    }

    private function viewerContext(int $articleId): array
    {
        $u = Auth::user();
        if (!$u) return ['id' => null, 'can_moderate' => false];

        $canModerate = $u['role'] === 'admin';
        if (!$canModerate) {
            $stmt = Database::conn()->prepare(
                'SELECT 1 FROM articles WHERE id = ? AND author_id = ? LIMIT 1'
            );
            $stmt->execute([$articleId, $u['id']]);
            $canModerate = (bool)$stmt->fetchColumn();
        }
        return ['id' => (int)$u['id'], 'can_moderate' => $canModerate];
    }
	
	private function canReadComments(array $article): bool
    {
        // Опубликована — видят все
        if (!empty($article['published_at'])) return true;

        // Черновик — только автор и админ
        $viewer = Auth::user();
        if (!$viewer) return false;
        if ($viewer['role'] === 'admin') return true;
        return (int)$viewer['id'] === (int)$article['author_id'];
    }

    private function articleIdBySlug(string $slug): ?int
    {
        $article = $this->articleBySlug($slug);
        return $article ? (int)$article['id'] : null;
    }

    private function articleBySlug(string $slug): ?array
    {
        $stmt = Database::conn()->prepare(
            'SELECT id, slug, author_id, published_at, comments_enabled
             FROM articles WHERE slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function usernameOf(int $userId): string
    {
        $stmt = Database::conn()->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (string)$stmt->fetchColumn();
    }

    private function format(array $row, array $viewer, array $statusMap = [], array $karmaMap = []): array
    {
        $status        = $row['status'] ?? 'visible';
        $isDeleted     = $status === 'deleted';
        $userDeleted   = !empty($row['deleted_at'] ?? null);
        $userGone      = $userDeleted; // Имя скрываем ТОЛЬКО если удален пользователь
        $isHidden      = $status === 'hidden';
        $isOwner       = $viewer['id'] !== null && (int)$row['user_id'] === (int)$viewer['id'];
        $canSeeBody    = !$isHidden || $viewer['can_moderate'] || $isOwner;

        if ($isDeleted) {
            $bodyHtml = '<p class="comment-deleted">[комментарий удалён]</p>';
        } elseif ($userDeleted) {
            $bodyHtml = Markdown::toHtml($row['body']);
        } elseif ($canSeeBody) {
            $bodyHtml = Markdown::toHtml($row['body']);
        } else {
            $bodyHtml = '<p class="comment-hidden">[' . e($row['hidden_reason'] ?? 'скрыт') . ']</p>';
        }

        $lastAt = array_key_exists('last_reply_at', $row) && $row['last_reply_at'] !== null
            ? $row['last_reply_at']
            : $row['created_at'];

        $userStatusKey = $statusMap[(int)$row['user_id']] ?? \App\UserStatus::NEWBIE;

        return [
            'id'                => (int)$row['id'],
            'user_id'           => (int)$row['user_id'],
            'username'          => $userGone ? 'Удалённый пользователь' : $row['username'],
            'initial'           => $userGone ? '·' : mb_strtoupper(mb_substr($row['username'], 0, 1)),
            'avatar_url'        => (!$userGone && !empty($row['avatar_path']))
                                     ? '/' . $row['avatar_path']
                                     : null,
            'is_user_deleted'   => $userDeleted,
            'user_status'       => $userGone ? null : $userStatusKey,
            'user_status_label' => $userGone ? null : \App\UserStatus::label($userStatusKey),
            'body_html'         => $bodyHtml,
			'body_raw'          => $isDeleted ? '' : $row['body'],
            'images'            => $isDeleted ? [] : ($row['_images'] ?? []),
			'reactions'         => $row['_reactions'] ?? [],
            'my_reaction'       => $row['_my_reaction'] ?? null,
			'user_karma'        => $karmaMap[(int)$row['user_id']] ?? 0,
            'created_at'        => $row['created_at'],
            'time_ago'          => time_ago($row['created_at']),
            'edited'            => $row['edited_at'] !== null,
            'reply_count'       => (int)($row['reply_count'] ?? 0),
            'is_deleted'        => $isDeleted,
            'is_hidden'         => $isHidden,
            'is_pinned'         => (int)($row['is_pinned'] ?? 0) === 1,
            'hidden_label'      => $isHidden ? ($row['hidden_reason'] ?? 'скрыт') : null,
            'last_activity'     => $lastAt,
            'last_activity_ago' => time_ago($lastAt),
        ];
    }
	
	/** POST /api/comments/{id}/react */
    public function react(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $commentId = (int)$id;
        $reaction  = (string)($_POST['reaction'] ?? '');

        $allowed = ['like', 'love', 'funny', 'sad', 'angry', 'poop'];
        if (!in_array($reaction, $allowed, true)) {
            json_response(['ok' => false, 'error' => 'Недопустимая реакция'], 400);
        }

        $db = Database::conn();

        // Комментарий существует и видим
        $stmt = $db->prepare(
            'SELECT 1 FROM comments WHERE id = ? AND status = "visible" LIMIT 1'
        );
        $stmt->execute([$commentId]);
        if (!$stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Комментарий не найден'], 404);
        }

        // Существующая реакция?
        $stmt = $db->prepare(
            'SELECT reaction FROM comment_reactions WHERE user_id = ? AND comment_id = ? LIMIT 1'
        );
        $stmt->execute([$user['id'], $commentId]);
        $existing = $stmt->fetchColumn();

        if ($existing === $reaction) {
            // Клик по своей же реакции — снимаем
            $db->prepare('DELETE FROM comment_reactions WHERE user_id = ? AND comment_id = ?')
               ->execute([$user['id'], $commentId]);
        } elseif ($existing) {
            // Смена эмодзи
            $db->prepare('UPDATE comment_reactions SET reaction = ? WHERE user_id = ? AND comment_id = ?')
               ->execute([$reaction, $user['id'], $commentId]);
        } else {
            // Новая
            $db->prepare(
                'INSERT INTO comment_reactions (user_id, comment_id, reaction) VALUES (?, ?, ?)'
            )->execute([$user['id'], $commentId, $reaction]);
        }

        // Свежие счётчики
        $stmt = $db->prepare(
            'SELECT reaction, COUNT(*) AS cnt FROM comment_reactions
             WHERE comment_id = ? GROUP BY reaction'
        );
        $stmt->execute([$commentId]);
        $counts = [];
        foreach ($stmt->fetchAll() as $r) {
            $counts[$r['reaction']] = (int)$r['cnt'];
        }

        $stmt = $db->prepare(
            'SELECT reaction FROM comment_reactions WHERE user_id = ? AND comment_id = ?'
        );
        $stmt->execute([$user['id'], $commentId]);
        $my = $stmt->fetchColumn() ?: null;
		
		// Проверяем достижения автора комментария (вдруг ему накидали на "Эксперта")
        $stmt = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $authorId = (int)$stmt->fetchColumn();
        if ($authorId > 0) {
            \App\Achievement::check($authorId);
        }

        json_response([
            'ok'          => true,
            'reactions'   => $counts,
            'my_reaction' => $my,
        ]);
    }
	
	private function autoSubscribe(int $userId, int $articleId): bool
    {
        $db = Database::conn();

        // Если пользователь явно отписался — уважаем
        $stmt = $db->prepare(
            'SELECT 1 FROM article_unfollows WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        if ($stmt->fetch()) return false;

        // Уже подписан?
        $stmt = $db->prepare(
            'SELECT 1 FROM article_follows WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        if ($stmt->fetch()) return false;

        // Новая подписка
        $db->prepare(
            'INSERT IGNORE INTO article_follows (user_id, article_id) VALUES (?, ?)'
        )->execute([$userId, $articleId]);

        return true;
    }

    private function isSubscribed(int $userId, int $articleId): bool
    {
        $stmt = Database::conn()->prepare(
            'SELECT 1 FROM article_follows WHERE user_id = ? AND article_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $articleId]);
        return (bool)$stmt->fetchColumn();
    }
	
	/** POST /api/comments/{id}/report */
    public function report(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Требуется авторизация'], 401);

        $commentId = (int)$id;
        $reason = (string)($_POST['reason'] ?? '');
        $note   = trim((string)($_POST['note'] ?? ''));

        $allowed = ['spam', 'abuse', 'other'];
        if (!in_array($reason, $allowed, true)) {
            json_response(['ok' => false, 'error' => 'Некорректная причина'], 400);
        }

        $rlKey = 'report_user:' . (int)$user['id'];
        $wait = \App\RateLimit::check($rlKey);
        if ($wait !== null) {
            json_response(['ok' => false, 'error' => 'Слишком много жалоб. ' . \App\RateLimit::humanWait($wait) . '.'], 429);
        }

        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT user_id, status FROM comments WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$commentId]);
        $c = $stmt->fetch();

        if (!$c || $c['status'] !== 'visible') {
            json_response(['ok' => false, 'error' => 'Комментарий не найден'], 404);
        }

        if ((int)$c['user_id'] === (int)$user['id']) {
            json_response(['ok' => false, 'error' => 'Нельзя пожаловаться на свой комментарий'], 400);
        }

        // Проверка на дубликат
        $stmt = $db->prepare(
            'SELECT 1 FROM comment_reports WHERE user_id = ? AND comment_id = ? LIMIT 1'
        );
        $stmt->execute([$user['id'], $commentId]);
        if ($stmt->fetch()) {
            json_response(['ok' => false, 'error' => 'Вы уже жаловались на этот комментарий'], 400);
        }

        $db->prepare(
            'INSERT INTO comment_reports (comment_id, user_id, reason, note) VALUES (?, ?, ?, ?)'
        )->execute([$commentId, $user['id'], $reason, $note !== '' ? $note : null]);

        \App\RateLimit::hit($rlKey, 10, 3600, 3600);

        json_response(['ok' => true]);
    }
}
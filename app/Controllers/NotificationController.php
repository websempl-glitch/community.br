<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;

class NotificationController
{
    public function count(): void
    {
        $user = Auth::user();
        if (!$user) json_response(['ok' => true, 'count' => 0]);

        $stmt = Database::conn()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$user['id']]);
        $count = (int)$stmt->fetchColumn();

        json_response(['ok' => true, 'count' => $count]);
    }

    public function index(): void
    {
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Auth required'], 401);

        $db = Database::conn();
        // ВАЖНО: LEFT JOIN для статей и сообщений
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
             LIMIT 10'
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
                // Ссылка ведет в личный ящик текущего пользователя
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

        json_response(['ok' => true, 'notifications' => $items]);
    }

    public function markReadSingle(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false], 401);

        Database::conn()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        )->execute([(int)$id, $user['id']]);

        json_response(['ok' => true]);
    }

    public function markAllRead(): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false], 401);

        Database::conn()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
        )->execute([$user['id']]);

        json_response(['ok' => true]);
    }
}
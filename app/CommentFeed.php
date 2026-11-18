<?php
declare(strict_types=1);

namespace App;

class CommentFeed
{
    /**
     * @param bool $subscribed  если false — возвращаем только ответы мне и упоминания
     * @return array{0:int,1:array<int,array>}
     */
    public static function forUser(int $userId, int $articleId, string $since, bool $subscribed = true): array
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM comments c
             WHERE c.article_id = ? AND c.status = "visible"
               AND c.user_id != ? AND c.created_at > ?
               AND NOT EXISTS (
                   SELECT 1 FROM comment_reads r
                   WHERE r.comment_id = c.id AND r.user_id = ?
               )'
        );
        $stmt->execute([$articleId, $userId, $since, $userId]);
        $totalCount = (int)$stmt->fetchColumn();

        if ($totalCount === 0) return [0, []];

        // Мои комментарии
        $stmt = $db->prepare(
            'SELECT id FROM comments WHERE user_id = ? AND article_id = ? AND status = "visible"'
        );
        $stmt->execute([$userId, $articleId]);
        $myIdsSet = array_flip(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));

        // Мой username
        $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $myUsername = (string)$stmt->fetchColumn();

        // Список — до 100, от новых к старым
        $stmt = $db->prepare(
            'SELECT c.id, c.body, c.parent_id, c.created_at,
                    COALESCE(c.parent_id, c.id) AS root_id,
                    u.id AS user_id, u.username, u.avatar_path, u.deleted_at
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.article_id = ? AND c.status = "visible"
               AND c.user_id != ? AND c.created_at > ?
               AND NOT EXISTS (
                   SELECT 1 FROM comment_reads r
                   WHERE r.comment_id = c.id AND r.user_id = ?
               )
             ORDER BY c.created_at DESC
             LIMIT 100'
        );
        $stmt->execute([$articleId, $userId, $since, $userId]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $type = 'new';
            if ($r['parent_id'] !== null && isset($myIdsSet[(int)$r['parent_id']])) {
                $type = 'reply';
            } elseif ($myUsername && self::mentionsUser($r['body'], $myUsername)) {
                $type = 'mention';
            }

            // Не подписан — оставляем только адресное
            if (!$subscribed && $type === 'new') continue;

            $items[] = [
                'id'         => (int)$r['id'],
                'type'       => $type,
                'user_id'    => (int)$r['user_id'],
                'username'   => !empty($r['deleted_at']) ? 'Удалённый пользователь' : $r['username'],
                'initial'    => !empty($r['deleted_at']) ? '·' : mb_strtoupper(mb_substr($r['username'], 0, 1)),
                'avatar_url' => (!empty($r['deleted_at']) || empty($r['avatar_path']))
                                 ? null
                                 : '/' . $r['avatar_path'],
                'preview'    => self::previewText($r['body']),
                'created_at' => $r['created_at'],
                'time_ago'   => time_ago($r['created_at']),
                'root_id'    => (int)$r['root_id'],
            ];
        }
		
		$statusMap = \App\UserStatus::mapFor(array_column($items, 'user_id'));
        foreach ($items as &$it) {
            $key = $statusMap[$it['user_id']] ?? \App\UserStatus::NEWBIE;
            $it['user_status']       = $key;
            $it['user_status_label'] = \App\UserStatus::label($key);
        }
        unset($it);
		
        usort($items, function ($a, $b) {
            $prio = ['reply' => 0, 'mention' => 1, 'new' => 2];
            $pa = $prio[$a['type']] ?? 9;
            $pb = $prio[$b['type']] ?? 9;
            if ($pa !== $pb) return $pa - $pb;
            return strcmp($b['created_at'], $a['created_at']);
        });

        $count = $subscribed ? $totalCount : count($items);

        return [$count, $items];
    }

    public static function mentionsUser(string $body, string $username): bool
    {
        $pattern = '/(?:^|[^a-zA-Z0-9_])@' . preg_quote($username, '/') . '(?![a-zA-Z0-9_])/i';
        return (bool)preg_match($pattern, $body);
    }

    public static function previewText(string $body): string
    {
        $text = preg_replace('/```.*?```/s', ' [код] ', $body);
        $text = preg_replace('/[`*#>_\[\]()]/', '', $text);
        $text = preg_replace('/\s+/', ' ', trim((string)$text));
        if (mb_strlen($text) > 100) {
            $text = mb_substr($text, 0, 100) . '…';
        }
        return $text;
    }
}
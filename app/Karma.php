<?php
declare(strict_types=1);

namespace App;

class Karma
{
    /**
     * Считает карму для одного пользователя.
     */
    public static function forUser(int $userId): int
    {
        $map = self::mapFor([$userId]);
        return $map[$userId] ?? 0;
    }

    /**
     * Возвращает карту кармы для массива ID пользователей (один запрос).
     * @param int[] $userIds
     * @return array<int, int>
     */
    public static function mapFor(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return [];

        $in = implode(',', array_fill(0, count($userIds), '?'));
        
        // Считаем реакции на комментарии этих пользователей, исключая их собственные реакции
        $stmt = Database::conn()->prepare(
            "SELECT c.user_id, 
                    SUM(
                        CASE cr.reaction
                            WHEN 'angry' THEN -1
                            WHEN 'poop' THEN -1
                            WHEN 'sad' THEN 0
                            ELSE 1
                        END
                    ) AS karma
             FROM comments c
             JOIN comment_reactions cr ON cr.comment_id = c.id
             WHERE c.user_id IN ($in)
               AND c.status = 'visible'
               AND cr.user_id != c.user_id
             GROUP BY c.user_id"
        );
        $stmt->execute($userIds);
        
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['user_id']] = (int)$row['karma'];
        }
        
        // Для тех, у кого нет реакций, карма = 0
        foreach ($userIds as $id) {
            if (!isset($map[$id])) $map[$id] = 0;
        }
        
        return $map;
    }
}
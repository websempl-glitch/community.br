<?php
declare(strict_types=1);

namespace App;

class UserStatus
{
    public const NEWBIE  = 'newbie';
    public const MEMBER  = 'member';
    public const VETERAN = 'veteran';

    private const THRESHOLD_MEMBER  = 10;
    private const THRESHOLD_VETERAN = 50;

    public static function keyByCount(int $commentCount): string
    {
        if ($commentCount >= self::THRESHOLD_VETERAN) return self::VETERAN;
        if ($commentCount >= self::THRESHOLD_MEMBER)  return self::MEMBER;
        return self::NEWBIE;
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::VETERAN => 'Ветеран',
            self::MEMBER  => 'Участник',
            default       => 'Новичок',
        };
    }

    /**
     * @param int[] $userIds
     * @return array<int,string> user_id => key
     */
    public static function mapFor(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) return [];

        $in = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = Database::conn()->prepare(
            "SELECT user_id, COUNT(*) AS cnt
             FROM comments
             WHERE user_id IN ($in) AND status = 'visible'
             GROUP BY user_id"
        );
        $stmt->execute($userIds);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['user_id']] = self::keyByCount((int)$r['cnt']);
        }
        // Кто не попал в выборку — у того 0 комментариев
        foreach ($userIds as $uid) {
            if (!isset($map[$uid])) $map[$uid] = self::NEWBIE;
        }
        return $map;
    }

    /** Для одного пользователя (когда нет смысла в batch) */
    public static function forUser(int $userId): string
    {
        $map = self::mapFor([$userId]);
        return $map[$userId] ?? self::NEWBIE;
    }
}
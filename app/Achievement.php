<?php
declare(strict_types=1);

namespace App;

class Achievement
{
    /**
     * Проверяет и выдает достижения пользователю на основе его действий.
     * @param int $userId Кому проверяем
     */
    public static function check(int $userId): void
    {
        $db = Database::conn();

        // Получаем все существующие бейджи и те, что у юзера уже есть
        $all = $db->query('SELECT id, code FROM achievements')->fetchAll();
        $stmt = $db->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = ?');
        $stmt->execute([$userId]);
        $earned = array_column($stmt->fetchAll(), 'achievement_id');

        $map = [];
        foreach ($all as $a) {
            $map[$a['code']] = (int)$a['id'];
        }

        $toAward = [];

        // 1. Проверка на первый комментарий
        $stmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND status = "visible"');
        $stmt->execute([$userId]);
        $commentsCount = (int)$stmt->fetchColumn();
        if ($commentsCount >= 1 && isset($map['first_comment']) && !in_array($map['first_comment'], $earned)) {
            $toAward[] = $map['first_comment'];
        }
        if ($commentsCount >= 10 && isset($map['comments_10']) && !in_array($map['comments_10'], $earned)) {
            $toAward[] = $map['comments_10'];
        }

        // 2. Проверка на статьи
        $stmt = $db->prepare('SELECT COUNT(*) FROM articles WHERE author_id = ? AND published_at IS NOT NULL');
        $stmt->execute([$userId]);
        $articlesCount = (int)$stmt->fetchColumn();
        if ($articlesCount >= 1 && isset($map['first_article']) && !in_array($map['first_article'], $earned)) {
            $toAward[] = $map['first_article'];
        }
        if ($articlesCount >= 5 && isset($map['articles_5']) && !in_array($map['articles_5'], $earned)) {
            $toAward[] = $map['articles_5'];
        }

        // 3. Проверка на карму
        $karma = Karma::forUser($userId);
        if ($karma >= 50 && isset($map['karma_50']) && !in_array($map['karma_50'], $earned)) {
            $toAward[] = $map['karma_50'];
        }

        // Выдаем новые бейджи
        if ($toAward) {
            $in = implode(',', array_fill(0, count($toAward), '(?, ?)'));
            $sql = "INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES $in";
            $params = [];
            foreach ($toAward as $achId) {
                $params[] = $userId;
                $params[] = $achId;
            }
            $db->prepare($sql)->execute($params);
        }
    }

    /**
     * Получает все бейджи пользователя для отображения.
     */
    public static function getForUser(int $userId): array
    {
        $stmt = Database::conn()->prepare(
            'SELECT a.name, a.icon, a.description, ua.created_at 
             FROM user_achievements ua
             JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = ?
             ORDER BY ua.created_at ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
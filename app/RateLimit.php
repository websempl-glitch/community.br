<?php
declare(strict_types=1);

namespace App;

class RateLimit
{
    /**
     * Проверить, не заблокирован ли ключ.
     * Возвращает:
     *   null — всё ок, можно продолжать
     *   int  — секунд до разблокировки
     */
    public static function check(string $key): ?int
    {
        $stmt = Database::conn()->prepare(
            'SELECT blocked_until FROM rate_limits WHERE bucket_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row || $row['blocked_until'] === null) return null;

        $until = strtotime($row['blocked_until']);
        if ($until <= time()) return null;

        return $until - time();
    }

    /**
     * Зафиксировать попытку.
     * @param int $maxHits    сколько попыток разрешено в окне
     * @param int $windowSec  длина окна в секундах
     * @param int $blockSec   на сколько блокировать при превышении
     */
    public static function hit(string $key, int $maxHits, int $windowSec, int $blockSec): bool
    {
        $db = Database::conn();
        $now = time();

        $stmt = $db->prepare(
            'SELECT id, hits, window_start, blocked_until
             FROM rate_limits WHERE bucket_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        // Нет записи — создаём
        if (!$row) {
            $db->prepare(
                'INSERT INTO rate_limits (bucket_key, hits, window_start)
                 VALUES (?, 1, NOW())'
            )->execute([$key]);
            return false;
        }

        $windowStart  = strtotime($row['window_start']);
        $blockedUntil = $row['blocked_until'] ? strtotime($row['blocked_until']) : 0;

        // Если блокировка активна — не трогаем
        if ($blockedUntil > $now) return true;

        // Если окно истекло — сбрасываем счётчик
        if ($now - $windowStart > $windowSec) {
            $db->prepare(
                'UPDATE rate_limits SET hits = 1, window_start = NOW(), blocked_until = NULL
                 WHERE id = ?'
            )->execute([$row['id']]);
            return false;
        }

        $newHits = (int)$row['hits'] + 1;

        // Превышение — блокируем и сообщаем, что действие надо отменить
        if ($newHits > $maxHits) {
            $blockedTs = date('Y-m-d H:i:s', $now + $blockSec);
            $db->prepare(
                'UPDATE rate_limits SET hits = ?, blocked_until = ? WHERE id = ?'
            )->execute([$newHits, $blockedTs, $row['id']]);
            return true;
        }

        $db->prepare('UPDATE rate_limits SET hits = ? WHERE id = ?')
           ->execute([$newHits, $row['id']]);
        return false;
    }

    /**
     * Сбросить счётчик для ключа — обычно при успешном действии.
     */
    public static function clear(string $key): void
    {
        Database::conn()->prepare('DELETE FROM rate_limits WHERE bucket_key = ?')
            ->execute([$key]);
    }

    /**
     * Форматирует секунды до разблокировки в читаемый вид.
     */
    public static function humanWait(int $seconds): string
    {
        if ($seconds < 60) return 'меньше минуты';
        $minutes = (int)ceil($seconds / 60);
        return $minutes . ' ' . self::pluralMinutes($minutes) . ' подождите';
    }

    private static function pluralMinutes(int $n): string
    {
        $n = $n % 10;
        if ($n === 1) return 'минуту';
        if ($n >= 2 && $n <= 4) return 'минуты';
        return 'минут';
    }
}
<?php
declare(strict_types=1);

namespace App;

class OrphanCleaner
{
    private const BATCH_LIMIT = 50;
    private const AGE_HOURS   = 24;

    /**
     * Удаляет картинки, загруженные, но не привязанные к комментарию.
     * Возвращает количество удалённых записей.
     */
    public static function run(): int
    {
        $db = Database::conn();

        $stmt = $db->prepare(
            'SELECT id, path FROM comment_images
             WHERE comment_id IS NULL
               AND created_at < NOW() - INTERVAL ' . self::AGE_HOURS . ' HOUR
             ORDER BY id ASC
             LIMIT ' . self::BATCH_LIMIT
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (!$rows) return 0;

        $deleted = 0;
        foreach ($rows as $row) {
            $absPath = __DIR__ . '/../' . $row['path'];
            if (is_file($absPath)) {
                @unlink($absPath);
            }

            try {
                $db->prepare('DELETE FROM comment_images WHERE id = ?')
                   ->execute([(int)$row['id']]);
                $deleted++;
            } catch (\Throwable) {
                // Ошибка удаления одной записи — идём дальше, не падаем
            }
        }

        return $deleted;
    }
}
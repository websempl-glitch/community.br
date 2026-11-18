<h2 class="profile-tab-title">Закладки</h2>
<?php if (!$items): ?>
    <p class="hint">Пока нет сохранённых статей.</p>
<?php else: ?>
    <div class="profile-list">
        <?php foreach ($items as $a): ?>
            <a href="/article/<?= e($a['slug']) ?>" class="profile-list-item">
                <h3><?= e($a['title']) ?></h3>
                <?php if ($a['excerpt']): ?>
                    <p><?= e($a['excerpt']) ?></p>
                <?php endif; ?>
                <div class="meta">
                    Автор: <?= $a['author_deleted_at'] ? 'Удалённый пользователь' : e($a['author']) ?> 
                    · <?= e(date('d.m.Y', strtotime($a['published_at']))) ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?= renderPagination($page, $totalPages, '/u/'.$profileUser['username'].'/bookmarks') ?>
<?php endif; ?>
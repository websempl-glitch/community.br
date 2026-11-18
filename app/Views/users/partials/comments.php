<h2 class="profile-tab-title">Комментарии</h2>
<?php if (!$items): ?>
    <p class="hint">Пока нет комментариев.</p>
<?php else: ?>
    <div class="profile-list">
        <?php foreach ($items as $c): ?>
            <a href="/article/<?= e($c['article_slug']) ?>#c-<?= $c['id'] ?>" class="profile-list-item profile-comment-item">
                <div class="meta"><?= e($c['article_title']) ?> · <?= e($c['time_ago']) ?></div>
                <div class="body"><?= $c['preview_html'] ?></div>
            </a>
        <?php endforeach; ?>
    </div>
    <?= renderPagination($page, $totalPages, '/u/'.$profileUser['username'].'/comments') ?>
<?php endif; ?>
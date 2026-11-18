<h2 class="profile-tab-title">Черновики</h2>

<?php if ($msg = flash('draft_created_with_suggestion')): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (!$items): ?>
    <p class="hint">Черновиков нет.</p>
<?php else: ?>
    <div class="profile-list">
        <?php foreach ($items as $d): ?>
            <div class="profile-list-item draft-list-item">
                <h3><?= e($d['title']) ?></h3>
                <div class="meta">изменён <?= e(date('d.m.Y', strtotime($d['updated_at']))) ?></div>
                <div style="margin-top:12px; display:flex; gap:16px; align-items:center;">
                    <a href="/article/<?= e($d['slug']) ?>/edit" class="link-btn">Редактировать</a>
                    <button type="button" class="btn-secondary draft-publish-btn" data-draft-id="<?= (int)$d['id'] ?>" style="padding: 4px 10px; font-size: 13px;">Опубликовать</button>
                    <button type="button" class="btn-danger-link draft-delete-btn" data-draft-id="<?= (int)$d['id'] ?>">Удалить</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= renderPagination($page, $totalPages, '/u/'.$profileUser['username'].'/drafts') ?>
<?php endif; ?>
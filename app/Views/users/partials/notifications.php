<h2 class="profile-tab-title">Уведомления</h2>

<?php if (empty($items)): ?>
    <p class="hint">У вас пока нет уведомлений.</p>
<?php else: ?>
    <div class="profile-list">
        <?php foreach ($items as $item): ?>
            <a href="<?= e($item['url']) ?>" class="profile-list-item notif-page-item">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <div class="avatar" style="width:28px; height:28px; font-size:12px;">
						<?php if (!empty($item['actor_avatar'])): ?>
							<img src="<?= e($item['actor_avatar']) ?>" alt="">
						<?php else: ?>
							<?= e($item['actor_initial']) ?>
						<?php endif; ?>
					</div>
                    <strong><?= e($item['actor_username']) ?></strong>
                    <span style="color: var(--muted); font-size: 13px;">
                        <?= match($item['type']) {
							'reply' => 'ответил вам',
							'mention' => 'упомянул вас',
							'new_article' => 'опубликовал статью',
							'message' => 'отправил сообщение',
							default => 'уведомление'
						} ?>
                    </span>
                    <?php if (!$item['is_read']): ?>
                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background: var(--danger);"></span>
                    <?php endif; ?>
                </div>
                <p class="profile-comment-body" style="padding-left: 38px;"><?= e($item['preview']) ?></p>
                <div class="meta"><?= e($item['time_ago']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
    <?= renderPagination($page, $totalPages, '/u/'.$profileUser['username'].'/notifications') ?>
<?php endif; ?>
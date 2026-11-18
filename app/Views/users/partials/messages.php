<div class="messages-layout" x-data="{ selected: [], selectAll: false }">
    
    <div class="messages-header">
		<form method="get" action="/u/<?= e($profileUser['username']) ?>/messages" style="display:flex; gap:8px;">
			<input type="hidden" name="tab" value="<?= e($tab) ?>">
			<input type="text" name="q" value="<?= e($q ?? '') ?>" placeholder="Поиск по тексту или имени..." class="admin-input" style="flex:1;">
			<button type="submit" class="btn-secondary">Найти</button>
		</form>
        <div class="messages-tabs">
            <a href="/u/<?= e($profileUser['username']) ?>/messages?tab=inbox" class="msg-tab <?= $tab === 'inbox' ? 'is-active' : '' ?>">
                Входящие 
                <?php if ($unreadCount > 0): ?>
                    <span class="badge-new"><?= $unreadCount ?></span>
                <?php endif; ?>
                <span class="tab-total">/ <?= $inboxCount ?></span>
            </a>
            <a href="/u/<?= e($profileUser['username']) ?>/messages?tab=sent" class="msg-tab <?= $tab === 'sent' ? 'is-active' : '' ?>">
                Отправленные <span class="tab-total">/ <?= $sentCount ?></span>
            </a>
        </div>
    </div>

    <?php if (!$items): ?>
        <p class="hint" style="padding: 20px 0;">Здесь пока пусто.</p>
    <?php else: ?>
        <div class="message-list">
            <!-- Чекбокс "Выбрать все" -->
            <label class="message-item select-all-row checkbox input">
                <input type="checkbox" x-model="selectAll" @change="selected = $event.target.checked ? <?= htmlspecialchars(json_encode(array_column($items, 'id'))) ?> : []">
                <span style="font-size: 13px; color: var(--muted);">Выбрать все</span>
				<!-- Кнопка массового удаления (появляется при выборе) -->
				<button type="button" class="btn-danger-link" style="margin-left: auto;" x-show="selected.length > 0" x-cloak
						@click="
							if(confirm('Удалить выбранные сообщения?')) {
								const fd = new FormData();
								selected.forEach(id => fd.append('ids[]', id));
								fetch('/api/messages/bulk-delete', {
									method: 'POST',
									headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
									body: fd
								}).then(r => r.json()).then(data => {
									if(data.ok) window.location.reload();
								});
							}
						">
					Удалить (<span x-text="selected.length"></span>)
				</button>
            </label>

            <?php foreach ($items as $m): ?>
                <div class="message-item-wrapper" style="display:flex; align-items:center; gap:15px;">
					<label class="checkbox input">
						<input type="checkbox" value="<?= (int)$m['id'] ?>" x-model="selected">
					</label>
                    
                    <a href="/u/<?= e($profileUser['username']) ?>/messages/view/<?= (int)$m['id'] ?>" class="message-item <?= !$m['is_read'] ? 'is-unread' : '' ?>" style="flex:1; border-bottom:none;">
                        
                        <div class="message-item-info">
                            <div class="message-item-name">
								<div class="avatar" style="width: 40px; height: 40px; font-size: 16px;">
									<?php if (!empty($m['peer_avatar'])): ?>
										<img src="/<?= e($m['peer_avatar']) ?>" alt="">
									<?php else: ?>
										<?= e(mb_strtoupper(mb_substr($m['peer_username'], 0, 1))) ?>
									<?php endif; ?>
								</div>
								<span style="font-size: 15px; font-weight: 700;"><?= $tab === 'sent' ? 'Кому: ' : 'От: ' ?></span>
								<strong><?= e($m['peer_username']) ?></strong>
								<?php if ($tab === 'sent'): ?>
									<?php if (!empty($m['read_at'])): ?>
										<span style="color: #2f6f48; margin-left: 6px; font-size: 14px;" title="Прочитано">✓✓</span>
									<?php else: ?>
										<span style="color: var(--muted); margin-left: 6px; font-size: 14px;" title="Доставлено">✓</span>
									<?php endif; ?>
								<?php endif; ?>

								<span class="message-time"><?= e(time_ago($m['created_at'])) ?></span>
							</div>
                            <div class="message-item-preview body"><?= e(mb_substr(strip_tags($m['body']), 0, 80)) ?>...</div>
                        </div>
                        <?php if (!$m['is_read']): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php 
		// Формируем базовую ссылку
		$baseUrl = '/u/'.$profileUser['username'].'/messages?tab='.$tab;
		// Если есть поисковый запрос, добавляем его в ссылку
		if (!empty($q)) {
			$baseUrl .= '&q=' . urlencode($q);
		}
		?>
		<?= renderPagination($page, $totalPages, $baseUrl) ?>
    <?php endif; ?>
</div>
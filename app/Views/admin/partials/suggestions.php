<section class="admin-suggestions">
    <header class="page-head" style="margin-bottom: 24px;">
        <h1>Предложения разделов</h1>
        <p class="lead">Всего новых: <span id="suggestions-total"><?= count($suggestions) ?></span></p>
    </header>

    <?php if (!$suggestions): ?>
        <p class="hint" style="padding: 40px 0;">Новых предложений нет.</p>
    <?php else: ?>
        <div class="report-list">
            <?php foreach ($suggestions as $s): ?>
                <div class="report-card">
                    <div class="report-head">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="report-type-badge" style="background: #e8f0fa; color: #2c5282;">
                                <?= $s['parent_id'] ? 'Подраздел' : 'Раздел' ?>
                            </span>
                            <span>Автор: <strong><?= e($s['author']) ?></strong></span>
                        </div>
                        <span class="hint"><?= e(time_ago($s['created_at'])) ?></span>
                    </div>
                    
                    <h3 style="font-size: 18px; margin: 12px 0 8px;"><?= e($s['name']) ?></h3>
                    
                    <?php if ($s['parent_name']): ?>
                        <p style="font-size: 14px; color: var(--muted); margin-bottom: 8px;">
                            Будет создан внутри раздела <strong><?= e($s['parent_name']) ?></strong>
                        </p>
                    <?php endif; ?>

                    <?php if ($s['description']): ?>
                        <div class="report-note" style="margin-bottom: 12px;">
                            «<?= e($s['description']) ?>»
                        </div>
                    <?php endif; ?>

                    <div class="report-meta" style="margin-bottom: 16px;">
                        <span>Статей ждут одобрения: <strong><?= (int)$s['articles_count'] ?></strong></span>
                    </div>

                    <div class="report-actions" style="display: flex; gap: 16px; align-items: center;">
                        <button type="button" class="btn-primary approve-btn" data-id="<?= (int)$s['id'] ?>">Одобрить</button>
                        <button type="button" class="btn-danger-link reject-btn" data-id="<?= (int)$s['id'] ?>">Отклонить</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('click', async (e) => {
    const approveBtn = e.target.closest('.approve-btn');
    const rejectBtn = e.target.closest('.reject-btn');
    
    if (approveBtn) {
        if (!confirm('Создать раздел и опубликовать связанные статьи?')) return;
        approveBtn.disabled = true;
        approveBtn.textContent = '...';
        
        try {
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            const r = await fetch(`/api/admin/suggestions/${approveBtn.dataset.id}/approve`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
            });
            const data = await r.json();
            if (data.ok) {
                approveBtn.closest('.report-card').remove();
				
				// Обновляем счётчик
                const totalEl = document.getElementById('suggestions-total');
                if (totalEl) {
                    totalEl.textContent = Math.max(0, parseInt(totalEl.textContent) - 1);
                }
                
                // Если предложений не осталось — показываем заглушку
                const list = document.querySelector('.report-list');
                if (list && !list.querySelector('.report-card')) {
                    list.outerHTML = '<p class="hint" style="padding: 40px 0;">Новых предложений нет.</p>';
                }
            } else {
                alert(data.error || 'Ошибка');
                approveBtn.disabled = false;
                approveBtn.textContent = 'Одобрить';
            }
        } catch {
            alert('Ошибка сети');
            approveBtn.disabled = false;
            approveBtn.textContent = 'Одобрить';
        }
    }
    
    if (rejectBtn) {
        const reason = prompt('Причина отклонения (необязательно):');
        if (reason === null) return; // Отмена
        
        rejectBtn.disabled = true;
        rejectBtn.textContent = '...';
        
        const fd = new FormData();
        fd.append('reason', reason);
        
        try {
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            const r = await fetch(`/api/admin/suggestions/${rejectBtn.dataset.id}/reject`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: fd,
            });
            const data = await r.json();
            if (data.ok) {
                rejectBtn.closest('.report-card').remove();
				
				const totalEl = document.getElementById('suggestions-total');
                if (totalEl) {
                    totalEl.textContent = Math.max(0, parseInt(totalEl.textContent) - 1);
                }
                
                const list = document.querySelector('.report-list');
                if (list && !list.querySelector('.report-card')) {
                    list.outerHTML = '<p class="hint" style="padding: 40px 0;">Новых предложений нет.</p>';
                }
            } else {
                alert(data.error || 'Ошибка');
                rejectBtn.disabled = false;
                rejectBtn.textContent = 'Отклонить';
            }
        } catch {
            alert('Ошибка сети');
            rejectBtn.disabled = false;
            rejectBtn.textContent = 'Отклонить';
        }
    }
});
</script>
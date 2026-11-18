<section class="admin-reports">
    <header class="page-head" style="margin-bottom: 24px;">
        <h1>Жалобы</h1>
        <p class="lead">Всего новых: <span id="reports-total"><?= count($reports) ?></span></p>
    </header>

    <?php if (!$reports): ?>
        <p class="hint" style="padding:40px 0;">Новых жалоб нет. Всё хорошо!</p>
    <?php else: ?>
        <div class="report-list">
            <?php foreach ($reports as $r): ?>
                <div class="report-card" data-report-id="<?= (int)$r['id'] ?>" data-type="<?= $r['type'] ?>">
                    
                    <div class="report-head">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <?php if ($r['type'] === 'message'): ?>
                                <span class="report-type-badge badge-message">Личное сообщение</span>
                            <?php else: ?>
                                <span class="report-type-badge badge-comment">Комментарий</span>
                            <?php endif; ?>
                            <span>Автор: <strong><?= e($r['author']) ?></strong></span>
                        </div>
                        <span class="hint"><?= e(time_ago($r['created_at'])) ?></span>
                    </div>
                    
                    <?php if ($r['type'] === 'comment' && !empty($r['article_slug'])): ?>
                        <a href="/article/<?= e($r['article_slug']) ?>#c-<?= (int)$r['comment_id'] ?>" 
                           target="_blank" 
                           class="report-content-link" 
                           title="Открыть комментарий в контексте статьи">
                            <div class="report-content"><?= $r['body_html'] ?></div>
                        </a>
                    <?php else: ?>
                        <div class="report-content"><?= $r['body_html'] ?></div>
                    <?php endif; ?>
                    
                    <div class="report-meta">
                        <span>Жалоба от <strong><?= e($r['reporter']) ?></strong>:</span>
                        <span class="report-reason">
                            <?php
                            $reasonLabels = ['spam' => 'Спам', 'abuse' => 'Оскорбление', 'other' => 'Другое'];
                            echo $reasonLabels[$r['reason']] ?? $r['reason'];
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($r['note'])): ?>
                        <div class="report-note">«<?= e($r['note']) ?>»</div>
                    <?php endif; ?>

                    <div class="report-actions">
                        <button type="button" class="btn-danger resolve-btn" data-action="delete">Удалить</button>
                        <button type="button" class="link-btn resolve-btn" data-action="dismiss">Отклонить жалобу</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.resolve-btn');
    if (!btn) return;
    
    const card = btn.closest('.report-card');
    const reportId = card.dataset.reportId;
    const type = card.dataset.type;
    const action = btn.dataset.action;
    
    if (action === 'delete' && !confirm('Удалить этот контент?')) return;
    
    btn.disabled = true;
    btn.textContent = '...';
    
    const fd = new FormData();
    fd.append('action', action);
    fd.append('type', type); // Передаем тип (comment или message)
    
    try {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const resp = await fetch(`/api/admin/reports/${reportId}/resolve`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body: fd
        });
        const data = await resp.json();
        if (data.ok) {
            card.remove();

            // 1. Обновляем счётчик в шапке
            const headerBadge = document.getElementById('reports-badge');
            if (headerBadge) {
                if (data.remaining === 0) {
                    headerBadge.remove();
                } else {
                    headerBadge.textContent = data.remaining;
                }
            }

            // 2. Обновляем счётчик на странице жалоб
            const totalEl = document.getElementById('reports-total');
            if (totalEl) {
                totalEl.textContent = data.remaining;
            }

            // 3. Если жалоб не осталось — показываем заглушку
            const list = document.querySelector('.report-list');
            if (list && !list.querySelector('.report-card')) {
                list.innerHTML = '<p class="hint" style="padding:40px 0;">Новых жалоб нет. Всё хорошо!</p>';
            }
        } else {
            alert(data.error || 'Ошибка');
            btn.disabled = false;
            btn.textContent = action === 'delete' ? 'Удалить' : 'Отклонить жалобу';
        }
    } catch {
        alert('Ошибка сети');
        btn.disabled = false;
    }
});
</script>
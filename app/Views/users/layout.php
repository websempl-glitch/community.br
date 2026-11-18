<div class="layout-grid">
    <div class="layout-main">
        <?php
        // Подключаем нужную вкладку        
        if ($activeTab === 'messages') {
            if (isset($viewMode) && $viewMode) {
                require __DIR__ . '/partials/message-view.php';
            } elseif (isset($receiver)) {
                require __DIR__ . '/partials/message-create.php';
            } else {
                require __DIR__ . '/partials/messages.php';
            }
        } else {
            require __DIR__ . '/partials/' . $activeTab . '.php';
        }
        ?>
    </div>

<aside class="layout-sidebar">
        <div class="profile-sidebar-sticky">
            <h2 class="profile-tab-title" style="border:none; margin-bottom:16px; font-size:20px;">Навигация</h2>
            <!-- Навигация -->
            <div class="profile-nav">
                <a href="/u/<?= e($profileUser['username']) ?>" class="profile-nav-item <?= $activeTab === 'overview' ? 'is-active' : '' ?>">Профиль</a>
                <a href="/u/<?= e($profileUser['username']) ?>/articles" class="profile-nav-item <?= $activeTab === 'articles' ? 'is-active' : '' ?>">
                    Статьи <span class="nav-count"><?= $stats['articles'] ?></span>
                </a>
                <a href="/u/<?= e($profileUser['username']) ?>/comments" class="profile-nav-item <?= $activeTab === 'comments' ? 'is-active' : '' ?>">
                    Комментарии <span class="nav-count"><?= $stats['comments'] ?></span>
                </a>
                
                <?php if ($isOwnProfile): ?>
					<a href="/u/<?= e($profileUser['username']) ?>/messages" class="profile-nav-item <?= $activeTab === 'messages' ? 'is-active' : '' ?>">
                        Сообщения
                    </a>
                    <a href="/u/<?= e($profileUser['username']) ?>/notifications" class="profile-nav-item <?= $activeTab === 'notifications' ? 'is-active' : '' ?>">
                        Уведомления
                    </a>
                    <?php if ($stats['drafts'] > 0): ?>
                    <a href="/u/<?= e($profileUser['username']) ?>/drafts" class="profile-nav-item <?= $activeTab === 'drafts' ? 'is-active' : '' ?>">
                        Черновики <span class="nav-count"><?= $stats['drafts'] ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($stats['bookmarks'] > 0): ?>
                    <a href="/u/<?= e($profileUser['username']) ?>/bookmarks" class="profile-nav-item <?= $activeTab === 'bookmarks' ? 'is-active' : '' ?>">
                        Закладки <span class="nav-count"><?= $stats['bookmarks'] ?></span>
                    </a>
                    <?php endif; ?>

                    <?php if ($stats['subs'] > 0): ?>
                    <a href="/u/<?= e($profileUser['username']) ?>/subscriptions" class="profile-nav-item <?= $activeTab === 'subscriptions' ? 'is-active' : '' ?>">
                        Подписки <span class="nav-count"><?= $stats['subs'] ?></span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </aside>
</div>

<script>
function followControl(userId, initial) {
    return {
        userId, following: !!initial, busy: false,
        async toggle() {
            if (this.busy) return; this.busy = true;
            try {
                const r = await fetch(`/api/users/${this.userId}/follow`, { method: 'POST', headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } });
                const data = await r.json();
                if (data.ok) this.following = !!data.following;
            } catch {}
            this.busy = false;
        }
    };
}
</script>

<script>
// --- Публикация черновика ---
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.draft-publish-btn');
    if (!btn) return;

    const id = btn.dataset.draftId;
    if (!id) return;
    if (!confirm('Опубликовать эту статью?')) return;

    btn.disabled = true;
    btn.textContent = 'Публикуем…';

    try {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const r = await fetch(`/api/articles/${id}/publish`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        window.location.reload();
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.textContent = 'Опубликовать';
    }
});

// --- Удаление черновика ---
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.draft-delete-btn');
    if (!btn) return;

    const id = btn.dataset.draftId;
    if (!id) return;
    if (!confirm('Удалить этот черновик безвозвратно?')) return;

    btn.disabled = true;
    btn.textContent = 'Удаление…';

    try {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const r = await fetch(`/api/articles/${id}/delete`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');

        // Убираем карточку из списка
        const card = btn.closest('.profile-list-item');
        if (card) card.remove();

        // Если черновиков не осталось — перезагружаем, чтобы обновить счетчик в меню
        const list = document.querySelector('.profile-list');
        if (list && !list.querySelector('.draft-list-item')) {
            window.location.reload();
        }
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.textContent = 'Удалить';
    }
});
</script>
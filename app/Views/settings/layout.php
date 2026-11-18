<div class="layout-grid">
    <div class="layout-main">
		<a href="/u/<?= e(\App\Auth::user()['username']) ?>" class="back-to-profile">← Вернуться в профиль</a>
        <?php require __DIR__ . '/partials/' . $contentView . '.php'; ?>
    </div>

    <aside class="layout-sidebar">
        <div class="profile-sidebar-sticky">
            <h2 class="profile-tab-title" style="border:none; margin-bottom:16px; font-size:20px;">Настройки</h2>
            
            <div class="profile-nav">
                <a href="/settings" class="profile-nav-item <?= $activeTab === 'profile' ? 'is-active' : '' ?>">Профиль</a>
                <a href="/settings/password" class="profile-nav-item <?= $activeTab === 'password' ? 'is-active' : '' ?>">Пароль</a>
                <a href="/settings/email" class="profile-nav-item <?= $activeTab === 'email' ? 'is-active' : '' ?>">Email</a>
				<a href="/settings/notifications" class="profile-nav-item <?= $activeTab === 'notifications' ? 'is-active' : '' ?>">Уведомления</a>
                
                <?php if (!in_array(\App\Auth::user()['role'], ['admin', 'owner'], true)): ?>
					<a href="/settings/delete" class="profile-nav-item <?= $activeTab === 'delete' ? 'is-active' : '' ?>" style="color: var(--danger);">Удаление</a>
				<?php endif; ?>
            </div>
        </div>
    </aside>
</div>
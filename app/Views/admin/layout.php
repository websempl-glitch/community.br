<div class="layout-grid">
    <div class="layout-main">
        <a href="/" class="back-to-profile">← Вернуться на сайт</a>
        <?php require __DIR__ . '/partials/' . $contentView . '.php'; ?>
    </div>

    <aside class="layout-sidebar">
        <div class="profile-sidebar-sticky">
            <h2 class="profile-tab-title" style="border:none; margin-bottom:16px; font-size:20px;">Админка</h2>
            <div class="profile-nav">
                <a href="/admin" class="profile-nav-item <?= $activeTab === 'dashboard' ? 'is-active' : '' ?>">Дашборд</a>
                <a href="/admin/categories" class="profile-nav-item <?= $activeTab === 'categories' ? 'is-active' : '' ?>">Разделы</a>
				<a href="/admin/users" class="profile-nav-item <?= $activeTab === 'users' ? 'is-active' : '' ?>">Пользователи</a>
                <a href="/admin/reports" class="profile-nav-item <?= $activeTab === 'reports' ? 'is-active' : '' ?>">Жалобы</a>
				<a href="/admin/suggestions" class="profile-nav-item <?= $activeTab === 'suggestions' ? 'is-active' : '' ?>">Предложения</a>
				<a href="/admin/settings" class="profile-nav-item <?= $activeTab === 'settings' ? 'is-active' : '' ?>">Настройки</a>
            </div>
        </div>
    </aside>
</div>
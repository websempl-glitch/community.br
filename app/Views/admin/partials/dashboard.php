<h2 class="profile-tab-title">Обзор</h2>
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-value"><?= (int)$stats['users'] ?></div>
        <div class="stat-label">Пользователей</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-value"><?= (int)$stats['articles'] ?></div>
        <div class="stat-label">Статей</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-value"><?= (int)$stats['comments'] ?></div>
        <div class="stat-label">Комментариев</div>
    </div>
    <div class="admin-stat-card" style="border-color: #f5d5d0;">
        <div class="stat-value" style="color: var(--danger);"><?= (int)$stats['reports'] ?></div>
        <div class="stat-label">Активных жалоб</div>
    </div>
</div>
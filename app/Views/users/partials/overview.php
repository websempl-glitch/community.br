<h2 class="profile-tab-title">Профиль</h2>
<div class="profile-main-card">
    <div class="profile-main-header">
        
        <div class="profile-avatar-lg">
            <?php if (!empty($profileUser['avatar_path'])): ?>
                <img src="/<?= e($profileUser['avatar_path']) ?>" alt="">
            <?php else: ?>
                <span><?= e(mb_strtoupper(mb_substr($profileUser['username'], 0, 1))) ?></span>
            <?php endif; ?>
        </div>
        
        <div class="profile-main-info">
            <h1 class="profile-name"><?= e($profileUser['username']) ?></h1>
            
            <div class="profile-badges">
                <span class="status-badge status-<?= e($statusKey) ?>"><?= e(\App\UserStatus::label($statusKey)) ?></span>
                <?php if ($profileUser['role'] === 'owner'): ?>
                    <span class="status-badge role-badge" style="background: #1a1a1a; color: #fafaf9;">Владелец</span>
                <?php elseif ($profileUser['role'] === 'admin'): ?>
                    <span class="role-badge">Администратор</span>
                <?php endif; ?>
                <span class="hint" style="margin-left: 8px;">на сайте с <?= e(date('d.m.Y', strtotime($profileUser['created_at']))) ?></span>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= (int)$stats['articles'] ?></span>
                    <span class="stat-label">статей</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= (int)$stats['comments'] ?></span>
                    <span class="stat-label">комментариев</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= (int)$stats['followers'] ?></span>
                    <span class="stat-label">подписчиков</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= (int)$stats['karma'] ?></span>
                    <span class="stat-label">кармы</span>
                </div>
            </div>

            <?php if ($profileUser['bio']): ?>
                <p class="profile-bio-full"><?= nl2br(e($profileUser['bio'])) ?></p>
            <?php endif; ?>

            <div class="profile-actions" style="margin-top: 24px;">
                <?php if ($isOwnProfile): ?>
                    <a href="/settings" class="btn-secondary">Настройки профиля</a>
                <?php elseif (\App\Auth::check()): ?>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div x-data="followControl(<?= (int)$profileUser['id'] ?>, <?= $isFollowing ? 'true' : 'false' ?>)">
                            <button type="button" class="btn-secondary follow-btn" 
                                    :class="{ 'is-active': following }" 
                                    @click="toggle()" :disabled="busy">
                                <span x-show="!following">Подписаться</span>
                                <span x-show="following">Вы подписаны</span>
                            </button>
                        </div>
                        <a href="/u/<?= e($profileUser['username']) ?>/message" class="btn-primary">Написать</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Достижения -->
    <?php if (!empty($achievements)): ?>
    <div class="profile-achievements-section">
        <h3 style="font-size: 12px; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; letter-spacing: 0.05em;">Достижения</h3>
        <div class="achievements-grid" style="justify-content: flex-start;">
            <?php foreach ($achievements as $ach): ?>
                <div class="achievement-item" title="<?= e($ach['name']) ?>: <?= e($ach['description']) ?>">
                    <span class="achievement-icon"><?= $ach['icon'] ?></span>
                    <span class="achievement-name"><?= e($ach['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
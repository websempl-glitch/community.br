<div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom: 24px;">
    <h2 class="profile-tab-title" style="border:none; margin:0; padding:0;">Пользователи (<?= $totalCount ?>)</h2>
    
    <form method="get" action="/admin/users" style="display:flex; gap:8px;">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Поиск по нику или email" class="admin-input" style="width: 250px;">
        <button type="submit" class="btn-secondary">Найти</button>
    </form>
</div>

<div class="admin-users-list">
    <?php foreach ($users as $u): ?>
        <div class="admin-user-card" style="<?= $u['banned_at'] ? 'opacity: 0.6; background: #fdf0ee; border-color: #f5d5d0;' : '' ?>">
            <div class="admin-user-avatar">
                <?php if ($u['avatar_path']): ?>
                    <img src="/<?= e($u['avatar_path']) ?>" alt="">
                <?php else: ?>
                    <?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            
            <div class="admin-user-info">
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="/u/<?= e($u['username']) ?>" target="_blank" style="font-size: 16px; font-weight: 600; text-decoration: none; color: var(--text);">
                        <?= e($u['username']) ?>
                    </a>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="role-badge" style="font-size:10px; padding: 2px 6px;">Админ</span>
                    <?php endif; ?>
                    <?php if ($u['banned_at']): ?>
                        <span class="status-badge" style="background:#c0392b; color:#fff; font-size:10px; padding: 2px 6px;">Забанен</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 13px; color: var(--muted); margin-top: 2px;">
                    <?= e($u['email']) ?> · с <?= e(date('d.m.Y', strtotime($u['created_at']))) ?>
                </div>
            </div>

            <div class="admin-user-actions">
                <!-- Если это владелец, мы не показываем кнопки управления -->
                <?php if ($u['role'] === 'owner'): ?>
                    <span class="status-badge" style="background:var(--text); color:var(--bg);">Владелец</span>
                
                <?php else: ?>
                    <?php 
                    // Показываем кнопку роли, если мы владелец, ИЛИ если мы админ и цель - обычный юзер
                    $canChangeRole = ($currentRole === 'owner') || ($currentRole === 'admin' && $u['role'] === 'user');
                    ?>
                    
                    <?php if ($canChangeRole): ?>
                    <form method="post" action="/admin/users/<?= $u['id'] ?>/role" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                        <button type="submit" class="btn-secondary" style="padding: 6px 10px; font-size: 12px;">
                            <?= $u['role'] === 'admin' ? 'Снять админа' : 'Сделать админом' ?>
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- Кнопка бана (та же логика) -->
                    <?php 
                    $canBan = ($currentRole === 'owner') || ($currentRole === 'admin' && $u['role'] === 'user');
                    if ($canBan): 
                    ?>
                    <form method="post" action="/admin/users/<?= $u['id'] ?>/ban" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="<?= $u['banned_at'] ? 'unban' : 'ban' ?>">
                        <button type="submit" class="<?= $u['banned_at'] ? 'btn-secondary' : 'btn-danger-link' ?>" style="padding: 6px 10px; font-size: 12px;">
                            <?= $u['banned_at'] ? 'Разбанить' : 'Забанить' ?>
                        </button>
                    </form>
                    <?php endif; ?>
					<!-- КНОПКА УДАЛЕНИЯ (только для владельца) -->
                    <?php if ($currentRole === 'owner'): ?>
                        <form method="post" action="/admin/users/<?= $u['id'] ?>/delete" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn-danger-link" style="padding: 6px 10px; font-size: 12px;" 
                                    onclick="return confirm('Вы уверены? Аккаунт будет анонимизирован, статьи и комментарии останутся.');">
                                Удалить
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Пагинация -->
<?php if ($totalPages > 1): ?>
    <div class="pagination pagination-profile">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="/admin/users?page=<?= $i ?>&q=<?= urlencode($query) ?>" 
               class="pagination-item <?= $i === $page ? 'active' : '' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
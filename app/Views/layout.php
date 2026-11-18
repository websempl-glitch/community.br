<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Статьи') ?></title>

    <script>
        // Скрипт для моментального применения темы до отрисовки (чтобы не было вспышки)
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <script src="/public/assets/comments.js" defer></script>
	<script src="/public/assets/ui.js" defer></script>
    <script src="/public/assets/editor.js" defer></script>
	<script src="/public/assets/notifications.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/highlight.min.js" defer></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github-dark.min.css" id="hljs-theme">
    <link rel="stylesheet" href="/public/assets/style.css">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="logo">Статьи</a>
        
        <div class="nav-right">
            <!-- Тема -->
            <button type="button" class="icon-btn theme-toggle" onclick="toggleTheme()" title="Сменить тему">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>

            <!-- Поиск -->
            <a href="/search" class="icon-btn" title="Поиск">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </a>

            <?php if (\App\Auth::check()): $u = \App\Auth::user(); ?>
                <?php if (in_array($u['role'], ['admin', 'owner'])): ?>
                    <?php 
                    // Считаем общее количество непроверенных жалоб (комменты + сообщения)
                    $reportCount = 0;
                    try {
                        $stmtR = \App\Database::conn()->query(
                            'SELECT (SELECT COUNT(*) FROM comment_reports WHERE status="pending") + 
                                    (SELECT COUNT(*) FROM message_reports WHERE status="pending") AS total'
                        );
                        $reportCount = (int)$stmtR->fetchColumn();
                    } catch (\Throwable) {}
                    ?>
                    <a href="/admin/reports" class="icon-btn" title="Жалобы" style="position: relative;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        
                        <?php if ($reportCount > 0): ?>
                            <span class="notification-badge" id="reports-badge" style="right: -2px; top: -2px;"><?= $reportCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>			
				<!-- Колокольчик -->
				<div x-data="notificationsApp" style="position: relative;">
                    <button type="button" class="icon-btn" @click="toggle()" title="Уведомления">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <template x-if="unreadCount > 0">
                            <span class="notification-badge" x-text="unreadCount"></span>
                        </template>
                    </button>

                    <div class="notifications-panel" x-show="isOpen" x-cloak @click.outside="close()">
                        <div class="notif-head">
                            <h3>Уведомления</h3>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <button class="link-btn" style="font-size:12px;" 
                                        @click="markAllRead()" 
                                        x-show="unreadCount > 0">Прочитать всё</button>
                                <button class="link-btn" style="font-size:18px;" @click="isOpen = false">×</button>
                            </div>
                        </div>
                        <div class="notif-list">
                            <template x-if="loading">
                                <p class="hint" style="padding: 16px;">Загрузка…</p>
                            </template>
                            <template x-if="!loading && items.length === 0">
                                <p class="hint" style="padding: 16px;">Нет уведомлений.</p>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <a :href="item.url" @click="goTo(item, $event)" 
                                   class="notif-item" 
                                   :class="{ 'is-unread': !item.is_read }">
                                    <div class="avatar" :class="{ 'has-image': !!item.actor_avatar }" style="width:32px; height:32px; font-size:12px;">
										<template x-if="item.actor_avatar">
											<img :src="item.actor_avatar" alt="" @error="item.actor_avatar = null">
										</template>
										<span x-show="!item.actor_avatar" x-text="item.actor_initial"></span>
									</div>
                                    <div class="notif-content">
                                        <div class="notif-text">
                                            <strong x-text="item.actor_username"></strong>
                                            <span x-text="item.type === 'reply' ? 'ответил вам' : (item.type === 'mention' ? 'упомянул вас' : (item.type === 'new_article' ? 'опубликовал статью' : 'отправил сообщение'))"></span>
                                        </div>
                                        <div class="notif-preview" x-text="item.preview"></div>
                                        <div class="notif-time" x-text="item.time_ago"></div>
                                    </div>
                                </a>
                            </template>
                        </div>
                        <?php if (\App\Auth::check()): ?>
                        <div class="notif-footer">
                            <a href="/u/<?= e(\App\Auth::user()['username']) ?>/notifications" class="link-btn" style="font-size:13px;">
                                Все уведомления →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>                
                <a href="/article/create" class="nav-write-btn">Написать</a>

                <!-- Профиль -->
                <div class="nav-profile">
                    <a href="/u/<?= e($u['username']) ?>" class="nav-profile-link">
                        <div class="avatar" style="width:28px; height:28px; font-size:12px;">
                            <?php if (!empty($u['avatar_path'])): ?>
                                <img src="/<?= e($u['avatar_path']) ?>" alt="">
                            <?php else: ?>
                                <?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?>
                            <?php endif; ?>
                        </div>
                        <span><?= e($u['username']) ?></span>
                    </a>
                    <form method="post" action="/logout" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="icon-btn" title="Выйти">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a href="/login" class="btn-primary" style="padding: 6px 16px; font-size: 14px;">Войти</a>
				<?php if (setting('registration_enabled', '1') === '1'): ?>
					<a href="/register" class="btn-primary" style="padding: 6px 16px; font-size: 14px;">Регистрация</a>
				<?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container main-content">
    <?= $content ?>
</main>

<script>
function toggleTheme() {
    const root = document.documentElement;
    const current = root.getAttribute('data-theme');
    const next = current === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    
    // Переключаем тему для подсветки кода
    const hljs = document.getElementById('hljs-theme');
    if (hljs) {
        hljs.href = next === 'light' 
            ? 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github-dark.min.css'
            : 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github.min.css';
    }
}
// Синхронизация темы при загрузке
document.addEventListener('DOMContentLoaded', () => {
    const hljs = document.getElementById('hljs-theme');
    if (hljs && document.documentElement.getAttribute('data-theme') === 'dark') {
        hljs.href = 'https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/github.min.css';
    }
});
</script>

</body>
</html>
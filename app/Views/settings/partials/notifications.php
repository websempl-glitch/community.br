<header class="page-head" style="margin-bottom: 32px;">
    <h1>Настройки уведомлений</h1>
    <p class="lead">Выберите, о каких событиях вы хотите получать уведомления.</p>
</header>

<?php if (!empty($toast)): ?>
    <div class="alert alert-success"><?= e($toast) ?></div>
<?php endif; ?>

<form method="post" action="/settings/notifications" class="settings-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="settings-section">
        <div class="settings-section-head">
            <h2>События</h2>
            <p class="hint">Уведомления приходят в колокольчик в шапке сайта.</p>
        </div>

        <label class="checkbox" style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" name="notify_replies" value="1" <?= $settings['notify_replies'] ? 'checked' : '' ?>>
            <span>Ответы на мои комментарии</span>
        </label>

        <label class="checkbox" style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" name="notify_mentions" value="1" <?= $settings['notify_mentions'] ? 'checked' : '' ?>>
            <span>Упоминания меня через <code>@username</code></span>
        </label>
		
		<label class="checkbox" style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" name="notify_new_articles" value="1" <?= ($settings['notify_new_articles'] ?? 1) ? 'checked' : '' ?>>
            <span>Новые статьи от авторов, на которых я подписан</span>
        </label>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn-primary">Сохранить настройки</button>
        <a href="/settings" class="link-btn">Отмена</a>
    </div>
</form>
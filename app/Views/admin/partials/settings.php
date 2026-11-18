<h2 class="profile-tab-title">Настройки сайта</h2>

<?php if (!empty($toast)): ?>
    <div class="alert alert-success"><?= e($toast) ?></div>
<?php endif; ?>

<form method="post" action="/admin/settings" class="form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <!-- Общие настройки -->
    <div class="admin-cat-group" style="margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; font-size: 16px;">Общие настройки</h3>
        <div class="admin-form-grid">
            <label class="field">
                <span>Название сайта</span>
                <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>" class="admin-input" placeholder="Мой блог">
            </label>
            <label class="field">
                <span>Описание (для SEO)</span>
                <input type="text" name="site_description" value="<?= e($settings['site_description'] ?? '') ?>" class="admin-input" placeholder="Блог о технологиях">
            </label>
        </div>

        <div class="admin-form-grid" style="margin-top: 16px;">
            <label class="field">
                <span>Приветствие на главной</span>
                <textarea name="home_welcome_text" class="admin-input" style="height: 80px; padding: 10px; resize: vertical;" placeholder="Добро пожаловать на наш блог!"><?= e($settings['home_welcome_text'] ?? '') ?></textarea>
            </label>
            
            <label class="field" style="justify-content: center;">
                <span>Регистрация</span>
                <label class="checkbox" style="margin-top: 8px;">
                    <input type="checkbox" name="registration_enabled" value="1" <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span>Открыта для новых пользователей</span>
                </label>
            </label>
        </div>
    </div>

    <!-- Настройки почты -->
    <div class="admin-cat-group" style="margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; font-size: 16px;">Отправка писем</h3>
        
        <div class="admin-form-grid">
            <label class="field">
                <span>Режим</span>
                <select name="mail_driver" class="admin-input">
                    <option value="log" <?= ($settings['mail_driver'] ?? 'log') === 'log' ? 'selected' : '' ?>>Запись в файл (тест)</option>
                    <option value="smtp" <?= ($settings['mail_driver'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP (боевой)</option>
                </select>
            </label>
            
            <label class="field">
                <span>Email отправителя</span>
                <input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>" class="admin-input" placeholder="noreply@site.ru">
            </label>
        </div>

        <hr style="border:0; border-top: 1px solid var(--border); margin: 20px 0;">

        <div class="admin-form-grid">
            <label class="field">
                <span>SMTP Хост</span>
                <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" class="admin-input" placeholder="smtp.yandex.ru">
            </label>
            <label class="field">
                <span>Порт</span>
                <input type="number" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '465') ?>" class="admin-input">
            </label>
            <label class="field">
                <span>Шифрование</span>
                <select name="smtp_secure" class="admin-input">
                    <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (порт 465)</option>
                    <option value="tls" <?= ($settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (порт 587)</option>
                </select>
            </label>
        </div>

        <div class="admin-form-grid" style="margin-top: 16px;">
            <label class="field">
                <span>Логин (обычно email)</span>
                <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>" class="admin-input">
            </label>
            <label class="field">
                <span>Пароль (или пароль приложения)</span>
                <input type="password" name="smtp_pass" value="<?= e($settings['smtp_pass'] ?? '') ?>" class="admin-input">
            </label>
        </div>
    </div>

    <div class="settings-actions">
        <button type="submit" class="btn-primary">Сохранить настройки</button>
    </div>
</form>
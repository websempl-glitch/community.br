<header class="page-head" style="margin-bottom: 32px;">
    <h1>Настройки профиля</h1>
    <p class="lead">Здесь можно изменить bio и аватар.</p>
</header>

<?php if (!empty($toast)): ?>
    <div class="alert alert-success" id="settings-toast"><?= e($toast) ?></div>
<?php endif; ?>

<form method="post" action="/settings" enctype="multipart/form-data" class="settings-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <!-- Аватар -->
    <div class="settings-section">
        <div class="settings-section-head">
            <h2>Аватар</h2>
            <p class="hint">JPEG, PNG или WebP, до 5 МБ. Обрезается по центру в квадрат.</p>
        </div>

        <div class="avatar-row">
            <div class="avatar-preview" id="avatar-preview">
                <?php
                // ИСПРАВЛЕНО: теперь 4 уровня вверх (../../../../), так как мы в папке partials
                $hasAvatar = false;
                $avatarUrl = '';
                if (!empty($user['avatar_path'])) {
                    $absPath = __DIR__ . '/../../../../' . $user['avatar_path'];
                    if (is_file($absPath)) {
                        $hasAvatar = true;
                        $avatarUrl = '/' . $user['avatar_path'] . '?v=' . filemtime($absPath);
                    }
                }
                ?>
                <?php if ($hasAvatar): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="">
                <?php else: ?>
                    <span><?= e(mb_strtoupper(mb_substr($user['username'], 0, 1))) ?></span>
                <?php endif; ?>
            </div>

            <div class="avatar-actions">
                <label class="btn-secondary">
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                           id="avatar-input" hidden>
                    Выбрать файл
                </label>
                <button type="button" class="link-btn"
                        id="avatar-remove"
                        data-has-avatar="<?= !empty($user['avatar_path']) ? '1' : '0' ?>">
                    Удалить аватар
                </button>
            </div>
        </div>

        <?php if (!empty($errors['avatar'])): ?>
            <small class="error" style="color:var(--danger); display:block; margin-top:8px;">
                <?= e($errors['avatar']) ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- Bio -->
    <div class="settings-section">
        <div class="settings-section-head">
            <h2>О себе</h2>
            <p class="hint">До 300 символов.</p>
        </div>

        <textarea name="bio"
                  class="textarea"
                  rows="4"
                  maxlength="300"
                  placeholder="Пара строк о себе…"><?= e($old['bio'] ?? $user['bio'] ?? '') ?></textarea>

        <div class="bio-counter">
            <span id="bio-count">0</span> / 300
        </div>

        <?php if (!empty($errors['bio'])): ?>
            <small class="error" style="color:var(--danger);"><?= e($errors['bio']) ?></small>
        <?php endif; ?>
    </div>

    <!-- Секции "Пароль" и "Учётная запись" УДАЛЕНЫ, так как они теперь в правом меню -->

    <div class="settings-actions">
        <button type="submit" class="btn-primary">Сохранить</button>
    </div>
</form>

<script>
(function () {
    // Счётчик bio
    const bio = document.querySelector('textarea[name=bio]');
    const count = document.getElementById('bio-count');
    function updateCount() { count.textContent = (bio.value || '').length; }
    bio.addEventListener('input', updateCount);
    updateCount();

    // Предпросмотр аватара при выборе файла
    const input = document.getElementById('avatar-input');
    const preview = document.getElementById('avatar-preview');
    input.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        preview.innerHTML = '<img src="' + url + '" alt="">';
    });

    // Удаление аватара
    const removeBtn = document.getElementById('avatar-remove');
    removeBtn.addEventListener('click', async () => {
        if (removeBtn.dataset.hasAvatar !== '1') {
            alert('У вас нет аватара.');
            return;
        }
        if (!confirm('Удалить аватар?')) return;

        const csrf = document.querySelector('meta[name=csrf-token]').content;
        try {
            const r = await fetch('/settings/avatar/delete', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
            });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Ошибка');

            const initial = preview.querySelector('span')?.textContent
                         || document.querySelector('.nav-profile-link').textContent.trim().charAt(0).toUpperCase();
            preview.innerHTML = '<span>' + initial + '</span>';
            removeBtn.dataset.hasAvatar = '0';
        } catch (e) {
            alert(e.message);
        }
    });
})();
</script>
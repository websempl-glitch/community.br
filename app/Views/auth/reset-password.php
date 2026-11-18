<section class="auth">
    <header class="auth-head">
        <h1>Новый пароль</h1>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($token)): ?>
        <form method="post" action="/reset-password" class="form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label class="field">
                <span>Новый пароль</span>
                <input type="password" name="password" required minlength="8">
                <small class="hint">Минимум 8 символов</small>
            </label>

            <label class="field">
                <span>Повторите пароль</span>
                <input type="password" name="confirm" required minlength="8">
            </label>

            <button type="submit" class="btn-primary btn-block">Сохранить пароль</button>
        </form>
    <?php else: ?>
        <div style="text-align: center; margin-top: 20px;">
            <a href="/forgot-password" class="btn-primary">Запросить новую ссылку</a>
        </div>
    <?php endif; ?>
</section>
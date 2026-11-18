<section class="auth">
    <header class="auth-head">
        <h1>Вход</h1>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <label class="field">
            <span>Email</span>
            <input type="email" name="email" required
                   value="<?= e($old['email'] ?? '') ?>">
        </label>

        <label class="field">
            <span>Пароль</span>
            <input type="password" name="password" required>
        </label>
		
		<?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">Пароль успешно изменён. Войдите с новым паролем.</div>
        <?php endif; ?>
		
        <label class="checkbox remember-row">
            <input type="checkbox" name="remember" value="1" checked>
            <span>Запомнить меня на 30 дней</span>
        </label>

        <button type="submit" class="btn-primary btn-block">Войти</button>
    </form>
	
	<div style="text-align: right; margin-top: -10px; margin-bottom: 20px;">
        <a href="/forgot-password" class="hint" style="text-decoration: underline;">Забыли пароль?</a>
    </div>
	
	<?php if (setting('registration_enabled', '1') === '1'): ?>
		<p class="auth-alt">Нет аккаунта? <a href="/register">Регистрация</a></p>
	<?php endif; ?>
</section>
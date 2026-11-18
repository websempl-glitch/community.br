<section class="auth">
    <header class="auth-head">
        <h1>Регистрация</h1>
        <p class="lead">Минимум данных. Подтверждение — по почте.</p>
    </header>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>
	
	<?php if (setting('registration_enabled', '1') === '1'): ?>
		<form method="post" action="/register" class="form" novalidate>
			<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

			<label class="field">
				<span>Email</span>
				<input type="email" name="email" required
					   value="<?= e($old['email'] ?? '') ?>"
					   class="<?= !empty($errors['email']) ? 'has-error' : '' ?>">
				<?php if (!empty($errors['email'])): ?>
					<small class="error"><?= e($errors['email']) ?></small>
				<?php endif; ?>
			</label>

			<label class="field">
				<span>Имя пользователя</span>
				<input type="text" name="username" required
					   value="<?= e($old['username'] ?? '') ?>"
					   class="<?= !empty($errors['username']) ? 'has-error' : '' ?>">
				<?php if (!empty($errors['username'])): ?>
					<small class="error"><?= e($errors['username']) ?></small>
				<?php endif; ?>
			</label>

			<label class="field">
				<span>Пароль</span>
				<input type="password" name="password" required minlength="8"
					   class="<?= !empty($errors['password']) ? 'has-error' : '' ?>">
				<?php if (!empty($errors['password'])): ?>
					<small class="error"><?= e($errors['password']) ?></small>
				<?php else: ?>
					<small class="hint">Минимум 8 символов</small>
				<?php endif; ?>
			</label>

			<button type="submit" class="btn-primary btn-block">Создать аккаунт</button>
		</form>

		<p class="auth-alt">Уже есть аккаунт? <a href="/login">Войти</a></p>
	<?php else: ?>
		<div style="text-align: center; margin-top: 24px;">
            <p class="auth-alt" style="margin-bottom: 16px;">Если у вас уже есть аккаунт, вы можете войти.</p>
            <a href="/login" class="btn-primary">Войти в аккаунт</a>
        </div>

    <?php endif; ?>
</section>
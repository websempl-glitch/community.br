<header class="settings-head">
	<h1>Смена пароля</h1>
	<p class="lead">После смены пароля все другие устройства будут разлогинены.</p>
</header>

<?php if (!empty($toast)): ?>
	<div class="alert alert-success"><?= e($toast) ?></div>
<?php endif; ?>

<form method="post" action="/settings/password" class="settings-form">
	<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

	<div class="settings-section">
		<div class="settings-section-head">
			<h2>Пароль</h2>
			<p class="hint">Минимум 8 символов.</p>
		</div>

		<label class="field">
			<span>Текущий пароль</span>
			<input type="password" name="current" required autocomplete="current-password"
				   class="<?= !empty($errors['current']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['current'])): ?>
				<small class="error"><?= e($errors['current']) ?></small>
			<?php endif; ?>
		</label>

		<label class="field">
			<span>Новый пароль</span>
			<input type="password" name="new" required minlength="8" autocomplete="new-password"
				   class="<?= !empty($errors['new']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['new'])): ?>
				<small class="error"><?= e($errors['new']) ?></small>
			<?php endif; ?>
		</label>

		<label class="field">
			<span>Повторите новый пароль</span>
			<input type="password" name="confirm" required minlength="8" autocomplete="new-password"
				   class="<?= !empty($errors['confirm']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['confirm'])): ?>
				<small class="error"><?= e($errors['confirm']) ?></small>
			<?php endif; ?>
		</label>
	</div>

	<div class="settings-actions">
		<button type="submit" class="btn-primary">Изменить пароль</button>
	</div>
</form>
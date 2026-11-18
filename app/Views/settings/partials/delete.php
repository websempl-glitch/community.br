<header class="settings-head">
	<h1>Удаление аккаунта</h1>
</header>

<div class="delete-warning">
	<p><strong>Это действие необратимо.</strong></p>
	<ul>
		<li>Профиль будет деактивирован безвозвратно.</li>
		<li>Ваши комментарии останутся, но будут помечены как «Удалённый пользователь».</li>
		<li>Ваши статьи останутся, но автор будет показан как «Удалённый пользователь».</li>
		<li>Вы выйдете из аккаунта на всех устройствах.</li>
		<li>Занять этот email и имя пользователя снова можно будет только через новую регистрацию (email освободится).</li>
	</ul>
</div>

<?php if (!empty($errors['general'])): ?>
	<div class="alert"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" action="/settings/delete" class="settings-form">
	<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

	<div class="settings-section">
		<div class="settings-section-head">
			<h2>Подтверждение</h2>
			<p class="hint">Введите имя пользователя, чтобы подтвердить удаление.</p>
		</div>

		<label class="field">
			<span>Имя пользователя: <code><?= e($user['username']) ?></code></span>
			<input type="text" name="confirm_username" required
				   autocomplete="off"
				   class="<?= !empty($errors['confirm']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['confirm'])): ?>
				<small class="error"><?= e($errors['confirm']) ?></small>
			<?php endif; ?>
		</label>

		<label class="field">
			<span>Пароль</span>
			<input type="password" name="password" required
				   autocomplete="current-password"
				   class="<?= !empty($errors['password']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['password'])): ?>
				<small class="error"><?= e($errors['password']) ?></small>
			<?php endif; ?>
		</label>

		<label class="checkbox">
			<input type="checkbox" name="agree" value="1">
			<span>Я понимаю, что действие необратимо</span>
		</label>
		<?php if (!empty($errors['agree'])): ?>
			<small class="error"><?= e($errors['agree']) ?></small>
		<?php endif; ?>
	</div>

	<div class="settings-actions">
		<button type="submit" class="btn-danger">Удалить аккаунт</button>
	</div>
</form>
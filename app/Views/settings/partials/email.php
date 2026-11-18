<header class="settings-head">
	<h1>Смена email</h1>
	<p class="lead">Мы отправим ссылку для подтверждения на новый адрес. Пока не подтвердите — email останется прежним.</p>
</header>

<?php if (!empty($toast)): ?>
	<div class="alert alert-success"><?= e($toast) ?></div>
<?php endif; ?>

<?php if ($pending): ?>
	<div class="email-pending">
		<div class="email-pending-head">
			<strong>Ожидает подтверждения:</strong>
			<span><?= e($pending['new_email']) ?></span>
		</div>
		<p class="hint">
			Письмо со ссылкой подтверждения отправлено на новый адрес.
			Ссылка действительна 24 часа. Старый email пока продолжает работать.
		</p>
		<form method="post" action="/settings/email/cancel" class="inline-form">
			<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
			<button type="submit" class="link-btn">Отменить смену</button>
		</form>
	</div>
<?php endif; ?>

<form method="post" action="/settings/email" class="settings-form">
	<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

	<div class="settings-section">
		<div class="settings-section-head">
			<h2>Новый email</h2>
			<p class="hint">Текущий: <?= e($user['email']) ?></p>
		</div>

		<label class="field">
			<span>Email</span>
			<input type="email" name="email" required
				   value="<?= e($_POST['email'] ?? '') ?>"
				   class="<?= !empty($errors['email']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['email'])): ?>
				<small class="error"><?= e($errors['email']) ?></small>
			<?php endif; ?>
		</label>

		<label class="field">
			<span>Текущий пароль</span>
			<input type="password" name="password" required
				   autocomplete="current-password"
				   class="<?= !empty($errors['password']) ? 'has-error' : '' ?>">
			<?php if (!empty($errors['password'])): ?>
				<small class="error"><?= e($errors['password']) ?></small>
			<?php endif; ?>
			<small class="hint">Для подтверждения, что это вы.</small>
		</label>
	</div>

	<div class="settings-actions">
		<button type="submit" class="btn-primary">Отправить подтверждение</button>
	</div>
</form>
<section class="auth">
    <header class="auth-head">
        <h1>Восстановление пароля</h1>
        <p class="lead">Введите email, указанный при регистрации.</p>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['sent'])): ?>
        <div class="alert alert-success">
            Если такой email зарегистрирован, мы отправили на него ссылку для сброса пароля. Проверьте почту (и папку «Спам»).
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="/login" class="btn-primary">Вернуться к входу</a>
        </div>
    <?php else: ?>
        <form method="post" action="/forgot-password" class="form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" required autofocus>
            </label>

            <button type="submit" class="btn-primary btn-block">Отправить ссылку</button>
        </form>
        
        <p class="auth-alt"><a href="/login">← Вернуться к входу</a></p>
    <?php endif; ?>
</section>
<section class="auth">
    <header class="auth-head">
        <h1>Не удалось подтвердить</h1>
        <p class="lead">
            <?= e($reason ?? 'Ссылка недействительна, истекла или уже была использована.') ?>
        </p>
    </header>
    <a href="/settings" class="btn-primary">В настройки</a>
</section>
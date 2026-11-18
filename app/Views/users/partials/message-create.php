<div class="message-create">
    <h2 style="font-size: 20px; margin-bottom: 24px;">
        Написать сообщение для <strong><?= e($receiver['username']) ?></strong>
    </h2>

    <?php if ($isBlockedMe): ?>
        <div class="alert">Пользователь ограничил возможность отправки сообщений.</div>
    <?php else: ?>
        
        <?php if ($replyTo): ?>
            <!-- Визуальный блок с цитатой -->
            <div class="message-quote-block">
                <div class="message-quote-head">
                    <strong><?= e($replyTo['sender_name']) ?></strong> писал(а):
                </div>
                <div class="message-quote-text"><?= nl2br(e($replyTo['body'])) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="/u/<?= e($receiver['username']) ?>/message" class="form" id="message-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="receiver_id" value="<?= (int)$receiver['id'] ?>">

            <label class="field">
                <span>Текст сообщения</span>
                <textarea name="body" id="message-body" class="textarea" rows="8" required autofocus data-md-editor></textarea>
            </label>

            <div class="settings-actions">
                <button type="submit" class="btn-primary">Отправить</button>
                <a href="/u/<?= e($receiver['username']) ?>" class="link-btn">Отмена</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function() {
    const textarea = document.getElementById('message-body');
    if (!textarea) return;
    
    const receiverId = <?= (int)$receiver['id'] ?>;
    const storageKey = 'msg_draft_' + receiverId;
    const replyText = <?= $replyTo ? json_encode("> " . str_replace("\n", "\n> ", $replyTo['body']) . "\n\n") : 'null' ?>;
    
    // 1. Восстанавливаем черновик при загрузке
    const draft = localStorage.getItem(storageKey);
    if (draft) {
        textarea.value = draft;
    } else if (replyText) {
        // 2. Если черновика нет, но мы отвечаем — вставляем цитату
        textarea.value = replyText;
    }

    // 3. Сохраняем при каждом изменении текста
    textarea.addEventListener('input', () => {
        localStorage.setItem(storageKey, textarea.value);
    });

    // 4. Удаляем черновик при отправке формы
    const form = document.getElementById('message-form');
    if (form) {
        form.addEventListener('submit', () => {
            localStorage.removeItem(storageKey);
        });
    }
})();
</script>
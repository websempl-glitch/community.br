<?php if (!empty($isDeleted)): ?>
    <!-- Состояние "сообщение удалено" -->
    <div class="message-view">
        <div class="message-view-head">
            <a href="/u/<?= e($profileUser['username']) ?>/messages" class="link-btn">← Назад в ящик</a>
        </div>
        <div class="alert" style="margin-top: 24px; padding: 20px;">
            <strong>Это сообщение было удалено.</strong><br>
            <span style="color: var(--muted); font-size: 14px;">
                Возможно, оно удалено вами или собеседником. Уведомление осталось, но само сообщение больше недоступно.
            </span>
        </div>
    </div>
<?php else: ?>

    <!-- Обычный просмотр сообщения -->
    <div class="message-view">
        <div class="message-view-head">
            <a href="/u/<?= e($profileUser['username']) ?>/messages" class="link-btn">← Назад в ящик</a>
            
            <div class="message-item-name" style="margin-top: 16px;">
                <div class="avatar" style="width:32px; height:32px; font-size:14px;">
                    <?php if (!empty($message['sender_avatar'])): ?>
                        <img src="/<?= e($message['sender_avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= e(mb_strtoupper(mb_substr($message['sender_username'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div>
                    От: <strong><?= e($message['sender_username']) ?></strong> 
                    <span style="color:var(--muted); font-size:13px;">· <?= e(date('d.m.Y H:i', strtotime($message['created_at']))) ?></span>
                </div>
            </div>
        </div>
        
        <div class="message-view-body markdown-content">
            <?= $bodyHtml ?>
            
            <?php if ((int)$message['sender_id'] === (int)$profileUser['id']): ?>
                <div style="font-size: 12px; color: var(--muted); margin-top: 16px; text-align: right;">
                    <?php if ($message['read_at']): ?>
                        <span style="color: #2f6f48;">✓✓ Прочитано <?= e(date('d.m.Y H:i', strtotime($message['read_at']))) ?></span>
                    <?php else: ?>
                        <span>✓ Доставлено</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="message-view-actions" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:12px; align-items: center;">
                <?php if ((int)$message['sender_id'] !== (int)$profileUser['id'] && !$isBlockedMe): ?>
                    <a href="/u/<?= e($message['sender_username']) ?>/message?reply_to=<?= (int)$message['id'] ?>" class="btn-primary">Ответить</a>
                    <button type="button" class="btn-danger-link" onclick="reportMessage(<?= (int)$message['id'] ?>)">Пожаловаться на спам</button>
                <?php elseif ((int)$message['sender_id'] === (int)$profileUser['id'] && !$isBlockedMe): ?>
                    <a href="/u/<?= e($message['receiver_username']) ?>/message" class="btn-primary">Написать ещё</a>
                <?php endif; ?>
                
                <button type="button" class="btn-danger-link" onclick="deleteMessage(<?= (int)$message['id'] ?>)">Удалить</button>
            </div>

            <?php if ($isBlockedByMe): ?>
                <button type="button" class="btn-secondary" onclick="toggleBlock(<?= $peerId ?>, false)">Разблокировать</button>
            <?php else: ?>
                <button type="button" class="btn-secondary" style="color:var(--danger); border-color:var(--danger);" onclick="toggleBlock(<?= $peerId ?>, true)">Заблокировать</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function deleteMessage(id) {
        if (!confirm('Удалить это сообщение?')) return;
        const fd = new FormData(); fd.append('ids[]', id);
        fetch('/api/messages/bulk-delete', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
            body: fd
        }).then(r => r.json()).then(data => {
            if (data.ok) window.location.href = '/u/<?= e($profileUser['username']) ?>/messages';
        });
    }

    function toggleBlock(id, isBlock) {
        if (isBlock && !confirm('Заблокировать пользователя? Он не сможет писать вам сообщения.')) return;
        fetch('/api/users/' + id + '/toggle-block', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content }
        }).then(r => r.json()).then(data => {
            if (data.ok) window.location.reload();
        });
    }

    function reportMessage(id) {
        if (!confirm('Отправить жалобу администратору на это сообщение?')) return;
        fetch('/api/messages/' + id + '/report', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content }
        }).then(r => r.json()).then(data => {
            if (data.ok) alert('Жалоба отправлена. Спасибо!');
            else alert(data.error || 'Ошибка при отправке жалобы');
        });
    }
    </script>

<?php endif; ?>
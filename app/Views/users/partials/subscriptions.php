<h2 class="profile-tab-title">Подписки</h2>

<?php if (!$items): ?>
    <p class="hint">Вы пока ни на кого не подписаны.</p>
<?php else: ?>
    <div class="subscriptions-list">
        <?php foreach ($items as $s): ?>
            <div class="subscription-item" x-data="{ show: true }" x-show="show">
                
                <a href="/u/<?= e($s['username']) ?>/articles" class="subscription-link">
                    <div class="avatar-preview">
                        <?php if (!empty($s['avatar_path'])): ?>
                            <img src="/<?= e($s['avatar_path']) ?>" alt="">
                        <?php else: ?>
                            <span><?= e(mb_strtoupper(mb_substr($s['username'], 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="subscription-info">
                        <div class="subscription-name">
                            <?= e($s['username']) ?>
                            <?php if ($s['new_articles_count'] > 0): ?>
                                <span class="new-articles-badge" title="Новых статей с вашего последнего визита">
                                    +<?= (int)$s['new_articles_count'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($s['bio']): ?>
                            <div class="subscription-bio"><?= e(mb_substr($s['bio'], 0, 80)) ?><?= mb_strlen($s['bio']) > 80 ? '…' : '' ?></div>
                        <?php endif; ?>
                        <div class="subscription-date">Подписка с <?= e(date('d.m.Y', strtotime($s['created_at']))) ?></div>
                    </div>
                </a>
                
                <button type="button" class="btn-secondary btn-sm unsubscribe-btn" 
                        @click="
                            if(confirm('Отписаться от <?= e($s['username']) ?>?')) {
                                fetch('/api/users/<?= (int)$s['id'] ?>/follow', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content }
                                }).then(r => r.json()).then(data => {
                                    if (data.ok && !data.following) show = false;
                                });
                            }
                        ">
                    Отписаться
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?= renderPagination($page, $totalPages, '/u/'.$profileUser['username'].'/subscriptions') ?>
<?php endif; ?>

<script>
document.addEventListener('click', function(e) {
    const link = e.target.closest('.subscription-link');
    if (link) {
        const badge = link.querySelector('.new-articles-badge');
        if (badge) badge.style.display = 'none';
    }
});
</script>
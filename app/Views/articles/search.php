<section class="search-page">
    <header class="page-head">
        <h1>Поиск</h1>
        <form method="get" action="/search" class="search-form-inline">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <input type="text" name="q" value="<?= e($query) ?>" placeholder="Что искать?" autofocus>
            <button type="submit" class="btn-primary">Найти</button>
        </form>

        <div class="search-tabs">
            <a href="/search?q=<?= urlencode($query) ?>&tab=articles"
               class="search-tab <?= $tab === 'articles' ? 'is-active' : '' ?>">
                Статьи
                <?php if ($query !== '' && mb_strlen($query) >= 2): ?>
                    <span class="search-tab-count"><?= count($articles) ?></span>
                <?php endif; ?>
            </a>
            <a href="/search?q=<?= urlencode($query) ?>&tab=comments"
               class="search-tab <?= $tab === 'comments' ? 'is-active' : '' ?>">
                Комментарии
                <?php if ($query !== '' && mb_strlen($query) >= 2): ?>
                    <span class="search-tab-count"><?= count($comments) ?></span>
                <?php endif; ?>
            </a>
            <a href="/search?q=<?= urlencode($query) ?>&tab=users"
               class="search-tab <?= $tab === 'users' ? 'is-active' : '' ?>">
                Пользователи
                <?php if ($query !== '' && mb_strlen($query) >= 2): ?>
                    <span class="search-tab-count"><?= count($users) ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <?php if ($query === ''): ?>
        <p class="hint" style="padding: 40px 0;">Введите запрос для поиска.</p>

    <?php elseif (mb_strlen($query) < 2): ?>
        <p class="hint" style="padding: 40px 0;">Запрос слишком короткий. Введите минимум 2 символа.</p>

    <?php else: ?>
        <?php
        $highlight = function (string $text) use ($query): string {
            $escaped = e($text);
            $pattern = '/(' . preg_quote(e($query), '/') . ')/iu';
            return preg_replace($pattern, '<mark>$1</mark>', $escaped);
        };
        ?>

        <?php if ($tab === 'articles'): ?>
            <?php if (!$articles): ?>
                <p class="hint" style="padding: 40px 0;">
                    По запросу «<strong><?= e($query) ?></strong>» статей не найдено.
                </p>
            <?php else: ?>
                <p class="hint" style="margin-bottom: 24px;">
                    Найдено: <?= count($articles) ?>
                    <?= plural_ru(count($articles), 'статья', 'статьи', 'статей') ?>
                </p>
                <div class="search-results">
                    <?php foreach ($articles as $a): ?>
                        <a class="search-article-card" href="/article/<?= e($a['slug']) ?>">
                            <h2><?= $highlight($a['title']) ?></h2>
                            <?php if ($a['excerpt']): ?>
                                <p class="search-article-lead"><?= $highlight($a['excerpt']) ?></p>
                            <?php endif; ?>
                            
                            <div class="search-article-meta">
                                <?php if (!empty($a['category_name'])): 
                                    $catName = !empty($a['parent_category_name']) 
                                        ? $a['parent_category_name'] . ' / ' . $a['category_name']
                                        : $a['category_name'];
                                ?>
                                    <span style="color: var(--text); font-weight: 500;"><?= e($catName) ?></span>
                                <?php else: ?>
                                    <span>Без категории</span>
                                <?php endif; ?>
                                
                                <span>·</span>
                                <span><?= $a['author_deleted_at'] ? 'Удалённый автор' : e($a['author']) ?></span>
                                <span>·</span>
                                <time><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></time>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'comments'): ?>
            <?php if (!$comments): ?>
                <p class="hint" style="padding: 40px 0;">
                    По запросу «<strong><?= e($query) ?></strong>» комментариев не найдено.
                </p>
            <?php else: ?>
                <p class="hint" style="margin-bottom: 24px;">
                    Найдено: <?= count($comments) ?>
                    <?= plural_ru(count($comments), 'комментарий', 'комментария', 'комментариев') ?>
                </p>
                <div class="search-comments">
                    <?php foreach ($comments as $c): ?>
                        <a class="search-comment" href="/article/<?= e($c['article_slug']) ?>#c-<?= (int)$c['id'] ?>">
                            <div class="search-comment-meta">
                                <span class="search-comment-article"><?= e($c['article_title']) ?></span>
                                <span>·</span>
                                <span><?= $c['user_deleted_at'] ? 'Удалённый пользователь' : e($c['author']) ?></span>
                                <span>·</span>
                                <time><?= e(time_ago($c['created_at'])) ?></time>
                            </div>
                            <div class="search-comment-body">
                                <?= $highlight($c['body']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'users'): ?>
            <?php if (!$users): ?>
                <p class="hint" style="padding: 40px 0;">
                    По запросу «<strong><?= e($query) ?></strong>» пользователей не найдено.
                </p>
            <?php else: ?>
                <p class="hint" style="margin-bottom: 24px;">
                    Найдено: <?= count($users) ?>
                    <?= plural_ru(count($users), 'пользователь', 'пользователя', 'пользователей') ?>
                </p>
                <div class="search-users">
                    <?php foreach ($users as $u): ?>
                        <a href="/u/<?= e($u['username']) ?>" class="search-user-card">
                            <div class="search-user-avatar">
                                <?php if (!empty($u['avatar_path'])): ?>
                                    <img src="/<?= e($u['avatar_path']) ?>" alt="">
                                <?php else: ?>
                                    <span><?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="search-user-info">
                                <div class="search-user-name"><?= $highlight($u['username']) ?></div>
                                <?php if ($u['bio']): ?>
                                    <div class="search-user-bio"><?= $highlight(mb_substr($u['bio'], 0, 120)) ?><?= mb_strlen($u['bio']) > 120 ? '…' : '' ?></div>
                                <?php endif; ?>
                                <div class="search-user-date">на сайте с <?= e(date('m.Y', strtotime($u['created_at']))) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
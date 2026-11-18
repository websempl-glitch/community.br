<div class="layout-grid">
    <div class="layout-main">
		<div class="feed-tabs">
            <a href="/" class="feed-tab <?= $feed === 'all' ? 'is-active' : '' ?>">Все статьи</a>
            <?php if (\App\Auth::check()): ?>
                <a href="/?feed=subscriptions" class="feed-tab <?= $feed === 'subscriptions' ? 'is-active' : '' ?>">
                    Мои подписки
                    <?php if (!empty($feedNewCount)): ?>
                        <span class="feed-badge"><?= (int)$feedNewCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
        <section class="articles-list">
            <header class="page-head">
				<?php $welcome = setting('home_welcome_text'); ?>
                <?php if ($welcome): ?>
                    <div class="home-welcome">
                        <?= nl2br(e($welcome)) ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if (!$articles): ?>
                <p class="lead">Пока нет опубликованных статей.</p>
            <?php else: ?>
                <?php foreach ($articles as $a): ?>
                    <div class="article-card">
						<a class="article-card-image" href="/article/<?= e($a['slug']) ?>">
							<?php if (!empty($a['display_image'])): ?>
								<img src="/<?= e($a['display_image']) ?>" alt="">
							<?php else: ?>
								<div class="article-card-placeholder">
									<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
								</div>
							<?php endif; ?>
						</a>
						
						<div class="article-card-content">
							<!-- Мета-информация лежит отдельно, ссылки в ней работают независимо -->
							<div class="article-card-meta">
								<?php if (!empty($a['pinned_global']) || !empty($a['pinned_category'])): ?>
									<span class="pinned-label">📌 Закреплено</span>
								<?php endif; ?>

								<span><?= $a['author_deleted_at'] ? 'Удалённый автор' : e($a['author']) ?></span>
								<span>·</span>
								<time><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></time>
							</div>
							
							<?php if (!empty($a['category_name'])): ?>
								<div class="article-card-categories">
									<?php
									$catUrl = !empty($a['parent_category_slug']) 
										? '/category/' . $a['parent_category_slug'] . '/' . $a['category_slug']
										: '/category/' . $a['category_slug'];
									$catName = !empty($a['parent_category_name']) 
										? $a['parent_category_name'] . ' / ' . $a['category_name']
										: $a['category_name'];
									?>
									<a href="<?= e($catUrl) ?>" class="meta-cat-link"><?= e($catName) ?></a>
								</div>
							<?php endif; ?>

							<!-- А вот тут ссылка, которая ведет на статью -->
							<a class="article-card-main" href="/article/<?= e($a['slug']) ?>">
								<h2><?= e($a['title']) ?></h2>
								<?php if ($a['excerpt']): ?>
									<p class="article-card-lead"><?= e($a['excerpt']) ?></p>
								<?php endif; ?>
							</a>
							<div class="article-card-footer">
								<div class="stat-icon">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									<?= (int)$a['views'] ?>
								</div>
								
								<?php if ($a['comments_count'] > 0): ?>
									<a href="/article/<?= e($a['slug']) ?>#comments" class="stat-icon stat-icon-link" title="Перейти к комментариям">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
										<?= (int)$a['comments_count'] ?>
									</a>
								<?php else: ?>
									<div class="stat-icon" title="Нет комментариев">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
										0
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <aside class="layout-sidebar">
        <div class="sidebar-static">
            
            <!-- Блок Разделы -->
            <?php if (!empty($categories)): ?>
            <div class="sidebar-widget">
                <h3 class="sidebar-title">Разделы</h3>
                <div class="categories-tree">
                    <?php foreach ($categories as $c): ?>
                        <div class="cat-tree-item">
                            <a href="/category/<?= e($c['slug']) ?>" class="cat-tree-link">
                                <?= e($c['name']) ?>
                            </a>
                            <?php if (!empty($subcategories[$c['id']])): ?>
                                <div class="cat-tree-subs">
                                    <?php foreach ($subcategories[$c['id']] as $sub): ?>
                                        <a href="/category/<?= e($c['slug']) ?>/<?= e($sub['slug']) ?>" class="cat-tree-sub">
                                            <?= e($sub['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Блок Обсуждаемое -->
            <?php if (!empty($discussed)): ?>
            <div class="sidebar-widget">
                <h3 class="sidebar-title">Обсуждаемое</h3>
                <div class="discussed-list">
                    <?php foreach ($discussed as $d): ?>
                        <a href="/article/<?= e($d['slug']) ?>" class="discussed-item">
                            <div class="discussed-time"><?= e(time_ago($d['published_at'])) ?></div>
                            <div class="discussed-title"><?= e($d['title']) ?></div>
                            <div class="discussed-stats">
                                <div class="stat-icon">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <?= (int)$d['views'] ?>
                                </div>
                                <div class="stat-icon">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                                    <?= (int)$d['comments_count'] ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </aside>
</div>
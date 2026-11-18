<div class="layout-grid">
    <div class="layout-main">
        <!-- Хлебные крошки -->
        <nav class="breadcrumbs">
            <a href="/">Главная</a>
            <?php foreach ($breadcrumbs as $i => $b): ?>
                <span class="breadcrumb-sep">/</span>
                <?php if ($i === count($breadcrumbs) - 1): ?>
                    <span><?= e($b['name']) ?></span>
                <?php else: ?>
                    <a href="<?= e($b['url']) ?>"><?= e($b['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <header class="page-head">
            <h1><?= e($category['name']) ?></h1>
        </header>

        <!-- Если есть подразделы -->
        <?php if (!empty($subcategories)): ?>
            <div class="subcategories-grid">
                <?php foreach ($subcategories as $sub): ?>
                    <a href="/category/<?= e($category['slug']) ?>/<?= e($sub['slug']) ?>" class="subcategory-card">
                        <h3><?= e($sub['name']) ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Список статей -->
        <?php if (!empty($articles)): ?>
            <h2 class="profile-tab-title" style="margin-top: 32px; font-size: 20px;">Статьи в этом разделе</h2>
            <div class="articles-list">
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

								<?php if (!empty($a['category_name'])): 
									$catUrl = !empty($a['parent_category_slug']) 
										? '/category/' . $a['parent_category_slug'] . '/' . $a['category_slug']
										: '/category/' . $a['category_slug'];
									$catName = !empty($a['parent_category_name']) 
										? $a['parent_category_name'] . ' / ' . $a['category_name']
										: $a['category_name'];
								?>
									<a href="<?= e($catUrl) ?>" class="meta-cat-link"><?= e($catName) ?></a>
									<span>·</span>
								<?php endif; ?>

								<span><?= $a['author_deleted_at'] ? 'Удалённый автор' : e($a['author']) ?></span>
								<span>·</span>
								<time><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></time>
							</div>								

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
            </div>
        <?php elseif (empty($subcategories)): ?>
            <p class="hint" style="margin-top: 32px;">В этом разделе пока нет ни подразделов, ни статей.</p>
        <?php endif; ?>
    </div>

    <aside class="layout-sidebar">
        <!-- Сайдбар с категориями -->
        <?php 
        $db = \App\Database::conn();
        $allCats = $db->query('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC')->fetchAll();
        $allSubs = $db->query('SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC')->fetchAll();
        $subsMap = [];
        foreach ($allSubs as $s) $subsMap[(int)$s['parent_id']][] = $s;
        ?>
        <div class="sidebar-widget">
            <h3 class="sidebar-title">Разделы</h3>
            <div class="categories-tree">
                <?php foreach ($allCats as $c): ?>
                    <div class="cat-tree-item">
                        <a href="/category/<?= e($c['slug']) ?>" class="cat-tree-link <?= $c['id'] == $category['id'] ? 'is-active' : '' ?>">
                            <?= e($c['name']) ?>
                        </a>
                        <?php if (!empty($subsMap[$c['id']])): ?>
                            <div class="cat-tree-subs">
                                <?php foreach ($subsMap[$c['id']] as $sub): ?>
                                    <a href="/category/<?= e($c['slug']) ?>/<?= e($sub['slug']) ?>" class="cat-tree-sub <?= $sub['id'] == $category['id'] ? 'is-active' : '' ?>">
                                        <?= e($sub['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>
<script>
window.__commentsInitial = {
    newCount: <?= (int)$newCount ?>,
    newItems: <?= json_encode($newItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    isSubscribed: <?= $isSubscribed ? 'true' : 'false' ?>,
    serverTime: <?= json_encode($serverNow) ?>,
    commentsOpen: <?= $commentsOpen ? 'true' : 'false' ?>,
    commentsClosedReason: <?= json_encode($commentsClosedReason) ?>,
	currentUser: <?= json_encode(\App\Auth::check() ? \App\Auth::user()['username'] : null) ?>,
	lastSeenAt: <?= json_encode($lastSeenAt) ?>
};
</script>

<div class="layout-grid"
     x-data="commentsApp"
     data-article-id="<?= (int)$article['id'] ?>"
     data-can-moderate="<?= $canModerate ? '1' : '0' ?>"
     @keydown.escape.window="handleEscape()">

    <div class="layout-main">

        <article class="article">
			
			<nav class="breadcrumbs">
			
				<script type="application/ld+json">
				<?php
				$listItems = [
					['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => rtrim(config('app.url'), '/') . '/']
				];
				$pos = 2;
				foreach ($breadcrumbs as $b) {
					$listItems[] = [
						'@type' => 'ListItem',
						'position' => $pos++,
						'name' => $b['name'],
						'item' => rtrim(config('app.url'), '/') . $b['url']
					];
				}
				$listItems[] = [
					'@type' => 'ListItem',
					'position' => $pos,
					'name' => $article['title']
				];
				echo json_encode([
					'@context' => 'https://schema.org',
					'@type' => 'BreadcrumbList',
					'itemListElement' => $listItems
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
				?>
				</script>
				
				<a href="/">Главная</a>
				<?php foreach ($breadcrumbs as $b): ?>
					<span class="breadcrumb-sep">/</span>
					<a href="<?= e($b['url']) ?>"><?= e($b['name']) ?></a>
				<?php endforeach; ?>
				<span class="breadcrumb-sep">/</span>
				<span class="breadcrumb-current"><?= e($article['title']) ?></span>
			</nav>
			
            <header class="article-head">
				<?php if ($article['published_at'] === null): ?>
					<div class="draft-badge">Черновик — виден только вам и администраторам</div>
				<?php endif; ?>
				<div class="meta">
					<?php if ($article['author_deleted_at']): ?>
						<span class="meta-link">Удалённый автор</span>
					<?php else: ?>
						<a href="/u/<?= e($article['author']) ?>" class="meta-link"><?= e($article['author']) ?></a>
					<?php endif; ?>
					<span>·</span>
					<?php if (!empty($breadcrumbs)): ?>
                        <?php 
                        // Берем последний элемент крошек (это самая точная категория)
                        $cat = end($breadcrumbs); 
                        ?>
                        <a href="<?= e($cat['url']) ?>" class="meta-link"><?= e($cat['name']) ?></a>
                        <span>·</span>
                    <?php endif; ?>
					<?php if ($article['published_at']): ?>
						<time><?= e(date('d.m.Y', strtotime($article['published_at']))) ?></time>
					<?php else: ?>
						<time><?= e(date('d.m.Y', strtotime($article['created_at']))) ?></time>
					<?php endif; ?>
				</div>
                <h1><?= e($article['title']) ?></h1>
                <?php if ($article['excerpt']): ?>
                    <p class="lead"><?= e($article['excerpt']) ?></p>
                <?php endif; ?>
            </header>

            <div class="article-body">
                <?= $bodyHtml ?>
            </div>
        </article>
		
		<div class="article-actions">
			<div x-data="bookmarkControl(<?= (int)$article['id'] ?>, <?= $isBookmarked ? 'true' : 'false' ?>, <?= \App\Auth::check() ? 'true' : 'false' ?>)"
				 class="article-actions-left">
				<button type="button"
						class="bookmark-btn"
						:class="{ 'is-active': bookmarked }"
						@click="toggle()"
						:disabled="busy">
					<span x-show="!bookmarked">☆ Сохранить в закладки</span>
					<span x-show="bookmarked">★ В закладках</span>
				</button>
				<template x-if="toast">
					<span class="bookmark-toast" x-text="toast"></span>
				</template>
			</div>

			<?php if ($canEdit): ?>
				<div class="article-actions-right"
					 x-data="deleteArticleControl(<?= (int)$article['id'] ?>)">
					 
					<?php if ($canPin): ?>
						<div x-data="{ 
								open: false, 
								pinnedG: <?= $article['pinned_global'] ? 'true' : 'false' ?>, 
								pinnedC: <?= $article['pinned_category'] ? 'true' : 'false' ?>,
								hasCategory: <?= $article['category_id'] ? 'true' : 'false' ?>,
								busy: false,
								updatePin(g, c) {
									this.busy = true;
									const fd = new FormData();
									fd.append('pinned_global', g ? '1' : '0');
									fd.append('pinned_category', c ? '1' : '0');
									fetch('/api/articles/<?= (int)$article['id'] ?>/update-pin', {
										method: 'POST',
										headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
										body: fd
									}).then(r => r.json()).then(data => {
										if (data.ok) {
											this.pinnedG = data.pinned_global;
											this.pinnedC = data.pinned_category;
										}
										this.busy = false;
									}).catch(() => this.busy = false);
								}
							}" style="position:relative;">
							<button type="button" class="btn-secondary" @click="open = !open" :disabled="busy">
								📌 Закрепление
							</button>
							
							<div x-show="open" x-cloak @click.outside="open = false" 
								 style="position:absolute; top:100%; left:0; margin-top:8px; background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:100; width:200px; display:flex; flex-direction:column; gap:8px;">
								<label class="pin-checkbox-label">
									<input type="checkbox" :checked="pinnedG" @change="updatePin($event.target.checked, pinnedC)">
									На главной
								</label>
								<label class="pin-checkbox-label" :class="{ 'is-disabled': !hasCategory }">
									<input type="checkbox" :checked="pinnedC" @change="updatePin(pinnedG, $event.target.checked)">
									В разделе
								</label>
							</div>
						</div>
					<?php endif; ?>
					
					<a href="/article/<?= e($article['slug']) ?>/edit" class="btn-secondary">Редактировать</a>

					<template x-if="!confirmStep">
						<button type="button" class="btn-danger-link"
								@click="requestDelete()">Удалить</button>
					</template>

					<template x-if="confirmStep">
						<div class="delete-confirm">
							<span>Удалить статью и все комментарии?</span>
							<button type="button" class="btn-danger"
									@click="confirmDelete()" :disabled="busy">
								<span x-show="!busy">Да, удалить</span>
								<span x-show="busy">Удаление…</span>
							</button>
							<button type="button" class="link-btn"
									@click="cancelDelete()">Отмена</button>
						</div>
					</template>
				</div>
			<?php endif; ?>
		</div>
		
		<!-- Карточка автора -->
        <div class="author-card">
            <div class="author-card-top">
                <a href="/u/<?= e($article['author']) ?>" class="author-avatar">
                    <?php
                    // Получаем аватар автора (в контроллере мы его не передавали, сделаем быстрый запрос)
                    $stmt = \App\Database::conn()->prepare('SELECT avatar_path FROM users WHERE id = ?');
                    $stmt->execute([$article['author_id']]);
                    $authAvatar = $stmt->fetchColumn();
                    ?>
                    <?php if ($authAvatar): ?>
                        <img src="/<?= e($authAvatar) ?>" alt="">
                    <?php else: ?>
                        <span><?= e(mb_strtoupper(mb_substr($article['author'], 0, 1))) ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="author-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $authorStats['followers'] ?></span>
                        <span class="stat-label">подписчиков</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $authorStats['articles'] ?></span>
                        <span class="stat-label">статей</span>
                    </div>
					<div class="stat-item">
                        <span class="stat-value"><?= $authorStats['karma'] ?></span>
                        <span class="stat-label">кармы</span>
                    </div>
                </div>
            </div>
            
            <div class="author-card-bottom">
                <div class="author-info">
                    <a href="/u/<?= e($article['author']) ?>" class="author-name">@<?= e($article['author']) ?></a>
                    <span class="status-badge status-<?= e($authorStats['status']['key']) ?>"><?= e($authorStats['status']['label']) ?></span>
                </div>
                
                <?php if (\App\Auth::check() && (int)\App\Auth::user()['id'] !== (int)$article['author_id']): ?>
                    <div class="author-actions" x-data="followControl(<?= (int)$article['author_id'] ?>, <?= $authorStats['is_following'] ? 'true' : 'false' ?>)">
                        <button type="button" class="btn-secondary follow-btn" 
                                :class="{ 'is-active': following }" 
                                @click="toggle()" :disabled="busy">
                            <span x-show="!following">Подписаться</span>
                            <span x-show="following">Вы подписаны</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

		<script>
		function bookmarkControl(articleId, initial, isLoggedIn) {
			return {
				articleId,
				bookmarked: !!initial,
				busy: false,
				toast: '',
				_toastTimer: null,

				async toggle() {
					if (!isLoggedIn) {
						window.location.href = '/login';
						return;
					}
					if (this.busy) return;
					this.busy = true;
					try {
						const r = await fetch(`/api/articles/${this.articleId}/bookmark`, {
							method: 'POST',
							headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
						});
						const data = await r.json();
						if (!data.ok) throw new Error(data.error || 'Ошибка');
						this.bookmarked = !!data.bookmarked;
						this.showToast(this.bookmarked ? 'Добавлено в закладки' : 'Убрано из закладок');
					} catch (e) {
						this.showToast('Не удалось сохранить');
					} finally {
						this.busy = false;
					}
				},

				showToast(msg) {
					this.toast = msg;
					if (this._toastTimer) clearTimeout(this._toastTimer);
					this._toastTimer = setTimeout(() => { this.toast = ''; }, 2500);
				},
			};
		}
		</script>
		
		<script>
		function followControl(userId, initial) {
			return {
				userId,
				following: !!initial,
				busy: false,

				async toggle() {
					if (this.busy) return;
					this.busy = true;
					try {
						const r = await fetch(`/api/users/${this.userId}/follow`, {
							method: 'POST',
							headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content },
						});
						const data = await r.json();
						if (!data.ok) throw new Error(data.error || 'Ошибка');
						this.following = !!data.following;
					} catch (e) {
						alert(e.message);
					} finally {
						this.busy = false;
					}
				},
			};
		}
		</script>
		
		<script>
		function deleteArticleControl(articleId) {
			return {
				confirmStep: false,
				busy: false,
				_timer: null,

				requestDelete() {
					if (this.busy) return;
					this.confirmStep = true;
					if (this._timer) clearTimeout(this._timer);
					this._timer = setTimeout(() => { this.confirmStep = false; }, 6000);
				},

				cancelDelete() {
					this.confirmStep = false;
					if (this._timer) clearTimeout(this._timer);
				},

				async confirmDelete() {
					if (this.busy) return;
					this.busy = true;
					try {
						const r = await fetch(`/api/articles/${articleId}/delete`, {
							method: 'POST',
							headers: {
								'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content,
							},
						});
						const data = await r.json();
						if (!data.ok) throw new Error(data.error || 'Ошибка');
						window.location.href = '/';
					} catch (e) {
						alert(e.message);
						this.busy = false;
						this.confirmStep = false;
					}
				},
			};
		}
		</script>
		
		<div class="comments-wrapper">
			<section class="comments" id="comments">

				<header class="comments-head">
					<div class="comments-head-left">
						<h2>Комментарии</h2>
						<span class="comments-count" x-text="totalLabel"></span>
					</div>
					<template x-if="isLoggedIn && isSubscribed">
						<span class="subscribe-state">
							Вы подписаны ·
							<button type="button" class="link-btn" @click="unsubscribe()">Отписаться</button>
						</span>
					</template>
				</header>
				
				<div class="discussion-search" x-show="comments.length >= 5">
					<input type="search"
						   placeholder="Найти в обсуждении…"
						   x-model="discussionSearch">
					<template x-if="discussionSearch.trim().length >= 2">
						<span class="discussion-search-info">
							<span x-text="filteredComments.length"></span>
							из <span x-text="comments.length"></span>
						</span>
					</template>
				</div>
				
				<template x-if="!commentsOpen">
					<div class="comments-closed">
						<template x-if="commentsClosedReason === 'draft'">
							<span>Статья в черновике — новые комментарии появятся после публикации.</span>
						</template>
						<template x-if="commentsClosedReason === 'disabled'">
							<span>Автор отключил комментарии к этой статье.</span>
						</template>
					</div>
				</template>
				
				<template x-if="isLoggedIn && commentsOpen">
					<form class="comment-form"
						  @submit.prevent="submit(null)"
						  @dragover.prevent="dragOver = true"
						  @dragleave.prevent="dragOver = false"
						  @drop.prevent="onDrop($event)">

						<div class="comment-form-box" :class="{ 'is-drag': dragOver }">
							<textarea class="textarea"
									  rows="3"
									  placeholder="Написать комментарий… (можно перетащить фото или вставить из буфера)"
									  x-model="newBody"
									  @paste="onPaste($event)"
									  :disabled="submitting"
									  data-md-editor></textarea>

							<template x-if="formImages.length">
								<div class="form-images">
									<template x-for="(img, i) in formImages" :key="img.id">
										<div class="form-image">
											<img :src="img.url" alt="">
											<button type="button" class="form-image-remove"
													@click="removeFormImage(i)">×</button>
										</div>
									</template>
								</div>
							</template>

							<div class="comment-form-actions">
								<div class="comment-form-left">
									<label class="attach-label">
										<input type="file" accept="image/*" multiple
											   @change="onPick($event)" hidden>
										📎 <span x-text="uploading ? 'Загрузка…' : 'Прикрепить фото'"></span>
									</label>
									<small class="hint" x-show="formImages.length"
										   x-text="formImages.length + ' / 10'"></small>
								</div>
								<div class="comment-form-right">
									<small class="hint" x-show="error" x-text="error" style="color:var(--danger)"></small>
									<button type="submit" class="btn-primary"
											:disabled="submitting || uploading || (!newBody.trim() && !formImages.length)">
										Отправить
									</button>
								</div>
							</div>
						</div>
					</form>
				</template>

				<template x-if="!isLoggedIn && commentsOpen">
					<p class="login-hint">
						<a href="/login">Войдите</a> или <a href="/register">зарегистрируйтесь</a>, чтобы оставить комментарий.
					</p>
				</template>

				<div class="comments-list" x-show="!loading">
					<template x-for="c in filteredComments" :key="c.id">
						<div class="comment"
							:id="'c-' + c.id"
							:class="{ 'is-pinned': c.is_pinned, 'is-fresh': isFresh(c) }">

							<template x-if="c.is_pinned">
								<div class="pinned-badge">📌 Закреплено</div>
							</template>
							<div class="comment-head">
								<div class="avatar" :class="{ 'has-image': !!c.avatar_url }">
									<template x-if="c.avatar_url">
										<img :src="c.avatar_url" alt="" @error="c.avatar_url = null">
									</template>
									<span x-show="!c.avatar_url" x-text="c.initial"></span>
								</div>
								<a :href="'/u/' + c.username"
								   class="comment-author"
								   x-text="c.username"
								   x-show="!c.is_deleted && !c.is_user_deleted"></a>
								<span class="comment-author is-deleted"
									  x-show="c.is_deleted || c.is_user_deleted"
									  x-text="c.username"></span>
								<template x-if="c.user_status">
									<span class="status-badge"
										  :class="'status-' + c.user_status"
										  x-text="c.user_status_label"></span>
								</template>
								<template x-if="c.user_karma > 0">
									<span class="comment-karma" title="Карма" x-text="c.user_karma"></span>
								</template>
								<span class="comment-date" x-text="c.time_ago"></span>
								<span class="comment-edited" x-show="c.edited && !c.is_deleted">· ред.</span>
								<span class="comment-hidden-badge" x-show="c.is_hidden" x-text="'· ' + c.hidden_label"></span>
							</div>

							<template x-if="editingId !== c.id">
								<div>
									<div class="comment-body"
										 :class="{ 'is-dimmed': c.is_hidden }"
										 x-html="c.body_html"></div>

									<template x-if="c.images && c.images.length">
										<div class="comment-images" :class="'count-' + Math.min(c.images.length, 4)">
											<template x-for="(img, i) in c.images.slice(0, 4)" :key="img.id">
												<div class="comment-image"
													 @click="openLightbox(c.images, i)">
													<img :src="img.url" :alt="'изображение ' + (i+1)" loading="lazy">
													<template x-if="i === 3 && c.images.length > 4">
														<div class="comment-image-more"
															 x-text="'+' + (c.images.length - 4)"></div>
													</template>
												</div>
											</template>
										</div>
									</template>
								</div>
							</template>

							<template x-if="editingId === c.id">
								<div class="comment-edit"
									 @dragover.prevent="dragOver = true"
									 @dragleave.prevent="dragOver = false"
									 @drop.prevent="onDropEdit($event)">

									<textarea class="textarea" rows="2"
											  x-model="editingBody"
											  @paste="onPasteEdit($event)"
											  data-md-editor></textarea>

									<template x-if="editingImages.length || editingNewImages.length">
										<div class="form-images">
											<template x-for="(img, i) in editingImages" :key="'old-' + img.id">
												<div class="form-image">
													<img :src="img.url" alt="">
													<button type="button" class="form-image-remove"
															@click="editingImages.splice(i, 1)">×</button>
												</div>
											</template>
											<template x-for="(img, i) in editingNewImages" :key="'new-' + img.id">
												<div class="form-image is-new">
													<img :src="img.url" alt="">
													<button type="button" class="form-image-remove"
															@click="editingNewImages.splice(i, 1)">×</button>
												</div>
											</template>
										</div>
									</template>

									<div class="comment-form-actions">
										<div class="comment-form-left">
											<label class="attach-label">
												<input type="file" accept="image/*" multiple
													   @change="onPickEdit($event)" hidden>
												📎 <span x-text="uploading ? 'Загрузка…' : 'Прикрепить фото'"></span>
											</label>
											<small class="hint" x-show="editingImages.length || editingNewImages.length"
												   x-text="(editingImages.length + editingNewImages.length) + ' / 10'"></small>
										</div>
										<div class="comment-form-right">
											<button type="button" class="link-btn" @click="cancelEdit()">Отмена</button>
											<button type="button" class="btn-primary"
													:disabled="!editingBody.trim() || uploading"
													@click="saveEdit(c)">Сохранить</button>
										</div>
									</div>
								</div>
							</template>

							<div class="comment-actions">
								<template x-if="c.replyCount > 0 || c.repliesLoaded">
									<button class="action-btn" @click="toggleReplies(c)">
										<span x-show="!c.repliesOpen">
											▸ <span x-text="c.replyCount"></span>
											<span x-text="c.replyCount === 1 ? 'ответ' : 'ответов'"></span>
										</span>
										<span x-show="c.repliesOpen">▾ Свернуть</span>
									</button>
								</template>
								<div class="reaction-wrap" x-data="{ open: false }" x-show="!c.is_deleted">
									<button type="button" class="action-btn reaction-btn"
											@click="open = !open">
										<span x-text="reactionEmoji(c.my_reaction || 'like')"></span>
										<span x-show="totalReactions(c) > 0" x-text="totalReactions(c)"></span>
									</button>
									<div class="reaction-picker" x-show="open" x-cloak @click.outside="open = false">
										<template x-for="r in ['like', 'love', 'funny', 'sad', 'angry', 'poop']" :key="r">
											<button type="button"
													class="reaction-emoji"
													:class="{ 'is-active': c.my_reaction === r }"
													@click="toggleReaction(c, r); open = false"
													x-text="reactionEmoji(r)"></button>
										</template>
									</div>
								</div>
								<button class="action-btn" @click="startReply(c)" x-show="!c.is_deleted && !c.is_hidden && commentsOpen">Ответить</button>
								<button class="action-btn action-menu"
										@click="openMenu($event, c)"
										x-show="!c.is_deleted">⋯</button>
							</div>

							<template x-if="replyTo === c.id">
								<form class="comment-form comment-reply-form" @submit.prevent="submit(replyTargetId)">
									<textarea class="textarea"
											  rows="2"
											  :placeholder="'Ответить ' + (replyToUser || c.username) + '…'"
											  x-model="replyBody"
											  :disabled="submitting"
											  data-md-editor></textarea>
									<div class="comment-form-actions">
										<button type="button" class="link-btn" @click="cancelReply()">Отмена</button>
										<button type="submit" class="btn-primary"
												:disabled="submitting || !replyBody.trim()">Ответить</button>
									</div>
								</form>
							</template>

							<template x-if="c.repliesOpen">
								<div class="replies">
									<template x-if="c.repliesLoading">
										<p class="hint">Загрузка…</p>
									</template>
									<template x-for="r in (c.replies || [])" :key="r.id">
										<div class="comment comment-reply"
											:id="'c-' + r.id"
											:class="{ 'is-fresh': isFresh(r) }">

											<div class="comment-head">
												<div class="avatar" :class="{ 'has-image': !!r.avatar_url }">
													<template x-if="r.avatar_url">
														<img :src="r.avatar_url" alt="" @error="r.avatar_url = null">
													</template>
													<span x-show="!r.avatar_url" x-text="r.initial"></span>
												</div>
												<a :href="'/u/' + r.username"
												   class="comment-author"
												   x-text="r.username"
												   x-show="!r.is_deleted && !r.is_user_deleted"></a>
												<span class="comment-author is-deleted"
													  x-show="r.is_deleted || r.is_user_deleted"
													  x-text="r.username"></span>
												<template x-if="r.user_status">
													<span class="status-badge"
														  :class="'status-' + r.user_status"
														  x-text="r.user_status_label"></span>
												</template>
												<template x-if="r.user_karma > 0">
													<span class="comment-karma" title="Карма" x-text="r.user_karma"></span>
												</template>
												<span class="comment-date" x-text="r.time_ago"></span>
												<span class="comment-edited" x-show="r.edited && !r.is_deleted">· ред.</span>
												<span class="comment-hidden-badge" x-show="r.is_hidden" x-text="'· ' + r.hidden_label"></span>
											</div>

											<template x-if="editingId !== r.id">
												<div>
													<div class="comment-body"
														 :class="{ 'is-dimmed': r.is_hidden }"
														 x-html="r.body_html"></div>

													<template x-if="r.images && r.images.length">
														<div class="comment-images" :class="'count-' + Math.min(r.images.length, 4)">
															<template x-for="(img, i) in r.images.slice(0, 4)" :key="img.id">
																<div class="comment-image"
																	 @click="openLightbox(r.images, i)">
																	<img :src="img.url" :alt="'изображение ' + (i+1)" loading="lazy">
																	<template x-if="i === 3 && r.images.length > 4">
																		<div class="comment-image-more"
																			 x-text="'+' + (r.images.length - 4)"></div>
																	</template>
																</div>
															</template>
														</div>
													</template>
												</div>
											</template>

											<template x-if="editingId === r.id">
												<div class="comment-edit"
													 @dragover.prevent="dragOver = true"
													 @dragleave.prevent="dragOver = false"
													 @drop.prevent="onDropEdit($event)">

													<textarea class="textarea" rows="2"
															  x-model="editingBody"
															  @paste="onPasteEdit($event)"
															  data-md-editor></textarea>

													<template x-if="editingImages.length || editingNewImages.length">
														<div class="form-images">
															<template x-for="(img, i) in editingImages" :key="'old-' + img.id">
																<div class="form-image">
																	<img :src="img.url" alt="">
																	<button type="button" class="form-image-remove"
																			@click="editingImages.splice(i, 1)">×</button>
																</div>
															</template>
															<template x-for="(img, i) in editingNewImages" :key="'new-' + img.id">
																<div class="form-image is-new">
																	<img :src="img.url" alt="">
																	<button type="button" class="form-image-remove"
																			@click="editingNewImages.splice(i, 1)">×</button>
																</div>
															</template>
														</div>
													</template>

													<div class="comment-form-actions">
														<div class="comment-form-left">
															<label class="attach-label">
																<input type="file" accept="image/*" multiple
																	   @change="onPickEdit($event)" hidden>
																📎 <span x-text="uploading ? 'Загрузка…' : 'Прикрепить фото'"></span>
															</label>
														</div>
														<div class="comment-form-right">
															<button type="button" class="link-btn" @click="cancelEdit()">Отмена</button>
															<button type="button" class="btn-primary"
																	:disabled="!editingBody.trim() || uploading"
																	@click="saveEdit(r)">Сохранить</button>
														</div>
													</div>
												</div>
											</template>

											<div class="comment-actions">
												<div class="reaction-wrap" x-data="{ open: false }" x-show="!r.is_deleted">
													<button type="button" class="action-btn reaction-btn"
															@click="open = !open">
														<span x-text="reactionEmoji(r.my_reaction || 'like')"></span>
														<span x-show="totalReactions(r) > 0" x-text="totalReactions(r)"></span>
													</button>
													<div class="reaction-picker" x-show="open" x-cloak @click.outside="open = false">
														<template x-for="em in ['like', 'love', 'funny', 'sad', 'angry', 'poop']" :key="em">
															<button type="button"
																	class="reaction-emoji"
																	:class="{ 'is-active': r.my_reaction === em }"
																	@click="toggleReaction(r, em); open = false"
																	x-text="reactionEmoji(em)"></button>
														</template>
													</div>
												</div>
												<button class="action-btn" @click="startReply(c, r)"
														x-show="!r.is_deleted && !r.is_hidden && commentsOpen">Ответить</button>
												<button class="action-btn action-menu"
														@click="openMenu($event, r, c)"
														x-show="!r.is_deleted">⋯</button>
											</div>
										</div>
									</template>
									<template x-if="!c.repliesLoading && c.replies && c.replies.length === 0">
										<p class="hint">Пока нет ответов.</p>
									</template>
								</div>
							</template>
						</div>
					</template>

					<template x-if="!loading && comments.length === 0">
						<p class="hint">Пока ни одного комментария. Будьте первым.</p>
					</template>
				</div>

				<template x-if="loading">
					<p class="hint">Загрузка комментариев…</p>
				</template>
			</section>
		</div>
    </div>

    <!-- Backdrop и кнопка для мобильного -->
    <div class="outline-backdrop"
         x-show="outlineOpen"
         x-cloak
         @click="outlineOpen = false"></div>

    <button type="button"
            class="outline-toggle"
            @click="outlineOpen = !outlineOpen"
            x-show="!outlineOpen">
        Обсуждение
    </button>

    <!-- Контекстное меню -->
    <div class="context-menu"
         x-show="menu.open"
         x-cloak
         :style="{ left: menu.x + 'px', top: menu.y + 'px' }"
         @click.stop>

        <template x-if="menu.comment">
            <div class="context-menu-inner">
                <button type="button" @click="menuReply()" x-show="commentsOpen && !menu.comment.is_hidden">Ответить</button>
				<button type="button" @click="menuQuote()" x-show="commentsOpen && !menu.comment.is_hidden">Цитировать выделенное</button>
                <button type="button" @click="menuCopyLink()">Скопировать ссылку</button>
				
				<template x-if="menu.comment && !menu.comment.is_deleted && !menu.isOwn">
					<button type="button" @click="openReport(menu.comment.id)">Пожаловаться</button>
				</template>
				
				<template x-if="menu.comment && !menu.comment.is_deleted && !menu.comment.is_user_deleted">
					<button type="button" @click="menuOpenProfile()">Профиль автора</button>
				</template>

                <template x-if="menu.isOwn || canModerate">
                    <div class="context-menu-group">
                        <div class="context-menu-divider"></div>
                        <template x-if="menu.isOwn">
                            <button type="button" @click="menuEdit()" x-show="!menu.comment.is_hidden">Редактировать</button>
                        </template>
                        <button type="button" class="is-danger" @click="menuDelete()" x-show="!menu.comment.is_hidden">Удалить</button>
                    </div>
                </template>

                <!-- Модерация (только для чужих комментариев) -->
                <template x-if="canModerate && !menu.isOwn">
                    <div class="context-menu-group">
                        <div class="context-menu-divider"></div>
                        <button type="button" @click="menuHide()" x-show="!menu.comment.is_hidden">Скрыть</button>
                        <button type="button" @click="menuUnhide()" x-show="menu.comment.is_hidden">Показать снова</button>
                        <button type="button" class="is-danger" @click="menuRestrict()">Ограничить пользователя</button>
                    </div>
                </template>

                <!-- Закрепление (для любых корневых комментариев, включая свои) -->
                <template x-if="canModerate && !menu.root">
                    <div class="context-menu-group">
                        <div class="context-menu-divider"></div>
                        <button type="button" @click="menuPin()" x-show="!menu.comment.is_pinned">Закрепить</button>
                        <button type="button" @click="menuUnpin()" x-show="menu.comment.is_pinned">Открепить</button>
                    </div>
                </template>
            </div>
        </template>
    </div>

	<!-- Оглавление обсуждения -->
	<aside class="layout-sidebar">
		<div class="sidebar-widget discussion-outline" :class="{ 'is-open': outlineOpen }">
			<header class="outline-head">
				<h3>Обсуждение</h3>
				<button type="button" class="outline-close"
						@click="outlineOpen = false"
						x-show="outlineOpen">×</button>
			</header>

			<div class="outline-sort">
				<button type="button"
						:class="{ 'is-active': sortBy === 'recent' }"
						@click="sortBy = 'recent'">Свежее</button>
				<button type="button"
						:class="{ 'is-active': sortBy === 'hot' }"
						@click="sortBy = 'hot'">Горячее</button>
			</div>

			<div class="outline-list">
				<template x-for="o in outline" :key="o.id">
					<button type="button"
							class="outline-item"
							@click="markOutlineRead(o.id); scrollToAndHighlight(o.id); outlineOpen = false">
						<div class="outline-item-head">
							<span class="outline-author" x-text="o.username"></span>
							
							<!-- Иконка закрепления -->
							<template x-if="o.is_pinned">
								<span class="outline-pin" title="Закреплено">📌</span>
							</template>
							
							<!-- Пометка "новое" -->
							<template x-if="o.isNew">
								<span class="outline-new-badge">новое</span>
							</template>
						</div>
						<div class="outline-preview" x-text="o.preview"></div>
						<div class="outline-meta">
							<span x-text="o.time_ago"></span>
							<template x-if="o.reply_count > 0">
								<span>· <span x-text="o.reply_count"></span> <span x-text="o.reply_count === 1 ? 'ответ' : 'ответов'"></span></span>
							</template>
						</div>
					</button>
				</template>

				<template x-if="!loading && !comments.length">
					<p class="hint outline-empty">Обсуждения пока нет.</p>
				</template>
			</div>
		</div>
	</aside>

    <!-- Лайтбокс -->
    <div class="lightbox"
         x-show="lightbox.open"
         x-cloak
         @click.self="closeLightbox()"
         @keydown.window.arrow-right="lightbox.open && nextLightbox()"
         @keydown.window.arrow-left="lightbox.open && prevLightbox()">

        <button type="button" class="lightbox-close" @click="closeLightbox()">×</button>

        <template x-if="lightbox.images.length > 1">
            <button type="button" class="lightbox-nav lightbox-prev" @click.stop="prevLightbox()">‹</button>
        </template>

        <template x-if="lightbox.images[lightbox.index]">
            <img class="lightbox-image"
                 :src="lightbox.images[lightbox.index].url"
                 @click.stop>
        </template>

        <template x-if="lightbox.images.length > 1">
            <button type="button" class="lightbox-nav lightbox-next" @click.stop="nextLightbox()">›</button>
        </template>

        <template x-if="lightbox.images.length > 1">
            <div class="lightbox-counter"
                 x-text="(lightbox.index + 1) + ' / ' + lightbox.images.length"></div>
        </template>
    </div>
	
	<!-- Плашка «N новых» -->
    <div class="updates-badge"
         x-show="pendingCount > 0 && !updatesPanelOpen"
         x-cloak
         x-transition.opacity.duration.200ms
         @click="openUpdatesPanel()">
        ↑ <span x-text="pendingCount"></span>
        <span x-text="pendingCount === 1 ? 'новый комментарий' : 'новых комментариев'"></span>
    </div>

    <!-- Панель со списком новых -->
    <div class="updates-panel"
         x-show="updatesPanelOpen"
         x-cloak
         @keydown.escape.window="updatesPanelOpen && (updatesPanelOpen = false)">

        <div class="updates-panel-backdrop" @click="updatesPanelOpen = false"></div>

        <div class="updates-panel-sheet" @click.stop>
            <header class="updates-panel-head">
                <h3>Новое в обсуждении</h3>
                <button type="button" class="link-btn"
                        @click="markAllRead()"
                        x-show="pendingCount > 0">Отметить всё прочитанным</button>
                <button type="button" class="updates-close"
                        @click="updatesPanelOpen = false">×</button>
            </header>

            <div class="updates-panel-body">
                <template x-for="item in pendingItems" :key="item.id">
                    <button type="button" class="updates-item" @click="goToUpdate(item)">
                        <div class="updates-item-head">
							<div class="avatar" :class="{ 'has-image': !!item.avatar_url }">
								<template x-if="item.avatar_url">
									<img :src="item.avatar_url" alt="" @error="item.avatar_url = null">
								</template>
								<span x-show="!item.avatar_url" x-text="item.initial"></span>
							</div>
                            <div class="updates-item-meta">
                                <span class="updates-item-author" x-text="item.username"></span>
								<template x-if="item.user_status">
									<span class="status-badge status-compact"
										  :class="'status-' + item.user_status"
										  x-text="item.user_status_label"></span>
								</template>
                                <template x-if="item.type === 'reply'">
                                    <span class="updates-item-type">ответил вам</span>
                                </template>
                                <template x-if="item.type === 'mention'">
                                    <span class="updates-item-type">упомянул вас</span>
                                </template>
                                <span class="updates-item-time" x-text="item.time_ago"></span>
                            </div>
                        </div>
                        <div class="updates-item-preview" x-text="item.preview"></div>
                    </button>
                </template>

                <template x-if="pendingItems.length === 0">
                    <p class="hint" style="padding: 24px; text-align: center;">
                        Здесь пока пусто.
                    </p>
                </template>
            </div>
        </div>
    </div>
	
	<!-- Тост -->
    <div class="toast"
         x-show="toast.message"
         x-cloak
         x-transition.opacity.duration.200ms
         x-text="toast.message">
	</div>
	
	<!-- Модалка жалобы -->
	<div class="report-modal" x-show="reportModal.open" x-cloak @keydown.escape.window="closeReport()">
		<div class="report-modal-backdrop" @click="closeReport()"></div>
		<div class="report-modal-box" @click.stop>
			<h3>Пожаловаться на комментарий</h3>
			<p class="hint">Выберите причину. Жалоба будет отправлена администратору.</p>
			
			<div class="report-reasons">
				<label>
					<input type="radio" value="spam" x-model="reportModal.reason">
					<span>Спам или реклама</span>
				</label>
				<label>
					<input type="radio" value="abuse" x-model="reportModal.reason">
					<span>Оскорбление или агрессия</span>
				</label>
				<label>
					<input type="radio" value="other" x-model="reportModal.reason">
					<span>Другое</span>
				</label>
			</div>
			
			<textarea class="textarea" rows="2" placeholder="Комментарий для модератора (необязательно)"
					  x-model="reportModal.note" style="margin-top:12px"></textarea>
					  
			<div class="report-modal-actions">
				<small class="hint" style="color:var(--danger)" x-show="reportModal.error" x-text="reportModal.error"></small>
				<button type="button" class="link-btn" @click="closeReport()">Отмена</button>
				<button type="button" class="btn-primary" 
						:disabled="reportModal.busy"
						@click="submitReport()">
					<span x-show="!reportModal.busy">Отправить жалобу</span>
					<span x-show="reportModal.busy">Отправка…</span>
				</button>
			</div>
		</div>
	</div>
	
	 <!-- Плавающая кнопка цитирования -->
    <button type="button"
            class="floating-quote-btn"
            x-show="quoteBtn.visible"
            x-cloak
            :style="`left: ${quoteBtn.x}px; top: ${quoteBtn.y}px`"
            @mousedown.prevent="handleQuoteClick()">
        ❝ Цитировать
    </button>
</div>
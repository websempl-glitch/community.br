<section class="editor">
    <header class="page-head">
        <h1>Редактирование статьи</h1>
        <p class="lead">Ссылка статьи не меняется. Если снять «Опубликовать» — статья вернётся в черновики.</p>
    </header>

    <form method="post" action="/article/<?= e($article['slug']) ?>/edit" class="form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <label class="field">
            <span>Заголовок</span>
            <input type="text" name="title" required
                   value="<?= e($article['title']) ?>"
                   class="<?= !empty($errors['title']) ? 'has-error' : '' ?>">
            <?php if (!empty($errors['title'])): ?>
                <small class="error"><?= e($errors['title']) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Лид (краткое описание)</span>
            <input type="text" name="excerpt"
                   value="<?= e($article['excerpt'] ?? '') ?>">
        </label>
		
        <div x-data="{ 
            parentId: <?= $currentParentId ?? 'null' ?>, 
            subId: <?= $currentSubId ?? 'null' ?>,
            categories: <?= htmlspecialchars(json_encode($categories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
            subcategories: <?= htmlspecialchars(json_encode($subcategories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>
        }">
            <div class="field">
                <span>Раздел</span>
                <select name="parent_id" x-model="parentId" @change="subId = null" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); font: inherit; background: var(--input-bg); color: var(--text);">
                    <option value="">— Без раздела —</option>
                    <template x-for="cat in categories" :key="cat.id">
                        <option :value="cat.id" x-text="cat.name" :selected="cat.id == parentId"></option>
                    </template>
                </select>
            </div>

            <div class="field" x-show="parentId" x-cloak style="margin-top: 12px;">
                <span>Подраздел</span>
                <select name="category_id" x-model="subId" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); font: inherit; background: var(--input-bg); color: var(--text);">
                    <option value="">— Прямо в разделе —</option>
                    <template x-for="sub in (subcategories[parentId] || [])" :key="sub.id">
                        <option :value="sub.id" x-text="sub.name" :selected="sub.id == subId"></option>
                    </template>
                </select>
            </div>
        </div>
		
		<div class="field" x-data="{ preview: '<?= !empty($article['cover_image']) ? '/' . e($article['cover_image']) : '' ?>', deleteCover: false }">
			<span>Обложка (16:9)</span>
			<div class="cover-preview" x-show="preview" x-cloak style="margin-bottom: 8px;">
				<img :src="preview" alt="">
				<button type="button" class="cover-remove" @click="preview = ''; deleteCover = true; $el.closest('div.field').querySelector('input[type=file]').value = ''">×</button>
			</div>
			<input type="hidden" name="delete_cover" :value="deleteCover ? '1' : '0'">
			<input type="file" name="cover" accept="image/*" class="file-input" @change="preview = URL.createObjectURL($event.target.files[0]); deleteCover = false;">
		</div>

        <label class="field">
            <span>Текст статьи</span>
            <textarea name="body" rows="18" required
                      class="textarea <?= !empty($errors['body']) ? 'has-error' : '' ?>"
                      data-md-editor="full"
            ><?= e($article['body']) ?></textarea>
            <?php if (!empty($errors['body'])): ?>
                <small class="error"><?= e($errors['body']) ?></small>
            <?php endif; ?>
        </label>
		
        <div x-data="{ published: <?= $article['published_at'] ? 'true' : 'false' ?> }">
			<label class="checkbox">
				<input type="checkbox" name="publish" x-model="published"
					   <?= $article['published_at'] ? 'checked' : '' ?>>
				<span>Опубликовано</span>
			</label>

			<label class="checkbox" x-show="published" x-cloak>
				<input type="checkbox" name="comments_enabled"
					   <?= (int)$article['comments_enabled'] === 1 ? 'checked' : '' ?>>
				<span>Разрешить комментарии</span>
			</label>
		</div>

        <div class="editor-actions">
            <button type="submit" class="btn-primary">Сохранить</button>
            <a href="/article/<?= e($article['slug']) ?>" class="link-btn">Отмена</a>
        </div>
    </form>
</section>
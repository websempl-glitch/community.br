<section class="editor">
    <header class="page-head">
        <h1>Новая статья</h1>
        <p class="lead">Поддерживается Markdown: <code># заголовок</code>,
           <code>**жирный**</code>, <code>`код`</code>, <code>```блок кода```</code>,
           <code>&gt; цитата</code>.</p>
    </header>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" action="/article/create" class="form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <label class="field">
            <span>Заголовок</span>
            <input type="text" name="title" required
                   value="<?= e($old['title'] ?? '') ?>"
                   class="<?= !empty($errors['title']) ? 'has-error' : '' ?>">
            <?php if (!empty($errors['title'])): ?>
                <small class="error"><?= e($errors['title']) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Лид (краткое описание)</span>
            <input type="text" name="excerpt" value="<?= e($old['excerpt'] ?? '') ?>">
        </label>
		
        <div x-data="{ 
			suggestMode: false,
			parentId: <?= (int)($old['parent_id'] ?? 0) ?>, 
			subId: <?= (int)($old['category_id'] ?? 0) ?>,
			suggestParentId: <?= (int)($old['suggest_parent_id'] ?? 0) ?>,
			categories: <?= htmlspecialchars(json_encode($categories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
			subcategories: <?= htmlspecialchars(json_encode($subcategories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>
		}">
			<!-- Переключатель -->
			<div style="margin-bottom: 16px;">
				<label class="checkbox" style="font-weight: 500;">
					<input type="checkbox" x-model="suggestMode">
					<span>Не нашли нужный раздел? Предложите свой</span>
				</label>
			</div>

			<!-- Обычный выбор раздела -->
			<div x-show="!suggestMode">
				<div class="field">
					<span>Раздел</span>
					<select name="parent_id" x-model="parentId" @change="subId = 0" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); font: inherit; background: var(--input-bg); color: var(--text);">
						<option value="">— Без раздела —</option>
						<template x-for="cat in categories" :key="cat.id">
							<option :value="cat.id" x-text="cat.name"></option>
						</template>
					</select>
				</div>

				<div class="field" x-show="parentId" x-cloak style="margin-top: 12px;">
					<span>Подраздел</span>
					<select name="category_id" x-model="subId" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); font: inherit; background: var(--input-bg); color: var(--text);">
						<option value="">— Прямо в разделе —</option>
						<template x-for="sub in (subcategories[parentId] || [])" :key="sub.id">
							<option :value="sub.id" x-text="sub.name"></option>
						</template>
					</select>
				</div>
			</div>

			<!-- Форма предложения раздела -->
			<div x-show="suggestMode" x-cloak style="background: var(--input-bg); padding: 16px; border-radius: var(--radius); border: 1px dashed var(--border); margin-bottom: 16px;">
				<p class="hint" style="margin-bottom: 12px;">Статья будет сохранена в черновиках и опубликуется после одобрения раздела.</p>
				
				<div class="field">
					<span>Название нового раздела или подраздела</span>
					<input type="text" name="suggest_name" x-bind:disabled="!suggestMode" value="<?= e($old['suggest_name'] ?? '') ?>" placeholder="Например: Умный дом">
				</div>
				
				<div class="field" style="margin-top: 12px;">
					<span>В какой существующий раздел его поместить? (оставьте пустым, если это новый корневой раздел)</span>
					<select name="suggest_parent_id" x-bind:disabled="!suggestMode" x-model="suggestParentId" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius); font: inherit; background: var(--card-bg); color: var(--text);">
						<option value="">— Корневой раздел (верхний уровень) —</option>
						<template x-for="cat in categories" :key="cat.id">
							<option :value="cat.id" x-text="cat.name"></option>
						</template>
					</select>
				</div>

				<div class="field" style="margin-top: 12px;">
					<span>Кратко опишите, зачем нужен этот раздел</span>
					<textarea name="suggest_desc" x-bind:disabled="!suggestMode" rows="2" class="textarea"><?= e($old['suggest_desc'] ?? '') ?></textarea>
				</div>
			</div>
		</div>
		
        <label class="field">
            <span>Текст статьи</span>
            <textarea name="body" rows="18" required
                      class="textarea <?= !empty($errors['body']) ? 'has-error' : '' ?>"
					  data-md-editor="full"><?= e($old['body'] ?? '') ?></textarea>
            <?php if (!empty($errors['body'])): ?>
                <small class="error"><?= e($errors['body']) ?></small>
            <?php endif; ?>
        </label>
		
		<div x-data="{ published: <?= !empty($old['publish']) ? 'true' : 'false' ?> }">
			<label class="checkbox">
				<input type="checkbox" name="publish" x-model="published"
					   <?= !empty($old['publish']) ? 'checked' : '' ?>>
				<span>Опубликовать сразу</span>
			</label>

			<label class="checkbox" x-show="published" x-cloak>
				<input type="checkbox" name="comments_enabled" checked>
				<span>Разрешить комментарии</span>
			</label>
		</div>

        <div class="editor-actions">
			<button type="submit" class="btn-primary">Сохранить</button>
			<a href="/" class="link-btn">Отмена</a>
		</div>
    </form>
</section>
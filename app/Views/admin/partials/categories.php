<h2 class="profile-tab-title">Управление разделами</h2>

<!-- Форма создания -->
<div class="admin-cat-group" style="margin-bottom: 32px;">
    <h3 style="margin-bottom: 16px; font-size: 16px;">Добавить раздел</h3>
    <form method="post" action="/admin/categories" x-data>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        
        <div class="admin-form-grid">
            <label class="field">
                <span>Название</span>
                <input type="text" name="name" required placeholder="Например: Устройства" class="admin-input">
            </label>
            
            <label class="field">
                <span>Родительский раздел</span>
                <select name="parent_id" class="admin-input">
                    <option value="">— Верхний уровень —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        
        <button type="submit" class="btn-primary" style="margin-top: 16px;">Создать</button>
    </form>
</div>

<!-- Список -->
<div class="admin-categories-list">
    <?php foreach ($categories as $cat): ?>
        <div class="admin-cat-group">
            <!-- Корневая категория -->
            <div class="admin-cat-row">
                <form id="update-cat-<?= $cat['id'] ?>" method="post" action="/admin/categories/<?= $cat['id'] ?>/update" style="display: contents;">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="text" name="name" value="<?= e($cat['name']) ?>" required class="admin-input">
                </form>
                
                <button type="submit" form="update-cat-<?= $cat['id'] ?>" class="btn-secondary">Сохранить</button>
                
                <form id="delete-cat-<?= $cat['id'] ?>" method="post" action="/admin/categories/<?= $cat['id'] ?>/delete" style="display: contents;">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                </form>
                <button type="submit" form="delete-cat-<?= $cat['id'] ?>" class="btn-danger-link" onclick="return confirm('Удалить раздел?');">Удалить</button>
            </div>

            <!-- Подразделы -->
            <?php if (!empty($subcategories[$cat['id']])): ?>
                <div class="admin-subcats">
                    <?php foreach ($subcategories[$cat['id']] as $sub): ?>
                        <div class="admin-cat-row" x-data="{ 
                            preview: '<?= $sub['image_path'] ? '/' . e($sub['image_path']) : '' ?>', 
                            deleteImg: false,
                            fileName: ''
                        }">
                            
                            <!-- Превью -->
                            <div class="admin-cat-thumb">
                                <template x-if="preview && !deleteImg">
                                    <div style="position:relative; width:100%; height:100%;">
                                        <img :src="preview" alt="">
                                        <button type="button" class="thumb-remove" @click="deleteImg = true; preview = ''">×</button>
                                    </div>
                                </template>
                                <template x-if="!preview || deleteImg">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                </template>
                            </div>

                            <!-- Форма обновления подраздела -->
                            <form id="update-sub-<?= $sub['id'] ?>" method="post" action="/admin/categories/<?= $sub['id'] ?>/update" enctype="multipart/form-data" style="display: contents;">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="delete_image" :value="deleteImg ? '1' : '0'">
                                
                                <input type="text" name="name" value="<?= e($sub['name']) ?>" required class="admin-input">
                                
                                <div class="custom-file">
                                    <input type="file" name="image" accept="image/*" id="sub-img-<?= $sub['id'] ?>" 
                                           @change="preview = URL.createObjectURL($event.target.files[0]); deleteImg = false; fileName = $event.target.files[0].name;">
                                    <label for="sub-img-<?= $sub['id'] ?>" class="btn-secondary">Фото</label>
                                </div>
                                <span class="hint" style="font-size: 11px; width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="fileName || 'Не выбрано'"></span>
                            </form>
                            
                            <button type="submit" form="update-sub-<?= $sub['id'] ?>" class="btn-secondary">Сохранить</button>
                            
                            <form id="delete-sub-<?= $sub['id'] ?>" method="post" action="/admin/categories/<?= $sub['id'] ?>/delete" style="display: contents;">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            </form>
                            <button type="submit" form="delete-sub-<?= $sub['id'] ?>" class="btn-danger-link" onclick="return confirm('Удалить подраздел?');">Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
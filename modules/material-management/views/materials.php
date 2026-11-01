<?php
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">材料主数据 · 全局目录</p><h1>材料与分类</h1><p class="muted">材料编码、名称、单位、规格和分类为全局主数据，不归属当前项目。</p></div>
</section>
<section class="panel">
    <div class="panel-heading"><h2>材料分类</h2><span class="muted"><?= count($data['categories']) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.category.save">
            <label>分类名称<input name="category_name" maxlength="255" required></label>
            <label>上级分类<select name="parent_category_id"><option value="">无</option><?php foreach ($data['categories'] as $category): ?><option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option><?php endforeach; ?></select></label>
            <label>排序值<input type="number" name="sort_order" min="0" max="1000000" value="0"></label>
            <button class="button button-primary" type="submit">新增分类</button>
        </form>
    <?php endif; ?>
    <?php foreach ($data['categories'] as $category): ?>
        <form method="post" class="form-grid record-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.category.save">
            <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">
            <label>分类名称<input name="category_name" maxlength="255" value="<?= $escape($category['category_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
            <label>上级分类<select name="parent_category_id" <?= $canEdit ? '' : 'disabled' ?>><option value="">无</option><?php foreach ($data['categories'] as $parent): if ((int) $parent['category_id'] === (int) $category['category_id']) { continue; } ?><option value="<?= (int) $parent['category_id'] ?>" <?= (int) $parent['category_id'] === (int) $category['parent_category_id'] ? 'selected' : '' ?>><?= $escape($parent['category_name']) ?></option><?php endforeach; ?></select></label>
            <label>排序值<input type="number" name="sort_order" min="0" max="1000000" value="<?= (int) $category['sort_order'] ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
            <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
        </form>
        <?php if ($canDelete): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.category.delete"><input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>"><button class="button button-quiet" type="submit">删除未引用分类</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>材料目录</h2><span class="muted"><?= count($data['materials']) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.save">
            <label>材料编码<input name="material_code" maxlength="50"></label>
            <label>材料名称<input name="material_name" maxlength="255" required></label>
            <label>计量单位<input name="unit" maxlength="50" required></label>
            <label>分类<select name="category_id"><option value="">无</option><?php foreach ($data['categories'] as $category): ?><option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option><?php endforeach; ?></select></label>
            <label class="field-wide">规格<textarea name="specifications" rows="2" maxlength="20000"></textarea></label>
            <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="2" maxlength="20000"></textarea></label>
            <div class="field-wide"><button class="button button-primary" type="submit">新增材料</button></div>
        </form>
    <?php endif; ?>
    <?php if ($data['materials'] === []): ?><p class="empty-state">尚无材料。</p><?php endif; ?>
    <?php foreach ($data['materials'] as $material): ?>
        <details class="record-card">
            <summary><strong><?= $escape($material['material_name']) ?></strong> <span class="muted"><?= $escape($material['material_code'] ?: '无编码') ?> · <?= $escape($material['unit']) ?> · <?= $escape($material['category_name'] ?? '未分类') ?></span></summary>
            <form method="post" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.save">
                <input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                <label>材料编码<input name="material_code" maxlength="50" value="<?= $escape($material['material_code'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>材料名称<input name="material_name" maxlength="255" value="<?= $escape($material['material_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>计量单位<input name="unit" maxlength="50" value="<?= $escape($material['unit']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>分类<select name="category_id" <?= $canEdit ? '' : 'disabled' ?>><option value="">无</option><?php foreach ($data['categories'] as $category): ?><option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $material['category_id'] ? 'selected' : '' ?>><?= $escape($category['category_name']) ?></option><?php endforeach; ?></select></label>
                <label class="field-wide">规格<textarea name="specifications" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($material['specifications'] ?? '') ?></textarea></label>
                <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($material['material_description_detailed'] ?? '') ?></textarea></label>
                <?php if ($canEdit): ?><div class="field-wide"><button class="button button-secondary" type="submit">保存材料</button></div><?php endif; ?>
            </form>
        </details>
        <?php if ($canDelete): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.delete"><input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>"><button class="button button-quiet" type="submit">删除未引用材料</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

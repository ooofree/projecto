<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading"><div><p class="eyebrow">材料主数据 · 全局目录</p><h1>品牌管理</h1></div></section>
<section class="panel">
    <div class="panel-heading"><h2>品牌</h2><span class="muted"><?= count($brands) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.brand.save">
            <label>品牌名称<input name="brand_name" maxlength="255" required></label><label>外部链接<input type="url" name="external_link" maxlength="2048"></label>
            <button class="button button-primary" type="submit">新增品牌</button>
        </form>
    <?php endif; ?>
    <?php foreach ($brands as $brand): ?>
        <form method="post" class="form-grid record-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.brand.save"><input type="hidden" name="brand_id" value="<?= (int) $brand['brand_id'] ?>">
            <label>品牌名称<input name="brand_name" maxlength="255" value="<?= $escape($brand['brand_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
            <label>外部链接<input type="url" name="external_link" maxlength="2048" value="<?= $escape($brand['external_link'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
            <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
        </form>
        <?php if ($canDelete): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.brand.delete"><input type="hidden" name="brand_id" value="<?= (int) $brand['brand_id'] ?>"><button class="button button-quiet" type="submit">删除未引用品牌</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

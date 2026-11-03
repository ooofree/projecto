<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading"><div><p class="eyebrow">供应链主数据 · 全局目录</p><h1>供应商</h1></div></section>
<section class="panel">
    <div class="panel-heading"><h2>供应商</h2><span class="muted"><?= count($suppliers) ?> 家</span></div>
    <?php if ($canEdit): ?>
        <details class="entry-disclosure">
            <summary>新增供应商</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier.save">
                <label>供应商名称<input name="supplier_name" maxlength="255" required></label><label>联系人<input name="contact_person" maxlength="255"></label><label>电话<input name="phone_number" maxlength="100"></label>
                <label>国家代码<input name="country_code" maxlength="2" pattern="[A-Za-z]{2}"></label><label>网站<input type="url" name="website_url" maxlength="2048"></label><label class="field-wide">地址<textarea name="address" rows="2" maxlength="20000"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存供应商</button></div>
            </form>
        </details>
    <?php endif; ?>
    <?php foreach ($suppliers as $supplier): ?>
        <details class="record-card">
            <summary><strong><?= $escape($supplier['supplier_name']) ?></strong> <span class="muted"><?= $escape($supplier['country_code'] ?? '') ?> · <?= $escape($supplier['contact_person'] ?? '') ?></span></summary>
            <form method="post" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier.save"><input type="hidden" name="supplier_id" value="<?= (int) $supplier['supplier_id'] ?>">
                <label>供应商名称<input name="supplier_name" maxlength="255" value="<?= $escape($supplier['supplier_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label><label>联系人<input name="contact_person" maxlength="255" value="<?= $escape($supplier['contact_person'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>电话<input name="phone_number" maxlength="100" value="<?= $escape($supplier['phone_number'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label><label>国家代码<input name="country_code" maxlength="2" value="<?= $escape($supplier['country_code'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>网站<input type="url" name="website_url" maxlength="2048" value="<?= $escape($supplier['website_url'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label><label class="field-wide">地址<textarea name="address" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($supplier['address'] ?? '') ?></textarea></label>
                <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存供应商</button><?php endif; ?>
            </form>
        </details>
        <?php if ($canEdit): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier.delete"><input type="hidden" name="supplier_id" value="<?= (int) $supplier['supplier_id'] ?>"><button class="button button-quiet" type="submit">删除无关联供应商</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

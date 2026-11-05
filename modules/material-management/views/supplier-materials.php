<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading"><div><p class="eyebrow">供应链主数据 · 全局目录</p><h1>供应商材料</h1><p class="muted">供应商报价与内部材料通过报价映射关联。</p></div></section>
<section class="panel">
    <div class="panel-heading"><h2>供应商材料目录</h2><span class="muted"><?= count($supplierMaterials) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <details class="entry-disclosure">
            <summary>新增供应商材料</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier_material.save">
                <label>供应商<select name="supplier_id" required><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['supplier_id'] ?>"><?= $escape($supplier['supplier_name']) ?></option><?php endforeach; ?></select></label>
                <label>品牌<select name="brand_id"><option value="">无</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['brand_id'] ?>"><?= $escape($brand['brand_name']) ?></option><?php endforeach; ?></select></label>
                <label>供应商材料编号<input name="supplier_material_code" maxlength="100" required></label><label>供应商材料名称<input name="supplier_material_name" maxlength="255" required></label>
                <label>交期（天）<input type="number" name="lead_time_days" min="0" max="36500"></label><label>覆盖数量<input type="number" name="coverage_quantity" min="0" step="0.0001"></label>
                <label>覆盖单位<input name="coverage_unit" maxlength="50"></label><label>包装总价<input type="number" name="price_total" min="0" step="0.0001"></label><label>币种<select name="currency_code"><option value="">未指定</option><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>"><?= $escape($name) ?></option><?php endforeach; ?></select></label>
                <label class="field-wide">规格<textarea name="specification" rows="2" maxlength="20000"></textarea></label><label class="field-wide">详情<textarea name="material_details" rows="2" maxlength="20000"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存供应商材料</button></div>
            </form>
        </details>
    <?php endif; ?>
    <?php foreach ($supplierMaterials as $item): ?>
        <details class="record-card">
            <summary><strong><?= $escape($item['supplier_material_name']) ?></strong> <span class="muted"><?= $escape($item['supplier_name']) ?> · <?= $escape($item['supplier_material_code']) ?></span></summary>
            <form method="post" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier_material.save"><input type="hidden" name="supplier_material_id" value="<?= (int) $item['supplier_material_id'] ?>">
                <label>供应商<select name="supplier_id" required <?= $canEdit ? '' : 'disabled' ?>><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['supplier_id'] ?>" <?= (int) $supplier['supplier_id'] === (int) $item['supplier_id'] ? 'selected' : '' ?>><?= $escape($supplier['supplier_name']) ?></option><?php endforeach; ?></select></label>
                <label>品牌<select name="brand_id" <?= $canEdit ? '' : 'disabled' ?>><option value="">无</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['brand_id'] ?>" <?= (int) $brand['brand_id'] === (int) $item['brand_id'] ? 'selected' : '' ?>><?= $escape($brand['brand_name']) ?></option><?php endforeach; ?></select></label>
                <label>材料编号<input name="supplier_material_code" maxlength="100" value="<?= $escape($item['supplier_material_code']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label><label>材料名称<input name="supplier_material_name" maxlength="255" value="<?= $escape($item['supplier_material_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>交期（天）<input type="number" name="lead_time_days" min="0" max="36500" value="<?= $escape($item['lead_time_days'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label><label>覆盖数量<input type="number" name="coverage_quantity" min="0" step="0.0001" value="<?= $escape($item['coverage_quantity'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>覆盖单位<input name="coverage_unit" maxlength="50" value="<?= $escape($item['coverage_unit'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label><label>包装总价<input type="number" name="price_total" min="0" step="0.0001" value="<?= $escape($item['price_total'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label><label>币种<select name="currency_code" <?= $canEdit ? '' : 'disabled' ?>><option value="" <?= empty($item['currency_code']) ? 'selected' : '' ?>>未指定</option><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>" <?= $code === $item['currency_code'] ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></label>
                <label class="field-wide">规格<textarea name="specification" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($item['specification'] ?? '') ?></textarea></label><label class="field-wide">详情<textarea name="material_details" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($item['material_details'] ?? '') ?></textarea></label>
                <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
            </form>
        </details>
        <?php if ($canEdit): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.supplier_material.delete"><input type="hidden" name="supplier_material_id" value="<?= (int) $item['supplier_material_id'] ?>"><button class="button button-quiet" type="submit">删除无关联供应商材料</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

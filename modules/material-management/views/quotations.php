<?php
ob_start();
require __DIR__ . '/_nav.php';
$today = \ProjectO\Support::nowLocal('Y-m-d');
?>
<section class="page-heading">
    <div><p class="eyebrow">项目供应商报价</p><h1>材料报价</h1><p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。报价按币种分别比较，不做汇率、税费或成本计算；默认报价由用户手动指定。</p></div>
</section>
<section class="panel">
    <div class="panel-heading"><h2>报价录入</h2></div>
    <?php if (!$canEdit): ?><p class="muted">当前账号仅有查看权限。</p><?php elseif ($data['materials'] === [] || $data['supplierMaterials'] === []): ?><p class="empty-state">请先创建材料、供应商和供应商材料。</p><?php else: ?>
        <details class="entry-disclosure">
            <summary>新增报价</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.quotation.save">
                <label>内部材料<select name="material_id" required><?php foreach ($data['materials'] as $material): ?><option value="<?= (int) $material['material_id'] ?>"><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name']) ?></option><?php endforeach; ?></select></label>
                <label>供应商材料<select name="supplier_material_id" required><?php foreach ($data['supplierMaterials'] as $item): ?><option value="<?= (int) $item['supplier_material_id'] ?>"><?= $escape($item['supplier_name'] . ' · ' . $item['supplier_material_name'] . ' [' . $item['supplier_material_code'] . ']') ?></option><?php endforeach; ?></select></label>
                <label>采购单价<input type="number" name="purchase_unit_price" min="0" step="0.0001" required></label><label>报价日期<input type="date" name="quotation_date" value="<?= $escape($today) ?>" required></label>
                <label>币种<select name="currency_code" required><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>" <?= $code === $defaultCurrency ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></label><label>最小起订量<input type="number" name="min_order_quantity" min="0" step="0.0001"></label>
                <label>确认税率（%）<input type="number" name="confirmed_tax_rate" min="0" max="100" step="0.01" value="0"></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存报价</button></div>
            </form>
        </details>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>报价对比</h2><span class="muted"><?= count($data['quotations']) ?> 条报价</span></div>
    <?php foreach ($data['materials'] as $material): ?>
        <?php $materialQuotes = array_values(array_filter($data['quotations'], static fn (array $quote): bool => (int) $quote['material_id'] === (int) $material['material_id'])); ?>
        <?php if ($materialQuotes === []) { continue; } ?>
        <article class="record-card">
            <div class="record-heading"><div><p class="eyebrow"><?= $escape($material['material_code'] ?: '无编码') ?></p><h3><?= $escape($material['material_name']) ?></h3></div><span class="badge"><?= $escape($material['unit']) ?></span></div>
            <div class="table-wrap">
                <table><thead><tr><th>供应商</th><th>供应商材料</th><th>报价日期</th><th>单价</th><th>币种</th><th>MOQ</th><th>税率快照</th><th>版本</th><th>默认</th></tr></thead><tbody>
                <?php foreach ($materialQuotes as $quote): ?>
                    <tr>
                        <td><?= $escape($quote['supplier_name']) ?></td><td><?= $escape($quote['supplier_material_name'] . ' [' . $quote['supplier_material_code'] . ']') ?></td>
                        <td><?= $escape($quote['quotation_date']) ?></td><td><?= $escape($quote['purchase_unit_price']) ?></td><td><?= $escape($quote['currency_code']) ?></td>
                        <td><?= $escape($quote['min_order_quantity'] ?? '—') ?></td><td><?= $escape(number_format((float) $quote['confirmed_tax_rate'] * 100, 2)) ?>%</td><td><?= (int) $quote['version'] ?></td>
                        <td><?= (int) $material['default_quotation_id'] === (int) $quote['quotation_id'] ? '是' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php if ($canEdit): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.quotation.default"><input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                    <label>人工选择默认报价<select name="quotation_id" required><?php foreach ($materialQuotes as $quote): ?><option value="<?= (int) $quote['quotation_id'] ?>" <?= (int) $material['default_quotation_id'] === (int) $quote['quotation_id'] ? 'selected' : '' ?>><?= $escape($quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code'] . ' · ' . $quote['quotation_date']) ?></option><?php endforeach; ?></select></label>
                    <button class="button button-secondary" type="submit">设置默认</button>
                </form>
                <?php if ($material['default_quotation_id'] !== null): ?>
                    <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.quotation.clear_default"><input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>"><button class="button button-quiet" type="submit">清除默认报价</button></form>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>报价维护</h2></div>
    <?php foreach ($data['quotations'] as $quote): ?>
        <details class="record-card">
            <summary><strong><?= $escape($quote['material_name']) ?></strong> <span class="muted"><?= $escape($quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code'] . ' · v' . $quote['version']) ?></span></summary>
            <form method="post" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.quotation.save"><input type="hidden" name="quotation_id" value="<?= (int) $quote['quotation_id'] ?>"><input type="hidden" name="version" value="<?= (int) $quote['version'] ?>">
                <input type="hidden" name="material_id" value="<?= (int) $quote['material_id'] ?>">
                <label>内部材料<select required disabled><?php foreach ($data['materials'] as $material): ?><option value="<?= (int) $material['material_id'] ?>" <?= (int) $material['material_id'] === (int) $quote['material_id'] ? 'selected' : '' ?>><?= $escape($material['material_name']) ?></option><?php endforeach; ?></select></label>
                <label>供应商材料<select name="supplier_material_id" required <?= $canEdit ? '' : 'disabled' ?>><?php foreach ($data['supplierMaterials'] as $item): ?><option value="<?= (int) $item['supplier_material_id'] ?>" <?= (int) $item['supplier_material_id'] === (int) $quote['supplier_material_id'] ? 'selected' : '' ?>><?= $escape($item['supplier_name'] . ' · ' . $item['supplier_material_name']) ?></option><?php endforeach; ?></select></label>
                <label>采购单价<input type="number" name="purchase_unit_price" min="0" step="0.0001" value="<?= $escape($quote['purchase_unit_price']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label><label>报价日期<input type="date" name="quotation_date" value="<?= $escape($quote['quotation_date']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>币种<select name="currency_code" required <?= $canEdit ? '' : 'disabled' ?>><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>" <?= $code === $quote['currency_code'] ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></label><label>最小起订量<input type="number" name="min_order_quantity" min="0" step="0.0001" value="<?= $escape($quote['min_order_quantity'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>确认税率（%）<input type="number" name="confirmed_tax_rate" min="0" max="100" step="0.01" value="<?= $escape(number_format((float) $quote['confirmed_tax_rate'] * 100, 2, '.', '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <?php if ($canEdit): ?><div class="field-wide"><button class="button button-secondary" type="submit">保存并递增版本</button></div><?php endif; ?>
            </form>
            <?php if ($canEdit): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.quotation.delete"><input type="hidden" name="quotation_id" value="<?= (int) $quote['quotation_id'] ?>"><button class="button button-quiet" type="submit">删除未关联样品的报价</button></form><?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

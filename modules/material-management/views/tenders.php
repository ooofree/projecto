<?php
ob_start();
require __DIR__ . '/_nav.php';
$statusLabels = ['pending' => '待确认', 'approved' => '已确认', 'completed' => '已完成'];
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目清单 · 招标数据</p>
        <h1>招标清单</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。项目基准币种为人民币（CNY）；清单币种可从 ISO 4217 标准目录选择，默认 CNY。金额按录入币种保存，不自动换算。</p>
    </div>
</section>

<?php if ($canEdit && $materials === []): ?>
    <div class="notice notice-info">请先在当前项目建立材料，再录入招标清单。</div>
<?php elseif ($canEdit): ?>
    <section class="panel">
        <div class="panel-heading"><h2>新增招标清单项</h2></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.tender.save">
            <label>项目材料
                <select name="material_id" required>
                    <option value="">请选择材料</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?= (int) $material['material_id'] ?>"><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ' (' . $material['unit'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>招标数量<input type="number" name="tender_quantity" min="0" step="0.01" required></label>
            <label>招标单价<input type="number" name="tender_unit_price" min="0" step="0.01" required></label>
            <label>币种<select name="currency_code" required><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>" <?= $code === $defaultCurrency ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></label>
            <div class="field-wide"><button class="button button-primary" type="submit">保存清单项</button></div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><h2>当前项目清单</h2><span class="muted"><?= count($items) ?> 项</span></div>
    <?php if ($items === []): ?><p class="empty-state">当前项目尚无招标清单项。</p><?php endif; ?>
    <?php foreach ($items as $item): ?>
        <details class="record-card">
            <summary>
                <strong><?= $escape($item['material_name']) ?></strong>
                <span class="muted"><?= $escape($item['tender_quantity'] . ' ' . $item['unit'] . ' · ' . $item['tender_unit_price'] . ' ' . $item['currency_code']) ?></span>
            </summary>
            <p class="record-meta">编码：<?= $escape($item['material_code'] ?: '—') ?> · 分类：<?= $escape($item['category_name'] ?? '未分类') ?> · 清单金额：<?= $escape(number_format((float) $item['tender_quantity'] * (float) $item['tender_unit_price'], 2)) ?> <?= $escape($item['currency_code']) ?> · 状态：<?= $escape($statusLabels[$item['status']] ?? $item['status']) ?></p>
            <?php if ($canEdit): ?>
                <form method="post" class="form-grid record-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.tender.save">
                    <input type="hidden" name="tender_item_id" value="<?= (int) $item['tender_item_id'] ?>">
                    <label>项目材料
                        <select name="material_id" required>
                            <?php foreach ($materials as $material): ?>
                                <option value="<?= (int) $material['material_id'] ?>" <?= (int) $material['material_id'] === (int) $item['material_id'] ? 'selected' : '' ?>><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ' (' . $material['unit'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>招标数量<input type="number" name="tender_quantity" min="0" step="0.01" value="<?= $escape($item['tender_quantity']) ?>" required></label>
                    <label>招标单价<input type="number" name="tender_unit_price" min="0" step="0.01" value="<?= $escape($item['tender_unit_price']) ?>" required></label>
                    <label>币种<select name="currency_code" required><?php foreach ($currencies as $code => $name): ?><option value="<?= $escape($code) ?>" <?= $code === $item['currency_code'] ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></label>
                    <div class="field-wide"><button class="button button-secondary" type="submit">保存更改</button></div>
                </form>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.tender.delete">
                    <input type="hidden" name="tender_item_id" value="<?= (int) $item['tender_item_id'] ?>">
                    <button class="button button-quiet" type="submit">删除清单项</button>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

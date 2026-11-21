<?php
ob_start();
require __DIR__ . '/_nav.php';

$categoryLabels = [
    'material' => '材料', 'auxiliary' => '辅材', 'processing' => '加工',
    'mechanical' => '机械', 'labor' => '人工', 'other' => '其他',
];
$typeLabels = ['normal' => '正常', 'reversal' => '冲销', 'adjustment' => '调整'];
$sourceLabels = ['manual' => '手工录入', 'purchase_order' => '采购订单', 'other' => '其他'];
$normalRecords = array_filter($records, static fn (array $r): bool => (string) $r['record_type'] === 'normal');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">实际发生费用</p>
        <h1>实际费用</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。费用独立登记，与组价重算链路隔离；费用≠材料消耗。</p>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>三级统计（净额口径）</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>项目净额</th><th>已分摊到组价</th><th>未分摊</th></tr></thead>
            <tbody>
            <tr>
                <td><?= $escape((string) $statistics['net']) ?></td>
                <td><?= $escape((string) $statistics['allocated']) ?></td>
                <td><?= $escape((string) $statistics['unallocated']) ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <p class="muted">恒等式：项目净额 ＝ Σ组价分摊 ＋ 未分摊；两套口径不相加。阶段性经营分析，不等同最终利润。</p>
</section>

<section class="panel">
    <div class="panel-heading"><h2>费用记录</h2><span class="muted"><?= count($records) ?> 条</span></div>
    <?php if ($records === []): ?>
        <p class="empty-state">暂无费用记录。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>日期</th><th>类别</th><th>对象</th><th>金额</th><th>币种</th><th>类型</th><th>来源</th><th>说明</th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= $escape((string) $record['occurred_at']) ?></td>
                        <td><?= $escape($categoryLabels[$record['cost_category']] ?? $record['cost_category']) ?></td>
                        <td><?= $escape($record['material_name'] ?? $record['aux_name'] ?? $record['catalog_name'] ?? '—') ?></td>
                        <td><?= $escape((string) $record['amount']) ?></td>
                        <td><?= $escape((string) $record['currency_code']) ?></td>
                        <td><span class="badge"><?= $escape($typeLabels[$record['record_type']] ?? $record['record_type']) ?></span></td>
                        <td><?= $escape($sourceLabels[$record['source_type'] ?? 'manual'] ?? ($record['source_type'] ?? '手工录入')) ?></td>
                        <td><?= $escape($record['description'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($canEdit): ?>
<section class="panel">
    <div class="panel-heading"><h2>登记正常费用</h2></div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="actual.record.save">
        <label>类别
            <select name="cost_category" required>
                <?php foreach ($categoryLabels as $key => $label): ?>
                    <option value="<?= $escape($key) ?>"><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>材料
            <select name="material_id">
                <option value="">—</option>
                <?php foreach ($materials as $material): ?>
                    <option value="<?= (int) $material['material_id'] ?>"><?= $escape($material['material_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>辅材
            <select name="aux_id">
                <option value="">—</option>
                <?php foreach ($auxiliaries as $aux): ?>
                    <option value="<?= (int) $aux['aux_id'] ?>"><?= $escape($aux['aux_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>目录项
            <select name="catalog_id">
                <option value="">—</option>
                <?php foreach (['processing' => '加工', 'mechanical' => '机械', 'labor' => '人工'] as $catType => $catLabel): ?>
                    <optgroup label="<?= $escape($catLabel) ?>">
                        <?php foreach (($catalogOptions[$catType] ?? []) as $item): ?>
                            <option value="<?= (int) $item['catalog_id'] ?>"><?= $escape($item['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label>金额<input type="number" name="amount" step="0.0001" required></label>
        <label>发生日期<input type="date" name="occurred_at" value="<?= \ProjectO\Support::nowLocal('Y-m-d') ?>" required></label>
        <label>税口径
            <select name="tax_status">
                <option value="unknown">未知</option>
                <option value="taxable">含税/应税</option>
                <option value="exempt">免税</option>
            </select>
        </label>
        <label class="field-wide">说明<textarea name="description" rows="2"></textarea></label>
        <div class="field-wide"><button class="button button-primary" type="submit">登记费用</button></div>
    </form>
</section>

<section class="panel">
    <div class="panel-heading"><h2>冲销原记录</h2><span class="muted">仅可冲销正常记录；冲销金额记为负向，原记录不可删除。</span></div>
    <?php if ($normalRecords === []): ?>
        <p class="empty-state">暂无可冲销的正常费用记录。</p>
    <?php else: ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="actual.reversal.save">
        <label>被冲销记录
            <select name="reversal_of_record_id" required>
                <?php foreach ($normalRecords as $record): ?>
                    <option value="<?= (int) $record['record_id'] ?>">#<?= (int) $record['record_id'] ?> · <?= $escape($record['description'] ?? $categoryLabels[$record['cost_category']]) ?> · <?= $escape((string) $record['amount']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>冲销金额（正数）<input type="number" name="amount" step="0.0001" min="0.0001" required></label>
        <label>发生日期<input type="date" name="occurred_at" value="<?= \ProjectO\Support::nowLocal('Y-m-d') ?>" required></label>
        <label class="field-wide">说明<textarea name="description" rows="2"></textarea></label>
        <div class="field-wide"><button class="button button-secondary" type="submit">登记冲销</button></div>
    </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>调整原记录</h2><span class="muted">仅可调整正常记录；金额可正可负，不形成调整链。</span></div>
    <?php if ($normalRecords === []): ?>
        <p class="empty-state">暂无可调整的正常费用记录。</p>
    <?php else: ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="actual.adjustment.save">
        <label>被调整记录
            <select name="adjustment_of_record_id" required>
                <?php foreach ($normalRecords as $record): ?>
                    <option value="<?= (int) $record['record_id'] ?>">#<?= (int) $record['record_id'] ?> · <?= $escape($record['description'] ?? $categoryLabels[$record['cost_category']]) ?> · <?= $escape((string) $record['amount']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>调整金额（可负）<input type="number" name="amount" step="0.0001" required></label>
        <label>发生日期<input type="date" name="occurred_at" value="<?= \ProjectO\Support::nowLocal('Y-m-d') ?>" required></label>
        <label class="field-wide">说明<textarea name="description" rows="2" required></textarea></label>
        <div class="field-wide"><button class="button button-secondary" type="submit">登记调整</button></div>
    </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>分摊到组价</h2></div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="actual.allocation.save">
        <label>费用记录
            <select name="record_id" required>
                <?php foreach ($normalRecords as $record): ?>
                    <option value="<?= (int) $record['record_id'] ?>">#<?= (int) $record['record_id'] ?> · <?= $escape($record['description'] ?? $categoryLabels[$record['cost_category']]) ?> · <?= $escape((string) $record['amount']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>组价
            <select name="costing_id" required>
                <?php foreach ($costings as $costing): ?>
                    <option value="<?= (int) $costing['costing_id'] ?>"><?= $escape($costing['material_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>分摊金额<input type="number" name="allocated_amount" step="0.0001" min="0.0001" required></label>
        <div class="field-wide"><button class="button button-secondary" type="submit">保存分摊</button></div>
    </form>
</section>
<?php endif; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

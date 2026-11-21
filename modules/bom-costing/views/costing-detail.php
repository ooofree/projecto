<?php
ob_start();
require __DIR__ . '/_nav.php';

$statusLabels = ['draft' => '草稿', 'confirmed' => '已确认'];
$calcStatusLabels = ['incomplete' => '信息不全', 'calculable' => '可计算', 'complete' => '已齐备'];
$resourceTypeLabels = [
    'material' => '材料', 'auxiliary' => '辅材', 'processing' => '加工',
    'mechanical' => '机械', 'labor' => '人工', 'parent_self' => '父项自身', 'other' => '其他',
];

$detailRows = is_array($costing['detail_rows'] ?? null) ? $costing['detail_rows'] : [];
$compositionRows = [];
$resourceRows = [];
foreach ($detailRows as $row) {
    if (str_starts_with((string) $row['kind'], 'resource:')) {
        $resourceRows[] = $row;
        continue;
    }
    $compositionRows[] = $row;
}

// 右栏成本预览先渲染进缓冲：既保证它是同一份 _cost-preview 片段（列表页与详情页口径一致），
// 也让左栏表格可以复用片段里定义的数量／金额格式化函数。
ob_start();
$previewHead = false;
$showDetailTable = true;
$previewLink = false;
require __DIR__ . '/_cost-preview.php';
$previewHtml = (string) ob_get_clean();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">组价详情</p>
        <h1><?= $escape($costing['material_name']) ?></h1>
        <p class="muted">状态：<?= $escape($statusLabels[$costing['status']] ?? $costing['status']) ?> ／
            齐备度：<?= $escape($calcStatusLabels[$costing['calc_status']] ?? $costing['calc_status']) ?> ／
            版本 v<?= (int) $costing['version'] ?> ／ 更新：<?= $escape(\ProjectO\Support::formatDateTime($costing['updated_at'])) ?></p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="?page=costings">返回清单</a>
        <?php if ($canEdit && $costing['status'] === 'draft'): ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="costing.recalc">
                <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                <button class="button button-secondary" type="submit">重新取价</button>
            </form>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="costing.confirm">
                <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                <button class="button button-primary" type="submit">确认组价</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<div class="cost-editor-workspace">
    <div class="cost-editor-main">
        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>材料组成</h2>
                    <span class="muted">实时引用材料管理里的组成关系，组价内不可修改；用量系数＝每 1 <?= $escape((string) $costing['unit']) ?> 父项的直接组成量</span>
                </div>
                <span class="muted"><?= count($compositionRows) ?> 行</span>
            </div>
            <?php if ($compositionRows === []): ?>
                <p class="empty-state">该父材料尚未配置子材料组成。请到材料管理维护组成关系，保存后回到本页重新取价。</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table cost-editor-table cost-comp-table">
                        <thead>
                            <tr>
                                <th>子材料</th>
                                <th>单位</th>
                                <th class="cost-num">用量系数</th>
                                <th>单价 / 取价</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($compositionRows as $row): ?>
                            <tr>
                                <td>
                                    <span class="cost-row-name"><?= $escape((string) ($row['object_name'] ?? '—')) ?></span>
                                    <?php if (($row['object_code'] ?? null) !== null && $row['object_code'] !== ''): ?>
                                        <span class="muted record-meta"><?= $escape((string) $row['object_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $escape((string) ($row['unit'] ?? '—')) ?></td>
                                <td class="cost-num"><?= $escape(cost_format_qty($row['quantity'])) ?></td>
                                <td class="cost-price-cell">
                                    <span class="cost-price-amount">
                                        <?php if ($row['unit_price'] !== null): ?>
                                            <?= $escape(number_format((float) $row['unit_price'], 2)) ?>
                                            <span class="muted cost-unit"><?= $escape((string) ($row['currency_code'] ?? '')) ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </span>
                                    <?php if ($row['status'] === 'priced'): ?>
                                        <span class="badge">已计价</span>
                                    <?php else: ?>
                                        <span class="badge cost-badge-missing">缺价</span>
                                    <?php endif; ?>
                                    <?php if ($row['status'] === 'priced' && (string) ($row['source'] ?? '') !== ''): ?>
                                        <span class="muted cost-row-note"><?= $escape((string) $row['source']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($row['status'] !== 'priced' && ($row['reason'] ?? null) !== null): ?>
                                        <span class="muted cost-row-note"><?= $escape((string) $row['reason']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>资源行</h2>
                    <span class="muted">加工／机械／人工／辅材，按每 1 <?= $escape((string) $costing['unit']) ?> 父项计；保存后自动重算</span>
                </div>
                <span class="muted"><?= count($resourceRows) ?> 行</span>
            </div>
            <?php if ($resourceRows === []): ?>
                <p class="empty-state">还没有资源行。用下方表单添加加工、机械、人工或辅材。</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table cost-editor-table cost-res-table">
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>对象</th>
                                <th class="cost-num">数量</th>
                                <th>单价 / 取价</th>
                                <th class="cost-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($resourceRows as $row): ?>
                            <tr>
                                <td class="cost-detail-kind"><?= $escape($resourceTypeLabels[substr((string) $row['kind'], 9)] ?? '其他') ?></td>
                                <td>
                                    <span class="cost-row-name"><?= $escape((string) ($row['object_name'] ?? '—')) ?></span>
                                    <?php if (($row['object_code'] ?? null) !== null && $row['object_code'] !== ''): ?>
                                        <span class="muted record-meta"><?= $escape((string) $row['object_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cost-num">
                                    <?= $escape(cost_format_qty($row['quantity'])) ?>
                                    <span class="muted cost-unit"><?= $escape((string) ($row['unit'] ?? '—')) ?></span>
                                </td>
                                <td class="cost-price-cell">
                                    <span class="cost-price-amount">
                                        <?php if ($row['unit_price'] !== null): ?>
                                            <?= $escape(number_format((float) $row['unit_price'], 2)) ?>
                                            <span class="muted cost-unit"><?= $escape((string) ($row['currency_code'] ?? '')) ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </span>
                                    <?php if ($row['status'] === 'priced'): ?>
                                        <span class="badge">已计价</span>
                                    <?php else: ?>
                                        <span class="badge cost-badge-missing">缺价</span>
                                    <?php endif; ?>
                                    <?php if ($row['status'] === 'priced' && (string) ($row['source'] ?? '') !== ''): ?>
                                        <span class="muted cost-row-note"><?= $escape((string) $row['source']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($row['status'] !== 'priced' && ($row['reason'] ?? null) !== null): ?>
                                        <span class="muted cost-row-note"><?= $escape((string) $row['reason']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cost-actions">
                                    <?php if ($canEdit): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="costing.resource.delete">
                                            <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                                            <input type="hidden" name="resource_line_id" value="<?= (int) $row['resource_line_id'] ?>">
                                            <button class="button button-quiet" type="submit">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <form method="post" class="form-grid cost-editor-add">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="costing.resource.save">
                    <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                    <label>类型
                        <select name="resource_type" required>
                            <option value="">请选择</option>
                            <option value="processing">加工</option>
                            <option value="mechanical">机械</option>
                            <option value="labor">人工</option>
                            <option value="auxiliary">辅材</option>
                        </select>
                    </label>
                    <label>目录项
                        <select name="catalog_id">
                            <option value="">—</option>
                            <?php foreach ($catalogOptions as $catType => $items): ?>
                                <optgroup label="<?= $escape($resourceTypeLabels[$catType] ?? $catType) ?>">
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= (int) $item['catalog_id'] ?>"><?= $escape($item['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
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
                    <label>数量<input type="number" name="quantity" min="0" step="0.000001" required></label>
                    <div class="field-wide"><button class="button button-primary" type="submit">添加资源行</button></div>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <aside class="panel cost-editor-preview cost-preview-panel">
        <div class="panel-heading">
            <div>
                <h2>成本预览</h2>
                <span class="muted">成本项小计占比；管理费／利润／税等费率未定案，暂不计入</span>
            </div>
        </div>
        <?= $previewHtml ?>
    </aside>
</div>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
ob_start();
require __DIR__ . '/_nav.php';

$statusLabels = ['draft' => '草稿', 'confirmed' => '已确认'];
$calcStatusLabels = ['incomplete' => '信息不全', 'calculable' => '可计算', 'complete' => '已齐备'];

/** 分类分组键：服务端已按分类排序，这里只归并，不改顺序。 */
$groupedCostings = [];
foreach ($costings as $costing) {
    $groupedCostings[(string) $costing['category_name']][] = $costing;
}
$selectedCostingId = $selectedCosting !== null ? (int) $selectedCosting['costing_id'] : 0;
$groupAnchor = static fn (string $categoryName): string => 'cost-group-' . (int) crc32($categoryName);
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">组价清单</p>
        <h1>BOM 组价</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。左侧点一份组价，右侧立即显示成本构成与占比；材料组成关系在材料管理中维护，组价实时引用，不存组件副本。</p>
    </div>
</section>

<div class="cost-workspace">
    <section class="panel cost-list-panel">
        <div class="panel-heading">
            <div>
                <h2>组价清单</h2>
                <span class="muted"><?= count($costings) ?> 份</span>
            </div>
            <label class="search-field">
                <span class="sr-only">搜索父材料</span>
                <input type="search" placeholder="搜索父材料…" data-list-search="costings-table">
            </label>
        </div>

        <?php if ($costings === []): ?>
            <p class="empty-state">
                当前项目还没有组价。<?= $canEdit && $availableMaterials !== [] ? '在下方“新建组价”里选一个父材料即可建立。' : '请先在材料管理中维护材料与组成关系。' ?>
            </p>
        <?php else: ?>
            <?php if (count($groupedCostings) > 1): ?>
                <div class="cost-chips">
                    <?php foreach ($groupedCostings as $categoryName => $groupItems): ?>
                        <a class="cost-chip" href="#<?= $escape($groupAnchor($categoryName)) ?>">
                            <?= $escape($categoryName) ?><span class="cost-chip-count"><?= count($groupItems) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="data-table cost-list-table">
                    <thead>
                        <tr>
                            <th>父材料</th>
                            <th>状态</th>
                            <th class="cost-num">参考合计</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="costings-table">
                    <?php foreach ($groupedCostings as $categoryName => $groupItems): ?>
                        <tr class="cost-group-head" id="<?= $escape($groupAnchor($categoryName)) ?>" data-list-item data-search="">
                            <td colspan="4">
                                <span class="cost-group-name"><?= $escape($categoryName) ?></span>
                                <span class="muted"><?= count($groupItems) ?> 份组价</span>
                            </td>
                        </tr>
                        <?php foreach ($groupItems as $costing): ?>
                            <?php
                            $isSelected = (int) $costing['costing_id'] === $selectedCostingId;
                            $searchText = mb_strtolower(implode(' ', [
                                (string) $costing['material_name'],
                                (string) $costing['material_code'],
                                (string) $costing['category_name'],
                                $statusLabels[$costing['status']] ?? (string) $costing['status'],
                                $calcStatusLabels[$costing['calc_status']] ?? (string) $costing['calc_status'],
                            ]));
                            ?>
                            <tr class="cost-row<?= $isSelected ? ' is-selected' : '' ?>" data-list-item data-search="<?= $escape($searchText) ?>">
                                <td>
                                    <a class="cost-row-link" href="?page=costings&amp;costing_id=<?= (int) $costing['costing_id'] ?>"><?= $escape((string) $costing['material_name']) ?></a>
                                    <span class="muted record-meta">
                                        <?= $escape(($costing['material_code'] !== null && $costing['material_code'] !== '' ? $costing['material_code'] . ' · ' : '') . (string) $costing['unit']) ?>
                                        · v<?= (int) $costing['version'] ?>
                                        <?php if ((int) $costing['missing_count'] > 0): ?> · 缺 <?= (int) $costing['missing_count'] ?> 项<?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cost-status-cell">
                                        <span class="badge"><?= $escape($statusLabels[$costing['status']] ?? (string) $costing['status']) ?></span>
                                        <span class="badge badge-neutral"><?= $escape($calcStatusLabels[$costing['calc_status']] ?? (string) $costing['calc_status']) ?></span>
                                    </span>
                                </td>
                                <td class="cost-num">
                                    <?php if ((float) $costing['reference_total'] > 0): ?>
                                        <?= $escape(number_format((float) $costing['reference_total'], 2)) ?>
                                        <span class="muted"><?= $escape((string) $costing['reference_currency']) ?><?= (int) $costing['currency_count'] > 1 ? ' ＋' . ((int) $costing['currency_count'] - 1) : '' ?></span>
                                    <?php else: ?>
                                        <span class="muted">待取价</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button button-secondary button-small" href="?page=costing-detail&amp;costing_id=<?= (int) $costing['costing_id'] ?>">打开</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="empty-state" data-list-empty hidden>没有匹配的组价。</p>
        <?php endif; ?>
    </section>

    <aside class="panel cost-preview-panel" id="cost-preview" aria-label="成本预览">
        <div class="panel-heading">
            <div>
                <h2>成本预览</h2>
                <span class="muted">选中左侧组价后显示成本项饼图、汇总与明细</span>
            </div>
            <?php if ($selectedCosting !== null): ?>
                <a class="button button-secondary button-small" href="?page=costing-detail&amp;costing_id=<?= (int) $selectedCosting['costing_id'] ?>">打开详情维护</a>
            <?php endif; ?>
        </div>
        <?php if ($selectedCosting === null): ?>
            <?php if ($previewNotice !== null): ?>
                <p class="cost-inline-warn cost-preview-empty"><?= $escape($previewNotice) ?></p>
            <?php else: ?>
                <p class="empty-state cost-preview-empty">请点击左侧组价清单中的父材料，查看成本预览。</p>
            <?php endif; ?>
        <?php else: ?>
            <?php $costing = $selectedCosting; $showDetailTable = true; require __DIR__ . '/_cost-preview.php'; ?>
        <?php endif; ?>
    </aside>
</div>

<?php if ($canEdit && $availableMaterials !== []): ?>
    <details class="panel cost-create-panel">
        <summary class="cost-create-summary">
            <span class="cost-create-title">新建组价</span>
            <span class="muted">一父一份；只能选择尚未建立组价的材料</span>
        </summary>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="costing.create">
            <label>父材料
                <select name="material_id" required>
                    <option value="">请选择材料</option>
                    <?php foreach ($availableMaterials as $material): ?>
                        <option value="<?= (int) $material['material_id'] ?>"><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ' (' . $material['unit'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="field-wide"><button class="button button-primary" type="submit">建立组价</button></div>
        </form>
    </details>
<?php endif; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
ob_start();
require __DIR__ . '/_nav.php';

$groupMode = ($group ?? 'space') === 'category' ? 'category' : 'space';

// Group the ledger once in PHP: space mode nests building -> floor, category
// mode groups by the material category. Records keep the newest-first order
// from the service query inside each group.
$spaceGroups = [];
$categoryGroups = [];
foreach ($estimates as $estimate) {
    $buildingKey = (string) (int) $estimate['building_id'];
    if (!isset($spaceGroups[$buildingKey])) {
        $spaceGroups[$buildingKey] = [
            'label' => trim($estimate['building_number'] . ' · ' . $estimate['building_name'], ' ·'),
            'sort' => (string) $estimate['building_number'],
            'floors' => [],
            'total' => 0,
        ];
    }
    $floorKey = $estimate['floor_id'] === null ? 'none' : (string) (int) $estimate['floor_id'];
    if (!isset($spaceGroups[$buildingKey]['floors'][$floorKey])) {
        $spaceGroups[$buildingKey]['floors'][$floorKey] = [
            'label' => ($estimate['floor_number'] ?? '') !== ''
                ? trim($estimate['floor_number'] . ' · ' . ($estimate['floor_name'] ?? ''), ' ·')
                : '未指定楼层',
            'sort' => ($estimate['floor_number'] ?? '') !== '' ? (string) $estimate['floor_number'] : '',
            'items' => [],
        ];
    }
    $spaceGroups[$buildingKey]['floors'][$floorKey]['items'][] = $estimate;
    $spaceGroups[$buildingKey]['total']++;

    $categoryName = trim((string) ($estimate['category_name'] ?? ''));
    $categoryKey = $estimate['category_id'] === null || $categoryName === ''
        ? 'none'
        : (string) (int) $estimate['category_id'];
    if (!isset($categoryGroups[$categoryKey])) {
        $categoryGroups[$categoryKey] = [
            'label' => $categoryName !== '' ? $categoryName : '未分类材料',
            'sort' => $categoryName,
            'items' => [],
        ];
    }
    $categoryGroups[$categoryKey]['items'][] = $estimate;
}
uasort($spaceGroups, static fn (array $a, array $b): int => strnatcasecmp($a['sort'], $b['sort']));
foreach ($spaceGroups as &$spaceGroup) {
    uasort($spaceGroup['floors'], static fn (array $a, array $b): int => strnatcasecmp($a['sort'], $b['sort']));
}
unset($spaceGroup);
uasort($categoryGroups, static fn (array $a, array $b): int => strnatcasecmp($a['sort'], $b['sort']));

$formatQuantity = static function (string $value): string {
    if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
        return $value;
    }
    $trimmed = rtrim(rtrim($value, '0'), '.');
    return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
};

$searchText = static function (array $estimate): string {
    $parts = [
        (string) ($estimate['material_code'] ?? ''),
        (string) $estimate['material_name'],
        (string) ($estimate['category_name'] ?? ''),
        (string) $estimate['building_number'],
        (string) $estimate['building_name'],
        (string) ($estimate['floor_number'] ?? ''),
        (string) ($estimate['room_number'] ?? ''),
        (string) ($estimate['remarks'] ?? ''),
    ];
    return mb_strtolower(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')), 'UTF-8');
};

// Searchable material picker: the native select remains the submitted value,
// while the text input and option list are the fast path used by app.js.
$renderMaterialCombo = static function (array $materials, ?int $selectedId) use ($escape): string {
    ob_start(); ?>
    <div class="combo" data-combo>
        <select name="material_id" data-combo-select required aria-label="材料">
            <option value="">请选择材料</option>
            <?php foreach ($materials as $material): ?>
                <option value="<?= (int) $material['material_id'] ?>"<?= $selectedId !== null && (int) $material['material_id'] === $selectedId ? ' selected' : '' ?>><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ' (' . $material['unit'] . ')') ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" class="combo-input" data-combo-input placeholder="输入名称或编码搜索" autocomplete="off" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="搜索并选择材料">
        <ul class="combo-options" data-combo-options role="listbox" hidden></ul>
    </div>
    <?php return (string) ob_get_clean();
};

$renderCountBadge = static function (int $count, string $attribute) use ($escape): string {
    return '<span class="ledger-count" ' . $attribute . ' data-unit=" 条">' . $count . ' 条</span>';
};

$renderRecord = static function (array $estimate) use ($groupMode, $choices, $canEdit, $csrfToken, $escape, $renderMaterialCombo, $searchText, $formatQuantity): string {
    $floorLabel = ($estimate['floor_number'] ?? '') !== ''
        ? trim($estimate['floor_number'] . ' · ' . ($estimate['floor_name'] ?? ''), ' ·')
        : '未指定楼层';
    $roomLabel = ($estimate['room_number'] ?? '') !== '' ? (string) $estimate['room_number'] : '未指定房间';
    $buildingLabel = trim((string) ($estimate['building_name'] ?? '')) !== ''
        ? (string) $estimate['building_name']
        : (string) $estimate['building_number'];
    $spaceLabel = $buildingLabel . ' · ' . $floorLabel . ' · ' . $roomLabel;
    $placeLabel = $groupMode === 'category' ? $spaceLabel : $roomLabel;
    $quantityText = $formatQuantity((string) $estimate['estimated_quantity']) . ' ' . $estimate['unit'];
    $remarksText = trim((string) ($estimate['remarks'] ?? ''));
    // 历史行可能指向子材料（组成关系后建）：候选里已经没有它，这里补回本行自身，
    // 保证存量记录仍能打开、改数量或改绑到顶层项，而不是被迫清空材料。
    $currentMaterials = $choices['materials'];
    $currentMaterialIds = array_map(static fn (array $material): int => (int) $material['material_id'], $currentMaterials);
    if (!in_array((int) $estimate['material_id'], $currentMaterialIds, true)) {
        $currentMaterials[] = [
            'material_id' => (int) $estimate['material_id'],
            'material_code' => (string) ($estimate['material_code'] ?? ''),
            'material_name' => (string) $estimate['material_name'],
            'unit' => (string) $estimate['unit'],
        ];
    }
    $isChildMaterial = in_array((int) $estimate['material_id'], $choices['child_material_ids'] ?? [], true);
    // 既不是组成子件、也没有 BOM 组成的存量行：口径收紧后不参与拆解，页面明确标记待整改。
    $isUncomposedMaterial = !$isChildMaterial && !in_array((int) $estimate['material_id'], $currentMaterialIds, true);
    ob_start(); ?>
    <details class="record-card ledger-record" data-ledger-record data-estimate-id="<?= (int) $estimate['estimate_detail_id'] ?>" data-search="<?= $escape($searchText($estimate)) ?>">
        <summary class="ledger-record-head">
            <span class="ledger-record-title">
                <strong><?= $escape($estimate['material_name']) ?></strong>
                <?php if (($estimate['material_code'] ?? '') !== ''): ?><span class="ledger-record-code"><?= $escape($estimate['material_code']) ?></span><?php endif; ?>
                <?php if ($isChildMaterial): ?><span class="badge badge-neutral" title="该材料现在是他项的组成子件：历史测算保留可编辑，新录入请改按父项测算">子材料</span><?php elseif ($isUncomposedMaterial): ?><span class="badge badge-warning" title="该材料还没有 BOM 组成：先建组成才会参与子材料拆解；新录入不能选它">未建 BOM</span><?php endif; ?>
            </span>
            <span class="ledger-record-place"><?= $escape($placeLabel) ?></span>
            <?php if ($remarksText !== ''): ?><span class="ledger-record-remarks" title="<?= $escape($remarksText) ?>"><?= $escape(mb_strimwidth($remarksText, 0, 36, '…', 'UTF-8')) ?></span><?php endif; ?>
            <span class="ledger-record-quantity"><?= $escape($quantityText) ?></span>
        </summary>
        <div class="ledger-record-body">
            <?php if ($canEdit): ?>
                <form method="post" class="form-grid record-form" data-estimate-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.estimate.save">
                    <input type="hidden" name="estimate_detail_id" value="<?= (int) $estimate['estimate_detail_id'] ?>">
                    <div class="field-wide ledger-material-field">
                        <span class="field-label">材料</span>
                        <?= $renderMaterialCombo($currentMaterials, (int) $estimate['material_id']) ?>
                    </div>
                    <label>楼栋<select name="building_id" required data-cascade-building><option value="">请选择楼栋</option><?php foreach ($choices['buildings'] as $building): ?><option value="<?= (int) $building['building_id'] ?>" <?= (int) $building['building_id'] === (int) $estimate['building_id'] ? 'selected' : '' ?>><?= $escape($building['building_number'] . ' ' . $building['building_name']) ?></option><?php endforeach; ?></select></label>
                    <label>楼层（可选）<select name="floor_id" data-cascade-floor><option value="">不指定</option><?php foreach ($choices['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>" data-building-id="<?= (int) $floor['building_id'] ?>" <?= (int) $floor['floor_id'] === (int) $estimate['floor_id'] ? 'selected' : '' ?>><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                    <label>房间（可选）<select name="room_id" data-cascade-room><option value="">不指定</option><?php foreach ($choices['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>" data-building-id="<?= (int) $room['building_id'] ?>" data-floor-id="<?= (int) $room['floor_id'] ?>" <?= (int) $room['room_id'] === (int) $estimate['room_id'] ? 'selected' : '' ?>><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                    <label>测算数量<input type="number" name="estimated_quantity" min="0" step="0.0001" value="<?= $escape($estimate['estimated_quantity']) ?>" required inputmode="decimal"></label>
                    <label class="field-wide">备注<textarea name="remarks" rows="2" maxlength="20000"><?= $escape($estimate['remarks'] ?? '') ?></textarea></label>
                    <div class="field-wide ledger-record-actions"><button class="button button-primary" type="submit">保存明细</button></div>
                </form>
                <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.estimate.delete"><input type="hidden" name="estimate_detail_id" value="<?= (int) $estimate['estimate_detail_id'] ?>"><button class="button button-quiet" type="submit">删除明细</button></form>
            <?php else: ?>
                <p class="record-meta">备注：<?= $escape($estimate['remarks'] ?? '—') ?></p>
            <?php endif; ?>
        </div>
    </details>
    <?php return (string) ob_get_clean();
};
?>
<section class="page-heading"><div><p class="eyebrow">项目测算 · 材料数量</p><h1>材料测算明细</h1><p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。本期仅记录空间、材料、测算数量和备注，不计算金额、税费、BOM 或汇总成本。测算按 BOM 基本单位（顶层项）计量：有下级组成的父项按它自己的单位（套／个／m／m²）测算，子材料的量随父项组价展开，不单独测算。</p></div></section>
<?php if ($canEdit && ($choices['buildings'] === [] || $choices['materials'] === [])): ?>
    <div class="notice notice-info">
        <?php if ($choices['materials'] === [] && ($choices['material_count'] ?? 0) > 0): ?>
            当前项目还没有任何建立了 BOM 组成的父材料，无法录入测算。测算只按父材料记数量、再按组成拆解到子材料：请先在材料管理里创建组合产品（父项）并添加子材料与用量系数；需要单独计价的部位（如墙面玻璃、吧台玻璃）也先父项化再建组成。
        <?php else: ?>
            创建测算明细前，请先建立当前项目楼栋，并在材料管理中维护材料。
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (($unmeasurableCount ?? 0) > 0): ?>
    <div class="notice notice-warning">
        有 <?= (int) $unmeasurableCount ?> 条测算行的材料还没有 BOM 组成，暂不参与拆解。请到 <a href="?page=materials">材料管理</a> 为这些材料建立组成，或改按已有父材料录入；拆解结果见 <a href="?page=demand-summary">子材料数量汇总</a>。
    </div>
<?php endif; ?>
<?php if ($canEdit && $choices['buildings'] !== [] && $choices['materials'] !== []): ?>
    <section class="panel estimate-entry-panel">
        <div class="panel-heading">
            <div>
                <h2>快速录入</h2>
                <p class="muted">搜材料 → 填数量 → 回车即存，光标自动回到材料框，可连续录入。楼栋/楼层/房间会沿用上次选择，仅在换位置时调整。</p>
                <?php if (($choices['child_material_ids'] ?? []) !== [] || ($choices['uncomposed_material_count'] ?? 0) > 0): ?><p class="muted">候选只列已建立 BOM 组成的父材料<?php if (($choices['child_material_ids'] ?? []) !== []): ?>，已隐藏 <?= count($choices['child_material_ids']) ?> 项子材料：它们按父项的用量系数展开，单独测算会与父项重复计成本<?php endif; ?><?php if (($choices['uncomposed_material_count'] ?? 0) > 0): ?>；另有 <?= (int) $choices['uncomposed_material_count'] ?> 项材料还没有组成，不能直接测算（需先建 BOM）<?php endif; ?>。</p><?php endif; ?>
            </div>
            <span class="entry-counter" data-entry-counter aria-live="polite">本次已录 <strong>0</strong> 条</span>
        </div>
        <form method="post" class="quick-entry" data-estimate-add data-estimate-form data-project-id="<?= (int) $currentProject['project_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.estimate.save">
            <div class="entry-location">
                <span class="entry-location-label">录入位置</span>
                <label class="entry-location-field"><span>楼栋</span><select name="building_id" required data-cascade-building><?php foreach ($choices['buildings'] as $building): ?><option value="<?= (int) $building['building_id'] ?>"><?= $escape($building['building_number'] . ' ' . $building['building_name']) ?></option><?php endforeach; ?></select></label>
                <span class="entry-location-sep" aria-hidden="true">›</span>
                <label class="entry-location-field"><span>楼层（可选）</span><select name="floor_id" data-cascade-floor><option value="">不指定</option><?php foreach ($choices['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>" data-building-id="<?= (int) $floor['building_id'] ?>"><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                <span class="entry-location-sep" aria-hidden="true">›</span>
                <label class="entry-location-field"><span>房间（可选）</span><select name="room_id" data-cascade-room><option value="">不指定</option><?php foreach ($choices['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>" data-building-id="<?= (int) $room['building_id'] ?>" data-floor-id="<?= (int) $room['floor_id'] ?>"><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                <span class="entry-location-hint">自动沿用上次位置</span>
            </div>
            <div class="entry-line">
                <div class="entry-step" aria-hidden="true">1</div>
                <div class="entry-field entry-field-material">
                    <span class="entry-field-label">材料 <span class="entry-field-hint">名称 / 编码搜索，↑↓ 选择，回车确认</span></span>
                    <?= $renderMaterialCombo($choices['materials'], null) ?>
                </div>
                <div class="entry-step" aria-hidden="true">2</div>
                <label class="entry-field entry-field-quantity">测算数量<span class="quantity-box"><input type="number" name="estimated_quantity" min="0" step="0.0001" placeholder="0" required inputmode="decimal" data-quantity-input autocomplete="off"><span class="quantity-unit" data-quantity-unit aria-hidden="true">—</span></span></label>
                <button class="button button-primary entry-submit" type="submit">保存并继续<span class="entry-submit-key" aria-hidden="true">Enter ↵</span></button>
            </div>
            <div class="entry-more">
                <label class="entry-remarks"><span>备注（可选）</span><input name="remarks" maxlength="20000" placeholder="选填，保存后自动清空" autocomplete="off"></label>
                <p class="entry-keys"><kbd>Enter</kbd> 保存并继续 · <kbd>Tab</kbd> 切换字段 · <kbd>Esc</kbd> 关闭候选</p>
            </div>
            <p class="entry-status" data-entry-status role="status" aria-live="polite" hidden></p>
        </form>
        <div class="entry-recent" data-entry-recent hidden>
            <span class="entry-recent-label">本次录入</span>
            <div class="entry-recent-chips" data-entry-recent-chips></div>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>测算记录</h2>
            <p class="muted"><?= $groupMode === 'category' ? '按材料分类归集，可展开每条记录修改数量、空间或材料。' : '按楼栋、楼层归集，可展开每条记录修改数量、空间或材料。' ?></p>
        </div>
        <span class="muted" data-estimate-limit-note>最多显示最近 500 条</span>
    </div>
    <?php if ($estimates === []): ?>
        <p class="empty-state" data-estimate-empty-state>当前项目尚无材料测算明细。<?= $canEdit && $choices['buildings'] !== [] && $choices['materials'] !== [] ? '在页面上方快速录入第一条。' : '' ?></p>
    <?php else: ?>
        <div class="toolbar ledger-toolbar" role="search" aria-label="筛选测算记录">
            <label class="ledger-filter" data-estimate-filter-field hidden>搜索记录<input type="search" name="search" form="estimate-export-form" data-estimate-filter placeholder="材料、编码、楼栋、房间或备注" autocomplete="off"></label>
            <div class="ledger-tabs" role="group" aria-label="测算记录分类方式">
                <a class="button button-<?= $groupMode === 'space' ? 'primary' : 'secondary' ?>" href="?page=estimates&amp;group=space"<?= $groupMode === 'space' ? ' aria-current="page"' : '' ?>>按空间</a>
                <a class="button button-<?= $groupMode === 'category' ? 'primary' : 'secondary' ?>" href="?page=estimates&amp;group=category"<?= $groupMode === 'category' ? ' aria-current="page"' : '' ?>>按材料分类</a>
            </div>
            <form method="post" action="" id="estimate-export-form" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.estimate.export">
                <button class="button button-secondary" type="submit">导出 Excel</button>
            </form>
            <span class="toolbar-count" data-estimate-count data-unit=" 条"><?= count($estimates) ?> 条</span>
        </div>
        <div class="ledger" data-estimate-ledger data-group-mode="<?= $escape($groupMode) ?>">
            <?php if ($groupMode === 'category'): ?>
                <?php foreach ($categoryGroups as $categoryGroup): ?>
                    <details class="ledger-group" data-ledger-group open>
                        <summary class="ledger-group-head">
                            <span class="ledger-group-title"><?= $escape($categoryGroup['label']) ?></span>
                            <?= $renderCountBadge(count($categoryGroup['items']), 'data-group-count') ?>
                        </summary>
                        <div class="ledger-group-body">
                            <?php foreach ($categoryGroup['items'] as $estimate): ?>
                                <?= $renderRecord($estimate) ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($spaceGroups as $spaceGroup): ?>
                    <details class="ledger-group" data-ledger-group open>
                        <summary class="ledger-group-head">
                            <span class="ledger-group-title"><?= $escape($spaceGroup['label']) ?></span>
                            <?= $renderCountBadge((int) $spaceGroup['total'], 'data-group-count') ?>
                        </summary>
                        <div class="ledger-group-body">
                            <?php foreach ($spaceGroup['floors'] as $floorGroup): ?>
                                <details class="ledger-subgroup" data-ledger-subgroup open>
                                    <summary class="ledger-subgroup-head">
                                        <span class="ledger-group-title"><?= $escape($floorGroup['label']) ?></span>
                                        <?= $renderCountBadge(count($floorGroup['items']), 'data-subgroup-count') ?>
                                    </summary>
                                    <div class="ledger-subgroup-body">
                                        <?php foreach ($floorGroup['items'] as $estimate): ?>
                                            <?= $renderRecord($estimate) ?>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="report-empty" data-estimate-empty hidden>没有匹配的测算记录。</p>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>
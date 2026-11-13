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
    ob_start(); ?>
    <details class="record-card ledger-record" data-ledger-record data-search="<?= $escape($searchText($estimate)) ?>">
        <summary class="ledger-record-head">
            <span class="ledger-record-title">
                <strong><?= $escape($estimate['material_name']) ?></strong>
                <?php if (($estimate['material_code'] ?? '') !== ''): ?><span class="ledger-record-code"><?= $escape($estimate['material_code']) ?></span><?php endif; ?>
            </span>
            <span class="ledger-record-place"><?= $escape($placeLabel) ?></span>
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
                        <?= $renderMaterialCombo($choices['materials'], (int) $estimate['material_id']) ?>
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
<section class="page-heading"><div><p class="eyebrow">项目测算 · 材料数量</p><h1>材料测算明细</h1><p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。本期仅记录空间、材料、测算数量和备注，不计算金额、税费、BOM 或汇总成本。</p></div></section>
<?php if ($canEdit && ($choices['buildings'] === [] || $choices['materials'] === [])): ?><div class="notice notice-info">创建测算明细前，请先建立当前项目楼栋，并在材料管理中维护材料。</div><?php endif; ?>
<?php if ($canEdit && $choices['buildings'] !== [] && $choices['materials'] !== []): ?>
    <section class="panel estimate-entry-panel">
        <div class="panel-heading">
            <div>
                <h2>快速录入</h2>
                <p class="muted">搜索并选择材料、填写数量后按回车保存；楼栋/楼层/房间会沿用上次选择，仅在变更时调整。</p>
            </div>
        </div>
        <form method="post" class="quick-entry" data-estimate-add data-estimate-form data-project-id="<?= (int) $currentProject['project_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.estimate.save">
            <div class="quick-entry-row">
                <div class="quick-field quick-field-material">
                    <span class="quick-field-label">材料</span>
                    <?= $renderMaterialCombo($choices['materials'], null) ?>
                </div>
                <label class="quick-field">测算数量<input type="number" name="estimated_quantity" min="0" step="0.0001" value="0" required inputmode="decimal"></label>
                <div class="quick-field quick-field-submit"><button class="button button-primary" type="submit">保存明细</button></div>
            </div>
            <div class="quick-entry-row quick-entry-row-space">
                <label class="quick-field">楼栋<select name="building_id" required data-cascade-building><?php foreach ($choices['buildings'] as $building): ?><option value="<?= (int) $building['building_id'] ?>"><?= $escape($building['building_number'] . ' ' . $building['building_name']) ?></option><?php endforeach; ?></select></label>
                <label class="quick-field">楼层（可选）<select name="floor_id" data-cascade-floor><option value="">不指定</option><?php foreach ($choices['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>" data-building-id="<?= (int) $floor['building_id'] ?>"><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                <label class="quick-field">房间（可选）<select name="room_id" data-cascade-room><option value="">不指定</option><?php foreach ($choices['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>" data-building-id="<?= (int) $room['building_id'] ?>" data-floor-id="<?= (int) $room['floor_id'] ?>"><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                <label class="quick-field">备注（可选）<input name="remarks" maxlength="20000" placeholder="可不填"></label>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>测算记录</h2>
            <p class="muted"><?= $groupMode === 'category' ? '按材料分类归集，可展开每条记录修改数量、空间或材料。' : '按楼栋、楼层归集，可展开每条记录修改数量、空间或材料。' ?></p>
        </div>
        <span class="muted">最多显示最近 500 条</span>
    </div>
    <?php if ($estimates === []): ?>
        <p class="empty-state">当前项目尚无材料测算明细。<?= $canEdit && $choices['buildings'] !== [] && $choices['materials'] !== [] ? '在页面上方快速录入第一条。' : '' ?></p>
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
        <div class="ledger" data-estimate-ledger>
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

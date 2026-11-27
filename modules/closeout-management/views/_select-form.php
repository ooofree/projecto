<?php
$candidateCount = count($selectCandidates);
$selectableCount = 0;
foreach ($selectCandidates as $candidate) {
    if (!empty($candidate['selectable'])) {
        $selectableCount++;
    }
}
?>
<form method="post" class="closeout-select-form" data-closeout-select-form>
    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="action" value="closeout.items.create">
    <input type="hidden" name="plan_id" value="<?= (int) $selectPlan['plan_id'] ?>">

    <div class="panel closeout-select-panel">
        <div class="closeout-select-toolbar">
            <label class="search-field">
                <span class="sr-only">搜索 BOM 材料</span>
                <input type="search" data-list-search="closeout-material-list" placeholder="搜材料名称、编号、分类（即时）" autocomplete="off">
            </label>
            <span class="closeout-selected" data-closeout-select-count>已选 0 项</span>
            <button class="button button-primary button-small" type="submit">纳入所选</button>
        </div>

        <div class="closeout-select-defaults">
            <p class="field-wide muted">
                <?php if ($selectHasParentMaterials): ?>
                    当前项目已有组合 BOM，只列出可整体收口的父材料（下级材料随父项整体消项）。
                <?php else: ?>
                    当前项目 BOM 中没有父材料，全部顶层材料均可纳入。
                <?php endif; ?>
                <?php if ($selectHiddenMaterialCount > 0): ?>
                    已隐藏 <?= (int) $selectHiddenMaterialCount ?> 项非父材料。
                <?php endif; ?>
                <?php if ($candidateCount === 0): ?>
                    BOM 里暂无可纳入的材料，请先维护材料清单。
                <?php endif; ?>
            </p>
            <label>计划开始<input type="date" name="planned_start_date" value="<?= $escape((string) ($selectPlan['planned_start_date'] ?? '')) ?>"></label>
            <label>计划完成<input type="date" name="planned_end_date" value="<?= $escape((string) ($selectPlan['planned_end_date'] ?? '')) ?>"></label>
            <label>责任单位
                <select name="responsible_supplier_id">
                    <option value="">未指定</option>
                    <?php foreach ($selectSuppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['supplier_id'] ?>"><?= $escape($supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>责任班组
                <select name="responsible_crew_id">
                    <option value="">未指定</option>
                    <?php foreach ($crews as $crew): ?>
                        <option value="<?= (int) $crew['crew_id'] ?>"><?= $escape($crew['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>风险等级
                <select name="risk_level">
                    <?php foreach (\ProjectO\Modules\CloseoutManagement\CloseoutLabels::RISK_LEVEL as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= $value === 'medium' ? 'selected' : '' ?>><?= $escape($label) ?></option>
                      <?php endforeach; ?>
                </select>
            </label>
        </div>

        <?php if ($candidateCount > 0): ?>
        <div class="table-wrap closeout-select-table-wrap">
            <table class="data-table closeout-select-table">
                <thead>
                    <tr>
                        <th class="closeout-col-check"><input type="checkbox" data-closeout-material-all aria-label="全选可纳入材料"></th>
                        <th>材料</th>
                        <th>分类</th>
                        <th>规格 / 单位</th>
                        <th>BOM 关系</th>
                        <th>可纳入性</th>
                    </tr>
                </thead>
                <tbody id="closeout-material-list">
                    <?php foreach ($selectCandidates as $candidate): ?>
                        <?php
                        $parentText = $candidate['parents'] === [] ? '顶层材料' : ('上级：' . implode('、', $candidate['parents']));
                        $searchText = mb_strtolower(implode(' ', [
                            (string) $candidate['material_code'],
                            (string) $candidate['material_name'],
                            (string) ($candidate['category_name'] ?? ''),
                            (string) ($candidate['specifications'] ?? ''),
                            $parentText,
                        ]), 'UTF-8');
                        ?>
                        <tr data-list-item data-search="<?= $escape($searchText) ?>">
                            <td class="closeout-col-check">
                                <input type="checkbox" name="material_ids[]" value="<?= (int) $candidate['material_id'] ?>"
                                    data-closeout-material
                                    <?= !empty($candidate['selectable']) ? '' : 'disabled' ?>
                                    aria-label="选择 <?= $escape($candidate['material_code']) ?>">
                            </td>
                            <td>
                                <span class="closeout-item-no"><?= $escape((string) $candidate['material_code']) ?></span>
                                <span class="closeout-item-name"><?= $escape((string) $candidate['material_name']) ?></span>
                            </td>
                            <td><?= $escape($candidate['category_name'] ?? '—') ?></td>
                            <td><?= $escape(trim(($candidate['specifications'] ?? '') . ' / ' . ($candidate['unit'] ?? ''), ' /')) ?: '—' ?></td>
                            <td>
                                <span class="closeout-item-meta"><?= $escape($parentText) ?></span>
                                <?php if ((int) $candidate['children_count'] > 0): ?>
                                    <span class="closeout-item-meta">含 <?= (int) $candidate['children_count'] ?> 个下级材料</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($candidate['selectable'])): ?>
                                    <span class="status-badge is-green">可纳入</span>
                                <?php else: ?>
                                    <span class="status-badge is-amber"><?= $escape((string) ($candidate['blocked_reason'] ?? '不可纳入')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="empty-state" data-list-empty hidden>没有匹配的材料。</p>
        </div>

        <div class="closeout-select-footer">
            <span class="muted">可纳入 <?= $selectableCount ?> / <?= $candidateCount ?> 项；已纳入事项的材料会自动禁用。</span>
            <button class="button button-primary" type="submit">纳入所选</button>
        </div>
        <?php endif; ?>
    </div>
</form>

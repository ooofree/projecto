<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;

$selectableCount = 0;
foreach ($candidates as $candidate) {
    if (!empty($candidate['selectable'])) {
        $selectableCount++;
    }
}
$hasParentMaterials = (bool) ($hasParentMaterials ?? true);
$selectionScopeText = $hasParentMaterials
    ? '消项清单只纳入有下级组成的父材料（组合产品），子材料和无下级的独立材料都不列出'
    : '当前项目 BOM 中没有父材料，全部顶层材料均可纳入；出现父材料后将只允许纳入父材料';
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工管理 · 工程消项</p>
        <h1>从 BOM 纳入消项事项</h1>
        <p class="muted">
            计划：<?= $escape($plan['plan_no'] . ' · ' . $plan['plan_name']) ?>。
            只读取 BOM 材料与组成关系，不核销数量、不回写 BOM 状态。<?= $escape($selectionScopeText) ?>；同一材料只能有一条有效事项。
        </p>
    </div>
    <div class="project-list-actions">
        <a class="button button-secondary" href="?page=closeout-items&amp;plan_id=<?= (int) $plan['plan_id'] ?>">返回清单</a>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>BOM 材料</h2>
        <span class="muted">
            可选 <?= $selectableCount ?> 项 / 共 <?= count($candidates) + (int) ($hiddenMaterialCount ?? 0) ?> 项 ·
            <span data-list-count data-unit=" 项"><?= $selectableCount ?> 项</span>
            <?php if (($hiddenMaterialCount ?? 0) > 0): ?>· 已隐藏 <?= (int) $hiddenMaterialCount ?> 项非父材料<?php endif; ?>
        </span>
    </div>
    <div class="panel-body">
        <label>筛选材料<input type="search" data-list-search="closeout-material-list" placeholder="材料编号、名称、规格或分类" autocomplete="off"></label>
    </div>
</section>

<form method="post" data-closeout-select-page>
    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="action" value="closeout.items.create">
    <input type="hidden" name="plan_id" value="<?= (int) $plan['plan_id'] ?>">

    <section class="panel">
        <div class="panel-heading">
            <h2>纳入口径</h2>
            <button class="button button-quiet" type="button" data-closeout-select-all>全选可选材料</button>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <label>计划开始日期<input type="date" name="planned_start_date"></label>
                <label>计划完成日期<input type="date" name="planned_end_date"></label>
                <label>责任单位
                    <select name="responsible_supplier_id">
                        <option value="">暂不指定</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int) $supplier['supplier_id'] ?>"><?= $escape($supplier['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>责任人
                    <select name="responsible_user_id">
                        <option value="">暂不指定</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['user_id'] ?>"><?= $escape($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>风险等级
                    <select name="risk_level">
                        <?php foreach (CloseoutLabels::RISK_LEVEL as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $value === 'medium' ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <input type="hidden" name="coverage_mode" value="whole">
            </div>
            <p class="muted"><?= $escape($selectionScopeText) ?>。<?= $hasParentMaterials ? '纳入父材料即视为其下级组成一并收口；子材料不单独纳入。' : '纳入顶层材料即视为该材料自身收口。' ?></p>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>材料清单</h2>
            <span class="muted"><?= $hasParentMaterials ? '仅父材料可纳入；已纳入的父材料不可重复选择' : '暂无父材料，全部顶层材料可选；已纳入材料不可重复选择' ?></span>
        </div>
        <div class="table-wrap">
            <?php if ($candidates === []): ?>
                <p class="empty-state">当前项目 BOM 中没有有下级组成的父材料，暂无可纳入的消项事项。请先在材料管理中维护组合产品的组成关系。</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>选择</th>
                        <th>材料编号</th>
                        <th>材料名称 / 规格</th>
                        <th>分类</th>
                        <th>上级材料</th>
                        <th>下级数量</th>
                        <th>可用性</th>
                    </tr>
                </thead>
                <tbody id="closeout-material-list">
                <?php foreach ($candidates as $candidate): ?>
                    <?php
                    $search = mb_strtolower(
                        implode(' ', [
                            (string) ($candidate['material_code'] ?? ''),
                            (string) $candidate['material_name'],
                            (string) ($candidate['specifications'] ?? ''),
                            (string) ($candidate['category_name'] ?? ''),
                        ]),
                        'UTF-8'
                    );
                    ?>
                    <tr data-list-item
                        data-search="<?= $escape($search) ?>"
                        data-availability="<?= !empty($candidate['selectable']) ? 'ok' : 'blocked' ?>">
                        <td>
                            <?php if (!empty($candidate['selectable'])): ?>
                                <input type="checkbox" name="material_ids[]" value="<?= (int) $candidate['material_id'] ?>"
                                       data-closeout-select-item aria-label="选择 <?= $escape($candidate['material_name']) ?>">
                            <?php else: ?>
                                <input type="checkbox" disabled
                                       aria-label="<?= $escape($candidate['material_name']) ?>不可纳入">
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($candidate['material_code'] ?? '—') ?></td>
                        <td>
                            <?= $escape($candidate['material_name']) ?>
                            <span class="muted"><?= $escape($candidate['specifications'] ?? '') ?></span>
                        </td>
                        <td><?= $escape($candidate['category_name'] ?? '—') ?></td>
                        <td class="muted"><?= $candidate['parents'] === [] ? '—' : $escape(implode('、', $candidate['parents'])) ?></td>
                        <td><?= (int) $candidate['children_count'] ?></td>
                        <td>
                            <?php if (empty($candidate['selectable'])): ?>
                                <span class="status-badge is-neutral"><?= $escape($candidate['blocked_reason'] ?? '不可纳入') ?></span>
                            <?php else: ?>
                                <span class="status-badge is-green">可纳入</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="empty-state" data-list-empty hidden>没有匹配的材料。</p>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <div class="dialog-actions">
                <button class="button button-primary" type="submit">纳入所选材料</button>
                <span class="muted">任一材料冲突时整批回滚，不会产生半批数据。</span>
            </div>
        </div>
    </section>
</form>

<?php $pageScripts = [['module' => 'closeout.management', 'file' => 'closeout.js', 'version' => (string) filemtime(dirname(__DIR__) . '/public/closeout.js')]]; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

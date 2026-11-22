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
                    <span class="muted">用量系数＝每 1 <?= $escape((string) $costing['unit']) ?> 父项直接消耗的子材料量；改数量右栏立刻试算，点底部“保存全部改动”一次写库并重算（增删仍写回材料管理的组成）</span>
                </div>
                <div class="cost-editor-head-actions">
                    <span class="muted"><?= count($compositionRows) ?> 行</span>
                    <?php if ($canEdit && $compositionCandidates !== []): ?>
                        <button class="button button-secondary button-small" type="button" data-dialog-open="comp-add-dialog">添加子材料</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($compositionRows === []): ?>
                <p class="empty-state">该父材料还没有子材料。用上面的“添加子材料”补一条，或到材料管理维护组成关系。</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table cost-editor-table cost-comp-table">
                        <thead>
                            <tr>
                                <th>子材料</th>
                                <th>单位</th>
                                <th class="cost-num">用量系数</th>
                                <th>单价 / 取价</th>
                                <?php if ($canEdit): ?><th class="cost-actions">操作</th><?php endif; ?>
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
                                <?php if ($canEdit): ?>
                                    <?php /* 行内改数字＝即时试算（右栏立刻重算）；改动统一由下方“保存全部改动”一次写库。 */ ?>
                                    <td class="cost-qty-cell">
                                        <input class="cost-qty-input" type="number"
                                               name="comp_qty[<?= (int) $row['composition_id'] ?>]"
                                               form="cost-qty-bulk"
                                               min="0.01" max="99999999" step="0.01"
                                               value="<?= $escape(cost_format_qty($row['quantity'])) ?>" required
                                               aria-label="用量系数"
                                               data-cost-input
                                               data-cost-key="c<?= (int) $row['composition_id'] ?>"
                                               data-cost-saved="<?= $escape(cost_format_qty($row['quantity'])) ?>"
                                               data-dialog-target="comp-edit-<?= (int) $row['composition_id'] ?>">
                                    </td>
                                <?php else: ?>
                                    <td class="cost-num"><?= $escape(cost_format_qty($row['quantity'])) ?></td>
                                <?php endif; ?>
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
                                <?php if ($canEdit): ?>
                                    <td class="cost-actions">
                                        <button class="button button-quiet button-small" type="button" data-dialog-open="comp-edit-<?= (int) $row['composition_id'] ?>">编辑</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($canEdit && $compositionCandidates === []): ?>
                <p class="muted cost-note">暂无可添加的子材料：候选必须是本项目里还没有自己子项的材料，且不能已经在这份组成里。要加新的子材料，先到<a href="?page=materials">材料管理</a>建好材料再回来。</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>资源行</h2>
                    <span class="muted">加工／机械／人工／辅材，按每 1 <?= $escape((string) $costing['unit']) ?> 父项计；改数量同样走底部“保存全部改动”</span>
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
                                <?php if ($canEdit): ?>
                                    <?php /* 与组成行一致：改数量即时试算，保存动作统一走“保存全部改动”。 */ ?>
                                    <td class="cost-qty-cell">
                                        <input class="cost-qty-input" type="number"
                                               name="res_qty[<?= (int) $row['resource_line_id'] ?>]"
                                               form="cost-qty-bulk"
                                               min="0.000001" step="0.000001"
                                               value="<?= $escape(cost_format_qty($row['quantity'])) ?>" required
                                               aria-label="数量"
                                               data-cost-input
                                               data-cost-key="r<?= (int) $row['resource_line_id'] ?>"
                                               data-cost-saved="<?= $escape(cost_format_qty($row['quantity'])) ?>">
                                    </td>
                                <?php else: ?>
                                    <td class="cost-num">
                                        <?= $escape(cost_format_qty($row['quantity'])) ?>
                                        <span class="muted cost-unit"><?= $escape((string) ($row['unit'] ?? '—')) ?></span>
                                    </td>
                                <?php endif; ?>
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
                <?php
                $catalogCounts = ['processing' => 0, 'mechanical' => 0, 'labor' => 0];
                foreach ($catalogOptions as $catType => $items) {
                    $catalogCounts[$catType] = count($items);
                }
                $auxCount = count($auxiliaries);
                ?>
                <form method="post" class="form-grid cost-editor-add" data-resource-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="costing.resource.save">
                    <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                    <label>类型
                        <select name="resource_type" required data-resource-type>
                            <option value="">请选择</option>
                            <option value="processing"<?= $catalogCounts['processing'] === 0 ? ' disabled title="资源目录里还没有加工项"' : '' ?>>加工</option>
                            <option value="mechanical"<?= $catalogCounts['mechanical'] === 0 ? ' disabled title="资源目录里还没有机械项"' : '' ?>>机械</option>
                            <option value="labor"<?= $catalogCounts['labor'] === 0 ? ' disabled title="资源目录里还没有人工项"' : '' ?>>人工</option>
                            <option value="auxiliary"<?= $auxCount === 0 ? ' disabled title="辅材目录里还没有辅材"' : '' ?>>辅材</option>
                        </select>
                    </label>
                    <label data-target="catalog" hidden>目录项
                        <select name="catalog_id" disabled>
                            <option value="">请选择目录项</option>
                            <?php foreach ($catalogOptions as $catType => $items): ?>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= (int) $item['catalog_id'] ?>" data-catalog-type="<?= $escape((string) $catType) ?>"><?= $escape((string) $item['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label data-target="aux" hidden>辅材
                        <select name="aux_id" disabled>
                            <option value="">请选择辅材</option>
                            <?php foreach ($auxiliaries as $aux): ?>
                                <option value="<?= (int) $aux['aux_id'] ?>"><?= $escape($aux['aux_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>数量<input type="number" name="quantity" min="0" step="0.000001" value="1" required></label>
                    <div class="field-wide">
                        <button class="button button-primary" type="submit">添加资源行</button>
                    </div>
                    <p class="muted cost-editor-hint field-wide">
                        目录现有：加工 <?= $catalogCounts['processing'] ?> 项 · 机械 <?= $catalogCounts['mechanical'] ?> 项 · 人工 <?= $catalogCounts['labor'] ?> 项 · 辅材 <?= $auxCount ?> 项。
                        <?php if (array_sum($catalogCounts) === 0 || $auxCount === 0): ?>
                            为 0 的类型加不了行，先补目录：<a href="?page=cost-catalog">资源目录</a>（加工／机械／人工）、<a href="?page=auxiliary-materials">辅材目录</a>。
                        <?php endif; ?>
                    </p>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($canEdit): ?>
            <?php /* 一键保存：表单在这里，输入框用 form="cost-qty-bulk" 跨单元格挂上来（表格里没法套表单）。 */ ?>
            <form method="post" class="cost-bulk-bar" id="cost-qty-bulk" data-cost-bulk hidden
                  data-cost-label="costing-detail&costing_id=<?= (int) $costing['costing_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="costing.quantities.save">
                <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                <div class="cost-bulk-text">
                    <strong data-cost-bulk-count>0</strong> 处数量改动未保存
                    <span class="muted">一次写库并重算，版本只 +1</span>
                </div>
                <div class="cost-bulk-actions">
                    <button class="button button-quiet button-small" type="button" data-cost-bulk-reset>撤销改动</button>
                    <?php if ((string) ($costing['status'] ?? '') === 'draft'): ?>
                        <button class="button button-secondary" type="submit" name="next" value="confirm">保存并确认</button>
                    <?php endif; ?>
                    <button class="button button-primary" type="submit">保存全部改动</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <aside class="panel cost-editor-preview cost-preview-panel">
        <div class="panel-heading">
            <div>
                <h2>成本预览</h2>
                <span class="muted">成本项小计占比；管理费／利润／税等费率未定案，暂不计入</span>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <p class="cost-preview-status" id="cost-preview-status" data-state="synced" role="status">实时试算：改左侧数量，这里立刻重算；改动由“保存全部改动”一次写库</p>
        <?php endif; ?>
        <div id="cost-preview-live" class="cost-preview-live"
             data-costing-id="<?= (int) $costing['costing_id'] ?>"
             data-endpoint="<?= $escape('?page=costing-detail&costing_id=' . (int) $costing['costing_id']) ?>">
            <?= $previewHtml ?>
        </div>
    </aside>
</div>

<?php if ($canEdit): ?>
    <?php /* 组成维护对话框：新增一条 / 逐行改用量系数。写的是材料管理里该父材料的组成，不是组价副本。 */ ?>
    <dialog class="edit-dialog" id="comp-add-dialog">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="costing.composition.save">
            <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
            <div class="dialog-heading">
                <h3>添加子材料</h3>
                <button class="button button-quiet button-small" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <p class="muted">要加入的是「<?= $escape((string) $costing['material_name']) ?>」的组成子材料，保存后写回材料管理，本组价自动重算。</p>
            <?php if ($compositionCandidates === []): ?>
                <p class="muted">暂无可添加的子材料：已有自己子项的材料不能再当子件（单层组成），已在本组成里的也不会重复出现。</p>
            <?php else: ?>
                <div class="form-grid">
                    <label class="field-wide">子材料
                        <select name="child_material_id" required>
                            <option value="">请选择子材料</option>
                            <?php foreach ($compositionCandidates as $candidate): ?>
                                <option value="<?= (int) $candidate['material_id'] ?>"><?= $escape(trim(((string) ($candidate['material_code'] ?? '')) . ' · ' . (string) $candidate['material_name'], ' ·')) ?>（<?= $escape((string) ($candidate['unit'] ?? '')) ?>）</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>用量系数
                        <input type="number" name="quantity" min="0.01" step="0.01" value="1.00" required>
                    </label>
                </div>
                <div class="dialog-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                    <button class="button button-primary" type="submit">保存子材料</button>
                </div>
            <?php endif; ?>
        </form>
    </dialog>

    <?php foreach ($compositionRows as $index => $row): ?>
        <dialog class="edit-dialog" id="comp-edit-<?= (int) $row['composition_id'] ?>">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="costing.composition.save">
                <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                <input type="hidden" name="composition_id" value="<?= (int) $row['composition_id'] ?>">
                <input type="hidden" name="child_material_id" value="<?= (int) ($row['child_material_id'] ?? 0) ?>">
                <div class="dialog-heading">
                    <h3>编辑子材料</h3>
                    <button class="button button-quiet button-small" type="button" data-dialog-close aria-label="关闭">关闭</button>
                </div>
                <p class="muted">子材料：<?= $escape((string) ($row['object_name'] ?? '—')) ?><?php if (($row['object_code'] ?? '') !== ''): ?>（<?= $escape((string) $row['object_code']) ?>）<?php endif; ?></p>
                <div class="form-grid">
                    <label>用量系数
                        <input type="number" name="quantity" min="0.01" step="0.01" value="<?= $escape(cost_format_qty($row['quantity'])) ?>" required>
                    </label>
                </div>
                <p class="muted">用量系数＝每 1 <?= $escape((string) $costing['unit']) ?> 父项消耗的该子材料量（单位 <?= $escape((string) ($row['unit'] ?? '—')) ?>）。</p>
                <div class="dialog-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                    <button class="button button-primary" type="submit">保存系数</button>
                </div>
            </form>
            <?php /* 删除单独一个表单：HTML 不允许表单嵌套。 */ ?>
            <form method="post" class="cost-dialog-danger" data-confirm="从父材料组成里移除「<?= $escape((string) ($row['object_name'] ?? '')) ?>」？材料管理与本组价都会按剩余组成重算。">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="costing.composition.delete">
                <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                <input type="hidden" name="composition_id" value="<?= (int) $row['composition_id'] ?>">
                <button class="button button-quiet button-small" type="submit">移除该子材料</button>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

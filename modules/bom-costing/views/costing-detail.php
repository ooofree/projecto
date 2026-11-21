<?php
ob_start();
require __DIR__ . '/_nav.php';

$statusLabels = ['draft' => '草稿', 'confirmed' => '已确认'];
$calcStatusLabels = ['incomplete' => '信息不全', 'calculable' => '可计算', 'complete' => '已齐备'];
$resourceTypeLabels = [
    'material' => '材料', 'auxiliary' => '辅材', 'processing' => '加工',
    'mechanical' => '机械', 'labor' => '人工',
];
$taxLabels = ['taxable' => '含税/应税', 'exempt' => '免税', 'unknown' => '未知'];
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

<section class="panel">
    <div class="panel-heading"><h2>当前态明细</h2><span class="muted">进入详情已按当前有效输入补算一次；缺项不落零价，标注原因待补。</span></div>
    <?php if (($costing['detail_rows'] ?? []) === []): ?>
        <p class="empty-state">暂无明细。先在下方核对组成关系，再添加加工／机械／人工资源行。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>类型</th><th>对象</th><th>编码</th><th>单位</th><th>数量/系数</th><th>单价</th><th>币种</th><th>计价单位</th><th>税口径</th><th>来源</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach ($costing['detail_rows'] as $row): ?>
                    <?php $kindLabel = str_starts_with((string) $row['kind'], 'resource:')
                        ? ($resourceTypeLabels[substr((string) $row['kind'], 9)] ?? $row['kind'])
                        : '材料组成'; ?>
                    <tr>
                        <td><?= $escape($kindLabel) ?></td>
                        <td><?= $escape($row['object_name'] ?? '—') ?></td>
                        <td><?= $escape($row['object_code'] ?? '—') ?></td>
                        <td><?= $escape($row['unit'] ?? '—') ?></td>
                        <td><?= $escape((string) $row['quantity']) ?></td>
                        <td><?= $row['unit_price'] !== null ? $escape((string) $row['unit_price']) : '—' ?></td>
                        <td><?= $escape($row['currency_code'] ?? '—') ?></td>
                        <td><?= $escape($row['pricing_unit'] ?? '—') ?></td>
                        <td><?= $escape($taxLabels[$row['tax_status']] ?? $row['tax_status']) ?></td>
                        <td><?= $escape($row['source']) ?></td>
                        <td>
                            <?php if ($row['status'] === 'priced'): ?>
                                <span class="badge">已计价</span>
                            <?php else: ?>
                                <span class="badge">缺价</span>
                                <span class="muted"><?= $escape($row['reason'] ?? '') ?></span>
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
    <div class="panel-heading"><h2>组成关系（实时引用，不可在组价内改）</h2></div>
    <?php if ($costing['compositions'] === []): ?>
        <p class="empty-state">该父材料尚未配置子材料组成。请到材料管理维护组成关系。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>子材料</th><th>编码</th><th>单位</th><th>用量系数</th></tr></thead>
                <tbody>
                <?php foreach ($costing['compositions'] as $comp): ?>
                    <tr>
                        <td><?= $escape($comp['material_name']) ?></td>
                        <td><?= $escape($comp['material_code'] ?? '—') ?></td>
                        <td><?= $escape($comp['unit']) ?></td>
                        <td><?= $escape((string) $comp['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>资源行（加工／机械／人工／辅材）</h2><span class="muted"><?= count($costing['resources']) ?> 行</span></div>
    <?php if ($costing['resources'] !== []): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>类型</th><th>目录项／对象</th><th>数量</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($costing['resources'] as $resource): ?>
                    <tr>
                        <td><?= $escape($resourceTypeLabels[$resource['resource_type']] ?? $resource['resource_type']) ?></td>
                        <td><?= $escape($resource['catalog_name'] ?? $resource['aux_name'] ?? $resource['material_name'] ?? '—') ?></td>
                        <td><?= $escape((string) $resource['quantity']) ?></td>
                        <td>
                            <?php if ($canEdit): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="costing.resource.delete">
                                    <input type="hidden" name="costing_id" value="<?= (int) $costing['costing_id'] ?>">
                                    <input type="hidden" name="resource_line_id" value="<?= (int) $resource['resource_line_id'] ?>">
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
        <form method="post" class="form-grid">
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
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

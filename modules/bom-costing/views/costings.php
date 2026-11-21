<?php
ob_start();
require __DIR__ . '/_nav.php';

$statusLabels = ['draft' => '草稿', 'confirmed' => '已确认'];
$calcStatusLabels = ['incomplete' => '信息不全', 'calculable' => '可计算', 'complete' => '已齐备'];
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">组价清单</p>
        <h1>BOM 组价</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。每个父材料对应一份组价；材料组成关系在材料管理中维护，组价实时引用，不存组件副本。</p>
    </div>
</section>

<?php if ($canEdit && $availableMaterials !== []): ?>
<section class="panel">
    <div class="panel-heading"><h2>新建组价</h2></div>
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
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><h2>组价清单</h2><span class="muted"><?= count($costings) ?> 份</span></div>
    <?php if ($costings === []): ?>
        <p class="empty-state">当前项目还没有组价。<?= $canEdit && $availableMaterials !== [] ? '在上方选择一个父材料建立组价。' : '请先在材料管理中维护材料与组成关系。' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>父材料</th><th>状态</th><th>齐备度</th><th>缺项</th><th>版本</th><th>更新时间</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($costings as $costing): ?>
                    <tr>
                        <td><strong><?= $escape($costing['material_name']) ?></strong></td>
                        <td><span class="badge"><?= $escape($statusLabels[$costing['status']] ?? $costing['status']) ?></span></td>
                        <td><span class="badge"><?= $escape($calcStatusLabels[$costing['calc_status']] ?? $costing['calc_status']) ?></span></td>
                        <td><?= (int) $costing['missing_count'] > 0 ? $escape((string) $costing['missing_count']) . ' 项缺价' : '—' ?></td>
                        <td>v<?= (int) $costing['version'] ?></td>
                        <td><?= $escape(\ProjectO\Support::formatDateTime($costing['updated_at'])) ?></td>
                        <td><a class="button button-secondary" href="?page=costing-detail&costing_id=<?= (int) $costing['costing_id'] ?>">打开</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

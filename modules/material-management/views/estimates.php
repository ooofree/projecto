<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading"><div><p class="eyebrow">项目估算 · 材料数量</p><h1>材料估算明细</h1><p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。本期仅记录空间、材料、估算数量和备注，不计算金额、税费、BOM 或汇总成本。</p></div></section>
<?php if ($canEdit && ($choices['buildings'] === [] || $choices['materials'] === [])): ?><div class="notice notice-info">创建估算明细前，请先建立项目楼栋和全局材料目录。</div><?php endif; ?>
<?php if ($canEdit && $choices['buildings'] !== [] && $choices['materials'] !== []): ?>
    <section class="panel">
        <div class="panel-heading"><h2>材料估算台账</h2><span class="muted"><?= count($estimates) ?> 条</span></div>
        <details class="entry-disclosure">
            <summary>新增估算明细</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.estimate.save">
                <label>楼栋<select name="building_id" required><?php foreach ($choices['buildings'] as $building): ?><option value="<?= (int) $building['building_id'] ?>"><?= $escape($building['building_number'] . ' ' . $building['building_name']) ?></option><?php endforeach; ?></select></label>
                <label>楼层（可选）<select name="floor_id"><option value="">不指定</option><?php foreach ($choices['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>"><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                <label>房间（可选）<select name="room_id"><option value="">不指定</option><?php foreach ($choices['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>"><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                <label>材料<select name="material_id" required><?php foreach ($choices['materials'] as $material): ?><option value="<?= (int) $material['material_id'] ?>"><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ' (' . $material['unit'] . ')') ?></option><?php endforeach; ?></select></label>
                <label>估算数量<input type="number" name="estimated_quantity" min="0" step="0.0001" value="0" required></label>
                <label class="field-wide">备注<textarea name="remarks" rows="2" maxlength="20000"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存明细</button></div>
            </form>
        </details>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><h2>估算记录</h2><span class="muted">最多显示最近 500 条</span></div>
    <?php if ($estimates === []): ?><p class="empty-state">当前项目尚无材料估算明细。</p><?php endif; ?>
    <?php foreach ($estimates as $estimate): ?>
        <details class="record-card">
            <summary><strong><?= $escape($estimate['material_name']) ?></strong> <span class="muted"><?= $escape($estimate['building_number'] . ' · ' . ($estimate['floor_number'] ?? '未指定楼层') . ' · ' . ($estimate['room_number'] ?? '未指定房间')) ?> · <?= $escape($estimate['estimated_quantity'] . ' ' . $estimate['unit']) ?></span></summary>
            <?php if ($canEdit): ?>
                <form method="post" class="form-grid record-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.estimate.save"><input type="hidden" name="estimate_detail_id" value="<?= (int) $estimate['estimate_detail_id'] ?>">
                    <label>楼栋<select name="building_id" required><?php foreach ($choices['buildings'] as $building): ?><option value="<?= (int) $building['building_id'] ?>" <?= (int) $building['building_id'] === (int) $estimate['building_id'] ? 'selected' : '' ?>><?= $escape($building['building_number'] . ' ' . $building['building_name']) ?></option><?php endforeach; ?></select></label>
                    <label>楼层<select name="floor_id"><option value="">不指定</option><?php foreach ($choices['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>" <?= (int) $floor['floor_id'] === (int) $estimate['floor_id'] ? 'selected' : '' ?>><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                    <label>房间<select name="room_id"><option value="">不指定</option><?php foreach ($choices['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>" <?= (int) $room['room_id'] === (int) $estimate['room_id'] ? 'selected' : '' ?>><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                    <label>材料<select name="material_id" required><?php foreach ($choices['materials'] as $material): ?><option value="<?= (int) $material['material_id'] ?>" <?= (int) $material['material_id'] === (int) $estimate['material_id'] ? 'selected' : '' ?>><?= $escape($material['material_name'] . ' (' . $material['unit'] . ')') ?></option><?php endforeach; ?></select></label>
                    <label>估算数量<input type="number" name="estimated_quantity" min="0" step="0.0001" value="<?= $escape($estimate['estimated_quantity']) ?>" required></label>
                    <label class="field-wide">备注<textarea name="remarks" rows="2" maxlength="20000"><?= $escape($estimate['remarks'] ?? '') ?></textarea></label>
                    <div class="field-wide"><button class="button button-secondary" type="submit">保存明细</button></div>
                </form>
                <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.estimate.delete"><input type="hidden" name="estimate_detail_id" value="<?= (int) $estimate['estimate_detail_id'] ?>"><button class="button button-quiet" type="submit">删除明细</button></form>
            <?php else: ?><p class="record-meta">备注：<?= $escape($estimate['remarks'] ?? '—') ?></p><?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

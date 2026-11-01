<?php
$documentLabels = ['pending_submission' => '待提交', 'submitted' => '已提交', 'reviewed' => '已审核'];
$sentLabels = ['not_sent' => '未送', 'sent' => '已送', 'partially_sent' => '部分送样'];
$approvalLabels = ['pending' => '待审批', 'approved' => '已通过', 'rejected' => '已拒绝', '已审批通过' => '已通过'];
$locationOptions = [];
foreach ($locations['buildings'] as $location) {
    $locationOptions[] = ['value' => 'building:' . $location['building_id'], 'label' => $location['building_number'] . ' ' . $location['building_name']];
}
foreach ($locations['floors'] as $location) {
    $locationOptions[] = ['value' => 'floor:' . $location['floor_id'], 'label' => $location['building_number'] . ' ' . $location['floor_number'] . ' ' . ($location['floor_name'] ?? '')];
}
foreach ($locations['areas'] as $location) {
    $locationOptions[] = ['value' => 'area:' . $location['area_id'], 'label' => $location['building_number'] . ' ' . $location['area_name']];
}
foreach ($locations['rooms'] as $location) {
    $locationOptions[] = ['value' => 'room:' . $location['room_id'], 'label' => $location['building_number'] . ' ' . $location['floor_number'] . ' ' . $location['room_number']];
}
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading"><div><p class="eyebrow">项目跟踪 · 材料样品</p><h1>样品跟踪</h1><p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。样品只关联当前项目空间，不关联材料估算明细。</p></div></section>
<?php if (!$imageStorageReady): ?><div class="notice notice-info">R2 图片存储尚未配置；样品资料可维护，但图片上传和查看暂不可用。</div><?php endif; ?>

<?php if ($canCreate && $quotations !== [] && $locationOptions !== []): ?>
    <section class="panel">
        <div class="panel-heading"><div><h2>登记样品</h2><p class="muted">先在项目空间建立楼栋、楼层或区域，再关联样品。</p></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.sample.save">
            <label>材料报价<select name="quotation_id" required><?php foreach ($quotations as $quote): ?><option value="<?= (int) $quote['quotation_id'] ?>"><?= $escape(($quote['material_code'] ? $quote['material_code'] . ' · ' : '') . $quote['material_name'] . ' · ' . $quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code']) ?></option><?php endforeach; ?></select></label>
            <label>项目位置<select name="location" required><option value="">请选择当前项目位置</option><?php foreach ($locationOptions as $location): ?><option value="<?= $escape($location['value']) ?>"><?= $escape($location['label']) ?></option><?php endforeach; ?></select></label>
            <label>送样数量<input type="number" name="sample_quantity" min="0" step="1" value="0" required></label><label>颜色<input name="color" maxlength="50"></label>
            <label>资料状态<select name="document_status"><option value="pending_submission">待提交</option><option value="submitted">已提交</option><option value="reviewed">已审核</option></select></label>
            <label>送样状态<select name="sent_status"><option value="not_sent">未送</option><option value="sent">已送</option><option value="partially_sent">部分送样</option></select></label>
            <label>送样日期<input type="date" name="sent_date"></label><label>业主审批状态<select name="owner_approval_status"><option value="pending">待审批</option><option value="approved">已通过</option><option value="rejected">已拒绝</option></select></label>
            <label>业主审批日期<input type="date" name="owner_approval_date"></label><label class="field-wide">备注<textarea name="remarks" rows="2" maxlength="20000"></textarea></label>
            <div class="field-wide"><button class="button button-primary" type="submit">登记样品</button></div>
        </form>
    </section>
<?php elseif ($canCreate): ?>
    <div class="notice notice-info">登记样品前，需先建立至少一个项目空间位置和一个材料报价。</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><h2>当前项目样品</h2><span class="muted"><?= count($samples) ?> 条（最多显示最近 200 条）</span></div>
    <?php if ($samples === []): ?><p class="empty-state">当前项目尚无未归档样品。</p><?php endif; ?>
    <?php foreach ($samples as $sample): ?>
        <details class="record-card" open>
            <summary><strong><?= $escape($sample['material_name']) ?></strong> <span class="muted"><?= $escape($sample['supplier_name']) ?> · <?= $escape($sample['location_name']) ?> · <?= $escape($approvalLabels[$sample['owner_approval_status']] ?? $sample['owner_approval_status']) ?></span></summary>
            <p class="record-meta">资料：<?= $escape($documentLabels[$sample['document_status']] ?? $sample['document_status']) ?> · 送样：<?= $escape($sentLabels[$sample['sent_status']] ?? $sample['sent_status']) ?> · 数量：<?= (int) $sample['sample_quantity'] ?> · <?= $escape($sample['color'] ?? '未指定颜色') ?></p>
            <?php if ($sample['remarks']): ?><p><?= nl2br($escape($sample['remarks'])) ?></p><?php endif; ?>
            <?php if ($sample['images'] !== []): ?>
                <div class="record-meta">图片：
                    <?php foreach ($sample['images'] as $image): ?>
                        <?php if (isset($image['url'])): ?><a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($image['original_name']) ?></a><?php else: ?><span><?= $escape($image['original_name']) ?>（R2 未配置）</span><?php endif; ?>
                        <span class="muted">(<?= number_format((int) $image['file_size'] / 1024, 1) ?> KB)</span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <form method="post" class="form-grid record-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.sample.save"><input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                    <label>材料报价<select name="quotation_id" required><?php foreach ($quotations as $quote): ?><option value="<?= (int) $quote['quotation_id'] ?>" <?= (int) $quote['quotation_id'] === (int) $sample['quotation_id'] ? 'selected' : '' ?>><?= $escape($quote['material_name'] . ' · ' . $quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code']) ?></option><?php endforeach; ?></select></label>
                    <label>项目位置<select name="location" required><?php foreach ($locationOptions as $location): ?><option value="<?= $escape($location['value']) ?>" <?= $location['value'] === $sample['location_type'] . ':' . $sample['location_id'] ? 'selected' : '' ?>><?= $escape($location['label']) ?></option><?php endforeach; ?></select></label>
                    <label>送样数量<input type="number" name="sample_quantity" min="0" step="1" value="<?= (int) $sample['sample_quantity'] ?>" required></label><label>颜色<input name="color" maxlength="50" value="<?= $escape($sample['color'] ?? '') ?>"></label>
                    <label>资料状态<select name="document_status"><?php foreach ($documentLabels as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $sample['document_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
                    <label>送样状态<select name="sent_status"><?php foreach ($sentLabels as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $sample['sent_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
                    <label>送样日期<input type="date" name="sent_date" value="<?= $escape($sample['sent_date'] ?? '') ?>"></label><label>业主审批状态<select name="owner_approval_status"><?php foreach (['pending' => '待审批', 'approved' => '已通过', 'rejected' => '已拒绝'] as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $sample['owner_approval_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
                    <label>业主审批日期<input type="date" name="owner_approval_date" value="<?= $escape($sample['owner_approval_date'] ?? '') ?>"></label><label class="field-wide">备注<textarea name="remarks" rows="2" maxlength="20000"><?= $escape($sample['remarks'] ?? '') ?></textarea></label>
                    <div class="field-wide"><button class="button button-secondary" type="submit">保存跟踪状态</button></div>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && $imageStorageReady): ?>
                <form method="post" enctype="multipart/form-data" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.sample.image.upload"><input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                    <label>上传图片<input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/bmp,image/webp" required></label><button class="button button-secondary" type="submit">上传到 R2</button><span class="muted">PNG/JPEG/GIF/BMP/WebP，最大 5 MB</span>
                </form>
            <?php endif; ?>
            <?php if ($canArchive): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.sample.archive"><input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                    <button class="button button-quiet" type="submit">归档样品（保留记录和图片）</button>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
$documentLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::DOCUMENT;
$sentLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::SENT;
$approvalLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::APPROVAL;
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
$canCreateSample = $canCreate && $choices['materials'] !== [] && $choices['suppliers'] !== [] && $locationOptions !== [];
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目跟踪 · 材料样品</p>
        <h1>样品跟踪</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。技术样品审批独立于商业报价；报价可后续补充。</p>
    </div>
    <?php if ($canCreateSample): ?>
        <button class="button button-primary" type="button" data-dialog-open="sample-create">新增样品</button>
    <?php endif; ?>
</section>
<?php if (!$imageStorageReady): ?>
    <?php if (is_array($storageStatus ?? null) && ($storageStatus['sdk_available'] ?? false) === false): ?>
        <div class="notice notice-info">服务器尚未安装 AWS SDK（composer require aws/aws-sdk-php）；R2 图片上传和查看暂不可用，样品资料仍可维护。</div>
    <?php else: ?>
        <div class="notice notice-info">R2 图片存储尚未配置；样品资料可维护，但图片上传和查看暂不可用。<?= !empty($canManageSettings) ? '图片存储由文件模块“系统设置”统一配置，<a href="?page=document-settings">前往配置</a>。' : '' ?></div>
    <?php endif; ?>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>样品列表</h2>
            <p class="muted">列出当前项目未归档的样品；点击“编辑”在弹窗中维护资料、送样和审批状态。</p>
        </div>
        <span class="muted"><?= count($samples) ?> 条<?= count($samples) >= 200 ? '（最多显示最近 200 条；导出包含全部）' : '' ?></span>
    </div>
    <div class="toolbar">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.sample.export">
            <button class="button button-secondary" type="submit">导出 Excel</button>
        </form>
    </div>

    <?php if ($samples === []): ?>
        <p class="report-empty">当前项目尚无未归档样品。<?= $canCreateSample ? '点击右上角“新增样品”开始登记。' : '' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="report-table" id="samples-table">
                <thead>
                <tr>
                    <th scope="col">材料</th>
                    <th scope="col">供应商</th>
                    <th scope="col">项目位置</th>
                    <th scope="col">送样数量</th>
                    <th scope="col">颜色</th>
                    <th scope="col">资料状态</th>
                    <th scope="col">送样状态</th>
                    <th scope="col">送样日期</th>
                    <th scope="col">业主审批</th>
                    <th scope="col">样品图片</th>
                    <th scope="col" class="actions">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($samples as $sample): ?>
                    <tr>
                        <td>
                            <span class="material-primary"><?= $escape($sample['material_name']) ?></span>
                            <span class="material-secondary"><?= $escape(($sample['material_code'] ? $sample['material_code'] . ' · ' : '') . $sample['unit']) ?></span>
                        </td>
                        <td><?= $escape($sample['supplier_name']) ?></td>
                        <td><?= $escape($sample['location_name'] !== '' ? $sample['location_name'] : '—') ?></td>
                        <td><?= (int) $sample['sample_quantity'] ?></td>
                        <td><?= $escape($sample['color'] ?: '—') ?></td>
                        <td><?= $escape($documentLabels[$sample['document_status']] ?? $sample['document_status']) ?></td>
                        <td><?= $escape($sentLabels[$sample['sent_status']] ?? $sample['sent_status']) ?></td>
                        <td><?= $escape($sample['sent_date'] ?: '—') ?></td>
                        <td>
                            <?= $escape($approvalLabels[$sample['owner_approval_status']] ?? $sample['owner_approval_status']) ?>
                            <?php if ($sample['owner_approval_date']): ?>
                                <span class="material-secondary"><?= $escape($sample['owner_approval_date']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sample['images'] === []): ?>
                                —
                            <?php else: ?>
                                <?php $linkedImages = 0; ?>
                                <div class="thumb-list">
                                    <?php foreach ($sample['images'] as $image): ?>
                                        <?php if (isset($image['url'])): $linkedImages++; ?>
                                            <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= $escape($image['original_name']) ?>">
                                                <img class="thumb" src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy">
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($linkedImages === 0): ?>
                                    <span class="muted"><?= count($sample['images']) ?> 张（R2 未配置）</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if ($canEdit): ?>
                                    <button class="button button-secondary" type="button" data-dialog-open="sample-edit-<?= (int) $sample['sample_id'] ?>">编辑</button>
                                <?php endif; ?>
                                <?php if ($canArchive): ?>
                                    <form method="post" action="" data-confirm="归档后样品将从列表移除，记录和图片保留。">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="material.sample.archive">
                                        <input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                                        <button class="button button-quiet" type="submit">归档</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($canCreate && !$canCreateSample): ?>
    <div class="notice notice-info">登记样品前，需先建立材料、供应商和项目空间位置；商业报价不是技术送样的前置条件。</div>
<?php endif; ?>

<?php if ($canCreateSample): ?>
    <dialog class="edit-dialog wide" id="sample-create" aria-labelledby="sample-create-title">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">材料样品</p>
                <h2 id="sample-create-title">新增样品</h2>
            </div>
            <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
        </div>
        <form method="post" action="" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.sample.save">
            <label>材料
                <select name="material_id" required>
                    <option value="">请选择材料</option>
                    <?php foreach ($choices['materials'] as $material): ?>
                        <option value="<?= (int) $material['material_id'] ?>"><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>供样供应商
                <select name="supplier_id" required>
                    <option value="">请选择供应商</option>
                    <?php foreach ($choices['suppliers'] as $supplier): ?>
                        <option value="<?= (int) $supplier['supplier_id'] ?>"><?= $escape($supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($canEditSampleQuotation): ?>
                <label>关联报价（可选）
                    <select name="quotation_id">
                        <option value="">暂未报价</option>
                        <?php foreach ($choices['quotations'] as $quote): ?>
                            <option value="<?= (int) $quote['quotation_id'] ?>"><?= $escape(($quote['material_code'] ? $quote['material_code'] . ' · ' : '') . $quote['material_name'] . ' · ' . $quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code'] . ' · ' . $quote['quotation_date']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>项目位置
                <select name="location" required>
                    <option value="">请选择当前项目位置</option>
                    <?php foreach ($locationOptions as $location): ?>
                        <option value="<?= $escape($location['value']) ?>"><?= $escape($location['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>送样数量<input type="number" name="sample_quantity" min="0" step="1" value="0" required></label>
            <label>颜色<input name="color" maxlength="50" value=""></label>
            <label>资料状态
                <select name="document_status">
                    <?php foreach ($documentLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>送样状态
                <select name="sent_status">
                    <?php foreach ($sentLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>送样日期<input type="date" name="sent_date"></label>
            <label>业主审批状态
                <select name="owner_approval_status">
                    <?php foreach ($approvalLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>业主审批日期<input type="date" name="owner_approval_date"></label>
            <label class="field-wide">备注<textarea name="remarks" rows="3" maxlength="20000"></textarea></label>
            <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存样品</button></div>
        </form>
    </dialog>
<?php endif; ?>

<?php if ($canEdit): ?>
    <?php foreach ($samples as $sample): ?>
        <dialog class="edit-dialog wide" id="sample-edit-<?= (int) $sample['sample_id'] ?>" aria-labelledby="sample-edit-title-<?= (int) $sample['sample_id'] ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">材料样品</p>
                    <h2 id="sample-edit-title-<?= (int) $sample['sample_id'] ?>">编辑样品</h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <form method="post" action="" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.sample.save">
                <input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                <label>材料
                    <select name="material_id" required>
                        <option value="">请选择材料</option>
                        <?php foreach ($choices['materials'] as $material): ?>
                            <option value="<?= (int) $material['material_id'] ?>" <?= (int) $material['material_id'] === (int) $sample['material_id'] ? 'selected' : '' ?>><?= $escape(($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>供样供应商
                    <select name="supplier_id" required>
                        <option value="">请选择供应商</option>
                        <?php foreach ($choices['suppliers'] as $supplier): ?>
                            <option value="<?= (int) $supplier['supplier_id'] ?>" <?= (int) $supplier['supplier_id'] === (int) $sample['supplier_id'] ? 'selected' : '' ?>><?= $escape($supplier['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($canEditSampleQuotation): ?>
                    <label>关联报价（可选）
                        <select name="quotation_id">
                            <option value="">暂未报价</option>
                            <?php foreach ($choices['quotations'] as $quote): ?>
                                <option value="<?= (int) $quote['quotation_id'] ?>" <?= (int) $quote['quotation_id'] === (int) $sample['quotation_id'] ? 'selected' : '' ?>><?= $escape($quote['material_name'] . ' · ' . $quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code'] . ' · ' . $quote['quotation_date']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <label>项目位置
                    <select name="location" required>
                        <?php
                        $currentLocationValue = $sample['location_type'] . ':' . $sample['location_id'];
                        $currentLocationFound = in_array($currentLocationValue, array_column($locationOptions, 'value'), true);
                        ?>
                        <option value="" <?= $currentLocationFound ? '' : 'selected' ?>><?= $currentLocationFound ? '请选择当前项目位置' : '原位置已归档，请重新选择' ?></option>
                        <?php foreach ($locationOptions as $location): ?>
                            <option value="<?= $escape($location['value']) ?>" <?= $location['value'] === $currentLocationValue ? 'selected' : '' ?>><?= $escape($location['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>送样数量<input type="number" name="sample_quantity" min="0" step="1" value="<?= (int) $sample['sample_quantity'] ?>" required></label>
                <label>颜色<input name="color" maxlength="50" value="<?= $escape($sample['color'] ?? '') ?>"></label>
                <label>资料状态
                    <select name="document_status">
                        <?php foreach ($documentLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $sample['document_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>送样状态
                    <select name="sent_status">
                        <?php foreach ($sentLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $sample['sent_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>送样日期<input type="date" name="sent_date" value="<?= $escape($sample['sent_date'] ?? '') ?>"></label>
                <label>业主审批状态
                    <select name="owner_approval_status">
                        <?php foreach ($approvalLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $sample['owner_approval_status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>业主审批日期<input type="date" name="owner_approval_date" value="<?= $escape($sample['owner_approval_date'] ?? '') ?>"></label>
                <label class="field-wide">备注<textarea name="remarks" rows="3" maxlength="20000"><?= $escape($sample['remarks'] ?? '') ?></textarea></label>
                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
            </form>
            <div class="dialog-section">
                <h3>样品图片</h3>
                <?php if ($sample['images'] === []): ?>
                    <p class="muted">尚未上传样品图片。</p>
                <?php else: ?>
                    <ul class="dialog-images">
                        <?php foreach ($sample['images'] as $image): ?>
                            <li>
                                <?php if (isset($image['url'])): ?>
                                    <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer"><img class="thumb" src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy"></a>
                                    <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($image['original_name']) ?></a>
                                <?php else: ?>
                                    <span><?= $escape($image['original_name']) ?>（R2 未配置）</span>
                                <?php endif; ?>
                                <span class="muted"><?= number_format((int) $image['file_size'] / 1024, 1) ?> KB</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($imageStorageReady): ?>
                    <form method="post" enctype="multipart/form-data" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="material.sample.image.upload">
                        <input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                        <label>上传图片<input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/bmp,image/webp" required></label>
                        <button class="button button-secondary" type="submit">上传到 R2</button>
                        <span class="muted">PNG/JPEG/GIF/BMP/WebP，最大 5 MB</span>
                    </form>
                <?php else: ?>
                    <p class="muted">R2 图片存储未配置，暂不可上传。</p>
                <?php endif; ?>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

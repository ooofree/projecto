<?php
$documentLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::DOCUMENT;
$sentLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::SENT;
$approvalLabels = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::APPROVAL;
$documentTones = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::DOCUMENT_TONES;
$sentTones = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::SENT_TONES;
$approvalTones = \ProjectO\Modules\MaterialManagement\SampleStatusLabels::APPROVAL_TONES;
$locationOptions = [];
foreach ($locations['buildings'] as $location) {
    $locationOptions[] = ['value' => 'building:' . $location['building_id'], 'label' => $location['building_name']];
}
foreach ($locations['floors'] as $location) {
    $locationOptions[] = ['value' => 'floor:' . $location['floor_id'], 'label' => trim($location['building_name'] . ' ' . ($location['floor_name'] ?: $location['floor_number']))];
}
foreach ($locations['areas'] as $location) {
    $locationOptions[] = ['value' => 'area:' . $location['area_id'], 'label' => trim($location['building_name'] . ' ' . $location['area_name'])];
}
foreach ($locations['rooms'] as $location) {
    $locationOptions[] = ['value' => 'room:' . $location['room_id'], 'label' => trim($location['building_name'] . ' ' . ($location['floor_name'] ?: $location['floor_number']) . ' ' . $location['room_number'])];
}
$canCreateSample = $canCreate && $choices['materials'] !== [] && $choices['suppliers'] !== [] && $locationOptions !== [];
// Coverage overview: every catalogue material against its active samples.
// Counts come from the server-side aggregate (full table, not the 200-row
// list), so gaps stay visible even on large projects.
$coverageRows = is_array($coverage ?? null) ? $coverage : [];
$coverageCounts = ['none' => 0, 'registered' => 0, 'sent' => 0];
$coverageAnnotations = [];
$coverageLabels = ['none' => '无样品记录', 'registered' => '已登记未送样', 'sent' => '已送样'];
$coverageTones = ['none' => 'red', 'registered' => 'amber', 'sent' => 'green'];
foreach ($coverageRows as $coverageRow) {
    $coverageCounts[$coverageRow['coverage_status']]++;
    if ($coverageRow['coverage_status'] === 'none') {
        $annotation = '无样品记录';
    } elseif ($coverageRow['coverage_status'] === 'registered') {
        $annotation = '已登记 ' . $coverageRow['sample_count'] . ' 条，未送样';
    } else {
        $annotation = '已送样 ' . $coverageRow['sent_count'] . ' 条';
        if ($coverageRow['unsent_count'] > 0) {
            $annotation .= '，另 ' . $coverageRow['unsent_count'] . ' 条未送';
        }
    }
    if ($coverageRow['rejected_count'] > 0) {
        $annotation .= '，含被拒 ' . $coverageRow['rejected_count'] . ' 条';
    }
    $coverageAnnotations[(int) $coverageRow['material_id']] = $annotation;
}
// Searchable picker shared by the create/edit dialogs: the native select stays
// the submitted value (and the no-JavaScript fallback), while app.js enhances
// the [data-combo] wrapper into a type-to-search input. Same pattern as the
// estimates and tender pages.
$renderSampleCombo = static function (string $name, array $options, ?string $selectedValue, string $placeholder, string $noun, string $ariaLabel, bool $required = true, string $extraSelectAttrs = '') use ($escape): string {
    ob_start(); ?>
    <div class="combo" data-combo data-combo-noun="<?= $escape($noun) ?>" data-combo-empty="没有匹配的<?= $escape($noun) ?>">
        <select name="<?= $escape($name) ?>" data-combo-select<?= $required ? ' required' : '' ?> aria-label="<?= $escape($ariaLabel) ?>"<?= $extraSelectAttrs !== '' ? ' ' . $extraSelectAttrs : '' ?>>
            <option value=""><?= $escape($placeholder) ?></option>
            <?php foreach ($options as $option): ?>
                <option value="<?= $escape((string) $option['value']) ?>"<?= $selectedValue !== null && (string) $option['value'] === $selectedValue ? ' selected' : '' ?>><?= $escape($option['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" class="combo-input" data-combo-input placeholder="输入关键词搜索" autocomplete="off" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="<?= $escape('搜索并选择' . $ariaLabel) ?>">
        <ul class="combo-options" data-combo-options role="listbox" hidden></ul>
    </div>
    <?php return (string) ob_get_clean();
};
$sampleMaterialOptions = [];
foreach ($choices['materials'] as $material) {
    $materialAnnotation = $coverageAnnotations[(int) $material['material_id']] ?? '';
    $sampleMaterialOptions[] = [
        'value' => (int) $material['material_id'],
        'label' => ($material['material_code'] ? $material['material_code'] . ' · ' : '') . $material['material_name'] . ($materialAnnotation !== '' ? '（' . $materialAnnotation . '）' : ''),
    ];
}
$sampleSupplierOptions = [];
foreach ($choices['suppliers'] as $supplier) {
    $sampleSupplierOptions[] = ['value' => (int) $supplier['supplier_id'], 'label' => $supplier['supplier_name']];
}
$sampleQuotationOptions = [];
foreach ($choices['quotations'] as $quote) {
    $sampleQuotationOptions[] = [
        'value' => (int) $quote['quotation_id'],
        'label' => ($quote['material_code'] ? $quote['material_code'] . ' · ' : '') . $quote['material_name'] . ' · ' . $quote['supplier_name'] . ' · ' . $quote['purchase_unit_price'] . ' ' . $quote['currency_code'] . ' · ' . $quote['quotation_date'],
    ];
}
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

<section class="panel" id="sample-coverage">
    <div class="panel-heading">
        <div>
            <h2>样品覆盖一览</h2>
            <p class="muted">按当前项目材料清单全量统计（不受列表 200 条上限影响），优先补充排在前面的材料；已删除（归档）的样品不计入。</p>
        </div>
        <span class="muted">无记录 <?= $coverageCounts['none'] ?> · 未送样 <?= $coverageCounts['registered'] ?> · 已送样 <?= $coverageCounts['sent'] ?></span>
    </div>
    <?php if ($coverageRows === []): ?>
        <p class="report-empty">当前项目尚无材料，先在“材料管理”建立材料清单后即可在此跟踪送样覆盖。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="report-table" id="sample-coverage-table">
                <thead>
                <tr>
                    <th scope="col">材料</th>
                    <th scope="col">样品覆盖</th>
                    <th scope="col">样品记录</th>
                    <th scope="col">未送样</th>
                    <th scope="col">最近送样日期</th>
                    <th scope="col" class="actions">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($coverageRows as $coverageRow): ?>
                    <tr>
                        <td>
                            <span class="material-primary"><?= $escape($coverageRow['material_name']) ?></span>
                            <span class="material-secondary"><?= $escape(($coverageRow['material_code'] ? $coverageRow['material_code'] . ' · ' : '') . $coverageRow['unit']) ?></span>
                        </td>
                        <td><span class="status-badge is-<?= $escape($coverageTones[$coverageRow['coverage_status']]) ?>"><?= $escape($coverageLabels[$coverageRow['coverage_status']]) ?></span></td>
                        <td><?= (int) $coverageRow['sample_count'] ?></td>
                        <td><?= (int) $coverageRow['unsent_count'] ?></td>
                        <td><?= $escape($coverageRow['last_sent_date'] ?: '—') ?></td>
                        <td>
                            <?php if ($canCreateSample): ?>
                                <div class="row-actions">
                                    <button class="button <?= $coverageRow['coverage_status'] === 'sent' ? 'button-quiet' : 'button-secondary' ?>" type="button" data-dialog-open="sample-create" data-sample-material="<?= (int) $coverageRow['material_id'] ?>"><?= $coverageRow['coverage_status'] === 'none' ? '登记样品' : '再登记一版' ?></button>
                                </div>
                            <?php else: ?>
                                —
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
    <div class="panel-heading">
        <div>
            <h2>样品列表</h2>
            <p class="muted">列出当前项目未删除的样品；点击“编辑”在弹窗中维护资料、送样和审批状态。</p>
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
        <p class="report-empty">当前项目尚无样品。<?= $canCreateSample ? '点击右上角“新增样品”开始登记。' : '' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="report-table" id="samples-table">
                <thead>
                <tr>
                    <th scope="col">材料</th>
                    <th scope="col">供应商</th>
                    <th scope="col">使用位置</th>
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
                        <td><span class="status-badge is-<?= $escape($documentTones[$sample['document_status']] ?? 'neutral') ?>"><?= $escape($documentLabels[$sample['document_status']] ?? $sample['document_status']) ?></span></td>
                        <td><span class="status-badge is-<?= $escape($sentTones[$sample['sent_status']] ?? 'neutral') ?>"><?= $escape($sentLabels[$sample['sent_status']] ?? $sample['sent_status']) ?></span></td>
                        <td><?= $escape($sample['sent_date'] ?: '—') ?></td>
                        <td>
                            <span class="status-badge is-<?= $escape($approvalTones[$sample['owner_approval_status']] ?? 'neutral') ?>"><?= $escape($approvalLabels[$sample['owner_approval_status']] ?? $sample['owner_approval_status']) ?></span>
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
                                            <button class="thumb-button" type="button" data-dialog-open="sample-images-<?= (int) $sample['sample_id'] ?>" title="查看样品图片">
                                                <img class="thumb" src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy">
                                            </button>
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
                                    <button class="button button-quiet" type="button" data-dialog-open="sample-delete-<?= (int) $sample['sample_id'] ?>">删除</button>
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
            <label>材料<?= $renderSampleCombo('material_id', $sampleMaterialOptions, null, '请选择材料', '材料', '材料', true, 'data-sample-material-select') ?></label>
            <div class="field-wide notice notice-info" data-sample-material-hint hidden></div>
            <label>供样供应商<?= $renderSampleCombo('supplier_id', $sampleSupplierOptions, null, '请选择供应商', '供应商', '供样供应商') ?></label>
            <?php if ($canEditSampleQuotation): ?>
                <label>关联报价（可选）<?= $renderSampleCombo('quotation_id', $sampleQuotationOptions, null, '暂未报价', '报价', '关联报价', false) ?></label>
            <?php endif; ?>
            <label>使用位置<?= $renderSampleCombo('location', $locationOptions, null, '请选择当前项目位置', '位置', '使用位置') ?></label>
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
                <?php
                $currentLocationValue = $sample['location_type'] . ':' . $sample['location_id'];
                $currentLocationFound = in_array($currentLocationValue, array_column($locationOptions, 'value'), true);
                ?>
                <label>材料<?= $renderSampleCombo('material_id', $sampleMaterialOptions, (string) $sample['material_id'], '请选择材料', '材料', '材料') ?></label>
                <label>供样供应商<?= $renderSampleCombo('supplier_id', $sampleSupplierOptions, (string) $sample['supplier_id'], '请选择供应商', '供应商', '供样供应商') ?></label>
                <?php if ($canEditSampleQuotation): ?>
                    <label>关联报价（可选）<?= $renderSampleCombo('quotation_id', $sampleQuotationOptions, $sample['quotation_id'] !== null ? (string) $sample['quotation_id'] : null, '暂未报价', '报价', '关联报价', false) ?></label>
                <?php endif; ?>
                <label>使用位置<?= $renderSampleCombo('location', $locationOptions, $currentLocationFound ? $currentLocationValue : null, $currentLocationFound ? '请选择当前项目位置' : '原位置已归档，请重新选择', '位置', '使用位置') ?></label>
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

<?php if ($imageStorageReady): ?>
    <?php foreach ($samples as $sample): ?>
        <?php if ($sample['images'] === []) { continue; } ?>
        <dialog class="edit-dialog wide" id="sample-images-<?= (int) $sample['sample_id'] ?>" aria-labelledby="sample-images-title-<?= (int) $sample['sample_id'] ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">材料样品</p>
                    <h2 id="sample-images-title-<?= (int) $sample['sample_id'] ?>">样品图片 · <?= $escape($sample['material_name']) ?></h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <div class="image-gallery">
                <?php foreach ($sample['images'] as $image): ?>
                    <?php if (!isset($image['url'])) { continue; } ?>
                    <figure>
                        <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer" title="在新窗口打开原图">
                            <img src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy">
                        </a>
                        <figcaption>
                            <?= $escape($image['original_name']) ?>
                            · <?= number_format((int) $image['file_size'] / 1024, 1) ?> KB
                            <?php if (!empty($image['created_at'])): ?>· <?= $escape($image['created_at']) ?><?php endif; ?>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <div class="dialog-section">
                <h3>样品信息</h3>
                <dl class="image-meta">
                    <div><dt>材料</dt><dd><?= $escape($sample['material_name']) ?><?= $sample['material_code'] ? '（' . $escape($sample['material_code']) . '）' : '' ?></dd></div>
                    <div><dt>供样供应商</dt><dd><?= $escape($sample['supplier_name']) ?></dd></div>
                    <div><dt>使用位置</dt><dd><?= $escape($sample['location_name'] !== '' ? $sample['location_name'] : '—') ?></dd></div>
                    <div><dt>送样数量</dt><dd><?= (int) $sample['sample_quantity'] ?></dd></div>
                    <div><dt>颜色</dt><dd><?= $escape($sample['color'] ?: '—') ?></dd></div>
                    <div><dt>送样日期</dt><dd><?= $escape($sample['sent_date'] ?: '—') ?></dd></div>
                    <div><dt>资料状态</dt><dd><span class="status-badge is-<?= $escape($documentTones[$sample['document_status']] ?? 'neutral') ?>"><?= $escape($documentLabels[$sample['document_status']] ?? $sample['document_status']) ?></span></dd></div>
                    <div><dt>送样状态</dt><dd><span class="status-badge is-<?= $escape($sentTones[$sample['sent_status']] ?? 'neutral') ?>"><?= $escape($sentLabels[$sample['sent_status']] ?? $sample['sent_status']) ?></span></dd></div>
                    <div><dt>业主审批</dt><dd><span class="status-badge is-<?= $escape($approvalTones[$sample['owner_approval_status']] ?? 'neutral') ?>"><?= $escape($approvalLabels[$sample['owner_approval_status']] ?? $sample['owner_approval_status']) ?></span><?= $sample['owner_approval_date'] ? ' <span class="muted">' . $escape($sample['owner_approval_date']) . '</span>' : '' ?></dd></div>
                    <?php if ($canViewQuotations && $sample['quotation_id'] !== null): ?>
                        <div><dt>商业报价</dt><dd><?= $escape($sample['purchase_unit_price'] . ' ' . $sample['currency_code'] . ' · ' . $sample['quotation_date']) ?></dd></div>
                    <?php endif; ?>
                    <div class="image-meta-wide"><dt>备注</dt><dd><?= $sample['remarks'] !== null && $sample['remarks'] !== '' ? nl2br($escape($sample['remarks'])) : '—' ?></dd></div>
                </dl>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($canArchive): ?>
    <?php foreach ($samples as $sample): ?>
        <dialog class="edit-dialog" id="sample-delete-<?= (int) $sample['sample_id'] ?>" aria-labelledby="sample-delete-title-<?= (int) $sample['sample_id'] ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">材料样品</p>
                    <h2 id="sample-delete-title-<?= (int) $sample['sample_id'] ?>">删除样品</h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <p>确定删除样品「<?= $escape($sample['material_name']) ?>」（供应商：<?= $escape($sample['supplier_name']) ?>）？</p>
            <p class="muted">删除为软删除：样品会从列表移除，跟踪记录与已上传图片保留在数据库中。</p>
            <form method="post" action="" data-confirm="删除后样品将从列表移除，记录和图片保留。确定删除？">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.sample.archive">
                <input type="hidden" name="sample_id" value="<?= (int) $sample['sample_id'] ?>">
                <div class="dialog-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                    <button class="button button-danger" type="submit">确认删除</button>
                </div>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($canCreate || $canEdit): ?>
<script>
// Enhance the sample dialog pickers with the shared searchable combo from
// app.js (same behaviour as the estimates/tender pages). Runs after the
// deferred bundle; without JavaScript the native selects still work.
if (typeof window.projectOInitCombos === 'function') window.projectOInitCombos();
</script>
<?php endif; ?>
<?php if ($canCreateSample): ?>
<script>
// Sample coverage helpers: preselect the material when a coverage row opens
// the create dialog, and show that material's coverage hint inside the dialog.
// Progressive enhancement only — the dialog and form work without this script.
(() => {
    const dialog = document.getElementById('sample-create');
    if (!dialog) return;
    const select = dialog.querySelector('[data-sample-material-select]');
    const hint = dialog.querySelector('[data-sample-material-hint]');
    const annotations = <?= json_encode($coverageAnnotations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const updateHint = () => {
        if (!hint || !select) return;
        const text = annotations[select.value] || '';
        hint.textContent = text === '' ? '' : '该材料当前：' + text + '。被拒后重送或换色重送可直接新增一版，无需修改旧记录。';
        hint.hidden = text === '';
    };
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dialog-open="sample-create"][data-sample-material]');
        if (trigger && select) {
            select.value = trigger.dataset.sampleMaterial;
            // Keep the searchable combo input in sync with the preselection.
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updateHint();
        }
    });
    if (select) select.addEventListener('change', updateHint);
})();
</script>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
$statusLabels = [
    'open' => '待评估',
    'in_review' => '处理中',
    'resolved' => '待关闭',
    'closed' => '已关闭',
];
$severityLabels = [
    'low' => '低',
    'medium' => '中',
    'high' => '高',
    'critical' => '紧急',
];
// Status and severity are separate visual semantics (Design System V2):
// status = workflow progress (blue/amber/green/neutral), severity = risk heat
// (neutral/blue/orange/red). Both always carry a text label.
$statusBadgeClass = [
    'open' => 'is-blue',
    'in_review' => 'is-amber',
    'resolved' => 'is-green',
    'closed' => 'is-neutral',
];
$severityBadgeClass = [
    'low' => 'is-neutral',
    'medium' => 'is-blue',
    'high' => 'is-orange',
    'critical' => 'is-red',
];
$rfiStatusLabels = [
    'draft' => '草稿',
    'submitted' => '已提交',
    'under_review' => '审核中',
    'responded' => '已答复',
    'closed' => '已关闭',
];
$rfiStatusBadgeClass = [
    'draft' => 'is-neutral',
    'submitted' => 'is-blue',
    'under_review' => 'is-amber',
    'responded' => 'is-green',
    'closed' => 'is-neutral',
];
$locationOptions = $locationCatalog['options'] ?? [];
$locationLabels = $locationCatalog['labels'] ?? [];
$spaceSelectionReady = !empty($spaceSelectionReady);
$locationTypeLabels = ['building' => '楼栋', 'floor' => '楼层', 'area' => '区域', 'room' => '房间'];
$locationPickerIndex = 0;
/**
 * Render the issue location field.
 *
 * Server-rendered fallback: a grouped native select that already submits the
 * right value. issue-location.js enhances it into search + tree browse + recent
 * picks, keeping this select as the submitted control, so the form still works
 * with JavaScript disabled. The catalogue is emitted as JSON for the enhancer;
 * it holds only this project's active spaces, already filtered server-side.
 */
$renderLocationSelect = static function (
    string $selectedValue,
    string $legacyText = '',
    string $noteValue = ''
) use (
    $escape,
    $locationOptions,
    $spaceSelectionReady,
    $locationTypeLabels,
    $locationCatalog,
    $currentProject,
    &$locationPickerIndex
): string {
    if (!$spaceSelectionReady) {
        return '<p class="muted">项目空间不可用，暂时无法选择位置。</p>';
    }

    $known = false;
    foreach ($locationOptions as $option) {
        if ($option['value'] === $selectedValue) {
            $known = true;
            break;
        }
    }

    $locationPickerIndex++;
    $pickerId = 'location-picker-' . $locationPickerIndex;
    $html = '<div class="field-wide" data-location-picker id="' . $escape($pickerId)
        . '" data-project-id="' . (int) $currentProject['project_id'] . '">';
    $html .= '<span class="field-label">位置（可选，从项目空间选择）</span>';

    // Progressive-enhancement payload: only active, project-scoped spaces.
    $html .= '<script type="application/json" data-location-catalog>'
        . json_encode(
            ['options' => array_values($locationOptions)],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        )
        . '</script>';

    $html .= '<select name="location" class="loc-fallback" aria-label="位置">';
    if ($legacyText !== '') {
        $html .= '<option value="legacy" selected>保留原位置：' . $escape($legacyText) . '</option>';
    }
    if ($selectedValue === '' && $legacyText === '') {
        $html .= '<option value="" selected>不指定位置</option>';
    } elseif ($known || $legacyText !== '') {
        $html .= '<option value="">不指定位置（清空）</option>';
    } else {
        $html .= '<option value="" selected>原位置已归档或不存在，请重新选择</option>';
    }
    $grouped = [];
    foreach ($locationOptions as $option) {
        $grouped[$option['type']][] = $option;
    }
    foreach ($locationTypeLabels as $type => $typeLabel) {
        if (!isset($grouped[$type])) {
            continue;
        }
        $html .= '<optgroup label="' . $escape($typeLabel) . '">';
        foreach ($grouped[$type] as $option) {
            $html .= '<option value="' . $escape($option['value']) . '"'
                . ($option['value'] === $selectedValue ? ' selected' : '') . '>'
                . $escape($option['label']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';

    // Server-rendered note field; the enhancer moves its value and removes this
    // wrapper so exactly one location_note input is ever submitted.
    $html .= '<span class="loc-note" data-location-note-field>'
        . '<label>位置补充说明（可选）'
        . '<input name="location_note" maxlength="255" data-location-note value="' . $escape($noteValue)
        . '" placeholder="如 C-E轴、前室靠门侧">'
        . '</label></span>';

    if ($locationOptions === []) {
        $html .= '<p class="loc-empty-note">当前项目还没有空间记录，请先在“项目空间”中维护楼栋、楼层、区域或房间。</p>';
    }
    $html .= '</div>';

    return $html;
};
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">文档管理 · 问题追踪</p>
        <h1>问题管理</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。RFI 答复不会自动改变问题状态。</p>
    </div>
    <?php if ($canCreate): ?>
        <button class="button button-primary" type="button" data-dialog-open="issue-create-dialog">登记问题</button>
    <?php endif; ?>
</section>
<?php
$navItems = [
    ['page' => 'projects', 'label' => '项目首页'],
    ['page' => 'documents', 'label' => '文档管理首页'],
    ['page' => 'issues', 'label' => '问题管理'],
    ['page' => 'rfis', 'label' => 'RFI 管理'],
];
if (!empty($canManageSettings)) {
    $navItems[] = ['page' => 'document-settings', 'label' => '系统设置'];
}
$navCurrent = 'issues';
$navLabel = '文档管理';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
?>
<?php if (!$imageStorageReady): ?>
    <div class="notice notice-info">图片存储尚未配置；问题与 RFI 图片暂不可上传或查看。</div>
<?php endif; ?>

<?php if ($canCreate): ?>
    <dialog class="edit-dialog" id="issue-create-dialog">
        <div class="dialog-heading">
            <h2>登记问题</h2>
            <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="issue.create">
            <label class="field-wide">
                问题描述
                <textarea name="description" rows="4" maxlength="10000" required></textarea>
            </label>
            <label>
                严重程度
                <select name="severity">
                    <option value="low">低</option>
                    <option value="medium" selected>中</option>
                    <option value="high">高</option>
                    <option value="critical">紧急</option>
                </select>
            </label>
            <?= $renderLocationSelect('') ?>
            <div class="field-wide dialog-actions">
                <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                <button class="button button-primary" type="submit">保存问题</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>问题台账</h2>
        </div>
    </div>
    <div class="toolbar" role="search" aria-label="筛选问题">
        <label class="search-field">
            <span class="sr-only">搜索问题</span>
            <input type="search" placeholder="搜索问题…" data-list-search="issues-table">
        </label>
        <label>
            状态
            <select data-list-filter="status">
                <option value="">全部状态</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            严重程度
            <select data-list-filter="severity">
                <option value="">全部程度</option>
                <?php foreach ($severityLabels as $value => $label): ?>
                    <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button button-quiet" type="button" data-list-filter-clear hidden>清空筛选</button>
        <span class="toolbar-count" data-list-count data-unit=" 条" aria-live="polite"><?= count($issues) ?> 条</span>
        <form method="post" action="" class="inline-form toolbar-export">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="issue.export">
            <button class="button button-secondary" type="submit">导出 Excel</button>
        </form>
    </div>
    <?php if ($issues === []): ?>
        <p class="empty-state">当前项目还没有问题记录。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>编号</th><th>问题</th><th>严重程度</th><th>状态</th><th>位置</th><th>图片</th><th>更新时间</th><th>操作</th></tr>
                </thead>
                <tbody id="issues-table">
                    <?php foreach ($issues as $issue): ?>
                        <?php $searchText = mb_strtolower(implode(' ', [
                            $issue['description'],
                            $issue['issue_number'] ?? '',
                            $issue['location_display'] ?? '',
                            $statusLabels[$issue['status']] ?? $issue['status'],
                            $severityLabels[$issue['severity']] ?? $issue['severity'],
                        ])); ?>
                        <?php
                        $openLinkedRfis = 0;
                        foreach ($issue['linked_rfis'] ?? [] as $linkedRfi) {
                            if ($linkedRfi['status'] !== 'closed') {
                                $openLinkedRfis++;
                            }
                        }
                        $openIssueFollowUps = 0;
                        foreach ($issue['follow_ups'] ?? [] as $followUp) {
                            if ((int) $followUp['is_completed'] !== 1) {
                                $openIssueFollowUps++;
                            }
                        }
                        ?>
                        <tr id="issue-<?= (int) $issue['issue_id'] ?>" data-list-item data-search="<?= $escape($searchText) ?>"
                            data-status="<?= $escape($issue['status']) ?>"
                            data-severity="<?= $escape($issue['severity']) ?>">
                            <td><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?></td>
                            <td><span class="table-primary-cell issue-description-clamp" title="<?= $escape($issue['description']) ?>"><?= $escape(mb_strimwidth($issue['description'], 0, 200, '…')) ?></span></td>
                            <td><span class="status-badge <?= $escape($severityBadgeClass[$issue['severity']] ?? 'is-neutral') ?>"><?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span></td>
                            <td><span class="status-badge <?= $escape($statusBadgeClass[$issue['status']] ?? 'is-neutral') ?>"><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span></td>
                            <td><?= $escape(($issue['location_display'] ?? '') !== '' ? $issue['location_display'] : '—') ?></td>
                            <td><?= count($issue['images']) ?></td>
                            <td><?= $escape(\ProjectO\Support::formatDateTime($issue['updated_at'])) ?></td>
                            <td>
                                <div class="row-actions">
                                    <button class="button button-secondary" type="button" data-dialog-open="issue-dialog-<?= (int) $issue['issue_id'] ?>">查看 / 编辑</button>
                                    <?php if ($canEdit): ?>
                                        <?php if ($issue['status'] === 'open'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="issue.quick">
                                                <input type="hidden" name="step" value="start">
                                                <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                                                <button class="button button-secondary" type="submit">开始处理</button>
                                            </form>
                                        <?php elseif ($issue['status'] === 'in_review'): ?>
                                            <button class="button button-secondary" type="button" data-dialog-open="issue-quick-resolve-<?= (int) $issue['issue_id'] ?>">转待关闭</button>
                                        <?php elseif ($issue['status'] === 'resolved'): ?>
                                            <?php if ($openLinkedRfis > 0): ?>
                                                <button class="button button-secondary" type="button" disabled title="请先关闭该问题的关联 RFI">关闭</button>
                                            <?php elseif ($openIssueFollowUps > 0): ?>
                                                <button class="button button-secondary" type="button" disabled title="请先完成该问题的后续事项">关闭</button>
                                            <?php else: ?>
                                                <button class="button button-secondary" type="button" data-dialog-open="issue-quick-close-<?= (int) $issue['issue_id'] ?>">关闭</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="empty-state" data-list-empty hidden>没有匹配的问题。</p>

        <?php foreach ($issues as $issue): ?>
            <dialog class="edit-dialog" id="issue-dialog-<?= (int) $issue['issue_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">问题 <?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · <?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></p>
                        <h2>问题详情与处理</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <p class="muted">登记于 <?= $escape(\ProjectO\Support::formatDateTime($issue['reported_at'])) ?> · 登记人编号 #<?= (int) $issue['reported_by'] ?></p>

                <?php if ($canEdit): ?>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="issue.update">
                        <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                        <label class="field-wide">
                            问题描述
                            <textarea name="description" rows="3" maxlength="10000" required><?= $escape($issue['description']) ?></textarea>
                        </label>
                        <label>
                            严重程度
                            <select name="severity" required>
                                <?php foreach ($severityLabels as $value => $label): ?>
                                    <option value="<?= $escape($value) ?>" <?= $issue['severity'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?= $renderLocationSelect(
                            (string) ($issue['location_value'] ?? ''),
                            $issue['location_type'] === null ? (string) ($issue['location'] ?? '') : '',
                            (string) ($issue['location_note'] ?? '')
                        ) ?>
                        <div class="field-wide dialog-actions">
                            <button class="button button-primary" type="submit">保存编辑</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p><?= nl2br($escape($issue['description'])) ?></p>
                    <p>严重程度：<?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?> · 位置：<?= $escape(($issue['location_display'] ?? '') !== '' ? $issue['location_display'] : '—') ?></p>
                <?php endif; ?>

                <?php if ($issue['resolution_result']): ?>
                    <p><strong>处理结果：</strong><?= nl2br($escape($issue['resolution_result'])) ?></p>
                <?php endif; ?>

                <?php if ($canEdit && $issue['available_transitions'] !== []): ?>
                    <details class="record-tools">
                        <summary>更新状态与关闭校验</summary>
                        <p class="muted record-meta">流程为「待评估 → 处理中 → 待关闭 → 已关闭」：只有进入「待关闭」后才会出现「已关闭」。关闭前须完成全部后续事项与关联 RFI，并填写现场验证与证据档案引用。<?php if ($openLinkedRfis > 0): ?>当前仍有 <?= $openLinkedRfis ?> 份关联 RFI 未关闭，暂不能选择「已关闭」。<?php endif; ?></p>
                        <form method="post" class="form-grid record-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="issue.transition">
                            <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                            <label>
                                转换至
                                <select name="status" required>
                                    <?php foreach ($issue['available_transitions'] as $status): ?>
                                        <?php $closable = $status !== 'closed' || $openLinkedRfis === 0; ?>
                                        <option value="<?= $escape($status) ?>" <?= $closable ? '' : 'disabled' ?>><?= $escape(($statusLabels[$status] ?? $status) . ($closable ? '' : '（先关闭关联 RFI）')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">
                                状态变更原因
                                <textarea name="reason" rows="2" maxlength="2000" required></textarea>
                            </label>
                            <label class="field-wide">
                                处理结果（进入“待关闭”时必填）
                                <textarea name="resolution_result" rows="2" maxlength="10000"><?= $escape($issue['resolution_result'] ?? '') ?></textarea>
                            </label>
                            <label>
                                现场验证（关闭时必选）
                                <select name="verification_status">
                                    <option value="">请选择</option>
                                    <option value="passed">已通过</option>
                                    <option value="not_required">不适用</option>
                                </select>
                            </label>
                            <label>
                                验证说明（不适用时必填）
                                <input name="verification_note" maxlength="2000" value="<?= $escape($issue['verification_note'] ?? '') ?>">
                            </label>
                            <label class="field-wide">
                                状态关联证据档案引用（关闭时必填）
                                <input name="evidence_reference" maxlength="1000" placeholder="受控档案系统中的编号或链接">
                            </label>
                            <div class="field-wide"><button class="button button-primary" type="submit">保存状态</button></div>
                        </form>
                    </details>
                <?php endif; ?>

                <?php if ($canEdit && $issue['status'] !== 'closed'): ?>
                    <details class="record-tools">
                        <summary>后续事项（<?= count($issue['follow_ups']) ?>）</summary>
                        <?php if ($issue['follow_ups'] !== []): ?>
                            <ul class="follow-up-list">
                                <?php foreach ($issue['follow_ups'] as $followUp): ?>
                                    <li>
                                        <div>
                                            <span class="<?= (int) $followUp['is_completed'] === 1 ? 'is-complete' : '' ?>"><?= $escape($followUp['title']) ?></span>
                                            <?php foreach ($followUp['status_history'] as $event): ?>
                                                <span class="muted record-meta"><?= $escape(\ProjectO\Support::formatDateTime($event['changed_at'])) ?> · <?= $escape($event['reason']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ((int) $followUp['is_completed'] !== 1): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="issue.followup.complete">
                                                <input type="hidden" name="follow_up_id" value="<?= (int) $followUp['follow_up_id'] ?>">
                                                <label>完成原因<input name="reason" maxlength="2000" required></label>
                                                <label>档案引用<input name="evidence_reference" maxlength="1000"></label>
                                                <button class="button button-quiet" type="submit">完成</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="selected-label">已完成</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="issue.followup.create">
                            <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                            <label>新增后续事项<input name="title" maxlength="255" required></label>
                            <button class="button button-secondary" type="submit">添加</button>
                        </form>
                    </details>
                <?php endif; ?>

                <?php $linkedRfis = $issue['linked_rfis'] ?? []; ?>
                <details class="record-tools">
                    <summary>关联 RFI（<?= count($linkedRfis) ?>）</summary>
                    <?php if ($linkedRfis === []): ?>
                        <p class="muted">该问题尚未派生 RFI。</p>
                    <?php else: ?>
                        <ul class="follow-up-list">
                            <?php foreach ($linkedRfis as $linkedRfi): ?>
                                <li>
                                    <div>
                                        <a href="?page=rfis"><?= $escape($linkedRfi['rfi_number'] !== '' ? $linkedRfi['rfi_number'] : '#' . $linkedRfi['rfi_id']) ?></a>
                                        <span class="status-badge <?= $escape($rfiStatusBadgeClass[$linkedRfi['status']] ?? 'is-neutral') ?>"><?= $escape($rfiStatusLabels[$linkedRfi['status']] ?? $linkedRfi['status']) ?></span>
                                        <span class="muted record-meta"><?= $linkedRfi['is_primary'] ? '主问题' : '关联问题' ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="muted record-meta">RFI 全部关闭后，问题会自动转为「待关闭」；最终关闭仍需人工填写处理结果、现场验证与证据引用。</p>
                    <?php endif; ?>
                </details>

                <details class="record-tools">
                    <summary>图片附件（<?= count($issue['images']) ?>）</summary>
                    <?php if ($issue['images'] !== []): ?>
                        <ul class="dialog-images">
                            <?php foreach ($issue['images'] as $image): ?>
                                <li>
                                    <?php if (isset($image['url'])): ?>
                                        <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($image['original_name']) ?></a>
                                    <?php else: ?>
                                        <span><?= $escape($image['original_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="muted"><?= number_format((int) $image['file_size'] / 1024, 1) ?> KB</span>
                                    <?php if ($canEdit && $imageStorageReady): ?>
                                        <form method="post" class="inline-form" data-confirm="删除后图片将从记录和对象存储中移除。">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="issue.image.delete">
                                            <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                                            <input type="hidden" name="image_id" value="<?= (int) $image['image_id'] ?>">
                                            <button class="button button-quiet" type="submit">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">尚未上传图片。</p>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <?php if ($imageStorageReady): ?>
                            <form method="post" enctype="multipart/form-data" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="issue.image.upload">
                                <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                                <label>上传图片<input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/bmp,image/webp" required></label>
                                <button class="button button-secondary" type="submit">上传</button>
                                <span class="muted">PNG/JPEG/GIF/BMP/WebP，最大 5 MB</span>
                            </form>
                        <?php else: ?>
                            <p class="muted">图片存储未配置，暂不可上传或查看；可在文件模块“系统设置”中配置。</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </details>

                <details class="record-tools">
                    <summary>状态历史（<?= count($issue['status_history']) ?>）</summary>
                    <?php if ($issue['status_history'] === []): ?>
                        <p class="muted">暂无状态历史。</p>
                    <?php else: ?>
                        <ol class="history-list">
                            <?php foreach ($issue['status_history'] as $event): ?>
                                <li>
                                    <strong><?= $escape($event['old_status'] === null ? '新建' : ($statusLabels[$event['old_status']] ?? $event['old_status'])) ?> → <?= $escape($statusLabels[$event['new_status']] ?? $event['new_status']) ?></strong>
                                    <span><?= $escape(\ProjectO\Support::formatDateTime($event['changed_at'])) ?> · 操作人 #<?= (int) $event['changed_by'] ?></span>
                                    <p><?= nl2br($escape($event['reason'])) ?></p>
                                    <?php if ($event['evidence_reference']): ?><p>证据档案：<?= $escape($event['evidence_reference']) ?></p><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </details>
            </dialog>

            <dialog class="edit-dialog is-compact" id="issue-quick-resolve-<?= (int) $issue['issue_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">问题 <?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · 快捷操作</p>
                        <h2>转待关闭</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="issue.quick">
                    <input type="hidden" name="step" value="resolve">
                    <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                    <label class="field-wide">
                        处理结果
                        <textarea name="resolution_result" rows="3" maxlength="10000" required><?= $escape($issue['resolution_result'] ?? '') ?></textarea>
                    </label>
                    <p class="muted field-wide record-meta">现场验证与证据引用在最终关闭时必填。</p>
                    <div class="field-wide dialog-actions">
                        <button class="button button-primary" type="submit">保存为待关闭</button>
                    </div>
                </form>
            </dialog>

            <dialog class="edit-dialog is-compact" id="issue-quick-close-<?= (int) $issue['issue_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">问题 <?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · 快捷操作</p>
                        <h2>关闭问题</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="issue.quick">
                    <input type="hidden" name="step" value="close">
                    <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                    <label class="field-wide">
                        处理结果
                        <textarea name="resolution_result" rows="3" maxlength="10000" required><?= $escape($issue['resolution_result'] ?? '') ?></textarea>
                    </label>
                    <label>
                        现场验证
                        <select name="verification_status" required>
                            <option value="">请选择</option>
                            <option value="passed" <?= $issue['verification_status'] === 'passed' ? 'selected' : '' ?>>通过</option>
                            <option value="not_required" <?= $issue['verification_status'] === 'not_required' ? 'selected' : '' ?>>不适用</option>
                        </select>
                    </label>
                    <label>
                        验证说明
                        <input name="verification_note" maxlength="2000" value="<?= $escape($issue['verification_note'] ?? '') ?>">
                    </label>
                    <label class="field-wide">
                        证据档案引用
                        <input name="evidence_reference" maxlength="1000" required value="<?= $escape($issue['evidence_reference'] ?? '') ?>">
                    </label>
                    <p class="muted field-wide record-meta">选择“不适用”时必须填写验证说明；有后续事项或未关闭 RFI 时无法关闭。</p>
                    <div class="field-wide dialog-actions">
                        <button class="button button-primary" type="submit">确认关闭</button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$pageStyles = [['module' => 'document.management', 'file' => 'issue-location.css', 'version' => $moduleAssetVersions['issue-location.css'] ?? '0']];
$pageScripts = [['module' => 'document.management', 'file' => 'issue-location.js', 'version' => $moduleAssetVersions['issue-location.js'] ?? '0']];
require dirname(__DIR__, 3) . '/views/layout.php';
?>

<?php
$statusLabels = [
    'draft' => '草稿',
    'submitted' => '已提交',
    'under_review' => '审核中',
    'responded' => '已答复',
    'closed' => '已关闭',
];
$priorityLabels = [
    'low' => '低',
    'medium' => '中',
    'high' => '高',
    'urgent' => '紧急',
];
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">文档管理 · 信息请求</p>
        <h1>RFI 管理</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。每份 RFI 必须关联一个主问题，可另关联多个问题。</p>
    </div>
    <?php if ($canCreate && $issues !== []): ?>
        <button class="button button-primary" type="button" data-dialog-open="rfi-create-dialog">创建 RFI</button>
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
$navCurrent = 'rfis';
$navLabel = '文档管理';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
?>
<?php if (!$imageStorageReady): ?>
    <div class="notice notice-info">图片存储尚未配置；问题与 RFI 图片暂不可上传或查看。</div>
<?php endif; ?>

<?php if ($canCreate): ?>
    <dialog class="edit-dialog" id="rfi-create-dialog">
        <div class="dialog-heading">
            <h2>创建 RFI 草稿</h2>
            <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
        </div>
        <?php if ($issues === []): ?>
            <p class="empty-state">请先登记问题，再从问题创建 RFI。</p>
        <?php else: ?>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="rfi.create">
                <label class="field-wide">
                    主问题（必选）
                    <select name="primary_issue_id" required>
                        <option value="">请选择</option>
                        <?php foreach ($issues as $issue): ?>
                            <option value="<?= (int) $issue['issue_id'] ?>"><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · <?= $escape($issue['status']) ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">
                    其他相关问题（可多选）
                    <select name="related_issue_ids[]" multiple size="4">
                        <?php foreach ($issues as $issue): ?>
                            <option value="<?= (int) $issue['issue_id'] ?>"><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    负责人
                    <select name="assigned_to" required>
                        <option value="">请选择</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['user_id'] ?>"><?= $escape($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    优先级
                    <select name="priority">
                        <?php foreach ($priorityLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $value === 'medium' ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>答复截止时间<input type="datetime-local" name="due_date" required></label>
                <label class="field-wide">澄清问题<textarea name="description" rows="3" maxlength="10000" required></textarea></label>
                <label class="checkbox-field field-wide">
                    <input type="checkbox" name="evidence_required" value="1">
                    关闭前必须填写证据档案引用
                </label>
                <div class="field-wide dialog-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                    <button class="button button-primary" type="submit">创建草稿</button>
                </div>
            </form>
        <?php endif; ?>
    </dialog>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>RFI 台账</h2>
            <span class="muted" data-list-count data-unit=" 份"><?= count($rfis) ?> 份</span>
        </div>
        <label class="search-field">
            <span class="sr-only">搜索 RFI</span>
            <input type="search" placeholder="搜索 RFI…" data-list-search="rfis-table">
        </label>
    </div>
    <?php if ($rfis === []): ?>
        <p class="empty-state">当前项目还没有 RFI。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>编号</th><th>RFI / 主问题</th><th>负责人</th><th>优先级</th><th>状态</th><th>截止时间</th><th>图片</th><th>操作</th></tr>
                </thead>
                <tbody id="rfis-table">
                    <?php foreach ($rfis as $rfi): ?>
                        <?php $searchText = mb_strtolower(implode(' ', [
                            $rfi['rfi_number'],
                            $rfi['description'],
                            $rfi['primary_issue_description'],
                            $rfi['assignee_name'],
                            $statusLabels[$rfi['status']] ?? $rfi['status'],
                            $priorityLabels[$rfi['priority']] ?? $rfi['priority'],
                        ])); ?>
                        <?php
                        $openRfiFollowUps = 0;
                        foreach ($rfi['follow_ups'] ?? [] as $followUp) {
                            if ((int) $followUp['is_completed'] !== 1) {
                                $openRfiFollowUps++;
                            }
                        }
                        ?>
                        <tr id="rfi-<?= (int) $rfi['rfi_id'] ?>" data-list-item data-search="<?= $escape($searchText) ?>">
                            <td><?= $escape($rfi['rfi_number']) ?></td>
                            <td>
                                <span class="table-primary-cell"><?= $escape(mb_strimwidth($rfi['description'], 0, 85, '…')) ?></span>
                                <span class="muted record-meta">主问题 <?= $escape($rfi['primary_issue_number'] ?: '#' . $rfi['primary_issue_id']) ?> · <?= $escape(mb_strimwidth($rfi['primary_issue_description'], 0, 65, '…')) ?></span>
                            </td>
                            <td><?= $escape($rfi['assignee_name']) ?></td>
                            <td><span class="badge badge-neutral"><?= $escape($priorityLabels[$rfi['priority']] ?? $rfi['priority']) ?></span></td>
                            <td><span class="badge"><?= $escape($statusLabels[$rfi['status']] ?? $rfi['status']) ?></span></td>
                            <td><?= $escape(\ProjectO\Support::formatDateTime($rfi['due_date'])) ?></td>
                            <td><?= count($rfi['images']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <button class="button button-secondary" type="button" data-dialog-open="rfi-dialog-<?= (int) $rfi['rfi_id'] ?>">查看 / 编辑</button>
                                    <?php if ($canEdit): ?>
                                        <?php if ($rfi['status'] === 'draft'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="rfi.quick">
                                                <input type="hidden" name="step" value="submit">
                                                <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                                                <button class="button button-secondary" type="submit">提交</button>
                                            </form>
                                        <?php elseif ($rfi['status'] === 'submitted'): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="rfi.quick">
                                                <input type="hidden" name="step" value="review">
                                                <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                                                <button class="button button-secondary" type="submit">开始审核</button>
                                            </form>
                                        <?php elseif ($rfi['status'] === 'under_review'): ?>
                                            <button class="button button-secondary" type="button" data-dialog-open="rfi-quick-respond-<?= (int) $rfi['rfi_id'] ?>">答复</button>
                                        <?php elseif ($rfi['status'] === 'responded'): ?>
                                            <?php if ($openRfiFollowUps > 0): ?>
                                                <button class="button button-secondary" type="button" disabled title="请先完成该 RFI 的后续事项">关闭</button>
                                            <?php else: ?>
                                                <button class="button button-secondary" type="button" data-dialog-open="rfi-quick-close-<?= (int) $rfi['rfi_id'] ?>">关闭</button>
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
        <p class="empty-state" data-list-empty hidden>没有匹配的 RFI。</p>

        <?php foreach ($rfis as $rfi): ?>
            <?php
            $relatedIssueIds = array_map(static fn (array $issue): int => (int) $issue['issue_id'], $rfi['related_issues']);
            $dueDateValue = \ProjectO\Support::formatDateTime($rfi['due_date'] ?? null, 'Y-m-d\\TH:i');
            ?>
            <dialog class="edit-dialog" id="rfi-dialog-<?= (int) $rfi['rfi_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow"><?= $escape($rfi['rfi_number']) ?> · <?= $escape($statusLabels[$rfi['status']] ?? $rfi['status']) ?></p>
                        <h2>RFI 详情与处理</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <p class="muted">主问题 <?= $escape($rfi['primary_issue_number'] ?: '#' . $rfi['primary_issue_id']) ?> · <?= $escape($rfi['primary_issue_status']) ?>：<?= $escape($rfi['primary_issue_description']) ?></p>
                <?php if ($rfi['related_issues'] !== []): ?>
                    <p><strong>其他关联问题：</strong>
                        <?php foreach ($rfi['related_issues'] as $index => $relatedIssue): ?>
                            <?= $escape($relatedIssue['issue_number'] ?: '#' . $relatedIssue['issue_id']) ?> <?= $escape($relatedIssue['description']) ?><?= $index < count($rfi['related_issues']) - 1 ? '；' : '' ?>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
                <p class="muted">负责人：<?= $escape($rfi['assignee_name']) ?> · 截止：<?= $escape(\ProjectO\Support::formatDateTime($rfi['due_date'])) ?> · 创建：<?= $escape(\ProjectO\Support::formatDateTime($rfi['created_at'])) ?></p>

                <?php if ($rfi['response_text']): ?>
                    <section class="record-tools">
                        <h3>正式答复</h3>
                        <p><?= nl2br($escape($rfi['response_text'])) ?></p>
                        <p class="muted">完整性已确认：<?= (int) $rfi['response_complete'] === 1 ? '是' : '否' ?></p>
                    </section>
                <?php endif; ?>

                <?php if ($canEdit && $rfi['status'] !== 'closed'): ?>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="rfi.update">
                        <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                        <?php if ($rfi['status'] === 'draft'): ?>
                            <label class="field-wide">
                                主问题
                                <select name="primary_issue_id" required>
                                    <?php foreach ($issues as $issue): ?>
                                        <option value="<?= (int) $issue['issue_id'] ?>" <?= (int) $rfi['primary_issue_id'] === (int) $issue['issue_id'] ? 'selected' : '' ?>><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">
                                其他相关问题
                                <select name="related_issue_ids[]" multiple size="4">
                                    <?php foreach ($issues as $issue): ?>
                                        <option value="<?= (int) $issue['issue_id'] ?>" <?= in_array((int) $issue['issue_id'], $relatedIssueIds, true) ? 'selected' : '' ?>>#<?= (int) $issue['issue_id'] ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <label>
                            负责人
                            <select name="assigned_to" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= (int) $user['user_id'] ?>" <?= (int) $rfi['assigned_to'] === (int) $user['user_id'] ? 'selected' : '' ?>><?= $escape($user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            优先级
                            <select name="priority" required>
                                <?php foreach ($priorityLabels as $value => $label): ?>
                                    <option value="<?= $escape($value) ?>" <?= $rfi['priority'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>答复截止时间<input type="datetime-local" name="due_date" value="<?= $escape($dueDateValue) ?>" required></label>
                        <label class="field-wide">澄清问题<textarea name="description" rows="3" maxlength="10000" required><?= $escape($rfi['description']) ?></textarea></label>
                        <label class="checkbox-field field-wide">
                            <input type="checkbox" name="evidence_required" value="1" <?= (int) $rfi['evidence_required'] === 1 ? 'checked' : '' ?>>
                            关闭前必须填写证据档案引用
                        </label>
                        <div class="field-wide"><button class="button button-primary" type="submit">保存编辑</button></div>
                    </form>
                    <?php if ($rfi['status'] !== 'draft'): ?>
                        <p class="muted">RFI 提交后，主问题及其他关联问题不可编辑；如需更正请按项目流程另行处理。</p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($canEdit && $rfi['available_transitions'] !== []): ?>
                    <details class="record-tools">
                        <summary>更新状态 / 答复 / 关闭</summary>
                        <p class="muted record-meta">流程为「草稿 → 已提交 → 审核中 → 已答复 → 已关闭」：只有进入「已答复」后才会出现「已关闭」。关闭前须完成全部后续事项；需要证据的 RFI 还须填写证据档案引用。关闭最后一份关联 RFI 后，其问题会自动转为「待关闭」；重新打开已关闭 RFI 会把「待关闭」的问题退回「处理中」。</p>
                        <form method="post" class="form-grid record-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="rfi.transition">
                            <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                            <label>
                                转换至
                                <select name="status" required>
                                    <?php foreach ($rfi['available_transitions'] as $status): ?>
                                        <option value="<?= $escape($status) ?>"><?= $escape($statusLabels[$status] ?? $status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">正式答复（提交答复时必填）
                                <textarea name="response_text" rows="3" maxlength="10000"><?= $escape($rfi['response_text'] ?? '') ?></textarea>
                            </label>
                            <label class="checkbox-field field-wide">
                                <input type="checkbox" name="response_complete" value="1" <?= (int) $rfi['response_complete'] === 1 ? 'checked' : '' ?>>
                                已确认答复完整
                            </label>
                            <label class="field-wide">状态关联证据档案引用
                                <input name="evidence_reference" maxlength="1000" placeholder="关闭前必填">
                            </label>
                            <label class="field-wide">状态变更原因
                                <textarea name="reason" rows="2" maxlength="2000" required></textarea>
                            </label>
                            <div class="field-wide"><button class="button button-primary" type="submit">保存状态</button></div>
                        </form>
                    </details>
                <?php endif; ?>

                <?php if ($canEdit && $rfi['status'] !== 'closed'): ?>
                    <details class="record-tools">
                        <summary>后续事项（<?= count($rfi['follow_ups']) ?>）</summary>
                        <?php if ($rfi['follow_ups'] !== []): ?>
                            <ul class="follow-up-list">
                                <?php foreach ($rfi['follow_ups'] as $followUp): ?>
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
                                                <input type="hidden" name="action" value="rfi.followup.complete">
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
                            <input type="hidden" name="action" value="rfi.followup.create">
                            <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                            <label>新增后续事项<input name="title" maxlength="255" required></label>
                            <button class="button button-secondary" type="submit">添加</button>
                        </form>
                    </details>
                <?php endif; ?>

                <details class="record-tools">
                    <summary>图片附件（<?= count($rfi['images']) ?>）</summary>
                    <?php if ($rfi['images'] !== []): ?>
                        <ul class="dialog-images">
                            <?php foreach ($rfi['images'] as $image): ?>
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
                                            <input type="hidden" name="action" value="rfi.image.delete">
                                            <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
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
                                <input type="hidden" name="action" value="rfi.image.upload">
                                <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
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
                    <summary>状态历史（<?= count($rfi['status_history']) ?>）</summary>
                    <?php if ($rfi['status_history'] === []): ?>
                        <p class="muted">暂无状态历史。</p>
                    <?php else: ?>
                        <ol class="history-list">
                            <?php foreach ($rfi['status_history'] as $event): ?>
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

            <dialog class="edit-dialog is-compact" id="rfi-quick-respond-<?= (int) $rfi['rfi_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow"><?= $escape($rfi['rfi_number']) ?> · 快捷操作</p>
                        <h2>提交正式答复</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="rfi.quick">
                    <input type="hidden" name="step" value="respond">
                    <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                    <label class="field-wide">
                        正式答复
                        <textarea name="response_text" rows="4" maxlength="10000" required><?= $escape($rfi['response_text'] ?? '') ?></textarea>
                    </label>
                    <label class="field-wide">
                        证据档案引用（可选）
                        <input name="evidence_reference" maxlength="1000" value="<?= $escape($rfi['evidence_reference'] ?? '') ?>">
                    </label>
                    <label class="checkbox-field field-wide">
                        <input type="checkbox" name="response_complete" value="1" required>
                        已确认答复完整
                    </label>
                    <div class="field-wide dialog-actions">
                        <button class="button button-primary" type="submit">提交答复</button>
                    </div>
                </form>
            </dialog>

            <dialog class="edit-dialog is-compact" id="rfi-quick-close-<?= (int) $rfi['rfi_id'] ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow"><?= $escape($rfi['rfi_number']) ?> · 快捷操作</p>
                        <h2>关闭 RFI</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="rfi.quick">
                    <input type="hidden" name="step" value="close">
                    <input type="hidden" name="rfi_id" value="<?= (int) $rfi['rfi_id'] ?>">
                    <label class="field-wide">
                        证据档案引用<?= (int) $rfi['evidence_required'] === 1 ? '（必填）' : '（可选）' ?>
                        <input name="evidence_reference" maxlength="1000"<?= (int) $rfi['evidence_required'] === 1 ? ' required' : '' ?> value="<?= $escape($rfi['evidence_reference'] ?? '') ?>">
                    </label>
                    <p class="muted field-wide record-meta">关闭后该 RFI 收口；若这是主问题最后一份未关闭 RFI，问题会转为待关闭。</p>
                    <div class="field-wide dialog-actions">
                        <button class="button button-primary" type="submit">确认关闭</button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

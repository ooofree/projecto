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
</section>
<nav class="module-nav" aria-label="文档管理">
    <a class="button button-secondary" href="?page=documents">文档管理首页</a>
    <a class="button button-secondary" href="?page=issues">问题管理</a>
</nav>

<?php if ($canCreate): ?>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>创建 RFI 草稿</h2>
                <p class="muted">RFI 不会关闭或自动改变关联问题；答复后请重新评估主问题。</p>
            </div>
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
                            <option value="<?= (int) $issue['issue_id'] ?>">
                                #<?= (int) $issue['issue_id'] ?> · <?= $escape($issue['status']) ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">
                    其他相关问题（可多选）
                    <select name="related_issue_ids[]" multiple size="4">
                        <?php foreach ($issues as $issue): ?>
                            <option value="<?= (int) $issue['issue_id'] ?>">
                                #<?= (int) $issue['issue_id'] ?> · <?= $escape(mb_substr($issue['description'], 0, 100)) ?>
                            </option>
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
                        <option value="low">低</option>
                        <option value="medium" selected>中</option>
                        <option value="high">高</option>
                        <option value="urgent">紧急</option>
                    </select>
                </label>
                <label>
                    答复截止时间
                    <input type="datetime-local" name="due_date" required>
                </label>
                <label class="field-wide">
                    澄清问题
                    <textarea name="description" rows="3" maxlength="10000" required></textarea>
                </label>
                <label class="checkbox-field field-wide">
                    <input type="checkbox" name="evidence_required" value="1">
                    关闭前必须填写证据档案引用
                </label>
                <div class="field-wide">
                    <button class="button button-primary" type="submit">创建草稿</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>RFI 记录</h2>
        <span class="muted"><?= count($rfis) ?> 份</span>
    </div>
    <?php if ($rfis === []): ?>
        <p class="empty-state">当前项目还没有 RFI。</p>
    <?php else: ?>
        <div class="record-list">
            <?php foreach ($rfis as $rfi): ?>
                <article class="record-card" id="rfi-<?= (int) $rfi['rfi_id'] ?>">
                    <div class="record-heading">
                        <div>
                            <p class="eyebrow"><?= $escape($rfi['rfi_number']) ?></p>
                            <h3><?= $escape($rfi['description']) ?></h3>
                        </div>
                        <div class="record-badges">
                            <span class="badge"><?= $escape($statusLabels[$rfi['status']] ?? $rfi['status']) ?></span>
                            <span class="badge badge-neutral"><?= $escape($priorityLabels[$rfi['priority']] ?? $rfi['priority']) ?></span>
                        </div>
                    </div>
                    <p class="muted record-meta">
                        主问题 #<?= (int) $rfi['primary_issue_id'] ?> · <?= $escape($rfi['primary_issue_status']) ?>：
                        <?= $escape($rfi['primary_issue_description']) ?>
                        · 负责人：<?= $escape($rfi['assignee_name']) ?>
                        <?php if ($rfi['due_date']): ?> · 截止：<?= $escape($rfi['due_date']) ?><?php endif; ?>
                    </p>
                    <?php if ($rfi['related_issues'] !== []): ?>
                        <p><strong>其他关联问题：</strong>
                            <?php foreach ($rfi['related_issues'] as $relatedIndex => $relatedIssue): ?>
                                #<?= (int) $relatedIssue['issue_id'] ?> (<?= $escape($relatedIssue['status']) ?>)
                                <?= $escape($relatedIssue['description']) ?>
                                <?= $relatedIndex < count($rfi['related_issues']) - 1 ? '；' : '' ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($rfi['response_text']): ?>
                        <p><strong>正式答复：</strong><?= nl2br($escape($rfi['response_text'])) ?></p>
                        <p class="muted">完整性已确认：<?= (int) $rfi['response_complete'] === 1 ? '是' : '否' ?></p>
                    <?php endif; ?>

                    <?php if ($canEdit && $rfi['available_transitions'] !== []): ?>
                        <details class="record-tools">
                            <summary>更新状态 / 答复 / 关闭</summary>
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
                                <label class="field-wide">
                                    正式答复（提交答复时必填）
                                    <textarea name="response_text" rows="3" maxlength="10000"></textarea>
                                </label>
                                <label class="checkbox-field field-wide">
                                    <input type="checkbox" name="response_complete" value="1">
                                    已确认答复完整
                                </label>
                                <label class="field-wide">
                                    状态关联证据档案引用
                                    <input name="evidence_reference" maxlength="1000" placeholder="此 RFI 标记需要证据时，关闭前必填">
                                </label>
                                <label class="field-wide">
                                    状态变更原因
                                    <textarea name="reason" rows="2" maxlength="2000" required></textarea>
                                </label>
                                <div class="field-wide">
                                    <button class="button button-primary" type="submit">保存状态</button>
                                </div>
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
                                                <span class="<?= (int) $followUp['is_completed'] === 1 ? 'is-complete' : '' ?>">
                                                    <?= $escape($followUp['title']) ?>
                                                </span>
                                                <?php foreach ($followUp['status_history'] as $event): ?>
                                                    <span class="muted record-meta">
                                                        <?= $escape($event['changed_at']) ?> · <?= $escape($event['old_status']) ?> → <?= $escape($event['new_status']) ?>
                                                        · <?= $escape($event['reason']) ?>
                                                        <?php if ($event['evidence_reference']): ?> · 档案：<?= $escape($event['evidence_reference']) ?><?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if ((int) $followUp['is_completed'] !== 1): ?>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="rfi.followup.complete">
                                                    <input type="hidden" name="follow_up_id" value="<?= (int) $followUp['follow_up_id'] ?>">
                                                    <label>
                                                        完成原因
                                                        <input name="reason" maxlength="2000" required>
                                                    </label>
                                                    <label>
                                                        档案引用（可选）
                                                        <input name="evidence_reference" maxlength="1000">
                                                    </label>
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
                                <label>
                                    新增后续事项
                                    <input name="title" maxlength="255" required>
                                </label>
                                <button class="button button-secondary" type="submit">添加</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <details class="record-tools">
                        <summary>状态历史（<?= count($rfi['status_history']) ?>）</summary>
                        <?php if ($rfi['status_history'] === []): ?>
                            <p class="muted">暂无状态历史。</p>
                        <?php else: ?>
                            <ol class="history-list">
                                <?php foreach ($rfi['status_history'] as $event): ?>
                                    <li>
                                        <strong><?= $escape($event['old_status'] ?? '新建') ?> → <?= $escape($event['new_status']) ?></strong>
                                        <span><?= $escape($event['changed_at']) ?> · 操作人 #<?= (int) $event['changed_by'] ?></span>
                                        <p><?= nl2br($escape($event['reason'])) ?></p>
                                        <?php if ($event['evidence_reference']): ?>
                                            <p>证据档案：<?= $escape($event['evidence_reference']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

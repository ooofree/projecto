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
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">文档管理 · 问题追踪</p>
        <h1>问题管理</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。RFI 答复不会自动改变问题状态。</p>
    </div>
</section>
<nav class="module-nav" aria-label="文档管理">
    <a class="button button-secondary" href="?page=documents">文档管理首页</a>
    <a class="button button-secondary" href="?page=rfis">RFI 管理</a>
</nav>

<?php if ($canCreate): ?>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>登记问题</h2>
                <p class="muted">问题是现场问题、图纸冲突和设计疑问的统一入口。</p>
            </div>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="issue.create">
            <label class="field-wide">
                问题描述
                <textarea name="description" rows="3" maxlength="10000" required></textarea>
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
            <label class="field-wide">
                位置（可选）
                <input name="location" maxlength="255">
            </label>
            <div class="field-wide">
                <button class="button button-primary" type="submit">登记问题</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>问题记录</h2>
        <span class="muted"><?= count($issues) ?> 条</span>
    </div>
    <?php if ($issues === []): ?>
        <p class="empty-state">当前项目还没有问题记录。</p>
    <?php else: ?>
        <div class="record-list">
            <?php foreach ($issues as $issue): ?>
                <article class="record-card" id="issue-<?= (int) $issue['issue_id'] ?>">
                    <div class="record-heading">
                        <div>
                            <p class="eyebrow">问题 #<?= (int) $issue['issue_id'] ?></p>
                            <h3><?= $escape($issue['description']) ?></h3>
                        </div>
                        <div class="record-badges">
                            <span class="badge"><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span>
                            <span class="badge badge-neutral"><?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span>
                        </div>
                    </div>
                    <p class="muted record-meta">
                        登记时间：<?= $escape($issue['reported_at']) ?>
                        <?php if ($issue['location'] !== null && $issue['location'] !== ''): ?>
                            · 位置：<?= $escape($issue['location']) ?>
                        <?php endif; ?>
                        · 登记人编号：<?= (int) $issue['reported_by'] ?>
                    </p>
                    <?php if ($issue['resolution_result']): ?>
                        <p><strong>处理结果：</strong><?= nl2br($escape($issue['resolution_result'])) ?></p>
                    <?php endif; ?>

                    <?php if ($canEdit && $issue['available_transitions'] !== []): ?>
                        <details class="record-tools">
                            <summary>更新状态与关闭校验</summary>
                            <form method="post" class="form-grid record-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="issue.transition">
                                <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                                <label>
                                    转换至
                                    <select name="status" required>
                                        <?php foreach ($issue['available_transitions'] as $status): ?>
                                            <option value="<?= $escape($status) ?>"><?= $escape($statusLabels[$status] ?? $status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="field-wide">
                                    状态变更原因
                                    <textarea name="reason" rows="2" maxlength="2000" required></textarea>
                                </label>
                                <label class="field-wide">
                                    处理结果（进入“待关闭”时必填）
                                    <textarea name="resolution_result" rows="2" maxlength="10000"></textarea>
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
                                    <input name="verification_note" maxlength="2000">
                                </label>
                                <label class="field-wide">
                                    状态关联证据档案引用（关闭时必填）
                                    <input name="evidence_reference" maxlength="1000" placeholder="受控档案系统中的编号或链接">
                                </label>
                                <div class="field-wide">
                                    <button class="button button-primary" type="submit">保存状态</button>
                                </div>
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
                                                    <input type="hidden" name="action" value="issue.followup.complete">
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
                                <input type="hidden" name="action" value="issue.followup.create">
                                <input type="hidden" name="issue_id" value="<?= (int) $issue['issue_id'] ?>">
                                <label>
                                    新增後续事项
                                    <input name="title" maxlength="255" required>
                                </label>
                                <button class="button button-secondary" type="submit">添加</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <details class="record-tools">
                        <summary>状态历史（<?= count($issue['status_history']) ?>）</summary>
                        <?php if ($issue['status_history'] === []): ?>
                            <p class="muted">暂无状态历史。</p>
                        <?php else: ?>
                            <ol class="history-list">
                                <?php foreach ($issue['status_history'] as $event): ?>
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

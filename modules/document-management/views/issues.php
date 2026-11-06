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
    <?php if ($canCreate): ?>
        <button class="button button-primary" type="button" data-dialog-open="issue-create-dialog">登记问题</button>
    <?php endif; ?>
</section>
<nav class="module-nav" aria-label="文档管理">
    <a class="button button-secondary" href="?page=projects">项目首页</a>
    <a class="button button-secondary" href="?page=documents">文档管理首页</a>
    <a class="button button-primary" href="?page=issues" aria-current="page">问题管理</a>
    <a class="button button-secondary" href="?page=rfis">RFI 管理</a>
    <?php if (!empty($canManageSettings)): ?>
        <a class="button button-secondary" href="?page=document-settings">系统设置</a>
    <?php endif; ?>
</nav>
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
            <label class="field-wide">
                位置（可选）
                <input name="location" maxlength="255">
            </label>
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
            <span class="muted" data-list-count data-unit=" 条"><?= count($issues) ?> 条</span>
        </div>
        <label class="search-field">
            <span class="sr-only">搜索问题</span>
            <input type="search" placeholder="搜索问题…" data-list-search="issues-table">
        </label>
    </div>
    <div class="toolbar">
        <form method="post" action="">
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
                            $issue['location'] ?? '',
                            $statusLabels[$issue['status']] ?? $issue['status'],
                            $severityLabels[$issue['severity']] ?? $issue['severity'],
                        ])); ?>
                        <tr data-list-item data-search="<?= $escape($searchText) ?>">
                            <td><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?></td>
                            <td class="table-primary-cell"><?= $escape(mb_strimwidth($issue['description'], 0, 100, '…')) ?></td>
                            <td><span class="badge badge-neutral"><?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span></td>
                            <td><span class="badge"><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span></td>
                            <td><?= $escape($issue['location'] ?: '—') ?></td>
                            <td><?= count($issue['images']) ?></td>
                            <td><?= $escape(\ProjectO\Support::formatDateTime($issue['updated_at'])) ?></td>
                            <td><button class="button button-secondary" type="button" data-dialog-open="issue-dialog-<?= (int) $issue['issue_id'] ?>">查看 / 编辑</button></td>
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
                        <label>
                            位置
                            <input name="location" maxlength="255" value="<?= $escape($issue['location'] ?? '') ?>">
                        </label>
                        <div class="field-wide dialog-actions">
                            <button class="button button-primary" type="submit">保存编辑</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p><?= nl2br($escape($issue['description'])) ?></p>
                    <p>严重程度：<?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?> · 位置：<?= $escape($issue['location'] ?: '—') ?></p>
                <?php endif; ?>

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
                                    <strong><?= $escape($event['old_status'] ?? '新建') ?> → <?= $escape($event['new_status']) ?></strong>
                                    <span><?= $escape(\ProjectO\Support::formatDateTime($event['changed_at'])) ?> · 操作人 #<?= (int) $event['changed_by'] ?></span>
                                    <p><?= nl2br($escape($event['reason'])) ?></p>
                                    <?php if ($event['evidence_reference']): ?><p>证据档案：<?= $escape($event['evidence_reference']) ?></p><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </details>
            </dialog>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;
use ProjectO\Support;

$progress = $detail['progress'] ?? [];
$acceptances = $detail['acceptances'] ?? [];
$images = $detail['images'] ?? [];
$imageUrls = $detail['imageUrls'] ?? [];
$relations = $detail['relations'] ?? ['parents' => [], 'children' => []];
$storageReady = (bool) ($detail['storageReady'] ?? false);
$storageStatus = $detail['storageStatus'] ?? null;

$status = (string) $item['status'];
$isOpen = in_array($status, CloseoutLabels::OPEN_STATUSES, true);
$canEditItem = $canEdit && $isOpen;
$displayName = (string) ($item['display_name'] ?? $item['material_name'] ?? $item['title'] ?? '');
$parentNames = array_map(static fn (array $row): string => (string) $row['material_name'], $relations['parents'] ?? []);
$childNames = array_map(static fn (array $row): string => (string) $row['material_name'], $relations['children'] ?? []);
$closeUrl = $closeUrl ?? '?page=closeouts';
$standalone = (bool) ($standalone ?? false);
$submittedDate = $item['submitted_at'] !== null && $item['submitted_at'] !== ''
    ? Support::formatDateTime((string) $item['submitted_at'], 'Y-m-d')
    : (string) $today;
$show = static function ($value, string $fallback = '—') use ($escape): string {
    $text = trim((string) ($value ?? ''));

    return $escape($text === '' ? $fallback : $text);
};
?>
<div class="closeout-detail">
    <div class="closeout-detail-head">
        <div>
            <p class="eyebrow">事项 <?= $escape((string) $item['item_no']) ?></p>
            <h2><?= $escape($displayName) ?></h2>
            <p class="muted">
                <?= $escape($item['material_name'] ?? '—') ?>
                <?php if (!empty($item['specifications'])): ?> · <?= $escape((string) $item['specifications']) ?><?php endif; ?>
                <?php if (!empty($item['unit'])): ?> · <?= $escape((string) $item['unit']) ?><?php endif; ?>
                · <?= $escape((string) $item['plan_no']) ?>
            </p>
        </div>
        <a class="button button-quiet button-small" href="<?= $escape($closeUrl) ?>"><?= $standalone ? '返回工作台' : '收起' ?></a>
    </div>

    <div class="closeout-detail-badges">
        <span class="status-badge <?= $escape(CloseoutLabels::itemTone($status)) ?>"><?= $escape($item['status_label']) ?></span>
        <span class="status-badge <?= $escape(CloseoutLabels::riskTone((string) $item['risk_level'])) ?>">风险 <?= $escape($item['risk_label']) ?></span>
        <?php if (!empty($item['is_overdue'])): ?><span class="status-badge is-red">已逾期</span><?php endif; ?>
        <span class="status-badge is-neutral"><?= $escape(CloseoutLabels::COVERAGE_MODE[$item['coverage_mode']] ?? (string) $item['coverage_mode']) ?></span>
        <?php if ((int) $item['responsible_user_id'] === 0): ?><span class="status-badge is-amber">未指派责任人</span><?php endif; ?>
    </div>

    <div class="closeout-detail-section">
        <h3 class="closeout-section-title">下一步</h3>
        <?php if ($status === 'pending' && $canEdit): ?>
            <form method="post" class="closeout-quick">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="closeout.item.start">
                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                <input type="hidden" name="status" value="<?= $escape((string) ($filters['status'] ?? '')) ?>">
                <button class="button button-primary closeout-wide" type="submit">开始执行</button>
            </form>
        <?php elseif ($status === 'ongoing' && $canEdit): ?>
            <form method="post" class="closeout-quick-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="closeout.item.submit">
                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                <input type="hidden" name="status" value="<?= $escape((string) ($filters['status'] ?? '')) ?>">
                <label class="field-wide">本次完成内容（可选）<input name="progress_note" maxlength="5000" placeholder="例如：门框收口完成，等待五金"></label>
                <button class="button button-primary closeout-wide" type="submit">提交验收</button>
            </form>
        <?php elseif ($status === 'pending_accept'): ?>
            <?php if ($canAccept): ?>
                <div class="closeout-quick-row">
                    <form method="post" class="inline-form" data-confirm="确认验收通过？实际完成日期记为今天。">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="closeout.item.accept">
                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                        <input type="hidden" name="result" value="passed">
                        <input type="hidden" name="status" value="<?= $escape((string) ($filters['status'] ?? '')) ?>">
                        <button class="button button-primary" type="submit">验收通过（今天）</button>
                    </form>
                    <a class="button button-secondary" href="#closeout-accept-form">填写意见 / 退回</a>
                </div>
                <p class="muted">通过即记今天完成；要改完成日期、写验收意见或退回，用下方验收表单。</p>
            <?php else: ?>
                <p class="muted">已提交验收，等待有验收权限的人员处理。</p>
            <?php endif; ?>
        <?php elseif ($status === 'closed'): ?>
            <p class="closeout-note is-success">已消项，实际完成 <?= $show($item['actual_complete_date']) ?>。</p>
            <?php if ($canManage): ?>
                <details class="closeout-inline-details">
                    <summary>重新打开（需原因）</summary>
                    <form method="post" class="closeout-quick-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="closeout.item.reopen">
                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                        <label class="field-wide">重新打开原因<textarea name="reopen_reason" rows="2" maxlength="5000" required placeholder="例如：验收后发现收口不严，需要整改"></textarea></label>
                        <button class="button button-secondary" type="submit">重新打开</button>
                    </form>
                </details>
            <?php endif; ?>
        <?php elseif ($status === 'cancelled'): ?>
            <div class="notice notice-warning">已取消：<?= $escape((string) ($item['cancel_reason'] ?? '—')) ?>（<?= $escape($item['cancelled_by_name'] ?? '—') ?> · <?= $escape(Support::formatDateTime($item['cancelled_at'] ?? null)) ?>）</div>
            <?php if ($canManage): ?>
                <details class="closeout-inline-details">
                    <summary>恢复事项（需原因）</summary>
                    <form method="post" class="closeout-quick-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="closeout.item.restore">
                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                        <label class="field-wide">恢复原因<textarea name="restore_reason" rows="2" maxlength="5000" required placeholder="例如：现场复核后仍需纳入消项"></textarea></label>
                        <button class="button button-secondary" type="submit">恢复事项</button>
                    </form>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($canManage && $isOpen): ?>
            <details class="closeout-inline-details">
                <summary>取消事项（需原因）</summary>
                <form method="post" class="closeout-quick-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="closeout.item.cancel">
                    <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                    <label class="field-wide">取消原因<textarea name="cancel_reason" rows="2" maxlength="5000" required placeholder="例如：该材料改为甲供，不再纳入本次消项范围"></textarea></label>
                    <button class="button button-danger" type="submit">取消事项</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if (!$canEdit && !$canManage && !$canAccept): ?>
            <p class="muted">当前账号没有维护或管理该事项的权限。</p>
        <?php endif; ?>
    </div>

    <?php if ($status === 'pending_accept' && $canAccept): ?>
        <div class="closeout-detail-section" id="closeout-accept-form">
            <h3 class="closeout-section-title">验收结论</h3>
            <form method="post" class="closeout-accept-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="closeout.item.accept">
                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                <div class="form-grid">
                    <label>结论
                        <select name="result" required>
                            <option value="passed">通过</option>
                            <option value="rejected">不通过（退回执行）</option>
                        </select>
                    </label>
                    <label>实际完成日期<input type="date" name="actual_complete_date" value="<?= $escape((string) $today) ?>" min="<?= $escape($submittedDate) ?>"></label>
                    <label class="field-wide">验收意见<textarea name="remark" rows="2" maxlength="5000" placeholder="通过时可选；不通过时必填"></textarea></label>
                    <label class="field-wide">证据引用<input name="evidence_reference" maxlength="1000" placeholder="验收单编号、照片编号、会议纪要链接"></label>
                </div>
                <div class="closeout-plan-actions">
                    <button class="button button-primary" type="submit">提交验收结论</button>
                    <span class="muted">实际完成日期不得早于提交验收日期（<?= $escape($submittedDate) ?>）。</span>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="closeout-detail-section">
        <h3 class="closeout-section-title">基本信息</h3>
        <dl class="closeout-facts">
            <div><dt>材料编号</dt><dd><?= $show($item['material_code']) ?></dd></div>
            <div><dt>分类</dt><dd><?= $show($item['category_name']) ?></dd></div>
            <div><dt>计划周期</dt><dd><?= $show($item['planned_start_date']) ?> 至 <?= $show($item['planned_end_date']) ?></dd></div>
            <div><dt>责任单位 / 人</dt><dd><?= $show($item['supplier_name'], '未指定') ?> / <?= $show($item['responsible_user_name'], '未指定') ?></dd></div>
            <div><dt>提交验收</dt><dd><?= $escape(Support::formatDateTime($item['submitted_at'] ?? null) ?: '—') ?></dd></div>
            <div><dt>实际完成</dt><dd><?= $show($item['actual_complete_date']) ?></dd></div>
        </dl>
        <?php if (!empty($item['description'])): ?>
            <p class="record-meta"><?= nl2br($escape((string) $item['description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($canEditItem): ?>
        <div class="closeout-detail-section">
            <h3 class="closeout-section-title">责任与计划</h3>
            <form method="post" class="closeout-edit-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="closeout.item.update">
                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                <div class="form-grid">
                    <label class="field-wide">自定义标签（留空显示材料名称）<input name="title" maxlength="255" value="<?= $escape((string) $item['title']) ?>" placeholder="例如：门安装收口"></label>
                    <label>计划开始<input type="date" name="planned_start_date" value="<?= $escape((string) ($item['planned_start_date'] ?? '')) ?>"></label>
                    <label>计划完成<input type="date" name="planned_end_date" value="<?= $escape((string) ($item['planned_end_date'] ?? '')) ?>"></label>
                    <label>责任单位
                        <select name="responsible_supplier_id">
                            <option value="">未指定</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= (int) $supplier['supplier_id'] ?>" <?= (int) $item['responsible_supplier_id'] === (int) $supplier['supplier_id'] ? 'selected' : '' ?>><?= $escape($supplier['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>责任人
                        <select name="responsible_user_id">
                            <option value="">未指定</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['user_id'] ?>" <?= (int) $item['responsible_user_id'] === (int) $user['user_id'] ? 'selected' : '' ?>><?= $escape($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>风险等级
                        <select name="risk_level">
                            <?php foreach (CloseoutLabels::RISK_LEVEL as $value => $label): ?>
                                <option value="<?= $escape($value) ?>" <?= $value === $item['risk_level'] ? 'selected' : '' ?>><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>覆盖方式
                        <input value="<?= $escape(CloseoutLabels::COVERAGE_MODE[$item['coverage_mode']] ?? (string) $item['coverage_mode']) ?>" readonly>
                        <input type="hidden" name="coverage_mode" value="<?= $escape((string) $item['coverage_mode']) ?>">
                    </label>
                    <label class="field-wide">说明<textarea name="description" rows="2" maxlength="5000"><?= $escape((string) ($item['description'] ?? '')) ?></textarea></label>
                </div>
                <div class="closeout-plan-actions">
                    <button class="button button-primary" type="submit">保存</button>
                    <span class="muted">材料与计划不可更改；改完直接留在本栏。</span>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="closeout-detail-section">
        <h3 class="closeout-section-title">进度记录 <span class="muted"><?= count($progress) ?> 条</span></h3>
        <?php if ($canEditItem): ?>
            <form method="post" class="closeout-quick-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="closeout.progress.add">
                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                <textarea name="progress_note" rows="2" maxlength="5000" required placeholder="例如：完成 2 樘门安装，等待五金收口"></textarea>
                <button class="button button-secondary" type="submit">记录进度</button>
            </form>
        <?php endif; ?>
        <?php if ($progress === []): ?>
            <p class="muted">还没有进度记录。</p>
        <?php else: ?>
            <ol class="closeout-timeline">
                <?php foreach ($progress as $entry): ?>
                    <li>
                        <strong><?= $escape(CloseoutLabels::PROGRESS_TYPE[$entry['progress_type']] ?? (string) $entry['progress_type']) ?></strong>
                        <span class="muted"><?= $escape($entry['recorded_by_name'] ?? '—') ?> · <?= $escape(Support::formatDateTime($entry['recorded_at'] ?? null)) ?></span>
                        <p><?= nl2br($escape((string) $entry['progress_note'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <div class="closeout-detail-section">
        <h3 class="closeout-section-title">验收记录 <span class="muted"><?= count($acceptances) ?> 次</span></h3>
        <?php if ($acceptances === []): ?>
            <p class="muted">还没有验收记录。</p>
        <?php else: ?>
            <ul class="closeout-acceptances">
                <?php foreach ($acceptances as $acceptance): ?>
                    <li>
                        <div>
                            <span class="status-badge <?= $acceptance['result'] === 'passed' ? 'is-green' : 'is-red' ?>"><?= $acceptance['result'] === 'passed' ? '通过' : '不通过' ?></span>
                            <span class="muted"><?= $escape($acceptance['accepted_by_name'] ?? '—') ?> · <?= $escape(Support::formatDateTime($acceptance['accepted_at'] ?? null)) ?></span>
                        </div>
                        <?php if (!empty($acceptance['remark'])): ?><p><?= nl2br($escape((string) $acceptance['remark'])) ?></p><?php endif; ?>
                        <?php if (!empty($acceptance['evidence_reference'])): ?><span class="muted">证据：<?= $escape((string) $acceptance['evidence_reference']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="closeout-detail-section">
        <h3 class="closeout-section-title">附件照片 <span class="muted"><?= count($images) ?> 张</span></h3>
        <?php if (!$storageReady): ?>
            <?php if (($storageStatus['sdk_available'] ?? true) === false): ?>
                <div class="notice notice-info">服务器尚未安装 AWS SDK；R2 附件照片暂不可上传或查看。</div>
            <?php else: ?>
                <div class="notice notice-info">R2 图片存储尚未配置；附件照片暂不可上传或查看。<?= !empty($canManageSettings) ? '前往文件模块“系统设置”统一配置。' : '' ?></div>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($images === []): ?>
                <p class="muted">还没有附件照片。</p>
            <?php else: ?>
                <div class="closeout-photo-grid">
                    <?php foreach ($images as $image): ?>
                        <figure>
                            <a href="<?= $escape($imageUrls[(int) $image['image_id']] ?? '') ?>" target="_blank" rel="noopener">
                                <img src="<?= $escape($imageUrls[(int) $image['image_id']] ?? '') ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy">
                            </a>
                            <figcaption>
                                <span title="<?= $escape($image['original_name']) ?>"><?= $escape($image['original_name']) ?></span>
                                <?php if ($canEdit && $status !== 'cancelled'): ?>
                                    <form method="post" class="inline-form" data-confirm="删除后照片无法恢复，确定删除吗？">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="closeout.image.delete">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <input type="hidden" name="image_id" value="<?= (int) $image['image_id'] ?>">
                                        <button class="linkbtn" type="submit">删除</button>
                                    </form>
                                <?php endif; ?>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canEdit && $status !== 'cancelled'): ?>
                <form method="post" enctype="multipart/form-data" class="closeout-upload-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="closeout.image.upload">
                    <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                    <label class="field-wide">上传照片（单张 ≤ 5 MB，可多选）<input type="file" name="image[]" accept="image/png,image/jpeg,image/gif,image/bmp,image/webp" multiple required></label>
                    <button class="button button-secondary button-small" type="submit">上传附件</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <details class="closeout-inline-details closeout-bom">
        <summary>BOM 组成 <span class="muted">每次打开实时读取，不保存快照</span></summary>
        <p><strong>上级材料：</strong><?= $escape($parentNames === [] ? '—' : implode('、', $parentNames)) ?></p>
        <p><strong>下级材料：</strong><?= $escape($childNames === [] ? '—' : implode('、', $childNames)) ?></p>
    </details>

    <p class="closeout-footnote">消项只记录工程收口、责任与验收证据，不核销 BOM 数量，也不回写 BOM 状态。</p>
</div>

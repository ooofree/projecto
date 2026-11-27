<?php
/**
 * Shared space (location) list for one closeout item.
 *
 * Expects: $locationItem, $locationRows, $escape, $csrfToken, $canEdit, $canAccept, $canManage.
 * Ticking a space checkbox = submit for acceptance; acceptance stays a separate
 * permission-guarded action. Nothing here is drag/fill: percentages are derived.
 */
$statusTone = static function (string $status): string {
    return match ($status) {
        'closed' => 'is-green',
        'pending_accept' => 'is-blue',
        'ongoing' => 'is-amber',
        'cancelled' => 'is-grey',
        default => 'is-grey',
    };
};
?>
<div class="closeout-locations">
    <?php if ($locationRows === []): ?>
        <p class="muted closeout-locations-empty">
            这条事项没有空间子项（旧数据或项目尚未录入该父材料的测算）。补录测算后点「同步新增父材料」即可补齐。
        </p>
    <?php else: ?>
        <table class="closeout-locations-table">
            <thead>
                <tr>
                    <th>空间</th>
                    <th>基线量</th>
                    <th>状态</th>
                    <th>计划完成 / 申报</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($locationRows as $loc): ?>
                <?php
                $locStatus = (string) $loc['status'];
                $locBase = rtrim(rtrim(number_format((float) $loc['baseline_qty'], 4, '.', ''), '0'), '.');
                ?>
                <tr class="closeout-location-row<?= !empty($loc['is_overdue']) ? ' is-overdue' : '' ?>">
                    <td>
                        <span class="closeout-location-space"><?= $escape((string) ($loc['space_label'] ?? '—')) ?></span>
                        <?php if (!empty($loc['is_overdue'])): ?><span class="status-badge is-red">逾期</span><?php endif; ?>
                    </td>
                    <td><span class="closeout-location-qty"><?= $escape($locBase) ?></span></td>
                    <td><span class="status-badge <?= $escape($statusTone($locStatus)) ?>"><?= $escape((string) $loc['status_label']) ?></span></td>
                    <td>
                        <span class="closeout-date"><?= $escape((string) ($loc['planned_end_date'] ?? '未排期')) ?></span>
                        <?php if (!empty($loc['submitted_at'])): ?><span class="closeout-item-meta">申报 <?= $escape((string) $loc['submitted_at']) ?></span><?php endif; ?>
                        <?php if (!empty($loc['accepted_at'])): ?><span class="closeout-item-meta">验收 <?= $escape((string) $loc['accepted_at']) ?><?= !empty($loc['accepted_by_name']) ? ' · ' . $escape((string) $loc['accepted_by_name']) : '' ?></span><?php endif; ?>
                    </td>
                    <td>
                        <div class="closeout-location-actions">
                        <?php if ($locStatus === 'closed'): ?>
                            <span class="closeout-item-meta">已完成，不再改动</span>
                        <?php elseif ($locStatus === 'cancelled'): ?>
                            <span class="closeout-item-meta">已退出：<?= $escape((string) ($loc['cancel_reason'] ?? '—')) ?></span>
                        <?php elseif (in_array($locStatus, ['pending', 'ongoing'], true) && $canEdit): ?>
                            <form method="post" class="inline-form" data-closeout-location-form>
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="closeout.location.submit">
                                <input type="hidden" name="location_id" value="<?= (int) $loc['location_id'] ?>">
                                <label class="closeout-loc-check">
                                    <input type="checkbox" data-closeout-location-check aria-label="将该空间提交验收">
                                    <span>完成申报</span>
                                </label>
                            </form>
                        <?php elseif ($locStatus === 'pending_accept' && $canAccept): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="closeout.location.accept">
                                <input type="hidden" name="location_id" value="<?= (int) $loc['location_id'] ?>">
                                <input type="hidden" name="result" value="passed">
                                <button class="button button-primary button-small" type="submit">通过</button>
                            </form>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="closeout.location.accept">
                                <input type="hidden" name="location_id" value="<?= (int) $loc['location_id'] ?>">
                                <input type="hidden" name="result" value="rejected">
                                <input type="text" name="remark" placeholder="驳回意见（必填）" maxlength="200" class="closeout-loc-remark">
                                <button class="button button-quiet button-small" type="submit">驳回</button>
                            </form>
                        <?php elseif ($locStatus === 'pending_accept'): ?>
                            <span class="closeout-item-meta">待验收，无验收权限</span>
                        <?php else: ?>
                            <span class="closeout-item-meta">—</span>
                        <?php endif; ?>
                        <?php if ($canManage && !in_array($locStatus, ['closed', 'cancelled'], true)): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="closeout.location.cancel">
                                <input type="hidden" name="location_id" value="<?= (int) $loc['location_id'] ?>">
                                <input type="text" name="cancel_reason" placeholder="退出原因（必填）" maxlength="200" class="closeout-loc-remark">
                                <button class="button button-quiet button-small" type="submit">放弃</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

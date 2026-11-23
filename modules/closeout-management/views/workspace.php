<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;
use ProjectO\Support;

$statusCounts = $stats['counts'];
$total = (int) $stats['total'];
$closed = (int) $statusCounts['closed'];
$cancelled = (int) $statusCounts['cancelled'];
$effective = max(0, $total - $cancelled);
$rate = $stats['completion_rate'];
$rateText = $rate === null ? '—' : number_format((float) $rate, 1) . '%';
$planId = (int) $filters['plan_id'];
$status = (string) $filters['status'];
$risk = (string) $filters['risk_level'];
$responsible = (int) $filters['responsible_user_id'];
$overdue = (int) $filters['overdue'];
$selectedItemId = $selectedItem !== null ? (int) $selectedItem['item_id'] : 0;
$statusKeys = array_keys(CloseoutLabels::ITEM_STATUS);
$totalAll = 0;
$closedAll = 0;
$cancelledAll = 0;
foreach ($plans as $planRow) {
    $totalAll += (int) $planRow['item_count'];
    $closedAll += (int) $planRow['closed_count'];
    $cancelledAll += (int) ($planRow['counts']['cancelled'] ?? 0);
}
$effectiveAll = max(0, $totalAll - $cancelledAll);
$rateAll = $effectiveAll > 0 ? $closedAll / $effectiveAll * 100 : null;

/**
 * Same-page URL builder: every filter change, plan switch and item selection
 * stays on ?page=closeouts. plan_id=0 is explicit ("all plans") and therefore
 * survives the empty-value cleanup.
 */
$buildUrl = static function (array $changes = []) use ($planId, $status, $risk, $responsible, $overdue): string {
    $params = array_merge([
        'page' => 'closeouts',
        'plan_id' => $planId,
        'status' => $status,
        'risk_level' => $risk,
        'responsible_user_id' => $responsible,
        'overdue' => $overdue,
    ], $changes);
    foreach ($params as $key => $value) {
        if ($key === 'page' || $key === 'plan_id') {
            continue;
        }
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            unset($params[$key]);
        }
    }

    return '?' . http_build_query($params);
};

if (!function_exists('closeout_donut_svg')) {
    /**
     * Pure SVG completion ring. CSP is style-src 'self' here, so the chart is
     * drawn with geometry attributes only — no inline style, no chart library.
     *
     * @param array<string, int> $counts
     */
    function closeout_donut_svg(array $counts, ?float $rate, callable $escapeFn): string
    {
        $segments = [
            ['key' => 'closed', 'label' => '已消项'],
            ['key' => 'pending_accept', 'label' => '待验收'],
            ['key' => 'ongoing', 'label' => '进行中'],
            ['key' => 'pending', 'label' => '待处理'],
        ];
        $total = 0;
        foreach ($segments as $segment) {
            $total += (int) ($counts[$segment['key']] ?? 0);
        }

        $markup = '<svg class="closeout-donut" viewBox="0 0 200 200" role="img" aria-label="消项完成率环形图">';
        $markup .= '<circle class="closeout-donut-track" cx="100" cy="100" r="76" fill="none" stroke-width="28" />';
        $offset = 0.0;
        foreach ($segments as $segment) {
            $count = (int) ($counts[$segment['key']] ?? 0);
            if ($count <= 0 || $total <= 0) {
                continue;
            }
            $share = $count / $total * 100;
            $markup .= '<circle class="closeout-seg closeout-seg-' . $segment['key'] . '" cx="100" cy="100" r="76"'
                . ' fill="none" stroke-width="28" pathLength="100"'
                . ' stroke-dasharray="' . rtrim(rtrim(sprintf('%.4F', $share), '0'), '.') . ' '
                . rtrim(rtrim(sprintf('%.4F', 100 - $share), '0'), '.') . '"'
                . ' stroke-dashoffset="-' . rtrim(rtrim(sprintf('%.4F', $offset), '0'), '.') . '"'
                . ' transform="rotate(-90 100 100)">'
                . '<title>' . $escapeFn($segment['label'] . '：' . $count . ' 项') . '</title></circle>';
            $offset += $share;
        }
        $markup .= '<text class="closeout-donut-rate" x="100" y="97" text-anchor="middle">'
            . $escapeFn($rate === null ? '—' : number_format($rate, 1) . '%') . '</text>';
        $markup .= '<text class="closeout-donut-caption" x="100" y="116" text-anchor="middle">完成率</text>';
        $markup .= '</svg>';

        return $markup;
    }
}

if (!function_exists('closeout_status_bar_svg')) {
    /**
     * Stacked status bar; one segment per status, shares of the scoped total.
     *
     * @param array<string, int> $counts
     */
    function closeout_status_bar_svg(array $counts, callable $escapeFn): string
    {
        $keys = ['pending', 'ongoing', 'pending_accept', 'closed', 'cancelled'];
        $total = 0;
        foreach ($keys as $key) {
            $total += (int) ($counts[$key] ?? 0);
        }

        $markup = '<svg class="closeout-statusbar" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="消项状态分布">';
        if ($total <= 0) {
            $markup .= '<rect class="closeout-bar-track" x="0" y="0" width="100" height="8" rx="2" /></svg>';

            return $markup;
        }
        $offset = 0.0;
        foreach ($keys as $key) {
            $count = (int) ($counts[$key] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $share = $count / $total * 100;
            $markup .= '<rect class="closeout-bar closeout-bar-' . $key . '" x="'
                . rtrim(rtrim(sprintf('%.4F', $offset), '0'), '.') . '" y="0" width="'
                . rtrim(rtrim(sprintf('%.4F', $share), '0'), '.') . '" height="8">'
                . '<title>' . $escapeFn(CloseoutLabels::itemStatus($key) . '：' . $count . ' 项') . '</title></rect>';
            $offset += $share;
        }
        $markup .= '</svg>';

        return $markup;
    }
}

if (!function_exists('closeout_mini_bar_svg')) {
    function closeout_mini_bar_svg(?float $rate): string
    {
        $width = $rate === null ? 0.0 : max(0.0, min(100.0, $rate));

        return '<svg class="closeout-mini-bar" viewBox="0 0 100 5" preserveAspectRatio="none" aria-hidden="true">'
            . '<rect class="closeout-mini-track" x="0" y="0" width="100" height="5" rx="2.5" />'
            . '<rect class="closeout-mini-fill" x="0" y="0" width="'
            . rtrim(rtrim(sprintf('%.2F', $width), '0'), '.') . '" height="5" rx="2.5" />'
            . '</svg>';
    }
}

if (!function_exists('closeout_due_label')) {
    /**
     * @return array{label: string, tone: string}
     */
    function closeout_due_label(?string $date, string $today, ?string $status = null): array
    {
        if ($date === null || $date === '') {
            return ['label' => '未排期', 'tone' => ''];
        }
        if ($status !== null && !in_array($status, CloseoutLabels::OPEN_STATUSES, true)) {
            return ['label' => '', 'tone' => ''];
        }
        $days = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->format('%r%a');
        if ($days < 0) {
            return ['label' => '逾期 ' . abs($days) . ' 天', 'tone' => 'is-red'];
        }
        if ($days === 0) {
            return ['label' => '今天到期', 'tone' => 'is-orange'];
        }
        if ($days <= 7) {
            return ['label' => '剩 ' . $days . ' 天', 'tone' => 'is-amber'];
        }

        return ['label' => '', 'tone' => ''];
    }
}

$openItems = array_values(array_filter(
    $items,
    static fn (array $row): bool => in_array((string) $row['status'], CloseoutLabels::OPEN_STATUSES, true)
));
$queueItems = array_slice($openItems, 0, 6);
ob_start();
?>
<section class="page-heading closeout-heading">
    <div>
        <p class="eyebrow">施工管理 · <?= $mode === 'items' ? '工程消项 · 消项清单' : '工程消项总览' ?></p>
        <h1>工程消项工作台</h1>
        <p class="muted">
            项目：<?= $escape($project['project_name']) ?>。选计划、看图表、勾事项、改状态都在这一页完成；消项只记录收口与验收证据，不核销 BOM 数量。
        </p>
    </div>
    <div class="project-list-actions">
        <?php if ($canCreate && $selectedPlan !== null && $selectedPlan['status'] === 'active'): ?>
            <a class="button button-primary" href="<?= $escape($buildUrl(['panel' => 'select', 'item_id' => null])) ?>">从 BOM 纳入</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <button class="button button-secondary" type="button" data-dialog-open="closeout-plan-create">新建消项计划</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($plans === []): ?>
    <section class="panel closeout-empty">
        <div class="panel-body">
            <h2>还没有消项计划</h2>
            <p class="muted">先建一个计划，再从 BOM 把需要收口的父材料一次性纳入；一个计划就是一条工作主线，不产生额外层级。</p>
            <?php if ($canCreate): ?>
                <button class="button button-primary" type="button" data-dialog-open="closeout-plan-create">新建消项计划</button>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
<div class="closeout-board" aria-label="消项图表板">
    <article class="closeout-board-card closeout-board-rate">
        <div class="closeout-board-head">
            <h3>完成率</h3>
            <span class="muted"><?= $planId > 0 ? '当前计划' : '全部计划' ?> · 不含已取消</span>
        </div>
        <div class="closeout-donut-wrap">
            <?= closeout_donut_svg($statusCounts, $rate === null ? null : (float) $rate, $escape) ?>
            <dl class="closeout-rate-facts">
                <div><dt>已消项</dt><dd><?= $closed ?></dd></div>
                <div><dt>应消项</dt><dd><?= $effective ?></dd></div>
                <div><dt>已取消</dt><dd><?= $cancelled ?></dd></div>
            </dl>
        </div>
    </article>

    <article class="closeout-board-card closeout-board-status">
        <div class="closeout-board-head">
            <h3>状态分布</h3>
            <span class="muted">点标签即筛选</span>
        </div>
        <?= closeout_status_bar_svg($statusCounts, $escape) ?>
        <div class="closeout-legend">
            <?php foreach ($statusKeys as $key): ?>
                <?php $isActive = $status === $key; ?>
                <a class="closeout-legend-link<?= $isActive ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['status' => $isActive ? null : $key, 'overdue' => null, 'item_id' => null])) ?>">
                    <span class="closeout-dot closeout-dot-<?= $escape($key) ?>" aria-hidden="true"></span>
                    <span class="closeout-legend-label"><?= $escape(CloseoutLabels::itemStatus($key)) ?></span>
                    <span class="closeout-legend-count"><?= (int) ($statusCounts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="closeout-board-card closeout-board-risk">
        <div class="closeout-board-head">
            <h3>风险与期限</h3>
            <span class="muted">未完成事项</span>
        </div>
        <ul class="closeout-risk-list">
            <?php $riskMax = max(1, ...array_values($stats['risk_counts'])); ?>
            <?php foreach (CloseoutLabels::RISK_LEVEL as $riskKey => $riskLabel): ?>
                <?php $riskCount = (int) ($stats['risk_counts'][$riskKey] ?? 0); ?>
                <li>
                    <span class="closeout-risk-label"><?= $escape($riskLabel) ?></span>
                    <span class="closeout-risk-track" aria-hidden="true">
                        <svg viewBox="0 0 100 7" preserveAspectRatio="none">
                            <rect class="closeout-mini-track" x="0" y="0" width="100" height="7" rx="3.5" />
                            <rect class="closeout-risk-fill closeout-risk-<?= $escape($riskKey) ?>" x="0" y="0" width="<?= rtrim(rtrim(sprintf('%.2F', $riskCount / $riskMax * 100), '0'), '.') ?>" height="7" rx="3.5" />
                        </svg>
                    </span>
                    <span class="closeout-risk-count"><?= $riskCount ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="closeout-deadline">
            <a class="<?= $overdue === 1 ? 'is-active' : '' ?>" href="<?= $escape($buildUrl(['status' => null, 'overdue' => $overdue === 1 ? null : 1, 'item_id' => null])) ?>">
                <strong><?= (int) $stats['overdue'] ?></strong><span>逾期</span>
            </a>
            <div><strong><?= (int) $stats['due_soon'] ?></strong><span>7 天内到期</span></div>
            <div><strong><?= (int) $stats['unassigned'] ?></strong><span>未指派责任人</span></div>
        </div>
    </article>
</div>

<section class="closeout-plan-strip" aria-label="消项计划切换">
    <a class="closeout-plan-chip<?= $planId === 0 ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['plan_id' => 0, 'item_id' => null])) ?>">
        <span class="closeout-plan-chip-title">全部计划</span>
        <span class="closeout-plan-chip-meta"><?= $totalAll ?> 项 · 已消项 <?= $closedAll ?></span>
        <?= closeout_mini_bar_svg($rateAll) ?>
    </a>
    <?php foreach ($plans as $plan): ?>
        <a class="closeout-plan-chip<?= $planId === (int) $plan['plan_id'] ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['plan_id' => (int) $plan['plan_id'], 'item_id' => null])) ?>">
            <span class="closeout-plan-chip-title">
                <span class="closeout-plan-dot closeout-plan-<?= $escape((string) $plan['status']) ?>" aria-hidden="true"></span>
                <?= $escape((string) $plan['plan_name']) ?>
            </span>
            <span class="closeout-plan-chip-meta"><?= $escape((string) $plan['plan_no']) ?> · <?= (int) $plan['closed_count'] ?>/<?= (int) $plan['item_count'] ?> · <?= $plan['completion_rate'] === null ? '—' : number_format((float) $plan['completion_rate'], 1) . '%' ?></span>
            <?= closeout_mini_bar_svg($plan['completion_rate'] === null ? null : (float) $plan['completion_rate']) ?>
        </a>
    <?php endforeach; ?>
</section>

<?php if ($selectedPlan !== null && ($canCreate || $canManage)): ?>
<details class="closeout-plan-admin">
    <summary>
        <span><strong>计划设置</strong> <span class="muted"><?= $escape($selectedPlan['plan_no'] . ' · ' . $selectedPlan['plan_name']) ?></span></span>
        <span class="muted"><?= $escape(CloseoutLabels::planStatus((string) $selectedPlan['status'])) ?> · <?= $escape($selectedPlan['planned_start_date'] ?? '—') ?> 至 <?= $escape($selectedPlan['planned_end_date'] ?? '—') ?></span>
    </summary>
    <div class="closeout-plan-admin-body">
        <?php if ($canCreate): ?>
        <form method="post" class="closeout-plan-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="closeout.plan.save">
            <input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['plan_id'] ?>">
            <div class="form-grid">
                <label class="field-wide">计划名称<input name="plan_name" maxlength="255" required value="<?= $escape((string) $selectedPlan['plan_name']) ?>"></label>
                <label>计划开始<input type="date" name="planned_start_date" value="<?= $escape((string) ($selectedPlan['planned_start_date'] ?? '')) ?>"></label>
                <label>计划结束<input type="date" name="planned_end_date" value="<?= $escape((string) ($selectedPlan['planned_end_date'] ?? '')) ?>"></label>
                <label class="field-wide">说明<textarea name="description" rows="2" maxlength="5000"><?= $escape((string) ($selectedPlan['description'] ?? '')) ?></textarea></label>
            </div>
            <div class="closeout-plan-actions">
                <button class="button button-secondary button-small" type="submit">保存计划信息</button>
                <span class="muted">只改名称、周期和说明；事项状态不受影响。</span>
            </div>
        </form>
        <?php endif; ?>
        <?php if ($canManage): ?>
        <div class="closeout-plan-actions">
            <?php if ($selectedPlan['status'] === 'draft'): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="closeout.plan.status">
                    <input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['plan_id'] ?>">
                    <input type="hidden" name="status_action" value="activate">
                    <button class="button button-primary button-small" type="submit">启动计划</button>
                </form>
            <?php elseif ($selectedPlan['status'] === 'active'): ?>
                <form method="post" class="inline-form" data-confirm="关闭后不再新增事项，未完成事项仍可执行和验收。确定关闭吗？">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="closeout.plan.status">
                    <input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['plan_id'] ?>">
                    <input type="hidden" name="status_action" value="close">
                    <button class="button button-quiet button-small" type="submit">关闭计划</button>
                </form>
            <?php else: ?>
                <form method="post" class="inline-form" data-confirm="重新打开后可继续新增事项。确定重新打开吗？">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="closeout.plan.status">
                    <input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['plan_id'] ?>">
                    <input type="hidden" name="status_action" value="reopen">
                    <button class="button button-secondary button-small" type="submit">重新打开</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</details>
<?php endif; ?>

<div class="closeout-workspace">
    <section class="panel closeout-list-panel">
        <div class="panel-heading">
            <div>
                <h2>消项事项</h2>
                <span class="muted"><span data-list-count data-unit=" 项"><?= count($items) ?> 项</span> · <?= $planId > 0 ? '当前计划' : '全部计划' ?></span>
            </div>
            <label class="search-field">
                <span class="sr-only">搜索事项</span>
                <input type="search" data-list-search="closeout-item-list" placeholder="搜事项、材料、责任人（即时）" autocomplete="off">
            </label>
        </div>

        <div class="closeout-filterbar" role="group" aria-label="按状态筛选">
            <?php $allFiltersClear = $status === '' && $overdue !== 1 && $risk === '' && $responsible === 0; ?>
            <a class="closeout-chip<?= $allFiltersClear ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['status' => null, 'overdue' => null, 'risk_level' => null, 'responsible_user_id' => null, 'item_id' => null])) ?>">全部<span class="closeout-chip-count"><?= $total ?></span></a>
            <?php foreach ($statusKeys as $key): ?>
                <?php if ($key === 'cancelled' && (int) $statusCounts['cancelled'] === 0) { continue; } ?>
                <a class="closeout-chip<?= $status === $key ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['status' => $key, 'overdue' => null, 'item_id' => null])) ?>">
                    <?= $escape(CloseoutLabels::itemStatus($key)) ?><span class="closeout-chip-count"><?= (int) ($statusCounts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
            <a class="closeout-chip closeout-chip-danger<?= $overdue === 1 ? ' is-active' : '' ?>" href="<?= $escape($buildUrl(['status' => null, 'overdue' => $overdue === 1 ? null : 1, 'item_id' => null])) ?>">逾期<span class="closeout-chip-count"><?= (int) $stats['overdue'] ?></span></a>
            <details class="closeout-more-filters">
                <summary>更多筛选<?php if ($risk !== '' || $responsible > 0): ?> · 已筛选<?php endif; ?></summary>
                <form method="get" class="closeout-filter-form">
                    <input type="hidden" name="page" value="closeouts">
                    <input type="hidden" name="plan_id" value="<?= $planId ?>">
                    <input type="hidden" name="status" value="<?= $escape($status) ?>">
                    <?php if ($overdue === 1): ?><input type="hidden" name="overdue" value="1"><?php endif; ?>
                    <label>风险
                        <select name="risk_level">
                            <option value="">全部风险</option>
                            <?php foreach (CloseoutLabels::RISK_LEVEL as $value => $label): ?>
                                <option value="<?= $escape($value) ?>" <?= $risk === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>责任人
                        <select name="responsible_user_id">
                            <option value="">全部责任人</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['user_id'] ?>" <?= $responsible === (int) $user['user_id'] ? 'selected' : '' ?>><?= $escape($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="closeout-filter-actions">
                        <button class="button button-secondary button-small" type="submit">应用</button>
                        <a class="button button-quiet button-small" href="<?= $escape($buildUrl(['risk_level' => null, 'responsible_user_id' => null, 'item_id' => null])) ?>">清除</a>
                    </div>
                </form>
            </details>
        </div>

        <?php if ($canEdit && $items !== []): ?>
        <form id="closeout-batch-form" class="closeout-batchbar" method="post" data-closeout-batch>
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="closeout.batch.update">
            <input type="hidden" name="plan_id" value="<?= $planId ?>">
            <input type="hidden" name="status" value="<?= $escape($status) ?>">
            <span class="closeout-selected" data-closeout-selected>已选 0 项</span>
            <label>批量计划完成<input type="date" name="planned_end_date"></label>
            <label>批量责任人
                <select name="responsible_user_id">
                    <option value="">保持不变</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int) $user['user_id'] ?>"><?= $escape($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>批量责任单位
                <select name="responsible_supplier_id">
                    <option value="">保持不变</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['supplier_id'] ?>"><?= $escape($supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-secondary button-small" type="submit">批量应用</button>
            <span class="muted closeout-batch-note">留空保持不变；已消项/已取消跳过</span>
        </form>
        <?php endif; ?>

        <?php if ($items === []): ?>
            <div class="panel-body">
                <p class="empty-state">
                    <?php if ($plans === []): ?>
                        还没有消项计划。
                    <?php elseif ($selectedPlan !== null && (int) $selectedPlan['item_count'] === 0): ?>
                        这个计划还没有事项。<?php if ($canCreate && $selectedPlan['status'] === 'active'): ?><a href="<?= $escape($buildUrl(['panel' => 'select', 'item_id' => null])) ?>">从 BOM 纳入第一批 →</a><?php endif; ?>
                    <?php else: ?>
                        没有符合当前筛选的事项。<a href="<?= $escape($buildUrl(['status' => null, 'risk_level' => null, 'responsible_user_id' => null, 'overdue' => null, 'item_id' => null])) ?>">清除筛选</a>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
        <div class="table-wrap closeout-table-wrap">
            <table class="data-table closeout-table">
                <thead>
                    <tr>
                        <?php if ($canEdit): ?>
                            <th class="closeout-col-check"><input type="checkbox" data-closeout-check-all aria-label="全选当前列表"></th>
                        <?php endif; ?>
                        <th>事项</th>
                        <th>责任</th>
                        <th>计划完成</th>
                        <th>风险</th>
                        <th>状态 / 下一步</th>
                    </tr>
                </thead>
                <tbody id="closeout-item-list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $isSelected = (int) $item['item_id'] === $selectedItemId;
                    $itemStatus = (string) $item['status'];
                    $due = closeout_due_label($item['planned_end_date'] ?? null, (string) $today, $itemStatus);
                    $searchText = mb_strtolower(implode(' ', [
                        (string) $item['item_no'],
                        (string) ($item['display_name'] ?? ''),
                        (string) ($item['material_name'] ?? ''),
                        (string) ($item['material_code'] ?? ''),
                        (string) ($item['category_name'] ?? ''),
                        (string) ($item['supplier_name'] ?? ''),
                        (string) ($item['responsible_user_name'] ?? ''),
                        (string) $item['status_label'],
                        (string) $item['risk_label'],
                        (string) ($item['planned_end_date'] ?? ''),
                    ]), 'UTF-8');
                    ?>
                    <tr class="closeout-row<?= $isSelected ? ' is-selected' : '' ?><?= !empty($item['is_overdue']) ? ' is-overdue' : '' ?>"
                        data-closeout-row
                        data-href="<?= $escape($buildUrl(['item_id' => (int) $item['item_id']])) ?>"
                        data-list-item
                        data-search="<?= $escape($searchText) ?>">
                        <?php if ($canEdit): ?>
                            <td class="closeout-col-check">
                                <input type="checkbox" form="closeout-batch-form" name="item_ids[]" value="<?= (int) $item['item_id'] ?>" data-closeout-check aria-label="选择 <?= $escape($item['item_no']) ?>">
                            </td>
                        <?php endif; ?>
                        <td>
                            <a class="closeout-item-no" href="<?= $escape($buildUrl(['item_id' => (int) $item['item_id']])) ?>"><?= $escape($item['item_no']) ?></a>
                            <span class="closeout-item-name"><?= $escape($item['display_name'] ?? $item['material_name'] ?? $item['title'] ?? '—') ?></span>
                            <span class="closeout-item-meta">
                                <?= $escape($item['material_name'] ?? '—') ?>
                                <?php if (!empty($item['material_code'])): ?> · <?= $escape((string) $item['material_code']) ?><?php endif; ?>
                                <?php if (!empty($item['category_name'])): ?> · <?= $escape((string) $item['category_name']) ?><?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($item['responsible_user_name']) || !empty($item['supplier_name'])): ?>
                                <span class="closeout-owner"><?= $escape($item['responsible_user_name'] ?? '—') ?></span>
                                <span class="closeout-item-meta"><?= $escape($item['supplier_name'] ?? '未指定单位') ?></span>
                            <?php else: ?>
                                <span class="status-badge is-amber">未指派</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="closeout-date"><?= $escape($item['planned_end_date'] ?? '未排期') ?></span>
                            <?php if ($due['label'] !== ''): ?><span class="status-badge <?= $escape($due['tone']) ?>"><?= $escape($due['label']) ?></span><?php endif; ?>
                        </td>
                        <td><span class="status-badge <?= $escape(CloseoutLabels::riskTone((string) $item['risk_level'])) ?>"><?= $escape($item['risk_label']) ?></span></td>
                        <td>
                            <div class="closeout-status-cell">
                                <span class="status-badge <?= $escape(CloseoutLabels::itemTone($itemStatus)) ?>"><?= $escape($item['status_label']) ?></span>
                                <span class="closeout-row-action">
                                    <?php if ($itemStatus === 'pending' && $canEdit): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="closeout.item.start">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <input type="hidden" name="status" value="<?= $escape($status) ?>">
                                            <button class="button button-primary button-small" type="submit">开始</button>
                                        </form>
                                    <?php elseif ($itemStatus === 'ongoing' && $canEdit): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="closeout.item.submit">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <input type="hidden" name="status" value="<?= $escape($status) ?>">
                                            <button class="button button-primary button-small" type="submit">提交验收</button>
                                        </form>
                                    <?php elseif ($itemStatus === 'pending_accept' && $canAccept): ?>
                                        <form method="post" class="inline-form" data-confirm="确认验收通过？实际完成日期记为今天。">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="closeout.item.accept">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <input type="hidden" name="result" value="passed">
                                            <input type="hidden" name="status" value="<?= $escape($status) ?>">
                                            <button class="button button-primary button-small" type="submit">通过</button>
                                        </form>
                                        <a class="button button-quiet button-small" href="<?= $escape($buildUrl(['item_id' => (int) $item['item_id']])) ?>#closeout-accept-form">验收</a>
                                    <?php else: ?>
                                        <a class="button button-quiet button-small" href="<?= $escape($buildUrl(['item_id' => (int) $item['item_id']])) ?>">详情</a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="empty-state" data-list-empty hidden>没有匹配的事项。</p>
        </div>
        <?php endif; ?>
    </section>

    <aside class="panel closeout-detail-panel" id="closeout-detail" aria-label="事项详情">
        <?php if ($selectedItem === null || $detail === null): ?>
            <div class="closeout-detail-head">
                <div>
                    <p class="eyebrow">工作台</p>
                    <h2>选择事项开始处理</h2>
                    <p class="muted">点左侧任意一行，详情和操作直接在右栏展开，不跳页。</p>
                </div>
            </div>
            <div class="closeout-detail-section">
                <h3 class="closeout-section-title">优先处理 <span class="muted"><?= count($openItems) ?> 项未完成</span></h3>
                <?php if ($queueItems === []): ?>
                    <p class="muted">当前筛选下没有未完成事项。</p>
                <?php else: ?>
                    <ul class="closeout-queue">
                        <?php foreach ($queueItems as $queue): ?>
                            <?php $queueDue = closeout_due_label($queue['planned_end_date'] ?? null, (string) $today, (string) $queue['status']); ?>
                            <li>
                                <a href="<?= $escape($buildUrl(['item_id' => (int) $queue['item_id']])) ?>">
                                    <span class="closeout-queue-no"><?= $escape($queue['item_no']) ?></span>
                                    <span class="closeout-queue-name"><?= $escape($queue['display_name'] ?? $queue['material_name'] ?? '—') ?></span>
                                    <span class="closeout-queue-meta">
                                        <span class="status-badge <?= $escape(CloseoutLabels::itemTone((string) $queue['status'])) ?>"><?= $escape($queue['status_label']) ?></span>
                                        <?php if ($queueDue['label'] !== ''): ?><span class="status-badge <?= $escape($queueDue['tone']) ?>"><?= $escape($queueDue['label']) ?></span><?php endif; ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="closeout-detail-section">
                <h3 class="closeout-section-title">当前范围</h3>
                <dl class="closeout-facts">
                    <div class="closeout-fact-wide"><dt>计划</dt><dd><?= $selectedPlan !== null ? $escape($selectedPlan['plan_no'] . ' · ' . $selectedPlan['plan_name']) : '全部计划' ?></dd></div>
                    <div><dt>事项</dt><dd><?= $total ?> 项 · 未完成 <?= (int) $stats['open'] ?></dd></div>
                    <div><dt>完成率</dt><dd><?= $escape($rateText) ?></dd></div>
                    <div><dt>逾期</dt><dd><?= (int) $stats['overdue'] ?> 项</dd></div>
                    <div><dt>7 天内到期</dt><dd><?= (int) $stats['due_soon'] ?> 项（至 <?= $escape((string) $stats['soon_date']) ?>）</dd></div>
                    <div><dt>未指派责任人</dt><dd><?= (int) $stats['unassigned'] ?> 项</dd></div>
                </dl>
                <p class="muted">提示：状态标签单击即可筛选；勾选行后可在清单上方批量改完成日期与责任人。</p>
            </div>
        <?php else: ?>
            <?php
            // The list loop leaves $item pointing at the last row; the detail
            // partial must render the selected item, not that leftover value.
            $item = $selectedItem;
            require __DIR__ . '/_detail.php';
            ?>
        <?php endif; ?>
    </aside>
</div>
<?php endif; ?>

<?php if ($canCreate): ?>
<dialog class="edit-dialog closeout-plan-dialog" id="closeout-plan-create">
    <div class="dialog-heading">
        <h2>新建消项计划</h2>
        <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
    </div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="closeout.plan.save">
        <label class="field-wide">计划名称<input name="plan_name" maxlength="255" required placeholder="例如：展厅工程收口消项计划"></label>
        <p class="muted field-wide">计划周期默认取项目开始与结束日期，可按本批次实际安排调整。</p>
        <label>计划开始日期<input type="date" name="planned_start_date" value="<?= $escape($project['start_date'] ?? '') ?>"></label>
        <label>计划结束日期<input type="date" name="planned_end_date" value="<?= $escape($project['end_date'] ?? '') ?>"></label>
        <label>初始状态<select name="status">
            <option value="active" selected>进行中（可直接纳入事项）</option>
            <option value="draft">草稿（稍后再启动）</option>
        </select></label>
        <label class="field-wide">说明<textarea name="description" rows="2" maxlength="5000" placeholder="消项范围、验收口径、参与单位等"></textarea></label>
        <div class="dialog-actions">
            <button class="button button-primary" type="submit">创建计划</button>
            <span class="muted">创建后自动回到这个工作台，并切到新计划。</span>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($selectData !== null): ?>
<dialog class="edit-dialog wide closeout-select-dialog" open data-auto-open aria-label="从 BOM 纳入事项">
    <div class="dialog-heading">
        <div>
            <h2>从 BOM 纳入事项</h2>
            <p class="muted">计划：<?= $escape($selectData['plan']['plan_no'] . ' · ' . $selectData['plan']['plan_name']) ?>。只读 BOM，不核销数量、不回写状态。</p>
        </div>
        <a class="button button-quiet" href="<?= $escape($buildUrl(['panel' => null])) ?>">关闭</a>
    </div>
    <?php
    $selectPlan = $selectData['plan'];
    $selectCandidates = $selectData['candidates'];
    $selectHiddenMaterialCount = $selectData['hiddenMaterialCount'];
    $selectHasParentMaterials = $selectData['hasParentMaterials'];
    $selectUsers = $selectData['users'];
    $selectSuppliers = $selectData['suppliers'];
    require __DIR__ . '/_select-form.php';
    ?>
</dialog>
<?php endif; ?>

<?php $pageScripts = [['module' => 'closeout.management', 'file' => 'closeout.js', 'version' => (string) filemtime(dirname(__DIR__) . '/public/closeout.js')]]; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

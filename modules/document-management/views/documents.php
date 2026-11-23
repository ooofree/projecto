<?php
$issueStatusLabels = [
    'open' => '待评估',
    'in_review' => '处理中',
    'resolved' => '待关闭',
    'closed' => '已关闭',
];
$issueStatusBadge = [
    'open' => 'is-blue',
    'in_review' => 'is-amber',
    'resolved' => 'is-green',
    'closed' => 'is-neutral',
];
$rfiStatusLabels = [
    'draft' => '草稿',
    'submitted' => '已提交',
    'under_review' => '审核中',
    'responded' => '已答复',
    'closed' => '已关闭',
];
$rfiStatusBadge = [
    'draft' => 'is-neutral',
    'submitted' => 'is-blue',
    'under_review' => 'is-amber',
    'responded' => 'is-green',
    'closed' => 'is-neutral',
];

// Presentation-only projections built by the plugin from the lists this page
// already loads. Missing keys fall back to zeros so the board still renders if
// another harness includes the view without the projections.
$home = is_array($homeMetrics ?? null) ? $homeMetrics : [];
$completion = is_array($home['completion'] ?? null) ? $home['completion'] : ['closed' => 0, 'total' => 0, 'percent' => 0];
$issueLane = is_array($home['issue'] ?? null) ? $home['issue'] : ['total' => 0, 'counts' => []];
$rfiLane = is_array($home['rfi'] ?? null) ? $home['rfi'] : ['total' => 0, 'counts' => [], 'answered' => 0, 'overdue' => 0];
$goals = is_array($home['goals'] ?? null) ? $home['goals'] : [];
$milestones = is_array($home['milestones'] ?? null) ? $home['milestones'] : [];
$queue = is_array($home['queue'] ?? null) ? $home['queue'] : [];
$recent = is_array($home['recent'] ?? null) ? $home['recent'] : [];
$issueTotal = (int) $issueLane['total'];
$rfiTotal = (int) $rfiLane['total'];
$issueCounts = is_array($issueLane['counts'] ?? null) ? $issueLane['counts'] : [];
$rfiCounts = is_array($rfiLane['counts'] ?? null) ? $rfiLane['counts'] : [];
$completionClosed = (int) ($completion['closed'] ?? 0);
$completionTotal = (int) ($completion['total'] ?? 0);
$completionPercent = max(0, min(100, (int) ($completion['percent'] ?? 0)));
$weekClosures = (int) ($home['week_closures'] ?? 0);
$readinessPercent = static fn (int $done, int $total): float => $total > 0 ? round(min(1, $done / $total) * 100, 1) : 0.0;
$reachedCount = 0;
foreach ($milestones as $milestone) {
    if (!empty($milestone['reached'])) {
        $reachedCount++;
    }
}
$issueDistribution = [];
foreach ($issueStatusLabels as $statusKey => $statusLabel) {
    $count = (int) ($issueCounts[$statusKey] ?? 0);
    if ($count > 0) {
        $issueDistribution[] = $statusLabel . ' ' . $count;
    }
}
$rfiDistribution = [];
foreach ($rfiStatusLabels as $statusKey => $statusLabel) {
    $count = (int) ($rfiCounts[$statusKey] ?? 0);
    if ($count > 0) {
        $rfiDistribution[] = $statusLabel . ' ' . $count;
    }
}
$issueSegmentClass = [
    'open' => 'is-open',
    'in_review' => 'is-review',
    'resolved' => 'is-verify',
    'closed' => 'is-closed',
];
$rfiSegmentClass = [
    'draft' => 'is-draft',
    'submitted' => 'is-open',
    'under_review' => 'is-review',
    'responded' => 'is-verify',
    'closed' => 'is-closed',
];
$urgencyClass = [
    'critical' => 'is-critical',
    'urgent' => 'is-critical',
    'high' => 'is-high',
    'medium' => 'is-medium',
    'low' => 'is-low',
];
ob_start();
?>
<section class="doc-hero" aria-labelledby="doc-home-title">
    <div class="doc-hero-copy">
        <p class="eyebrow">工程协同 · 文档总览</p>
        <h1 id="doc-home-title">文档管理</h1>
        <p class="muted doc-hero-project">当前项目：<?= $escape($currentProject['project_name']) ?></p>
    </div>
    <div class="doc-rate" aria-label="综合闭环率">
        <div class="doc-rate-ring" style="--doc-rate-value: <?= $completionPercent ?>" aria-hidden="true">
            <b><?= $completionTotal > 0 ? $completionPercent . '%' : '—' ?></b>
        </div>
        <div class="doc-rate-copy">
            <span class="doc-rate-title">综合闭环率</span>
            <span class="doc-rate-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $completionPercent ?>" aria-label="综合闭环率">
                <span style="width: <?= $completionPercent ?>%"></span>
            </span>
            <span class="doc-rate-meta"><?= $completionTotal > 0 ? '已闭环 ' . $completionClosed . ' / ' . $completionTotal . ' 条记录' : '暂无记录' ?></span>
        </div>
        <div class="doc-rate-week">
            <b><?= $weekClosures ?></b>
            <span>本周闭环</span>
        </div>
    </div>
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
$navCurrent = 'documents';
$navLabel = '文档管理';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
?>

<section class="doc-pipeline" aria-label="问题与 RFI 推进情况">
    <article class="doc-lane">
        <div class="doc-lane-head">
            <h2>问题推进</h2>
            <a href="?page=issues">全部 <?= $issueTotal ?> 条 →</a>
        </div>
        <div class="doc-lane-bar" role="img" aria-label="问题 <?= $issueTotal ?> 条：<?= $escape($issueDistribution === [] ? '暂无记录' : implode('、', $issueDistribution)) ?>">
            <?php foreach ($issueStatusLabels as $statusKey => $statusLabel): ?>
                <?php $count = (int) ($issueCounts[$statusKey] ?? 0); ?>
                <?php if ($count > 0): ?>
                    <span class="doc-seg <?= $escape($issueSegmentClass[$statusKey]) ?>" style="width: <?= $readinessPercent($count, $issueTotal) ?>%" title="<?= $escape($statusLabel) ?> <?= $count ?> 条"></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <ul class="doc-lane-legend">
            <?php foreach ($issueStatusLabels as $statusKey => $statusLabel): ?>
                <li><i class="doc-dot <?= $escape($issueSegmentClass[$statusKey]) ?>" aria-hidden="true"></i><?= $escape($statusLabel) ?> <b><?= (int) ($issueCounts[$statusKey] ?? 0) ?></b></li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="doc-lane">
        <div class="doc-lane-head">
            <h2>RFI 推进</h2>
            <a href="?page=rfis">全部 <?= $rfiTotal ?> 份 →</a>
        </div>
        <?php if ((int) ($rfiLane['overdue'] ?? 0) > 0): ?>
            <p class="doc-lane-alert" role="status">逾期 <?= (int) $rfiLane['overdue'] ?> 份 RFI 待处理</p>
        <?php endif; ?>
        <div class="doc-lane-bar" role="img" aria-label="RFI <?= $rfiTotal ?> 份：<?= $escape($rfiDistribution === [] ? '暂无记录' : implode('、', $rfiDistribution)) ?>">
            <?php foreach ($rfiStatusLabels as $statusKey => $statusLabel): ?>
                <?php $count = (int) ($rfiCounts[$statusKey] ?? 0); ?>
                <?php if ($count > 0): ?>
                    <span class="doc-seg <?= $escape($rfiSegmentClass[$statusKey]) ?>" style="width: <?= $readinessPercent($count, $rfiTotal) ?>%" title="<?= $escape($statusLabel) ?> <?= $count ?> 份"></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <ul class="doc-lane-legend">
            <?php foreach ($rfiStatusLabels as $statusKey => $statusLabel): ?>
                <li><i class="doc-dot <?= $escape($rfiSegmentClass[$statusKey]) ?>" aria-hidden="true"></i><?= $escape($statusLabel) ?> <b><?= (int) ($rfiCounts[$statusKey] ?? 0) ?></b></li>
            <?php endforeach; ?>
        </ul>
    </article>
</section>

<div class="doc-board">
    <section class="panel">
        <div class="panel-heading">
            <h2>推进目标</h2>
            <span class="muted">按记录实时统计</span>
        </div>
        <div class="panel-body">
            <div class="doc-goals">
                <?php foreach ($goals as $goal): ?>
                    <?php
                    $done = (int) ($goal['done'] ?? 0);
                    $total = (int) ($goal['total'] ?? 0);
                    $remaining = (int) ($goal['remaining'] ?? 0);
                    $percent = $readinessPercent($done, $total);
                    $cleared = $total > 0 && $remaining === 0;
                    ?>
                    <a class="doc-goal<?= $cleared ? ' is-reached' : '' ?>" href="<?= $escape((string) $goal['href']) ?>">
                        <span class="doc-goal-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M6 3h2v18H6V3Zm2 1h8l-1.5 3.2L16 10.4H8V4Z"/></svg>
                        </span>
                        <span class="doc-goal-body">
                            <span class="doc-goal-name">
                                <span><?= $escape((string) $goal['label']) ?></span>
                                <span class="doc-goal-rate"><?= $total > 0 ? (int) round($percent) . '%' : '—' ?></span>
                            </span>
                            <span class="doc-goal-hint"><?= $escape((string) $goal['hint']) ?></span>
                            <span class="doc-goal-track">
                                <span style="width: <?= $percent ?>%"></span>
                            </span>
                            <span class="doc-goal-meta">
                                <span><?= $done ?> / <?= $total ?></span>
                                <span><?= $total === 0 ? '暂无记录' : ($cleared ? '已完成' : '还剩 ' . $remaining . ' 项') ?></span>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>优先待办</h2>
            <span class="muted">按逾期、优先级、停留时间排序</span>
        </div>
        <div class="panel-body">
            <?php if ($queue === []): ?>
                <p class="empty-state">当前没有待推进的问题或 RFI。</p>
                <?php if (!empty($canCreate)): ?>
                    <div class="row-actions">
                        <a class="button button-primary" href="?page=issues">登记问题</a>
                        <a class="button button-secondary" href="?page=rfis">创建 RFI</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <ul class="doc-next">
                    <?php foreach ($queue as $item): ?>
                        <?php
                        $isOverdue = !empty($item['overdue']);
                        $flagClass = $isOverdue ? 'is-overdue' : ($urgencyClass[(string) $item['urgency']] ?? 'is-medium');
                        $kindLabel = $item['kind'] === 'rfi' ? 'RFI' : '问题';
                        $ageDays = (int) ($item['age_days'] ?? 0);
                        $dueDate = (string) ($item['due_date'] ?? '');
                        $meta = '';
                        if ($item['kind'] === 'rfi') {
                            $meta = $isOverdue
                                ? '逾期 ' . (int) ($item['overdue_days'] ?? 0) . ' 天'
                                : ($dueDate !== '' ? '截止 ' . substr($dueDate, 5, 5) : '未设截止');
                        } else {
                            $stay = $ageDays <= 0 ? '今日更新' : '停留 ' . $ageDays . ' 天';
                            $meta = $item['status'] === 'resolved' ? '待复核 · ' . $stay : $stay;
                        }
                        ?>
                        <li class="doc-next-row<?= $isOverdue ? ' is-overdue' : '' ?>">
                            <span class="doc-next-flag <?= $escape($flagClass) ?>"><?= $isOverdue ? '逾期' : $escape((string) $item['urgency_label']) ?></span>
                            <a class="doc-next-main" href="<?= $escape((string) $item['href']) ?>">
                                <span class="doc-next-kind"><?= $kindLabel ?></span>
                                <span class="doc-next-number"><?= $escape((string) $item['number']) ?></span>
                                <span class="doc-next-title" title="<?= $escape((string) $item['title']) ?>"><?= $escape(mb_strimwidth((string) $item['title'], 0, 100, '…')) ?></span>
                            </a>
                            <span class="doc-next-meta"><?= $escape($meta) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="doc-block-foot">完整清单在 <a href="?page=issues">问题管理</a> 与 <a href="?page=rfis">RFI 管理</a> 中查看。</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel doc-metrics-panel">
    <div class="panel-heading">
        <h2>过程节点</h2>
        <span class="muted"><?= $reachedCount ?>/<?= count($milestones) ?> 已完成 · 由记录自动推算</span>
    </div>
    <div class="panel-body">
        <div class="doc-metrics">
            <?php foreach ($milestones as $milestone): ?>
                <?php
                $done = (int) ($milestone['done'] ?? 0);
                $target = max(1, (int) ($milestone['target'] ?? 1));
                $reached = !empty($milestone['reached']);
                ?>
                <span class="doc-metric<?= $reached ? ' is-reached' : '' ?>" title="<?= $escape((string) $milestone['rule']) ?>" aria-label="<?= $escape((string) $milestone['name'] . '：' . $milestone['rule'] . '（' . min($done, $target) . '/' . $target . '）') ?>">
                    <span class="doc-metric-mark" aria-hidden="true">
                        <?php if ($reached): ?>
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="m9.2 16.2-4-4 1.4-1.4 2.6 2.6L16.8 6l1.4 1.4-9 8.8Z"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M4 11h16v2H4z"/></svg>
                        <?php endif; ?>
                    </span>
                    <span class="doc-metric-name"><?= $escape((string) $milestone['name']) ?></span>
                    <span class="doc-metric-progress"><?= min($done, $target) ?>/<?= $target ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel doc-feed-panel">
    <div class="panel-heading">
        <h2>最近动态</h2>
        <span class="muted">问题与 RFI 混合，按更新时间排序</span>
    </div>
    <div class="panel-body">
        <?php if ($recent === []): ?>
            <p class="empty-state">当前项目还没有问题或 RFI 记录。<?php if (!empty($canCreate)): ?>从登记第一个问题开始。<?php endif; ?></p>
        <?php else: ?>
            <ul class="doc-feed">
                <?php foreach ($recent as $item): ?>
                    <?php
                    $isIssue = $item['kind'] === 'issue';
                    $labels = $isIssue ? $issueStatusLabels : $rfiStatusLabels;
                    $badges = $isIssue ? $issueStatusBadge : $rfiStatusBadge;
                    $href = $isIssue ? '?page=issues' : '?page=rfis';
                    ?>
                    <li class="doc-feed-row">
                        <span class="doc-feed-kind <?= $isIssue ? 'is-issue' : 'is-rfi' ?>"><?= $isIssue ? '问题' : 'RFI' ?></span>
                        <a class="doc-feed-main" href="<?= $escape($href) ?>">
                            <span class="doc-feed-number"><?= $escape((string) $item['number']) ?></span>
                            <span class="doc-feed-title" title="<?= $escape((string) $item['summary']) ?>"><?= $escape(mb_strimwidth((string) $item['summary'], 0, 90, '…')) ?></span>
                        </a>
                        <span class="status-badge <?= $escape($badges[$item['status']] ?? 'is-neutral') ?>"><?= $escape($labels[$item['status']] ?? (string) $item['status']) ?></span>
                        <time class="doc-feed-time"><?= $escape(\ProjectO\Support::formatDateTime((string) $item['updated_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="doc-source">以上比率与节点均由现有问题、RFI 与跟进记录实时推算，仅用于掌握推进节奏，不写入数据库。</p>
    </div>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

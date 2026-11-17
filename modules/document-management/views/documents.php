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
$recentActivity = $recentActivity ?? [];
$pendingIssues = $pendingIssues ?? [];
$pendingRfis = $pendingRfis ?? [];
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目协作</p>
        <h1>文档管理</h1>
        <p class="muted">当前项目：<?= $escape($currentProject['project_name']) ?></p>
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

<section class="kpi-strip" aria-label="文档摘要">
    <a class="kpi-item" href="?page=issues">
        <span class="kpi-label">问题</span>
        <span class="kpi-value"><?= (int) $issueCount ?></span>
        <span class="kpi-note"><?= (int) $openIssueCount ?> 条待处理或审核中</span>
    </a>
    <a class="kpi-item" href="?page=rfis">
        <span class="kpi-label">RFI</span>
        <span class="kpi-value"><?= (int) $rfiCount ?></span>
        <span class="kpi-note"><?= (int) $openRfiCount ?> 份尚未关闭</span>
    </a>
</section>

<div class="doc-home-grid">
    <section class="panel">
        <div class="panel-heading">
            <h2>最近更新</h2>
            <span class="muted">问题与 RFI 混合，按更新时间排序</span>
        </div>
        <?php if ($recentActivity === []): ?>
            <p class="empty-state">当前项目还没有问题或 RFI 记录。<?php if (!empty($canCreate)): ?>从登记第一个问题开始。<?php endif; ?></p>
            <?php if (!empty($canCreate)): ?>
                <div class="row-actions">
                    <a class="button button-primary" href="?page=issues">登记问题</a>
                    <a class="button button-secondary" href="?page=rfis">创建 RFI</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <ul class="work-queue">
                <?php foreach ($recentActivity as $item): ?>
                    <?php
                    $isIssue = $item['kind'] === 'issue';
                    $labels = $isIssue ? $issueStatusLabels : $rfiStatusLabels;
                    $badges = $isIssue ? $issueStatusBadge : $rfiStatusBadge;
                    $href = $isIssue ? '?page=issues' : '?page=rfis';
                    ?>
                    <li class="work-queue-item">
                        <span class="work-queue-kind <?= $isIssue ? 'is-issue' : 'is-rfi' ?>"><?= $isIssue ? '问题' : 'RFI' ?></span>
                        <a class="work-queue-main" href="<?= $escape($href) ?>">
                            <span class="work-queue-number"><?= $escape($item['number']) ?></span>
                            <span class="work-queue-summary"><?= $escape(mb_strimwidth($item['summary'], 0, 120, '…')) ?></span>
                        </a>
                        <span class="status-badge <?= $escape($badges[$item['status']] ?? 'is-neutral') ?>"><?= $escape($labels[$item['status']] ?? $item['status']) ?></span>
                        <time class="work-queue-time"><?= $escape(\ProjectO\Support::formatDateTime($item['updated_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="muted work-queue-more">在<a href="?page=issues">问题管理</a>或<a href="?page=rfis">RFI 管理</a>中打开对应记录查看详情。</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>待处理</h2>
            <span class="muted">未关闭的问题与 RFI</span>
        </div>
        <?php if ($pendingIssues === [] && $pendingRfis === []): ?>
            <p class="empty-state">没有待处理事项，全部问题与 RFI 均已关闭。</p>
        <?php else: ?>
            <?php if ($pendingIssues !== []): ?>
                <h3 class="work-queue-group-title">问题（<?= (int) $openIssueCount ?> 条待处理或审核中）</h3>
                <ul class="work-queue">
                    <?php foreach ($pendingIssues as $issue): ?>
                        <li class="work-queue-item">
                            <a class="work-queue-main" href="?page=issues">
                                <span class="work-queue-number"><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?></span>
                                <span class="work-queue-summary"><?= $escape(mb_strimwidth($issue['description'], 0, 120, '…')) ?></span>
                            </a>
                            <span class="status-badge <?= $escape($issueStatusBadge[$issue['status']] ?? 'is-neutral') ?>"><?= $escape($issueStatusLabels[$issue['status']] ?? $issue['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($pendingRfis !== []): ?>
                <h3 class="work-queue-group-title">RFI（<?= (int) $openRfiCount ?> 份尚未关闭）</h3>
                <ul class="work-queue">
                    <?php foreach ($pendingRfis as $rfi): ?>
                        <li class="work-queue-item">
                            <a class="work-queue-main" href="?page=rfis">
                                <span class="work-queue-number"><?= $escape($rfi['rfi_number'] ?: '#' . $rfi['rfi_id']) ?></span>
                                <span class="work-queue-summary"><?= $escape(mb_strimwidth($rfi['description'], 0, 120, '…')) ?></span>
                            </a>
                            <span class="status-badge <?= $escape($rfiStatusBadge[$rfi['status']] ?? 'is-neutral') ?>"><?= $escape($rfiStatusLabels[$rfi['status']] ?? $rfi['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="muted work-queue-more">最多各显示 5 条；完整清单见<a href="?page=issues">问题管理</a>与<a href="?page=rfis">RFI 管理</a>。</p>
        <?php endif; ?>
    </section>
</div>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>
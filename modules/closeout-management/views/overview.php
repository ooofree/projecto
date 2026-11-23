<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;
use ProjectO\Support;

$statusCounts = $stats['counts'];
$rateLabel = $stats['completion_rate'] === null ? '—' : number_format((float) $stats['completion_rate'], 1) . '%';
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工管理 · 工程消项</p>
        <h1>工程消项总览</h1>
        <p class="muted">
            项目：<?= $escape($project['project_name']) ?>。消项只记录工程收口事项、责任与验收证据，不核销 BOM 数量，也不回写 BOM 状态。
        </p>
    </div>
    <div class="project-list-actions">
        <?php if ($canCreatePlan): ?>
            <button class="button button-primary" type="button" data-dialog-open="closeout-plan-create">新建消项计划</button>
        <?php endif; ?>
        <?php if ($canViewItems): ?>
            <a class="button button-secondary" href="?page=closeout-items">打开消项清单</a>
        <?php endif; ?>
    </div>
</section>

<section class="kpi-strip" aria-label="消项统计">
    <div class="kpi-item">
        <span class="kpi-label">事项总数</span>
        <span class="kpi-value"><?= (int) $stats['total'] ?></span>
        <span class="kpi-note">含已取消历史</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">待处理</span>
        <span class="kpi-value"><?= (int) $statusCounts['pending'] ?></span>
        <span class="kpi-note">尚未开始执行</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">进行中</span>
        <span class="kpi-value"><?= (int) $statusCounts['ongoing'] ?></span>
        <span class="kpi-note">执行或返工中</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">待验收</span>
        <span class="kpi-value"><?= (int) $statusCounts['pending_accept'] ?></span>
        <span class="kpi-note">等待验收结论</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">已消项</span>
        <span class="kpi-value"><?= (int) $statusCounts['closed'] ?></span>
        <span class="kpi-note">验收通过</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">逾期 / 完成率</span>
        <span class="kpi-value"><?= (int) $stats['overdue'] ?></span>
        <span class="kpi-note">完成率 <?= $escape($rateLabel) ?> · 今天 <?= $escape($today) ?></span>
    </div>
</section>

<?php if ($plans === []): ?>
    <div class="notice notice-info">当前项目还没有消项计划。先新建计划，再从 BOM 批量纳入需要收口的事项。</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>消项计划</h2>
        <span class="muted"><?= count($plans) ?> 个计划 · 进行中 <?= (int) $activePlanCount ?></span>
    </div>
    <?php if ($plans !== []): ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>计划编号</th>
                    <th>计划名称</th>
                    <th>状态</th>
                    <th>计划周期</th>
                    <th>事项 / 已消项</th>
                    <th>完成率</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><strong><?= $escape($plan['plan_no']) ?></strong></td>
                    <td>
                        <?= $escape($plan['plan_name']) ?>
                        <?php if (!empty($plan['description'])): ?>
                            <span class="muted"><?= $escape(mb_substr((string) $plan['description'], 0, 60)) ?><?= mb_strlen((string) $plan['description']) > 60 ? '…' : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge <?= $escape(CloseoutLabels::planTone((string) $plan['status'])) ?>"><?= $escape(CloseoutLabels::planStatus((string) $plan['status'])) ?></span></td>
                    <td class="muted">
                        <?= $escape($plan['planned_start_date'] ?? '—') ?> 至 <?= $escape($plan['planned_end_date'] ?? '—') ?>
                    </td>
                    <td><?= (int) $plan['item_count'] ?> / <?= (int) $plan['closed_count'] ?></td>
                    <td><?= $plan['completion_rate'] === null ? '—' : number_format((float) $plan['completion_rate'], 1) . '%' ?></td>
                    <td>
                        <div class="project-list-actions">
                            <a class="button button-quiet" href="?page=closeout-items&amp;plan_id=<?= (int) $plan['plan_id'] ?>">打开清单</a>
                            <?php if ($plan['status'] === 'active' && $canCreatePlan): ?>
                                <a class="button button-quiet" href="?page=closeout-select&amp;plan_id=<?= (int) $plan['plan_id'] ?>">从 BOM 纳入</a>
                            <?php endif; ?>
                            <?php if ($canManagePlans): ?>
                                <?php if ($plan['status'] === 'draft'): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="closeout.plan.status">
                                        <input type="hidden" name="plan_id" value="<?= (int) $plan['plan_id'] ?>">
                                        <input type="hidden" name="status_action" value="activate">
                                        <button class="button button-quiet" type="submit">启动计划</button>
                                    </form>
                                <?php elseif ($plan['status'] === 'active'): ?>
                                    <form method="post" class="inline-form" data-confirm="关闭后不能再新增事项，未完成事项仍可继续执行和验收。确定关闭该计划吗？">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="closeout.plan.status">
                                        <input type="hidden" name="plan_id" value="<?= (int) $plan['plan_id'] ?>">
                                        <input type="hidden" name="status_action" value="close">
                                        <button class="button button-quiet" type="submit">关闭计划</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="inline-form" data-confirm="重新打开计划后可继续新增事项。确定重新打开吗？">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="closeout.plan.status">
                                        <input type="hidden" name="plan_id" value="<?= (int) $plan['plan_id'] ?>">
                                        <input type="hidden" name="status_action" value="reopen">
                                        <button class="button button-quiet" type="submit">重新打开</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="panel-body">
            <p class="empty-state">暂无消项计划。</p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>最近更新事项</h2>
        <a class="button button-quiet" href="?page=closeout-items">查看全部</a>
    </div>
    <?php if ($recentItems === []): ?>
        <div class="panel-body"><p class="empty-state">还没有消项事项。创建计划后从 BOM 纳入。</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>事项编号</th>
                    <th>事项</th>
                    <th>材料</th>
                    <th>状态</th>
                    <th>风险</th>
                    <th>责任人</th>
                    <th>计划完成</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentItems as $item): ?>
                <tr>
                    <td><a href="?page=closeout-item&amp;item_id=<?= (int) $item['item_id'] ?>"><?= $escape($item['item_no']) ?></a></td>
                    <td><?= $escape($item['display_name'] ?? $item['material_name'] ?? $item['title'] ?? '—') ?></td>
                    <td><?= $escape($item['material_name'] ?? '—') ?></td>
                    <td><span class="status-badge <?= $escape(CloseoutLabels::itemTone((string) $item['status'])) ?>"><?= $escape($item['status_label']) ?></span></td>
                    <td><span class="status-badge <?= $escape(CloseoutLabels::riskTone((string) $item['risk_level'])) ?>"><?= $escape($item['risk_label']) ?></span></td>
                    <td><?= $escape($item['responsible_user_name'] ?? '—') ?></td>
                    <td class="muted">
                        <?= $escape($item['planned_end_date'] ?? '—') ?><?= !empty($item['is_overdue']) ? ' <span class="status-badge is-red">逾期</span>' : '' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($canCreatePlan): ?>
<dialog class="edit-dialog" id="closeout-plan-create">
    <div class="dialog-heading">
        <h2>新建消项计划</h2>
        <button class="button button-quiet" type="button" data-dialog-close>关闭</button>
    </div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="closeout.plan.save">
        <label class="field-wide">计划名称<input name="plan_name" maxlength="255" required placeholder="例如：展厅工程收口消项计划"></label>
        <p class="muted field-wide">计划周期默认取项目开始与结束日期，可按本批次实际安排手动调整。</p>
        <label>计划开始日期<input type="date" name="planned_start_date" value="<?= $escape($project['start_date'] ?? '') ?>"></label>
        <label>计划结束日期<input type="date" name="planned_end_date" value="<?= $escape($project['end_date'] ?? '') ?>"></label>
        <label>初始状态<select name="status">
            <option value="active" selected>进行中（可直接纳入事项）</option>
            <option value="draft">草稿（稍后再启动）</option>
        </select></label>
        <label class="field-wide">说明<textarea name="description" rows="2" maxlength="5000" placeholder="消项范围、验收口径、参与单位等"></textarea></label>
        <div class="dialog-actions">
            <button class="button button-primary" type="submit">创建计划</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;
use ProjectO\Support;

$planId = (int) $filters['plan_id'];
$status = (string) $filters['status'];
$risk = (string) $filters['risk_level'];
$responsible = (int) $filters['responsible_user_id'];
$overdue = (int) $filters['overdue'];
$keyword = (string) $filters['keyword'];

$activePlan = null;
foreach ($plans as $plan) {
    if ($planId > 0 && (int) $plan['plan_id'] === $planId) {
        $activePlan = $plan;
        break;
    }
}
if ($activePlan === null) {
    foreach ($plans as $plan) {
        if ($plan['status'] === 'active') {
            $activePlan = $plan;
            break;
        }
    }
}
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工管理 · 工程消项</p>
        <h1>消项清单</h1>
        <p class="muted">
            项目：<?= $escape($project['project_name']) ?>。按计划、状态、责任人筛选，逾期事项按计划完成日期判断。
        </p>
    </div>
    <div class="project-list-actions">
        <?php if ($canCreate && $activePlan !== null && $activePlan['status'] === 'active'): ?>
            <a class="button button-primary" href="?page=closeout-select&amp;plan_id=<?= (int) $activePlan['plan_id'] ?>">从 BOM 纳入事项</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a class="button button-secondary" href="?page=closeouts">新建计划</a>
        <?php endif; ?>
    </div>
</section>

<section class="kpi-strip" aria-label="当前筛选统计">
    <div class="kpi-item">
        <span class="kpi-label">事项总数</span>
        <span class="kpi-value"><?= (int) $stats['total'] ?></span>
        <span class="kpi-note"><?= $planId > 0 ? '当前计划' : '全部计划' ?></span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">未完成</span>
        <span class="kpi-value"><?= (int) $stats['open'] ?></span>
        <span class="kpi-note">待处理 + 进行中 + 待验收</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">已消项</span>
        <span class="kpi-value"><?= (int) $stats['counts']['closed'] ?></span>
        <span class="kpi-note">验收通过</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-label">逾期</span>
        <span class="kpi-value"><?= (int) $stats['overdue'] ?></span>
        <span class="kpi-note">今天 <?= $escape($today) ?></span>
    </div>
</section>

<section class="panel">
    <form method="get" class="toolbar" role="search" aria-label="筛选消项事项">
        <input type="hidden" name="page" value="closeout-items">
        <label>计划
            <select name="plan_id">
                <option value="0">全部计划</option>
                <?php foreach ($plans as $plan): ?>
                    <option value="<?= (int) $plan['plan_id'] ?>" <?= $planId === (int) $plan['plan_id'] ? 'selected' : '' ?>>
                        <?= $escape($plan['plan_no'] . ' · ' . $plan['plan_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>状态
            <select name="status">
                <option value="">全部状态</option>
                <?php foreach (CloseoutLabels::ITEM_STATUS as $value => $label): ?>
                    <option value="<?= $escape($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
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
                <option value="0">全部责任人</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['user_id'] ?>" <?= $responsible === (int) $user['user_id'] ? 'selected' : '' ?>><?= $escape($user['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox-field"><input type="checkbox" name="overdue" value="1" <?= $overdue === 1 ? 'checked' : '' ?>> 只看逾期</label>
        <label>搜索<input type="search" name="keyword" value="<?= $escape($keyword) ?>" placeholder="事项编号、名称、材料"></label>
        <div><button class="button button-secondary" type="submit">筛选</button></div>
        <span class="toolbar-count"><?= count($items) ?> 项</span>
    </form>

    <?php if ($items === []): ?>
        <div class="panel-body">
            <p class="empty-state">没有符合条件的消项事项。<?= $plans === [] ? '请先在总览页新建消项计划。' : '可在筛选条件中放宽范围，或从 BOM 继续纳入。' ?></p>
        </div>
    <?php else: ?>
        <?php if ($canEdit): ?>
        <form method="post" id="closeout-batch-form" data-closeout-batch>
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="closeout.batch.update">
            <?php if ($planId > 0): ?>
                <input type="hidden" name="plan_id" value="<?= $planId ?>">
            <?php endif; ?>
            <div class="panel-body">
                <div class="form-grid">
                    <label>批量计划开始<input type="date" name="planned_start_date"></label>
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
                </div>
                <div class="dialog-actions">
                    <button class="button button-secondary" type="submit">应用批量调整</button>
                    <span class="muted">只调整待处理、进行中、待验收的事项；已消项/已取消会跳过。留空字段保持不变。</span>
                </div>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($canEdit): ?>
                            <th><input type="checkbox" data-closeout-check-all aria-label="全选当前列表"></th>
                        <?php endif; ?>
                        <th>事项编号</th>
                        <th>事项</th>
                        <th>材料 / 分类</th>
                        <th>状态</th>
                        <th>风险</th>
                        <th>责任单位</th>
                        <th>责任人</th>
                        <th>计划周期</th>
                        <th>实际完成</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <?php if ($canEdit): ?>
                            <td><input type="checkbox" name="item_ids[]" value="<?= (int) $item['item_id'] ?>" data-closeout-check aria-label="选择 <?= $escape($item['item_no']) ?>"></td>
                        <?php endif; ?>
                        <td><strong><?= $escape($item['item_no']) ?></strong></td>
                        <td>
                            <?= $escape($item['display_name'] ?? $item['material_name'] ?? $item['title'] ?? '—') ?>
                            <span class="status-badge is-neutral"><?= $escape(CloseoutLabels::COVERAGE_MODE[$item['coverage_mode']] ?? (string) $item['coverage_mode']) ?></span>
                            <?php if (!empty($item['is_overdue'])): ?><span class="status-badge is-red">逾期</span><?php endif; ?>
                        </td>
                        <td>
                            <?= $escape($item['material_name'] ?? '—') ?>
                            <span class="muted"><?= $escape($item['category_name'] ?? '') ?></span>
                        </td>
                        <td><span class="status-badge <?= $escape(CloseoutLabels::itemTone((string) $item['status'])) ?>"><?= $escape($item['status_label']) ?></span></td>
                        <td><span class="status-badge <?= $escape(CloseoutLabels::riskTone((string) $item['risk_level'])) ?>"><?= $escape($item['risk_label']) ?></span></td>
                        <td><?= $escape($item['supplier_name'] ?? '—') ?></td>
                        <td><?= $escape($item['responsible_user_name'] ?? '—') ?></td>
                        <td class="muted"><?= $escape($item['planned_start_date'] ?? '—') ?> 至 <?= $escape($item['planned_end_date'] ?? '—') ?></td>
                        <td class="muted"><?= $escape($item['actual_complete_date'] ?? '—') ?></td>
                        <td>
                            <div class="project-list-actions">
                                <a class="button button-quiet" href="?page=closeout-item&amp;item_id=<?= (int) $item['item_id'] ?>">打开</a>
                                <?php if ($canAccept && $item['status'] === 'pending_accept'): ?>
                                    <a class="button button-primary" href="?page=closeout-item&amp;item_id=<?= (int) $item['item_id'] ?>#closeout-accept">验收</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canEdit): ?>
            <div class="panel-body">
                <div class="dialog-actions">
                    <button class="button button-primary" type="submit">保存所选事项的批量调整</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php $pageScripts = [['module' => 'closeout.management', 'file' => 'closeout.js', 'version' => (string) filemtime(dirname(__DIR__) . '/public/closeout.js')]]; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

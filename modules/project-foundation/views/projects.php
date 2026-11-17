<?php
$statusLabels = [
    'planning' => '计划中',
    'ongoing' => '进行中',
    'completed' => '已完成',
    'on-hold' => '已暂停',
];
$currentProjectId = is_array($currentProject) ? (int) $currentProject['project_id'] : 0;

// Portfolio report: counts derived only from the server-provided project list
// the viewer is allowed to see. No progress/cost figures are invented.
$statusCounts = ['planning' => 0, 'ongoing' => 0, 'completed' => 0, 'on-hold' => 0];
foreach ($projects as $project) {
    $key = (string) $project['status'];
    if (isset($statusCounts[$key])) {
        $statusCounts[$key]++;
    }
}

// Card click enters the project: select it (existing POST contract) and land
// on the first module the viewer may open inside the project.
$entryPage = 'projects';
foreach ([
    ['spaces', $canViewSpaces],
    ['materials', $canViewMaterials],
    ['documents', $canViewDocuments],
] as [$candidate, $allowed]) {
    if ($allowed) {
        $entryPage = $candidate;
        break;
    }
}
ob_start();
?>
<section class="page-heading projects-heading">
    <div>
        <p class="eyebrow">PROJECT OVERVIEW</p>
        <h1>项目首页</h1>
        <p class="muted">点击项目卡片直接进入项目内部。</p>
    </div>
    <div class="project-list-actions">
        <?php if ($archivedView): ?>
            <a class="button button-secondary" href="?page=projects">返回项目</a>
        <?php else: ?>
            <a class="button button-secondary" href="?page=projects&archived=1">▣ 已归档项目</a>
        <?php endif; ?>
        <?php if ($canCreate && !$archivedView): ?>
            <button class="button button-primary" type="button" data-dialog-open="create-project-dialog"><span aria-hidden="true">＋</span> 新建项目</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($canCreate && !$archivedView): ?>
    <dialog class="edit-dialog create-project-dialog" id="create-project-dialog" aria-labelledby="create-project-title">
        <div class="dialog-heading">
            <div>
                <h2 id="create-project-title">新建项目</h2>
                <p class="muted">创建后，项目空间、材料和文档等信息将独立归属于该项目。</p>
            </div>
            <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
        </div>
        <form method="post" action="" class="form-grid project-create-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="project.create">
            <label class="field-wide">项目名称 *<input name="project_name" maxlength="255" required placeholder="请输入项目名称"></label>
            <label>开始日期<input type="date" name="start_date"></label>
            <label>结束日期<input type="date" name="end_date"></label>
            <label class="field-wide">项目状态
                <select name="status">
                    <option value="planning">计划中</option>
                    <option value="ongoing">进行中</option>
                    <option value="completed">已完成</option>
                    <option value="on-hold">已暂停</option>
                </select>
            </label>
            <div class="field-wide dialog-actions">
                <button class="button button-secondary" type="button" data-dialog-close>取消</button>
                <button class="button button-primary" type="submit">创建并进入项目</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<?php if (!$archivedView): ?>
    <section class="stats portfolio-stats" aria-label="项目组合报表">
        <div class="stat">
            <span class="stat-label">项目总数</span>
            <span class="stat-value"><?= count($projects) ?></span>
            <span class="stat-foot">当前权限可见</span>
        </div>
        <?php foreach ($statusLabels as $value => $label): ?>
            <div class="stat">
                <span class="stat-label"><?= $escape($label) ?></span>
                <span class="stat-value"><?= (int) $statusCounts[$value] ?></span>
                <span class="stat-foot"><?= $value === 'ongoing' ? '施工中的项目' : ($value === 'completed' ? '已收尾的项目' : ($value === 'planning' ? '待启动的项目' : '暂停中的项目')) ?></span>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="portfolio-section" aria-labelledby="projects-list-title">
    <div class="section-head">
        <div>
            <h3 id="projects-list-title"><?= $archivedView ? '已归档项目' : '全部项目' ?></h3>
            <p><?= $archivedView ? '已归档的项目保留全部记录，可由管理员恢复。' : '项目基本信息来自项目基础模块；点击卡片进入项目内部。' ?></p>
        </div>
        <div class="section-tools">
            <?php if (!$archivedView): ?>
                <div class="tabs" role="group" aria-label="按状态筛选项目">
                    <button class="tab is-active" type="button" data-project-filter="" aria-pressed="true">全部</button>
                    <button class="tab" type="button" data-project-filter="ongoing" aria-pressed="false">进行中</button>
                    <button class="tab" type="button" data-project-filter="planning" aria-pressed="false">计划中</button>
                    <button class="tab" type="button" data-project-filter="completed" aria-pressed="false">已完成</button>
                    <button class="tab" type="button" data-project-filter="on-hold" aria-pressed="false">已暂停</button>
                </div>
            <?php endif; ?>
            <label class="search-field portfolio-search">
                <span class="sr-only">搜索项目名称</span>
                <input type="search" placeholder="⌕ 搜索项目名称" data-project-search autocomplete="off">
            </label>
            <span class="muted tiny" data-project-count data-unit=" 个项目" aria-live="polite"><?= count($projects) ?> 个项目</span>
        </div>
    </div>
    <?php if ($projects === []): ?>
        <div class="empty-projects">
            <span class="empty-projects-icon" aria-hidden="true">＋</span>
            <h3>从第一个项目开始</h3>
            <p class="muted"><?= $archivedView ? '没有已归档的项目。' : '创建项目后，空间、材料和文档等信息将独立归属于该项目。' ?></p>
            <?php if ($canCreate && !$archivedView): ?>
                <button class="button button-primary" type="button" data-dialog-open="create-project-dialog">新建项目</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="portfolio" data-project-grid>
            <?php foreach ($projects as $project): ?>
                <?php
                $isCurrentProject = $currentProjectId === (int) $project['project_id'];
                $projectSearch = mb_strtolower((string) $project['project_name'], 'UTF-8');
                ?>
                <article class="project-card<?= $isCurrentProject ? ' is-current' : '' ?><?= $archivedView ? '' : ' is-clickable' ?>"
                    data-project-card
                    data-search="<?= $escape($projectSearch) ?>"
                    data-status="<?= $escape((string) $project['status']) ?>"
                    <?php if (!$archivedView): ?>data-project-enter tabindex="0" role="link" aria-label="进入项目 <?= $escape($project['project_name']) ?>"<?php endif; ?>>
                    <?php if (!$archivedView): ?>
                        <form method="post" action="" class="project-enter-form" data-project-enter-form>
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="project.select">
                            <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                            <input type="hidden" name="return" value="<?= $escape($entryPage) ?>">
                        </form>
                    <?php endif; ?>
                    <div class="project-head">
                        <span class="mini-mark" aria-hidden="true"><?= $escape(mb_strtoupper(mb_substr((string) $project['project_name'], 0, 1))) ?></span>
                        <?php if ($archivedView): ?>
                            <span class="badge badge-neutral">已归档</span>
                        <?php else: ?>
                            <span class="status-pill status-<?= $escape((string) $project['status']) ?>"><?= $escape($statusLabels[$project['status']] ?? $project['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="project-card-title" title="<?= $escape($project['project_name']) ?>"><?= $escape($project['project_name']) ?></h4>
                    <div class="project-card-meta"><?= $isCurrentProject && !$archivedView ? '当前工作区' : '点击卡片进入项目' ?></div>
                    <div class="project-dates">
                        <span>开始 <?= $escape($project['start_date'] ?: '未设置') ?></span>
                        <span>结束 <?= $escape($project['end_date'] ?: '未设置') ?></span>
                    </div>
                    <div class="project-footer">
                        <?php if ($archivedView): ?>
                            <?php if ($canDeleteProjects): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="project.restore">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                    <button class="linkbtn" type="submit">恢复项目 →</button>
                                </form>
                            <?php else: ?>
                                <span class="muted tiny">仅管理员可恢复</span>
                            <?php endif; ?>
                        <?php elseif ($isCurrentProject): ?>
                            <span class="current-project-label"><span aria-hidden="true">✓</span> 当前项目</span>
                        <?php else: ?>
                            <span class="linkbtn" aria-hidden="true">进入项目 →</span>
                        <?php endif; ?>
                        <?php if (!$archivedView && $canDeleteProjects): ?>
                            <form method="post" data-confirm="删除空项目；如项目含有关联数据，将归档并保留记录。确定继续？">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="project.delete">
                                <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                <button class="linkbtn linkbtn-quiet" type="submit">删除 / 归档</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="empty-state" data-project-empty hidden>没有符合条件的项目。请调整筛选条件或搜索关键词。</p>
    <?php endif; ?>
    <div class="portfolio-foot">
        <a class="archive-link" href="?page=projects<?= $archivedView ? '' : '&archived=1' ?>"><?= $archivedView ? '返回项目列表 →' : '查看已归档项目 →' ?></a>
    </div>
</section>
<p class="footer-note"><span>Project O · 项目工作台</span><span>项目状态及日期来自项目基础信息；报表为当前可见项目的实时统计。</span></p>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

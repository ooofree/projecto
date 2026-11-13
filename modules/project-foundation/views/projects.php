<?php
$statusLabels = [
    'planning' => '计划中',
    'ongoing' => '进行中',
    'completed' => '已完成',
    'on-hold' => '已暂停',
];
$currentProjectId = is_array($currentProject) ? (int) $currentProject['project_id'] : 0;
ob_start();
?>
<section class="page-heading projects-heading">
    <div>
        <p class="eyebrow">PROJECT WORKSPACE</p>
        <h1>项目首页</h1>
        <p class="muted">从项目总览快速进入空间、材料和文档等日常工作。</p>
    </div>
    <?php if ($archivedView): ?>
        <a class="button button-secondary" href="?page=projects">返回项目</a>
    <?php else: ?>
        <a class="button button-secondary" href="?page=projects&archived=1">查看已归档</a>
    <?php endif; ?>
    <?php if ($canCreate && !$archivedView): ?>
        <details class="project-create" id="create-project">
            <summary class="button button-primary"><span aria-hidden="true">＋</span> 新建项目</summary>
            <form method="post" action="" class="form-grid project-create-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="project.create">
                <label class="field-wide">项目名称<input name="project_name" maxlength="255" required></label>
                <label>开始日期<input type="date" name="start_date"></label>
                <label>结束日期<input type="date" name="end_date"></label>
                <label>状态
                    <select name="status">
                        <option value="planning">计划中</option>
                        <option value="ongoing">进行中</option>
                        <option value="completed">已完成</option>
                        <option value="on-hold">已暂停</option>
                    </select>
                </label>
                <div class="field-wide"><button class="button button-primary" type="submit">创建并进入项目</button></div>
            </form>
        </details>
    <?php endif; ?>
</section>

<?php if (is_array($currentProject)): ?>
    <section class="project-hero" aria-label="当前项目">
        <div class="project-hero-main">
            <div class="project-hero-copy">
                <p class="eyebrow">当前工作项目</p>
                <div class="project-title-line">
                    <h2><?= $escape($currentProject['project_name']) ?></h2>
                    <span class="status-pill status-<?= $escape($currentProject['status']) ?>">
                        <span class="status-dot" aria-hidden="true"></span>
                        <?= $escape($statusLabels[$currentProject['status']] ?? $currentProject['status']) ?>
                    </span>
                </div>
                <p class="project-hero-hint">选择一个常用入口，继续处理当前项目。</p>
            </div>
            <div class="project-quick-links" aria-label="当前项目工作入口">
                <?php if ($canViewSpaces): ?>
                    <a class="quick-link" href="?page=spaces"><span class="quick-link-icon" aria-hidden="true">⌂</span><span>项目空间</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewMaterials): ?>
                    <a class="quick-link" href="?page=materials"><span class="quick-link-icon" aria-hidden="true">▦</span><span>材料管理</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewQuotations): ?>
                    <a class="quick-link" href="?page=quotations"><span class="quick-link-icon" aria-hidden="true">＄</span><span>材料报价</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewEstimates): ?>
                    <a class="quick-link" href="?page=estimates"><span class="quick-link-icon" aria-hidden="true">▤</span><span>材料测算</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewSamples): ?>
                    <a class="quick-link" href="?page=samples"><span class="quick-link-icon" aria-hidden="true">◇</span><span>样品跟踪</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewLaborCrews ?? false): ?>
                    <a class="quick-link" href="?page=labor-crews"><span class="quick-link-icon" aria-hidden="true">☎</span><span>施工班组</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <?php if ($canViewDocuments): ?>
                    <a class="quick-link" href="?page=documents"><span class="quick-link-icon" aria-hidden="true">▧</span><span>文档管理</span><span class="quick-link-arrow" aria-hidden="true">→</span></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="projects-section" aria-labelledby="projects-list-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">YOUR PORTFOLIO</p>
            <h2 id="projects-list-title"><?= $archivedView ? '已归档项目' : '全部项目' ?></h2>
        </div>
        <span class="project-count"><strong><?= count($projects) ?></strong> 个项目</span>
    </div>
    <?php if ($projects === []): ?>
        <div class="empty-projects">
            <span class="empty-projects-icon" aria-hidden="true">＋</span>
            <h3>从第一个项目开始</h3>
            <p class="muted"><?= $archivedView ? '没有已归档的项目。' : '创建项目后，空间、材料和文档等信息将独立归属于该项目。' ?></p>
            <?php if ($canCreate && !$archivedView): ?>
                <button class="button button-primary" type="button" data-disclosure-open="create-project">创建项目</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="project-grid">
            <?php foreach ($projects as $project): ?>
                <?php $isCurrentProject = $currentProjectId === (int) $project['project_id']; ?>
                <article class="project-card<?= $isCurrentProject ? ' is-current' : '' ?>">
                    <div class="project-card-top">
                        <span class="project-card-mark" aria-hidden="true"><?= $escape(mb_strtoupper(mb_substr((string) $project['project_name'], 0, 1))) ?></span>
                        <?php if ($archivedView): ?>
                            <span class="badge badge-neutral">已归档</span>
                        <?php else: ?>
                            <span class="status-pill status-<?= $escape($project['status']) ?>">
                                <span class="status-dot" aria-hidden="true"></span>
                                <?= $escape($statusLabels[$project['status']] ?? $project['status']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="project-card-title"><?= $escape($project['project_name']) ?></h3>
                    <div class="project-dates">
                        <span><span class="project-date-label">开始</span><?= $escape($project['start_date'] ?: '未设置') ?></span>
                        <span><span class="project-date-label">结束</span><?= $escape($project['end_date'] ?: '未设置') ?></span>
                    </div>
                    <div class="project-card-footer">
                        <?php if ($archivedView): ?>
                            <?php if ($canDeleteProjects): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="project.restore">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                    <button class="button button-secondary" type="submit">恢复项目</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">仅管理员可恢复</span>
                            <?php endif; ?>
                        <?php elseif ($isCurrentProject): ?>
                            <span class="current-project-label"><span aria-hidden="true">✓</span> 当前工作区</span>
                        <?php else: ?>
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="project.select">
                                <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                <button class="button button-secondary" type="submit">进入项目 <span aria-hidden="true">→</span></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$archivedView && $canDeleteProjects): ?>
                            <form method="post" data-confirm="删除空项目；如项目含有关联数据，将归档并保留记录。确定继续？">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="project.delete">
                                <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                <button class="button button-quiet" type="submit">删除 / 归档</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

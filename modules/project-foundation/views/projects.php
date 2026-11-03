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
<section class="page-heading">
    <div>
        <p class="eyebrow">工作空间</p>
        <h1 class="report-heading">项目</h1>
    </div>
</section>

<?php if (is_array($currentProject)): ?>
    <section class="current-project" aria-label="当前项目">
        <span class="muted">当前项目</span>
        <strong><?= $escape($currentProject['project_name']) ?></strong>
        <span class="badge"><?= $escape($statusLabels[$currentProject['status']] ?? $currentProject['status']) ?></span>
        <div class="project-quick-links" aria-label="当前项目工作入口">
            <?php if ($canViewDocuments): ?><a class="button" href="?page=documents">文档管理</a><?php endif; ?>
            <?php if ($canViewMaterials): ?><a class="button" href="?page=materials">材料目录</a><?php endif; ?>
            <?php if ($canViewQuotations): ?><a class="button" href="?page=quotations">材料报价</a><?php endif; ?>
            <?php if ($canViewSamples): ?><a class="button" href="?page=samples">材料样品</a><?php endif; ?>
            <?php if ($canViewSpaces): ?>
                <a class="button" href="?page=spaces">项目空间</a>
                <?php if ($canViewEstimates): ?><a class="button" href="?page=estimates">材料估算明细</a><?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>项目清单</h2>
        <div class="project-list-actions">
            <span class="muted"><?= count($projects) ?> 个项目</span>
            <?php if ($canCreate): ?>
                <details class="project-create" id="create-project">
                    <summary class="button button-quiet">新建项目</summary>
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
        </div>
    </div>
    <?php if ($projects === []): ?>
        <p class="empty-state">还没有项目。创建项目后即可开始使用。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="project-table">
                <thead>
                <tr>
                    <th scope="col">项目</th>
                    <th scope="col">状态</th>
                    <th scope="col">开始日期</th>
                    <th scope="col">结束日期</th>
                    <th scope="col" class="actions"><span class="sr-only">操作</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr class="<?= $currentProjectId === (int) $project['project_id'] ? 'project-current-row' : '' ?>">
                        <td class="project-name">
                            <?= $escape($project['project_name']) ?>
                            <?php if ($currentProjectId === (int) $project['project_id']): ?><span class="selected-label">当前工作区</span><?php endif; ?>
                        </td>
                        <td><span class="badge"><?= $escape($statusLabels[$project['status']] ?? $project['status']) ?></span></td>
                        <td><?= $escape($project['start_date'] ?: '—') ?></td>
                        <td><?= $escape($project['end_date'] ?: '—') ?></td>
                        <td class="actions">
                            <?php if ($currentProjectId !== (int) $project['project_id']): ?>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="project.select">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                    <button class="button" type="submit">选择项目</button>
                                </form>
                            <?php else: ?>
                                <span class="selected-label">当前项目</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

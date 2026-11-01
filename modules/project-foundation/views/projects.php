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
        <h1>项目</h1>
        <p class="muted">选择项目后，后续业务将在该项目上下文中开展。</p>
    </div>
</section>

<?php if (is_array($currentProject)): ?>
    <section class="current-project">
        <div>
            <p class="eyebrow">当前项目</p>
            <h2><?= $escape($currentProject['project_name']) ?></h2>
        </div>
        <span class="badge"><?= $escape($statusLabels[$currentProject['status']] ?? $currentProject['status']) ?></span>
        <?php if ($canViewDocuments): ?>
            <a class="button button-secondary" href="?page=documents">文档管理</a>
        <?php endif; ?>
        <?php if ($canViewMaterials): ?><a class="button button-secondary" href="?page=materials">材料目录</a><?php endif; ?>
        <?php if ($canViewQuotations): ?><a class="button button-secondary" href="?page=quotations">材料报价</a><?php endif; ?>
        <?php if ($canViewSamples): ?><a class="button button-secondary" href="?page=samples">材料样品</a><?php endif; ?>
        <?php if ($canViewSpaces): ?>
            <a class="button button-secondary" href="?page=spaces">项目空间</a>
            <?php if ($canViewEstimates): ?><a class="button button-secondary" href="?page=estimates">材料估算明细</a><?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="notice notice-info">请创建或选择一个项目以建立当前工作上下文。</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>项目列表</h2>
        <span class="muted"><?= count($projects) ?> 个项目</span>
    </div>
    <?php if ($projects === []): ?>
        <p class="empty-state">还没有项目。创建项目后即可开始使用。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>项目名称</th>
                    <th>状态</th>
                    <th>开始日期</th>
                    <th>结束日期</th>
                    <th><span class="sr-only">操作</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td class="project-name"><?= $escape($project['project_name']) ?></td>
                        <td><span class="badge"><?= $escape($statusLabels[$project['status']] ?? $project['status']) ?></span></td>
                        <td><?= $escape($project['start_date'] ?: '—') ?></td>
                        <td><?= $escape($project['end_date'] ?: '—') ?></td>
                        <td class="actions">
                            <?php if ($currentProjectId !== (int) $project['project_id']): ?>
                                <form method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="project.select">
                                    <input type="hidden" name="project_id" value="<?= (int) $project['project_id'] ?>">
                                    <button class="button button-secondary" type="submit">选择项目</button>
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

<?php if ($canCreate): ?>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>创建项目</h2>
                <p class="muted">项目名称、日期和状态使用旧系统 Projects 字段及状态值。</p>
            </div>
        </div>
        <form method="post" action="" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="project.create">
            <label class="field-wide">
                项目名称
                <input name="project_name" maxlength="255" required>
            </label>
            <label>
                开始日期
                <input type="date" name="start_date">
            </label>
            <label>
                结束日期
                <input type="date" name="end_date">
            </label>
            <label>
                状态
                <select name="status">
                    <option value="planning">计划中</option>
                    <option value="ongoing">进行中</option>
                    <option value="completed">已完成</option>
                    <option value="on-hold">已暂停</option>
                </select>
            </label>
            <div class="field-wide">
                <button class="button button-primary" type="submit">创建并选择项目</button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

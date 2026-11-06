<?php
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目协作</p>
        <h1>文档管理</h1>
        <p class="muted">当前项目：<?= $escape($currentProject['project_name']) ?></p>
    </div>
</section>

<nav class="module-nav" aria-label="文档管理">
    <a class="button button-secondary" href="?page=projects">项目首页</a>
    <a class="button button-primary" href="?page=documents" aria-current="page">文档管理首页</a>
    <a class="button button-secondary" href="?page=issues">问题管理</a>
    <a class="button button-secondary" href="?page=rfis">RFI 管理</a>
    <?php if (!empty($canManageSettings)): ?>
        <a class="button button-secondary" href="?page=document-settings">系统设置</a>
    <?php endif; ?>
</nav>

<section class="record-grid">
    <article class="panel record-summary">
        <p class="eyebrow">问题</p>
        <h2><?= (int) $issueCount ?> 条记录</h2>
        <p class="muted"><?= (int) $openIssueCount ?> 条待处理或审核中</p>
        <a href="?page=issues">进入问题管理</a>
    </article>
    <article class="panel record-summary">
        <p class="eyebrow">RFI</p>
        <h2><?= (int) $rfiCount ?> 份记录</h2>
        <p class="muted"><?= (int) $openRfiCount ?> 份尚未关闭</p>
        <a href="?page=rfis">进入 RFI 管理</a>
    </article>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

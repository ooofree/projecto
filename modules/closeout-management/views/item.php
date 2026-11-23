<?php
use ProjectO\Modules\CloseoutManagement\CloseoutLabels;
use ProjectO\Support;

$displayName = (string) ($item['display_name'] ?? $item['material_name'] ?? $item['title'] ?? '');
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工管理 · 工程消项 · 事项</p>
        <h1><?= $escape($item['item_no']) ?> · <?= $escape($displayName) ?></h1>
        <p class="muted">这个地址保留给直接链接与打印；日常查看和操作请回工作台，清单与详情在同一屏。</p>
    </div>
    <div class="project-list-actions">
        <a class="button button-primary" href="?page=closeouts&amp;plan_id=<?= (int) $item['plan_id'] ?>">返回工作台</a>
    </div>
</section>

<section class="panel closeout-standalone-panel">
    <?php
    $closeUrl = '?page=closeouts&plan_id=' . (int) $item['plan_id'];
    $standalone = true;
    $filters = [];
    $canManageSettings = $canManageSettings ?? false;
    require __DIR__ . '/_detail.php';
    ?>
</section>

<?php $pageScripts = [['module' => 'closeout.management', 'file' => 'closeout.js', 'version' => (string) filemtime(dirname(__DIR__) . '/public/closeout.js')]]; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

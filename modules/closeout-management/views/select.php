<?php
$candidateCount = count($candidates);
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工管理 · 工程消项 · 从 BOM 纳入消项事项</p>
        <h1><?= $escape($plan['plan_no'] . ' · ' . $plan['plan_name']) ?></h1>
        <p class="muted">一次性勾选需要收口的 BOM 材料并落到同一计划。只读取 BOM，不核销数量、不回写状态；提交后回到消项工作台。</p>
    </div>
    <div class="project-list-actions">
        <a class="button button-quiet" href="?page=closeouts&amp;plan_id=<?= (int) $plan['plan_id'] ?>">返回工作台</a>
    </div>
</section>

<?php
$selectPlan = $plan;
$selectCandidates = $candidates;
$selectHiddenMaterialCount = $hiddenMaterialCount;
$selectHasParentMaterials = $hasParentMaterials;
$selectUsers = $users;
$selectSuppliers = $suppliers;
require __DIR__ . '/_select-form.php';
?>

<?php $pageScripts = [['module' => 'closeout.management', 'file' => 'closeout.js', 'version' => (string) filemtime(dirname(__DIR__) . '/public/closeout.js')]]; ?>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

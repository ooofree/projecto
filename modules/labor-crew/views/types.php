<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工班组 · 共享参考目录</p>
        <h1>工种目录</h1>
        <p class="muted">工种跨项目共享，各项目的班组从这里选择工种。后续任务模块将按工种汇总班组投入。</p>
    </div>
</section>
<section class="panel">
    <div class="panel-heading"><h2>工种</h2><span class="muted"><?= count($types) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <details class="entry-disclosure">
            <summary>新增工种</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="labor.crew_type.save">
                <label>工种名称<input name="type_name" maxlength="100" required></label>
                <label>排序<input type="number" name="sort_order" min="0" max="9999" value="0"></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存工种</button></div>
            </form>
        </details>
    <?php endif; ?>
    <?php foreach ($types as $type): ?>
        <form method="post" class="form-grid record-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="labor.crew_type.save"><input type="hidden" name="crew_type_id" value="<?= (int) $type['crew_type_id'] ?>">
            <label>工种名称<input name="type_name" maxlength="100" value="<?= $escape($type['type_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
            <label>排序<input type="number" name="sort_order" min="0" max="9999" value="<?= (int) $type['sort_order'] ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
            <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
        </form>
        <?php if ($canDelete): ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="labor.crew_type.delete"><input type="hidden" name="crew_type_id" value="<?= (int) $type['crew_type_id'] ?>">
                <button class="button button-quiet" type="submit">删除未被班组使用的工种</button>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

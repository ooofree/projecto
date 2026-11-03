<?php ob_start(); require __DIR__ . '/_nav.php'; ?>
<section class="page-heading"><div><p class="eyebrow">材料主数据 · 全局目录</p><h1>辅材</h1><p class="muted">本期维护辅材主数据；BOM、用量换算和成本计算不在本范围。</p></div></section>
<section class="panel">
    <div class="panel-heading"><h2>辅材目录</h2><span class="muted"><?= count($auxiliaries) ?> 项</span></div>
    <?php if ($canEdit): ?>
        <details class="entry-disclosure">
            <summary>新增辅材</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.auxiliary.save">
                <label>辅材名称<input name="aux_name" maxlength="255" required></label><label>计量单位<input name="unit" maxlength="50" required></label><label class="field-wide">说明<textarea name="description" rows="2" maxlength="20000"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存辅材</button></div>
            </form>
        </details>
    <?php endif; ?>
    <?php foreach ($auxiliaries as $auxiliary): ?>
        <form method="post" class="form-grid record-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.auxiliary.save"><input type="hidden" name="aux_id" value="<?= (int) $auxiliary['aux_id'] ?>">
            <label>辅材名称<input name="aux_name" maxlength="255" value="<?= $escape($auxiliary['aux_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label><label>计量单位<input name="unit" maxlength="50" value="<?= $escape($auxiliary['unit']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
            <label class="field-wide">说明<textarea name="description" rows="2" maxlength="20000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($auxiliary['description'] ?? '') ?></textarea></label>
            <?php if ($canEdit): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
        </form>
        <?php if ($canEdit): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="material.auxiliary.delete"><input type="hidden" name="aux_id" value="<?= (int) $auxiliary['aux_id'] ?>"><button class="button button-quiet" type="submit">删除辅材</button></form><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

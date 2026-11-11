<?php
$typeFilter = isset($typeFilter) ? (int) $typeFilter : 0;
$keyword = isset($keyword) ? trim((string) $keyword) : '';
$filtered = [];
foreach ($crews as $crew) {
    if ($typeFilter > 0 && (int) $crew['crew_type_id'] !== $typeFilter) {
        continue;
    }
    if ($keyword !== '') {
        $haystack = mb_strtolower(
            $crew['crew_name'] . ' ' . $crew['leader_name'] . ' ' . $crew['leader_phone']
            . ' ' . ($crew['wechat_id'] ?? '') . ' ' . ($crew['notes'] ?? ''),
            'UTF-8'
        );
        if (!str_contains($haystack, mb_strtolower($keyword, 'UTF-8'))) {
            continue;
        }
    }
    $filtered[] = $crew;
}
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">施工班组 · 项目主数据</p>
        <h1>班组负责人联系方式</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。班组只对本次项目登记，后续任务模块按工种与班组取人。</p>
    </div>
    <div class="project-list-actions">
        <?php if ($canEdit): ?>
            <button class="button button-primary" type="button" data-dialog-open="crew-create-dialog">登记班组</button>
        <?php endif; ?>
        <form method="post" action="" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="labor.crew.export">
            <button class="button button-secondary" type="submit">导出 Excel</button>
        </form>
    </div>
</section>
<?php if (!$encryptionReady): ?>
    <div class="notice notice-info">服务器未配置数据加密密钥（APP_DATA_KEY），身份证号暂不可保存；其余联系方式不受影响。</div>
<?php endif; ?>
<?php if (!$archivedView && $canArchive && count($crews) > 0): ?>
    <p class="muted"><a class="button button-quiet" href="?page=labor-crews&archived=1">查看已归档班组</a></p>
<?php endif; ?>
<?php if ($archivedView): ?>
    <p class="muted">当前为已归档视图。<a class="button button-quiet" href="?page=labor-crews">返回在用班组</a></p>
<?php endif; ?>

<?php if ($canEdit): ?>
    <dialog class="edit-dialog" id="crew-create-dialog">
        <div class="dialog-heading"><h2>登记班组</h2><button class="button button-quiet" type="button" data-dialog-close>关闭</button></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="labor.crew.save">
            <label>班组名称<input name="crew_name" maxlength="255" required></label>
            <label>工种<select name="crew_type_id" required>
                <option value="">请选择工种</option>
                <?php foreach ($types as $type): ?><option value="<?= (int) $type['crew_type_id'] ?>"><?= $escape($type['type_name']) ?></option><?php endforeach; ?>
            </select></label>
            <label>排序<input type="number" name="sort_order" min="0" max="9999" value="0" title="值小者在前"></label>
            <label>负责人姓名<input name="leader_name" maxlength="100" required></label>
            <label>负责人手机号<input name="leader_phone" maxlength="30" required></label>
            <label>微信号<input name="wechat_id" maxlength="100"></label>
            <label>身份证号<input name="id_card_number" maxlength="25" inputmode="numeric" placeholder="18 位，选填"></label>
            <label>紧急联系人姓名<input name="emergency_contact_name" maxlength="100"></label>
            <label>紧急联系人电话<input name="emergency_contact_phone" maxlength="30"></label>
            <label class="field-wide">备注<textarea name="notes" rows="2" maxlength="2000" placeholder="常驻区域、擅长工艺等"></textarea></label>
            <div class="dialog-actions"><button class="button button-primary" type="submit">保存班组</button></div>
        </form>
    </dialog>
<?php endif; ?>

<section class="panel">
    <form method="get" class="toolbar" role="search" aria-label="筛选班组">
        <input type="hidden" name="page" value="labor-crews">
        <?php if ($archivedView): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <label>搜索<input type="search" name="keyword" value="<?= $escape($keyword) ?>" placeholder="班组、负责人、电话或备注"></label>
        <label>工种<select name="type">
            <option value="0">全部工种</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['crew_type_id'] ?>" <?= $typeFilter === (int) $type['crew_type_id'] ? 'selected' : '' ?>><?= $escape($type['type_name']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <div><button class="button button-secondary" type="submit">筛选</button></div>
        <span class="toolbar-count"><?= count($filtered) ?> 个班组</span>
    </form>

    <?php if ($filtered === []): ?>
        <p class="muted"><?= $crews === [] ? '当前项目还没有班组，点击「登记班组」开始。' : '没有符合筛选条件的班组。' ?></p>
    <?php endif; ?>

    <?php foreach ($filtered as $crew): ?>
        <?php
        $displayId = $crew['id_card_masked'];
        if ($canViewIdNumbers && !empty($crew['id_card_full'])) {
            $displayId = (string) $crew['id_card_full'];
        }
        ?>
        <details class="record-card">
            <summary>
                <strong><?= $escape($crew['crew_name']) ?></strong>
                <span class="badge badge-neutral"><?= $escape($crew['type_name']) ?></span>
                <span class="muted"><?= $escape($crew['leader_name']) ?> · <?= $escape($crew['leader_phone']) ?></span>
                <span class="muted">排序 <?= (int) $crew['sort_order'] ?></span>
                <?php if ((int) $crew['is_active'] !== 1): ?><span class="status-badge">已归档</span><?php endif; ?>
            </summary>
            <form method="post" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="labor.crew.save">
                <input type="hidden" name="crew_id" value="<?= (int) $crew['crew_id'] ?>">
                <?php if ($archivedView): ?><input type="hidden" name="from_archived" value="1"><?php endif; ?>
                <label>班组名称<input name="crew_name" maxlength="255" value="<?= $escape($crew['crew_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>工种<select name="crew_type_id" required <?= $canEdit ? '' : 'disabled' ?>>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['crew_type_id'] ?>" <?= (int) $type['crew_type_id'] === (int) $crew['crew_type_id'] ? 'selected' : '' ?>><?= $escape($type['type_name']) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label>排序<input type="number" name="sort_order" min="0" max="9999" value="<?= (int) $crew['sort_order'] ?>" title="值小者在前" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>负责人姓名<input name="leader_name" maxlength="100" value="<?= $escape($crew['leader_name']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>负责人手机号<input name="leader_phone" maxlength="30" value="<?= $escape($crew['leader_phone']) ?>" required <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>微信号<input name="wechat_id" maxlength="100" value="<?= $escape($crew['wechat_id'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>身份证号<input maxlength="25" value="<?= $escape($displayId) ?>" disabled></label>
                <?php if ($canEdit): ?>
                    <label>身份证号（修改）<input name="id_card_number" maxlength="25" placeholder="留空保持不变"></label>
                    <label class="checkbox-field"><input type="checkbox" name="id_card_clear" value="1"> 清除已保存的身份证号</label>
                <?php endif; ?>
                <label>紧急联系人姓名<input name="emergency_contact_name" maxlength="100" value="<?= $escape($crew['emergency_contact_name'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label>紧急联系人电话<input name="emergency_contact_phone" maxlength="30" value="<?= $escape($crew['emergency_contact_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>></label>
                <label class="field-wide">备注<textarea name="notes" rows="2" maxlength="2000" <?= $canEdit ? '' : 'disabled' ?>><?= $escape($crew['notes'] ?? '') ?></textarea></label>
                <p class="muted field-wide">最后更新：<?= $escape(\ProjectO\Support::formatDateTime($crew['updated_at'] ?? null)) ?></p>
                <?php if ($canEdit): ?><div class="dialog-actions"><button class="button button-secondary" type="submit">保存班组</button></div><?php endif; ?>
            </form>
            <?php if ($canEdit && (int) $crew['is_active'] === 1 && $canArchive): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="labor.crew.archive">
                    <input type="hidden" name="crew_id" value="<?= (int) $crew['crew_id'] ?>">
                <?php if ($archivedView): ?><input type="hidden" name="from_archived" value="1"><?php endif; ?>
                    <button class="button button-quiet" type="submit">归档班组</button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit && (int) $crew['is_active'] !== 1 && $canArchive): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="labor.crew.restore">
                    <input type="hidden" name="crew_id" value="<?= (int) $crew['crew_id'] ?>">
                <?php if ($archivedView): ?><input type="hidden" name="from_archived" value="1"><?php endif; ?>
                    <button class="button button-quiet" type="submit">恢复班组</button>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

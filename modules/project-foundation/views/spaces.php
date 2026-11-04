<?php
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目基础 · 空间台账</p>
        <h1>项目空间<?= $archivedView ? ' · 删除与归档' : '' ?></h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。楼栋、楼层、区域和房间均由当前项目上下文确定。</p>
    </div>
</section>
<nav class="module-nav" aria-label="项目空间">
    <a class="button button-secondary" href="?page=projects">项目首页</a>
    <a class="button button-<?= $archivedView ? 'secondary' : 'primary' ?>" href="?page=spaces"<?= !$archivedView ? ' aria-current="page"' : '' ?>>当前空间</a>
    <a class="button button-<?= $archivedView ? 'primary' : 'secondary' ?>" href="?page=spaces&amp;archived=1"<?= $archivedView ? ' aria-current="page"' : '' ?>>空间回收站</a>
</nav>

<?php if (!$canManage): ?><div class="notice notice-info">当前账号仅有查看权限。</div><?php endif; ?>
<section class="panel">
    <div class="panel-heading">
        <h2><?= $archivedView ? '空间回收站（包含当前结构）' : '空间结构' ?></h2>
        <span class="muted"><?= count($buildings) ?> 栋</span>
    </div>
    <?php if ($canManage && !$archivedView): ?>
        <details class="entry-disclosure">
            <summary>新增楼栋</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="space.save">
                <input type="hidden" name="type" value="building">
                <label>楼栋编号<input name="number" maxlength="50" required></label>
                <label>楼栋名称<input name="name" maxlength="255" required></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存楼栋</button></div>
            </form>
        </details>
    <?php endif; ?>

    <?php if ($buildings === []): ?>
        <p class="empty-state">当前项目尚无楼栋。</p>
    <?php else: ?>
        <?php foreach ($buildings as $building): ?>
            <article class="record-card">
                <div class="record-heading">
                    <div>
                        <p class="eyebrow">楼栋 <?= $escape($building['building_number']) ?><?= (int) $building['is_archived'] === 1 ? ' · 已归档' : '' ?></p>
                        <h3><?= $escape($building['building_name']) ?></h3>
                    </div>
                    <?php if ($canManage && $archivedView && (int) $building['is_archived'] === 1): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="space.restore">
                            <input type="hidden" name="type" value="building">
                            <input type="hidden" name="id" value="<?= (int) $building['building_id'] ?>">
                            <button class="button button-secondary" type="submit">恢复楼栋</button>
                        </form>
                    <?php elseif ($canManage && !$archivedView): ?>
                        <form method="post" data-confirm="删除无关联楼栋；如存在下级或业务关联，将归档并保留记录。确定继续？">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="space.delete">
                            <input type="hidden" name="type" value="building">
                            <input type="hidden" name="id" value="<?= (int) $building['building_id'] ?>">
                            <button class="button button-quiet" type="submit">删除 / 归档</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($canManage && !$archivedView): ?>
                    <details class="record-tools">
                        <summary>编辑楼栋</summary>
                        <form method="post" class="form-grid record-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="space.save">
                            <input type="hidden" name="type" value="building">
                            <input type="hidden" name="id" value="<?= (int) $building['building_id'] ?>">
                            <label>楼栋编号<input name="number" maxlength="50" value="<?= $escape($building['building_number']) ?>" required></label>
                            <label>楼栋名称<input name="name" maxlength="255" value="<?= $escape($building['building_name']) ?>" required></label>
                            <div class="field-wide"><button class="button button-secondary" type="submit">保存楼栋</button></div>
                        </form>
                    </details>
                <?php endif; ?>

                <div class="form-grid">
                    <section class="field-wide">
                        <h4>楼层</h4>
                        <?php foreach ($building['floors'] as $floor): ?>
                            <?php if ((int) $floor['is_archived'] === 1): ?>
                                <p class="eyebrow">楼层 <?= $escape($floor['floor_number']) ?> · 已归档</p>
                                <?php if ($archivedView): ?><p class="muted"><?= $escape($floor['floor_name'] ?: '未命名') ?> · 用途：<?= $escape($floor['usage_type'] ?: '未设置') ?></p><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$archivedView): ?><form method="post" class="form-grid record-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="floor">
                                <input type="hidden" name="id" value="<?= (int) $floor['floor_id'] ?>">
                                <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                <label>楼层编号<input name="number" maxlength="50" value="<?= $escape($floor['floor_number']) ?>" required></label>
                                <label>楼层名称<input name="name" maxlength="50" value="<?= $escape($floor['floor_name'] ?? '') ?>"></label>
                                <label>用途<input name="usage_type" maxlength="255" value="<?= $escape($floor['usage_type'] ?? '') ?>"></label>
                                <?php if ($canManage): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
                            </form><?php endif; ?>
                            <?php if ($canManage && $archivedView && (int) $floor['is_archived'] === 1): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.restore"><input type="hidden" name="type" value="floor"><input type="hidden" name="id" value="<?= (int) $floor['floor_id'] ?>">
                                    <button class="button button-secondary" type="submit">恢复楼层 <?= $escape($floor['floor_number']) ?></button>
                                </form>
                            <?php elseif ($canManage && !$archivedView): ?>
                                <form method="post" class="inline-form" data-confirm="删除无关联楼层；如有房间或业务关联，将归档并保留记录。确定继续？">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.delete"><input type="hidden" name="type" value="floor"><input type="hidden" name="id" value="<?= (int) $floor['floor_id'] ?>">
                                    <button class="button button-quiet" type="submit">删除 / 归档楼层</button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($canManage && !$archivedView): ?>
                            <details class="entry-disclosure">
                                <summary>新增楼层</summary>
                                <form method="post" class="form-grid record-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.save">
                                    <input type="hidden" name="type" value="floor">
                                    <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                    <label>楼层编号<input name="number" maxlength="50" required></label>
                                    <label>楼层名称<input name="name" maxlength="50"></label>
                                    <label>用途<input name="usage_type" maxlength="255"></label>
                                    <button class="button button-primary" type="submit">新增楼层</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </section>

                    <section class="field-wide">
                        <h4>施工区域</h4>
                        <?php foreach ($building['areas'] as $area): ?>
                            <?php if ((int) $area['is_archived'] === 1): ?>
                                <p class="eyebrow">区域 <?= $escape($area['area_name']) ?> · 已归档</p>
                                <?php if ($archivedView && $area['description']): ?><p class="muted"><?= $escape($area['description']) ?></p><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$archivedView): ?><form method="post" class="form-grid record-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="area">
                                <input type="hidden" name="id" value="<?= (int) $area['area_id'] ?>">
                                <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                <label>区域名称<input name="name" maxlength="255" value="<?= $escape($area['area_name']) ?>" required></label>
                                <label>说明<input name="description" maxlength="2000" value="<?= $escape($area['description'] ?? '') ?>"></label>
                                <?php if ($canManage): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
                            </form><?php endif; ?>
                            <?php if ($canManage && $archivedView && (int) $area['is_archived'] === 1): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.restore"><input type="hidden" name="type" value="area"><input type="hidden" name="id" value="<?= (int) $area['area_id'] ?>">
                                    <button class="button button-secondary" type="submit">恢复区域</button>
                                </form>
                            <?php elseif ($canManage && !$archivedView): ?>
                                <form method="post" class="inline-form" data-confirm="删除无关联区域；如存在房间关联或业务数据，将归档并保留记录。确定继续？">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.delete"><input type="hidden" name="type" value="area"><input type="hidden" name="id" value="<?= (int) $area['area_id'] ?>">
                                    <button class="button button-quiet" type="submit">删除 / 归档区域</button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($canManage && !$archivedView): ?>
                            <details class="entry-disclosure">
                                <summary>新增施工区域</summary>
                                <form method="post" class="form-grid record-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.save">
                                    <input type="hidden" name="type" value="area">
                                    <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                    <label>区域名称<input name="name" maxlength="255" required></label>
                                    <label>说明<input name="description" maxlength="2000"></label>
                                    <button class="button button-primary" type="submit">新增区域</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </section>

                    <section class="field-wide">
                        <h4>房间</h4>
                        <?php foreach ($building['rooms'] as $room): ?>
                            <?php if ((int) $room['is_archived'] === 1): ?>
                                <p class="eyebrow">房间 <?= $escape($room['room_number']) ?> · 已归档</p>
                                <?php if ($archivedView): ?><p class="muted">楼层 <?= $escape($room['floor_number']) ?> · 类型：<?= $escape($room['room_type'] ?: '未设置') ?></p><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$archivedView): ?><form method="post" class="form-grid record-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="room">
                                <input type="hidden" name="id" value="<?= (int) $room['room_id'] ?>">
                                <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                <input type="hidden" name="floor_id" value="<?= (int) $room['floor_id'] ?>">
                                <label>楼层<input value="<?= $escape($room['floor_number']) ?>" disabled></label>
                                <label>房间号<input name="number" maxlength="50" value="<?= $escape($room['room_number']) ?>" required></label>
                                <label>房间类型<input name="room_type" maxlength="50" value="<?= $escape($room['room_type'] ?? '') ?>"></label>
                                <?php if ($canManage): ?><button class="button button-secondary" type="submit">保存</button><?php endif; ?>
                            </form><?php endif; ?>
                            <?php if ($canManage && $archivedView && (int) $room['is_archived'] === 1): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.restore"><input type="hidden" name="type" value="room"><input type="hidden" name="id" value="<?= (int) $room['room_id'] ?>">
                                    <button class="button button-secondary" type="submit">恢复房间</button>
                                </form>
                            <?php elseif ($canManage && !$archivedView): ?>
                                <form method="post" class="inline-form" data-confirm="删除无关联房间；如存在材料或业务关联，将归档并保留记录。确定继续？">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="space.delete"><input type="hidden" name="type" value="room"><input type="hidden" name="id" value="<?= (int) $room['room_id'] ?>">
                                    <button class="button button-quiet" type="submit">删除 / 归档房间</button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($canManage && !$archivedView && $building['floors'] !== []): ?>
                            <details class="entry-disclosure">
                                <summary>新增房间</summary>
                                <form method="post" class="form-grid record-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.save">
                                    <input type="hidden" name="type" value="room">
                                    <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                                    <label>楼层<select name="floor_id" required><?php foreach ($building['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>"><?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
                                    <label>房间号<input name="number" maxlength="50" required></label>
                                    <label>房间类型<input name="room_type" maxlength="50"></label>
                                    <button class="button button-primary" type="submit">新增房间</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </section>
                </div>

                <?php if ($building['areas'] !== [] && $building['floors'] !== [] && $building['rooms'] !== []): ?>
                    <details class="record-tools">
                        <summary>区域房间关联（<?= count($building['area_rooms']) ?>）</summary>
                        <?php foreach ($building['area_rooms'] as $mapping): ?>
                            <form method="post" class="inline-form">
                                <span><?= $escape($mapping['area_name']) ?> · <?= $escape($mapping['floor_number']) ?> · <?= $escape($mapping['room_number']) ?></span>
                                <?php if ($canManage && !$archivedView): ?>
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.room.unmap">
                                    <input type="hidden" name="mapping_id" value="<?= (int) $mapping['area_room_id'] ?>">
                                    <button class="button button-quiet" type="submit">解除关联</button>
                                <?php endif; ?>
                            </form>
                        <?php endforeach; ?>
                        <?php if ($canManage && !$archivedView): ?>
                            <form method="post" class="form-grid record-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.room.map">
                                <label>区域<select name="area_id" required><?php foreach ($building['areas'] as $area): ?><option value="<?= (int) $area['area_id'] ?>"><?= $escape($area['area_name']) ?></option><?php endforeach; ?></select></label>
                                <label>楼层<select name="floor_id" required><?php foreach ($building['floors'] as $floor): ?><option value="<?= (int) $floor['floor_id'] ?>"><?= $escape($floor['floor_number']) ?></option><?php endforeach; ?></select></label>
                                <label>房间<select name="room_id" required><?php foreach ($building['rooms'] as $room): ?><option value="<?= (int) $room['room_id'] ?>"><?= $escape($room['floor_number'] . ' · ' . $room['room_number']) ?></option><?php endforeach; ?></select></label>
                                <button class="button button-primary" type="submit">关联房间</button>
                            </form>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

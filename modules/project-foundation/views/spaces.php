<?php
ob_start();
?>
<section class="page-heading report-intro">
    <div>
        <p class="eyebrow">项目基础 · 空间台账</p>
        <h1>项目空间<?= $archivedView ? ' · 删除与归档' : '' ?></h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。按楼栋 → 楼层 → 房间的树状结构维护。</p>
    </div>
    <?php if ($canManage && !$archivedView): ?>
        <div class="project-list-actions">
            <button class="button button-quiet" type="button" data-dialog-open="space-building-create">新增楼栋</button>
        </div>
    <?php endif; ?>
</section>
<nav class="module-nav" aria-label="项目空间">
    <a class="button button-secondary" href="?page=projects">项目首页</a>
    <a class="button button-<?= $archivedView ? 'secondary' : 'primary' ?>" href="?page=spaces"<?= !$archivedView ? ' aria-current="page"' : '' ?>>当前空间</a>
    <a class="button button-<?= $archivedView ? 'primary' : 'secondary' ?>" href="?page=spaces&amp;archived=1"<?= $archivedView ? ' aria-current="page"' : '' ?>>空间回收站</a>
</nav>

<?php if (!$canManage): ?>
    <div class="notice notice-info">当前账号仅有查看权限。</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2><?= $archivedView ? '空间回收站（包含当前结构）' : '空间结构' ?></h2>
            <p class="muted"><?= $archivedView ? '已归档的记录可在这里恢复；标记为“当前”的记录仍在使用中。' : '展开楼层查看房间；新增楼层在楼栋下，新增房间在所属楼层内完成。' ?></p>
        </div>
        <span class="muted"><?= count($buildings) ?> 栋</span>
    </div>

    <?php if ($buildings === []): ?>
        <p class="empty-state">当前项目尚无楼栋。<?= $canManage && !$archivedView ? '使用页面右上角“新增楼栋”开始登记。' : '' ?></p>
    <?php else: ?>
        <?php foreach ($buildings as $building): ?>
            <?php
            $buildingId = (int) $building['building_id'];
            $buildingArchived = (int) $building['is_archived'] === 1;
            $floors = $building['floors'];
            $rooms = $building['rooms'];
            $roomsByFloor = [];
            foreach ($rooms as $room) {
                $roomsByFloor[(int) $room['floor_id']][] = $room;
            }
            ?>
            <article class="record-card space-building">
                <div class="record-heading">
                    <div>
                        <p class="eyebrow">楼栋 <?= $escape($building['building_number']) ?><?= $buildingArchived ? ' · 已归档' : '' ?></p>
                        <h3><?= $escape($building['building_name']) ?></h3>
                        <p class="space-node-meta space-building-meta"><?= count($floors) ?> 层 · <?= count($rooms) ?> 个房间</p>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="record-badges">
                            <?php if ($archivedView && $buildingArchived): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.restore">
                                    <input type="hidden" name="type" value="building">
                                    <input type="hidden" name="id" value="<?= $buildingId ?>">
                                    <button class="button button-secondary" type="submit">恢复楼栋</button>
                                </form>
                            <?php elseif (!$archivedView): ?>
                                <button class="button button-secondary" type="button" data-dialog-open="space-building-edit-<?= $buildingId ?>">编辑楼栋</button>
                                <form method="post" data-confirm="删除无关联楼栋；如存在下级或业务关联，将归档并保留记录。确定继续？">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.delete">
                                    <input type="hidden" name="type" value="building">
                                    <input type="hidden" name="id" value="<?= $buildingId ?>">
                                    <button class="button button-quiet" type="submit">删除 / 归档</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="space-tree">
                    <?php if ($floors === []): ?>
                        <p class="space-empty">尚无楼层。<?= $canManage && !$archivedView ? '使用下方“新增楼层”开始登记。' : '' ?></p>
                    <?php else: ?>
                        <?php foreach ($floors as $floor): ?>
                            <?php
                            $floorId = (int) $floor['floor_id'];
                            $floorArchived = (int) $floor['is_archived'] === 1;
                            $floorRooms = $roomsByFloor[$floorId] ?? [];
                            ?>
                            <details class="space-floor" open>
                                <summary class="space-floor-head">
                                    <span class="space-floor-label">
                                        <span class="space-floor-number"><?= $escape($floor['floor_number']) ?></span>
                                        <span class="space-floor-name"><?= $floor['floor_name'] ? $escape($floor['floor_name']) : '未命名楼层' ?></span>
                                        <?php if (($floor['usage_type'] ?? '') !== ''): ?>
                                            <span class="space-node-meta"><?= $escape($floor['usage_type']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($archivedView): ?>
                                            <span class="status-badge <?= $floorArchived ? 'is-neutral' : 'is-green' ?>"><?= $floorArchived ? '已归档' : '当前' ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="space-floor-count"><?= count($floorRooms) ?> 间</span>
                                </summary>
                                <div class="space-floor-body">
                                    <?php if ($canManage): ?>
                                        <div class="space-floor-actions">
                                            <?php if ($archivedView && $floorArchived): ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="space.restore">
                                                    <input type="hidden" name="type" value="floor">
                                                    <input type="hidden" name="id" value="<?= $floorId ?>">
                                                    <button class="button button-secondary" type="submit">恢复楼层</button>
                                                </form>
                                            <?php elseif (!$archivedView): ?>
                                                <button class="button button-secondary" type="button" data-dialog-open="space-floor-edit-<?= $floorId ?>">编辑楼层</button>
                                                <form method="post" data-confirm="删除无关联楼层；如有房间或业务关联，将归档并保留记录。确定继续？">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="space.delete">
                                                    <input type="hidden" name="type" value="floor">
                                                    <input type="hidden" name="id" value="<?= $floorId ?>">
                                                    <button class="button button-quiet" type="submit">删除 / 归档楼层</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($floorRooms === []): ?>
                                        <p class="space-empty">本楼层暂无房间。</p>
                                    <?php else: ?>
                                        <ul class="space-room-list">
                                            <?php foreach ($floorRooms as $room): ?>
                                                <?php
                                                $roomId = (int) $room['room_id'];
                                                $roomArchived = (int) $room['is_archived'] === 1;
                                                ?>
                                                <li class="space-node-row space-room">
                                                    <span class="space-node-main">
                                                        <span class="space-node-title"><?= $escape($room['room_number']) ?></span>
                                                        <span class="space-node-meta"><?= ($room['room_type'] ?? '') !== '' ? $escape($room['room_type']) : '未设置类型' ?></span>
                                                    </span>
                                                    <?php if ($archivedView): ?>
                                                        <span class="status-badge <?= $roomArchived ? 'is-neutral' : 'is-green' ?>"><?= $roomArchived ? '已归档' : '当前' ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($canManage): ?>
                                                        <div class="row-actions">
                                                            <?php if ($archivedView && $roomArchived): ?>
                                                                <form method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                                    <input type="hidden" name="action" value="space.restore">
                                                                    <input type="hidden" name="type" value="room">
                                                                    <input type="hidden" name="id" value="<?= $roomId ?>">
                                                                    <button class="button button-secondary" type="submit">恢复</button>
                                                                </form>
                                                            <?php elseif (!$archivedView): ?>
                                                                <button class="button button-secondary" type="button" data-dialog-open="space-room-edit-<?= $roomId ?>">编辑</button>
                                                                <form method="post" data-confirm="删除无关联房间；如存在材料或业务关联，将归档并保留记录。确定继续？">
                                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                                    <input type="hidden" name="action" value="space.delete">
                                                                    <input type="hidden" name="type" value="room">
                                                                    <input type="hidden" name="id" value="<?= $roomId ?>">
                                                                    <button class="button button-quiet" type="submit">删除 / 归档</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if ($canManage && !$archivedView && !$floorArchived): ?>
                                        <button class="space-add-node" type="button" data-dialog-open="space-room-create-<?= $floorId ?>">＋ 新增房间</button>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($canManage && !$archivedView): ?>
                        <button class="space-add-node space-add-floor" type="button" data-dialog-open="space-floor-create-<?= $buildingId ?>">＋ 新增楼层</button>
                    <?php endif; ?>
                </div>

                <?php if ($canManage && !$archivedView): ?>
                    <dialog class="edit-dialog" id="space-floor-create-<?= $buildingId ?>" aria-labelledby="space-floor-create-title-<?= $buildingId ?>">
                        <div class="dialog-heading">
                            <div>
                                <p class="eyebrow">楼层</p>
                                <h2 id="space-floor-create-title-<?= $buildingId ?>">新增楼层</h2>
                            </div>
                            <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                        </div>
                        <form method="post" class="form-grid">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="space.save">
                            <input type="hidden" name="type" value="floor">
                            <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                            <label>楼层编号<input name="number" maxlength="50" required></label>
                            <label>楼层名称<input name="name" maxlength="50"></label>
                            <label>用途<input name="usage_type" maxlength="255"></label>
                            <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存楼层</button></div>
                        </form>
                    </dialog>

                    <?php foreach ($floors as $floor): ?>
                        <?php if ((int) $floor['is_archived'] === 1) { continue; } ?>
                        <dialog class="edit-dialog" id="space-room-create-<?= (int) $floor['floor_id'] ?>" aria-labelledby="space-room-create-title-<?= (int) $floor['floor_id'] ?>">
                            <div class="dialog-heading">
                                <div>
                                    <p class="eyebrow">房间</p>
                                    <h2 id="space-room-create-title-<?= (int) $floor['floor_id'] ?>">在楼层 <?= $escape($floor['floor_number']) ?> 新增房间</h2>
                                </div>
                                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                            </div>
                            <form method="post" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="room">
                                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                <input type="hidden" name="floor_id" value="<?= (int) $floor['floor_id'] ?>">
                                <label>楼层<input value="<?= $escape($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?>" disabled></label>
                                <label>房间号<input name="number" maxlength="50" required></label>
                                <label>房间类型<input name="room_type" maxlength="50"></label>
                                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存房间</button></div>
                            </form>
                        </dialog>
                    <?php endforeach; ?>

                    <?php foreach ($floors as $floor): ?>
                        <dialog class="edit-dialog" id="space-floor-edit-<?= (int) $floor['floor_id'] ?>" aria-labelledby="space-floor-edit-title-<?= (int) $floor['floor_id'] ?>">
                            <div class="dialog-heading">
                                <div>
                                    <p class="eyebrow">楼层</p>
                                    <h2 id="space-floor-edit-title-<?= (int) $floor['floor_id'] ?>">编辑楼层 <?= $escape($floor['floor_number']) ?></h2>
                                </div>
                                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                            </div>
                            <form method="post" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="floor">
                                <input type="hidden" name="id" value="<?= (int) $floor['floor_id'] ?>">
                                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                <label>楼层编号<input name="number" maxlength="50" value="<?= $escape($floor['floor_number']) ?>" required></label>
                                <label>楼层名称<input name="name" maxlength="50" value="<?= $escape($floor['floor_name'] ?? '') ?>"></label>
                                <label>用途<input name="usage_type" maxlength="255" value="<?= $escape($floor['usage_type'] ?? '') ?>"></label>
                                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
                            </form>
                        </dialog>
                    <?php endforeach; ?>

                    <?php foreach ($rooms as $room): ?>
                        <dialog class="edit-dialog" id="space-room-edit-<?= (int) $room['room_id'] ?>" aria-labelledby="space-room-edit-title-<?= (int) $room['room_id'] ?>">
                            <div class="dialog-heading">
                                <div>
                                    <p class="eyebrow">房间</p>
                                    <h2 id="space-room-edit-title-<?= (int) $room['room_id'] ?>">编辑房间 <?= $escape($room['room_number']) ?></h2>
                                </div>
                                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                            </div>
                            <form method="post" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="space.save">
                                <input type="hidden" name="type" value="room">
                                <input type="hidden" name="id" value="<?= (int) $room['room_id'] ?>">
                                <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                                <input type="hidden" name="floor_id" value="<?= (int) $room['floor_id'] ?>">
                                <label>楼层<input value="<?= $escape($room['floor_number']) ?>" disabled></label>
                                <label>房间号<input name="number" maxlength="50" value="<?= $escape($room['room_number']) ?>" required></label>
                                <label>房间类型<input name="room_type" maxlength="50" value="<?= $escape($room['room_type'] ?? '') ?>"></label>
                                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
                            </form>
                        </dialog>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php if ($canManage && !$archivedView): ?>
    <dialog class="edit-dialog" id="space-building-create" aria-labelledby="space-building-create-title">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">楼栋</p>
                <h2 id="space-building-create-title">新增楼栋</h2>
            </div>
            <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="space.save">
            <input type="hidden" name="type" value="building">
            <label>楼栋编号<input name="number" maxlength="50" required></label>
            <label>楼栋名称<input name="name" maxlength="255" required></label>
            <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存楼栋</button></div>
        </form>
    </dialog>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

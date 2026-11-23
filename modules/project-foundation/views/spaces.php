<?php
ob_start();

/**
 * 项目空间 · 空间甲板（Space Deck）
 *
 * 布局目标（用户 2026-11-23 反馈：布局不好、录入慢、不美观，希望“现代化、能展开、像游戏”）：
 *  - 左侧关卡式目录：楼栋为分组、楼层为关卡入口，选中楼层即切换舞台；
 *  - 中间楼层舞台：房间以磁贴铺开，一屏可见整层，可按密度切换、搜索、方向键选房；
 *  - 底部常驻快速录入：不弹窗，回车即存，保存后回到同一楼层并自动聚焦；
 *  - 顶部 HUD 统计与视图切换，保留删除/归档语义。
 *
 * 契约不变：权限门控、CSRF、action/type/字段名、楼栋→楼层→房间层级、
 * 归档视图只读、无 JS 时所有楼层顺序展开且表单照常提交。
 * 测试契约：space-tree、data-dialog-open="space-floor-create-*" /
 * "space-room-create-*"、id="space-floor-edit-*" / "space-room-edit-*"、
 * value="<房间号>"、module-nav 的当前态链接全部保留。
 */

$buildingRoomIndex = [];
$totalFloorCount = 0;
$totalRoomCount = 0;
$roomTypeNames = [];
foreach ($buildings as $buildingRow) {
    $buildingKey = (int) $buildingRow['building_id'];
    $roomsByFloor = [];
    foreach ($buildingRow['rooms'] as $roomRow) {
        $roomsByFloor[(int) $roomRow['floor_id']][] = $roomRow;
        $typeName = trim((string) ($roomRow['room_type'] ?? ''));
        if ($typeName !== '') {
            $roomTypeNames[$typeName] = true;
        }
    }
    $buildingRoomIndex[$buildingKey] = $roomsByFloor;
    $totalFloorCount += count($buildingRow['floors']);
    $totalRoomCount += count($buildingRow['rooms']);
}

// Selected floor comes from ?floor= after a save/refresh; unknown values fall
// back to the first floor of ?building= (after creating/editing a building) and
// then to the project's first floor so the stage always has something to show.
$requestedFloorId = (int) ($_GET['floor'] ?? 0);
$requestedBuildingId = (int) ($_GET['building'] ?? 0);
$activeFloorPair = null;
$firstFloorPair = null;
$buildingFirstFloorPair = null;
foreach ($buildings as $buildingRow) {
    foreach ($buildingRow['floors'] as $floorRow) {
        $pair = ['floor' => $floorRow, 'building' => $buildingRow];
        if ($firstFloorPair === null) {
            $firstFloorPair = $pair;
        }
        if ($requestedBuildingId > 0
            && $buildingFirstFloorPair === null
            && (int) $buildingRow['building_id'] === $requestedBuildingId) {
            $buildingFirstFloorPair = $pair;
        }
        if ((int) $floorRow['floor_id'] === $requestedFloorId) {
            $activeFloorPair = $pair;
            break 2;
        }
    }
}
if ($activeFloorPair === null) {
    $activeFloorPair = $buildingFirstFloorPair ?? $firstFloorPair;
}
$activeFloorId = $activeFloorPair !== null ? (int) $activeFloorPair['floor']['floor_id'] : 0;
$activeBuildingId = $activeFloorPair !== null ? (int) $activeFloorPair['building']['building_id'] : 0;
$activeFloorRooms = $activeFloorPair !== null
    ? ($buildingRoomIndex[$activeBuildingId][$activeFloorId] ?? [])
    : [];
$focusTarget = (string) ($_GET['focus'] ?? '');
$projectId = (int) ($currentProject['project_id'] ?? 0);

$floorLabel = static function (array $pair): string {
    $number = trim((string) $pair['floor']['floor_number']);
    $name = trim((string) ($pair['floor']['floor_name'] ?? ''));
    return trim($number . ' ' . $name);
};
$searchKey = static function (array $parts): string {
    $kept = array_filter($parts, static fn ($value): bool => $value !== null && trim((string) $value) !== '');
    return mb_strtolower(implode(' ', $kept), 'UTF-8');
};
$numberText = static function ($value, int $decimals = 1): string {
    return rtrim(rtrim(number_format((float) $value, $decimals), '0'), '.');
};
?>
<section class="space-hud">
    <div class="space-hud-main">
        <p class="eyebrow">项目基础 · 空间台账</p>
        <h1>项目空间<?= $archivedView ? ' · 删除与归档' : '' ?></h1>
        <p class="space-hud-project">
            <span class="space-hud-dot" aria-hidden="true"></span>
            <span class="space-hud-project-name"><?= $escape((string) $currentProject['project_name']) ?></span>
            <span class="space-hud-tag"><?= $archivedView ? '归档视图' : '实时结构' ?></span>
        </p>
    </div>
    <dl class="space-hud-stats">
        <div>
            <dt>楼栋</dt>
            <dd><?= count($buildings) ?></dd>
        </div>
        <div>
            <dt>楼层</dt>
            <dd><?= $totalFloorCount ?></dd>
        </div>
        <div>
            <dt>房间</dt>
            <dd><?= $totalRoomCount ?></dd>
        </div>
        <div class="is-live">
            <dt>当前层</dt>
            <dd data-space-hud-floor><?= $activeFloorPair !== null ? $escape((string) $activeFloorPair['floor']['floor_number']) : '—' ?></dd>
        </div>
    </dl>
    <div class="space-hud-actions">
        <nav class="module-nav space-tabs" aria-label="项目空间">
            <a class="button button-secondary" href="?page=projects">项目首页</a>
            <a class="button button-<?= $archivedView ? 'secondary' : 'primary' ?>" href="?page=spaces"<?= !$archivedView ? ' aria-current="page"' : '' ?>>当前空间</a>
            <a class="button button-<?= $archivedView ? 'primary' : 'secondary' ?>" href="?page=spaces&amp;archived=1"<?= $archivedView ? ' aria-current="page"' : '' ?>>空间回收站</a>
        </nav>
        <div class="space-hud-tools">
            <span class="space-keys" aria-hidden="true"><kbd>/</kbd> 搜索 <kbd>N</kbd> 新增 <kbd>↑↓←→</kbd> 选房</span>
            <?php if ($canManage && !$archivedView): ?>
                <button class="button button-quiet" type="button" data-dialog-open="space-building-create">＋ 新增楼栋</button>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$canManage): ?>
    <div class="notice notice-info">当前账号仅有查看权限。</div>
<?php endif; ?>

<?php if ($buildings === []): ?>
    <section class="space-stage-empty">
        <p class="space-stage-empty-mark" aria-hidden="true">▦</p>
        <h2>还没有楼栋</h2>
        <p class="muted">先登记楼栋，再按楼层铺开房间。<?= $canManage && !$archivedView ? '使用页面右上角“新增楼栋”开始。' : '' ?></p>
    </section>
<?php else: ?>
<div class="space-deck space-tree" data-space-deck data-active-floor="<?= $activeFloorId ?>" data-project="<?= $projectId ?>"<?= $focusTarget !== '' ? ' data-focus="' . $escape($focusTarget) . '"' : '' ?>>
    <aside class="space-rail" data-space-rail aria-label="楼栋与楼层目录">
        <header class="space-rail-head">
            <div>
                <p class="space-rail-title">空间目录</p>
                <p class="space-rail-sub">楼栋 › 楼层</p>
            </div>
            <button class="space-rail-collapse" type="button" data-space-rail-toggle aria-expanded="true" aria-controls="space-rail-list">收起</button>
        </header>
        <div class="space-rail-list" id="space-rail-list">
            <?php foreach ($buildings as $building): ?>
                <?php
                $buildingId = (int) $building['building_id'];
                $buildingArchived = (int) $building['is_archived'] === 1;
                $floors = $building['floors'];
                $rooms = $building['rooms'];
                $isActiveBuilding = $buildingId === $activeBuildingId;
                ?>
                <details class="space-rail-building" data-space-building="<?= $buildingId ?>"<?= $isActiveBuilding ? ' open' : '' ?>>
                    <summary>
                        <span class="space-rail-bno"><?= $escape((string) $building['building_number']) ?></span>
                        <span class="space-rail-btext">
                            <strong><?= $escape((string) $building['building_name']) ?></strong>
                            <span class="space-rail-bmeta"><?= count($floors) ?> 层 · <?= count($rooms) ?> 间<?= $buildingArchived ? ' · 已归档' : '' ?></span>
                        </span>
                        <span class="space-rail-caret" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="space-rail-panel">
                        <?php if ($floors === []): ?>
                            <p class="space-rail-empty">尚无楼层</p>
                        <?php else: ?>
                            <ol class="space-floor-list">
                                <?php foreach ($floors as $floor): ?>
                                    <?php
                                    $floorId = (int) $floor['floor_id'];
                                    $floorArchived = (int) $floor['is_archived'] === 1;
                                    $floorRooms = $buildingRoomIndex[$buildingId][$floorId] ?? [];
                                    $isCurrentFloor = $floorId === $activeFloorId;
                                    ?>
                                    <li>
                                        <a
                                            class="space-floor-link<?= $isCurrentFloor ? ' is-current' : '' ?>"
                                            href="?page=spaces<?= $archivedView ? '&amp;archived=1' : '' ?>&amp;floor=<?= $floorId ?>#floor-<?= $floorId ?>"
                                            data-space-floor="<?= $floorId ?>"
                                            data-space-building-target="<?= $buildingId ?>"
                                            <?= $isCurrentFloor ? 'aria-current="page"' : '' ?>
                                        >
                                            <span class="space-floor-num"><?= $escape((string) $floor['floor_number']) ?></span>
                                            <span class="space-floor-name"><?= $floor['floor_name'] !== null && $floor['floor_name'] !== '' ? $escape((string) $floor['floor_name']) : '未命名楼层' ?></span>
                                            <span class="space-floor-count"><?= count($floorRooms) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if ($canManage): ?>
                            <div class="space-rail-actions">
                                <?php if ($archivedView && $buildingArchived): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="space.restore">
                                        <input type="hidden" name="type" value="building">
                                        <input type="hidden" name="id" value="<?= $buildingId ?>">
                                        <button class="button button-secondary space-mini" type="submit">恢复楼栋</button>
                                    </form>
                                <?php elseif (!$archivedView): ?>
                                    <button class="button button-secondary space-mini" type="button" data-dialog-open="space-building-edit-<?= $buildingId ?>">编辑楼栋</button>
                                    <form method="post" data-confirm="删除无关联楼栋；如存在下级或业务关联，将归档并保留记录。确定继续？">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="space.delete">
                                        <input type="hidden" name="type" value="building">
                                        <input type="hidden" name="id" value="<?= $buildingId ?>">
                                        <button class="button button-quiet space-mini" type="submit">删除 / 归档</button>
                                    </form>
                                    <button class="button button-quiet space-mini space-rail-add" type="button" data-dialog-open="space-floor-create-<?= $buildingId ?>">＋ 新增楼层</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="space-stage-wrap">
        <div class="space-toolbar">
            <span class="space-toolbar-title">楼层舞台</span>
            <label class="space-search">
                <span class="space-search-icon" aria-hidden="true">⌕</span>
                <input type="search" data-space-search placeholder="搜索房间号 / 类型 / 楼层" aria-label="搜索房间、类型或楼层" autocomplete="off">
            </label>
            <span class="space-toolbar-count" data-space-count>
                本层 <b><?= count($activeFloorRooms) ?></b> 间 · 全项目 <b><?= $totalRoomCount ?></b> 间
            </span>
            <div class="space-density" role="group" aria-label="磁贴密度">
                <button type="button" data-space-density="dense" aria-pressed="false">紧凑</button>
                <button type="button" data-space-density="normal" aria-pressed="true">标准</button>
                <button type="button" data-space-density="roomy" aria-pressed="false">宽松</button>
            </div>
            <button class="space-rail-open" type="button" data-space-rail-toggle aria-expanded="true">◧ 目录</button>
        </div>

        <?php foreach ($buildings as $building): ?>
            <?php
            $buildingId = (int) $building['building_id'];
            $buildingArchived = (int) $building['is_archived'] === 1;
            $floors = $building['floors'];
            ?>
            <?php foreach ($floors as $floor): ?>
                <?php
                $floorId = (int) $floor['floor_id'];
                $floorArchived = (int) $floor['is_archived'] === 1;
                $floorRooms = $buildingRoomIndex[$buildingId][$floorId] ?? [];
                $isCurrentFloor = $floorId === $activeFloorId;
                $usageType = trim((string) ($floor['usage_type'] ?? ''));
                $panelSearch = $searchKey([
                    $building['building_number'],
                    $building['building_name'],
                    $floor['floor_number'],
                    $floor['floor_name'],
                    $usageType,
                ]);
                ?>
                <article
                    class="space-stage<?= $isCurrentFloor ? ' is-current' : '' ?>"
                    id="floor-<?= $floorId ?>"
                    data-space-panel
                    data-floor="<?= $floorId ?>"
                    data-building="<?= $buildingId ?>"
                    data-label="<?= $escape($floorLabel(['floor' => $floor, 'building' => $building])) ?>"
                    data-count="<?= count($floorRooms) ?>"
                    data-search="<?= $escape($panelSearch) ?>"
                >
                    <header class="space-stage-head">
                        <div class="space-stage-id">
                            <span class="space-stage-level"><?= $escape((string) $floor['floor_number']) ?></span>
                            <div class="space-stage-text">
                                <p class="space-stage-path"><?= $escape((string) $building['building_number']) ?> · <?= $escape((string) $building['building_name']) ?></p>
                                <h2><?= $floor['floor_name'] !== null && $floor['floor_name'] !== '' ? $escape((string) $floor['floor_name']) : '未命名楼层' ?></h2>
                            </div>
                            <?php if ($usageType !== ''): ?>
                                <span class="space-stage-usage"><?= $escape($usageType) ?></span>
                            <?php endif; ?>
                            <?php if ($archivedView): ?>
                                <span class="status-badge is-neutral"><?= $floorArchived ? '已归档' : '未归档' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="space-stage-actions">
                            <span class="space-stage-count"><b><?= count($floorRooms) ?></b> 间</span>
                            <?php if ($canManage && !$archivedView): ?>
                                <button class="button button-secondary space-mini" type="button" data-dialog-open="space-room-create-<?= $floorId ?>">＋ 新增房间</button>
                                <button class="button button-quiet space-mini" type="button" data-dialog-open="space-floor-edit-<?= $floorId ?>">编辑楼层</button>
                                <form method="post" data-confirm="删除无关联楼层；如有房间或业务关联，将归档并保留记录。确定继续？">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.delete">
                                    <input type="hidden" name="type" value="floor">
                                    <input type="hidden" name="id" value="<?= $floorId ?>">
                                    <button class="button button-quiet space-mini" type="submit">删除 / 归档</button>
                                </form>
                            <?php elseif ($archivedView && $floorArchived && $canManage): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="space.restore">
                                    <input type="hidden" name="type" value="floor">
                                    <input type="hidden" name="id" value="<?= $floorId ?>">
                                    <button class="button button-secondary space-mini" type="submit">恢复楼层</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php if ($canManage && !$archivedView && !$floorArchived): ?>
                        <form class="space-quick" id="space-quick-<?= $floorId ?>" method="post" data-space-quick="<?= $floorId ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="space.save">
                            <input type="hidden" name="type" value="room">
                            <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                            <input type="hidden" name="floor_id" value="<?= $floorId ?>">
                            <span class="space-quick-tag">快速录入</span>
                            <label class="space-quick-field">
                                <span>房间号</span>
                                <input name="number" maxlength="50" required autocomplete="off" placeholder="如 301" data-space-quick-number>
                            </label>
                            <label class="space-quick-field">
                                <span>类型</span>
                                <input name="room_type" maxlength="50" autocomplete="off" list="space-room-types" placeholder="如 会议室" data-space-quick-type>
                            </label>
                            <button class="button button-primary space-quick-submit" type="submit">保存并继续</button>
                            <span class="space-quick-hint">保存后回到本层，焦点留在房间号</span>
                        </form>
                    <?php endif; ?>

                    <div class="space-grid" data-space-grid>
                        <?php foreach ($floorRooms as $room): ?>
                            <?php
                            $roomId = (int) $room['room_id'];
                            $roomArchived = (int) $room['is_archived'] === 1;
                            $roomType = trim((string) ($room['room_type'] ?? ''));
                            $roomStatus = trim((string) ($room['room_status'] ?? ''));
                            $roomArea = isset($room['room_area']) && $room['room_area'] !== null && $room['room_area'] !== '' ? (float) $room['room_area'] : 0.0;
                            $roomHeight = isset($room['room_height']) && $room['room_height'] !== null && $room['room_height'] !== '' ? (float) $room['room_height'] : 0.0;
                            $accent = $roomType !== '' ? (crc32($roomType) % 5) + 1 : 0;
                            $tileSearch = $searchKey([
                                $room['room_number'],
                                $roomType,
                                $roomStatus,
                                $building['building_number'],
                                $building['building_name'],
                                $floor['floor_number'],
                                $floor['floor_name'],
                            ]);
                            ?>
                            <article
                                class="space-tile<?= $accent > 0 ? ' space-tile--a' . $accent : '' ?><?= $roomArchived ? ' is-archived' : '' ?>"
                                tabindex="0"
                                data-space-tile
                                data-room="<?= $roomId ?>"
                                data-search="<?= $escape($tileSearch) ?>"
                            >
                                <div class="space-tile-head">
                                    <span class="space-tile-number"><?= $escape((string) $room['room_number']) ?></span>
                                    <span class="space-tile-type<?= $roomType === '' ? ' is-empty' : '' ?>"><?= $roomType !== '' ? $escape($roomType) : '未设类型' ?></span>
                                </div>
                                <div class="space-tile-facts">
                                    <?php if ($roomArea > 0): ?>
                                        <span class="space-tile-fact"><b><?= $escape($numberText($roomArea, 1)) ?></b> ㎡</span>
                                    <?php endif; ?>
                                    <?php if ($roomHeight > 0): ?>
                                        <span class="space-tile-fact"><b><?= $escape($numberText($roomHeight, 2)) ?></b> m</span>
                                    <?php endif; ?>
                                    <?php if ($roomStatus !== ''): ?>
                                        <span class="space-tile-status"><?= $escape($roomStatus) ?></span>
                                    <?php endif; ?>
                                    <?php if ($archivedView && $roomArchived): ?>
                                        <span class="space-tile-status is-archived">已归档</span>
                                    <?php endif; ?>
                                </div>
                                <div class="space-tile-actions">
                                    <?php if ($archivedView && $roomArchived && $canManage): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="space.restore">
                                            <input type="hidden" name="type" value="room">
                                            <input type="hidden" name="id" value="<?= $roomId ?>">
                                            <button class="button button-secondary space-mini" type="submit">恢复</button>
                                        </form>
                                    <?php elseif (!$archivedView && $canManage): ?>
                                        <button class="button button-quiet space-mini" type="button" data-dialog-open="space-room-edit-<?= $roomId ?>">编辑</button>
                                        <form method="post" data-confirm="删除无关联房间；如存在材料或业务关联，将归档并保留记录。确定继续？">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="space.delete">
                                            <input type="hidden" name="type" value="room">
                                            <input type="hidden" name="id" value="<?= $roomId ?>">
                                            <button class="button button-quiet space-mini" type="submit">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if ($floorRooms === []): ?>
                            <p class="space-empty-tile">
                                <span aria-hidden="true">◇</span>
                                本层暂无房间<?= $canManage && !$archivedView ? '，用上方快速录入开始登记' : '' ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($canManage && !$archivedView && !$floorArchived): ?>
                            <a class="space-tile-add" href="#space-quick-<?= $floorId ?>" data-dialog-open="space-room-create-<?= $floorId ?>">
                                <span class="space-tile-add-mark" aria-hidden="true">＋</span>
                                <span>新增房间</span>
                                <span class="space-tile-add-hint">打开表单</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($roomTypeNames !== []): ?>
<datalist id="space-room-types">
    <?php foreach (array_keys($roomTypeNames) as $typeName): ?>
        <option value="<?= $escape((string) $typeName) ?>"></option>
    <?php endforeach; ?>
</datalist>
<?php endif; ?>

<?php if ($canManage && !$archivedView): ?>
    <?php foreach ($buildings as $building): ?>
        <?php $buildingId = (int) $building['building_id']; ?>
        <dialog class="edit-dialog" id="space-building-edit-<?= $buildingId ?>" aria-labelledby="space-building-edit-title-<?= $buildingId ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">楼栋</p>
                    <h2 id="space-building-edit-title-<?= $buildingId ?>">编辑楼栋 <?= $escape((string) $building['building_number']) ?></h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="space.save">
                <input type="hidden" name="type" value="building">
                <input type="hidden" name="id" value="<?= $buildingId ?>">
                <label>楼栋编号<input name="number" maxlength="50" value="<?= $escape((string) $building['building_number']) ?>" required></label>
                <label>楼栋名称<input name="name" maxlength="255" value="<?= $escape((string) $building['building_name']) ?>" required></label>
                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
            </form>
        </dialog>

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
    <?php endforeach; ?>

    <?php foreach ($buildings as $building): ?>
        <?php
        $buildingId = (int) $building['building_id'];
        $floors = $building['floors'];
        ?>
        <?php foreach ($floors as $floor): ?>
            <?php if ((int) $floor['is_archived'] === 1) { continue; } ?>
            <?php $floorId = (int) $floor['floor_id']; ?>
            <dialog class="edit-dialog" id="space-room-create-<?= $floorId ?>" aria-labelledby="space-room-create-title-<?= $floorId ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">房间</p>
                        <h2 id="space-room-create-title-<?= $floorId ?>">在楼层 <?= $escape((string) $floor['floor_number']) ?> 新增房间</h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="space.save">
                    <input type="hidden" name="type" value="room">
                    <input type="hidden" name="building_id" value="<?= $buildingId ?>">
                    <input type="hidden" name="floor_id" value="<?= $floorId ?>">
                    <label>楼层<input value="<?= $escape((string) $floor['floor_number'] . ' ' . (string) ($floor['floor_name'] ?? '')) ?>" disabled></label>
                    <label>房间号<input name="number" maxlength="50" required></label>
                    <label>房间类型<input name="room_type" maxlength="50"></label>
                    <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存房间</button></div>
                </form>
            </dialog>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php foreach ($buildings as $building): ?>
        <?php $floors = $building['floors']; ?>
        <?php foreach ($floors as $floor): ?>
            <?php $floorId = (int) $floor['floor_id']; ?>
            <dialog class="edit-dialog" id="space-floor-edit-<?= $floorId ?>" aria-labelledby="space-floor-edit-title-<?= $floorId ?>">
                <div class="dialog-heading">
                    <div>
                        <p class="eyebrow">楼层</p>
                        <h2 id="space-floor-edit-title-<?= $floorId ?>">编辑楼层 <?= $escape((string) $floor['floor_number']) ?></h2>
                    </div>
                    <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="space.save">
                    <input type="hidden" name="type" value="floor">
                    <input type="hidden" name="id" value="<?= $floorId ?>">
                    <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                    <label>楼层编号<input name="number" maxlength="50" value="<?= $escape((string) $floor['floor_number']) ?>" required></label>
                    <label>楼层名称<input name="name" maxlength="50" value="<?= $escape((string) ($floor['floor_name'] ?? '')) ?>"></label>
                    <label>用途<input name="usage_type" maxlength="255" value="<?= $escape((string) ($floor['usage_type'] ?? '')) ?>"></label>
                    <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
                </form>
            </dialog>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php foreach ($buildings as $building): ?>
        <?php $floors = $building['floors']; ?>
        <?php foreach ($floors as $floor): ?>
            <?php
            $floorId = (int) $floor['floor_id'];
            $floorRooms = $buildingRoomIndex[(int) $building['building_id']][$floorId] ?? [];
            ?>
            <?php foreach ($floorRooms as $room): ?>
                <?php $roomId = (int) $room['room_id']; ?>
                <dialog class="edit-dialog" id="space-room-edit-<?= $roomId ?>" aria-labelledby="space-room-edit-title-<?= $roomId ?>">
                    <div class="dialog-heading">
                        <div>
                            <p class="eyebrow">房间</p>
                            <h2 id="space-room-edit-title-<?= $roomId ?>">编辑房间 <?= $escape((string) $room['room_number']) ?></h2>
                        </div>
                        <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="space.save">
                        <input type="hidden" name="type" value="room">
                        <input type="hidden" name="id" value="<?= $roomId ?>">
                        <input type="hidden" name="building_id" value="<?= (int) $building['building_id'] ?>">
                        <input type="hidden" name="floor_id" value="<?= $floorId ?>">
                        <label>楼层<input value="<?= $escape((string) $floor['floor_number']) ?>" disabled></label>
                        <label>房间号<input name="number" maxlength="50" value="<?= $escape((string) $room['room_number']) ?>" required></label>
                        <label>房间类型<input name="room_type" maxlength="50" value="<?= $escape((string) ($room['room_type'] ?? '')) ?>"></label>
                        <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
                    </form>
                </dialog>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

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

<?php
ob_start();
require __DIR__ . '/_nav.php';

$formatNumber = static function (string $value): string {
    if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
        return $value;
    }
    $trimmed = rtrim(rtrim($value, '0'), '.');
    return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
};

$activeFilters = array_filter([
    'category_id' => $filters['category_id'] ?? null,
    'building_id' => $filters['building_id'] ?? null,
    'floor_id' => $filters['floor_id'] ?? null,
    'room_id' => $filters['room_id'] ?? null,
    'search' => trim((string) ($filters['search'] ?? '')),
], static fn ($value): bool => $value !== null && $value !== '');
$hasFilters = $activeFilters !== [];
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目测算 · BOM 拆解</p>
        <h1>子材料数量汇总</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。测算只记父材料（有 BOM 组成的顶层项）的数量，本页按组成系数把它拆成子材料需求量：每个材料编号先逐条列出贡献明细（位置空间 / 父材料 / 测算数量 / 用量系数），再紧跟一条同编号汇总行；不按材料大类小计，大类只用于筛选。数量 = 测算数量 × 用量系数，不含损耗。</p>
    </div>
</section>

<?php if (($summary['skipped_rows'] ?? 0) > 0): ?>
    <div class="notice notice-warning">
        本项目还有 <?= (int) $summary['skipped_rows'] ?> 条测算行的材料没有 BOM 组成，未参与拆解。请到
        <a href="?page=materials">材料管理</a> 为这些材料建立组成（子材料 + 用量系数），或到
        <a href="?page=estimates">材料测算</a> 改按已有父材料录入。
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>筛选与导出</h2>
            <p class="muted">空间筛选包含已归档楼栋/楼层/房间，命中时位置会标注「已归档」；找不到所需材料分类时只说明该分类下没有需求量。</p>
        </div>
        <span class="toolbar-count" data-demand-count data-unit=" 条明细"><?= (int) $summary['detail_count'] ?> 条明细 · <?= (int) $summary['group_count'] ?> 个编号</span>
    </div>
    <form method="get" action="" class="form-grid demand-filter" data-cascade-form>
        <input type="hidden" name="page" value="demand-summary">
        <label>材料分类
            <select name="category_id">
                <option value="">全部</option>
                <?php foreach ($options['categories'] as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>"<?= ($filters['category_id'] ?? null) === (int) $category['category_id'] ? ' selected' : '' ?>><?= $escape((string) $category['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>楼栋
            <select name="building_id" data-cascade-building>
                <option value="">全部</option>
                <?php foreach ($options['buildings'] as $building): ?>
                    <option value="<?= (int) $building['building_id'] ?>"<?= ($filters['building_id'] ?? null) === (int) $building['building_id'] ? ' selected' : '' ?>><?= $escape(trim($building['building_number'] . ' ' . $building['building_name'])) ?><?= (int) $building['archived'] > 0 ? '（已归档）' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>楼层
            <select name="floor_id" data-cascade-floor>
                <option value="">全部</option>
                <?php foreach ($options['floors'] as $floor): ?>
                    <option value="<?= (int) $floor['floor_id'] ?>" data-building-id="<?= (int) $floor['building_id'] ?>"<?= ($filters['floor_id'] ?? null) === (int) $floor['floor_id'] ? ' selected' : '' ?>><?= $escape(trim($floor['floor_number'] . ' ' . ($floor['floor_name'] ?? '')) ?: '未指定楼层') ?><?= ((int) $floor['archived'] > 0 || (int) $floor['building_archived'] > 0) ? '（已归档）' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>房间
            <select name="room_id" data-cascade-room>
                <option value="">全部</option>
                <?php foreach ($options['rooms'] as $room): ?>
                    <option value="<?= (int) $room['room_id'] ?>" data-building-id="<?= (int) $room['building_id'] ?>" data-floor-id="<?= (int) $room['floor_id'] ?>"<?= ($filters['room_id'] ?? null) === (int) $room['room_id'] ? ' selected' : '' ?>><?= $escape(trim(($room['floor_number'] ?? '') . ' · ' . $room['room_number'], ' ·')) ?><?= ((int) $room['archived'] > 0 || (int) $room['floor_archived'] > 0 || (int) $room['building_archived'] > 0) ? '（已归档）' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field-wide">搜索材料
            <input type="search" name="search" value="<?= $escape((string) ($filters['search'] ?? '')) ?>" placeholder="材料编号 / 名称 / 规格 / 父材料" autocomplete="off">
        </label>
        <div class="field-wide ledger-record-actions">
            <button class="button button-primary" type="submit">筛选</button>
            <?php if ($hasFilters): ?><a class="button button-quiet" href="?page=demand-summary">清除筛选</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>子材料需求量</h2>
            <p class="muted">同一编号的明细连续排列，随后一条汇总行（SUM，备注「不含损耗」）。导出 Excel 与页面口径一致。</p>
        </div>
        <form method="post" action="" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.demand.export">
            <input type="hidden" name="category_id" value="<?= $escape((string) ($filters['category_id'] ?? '')) ?>">
            <input type="hidden" name="building_id" value="<?= $escape((string) ($filters['building_id'] ?? '')) ?>">
            <input type="hidden" name="floor_id" value="<?= $escape((string) ($filters['floor_id'] ?? '')) ?>">
            <input type="hidden" name="room_id" value="<?= $escape((string) ($filters['room_id'] ?? '')) ?>">
            <input type="hidden" name="search" value="<?= $escape((string) ($filters['search'] ?? '')) ?>">
            <button class="button button-secondary" type="submit">导出 Excel</button>
        </form>
    </div>
    <?php if ($summary['groups'] === []): ?>
        <p class="empty-state" data-demand-empty-state>
            <?php if (($summary['skipped_rows'] ?? 0) > 0): ?>
                没有可拆解的数据：测算行的材料还没有 BOM 组成，先在材料管理里建组成再来查看。
            <?php elseif ($hasFilters): ?>
                当前筛选条件下没有子材料需求量，试试清除筛选。
            <?php else: ?>
                当前项目还没有可拆解的测算记录：先去材料测算录入父材料数量。
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="report-table demand-summary-table" data-demand-table>
                <thead>
                <tr>
                    <th scope="col">序号</th>
                    <th scope="col">材料编号</th>
                    <th scope="col">材料名称</th>
                    <th scope="col">规格</th>
                    <th scope="col">单位</th>
                    <th scope="col">位置空间</th>
                    <th scope="col">父材料</th>
                    <th scope="col" class="demand-num">测算数量</th>
                    <th scope="col" class="demand-num">用量系数</th>
                    <th scope="col" class="demand-num">子材料数量</th>
                    <th scope="col">备注</th>
                </tr>
                </thead>
                <tbody>
                <?php $detailSerial = 0; ?>
                <?php foreach ($summary['groups'] as $group): ?>
                    <?php foreach ($group['rows'] as $row): ?>
                        <?php $detailSerial++; ?>
                        <tr>
                            <td><?= $detailSerial ?></td>
                            <td class="demand-code"><?= $escape($group['code'] !== '' ? $group['code'] : '—') ?></td>
                            <td><?= $escape($group['name']) ?></td>
                            <td class="demand-spec"><?= $escape($group['spec'] !== '' ? $group['spec'] : '—') ?></td>
                            <td><?= $escape($group['unit']) ?></td>
                            <td><?= $escape($row['space']) ?></td>
                            <td><?= $escape($row['parent_code'] !== '' ? $row['parent_code'] . ' · ' . $row['parent_name'] : $row['parent_name']) ?></td>
                            <td class="demand-num"><?= $escape($formatNumber($row['quantity'])) ?> <?= $escape($row['parent_unit']) ?></td>
                            <td class="demand-num"><?= $escape($formatNumber($row['coefficient'])) ?></td>
                            <td class="demand-num"><?= $escape($formatNumber($row['demand'])) ?> <?= $escape($group['unit']) ?></td>
                            <td><?= $escape($row['remarks'] !== '' ? $row['remarks'] : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="summary-row" data-demand-summary="<?= $escape($group['key']) ?>">
                        <td class="summary-label">Σ</td>
                        <td class="demand-code"><?= $escape($group['code'] !== '' ? $group['code'] : '—') ?></td>
                        <td><?= $escape($group['name']) ?></td>
                        <td class="demand-spec"><?= $escape($group['spec'] !== '' ? $group['spec'] : '—') ?></td>
                        <td><?= $escape($group['unit']) ?></td>
                        <td>汇总（<?= count($group['rows']) ?> 条明细）</td>
                        <td>—</td>
                        <td class="demand-num">—</td>
                        <td class="demand-num">—</td>
                        <td class="demand-num demand-total"><?= $escape($formatNumber($group['total'])) ?> <?= $escape($group['unit']) ?></td>
                        <td>不含损耗</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted section-divider-note">口径：仅统计有 BOM 组成的父材料测算行；未建组成的测算行不计入（页首会提示条数）。分类筛选按子材料所属分类。</p>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

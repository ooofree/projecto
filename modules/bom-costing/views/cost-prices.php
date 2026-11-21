<?php
ob_start();
require __DIR__ . '/_nav.php';

$statusLabels = ['active' => '生效', 'paused' => '暂停'];
$catalogTypeLabels = ['processing' => '加工', 'mechanical' => '机械', 'labor' => '人工'];
$auxPrices = array_values(array_filter($prices, static fn (array $p): bool => $p['aux_id'] !== null));
$catalogPrices = array_values(array_filter($prices, static fn (array $p): bool => $p['catalog_id'] !== null));
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">项目定价</p>
        <h1>项目成本价</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。辅材与加工／机械／人工目录项在本项目内的当前成本价；组价取价四级中的项目成本价即取自本表。材料成本价开关待决策，暂不在此维护。</p>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>辅材成本价（<?= count($auxPrices) ?>）</h2></div>
    <?php if ($auxPrices === []): ?>
        <p class="empty-state">当前项目尚无辅材成本价。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>辅材</th><th>单位</th><th>单价</th><th>计价单位</th><th>币种</th><th>生效区间</th><th>状态</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($auxPrices as $price): ?>
                    <tr>
                        <td><strong><?= $escape($price['aux_name'] ?? '—') ?></strong></td>
                        <td><?= $escape($price['aux_unit'] ?? '—') ?></td>
                        <td><?= $escape((string) $price['unit_price']) ?></td>
                        <td><?= $escape($price['pricing_unit']) ?></td>
                        <td><?= $escape($price['currency_code']) ?></td>
                        <td><?= $escape(($price['valid_from'] ?? '—') . ' ~ ' . ($price['valid_to'] ?? '—')) ?></td>
                        <td><span class="badge"><?= $escape($statusLabels[$price['status']] ?? $price['status']) ?></span></td>
                        <td>
                            <?php if ($canEdit): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="cost.price.delete">
                                    <input type="hidden" name="price_id" value="<?= (int) $price['price_id'] ?>">
                                    <button class="button button-quiet" type="submit">删除</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="cost.price.save">
            <label>辅材
                <select name="aux_id" required>
                    <option value="">请选择辅材</option>
                    <?php foreach ($auxiliaries as $aux): ?>
                        <option value="<?= (int) $aux['aux_id'] ?>"><?= $escape($aux['aux_name'] . ' (' . $aux['unit'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>单价<input type="number" name="unit_price" min="0.0001" step="0.0001" required></label>
            <label>计价单位<input name="pricing_unit" maxlength="50" placeholder="如：个／kg／台班" required></label>
            <label>币种
                <select name="currency_code">
                    <?php foreach ($currencies as $code => $name): ?>
                        <option value="<?= $escape($code) ?>" <?= $code === $defaultCurrency ? 'selected' : '' ?>><?= $escape($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>状态
                <select name="status">
                    <option value="active">生效</option>
                    <option value="paused">暂停</option>
                </select>
            </label>
            <label>生效日期<input type="date" name="valid_from"></label>
            <label>失效日期<input type="date" name="valid_to"></label>
            <div class="field-wide"><button class="button button-primary" type="submit">保存辅材成本价</button></div>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2>目录项成本价（<?= count($catalogPrices) ?>）</h2></div>
    <?php if ($catalogPrices === []): ?>
        <p class="empty-state">当前项目尚无目录项成本价。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>类型</th><th>目录项</th><th>单位</th><th>单价</th><th>计价单位</th><th>币种</th><th>生效区间</th><th>状态</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($catalogPrices as $price): ?>
                    <tr>
                        <td><?= $escape($catalogTypeLabels[$price['catalog_type']] ?? $price['catalog_type']) ?></td>
                        <td><strong><?= $escape($price['catalog_name'] ?? '—') ?></strong></td>
                        <td><?= $escape($price['catalog_unit'] ?? '—') ?></td>
                        <td><?= $escape((string) $price['unit_price']) ?></td>
                        <td><?= $escape($price['pricing_unit']) ?></td>
                        <td><?= $escape($price['currency_code']) ?></td>
                        <td><?= $escape(($price['valid_from'] ?? '—') . ' ~ ' . ($price['valid_to'] ?? '—')) ?></td>
                        <td><span class="badge"><?= $escape($statusLabels[$price['status']] ?? $price['status']) ?></span></td>
                        <td>
                            <?php if ($canEdit): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <input type="hidden" name="action" value="cost.price.delete">
                                    <input type="hidden" name="price_id" value="<?= (int) $price['price_id'] ?>">
                                    <button class="button button-quiet" type="submit">删除</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="cost.price.save">
            <label>目录项
                <select name="catalog_id" required>
                    <option value="">请选择目录项</option>
                    <?php foreach ($catalogOptions as $catType => $items): ?>
                        <optgroup label="<?= $escape($catalogTypeLabels[$catType] ?? $catType) ?>">
                            <?php foreach ($items as $item): ?>
                                <option value="<?= (int) $item['catalog_id'] ?>"><?= $escape($item['name'] . ' (' . $item['unit'] . ')') ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>单价<input type="number" name="unit_price" min="0.0001" step="0.0001" required></label>
            <label>计价单位<input name="pricing_unit" maxlength="50" placeholder="如：次／台班／工日" required></label>
            <label>币种
                <select name="currency_code">
                    <?php foreach ($currencies as $code => $name): ?>
                        <option value="<?= $escape($code) ?>" <?= $code === $defaultCurrency ? 'selected' : '' ?>><?= $escape($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>状态
                <select name="status">
                    <option value="active">生效</option>
                    <option value="paused">暂停</option>
                </select>
            </label>
            <label>生效日期<input type="date" name="valid_from"></label>
            <label>失效日期<input type="date" name="valid_to"></label>
            <div class="field-wide"><button class="button button-primary" type="submit">保存目录项成本价</button></div>
        </form>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

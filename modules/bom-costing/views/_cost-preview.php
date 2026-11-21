<?php
/**
 * 成本预览正文（组价列表右栏 / 组价详情页共用）。
 *
 * 期望变量：
 * - $costing       组价头（含 material_name / material_code / unit / detail_rows）
 * - $preview       CostBreakdown::fromRows() 的输出
 * - $showDetailTable 是否附“成本明细”表（列表右栏 true，详情页 true，与旧系统右栏一致）
 * - $previewLink   是否在“无可计价结果”时给出跳详情补价的链接（详情页自身传 false）
 *
 * 口径提示写在块内，避免任何一处展示被误读为最终综合单价。
 */
$previewCurrencies = is_array($preview['currencies'] ?? null) ? $preview['currencies'] : [];
$previewMissing = (int) ($preview['missing'] ?? 0);
$showDetailTable = (bool) ($showDetailTable ?? false);
$previewHead = (bool) ($previewHead ?? true);
$previewLink = (bool) ($previewLink ?? true);
$previewDetailRows = is_array($costing['detail_rows'] ?? null) ? $costing['detail_rows'] : [];
$statusLabels = ['draft' => '草稿', 'confirmed' => '已确认'];
$calcStatusLabels = ['incomplete' => '信息不全', 'calculable' => '可计算', 'complete' => '已齐备'];
$resourceTypeLabels = [
    'material' => '材料', 'auxiliary' => '辅材', 'processing' => '加工',
    'mechanical' => '机械', 'labor' => '人工', 'parent_self' => '父项自身', 'other' => '其他',
];

if (!function_exists('cost_compact_amount')) {
    /** 图表圆心用的紧凑金额（上万后缩写，避免长数字撑出圆）。 */
    function cost_compact_amount(float $value): string
    {
        $abs = abs($value);
        if ($abs >= 1000000) {
            return number_format($value / 1000000, 2, '.', '') . 'M';
        }
        if ($abs >= 10000) {
            return number_format($value / 10000, 1, '.', '') . '万';
        }

        return number_format($value, 2, '.', '');
    }
}

if (!function_exists('cost_format_qty')) {
    /** 数量按 3 位小数格式化后去尾零（3.000000 → 3，0.5 → 0.5）。 */
    function cost_format_qty(mixed $value): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}

if (!function_exists('cost_donut_svg')) {
    /**
     * 纯 SVG 环形占比图：按 pathLength=100 用 dasharray 画扇区，不依赖外部图表库，
     * 因此不受 CSP（style-src 'self'，无内联样式）限制。
     *
     * @param array{code: string, total: float, categories: array<int, array<string, mixed>>} $currency
     */
    function cost_donut_svg(array $currency, callable $escapeFn): string
    {
        $total = (float) $currency['total'];
        $segments = [];
        $offset = 0.0;
        foreach ($currency['categories'] as $category) {
            $share = $total > 0 ? ((float) $category['amount'] / $total) * 100 : 0.0;
            if ($share <= 0.0) {
                continue;
            }
            $segments[] = [
                'color' => (int) ($category['color'] ?? 7),
                'share' => $share,
                'offset' => $offset,
                'label' => (string) $category['label'],
                'amount' => (float) $category['amount'],
                'code' => (string) $currency['code'],
                'percent' => (float) $category['percent'],
            ];
            $offset += $share;
        }

        $markup = '<svg class="cost-donut" viewBox="0 0 220 220" role="img" aria-label="成本项占比环形图">';
        $markup .= '<circle cx="110" cy="110" r="82" fill="none" stroke="var(--surface-2)" stroke-width="34" />';
        foreach ($segments as $segment) {
            $share = rtrim(rtrim(sprintf('%.4F', $segment['share']), '0'), '.');
            $start = rtrim(rtrim(sprintf('%.4F', $segment['offset']), '0'), '.');
            $title = $segment['label'] . '：' . number_format($segment['amount'], 2) . ' ' . $segment['code']
                . '（' . number_format($segment['percent'], 2) . '%）';
            $markup .= '<circle class="cost-seg cost-seg-' . $segment['color'] . '" cx="110" cy="110" r="82"'
                . ' fill="none" stroke-width="34" pathLength="100"'
                . ' stroke-dasharray="' . $share . ' ' . rtrim(rtrim(sprintf('%.4F', 100 - (float) $share), '0'), '.') . '"'
                . ' stroke-dashoffset="-'.$start.'"'
                . ' transform="rotate(-90 110 110)">'
                . '<title>' . $escapeFn($title) . '</title></circle>';
        }
        $markup .= '<text class="cost-donut-total" x="110" y="106" text-anchor="middle">'
            . $escapeFn(cost_compact_amount($total)) . '</text>';
        $markup .= '<text class="cost-donut-code" x="110" y="126" text-anchor="middle">'
            . $escapeFn((string) $currency['code']) . '</text>';
        $markup .= '</svg>';

        return $markup;
    }
}
?>
<div class="cost-preview">
<?php if ($previewHead): ?>
    <div class="cost-preview-head">
        <div>
            <h2><?= $escape((string) $costing['material_name']) ?></h2>
            <p class="muted">
                <?= $escape(($costing['material_code'] !== null && $costing['material_code'] !== '' ? $costing['material_code'] . ' · ' : '') . (string) $costing['unit']) ?>
                ／ <?= $escape($statusLabels[$costing['status']] ?? (string) $costing['status']) ?>
                ／ <?= $escape($calcStatusLabels[$costing['calc_status']] ?? (string) $costing['calc_status']) ?>
                ／ 版本 v<?= (int) $costing['version'] ?>
            </p>
        </div>
        <?php if ($previewMissing > 0): ?>
            <span class="badge cost-badge-warn"><?= $previewMissing ?> 项缺价</span>
        <?php endif; ?>
    </div>
<?php elseif ($previewMissing > 0): ?>
    <p class="cost-inline-warn"><span class="badge cost-badge-warn"><?= $previewMissing ?> 项缺价</span> 缺价行未计入占比，补齐取价后自动并入。</p>
<?php endif; ?>

    <?php if ($previewCurrencies === []): ?>
        <p class="empty-state">
            还没有可计价的有效结果：材料组成或资源行未命中有效取价，或尚未补算。
            <?php if ($previewMissing > 0): ?>当前有 <?= $previewMissing ?> 项缺价。<?php endif; ?>
            <?php if ($previewLink): ?>
                <a class="linkbtn" href="?page=costing-detail&amp;costing_id=<?= (int) $costing['costing_id'] ?>">到详情补算与补价 →</a>
            <?php else: ?>
                缺价项见左侧取价状态；到材料报价或项目成本价补齐取价后，回本页重新取价即可出图。
            <?php endif; ?>
        </p>
    <?php else: ?>
        <?php foreach ($previewCurrencies as $currencyIndex => $currency): ?>
            <?php
            $currencyTotal = (float) $currency['total'];
            $categories = $currency['categories'];
            ?>
            <div class="cost-currency<?= $currencyIndex > 0 ? ' cost-currency-secondary' : '' ?>">
                <div class="cost-total">
                    <span class="cost-total-label">参考合计</span>
                    <strong class="cost-total-value"><?= $escape(number_format($currencyTotal, 2)) ?></strong>
                    <span class="cost-total-code"><?= $escape((string) $currency['code']) ?></span>
                    <span class="cost-total-meta">按 1 <?= $escape((string) $costing['unit']) ?> 计 · <?= (int) $currency['lines'] ?> 行已计价</span>
                </div>

                <div class="cost-chart">
                    <?= cost_donut_svg($currency, $escape) ?>
                    <ul class="cost-legend">
                        <?php foreach ($categories as $category): ?>
                            <?php $share = $currencyTotal > 0 ? ((float) $category['amount'] / $currencyTotal) * 100 : 0.0; ?>
                            <li>
                                <span class="cost-legend-dot cost-seg-bg-<?= (int) ($category['color'] ?? 7) ?>" aria-hidden="true"></span>
                                <span class="cost-legend-label"><?= $escape((string) $category['label']) ?></span>
                                <span class="cost-legend-amount"><?= $escape(number_format((float) $category['amount'], 2)) ?></span>
                                <span class="cost-legend-percent"><?= $escape(number_format($share, 2)) ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <h3 class="cost-detail-title">成本汇总</h3>
                <div class="table-wrap">
                    <table class="data-table cost-summary-table">
                        <thead>
                            <tr>
                                <th>成本项</th>
                                <th class="cost-num">小计（<?= $escape((string) $currency['code']) ?>）</th>
                                <th class="cost-num">占比</th>
                                <th class="cost-num">行数</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <?php $share = $currencyTotal > 0 ? ((float) $category['amount'] / $currencyTotal) * 100 : 0.0; ?>
                            <tr>
                                <td>
                                    <span class="cost-legend-dot cost-seg-bg-<?= (int) ($category['color'] ?? 7) ?>" aria-hidden="true"></span>
                                    <?= $escape((string) $category['label']) ?>
                                </td>
                                <td class="cost-num"><?= $escape(number_format((float) $category['amount'], 2)) ?></td>
                                <td class="cost-num"><?= $escape(number_format($share, 2)) ?>%</td>
                                <td class="cost-num"><?= (int) $category['lines'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="cost-summary-total">
                                <td>合计</td>
                                <td class="cost-num"><?= $escape(number_format($currencyTotal, 2)) ?></td>
                                <td class="cost-num">100.00%</td>
                                <td class="cost-num"><?= (int) $currency['lines'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($preview['mixed_currency'])): ?>
            <p class="cost-note">该组价含 <?= count($previewCurrencies) ?> 种币种，按币种分别统计，不做汇率折算。</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($showDetailTable && $previewDetailRows !== []): ?>
        <div>
            <h3 class="cost-detail-title">成本明细</h3>
            <div class="table-wrap">
                <table class="data-table cost-detail-table">
                    <thead>
                        <tr>
                            <th>成本项</th>
                            <th>对象</th>
                            <th class="cost-num">单价</th>
                            <th class="cost-num">数量</th>
                            <th class="cost-num">小计</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($previewDetailRows as $row): ?>
                        <?php
                        $kindLabel = str_starts_with((string) $row['kind'], 'resource:')
                            ? ($resourceTypeLabels[substr((string) $row['kind'], 9)] ?? '其他')
                            : '材料组成';
                        $rowCurrency = (string) ($row['currency_code'] ?? '');
                        ?>
                        <tr>
                            <td class="cost-detail-kind"><?= $escape($kindLabel) ?></td>
                            <td class="cost-detail-object">
                                <span class="cost-detail-name" title="<?= $escape((string) ($row['object_name'] ?? '—')) ?>"><?= $escape((string) ($row['object_name'] ?? '—')) ?></span>
                                <?php if (($row['object_code'] ?? null) !== null && $row['object_code'] !== ''): ?>
                                    <span class="muted record-meta"><?= $escape((string) $row['object_code']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cost-num"><?= $row['unit_price'] !== null ? $escape(number_format((float) $row['unit_price'], 2)) : '—' ?></td>
                            <td class="cost-num">
                                <?= $escape(cost_format_qty($row['quantity'])) ?>
                                <span class="muted cost-unit"><?= $escape((string) ($row['unit'] ?? '—')) ?></span>
                            </td>
                            <td class="cost-num">
                                <?php if ($row['status'] === 'priced' && $row['unit_price'] !== null): ?>
                                    <?= $escape(number_format((float) $row['quantity'] * (float) $row['unit_price'], 2)) ?>
                                    <span class="muted cost-unit"><?= $escape($rowCurrency) ?></span>
                                <?php else: ?>
                                    <span class="badge cost-badge-missing">缺价</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <p class="cost-note">
        小计＝数量×单价，只含已计价行；管理费／利润／税／运输保险等费率尚未定案，未计入，
        因此这里是成本项小计占比，不是最终综合单价。
    </p>
</div>

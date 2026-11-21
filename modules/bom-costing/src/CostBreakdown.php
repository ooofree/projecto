<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

/**
 * 组价“参考金额”分解器：把当前态明细行按成本项聚合成可直接画饼图/汇总表的结构。
 *
 * 口径（重要，和 V3.0 结构先行一致）：
 * - 小计＝数量×单价，只统计已计价行；缺价行不计入，只在预览里报缺项数。
 * - 管理费/利润/税/运输保险等费率在定案前不参与计算，所以这里给的是
 *   “成本项小计占比”，不是最终综合单价，视图上必须带对应说明。
 * - 系统内没有汇率表，多币种分别统计，不做折算；每种币种各出一张图。
 */
final class CostBreakdown
{
    /** 成本项标签（顺序即图表/汇总表的展示顺序）。 */
    public const CATEGORY_LABELS = [
        'material' => '材料组成',
        'auxiliary' => '辅材',
        'processing' => '加工费',
        'mechanical' => '机械费',
        'labor' => '人工费',
        'parent_self' => '父项自身',
        'other' => '其他',
    ];

    /** 明细行 kind → 成本项 key。 */
    public static function categoryOf(string $kind): string
    {
        if ($kind === 'composition') {
            return 'material';
        }
        if (str_starts_with($kind, 'resource:')) {
            $type = substr($kind, 9);

            return array_key_exists($type, self::CATEGORY_LABELS) ? $type : 'other';
        }

        return 'other';
    }

    /**
     * 成本项固定配色序号：颜色跟着“成本项”走而不是跟着排序位走，
     * 这样同一成本项在列表、详情、缺项场景下颜色始终一致。
     */
    private const COLOR_BY_KEY = [
        'material' => 1,
        'auxiliary' => 2,
        'processing' => 3,
        'mechanical' => 4,
        'labor' => 5,
        'parent_self' => 6,
        'other' => 7,
    ];

    /**
     * @param list<array<string, mixed>> $rows buildDetailRows() 输出的当前态明细行
     * @return array{
     *     currencies: list<array{code: string, total: float, lines: int, categories: list<array{key: string, label: string, amount: float, percent: float, lines: int}>}>,
     *     missing: int,
     *     priced: int,
     *     has_amount: bool,
     *     mixed_currency: bool
     * }
     */
    public static function fromRows(array $rows): array
    {
        $buckets = [];
        $missing = 0;
        $priced = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'missing');
            if ($status !== 'priced' || ($row['unit_price'] ?? null) === null) {
                $missing++;
                continue;
            }

            $code = strtoupper(trim((string) ($row['currency_code'] ?? ''))) ?: 'CNY';
            // 逐行先圆到分，保证明细表“小计”加起来等于图表与合计，不出现半分差。
            $amount = round(((float) $row['quantity']) * ((float) $row['unit_price']), 2);
            $category = self::categoryOf((string) ($row['kind'] ?? ''));

            if (!isset($buckets[$code])) {
                $buckets[$code] = ['total' => 0.0, 'lines' => 0, 'categories' => []];
            }
            $bucket = &$buckets[$code];
            $bucket['total'] += $amount;
            $bucket['lines']++;
            if (!isset($bucket['categories'][$category])) {
                $bucket['categories'][$category] = ['amount' => 0.0, 'lines' => 0];
            }
            $bucket['categories'][$category]['amount'] += $amount;
            $bucket['categories'][$category]['lines']++;
            $priced++;
            unset($bucket);
        }

        $currencies = [];
        foreach ($buckets as $code => $bucket) {
            $total = round((float) $bucket['total'], 2);
            $categories = [];
            foreach (self::CATEGORY_LABELS as $key => $label) {
                if (!isset($bucket['categories'][$key])) {
                    continue;
                }
                $amount = round((float) $bucket['categories'][$key]['amount'], 2);
                $categories[] = [
                    'key' => $key,
                    'label' => $label,
                    'color' => self::COLOR_BY_KEY[$key] ?? 7,
                    'amount' => $amount,
                    'percent' => $total > 0 ? round($amount / $total * 100, 2) : 0.0,
                    'lines' => (int) $bucket['categories'][$key]['lines'],
                ];
            }
            $currencies[] = [
                'code' => (string) $code,
                'total' => $total,
                'lines' => (int) $bucket['lines'],
                'categories' => $categories,
            ];
        }

        // 金额大的币种优先，避免主图被零碎币种抢位。
        usort($currencies, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'currencies' => $currencies,
            'missing' => $missing,
            'priced' => $priced,
            'has_amount' => $currencies !== [],
            'mixed_currency' => count($currencies) > 1,
        ];
    }
}

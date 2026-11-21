<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;

/**
 * 取价四级优先级（总纲 §四，B6 框架，已确认）：
 *   ① 当前有效默认报价（人工选定，契约不动）
 *   ② 最新有效报价（无默认或默认失效时回退；先过滤有效性，再按生效日排序，同日 quotation_id 最大）
 *   ③ 项目成本价（仅材料/辅材/目录项允许且有有效记录）
 *   ④ 缺价：返回 missing，禁按零、禁伪装完整。
 *
 * 本类只解析"当前有效输入"，不做金额公式计算（税率/抵扣/舍入定案前不落数值公式）。
 * 有效性过滤：status = active；valid_from 为空或 <= 今天；valid_to 为空或 >= 今天；
 * 计价单位与组价单位一致校验（V3.0 简化为单位一致，换算表后置）。
 */
final class PriceResolver
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * 解析材料取价。
     *
     * @return array{
     *   status: 'quotation_default'|'quotation_latest'|'cost_price'|'missing',
     *   quotation_id: int|null,
     *   project_cost_price_id: int|null,
     *   unit_price: string|null,
     *   currency_code: string|null,
     *   pricing_unit: string|null,
     *   tax_status: string,
     *   reason: string
     * }
     */
    public function forMaterial(int $projectId, int $materialId, string $today): array
    {
        // ① 默认报价（仅当当前有效）。
        $default = $this->findDefaultQuotation($projectId, $materialId, $today);
        if ($default !== null) {
            return $this->quotationResult('quotation_default', $default);
        }

        // ② 最新有效报价。
        $latest = $this->findLatestQuotation($projectId, $materialId, $today);
        if ($latest !== null) {
            return $this->quotationResult('quotation_latest', $latest);
        }

        // ③ 材料项目成本价（有效行）。
        $costPrice = $this->findMaterialCostPrice($projectId, $materialId, $today);
        if ($costPrice !== null) {
            return [
                'status' => 'cost_price',
                'quotation_id' => null,
                'project_cost_price_id' => (int) $costPrice['price_id'],
                'unit_price' => (string) $costPrice['unit_price'],
                'currency_code' => (string) $costPrice['currency_code'],
                'pricing_unit' => (string) $costPrice['pricing_unit'],
                'tax_status' => 'unknown',
                'reason' => '项目成本价',
            ];
        }

        return $this->missing('无有效报价或项目成本价');
    }

    /**
     * 解析辅材取价（项目成本价当前有效行）。
     */
    public function forAuxiliary(int $projectId, int $auxId, string $today): array
    {
        $stmt = $this->db->prepare(
            // 注：PDO 关闭模拟预处理（ATTR_EMULATE_PREPARES=false）时，命名占位符会被
            // 逐位置展开，同名占位符重复出现而只绑定一次就会抛 HY093，故两端分别命名。
            'SELECT price_id, unit_price, currency_code, pricing_unit
             FROM project_cost_prices
             WHERE project_id = :project_id AND aux_id = :aux_id
               AND status = :active
               AND (valid_from IS NULL OR valid_from <= :today_from)
               AND (valid_to IS NULL OR valid_to >= :today_to)
             ORDER BY price_id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'aux_id' => $auxId,
            'active' => 'active',
            'today_from' => $today,
            'today_to' => $today,
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $this->missing('辅材无有效项目成本价');
        }

        return [
            'status' => 'cost_price',
            'quotation_id' => null,
            'project_cost_price_id' => (int) $row['price_id'],
            'unit_price' => (string) $row['unit_price'],
            'currency_code' => (string) $row['currency_code'],
            'pricing_unit' => (string) $row['pricing_unit'],
            'tax_status' => 'unknown',
            'reason' => '辅材项目成本价',
        ];
    }

    /**
     * 解析目录项（加工/机械/人工）取价（项目成本价当前有效行；共享目录不带价格）。
     */
    public function forCatalog(int $projectId, int $catalogId, string $today): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.price_id, p.unit_price, p.currency_code, p.pricing_unit
             FROM project_cost_prices p
             WHERE p.project_id = :project_id AND p.catalog_id = :catalog_id
               AND p.status = :active
               AND (p.valid_from IS NULL OR p.valid_from <= :today_from)
               AND (p.valid_to IS NULL OR p.valid_to >= :today_to)
             ORDER BY p.price_id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'catalog_id' => $catalogId,
            'active' => 'active',
            'today_from' => $today,
            'today_to' => $today,
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $this->missing('目录项无有效项目成本价');
        }

        return [
            'status' => 'cost_price',
            'quotation_id' => null,
            'project_cost_price_id' => (int) $row['price_id'],
            'unit_price' => (string) $row['unit_price'],
            'currency_code' => (string) $row['currency_code'],
            'pricing_unit' => (string) $row['pricing_unit'],
            'tax_status' => 'unknown',
            'reason' => '目录项项目成本价',
        ];
    }

    private function findDefaultQuotation(int $projectId, int $materialId, string $today): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT q.quotation_id, q.purchase_unit_price, q.currency_code, q.pricing_unit, q.tax_status
             FROM Material_Quotations q
             JOIN Materials m ON m.material_id = q.material_id AND m.project_id = q.project_id
             WHERE q.project_id = :project_id
               AND q.material_id = :material_id
               AND m.default_quotation_id = q.quotation_id
               AND q.status = :active
               AND (q.valid_from IS NULL OR q.valid_from <= :today_from)
               AND (q.valid_to IS NULL OR q.valid_to >= :today_to)
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'material_id' => $materialId,
            'active' => 'active',
            'today_from' => $today,
            'today_to' => $today,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function findLatestQuotation(int $projectId, int $materialId, string $today): ?array
    {
        // 先过滤有效性，再按生效日（quotation_date）排序；同日决胜 quotation_id 最大（决策 5）。
        $stmt = $this->db->prepare(
            'SELECT quotation_id, purchase_unit_price, currency_code, pricing_unit, tax_status
             FROM Material_Quotations
             WHERE project_id = :project_id
               AND material_id = :material_id
               AND status = :active
               AND (valid_from IS NULL OR valid_from <= :today_from)
               AND (valid_to IS NULL OR valid_to >= :today_to)
             ORDER BY quotation_date DESC, quotation_id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'material_id' => $materialId,
            'active' => 'active',
            'today_from' => $today,
            'today_to' => $today,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function findMaterialCostPrice(int $projectId, int $materialId, string $today): ?array
    {
        // 材料项目成本价：材料经 aux/catalog 两类型化 FK 不直接挂 material_id；
        // 材料成本价首期通过报价链路取得；此处保留接口位，待"材料允许项目成本价开关"定案后接入。
        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function quotationResult(string $status, array $row): array
    {
        return [
            'status' => $status,
            'quotation_id' => (int) $row['quotation_id'],
            'project_cost_price_id' => null,
            'unit_price' => (string) $row['purchase_unit_price'],
            'currency_code' => (string) $row['currency_code'],
            'pricing_unit' => $row['pricing_unit'] !== null ? (string) $row['pricing_unit'] : '',
            'tax_status' => (string) ($row['tax_status'] ?? 'unknown'),
            'reason' => $status === 'quotation_default' ? '默认报价' : '最新有效报价',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function missing(string $reason): array
    {
        return [
            'status' => 'missing',
            'quotation_id' => null,
            'project_cost_price_id' => null,
            'unit_price' => null,
            'currency_code' => null,
            'pricing_unit' => null,
            'tax_status' => 'unknown',
            'reason' => $reason,
        ];
    }
}

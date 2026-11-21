<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;
use ProjectO\HttpException;

/**
 * 项目成本价服务（设计 ⑥ project_cost_prices，DR-2 后 aux_id / catalog_id 两类型化 FK）。
 *
 * 职责边界：
 * - 项目隔离：复合项目 FK ＋ 服务端 project_id 校验双重强制。
 * - 目标恰一：辅材（aux_id）或目录项（catalog_id）二选一；材料成本价开关待决策，本服务不接材料。
 * - 同一项目同一对象维护一条当前成本价（upsert）；历史报价链路不受影响。
 * - 单价 > 0、计价单位必填；币种默认 CNY；金额/单价定点，禁 float 计算。
 * - 被结果行引用的成本价受 RESTRICT 保护删除，数据库兜底，服务端转可读错误。
 */
final class CostPriceService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $projectId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.price_id, p.project_id, p.aux_id, p.catalog_id, p.unit_price,
                    p.currency_code, p.pricing_unit, p.valid_from, p.valid_to, p.status,
                    a.aux_name, a.unit AS aux_unit,
                    c.name AS catalog_name, c.unit AS catalog_unit, c.catalog_type
             FROM project_cost_prices p
             LEFT JOIN Auxiliary_Materials a ON a.aux_id = p.aux_id AND a.project_id = p.project_id
             LEFT JOIN resource_catalog c ON c.catalog_id = p.catalog_id
             WHERE p.project_id = :project_id
             ORDER BY COALESCE(c.catalog_type, 'auxiliary'),
                      COALESCE(a.aux_name, c.name), p.price_id"
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    /**
     * 保存（新增或更新）一条项目成本价。同项目同对象已存在当前行时更新该行，避免重复定价。
     * @param array<string, mixed> $data
     */
    public function save(int $projectId, array $data, int $userId): int
    {
        $auxId = $this->optionalId($data['aux_id'] ?? null);
        $catalogId = $this->optionalId($data['catalog_id'] ?? null);
        if (($auxId === null) === ($catalogId === null)) {
            throw new HttpException('必须且只能选择一个辅材或目录项作为定价对象。', 422);
        }

        $priceId = $this->optionalId($data['price_id'] ?? null);
        $unitPrice = $this->positiveDecimal($data['unit_price'] ?? null, '单价');
        $pricingUnit = trim((string) ($data['pricing_unit'] ?? ''));
        if ($pricingUnit === '' || mb_strlen($pricingUnit) > 50) {
            throw new HttpException('计价单位不能为空且不超过 50 字。', 422);
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? 'CNY')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new HttpException('币种代码无效。', 422);
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'paused'], true)) {
            $status = 'active';
        }

        $validFrom = $this->optionalDate($data['valid_from'] ?? null);
        $validTo = $this->optionalDate($data['valid_to'] ?? null);
        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            throw new HttpException('失效日期不能早于生效日期。', 422);
        }

        if ($auxId !== null) {
            $this->requireProjectAuxiliary($projectId, $auxId);
        } else {
            $this->requireCatalog((int) $catalogId);
        }

        $existingId = $priceId;
        if ($existingId === null) {
            $existingId = $this->findTargetRow($projectId, $auxId, $catalogId);
        } else {
            $this->requireOwnedRow($projectId, $existingId, $auxId, $catalogId);
        }

        if ($existingId !== null) {
            $update = $this->db->prepare(
                'UPDATE project_cost_prices
                 SET aux_id = :aux_id, catalog_id = :catalog_id, unit_price = :unit_price,
                     currency_code = :currency, pricing_unit = :pricing_unit,
                     valid_from = :valid_from, valid_to = :valid_to, status = :status
                 WHERE project_id = :project_id AND price_id = :price_id'
            );
            $update->execute([
                'aux_id' => $auxId,
                'catalog_id' => $catalogId,
                'unit_price' => $unitPrice,
                'currency' => $currency,
                'pricing_unit' => $pricingUnit,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'status' => $status,
                'project_id' => $projectId,
                'price_id' => $existingId,
            ]);

            return $existingId;
        }

        $insert = $this->db->prepare(
            'INSERT INTO project_cost_prices
                (project_id, aux_id, catalog_id, unit_price, currency_code, pricing_unit,
                 valid_from, valid_to, status, created_by)
             VALUES
                (:project_id, :aux_id, :catalog_id, :unit_price, :currency, :pricing_unit,
                 :valid_from, :valid_to, :status, :user_id)'
        );
        $insert->execute([
            'project_id' => $projectId,
            'aux_id' => $auxId,
            'catalog_id' => $catalogId,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'pricing_unit' => $pricingUnit,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'status' => $status,
            'user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** 删除一条未被引用的成本价；被结果行引用时数据库 RESTRICT 兜底。 */
    public function delete(int $projectId, int $priceId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM project_cost_prices WHERE project_id = :project_id AND price_id = :price_id'
        );
        $stmt->execute(['project_id' => $projectId, 'price_id' => $priceId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('成本价不存在或不属于当前项目。', 404);
        }

        $delete = $this->db->prepare('DELETE FROM project_cost_prices WHERE project_id = :project_id AND price_id = :price_id');
        try {
            $delete->execute(['project_id' => $projectId, 'price_id' => $priceId]);
        } catch (\PDOException $exception) {
            throw new HttpException('该成本价已被组价明细引用，不能删除；可先暂停（paused）再调整。', 422);
        }
    }

    private function findTargetRow(int $projectId, ?int $auxId, ?int $catalogId): ?int
    {
        $sql = 'SELECT price_id FROM project_cost_prices WHERE project_id = :project_id';
        $params = ['project_id' => $projectId];
        if ($auxId !== null) {
            $sql .= ' AND aux_id = :aux_id';
            $params['aux_id'] = $auxId;
        } else {
            $sql .= ' AND catalog_id = :catalog_id';
            $params['catalog_id'] = $catalogId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function requireOwnedRow(int $projectId, int $priceId, ?int $auxId, ?int $catalogId): void
    {
        $stmt = $this->db->prepare(
            'SELECT aux_id, catalog_id FROM project_cost_prices WHERE project_id = :project_id AND price_id = :price_id'
        );
        $stmt->execute(['project_id' => $projectId, 'price_id' => $priceId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException('成本价不存在或不属于当前项目。', 404);
        }
        $rowAux = $row['aux_id'] !== null ? (int) $row['aux_id'] : null;
        $rowCatalog = $row['catalog_id'] !== null ? (int) $row['catalog_id'] : null;
        if ($rowAux !== $auxId || $rowCatalog !== $catalogId) {
            throw new HttpException('成本价定价对象与提交内容不一致。', 422);
        }
    }

    private function requireProjectAuxiliary(int $projectId, int $auxId): void
    {
        $stmt = $this->db->prepare('SELECT 1 FROM Auxiliary_Materials WHERE project_id = :project_id AND aux_id = :id');
        $stmt->execute(['project_id' => $projectId, 'id' => $auxId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('辅材不存在或不属于当前项目。', 404);
        }
    }

    private function requireCatalog(int $catalogId): void
    {
        $stmt = $this->db->prepare('SELECT 1 FROM resource_catalog WHERE catalog_id = :id');
        $stmt->execute(['id' => $catalogId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('目录项不存在。', 404);
        }
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException('编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('编号无效。', 422);
        }

        return $id;
    }

    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException('日期格式无效。', 422);
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new HttpException('日期格式无效。', 422);
        }

        return $value;
    }

    private function positiveDecimal(mixed $value, string $label): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9]\d{0,13})(?:\.\d{1,4})?$/', $value)) {
            throw new HttpException($label . '精度或格式无效。', 422);
        }
        if ((float) $value <= 0.0) {
            throw new HttpException($label . '必须大于零。', 422);
        }

        return $value;
    }
}

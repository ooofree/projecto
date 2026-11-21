<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;
use ProjectO\HttpException;

/**
 * 实际费用与分摊应用服务（总纲 §七，独立隔离：组价重算链路永不读写本表）。
 *
 * 规则：
 * - 费用≠消耗；首期人工录入。
 * - 成本对象三类型化 FK ＋类别严格绑定（DDL CHECK 兜底，服务端先行可读校验）。
 * - 冲销五规则：允许多次部分冲销；累计≤原额；冲销不可再被冲销（目标仅 normal）；币种一致；可追溯。
 * - 调整：adjustment_of 关联原记录（仅 normal，禁调整链）；金额可正可负；币种/单位/归属继承原记录。
 * - 归属稳定：原费用被分摊/冲销/调整引用后归属字段禁改；更正走"冲销＋新登记"。
 * - 分摊：一费可摊多组价；Σ分摊≤可分摊净额（amount＋Σ冲销＋Σ调整）；写入先锁原记录行。
 * - 三级统计：项目级＝原记录＋冲销＋调整净额；组价级＝只算分摊；未分摊＝净额−Σ分摊。
 *   恒等式：项目净额＝Σ组价分摊＋未分摊（严禁相加两套口径）。
 */
final class ActualCostService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** 登记正常费用。 @param array<string, mixed> $data */
    public function recordNormal(int $projectId, array $data, int $userId): int
    {
        $category = $this->validateCategory($data['cost_category'] ?? null);
        [$materialId, $auxId, $catalogId] = $this->bindObject($projectId, $category, $data);

        $amount = $this->signedDecimal($data['amount'] ?? null, '金额');
        $occurredAt = $this->requiredDate($data['occurred_at'] ?? null, '发生日期');
        $currency = strtoupper((string) ($data['currency_code'] ?? 'CNY'));
        $taxStatus = $this->validateTaxStatus($data['tax_status'] ?? 'unknown');
        $buildingId = $this->optionalId($data['building_id'] ?? null);
        $quantity = $this->optionalDecimal($data['quantity'] ?? null);
        $unit = is_string($data['unit'] ?? null) ? trim($data['unit']) : null;
        $description = is_string($data['description'] ?? null) ? trim($data['description']) : null;

        $stmt = $this->db->prepare(
            'INSERT INTO actual_cost_records
                (project_id, building_id, cost_category, material_id, aux_id, catalog_id,
                 description, quantity, unit, amount, record_type, currency_code, tax_status,
                 occurred_at, source_type, source_ref, recorded_by)
             VALUES
                (:project_id, :building_id, :category, :material_id, :aux_id, :catalog_id,
                 :description, :quantity, :unit, :amount, :record_type, :currency, :tax_status,
                 :occurred_at, :source_type, :source_ref, :user_id)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'building_id' => $buildingId,
            'category' => $category,
            'material_id' => $materialId,
            'aux_id' => $auxId,
            'catalog_id' => $catalogId,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'amount' => $amount,
            'record_type' => 'normal',
            'currency' => $currency,
            'tax_status' => $taxStatus,
            'occurred_at' => $occurredAt,
            'source_type' => is_string($data['source_type'] ?? null) ? trim($data['source_type']) : null,
            'source_ref' => is_string($data['source_ref'] ?? null) ? trim($data['source_ref']) : null,
            'user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** 冲销：部分/全额冲销一条 normal 记录。 @param array<string, mixed> $data */
    public function recordReversal(int $projectId, array $data, int $userId): int
    {
        $targetId = $this->requiredId($data['reversal_of_record_id'] ?? null, '被冲销记录');
        $target = $this->requireNormalRecord($projectId, $targetId);

        $amount = $this->signedDecimal($data['amount'] ?? null, '冲销金额');
        // 冲销金额方向为负（冲减原额）；累计冲销绝对值≤原额净额。
        $amountNegative = '-' . ltrim($amount, '-');
        $net = $this->netAmount($projectId, $targetId);
        $reversedSoFar = $this->totalReversals($projectId, $targetId);
        $proposed = abs((float) $amountNegative);
        if ($proposed <= 0.0) {
            throw new HttpException('冲销金额必须大于零。', 422);
        }
        if ($reversedSoFar + $proposed > (float) $net + 0.0001) {
            throw new HttpException('累计冲销不能超过原记录净额。', 422);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO actual_cost_records
                (project_id, building_id, cost_category, material_id, aux_id, catalog_id,
                 description, quantity, unit, unit_price, amount, record_type,
                 reversal_of_record_id, currency_code, tax_status, occurred_at, recorded_by)
             VALUES
                (:project_id, :building_id, :category, :material_id, :aux_id, :catalog_id,
                 :description, NULL, NULL, NULL, :amount, :record_type,
                 :target_id, :currency, :tax_status, :occurred_at, :user_id)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'building_id' => $target['building_id'],
            'category' => $target['cost_category'],
            'material_id' => $target['material_id'],
            'aux_id' => $target['aux_id'],
            'catalog_id' => $target['catalog_id'],
            'description' => '冲销 #' . $targetId,
            'amount' => $amountNegative,
            'record_type' => 'reversal',
            'target_id' => $targetId,
            'currency' => $target['currency_code'],
            'tax_status' => $target['tax_status'],
            'occurred_at' => $this->requiredDate($data['occurred_at'] ?? null, '发生日期'),
            'user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** 调整：关联原 normal 记录；币种/单位/归属继承；金额可正可负。 @param array<string, mixed> $data */
    public function recordAdjustment(int $projectId, array $data, int $userId): int
    {
        $targetId = $this->requiredId($data['adjustment_of_record_id'] ?? null, '被调整记录');
        $target = $this->requireNormalRecord($projectId, $targetId);

        $amount = $this->signedDecimal($data['amount'] ?? null, '调整金额');
        if ((float) $amount == 0.0) {
            throw new HttpException('调整金额不能为零。', 422);
        }

        // 负向调整致净额不足覆盖分摊 → 拒绝并提示先调分摊（同事务校验）。
        $net = $this->netAmount($projectId, $targetId);
        $allocated = $this->totalAllocated($projectId, $targetId);
        if ((float) $net + (float) $amount < (float) $allocated - 0.0001) {
            throw new HttpException('负向调整后净额将不足以覆盖已分摊金额，请先调整分摊。', 422);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO actual_cost_records
                (project_id, building_id, cost_category, material_id, aux_id, catalog_id,
                 description, quantity, unit, unit_price, amount, record_type,
                 adjustment_of_record_id, currency_code, tax_status, occurred_at, recorded_by)
             VALUES
                (:project_id, :building_id, :category, :material_id, :aux_id, :catalog_id,
                 :description, NULL, NULL, NULL, :amount, :record_type,
                 :target_id, :currency, :tax_status, :occurred_at, :user_id)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'building_id' => $target['building_id'],
            'category' => $target['cost_category'],
            'material_id' => $target['material_id'],
            'aux_id' => $target['aux_id'],
            'catalog_id' => $target['catalog_id'],
            'description' => is_string($data['description'] ?? null) ? trim($data['description']) : '调整 #' . $targetId,
            'amount' => $amount,
            'record_type' => 'adjustment',
            'target_id' => $targetId,
            'currency' => $target['currency_code'],
            'tax_status' => $target['tax_status'],
            'occurred_at' => $this->requiredDate($data['occurred_at'] ?? null, '发生日期'),
            'user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * 分摊一条 normal（或其净额口径）记录到组价。先锁原记录行；Σ分摊≤可分摊净额。
     * @param array<string, mixed> $data
     */
    public function allocate(int $projectId, array $data, int $userId): int
    {
        $recordId = $this->requiredId($data['record_id'] ?? null, '费用记录');
        $costingId = $this->requiredId($data['costing_id'] ?? null, '组价');
        $amount = $this->positiveDecimal($data['allocated_amount'] ?? null, '分摊金额');

        $this->db->beginTransaction();
        try {
            // 锁原记录行（分摊/冲销/调整写入先锁原记录，六步顺序的最低成本方案）。
            $lockClause = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare(
                'SELECT amount, currency_code FROM actual_cost_records
                 WHERE project_id = :project_id AND record_id = :record_id' . $lockClause
            );
            $lock->execute(['project_id' => $projectId, 'record_id' => $recordId]);
            $target = $lock->fetch();
            if ($target === false) {
                throw new HttpException('费用记录不存在或不属于当前项目。', 404);
            }

            $this->requireProjectCosting($projectId, $costingId);

            $net = $this->netAmount($projectId, $recordId);
            $allocated = $this->totalAllocated($projectId, $recordId);
            if ($allocated + (float) $amount > (float) $net + 0.0001) {
                throw new HttpException('分摊金额累计不能超过可分摊净额。', 422);
            }

            $isMysql = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $conflictClause = $isMysql
                ? 'ON DUPLICATE KEY UPDATE allocated_amount = allocated_amount + VALUES(allocated_amount)'
                : 'ON CONFLICT(record_id, costing_id) DO UPDATE SET allocated_amount = allocated_amount + excluded.allocated_amount';
            $stmt = $this->db->prepare(
                'INSERT INTO actual_cost_allocations
                    (project_id, record_id, costing_id, allocated_amount, allocated_by)
                 VALUES (:project_id, :record_id, :costing_id, :amount, :user_id)
                 ' . $conflictClause
            );
            $stmt->execute([
                'project_id' => $projectId,
                'record_id' => $recordId,
                'costing_id' => $costingId,
                'amount' => $amount,
                'user_id' => $userId,
            ]);

            $this->db->commit();
            return (int) $this->db->lastInsertId();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * 三级统计（只读）：项目净额、组价分摊合计、未分摊。
     * @return array{net: string, allocated: string, unallocated: string, by_costing: list<array<string, mixed>>}
     */
    public function statistics(int $projectId): array
    {
        $netStmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM actual_cost_records WHERE project_id = :project_id'
        );
        $netStmt->execute(['project_id' => $projectId]);
        $net = (string) $netStmt->fetchColumn();

        $allocStmt = $this->db->prepare(
            'SELECT COALESCE(SUM(allocated_amount), 0) FROM actual_cost_allocations WHERE project_id = :project_id'
        );
        $allocStmt->execute(['project_id' => $projectId]);
        $allocated = (string) $allocStmt->fetchColumn();

        $byCostingStmt = $this->db->prepare(
            'SELECT a.costing_id, c.parent_material_id, m.material_name,
                    COALESCE(SUM(a.allocated_amount), 0) AS allocated_amount
             FROM actual_cost_allocations a
             JOIN bom_costings c ON c.costing_id = a.costing_id AND c.project_id = a.project_id
             JOIN Materials m ON m.material_id = c.parent_material_id AND m.project_id = c.project_id
             WHERE a.project_id = :project_id
             GROUP BY a.costing_id, c.parent_material_id, m.material_name
             ORDER BY a.costing_id'
        );
        $byCostingStmt->execute(['project_id' => $projectId]);
        $byCosting = $byCostingStmt->fetchAll();

        $unallocated = number_format((float) $net - (float) $allocated, 4, '.', '');

        return [
            'net' => $net,
            'allocated' => $allocated,
            'unallocated' => $unallocated,
            'by_costing' => $byCosting,
        ];
    }

    /** 可分摊净额＝原记录 ＋ Σ冲销（负）＋ Σ调整（可正可负）。 */
    private function netAmount(int $projectId, int $recordId): string
    {
        $stmt = $this->db->prepare(
            // 同一个取值参与三处匹配，必须各自命名：PDO 关闭模拟预处理时同名占位符
            // 重复出现而只绑定一次会抛 SQLSTATE[HY093]。
            'SELECT COALESCE(SUM(amount), 0) FROM actual_cost_records
             WHERE project_id = :project_id
               AND (record_id = :self_id
                    OR reversal_of_record_id = :reversal_id
                    OR adjustment_of_record_id = :adjustment_id)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'self_id' => $recordId,
            'reversal_id' => $recordId,
            'adjustment_id' => $recordId,
        ]);

        return (string) $stmt->fetchColumn();
    }

    private function totalReversals(int $projectId, int $recordId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(ABS(amount)), 0) FROM actual_cost_records
             WHERE project_id = :project_id AND reversal_of_record_id = :record_id'
        );
        $stmt->execute(['project_id' => $projectId, 'record_id' => $recordId]);

        return (float) $stmt->fetchColumn();
    }

    private function totalAllocated(int $projectId, int $recordId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(allocated_amount), 0) FROM actual_cost_allocations
             WHERE project_id = :project_id AND record_id = :record_id'
        );
        $stmt->execute(['project_id' => $projectId, 'record_id' => $recordId]);

        return (float) $stmt->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function requireNormalRecord(int $projectId, int $recordId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM actual_cost_records
             WHERE project_id = :project_id AND record_id = :record_id'
        );
        $stmt->execute(['project_id' => $projectId, 'record_id' => $recordId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException('费用记录不存在或不属于当前项目。', 404);
        }
        if ((string) $row['record_type'] !== 'normal') {
            throw new HttpException('只能对正常费用记录做冲销或调整。', 422);
        }

        return $row;
    }

    private function requireProjectCosting(int $projectId, int $costingId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM bom_costings WHERE project_id = :project_id AND costing_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $costingId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('组价不存在或不属于当前项目。', 404);
        }
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function bindObject(int $projectId, string $category, array $data): array
    {
        $materialId = $this->optionalId($data['material_id'] ?? null);
        $auxId = $this->optionalId($data['aux_id'] ?? null);
        $catalogId = $this->optionalId($data['catalog_id'] ?? null);

        if ($category === 'material') {
            $this->requireProjectMaterial($projectId, $materialId);
            return [$materialId, null, null];
        }
        if ($category === 'auxiliary') {
            $this->requireProjectAuxiliary($projectId, $auxId);
            return [null, $auxId, null];
        }
        if (in_array($category, ['processing', 'mechanical', 'labor'], true)) {
            $this->requireCatalogType($catalogId, $category);
            return [null, null, $catalogId];
        }

        // other：三列全空。
        if ($materialId !== null || $auxId !== null || $catalogId !== null) {
            throw new HttpException('其他费用不关联材料/辅材/目录项。', 422);
        }

        return [null, null, null];
    }

    private function validateCategory(mixed $value): string
    {
        $allowed = ['material', 'auxiliary', 'processing', 'mechanical', 'labor', 'other'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new HttpException('费用类别无效。', 422);
        }

        return $value;
    }

    private function validateTaxStatus(mixed $value): string
    {
        $allowed = ['taxable', 'exempt', 'unknown'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            return 'unknown';
        }

        return $value;
    }

    private function requireProjectMaterial(int $projectId, ?int $materialId): void
    {
        if ($materialId === null) {
            throw new HttpException('材料费用必须选择材料。', 422);
        }
        $stmt = $this->db->prepare('SELECT 1 FROM Materials WHERE project_id = :project_id AND material_id = :id');
        $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('材料不存在或不属于当前项目。', 404);
        }
    }

    private function requireProjectAuxiliary(int $projectId, ?int $auxId): void
    {
        if ($auxId === null) {
            throw new HttpException('辅材费用必须选择辅材。', 422);
        }
        $stmt = $this->db->prepare('SELECT 1 FROM Auxiliary_Materials WHERE project_id = :project_id AND aux_id = :id');
        $stmt->execute(['project_id' => $projectId, 'id' => $auxId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('辅材不存在或不属于当前项目。', 404);
        }
    }

    private function requireCatalogType(?int $catalogId, string $category): void
    {
        if ($catalogId === null) {
            throw new HttpException('该类别费用必须选择目录项。', 422);
        }
        $stmt = $this->db->prepare('SELECT catalog_type FROM resource_catalog WHERE catalog_id = :id');
        $stmt->execute(['id' => $catalogId]);
        $type = $stmt->fetchColumn();
        if ($type === false) {
            throw new HttpException('目录项不存在。', 404);
        }
        if ((string) $type !== $category) {
            throw new HttpException('目录项类型与费用类别不匹配。', 422);
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

    private function requiredId(mixed $value, string $label): int
    {
        $id = $this->optionalId($value);
        if ($id === null) {
            throw new HttpException($label . '编号无效。', 422);
        }

        return $id;
    }

    private function requiredDate(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new HttpException($label . '无效。', 422);
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new HttpException($label . '无效。', 422);
        }

        return $value;
    }

    private function signedDecimal(mixed $value, string $label): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim((string) $value);
        if (!preg_match('/^-?(?:0|[1-9]\d{0,17})(?:\.\d{1,4})?$/', $value)) {
            throw new HttpException($label . '精度或格式无效。', 422);
        }

        return $value;
    }

    private function positiveDecimal(mixed $value, string $label): string
    {
        $value = $this->signedDecimal($value, $label);
        if ((float) $value <= 0.0) {
            throw new HttpException($label . '必须大于零。', 422);
        }

        return $value;
    }

    private function optionalDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->signedDecimal($value, '数量');
    }
}

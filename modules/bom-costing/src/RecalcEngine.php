<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;
use ProjectO\HttpException;

/**
 * 串行重算引擎（V3.0 §3：项目级串行重算 ＋ 单 version，替代四字段协议）。
 *
 * 流程：拿项目锁 → 读组价当前结构与 version → 按当前时间验证取价有效性 → 计算 →
 *       提交时校验 version 未变（变了＝期间有人工编辑 → 丢弃本轮，重新入队）→ 更新明细＋审计 → 释放锁。
 *
 * 边界：
 * - 同一项目同一时刻只有一个重算在跑（MySQL GET_LOCK）；不同项目可并行。
 * - 重算不改 version；人工结构修改由 CostingService 事务内 version+1。
 * - 组成边变化属上游输入，走自动重算、不退草稿（DR-1）；边删→重算时清理对应结果行。
 * - 缺价/单位不匹配/税口径 unknown → 该行不落有效结果，calc_status 降级；禁静默零价。
 * - 数值金额公式（管理费/利润/税等）在税率与舍入定案前不计算，结果金额列留空，仅落取价来源与单价。
 */
final class RecalcEngine
{
    private const LOCK_PREFIX = 'cost_recalc_project_';

    /** 进程内互斥兜底（SQLite 测试或无 GET_LOCK 的驱动；MySQL 生产以 GET_LOCK 为准）。 */
    private static array $inProcessLocks = [];

    public function __construct(
        private readonly PDO $db,
        private readonly PriceResolver $prices
    ) {
    }

    /**
     * 重算单个组价。返回是否提交成功（false＝version 被人工编辑改变，本轮丢弃待重试）。
     */
    public function recalc(int $projectId, int $costingId, int $userId, string $today): bool
    {
        $lockName = self::LOCK_PREFIX . $projectId;
        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $usesDbLock = $driver === 'mysql';

        if ($usesDbLock) {
            // 项目级串行锁：GET_LOCK 超时 5 秒；拿不到说明同项目另有重算在跑，本轮放弃由队列重试。
            $locked = $this->db->query("SELECT GET_LOCK(" . $this->db->quote($lockName) . ", 5)")->fetchColumn();
            if ($locked === false || (int) $locked !== 1) {
                throw new HttpException('当前项目组价正在重算，请稍后重试。', 409);
            }
        } else {
            // 非 MySQL（测试 SQLite）：进程内互斥，同一项目串行。
            if (isset(self::$inProcessLocks[$lockName])) {
                throw new HttpException('当前项目组价正在重算，请稍后重试。', 409);
            }
            self::$inProcessLocks[$lockName] = true;
        }

        try {
            return $this->recalcWithLock($projectId, $costingId, $userId, $today);
        } finally {
            if ($usesDbLock) {
                $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockName) . ")");
            } else {
                unset(self::$inProcessLocks[$lockName]);
            }
        }
    }

    private function recalcWithLock(int $projectId, int $costingId, int $userId, string $today): bool
    {
        // 读结构与 version（快照读，提交时条件更新校验未变）。
        $head = $this->readHead($projectId, $costingId);
        $readVersion = (int) $head['version'];

        $this->db->beginTransaction();
        try {
            // 组件：实时读 Material_Compositions 当前边（DR-1）。
            $compositions = $this->readCompositions($projectId, (int) $head['parent_material_id']);
            // 资源行：实时读目录/材料表，无快照。
            $resources = $this->readResources($projectId, $costingId);

            $resultRows = [];
            $hasMissing = false;
            $hasPriced = false;

            foreach ($compositions as $comp) {
                $resolved = $this->prices->forMaterial($projectId, (int) $comp['child_material_id'], $today);
                $row = $this->buildComponentRow($projectId, $costingId, $comp, $resolved, $userId);
                if ($resolved['status'] === 'missing') {
                    $hasMissing = true;
                } else {
                    $hasPriced = true;
                }
                $resultRows[] = $row;
            }

            foreach ($resources as $resource) {
                $resolved = $this->resolveResourceLine($projectId, $resource, $today);
                $row = $this->buildResourceRow($projectId, $costingId, $resource, $resolved, $userId);
                if ($resolved['status'] === 'missing') {
                    $hasMissing = true;
                } else {
                    $hasPriced = true;
                }
                $resultRows[] = $row;
            }

            // version 乐观校验：提交时头表 version 必须仍等于读值；否则期间有人工编辑，丢弃本轮。
            $lockClause = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $check = $this->db->prepare(
                'SELECT version FROM bom_costings
                 WHERE project_id = :project_id AND costing_id = :costing_id' . $lockClause
            );
            $check->execute(['project_id' => $projectId, 'costing_id' => $costingId]);
            $currentVersion = (int) $check->fetchColumn();
            if ($currentVersion !== $readVersion) {
                $this->db->rollBack();
                return false;
            }

            // 清理当前态明细：组件边删除→对应结果行移除；资源行删除→对应结果行移除（当前态语义）。
            $this->clearResults($projectId, $costingId);

            foreach ($resultRows as $row) {
                $this->upsertResult($row);
            }

            $calcStatus = $this->deriveCalcStatus($hasPriced, $hasMissing, $resultRows);
            $update = $this->db->prepare(
                'UPDATE bom_costings SET calc_status = :calc_status, updated_at = CURRENT_TIMESTAMP
                 WHERE project_id = :project_id AND costing_id = :costing_id AND version = :read_version'
            );
            $update->execute([
                'calc_status' => $calcStatus,
                'project_id' => $projectId,
                'costing_id' => $costingId,
                'read_version' => $readVersion,
            ]);
            if ($update->rowCount() !== 1) {
                // 条件更新未命中：version 在提交瞬间被改变，丢弃本轮。
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function readHead(int $projectId, int $costingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM bom_costings WHERE project_id = :project_id AND costing_id = :costing_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException('组价不存在或不属于当前项目。', 404);
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function readCompositions(int $projectId, int $parentMaterialId): array
    {
        $stmt = $this->db->prepare(
            'SELECT composition_id, child_material_id, quantity
             FROM Material_Compositions
             WHERE project_id = :project_id AND parent_material_id = :parent_id'
        );
        $stmt->execute(['project_id' => $projectId, 'parent_id' => $parentMaterialId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function readResources(int $projectId, int $costingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT resource_line_id, resource_type, aux_id, catalog_id, material_id, quantity
             FROM bom_costing_resources
             WHERE project_id = :project_id AND costing_id = :costing_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);

        return $stmt->fetchAll();
    }

    /**
     * 按资源行类型解析取价结果（供重算与详情缺项展示复用同一口径）。
     * @param array<string, mixed> $resource
     * @return array<string, mixed>
     */
    public function resolveResourceLine(int $projectId, array $resource, string $today): array
    {
        $type = (string) $resource['resource_type'];
        if ($type === 'auxiliary') {
            return $this->prices->forAuxiliary($projectId, (int) $resource['aux_id'], $today);
        }
        if (in_array($type, ['processing', 'mechanical', 'labor'], true)) {
            return $this->prices->forCatalog($projectId, (int) $resource['catalog_id'], $today);
        }
        // parent_self：父项自身成本，取父材料报价/成本价。
        return $this->prices->forMaterial($projectId, (int) $resource['material_id'], $today);
    }

    /**
     * @param array<string, mixed> $comp
     * @param array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private function buildComponentRow(int $projectId, int $costingId, array $comp, array $resolved, int $userId): array
    {
        return [
            'project_id' => $projectId,
            'costing_id' => $costingId,
            'composition_id' => (int) $comp['composition_id'],
            'resource_line_id' => null,
            'quantity' => $this->coefficientToQuantity((string) $comp['quantity']),
            'resolved' => $resolved,
            'userId' => $userId,
        ];
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private function buildResourceRow(int $projectId, int $costingId, array $resource, array $resolved, int $userId): array
    {
        return [
            'project_id' => $projectId,
            'costing_id' => $costingId,
            'composition_id' => null,
            'resource_line_id' => (int) $resource['resource_line_id'],
            'quantity' => (string) $resource['quantity'],
            'resolved' => $resolved,
            'userId' => $userId,
        ];
    }

    /**
     * 系数 decimal(10,2) → 计算数量 decimal(18,6)。损耗做进数量系数（决策 3），
     * 录入时已含损耗；当前无显式 loss_rate 字段，计算数量＝系数。
     */
    private function coefficientToQuantity(string $coefficient): string
    {
        return number_format((float) $coefficient, 6, '.', '');
    }

    /** @param array<string, mixed> $row */
    private function upsertResult(array $row): void
    {
        $resolved = $row['resolved'];
        // 缺价行不落有效结果（禁零价）；保留缺项由 calc_status 与派生查询体现。
        if ($resolved['status'] === 'missing') {
            return;
        }

        $isMysql = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $conflictClause = $isMysql
            ? 'ON DUPLICATE KEY UPDATE
                quantity = VALUES(quantity), unit_price = VALUES(unit_price),
                currency_code = VALUES(currency_code), pricing_unit = VALUES(pricing_unit),
                quotation_id = VALUES(quotation_id), project_cost_price_id = VALUES(project_cost_price_id),
                source_tax_status = VALUES(source_tax_status), calculated_at = CURRENT_TIMESTAMP'
            : 'ON CONFLICT(costing_id, composition_id) DO UPDATE SET
                quantity = excluded.quantity, unit_price = excluded.unit_price,
                currency_code = excluded.currency_code, pricing_unit = excluded.pricing_unit,
                quotation_id = excluded.quotation_id, project_cost_price_id = excluded.project_cost_price_id,
                source_tax_status = excluded.source_tax_status, calculated_at = CURRENT_TIMESTAMP';
        $stmt = $this->db->prepare(
            'INSERT INTO cost_line_results
                (costing_id, project_id, composition_id, resource_line_id, quantity, unit_price,
                 currency_code, pricing_unit, quotation_id, project_cost_price_id, manual_price,
                 manual_confirmed_by, source_tax_status, calculated_by, formula_version, rounding_spec_version)
             VALUES
                (:costing_id, :project_id, :composition_id, :resource_line_id, :quantity, :unit_price,
                 :currency_code, :pricing_unit, :quotation_id, :cost_price_id, NULL,
                 NULL, :tax_status, :user_id, :formula_version, :rounding_version)
             ' . $conflictClause
        );
        $stmt->execute([
            'costing_id' => $row['costing_id'],
            'project_id' => $row['project_id'],
            'composition_id' => $row['composition_id'],
            'resource_line_id' => $row['resource_line_id'],
            'quantity' => $row['quantity'],
            'unit_price' => $resolved['unit_price'],
            'currency_code' => $resolved['currency_code'],
            'pricing_unit' => $resolved['pricing_unit'] ?? '',
            'quotation_id' => $resolved['quotation_id'],
            'cost_price_id' => $resolved['project_cost_price_id'],
            'tax_status' => $resolved['tax_status'],
            'user_id' => $row['userId'],
            'formula_version' => 'v3.0-structure',
            'rounding_version' => 'pending',
        ]);
    }

    private function clearResults(int $projectId, int $costingId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM cost_line_results WHERE project_id = :project_id AND costing_id = :costing_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);
    }

    /**
     * 派生 calc_status：
     * - 无任何可计价行 → incomplete
     * - 有可计价行但存在缺项 → calculable（默认按 incomplete 限制参与分析，A7 待决策）
     * - 全部可计价 → complete
     *
     * @param list<array<string, mixed>> $rows
     */
    private function deriveCalcStatus(bool $hasPriced, bool $hasMissing, array $rows): string
    {
        if (!$hasPriced) {
            return 'incomplete';
        }
        if ($hasMissing) {
            return 'calculable';
        }

        return 'complete';
    }
}

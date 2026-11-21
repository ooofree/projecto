<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;
use ProjectO\HttpException;

/**
 * 组价头与资源行的应用服务。
 *
 * 职责边界（V3.0）：
 * - 项目隔离由复合 FK ＋ 服务端 project_id 校验双重强制（不靠客户端提交）。
 * - 一父一份（F1）：UNIQUE(project_id, parent_material_id)。
 * - 人工结构修改（资源行增删改/数量/来源）事务内 version+1，兼乐观锁；重算不改 version。
 * - calc_status 由当前态结果行派生查询，不落头表；缺项＝存在 missing 行或无结果行。
 * - 组件实时引用 Material_Compositions（DR-1），本服务不复制组成。
 *
 * 数值公式（管理费/利润/税/运输/保险/舍入）在税率与舍入定案前不实现，结果金额列留空。
 */
final class CostingService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** 组价列表（含父材料信息、双态、缺项数、更新时间）。 */
    public function listCostings(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.costing_id, c.parent_material_id, c.status, c.calc_status, c.version,
                    c.confirmed_at, c.updated_at,
                    m.material_code, m.material_name, m.unit
             FROM bom_costings c
             JOIN Materials m ON m.material_id = c.parent_material_id AND m.project_id = c.project_id
             WHERE c.project_id = :project_id
             ORDER BY m.material_name, c.costing_id'
        );
        $stmt->execute(['project_id' => $projectId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['missing_count'] = $this->missingCount(
                (int) $row['project_id'],
                (int) $row['costing_id'],
                (int) $row['parent_material_id']
            );
        }
        unset($row);

        return $rows;
    }

    /** 当前项目尚未建立组价的材料（供新建组价选择父材料）。 @return list<array<string, mixed>> */
    public function listAvailableMaterials(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.material_id, m.material_code, m.material_name, m.unit
             FROM Materials m
             WHERE m.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM bom_costings c
                   WHERE c.project_id = m.project_id AND c.parent_material_id = m.material_id
               )
             ORDER BY m.material_name, m.material_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    /** 读取单个组价（含资源行、组件实时快照、当前态结果行）。 */
    public function requireCosting(int $projectId, int $costingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, m.material_code, m.material_name, m.unit
             FROM bom_costings c
             JOIN Materials m ON m.material_id = c.parent_material_id AND m.project_id = c.project_id
             WHERE c.project_id = :project_id AND c.costing_id = :costing_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);
        $costing = $stmt->fetch();
        if ($costing === false) {
            throw new HttpException('组价不存在或不属于当前项目。', 404);
        }

        $costing['resources'] = $this->listResources($projectId, $costingId);
        $costing['compositions'] = $this->compositionsForParent($projectId, (int) $costing['parent_material_id']);
        $costing['results'] = $this->listResults($projectId, $costingId);
        $costing['missing_count'] = $this->missingCount($projectId, $costingId, (int) $costing['parent_material_id']);

        return $costing;
    }

    /** 为父材料确保一份组价头（一父一份；不存在则创建 draft）。返回 costing_id。 */
    public function ensureCosting(int $projectId, int $parentMaterialId, int $userId): int
    {
        $this->requireProjectMaterial($projectId, $parentMaterialId);

        $stmt = $this->db->prepare(
            'SELECT costing_id FROM bom_costings
             WHERE project_id = :project_id AND parent_material_id = :material_id'
        );
        $stmt->execute(['project_id' => $projectId, 'material_id' => $parentMaterialId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $insert = $this->db->prepare(
            'INSERT INTO bom_costings (project_id, parent_material_id, status, calc_status, version, created_by)
             VALUES (:project_id, :material_id, :status, :calc_status, 0, :user_id)'
        );
        $insert->execute([
            'project_id' => $projectId,
            'material_id' => $parentMaterialId,
            'status' => 'draft',
            'calc_status' => 'incomplete',
            'user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * 保存一条资源行（新增或更新）。人工结构修改：事务内 version+1。
     *
     * @param array<string, mixed> $data
     */
    public function saveResource(int $projectId, int $costingId, array $data, int $userId): int
    {
        $costing = $this->requireCostingRow($projectId, $costingId);
        $resourceType = (string) ($data['resource_type'] ?? '');
        $allowedTypes = ['auxiliary', 'processing', 'mechanical', 'labor', 'parent_self'];
        if (!in_array($resourceType, $allowedTypes, true)) {
            throw new HttpException('资源类型无效。', 422);
        }

        $auxId = $this->optionalId($data['aux_id'] ?? null);
        $catalogId = $this->optionalId($data['catalog_id'] ?? null);
        $materialId = $this->optionalId($data['material_id'] ?? null);
        $quantity = $this->positiveDecimal($data['quantity'] ?? null, '数量');

        // 类型与目标匹配校验（与 DDL CHECK 对齐，服务端先行给出可读错误）。
        $this->validateResourceTarget($projectId, $resourceType, $auxId, $catalogId, $materialId, (int) $costing['parent_material_id']);

        $lineId = $this->optionalId($data['resource_line_id'] ?? null);

        $this->db->beginTransaction();
        try {
            if ($lineId === null) {
                $this->assertResourceUnique($projectId, $costingId, $resourceType, $auxId, $catalogId, $materialId);
                $insert = $this->db->prepare(
                    'INSERT INTO bom_costing_resources
                        (costing_id, project_id, resource_type, aux_id, catalog_id, material_id, quantity)
                     VALUES (:costing_id, :project_id, :resource_type, :aux_id, :catalog_id, :material_id, :quantity)'
                );
                $insert->execute([
                    'costing_id' => $costingId,
                    'project_id' => $projectId,
                    'resource_type' => $resourceType,
                    'aux_id' => $auxId,
                    'catalog_id' => $catalogId,
                    'material_id' => $materialId,
                    'quantity' => $quantity,
                ]);
                $lineId = (int) $this->db->lastInsertId();
            } else {
                $this->requireResourceRow($projectId, $costingId, $lineId);
                $update = $this->db->prepare(
                    'UPDATE bom_costing_resources
                     SET resource_type = :resource_type, aux_id = :aux_id, catalog_id = :catalog_id,
                         material_id = :material_id, quantity = :quantity
                     WHERE project_id = :project_id AND costing_id = :costing_id AND resource_line_id = :line_id'
                );
                $update->execute([
                    'resource_type' => $resourceType,
                    'aux_id' => $auxId,
                    'catalog_id' => $catalogId,
                    'material_id' => $materialId,
                    'quantity' => $quantity,
                    'project_id' => $projectId,
                    'costing_id' => $costingId,
                    'line_id' => $lineId,
                ]);
            }

            // 人工结构修改：version+1（乐观锁）。
            $this->bumpVersion($projectId, $costingId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $lineId;
    }

    /** 删除资源行。人工结构修改：version+1。 */
    public function deleteResource(int $projectId, int $costingId, int $lineId): void
    {
        $this->requireCostingRow($projectId, $costingId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM bom_costing_resources
                 WHERE project_id = :project_id AND costing_id = :costing_id AND resource_line_id = :line_id'
            );
            $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId, 'line_id' => $lineId]);
            if ($stmt->rowCount() !== 1) {
                throw new HttpException('资源行不存在或不属于当前组价。', 404);
            }
            $this->bumpVersion($projectId, $costingId);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** 确认组价（业务确认）。calc_status 不随确认改变；confirmed＋incomplete 禁入完整分析由展示层控制。 */
    public function confirm(int $projectId, int $costingId, int $userId): void
    {
        $costing = $this->requireCostingRow($projectId, $costingId);
        $stmt = $this->db->prepare(
            'UPDATE bom_costings
             SET status = :status, confirmed_by = :user_id, confirmed_at = CURRENT_TIMESTAMP
             WHERE project_id = :project_id AND costing_id = :costing_id AND status = :draft'
        );
        $stmt->execute([
            'status' => 'confirmed',
            'user_id' => $userId,
            'project_id' => $projectId,
            'costing_id' => $costingId,
            'draft' => 'draft',
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('只有草稿状态的组价可以确认。', 422);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listResources(int $projectId, int $costingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.resource_line_id, r.resource_type, r.aux_id, r.catalog_id, r.material_id, r.quantity,
                    a.aux_name, a.unit AS aux_unit,
                    c.name AS catalog_name, c.unit AS catalog_unit, c.catalog_type,
                    m.material_code, m.material_name, m.unit AS material_unit
             FROM bom_costing_resources r
             LEFT JOIN Auxiliary_Materials a ON a.aux_id = r.aux_id AND a.project_id = r.project_id
             LEFT JOIN resource_catalog c ON c.catalog_id = r.catalog_id
             LEFT JOIN Materials m ON m.material_id = r.material_id AND m.project_id = r.project_id
             WHERE r.project_id = :project_id AND r.costing_id = :costing_id
             ORDER BY r.resource_line_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);

        return $stmt->fetchAll();
    }

    /** 组件实时引用 Material_Compositions（DR-1）：按父材料读当前边。 */
    public function compositionsForParent(int $projectId, int $parentMaterialId): array
    {
        $stmt = $this->db->prepare(
            'SELECT comp.composition_id, comp.child_material_id, comp.quantity,
                    m.material_code, m.material_name, m.unit
             FROM Material_Compositions comp
             JOIN Materials m ON m.material_id = comp.child_material_id AND m.project_id = comp.project_id
             WHERE comp.project_id = :project_id AND comp.parent_material_id = :parent_id
             ORDER BY m.material_name, comp.composition_id'
        );
        $stmt->execute(['project_id' => $projectId, 'parent_id' => $parentMaterialId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listResults(int $projectId, int $costingId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    cm.material_name AS comp_material_name, cm.material_code AS comp_material_code,
                    cm.unit AS comp_unit,
                    a.aux_name, a.unit AS aux_unit,
                    c.name AS catalog_name, c.unit AS catalog_unit, c.catalog_type,
                    m.material_code AS res_material_code, m.material_name AS res_material_name,
                    m.unit AS res_material_unit
             FROM cost_line_results r
             LEFT JOIN Material_Compositions comp ON comp.composition_id = r.composition_id AND comp.project_id = r.project_id
             LEFT JOIN Materials cm ON cm.material_id = comp.child_material_id AND cm.project_id = comp.project_id
             LEFT JOIN bom_costing_resources res ON res.resource_line_id = r.resource_line_id AND res.project_id = r.project_id
             LEFT JOIN Auxiliary_Materials a ON a.aux_id = res.aux_id AND a.project_id = res.project_id
             LEFT JOIN resource_catalog c ON c.catalog_id = res.catalog_id
             LEFT JOIN Materials m ON m.material_id = res.material_id AND m.project_id = res.project_id
             WHERE r.project_id = :project_id AND r.costing_id = :costing_id
             ORDER BY r.result_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);

        return $stmt->fetchAll();
    }

    /**
     * 缺项数＝当前组件边 ＋ 资源行中尚未命中有效结果行的条数。
     * 缺价行不落结果表（禁零价），故按当前结构与已计价结果行差集派生。
     */
    private function missingCount(int $projectId, int $costingId, int $parentMaterialId): int
    {
        $pricedCompositions = [];
        $pricedResources = [];
        foreach ($this->listResults($projectId, $costingId) as $result) {
            if ($result['composition_id'] !== null) {
                $pricedCompositions[] = (int) $result['composition_id'];
            }
            if ($result['resource_line_id'] !== null) {
                $pricedResources[] = (int) $result['resource_line_id'];
            }
        }

        $missing = 0;
        foreach ($this->compositionsForParent($projectId, $parentMaterialId) as $comp) {
            if (!in_array((int) $comp['composition_id'], $pricedCompositions, true)) {
                $missing++;
            }
        }
        foreach ($this->listResources($projectId, $costingId) as $resource) {
            if (!in_array((int) $resource['resource_line_id'], $pricedResources, true)) {
                $missing++;
            }
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    private function requireCostingRow(int $projectId, int $costingId): array
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

    private function requireResourceRow(int $projectId, int $costingId, int $lineId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM bom_costing_resources
             WHERE project_id = :project_id AND costing_id = :costing_id AND resource_line_id = :line_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId, 'line_id' => $lineId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('资源行不存在或不属于当前组价。', 404);
        }
    }

    private function validateResourceTarget(
        int $projectId,
        string $resourceType,
        ?int $auxId,
        ?int $catalogId,
        ?int $materialId,
        int $parentMaterialId
    ): void {
        if ($resourceType === 'auxiliary') {
            if ($auxId === null || $catalogId !== null || $materialId !== null) {
                throw new HttpException('辅材行必须且只能选择一个辅材目录项。', 422);
            }
            $this->requireProjectAuxiliary($projectId, $auxId);
            return;
        }

        if (in_array($resourceType, ['processing', 'mechanical', 'labor'], true)) {
            if ($catalogId === null || $auxId !== null || $materialId !== null) {
                throw new HttpException('加工/机械/人工行必须且只能选择一个目录项。', 422);
            }
            $this->requireCatalogType($catalogId, $resourceType);
            return;
        }

        // parent_self：受控写入，必须指向头表父材料。
        if ($materialId === null || $auxId !== null || $catalogId !== null) {
            throw new HttpException('父项自身行必须指向当前父材料。', 422);
        }
        if ($materialId !== $parentMaterialId) {
            throw new HttpException('父项自身行只能指向当前父材料。', 422);
        }
    }

    private function assertResourceUnique(
        int $projectId,
        int $costingId,
        string $resourceType,
        ?int $auxId,
        ?int $catalogId,
        ?int $materialId
    ): void {
        $key = $auxId !== null ? 'a' . $auxId : ($catalogId !== null ? 'c' . $catalogId : 'm' . $materialId);
        $stmt = $this->db->prepare(
            'SELECT 1 FROM bom_costing_resources
             WHERE project_id = :project_id AND costing_id = :costing_id
               AND resource_type = :resource_type AND resource_key = :resource_key'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'costing_id' => $costingId,
            'resource_type' => $resourceType,
            'resource_key' => $key,
        ]);
        if ($stmt->fetchColumn() !== false) {
            throw new HttpException('同一组价内该资源已存在一行。', 422);
        }
    }

    private function bumpVersion(int $projectId, int $costingId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE bom_costings SET version = version + 1
             WHERE project_id = :project_id AND costing_id = :costing_id'
        );
        $stmt->execute(['project_id' => $projectId, 'costing_id' => $costingId]);
    }

    private function requireProjectMaterial(int $projectId, int $materialId): void
    {
        $stmt = $this->db->prepare('SELECT 1 FROM Materials WHERE project_id = :project_id AND material_id = :id');
        $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('材料不存在或不属于当前项目。', 404);
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

    private function requireCatalogType(int $catalogId, string $resourceType): void
    {
        $stmt = $this->db->prepare('SELECT catalog_type FROM resource_catalog WHERE catalog_id = :id');
        $stmt->execute(['id' => $catalogId]);
        $type = $stmt->fetchColumn();
        if ($type === false) {
            throw new HttpException('目录项不存在。', 404);
        }
        if ((string) $type !== $resourceType) {
            throw new HttpException('目录项类型与资源类型不匹配。', 422);
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

    private function positiveDecimal(mixed $value, string $label): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9]\d{0,17})(?:\.\d{1,6})?$/', $value)) {
            throw new HttpException($label . '精度或格式无效。', 422);
        }
        if ((float) $value <= 0.0) {
            throw new HttpException($label . '必须大于零。', 422);
        }

        return $value;
    }
}

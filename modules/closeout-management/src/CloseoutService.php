<?php
declare(strict_types=1);

namespace ProjectO\Modules\CloseoutManagement;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use ProjectO\HttpException;
use ProjectO\Support;
use Throwable;

/**
 * Engineering closeout (消项) domain service.
 *
 * All reads and writes are scoped by the project id resolved server-side by the
 * caller. The service owns numbering, coverage uniqueness (one active item per
 * material, no ancestor/descendant pair), the status machine, progress history,
 * acceptance records and project totals.
 *
 * Material name, specification, unit, category and composition are read live
 * from BOM on every request. Nothing from BOM is copied into the closeout item.
 * The closeout domain is read-only with respect to BOM: it never writes
 * Materials or Material_Compositions and never consumes BOM quantities.
 */
final class CloseoutService
{
    private const ITEM_SELECT = <<<'SQL'
SELECT i.item_id, i.project_id, i.plan_id, i.item_no, i.material_id, i.coverage_mode,
       i.coverage_root_id, i.title, i.description, i.status,
       COALESCE(NULLIF(i.title, ''), m.material_name, '') AS display_name,
       i.responsible_supplier_id, i.responsible_user_id, i.responsible_crew_id,
       i.planned_start_date, i.planned_end_date, i.submitted_at, i.actual_complete_date,
       i.risk_level, i.cancel_reason, i.cancelled_by, i.cancelled_at,
       i.created_by, i.created_at, i.updated_at,
       p.plan_no, p.plan_name, p.status AS plan_status,
       m.material_code, m.material_name, m.unit, m.specifications,
       c.category_name,
       s.supplier_name,
       COALESCE(NULLIF(u.display_name, ''), u.username) AS responsible_user_name,
       lc.crew_name AS responsible_crew_name,
       lc.leader_name AS responsible_crew_leader,
       COALESCE(NULLIF(cu.display_name, ''), cu.username) AS created_by_name,
       COALESCE(NULLIF(xu.display_name, ''), xu.username) AS cancelled_by_name
FROM project_closeout_items i
JOIN project_closeout_plans p ON p.plan_id = i.plan_id AND p.project_id = i.project_id
LEFT JOIN Materials m ON m.material_id = i.material_id AND m.project_id = i.project_id
LEFT JOIN Material_Categories c ON c.category_id = m.category_id
LEFT JOIN Suppliers s ON s.supplier_id = i.responsible_supplier_id
LEFT JOIN Users u ON u.user_id = i.responsible_user_id
LEFT JOIN Labor_Crews lc ON lc.crew_id = i.responsible_crew_id
LEFT JOIN Users cu ON cu.user_id = i.created_by
LEFT JOIN Users xu ON xu.user_id = i.cancelled_by
SQL;

    public function __construct(private readonly PDO $db)
    {
    }

    // ---------------------------------------------------------------- plans

    /**
     * @return list<array<string, mixed>>
     */
    public function listPlans(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT p.plan_id, p.project_id, p.plan_no, p.plan_name, p.description, p.status,
                    p.planned_start_date, p.planned_end_date, p.closed_at, p.reopened_at,
                    p.created_at, p.updated_at,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS created_by_name
             FROM project_closeout_plans p
             LEFT JOIN Users u ON u.user_id = p.created_by
             WHERE p.project_id = :project_id
             ORDER BY p.plan_id DESC'
        );
        $statement->execute(['project_id' => $projectId]);
        $plans = $statement->fetchAll();

        $counts = [];
        $countStatement = $this->db->prepare(
            'SELECT plan_id, status, COUNT(*) AS total
             FROM project_closeout_items
             WHERE project_id = :project_id
             GROUP BY plan_id, status'
        );
        $countStatement->execute(['project_id' => $projectId]);
        foreach ($countStatement->fetchAll() as $row) {
            $counts[(int) $row['plan_id']][(string) $row['status']] = (int) $row['total'];
        }

        foreach ($plans as &$plan) {
            $planCounts = $counts[(int) $plan['plan_id']] ?? [];
            $total = array_sum($planCounts);
            $closed = (int) ($planCounts['closed'] ?? 0);
            $denominator = $total - (int) ($planCounts['cancelled'] ?? 0);
            $plan['counts'] = $planCounts;
            $plan['item_count'] = $total;
            $plan['closed_count'] = $closed;
            $plan['completion_rate'] = $denominator > 0 ? round($closed / $denominator * 100, 1) : null;
        }
        unset($plan);

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    public function requirePlan(int $projectId, int $planId): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM project_closeout_plans WHERE plan_id = :plan_id AND project_id = :project_id LIMIT 1'
        );
        $statement->execute(['plan_id' => $planId, 'project_id' => $projectId]);
        $plan = $statement->fetch();
        if ($plan === false) {
            throw new HttpException('消项计划不存在或不属于当前项目。', 404);
        }

        return $plan;
    }

    /**
     * Creates a plan (no plan_id in input) or updates an open plan.
     *
     * @param array<string, mixed> $input
     */
    public function savePlan(int $projectId, array $input, int $userId): int
    {
        $planId = $this->optionalId($input, 'plan_id');
        $planName = $this->requiredText($input, 'plan_name', '消项计划名称', 255);
        $description = $this->optionalText($input, 'description', 5000);
        $startDate = $this->optionalDate($input['planned_start_date'] ?? null, '计划开始日期');
        $endDate = $this->optionalDate($input['planned_end_date'] ?? null, '计划结束日期');
        $this->assertDateOrder($startDate, $endDate);

        if ($planId === null) {
            $status = (string) ($input['status'] ?? 'active');
            if (!in_array($status, ['draft', 'active'], true)) {
                throw new HttpException('新建计划状态只能为草稿或进行中。', 422);
            }
            $planNo = $this->nextNumber($projectId, 'CLO_PLAN', 'P' . $projectId . '-CLO-PLAN');
            $insert = $this->db->prepare(
                'INSERT INTO project_closeout_plans
                 (project_id, plan_no, plan_name, description, status, planned_start_date, planned_end_date, created_by)
                 VALUES (:project_id, :plan_no, :plan_name, :description, :status, :start_date, :end_date, :created_by)'
            );
            $insert->execute([
                'project_id' => $projectId,
                'plan_no' => $planNo,
                'plan_name' => $planName,
                'description' => $description,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'created_by' => $userId,
            ]);

            $newPlanId = (int) $this->db->lastInsertId();
            // Active plans provision themselves: every measured parent material
            // becomes an item right away, no manual picking step. Draft plans
            // wait until they are started.
            if ($status === 'active') {
                $this->autoProvision($projectId, $newPlanId, $userId);
            }

            return $newPlanId;
        }

        $plan = $this->requirePlan($projectId, $planId);
        if ($plan['status'] === 'closed') {
            throw new HttpException('已关闭的消项计划不能修改，请先重新打开计划。', 422);
        }
        $update = $this->db->prepare(
            'UPDATE project_closeout_plans
             SET plan_name = :plan_name, description = :description,
                 planned_start_date = :start_date, planned_end_date = :end_date
             WHERE plan_id = :plan_id AND project_id = :project_id'
        );
        $update->execute([
            'plan_name' => $planName,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'plan_id' => $planId,
            'project_id' => $projectId,
        ]);

        return $planId;
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function setPlanStatus(int $projectId, int $planId, string $action, int $userId): array
    {
        $before = $this->requirePlan($projectId, $planId);
        $now = $this->nowUtc();

        switch ($action) {
            case 'activate':
                if ($before['status'] !== 'draft') {
                    throw new HttpException('只有草稿状态的计划可以启动。', 422);
                }
                $statement = $this->db->prepare(
                    'UPDATE project_closeout_plans SET status = \'active\'
                     WHERE plan_id = :plan_id AND project_id = :project_id'
                );
                break;
            case 'close':
                if (!in_array($before['status'], ['draft', 'active'], true)) {
                    throw new HttpException('只有草稿或进行中的计划可以关闭。', 422);
                }
                $statement = $this->db->prepare(
                    'UPDATE project_closeout_plans
                     SET status = \'closed\', closed_at = :now, closed_by = :user_id
                     WHERE plan_id = :plan_id AND project_id = :project_id'
                );
                $statement->bindValue(':now', $now);
                $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
                break;
            case 'reopen':
                if ($before['status'] !== 'closed') {
                    throw new HttpException('只有已关闭的计划可以重新打开。', 422);
                }
                $statement = $this->db->prepare(
                    'UPDATE project_closeout_plans
                     SET status = \'active\', reopened_at = :now, reopened_by = :user_id
                     WHERE plan_id = :plan_id AND project_id = :project_id'
                );
                $statement->bindValue(':now', $now);
                $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
                break;
            default:
                throw new HttpException('不支持的计划状态操作。', 422);
        }

        $statement->bindValue(':plan_id', $planId, PDO::PARAM_INT);
        $statement->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $statement->execute();

        if ($action === 'activate' || $action === 'reopen') {
            $this->autoProvision($projectId, $planId, $userId);
        }

        return ['before' => $before, 'after' => $this->requirePlan($projectId, $planId)];
    }

    // ---------------------------------------------------------------- items

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listItems(int $projectId, array $filters = [], ?int $limit = null): array
    {
        $where = ['i.project_id = :project_id'];
        $params = ['project_id' => $projectId];

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $where[] = 'i.plan_id = :plan_id';
            $params['plan_id'] = $planId;
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && isset(CloseoutLabels::ITEM_STATUS[$status])) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        $risk = (string) ($filters['risk_level'] ?? '');
        if ($risk !== '' && isset(CloseoutLabels::RISK_LEVEL[$risk])) {
            $where[] = 'i.risk_level = :risk_level';
            $params['risk_level'] = $risk;
        }
        $responsible = (int) ($filters['responsible_user_id'] ?? 0);
        if ($responsible > 0) {
            $where[] = 'i.responsible_user_id = :responsible_user_id';
            $params['responsible_user_id'] = $responsible;
        }
        $responsibleCrew = (int) ($filters['responsible_crew_id'] ?? 0);
        if ($responsibleCrew > 0) {
            $where[] = 'i.responsible_crew_id = :responsible_crew_id';
            $params['responsible_crew_id'] = $responsibleCrew;
        }
        if (!empty($filters['overdue'])) {
            $where[] = "i.status IN ('pending', 'ongoing', 'pending_accept')
                        AND i.planned_end_date IS NOT NULL
                        AND i.planned_end_date < :today";
            $params['today'] = $this->today();
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(i.item_no LIKE :keyword OR i.title LIKE :keyword
                        OR m.material_name LIKE :keyword OR m.material_code LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $params['today_order'] = $this->today();
        $sql = self::ITEM_SELECT
            . ' WHERE ' . implode(' AND ', $where)
            // Work queue order: open items first (overdue before future), then
            // closed, then cancelled. Within a group the earliest planned end
            // date wins. The flat list must not open with already-closed rows
            // just because their dates are older.
            . " ORDER BY
                CASE WHEN i.status IN ('pending', 'ongoing', 'pending_accept') THEN 0
                     WHEN i.status = 'closed' THEN 1
                     ELSE 2 END,
                CASE WHEN i.status IN ('pending', 'ongoing', 'pending_accept')
                          AND i.planned_end_date IS NOT NULL
                          AND i.planned_end_date < :today_order THEN 0 ELSE 1 END,
                COALESCE(i.planned_end_date, '9999-12-31'),
                i.item_id DESC";
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $items = $statement->fetchAll();

        return $this->decorateItems($items);
    }

    /**
     * @return array<string, mixed>
     */
    public function requireItem(int $projectId, int $itemId): array
    {
        $statement = $this->db->prepare(
            self::ITEM_SELECT . ' WHERE i.project_id = :project_id AND i.item_id = :item_id LIMIT 1'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);
        $item = $statement->fetch();
        if ($item === false) {
            throw new HttpException('消项事项不存在或不属于当前项目。', 404);
        }

        return $this->decorateItems([$item])[0];
    }

    /**
     * Batch include BOM materials into an active plan. Any conflict rolls back
     * the whole selection so the operator can re-select without half a batch.
     *
     * V2.0: each included parent material also gets its space sub-items built
     * from the material estimate detail (父材料 × 楼栋/楼层/房间 × 数量). A
     * parent material without any estimate row is rejected unless the caller
     * explicitly allows an unlocated (legacy) item.
     *
     * @param list<int|string> $materialIds
     * @param array<string, mixed> $defaults
     * @param list<string>|null $spaceKeys building-floor-room keys; null = all measured spaces
     * @return list<array{item_id: int, item_no: string}>
     */
    public function createItems(
        int $projectId,
        int $planId,
        array $materialIds,
        array $defaults,
        int $userId,
        ?array $spaceKeys = null,
        bool $allowUnlocated = false
    ): array {
        $plan = $this->requirePlan($projectId, $planId);
        if ($plan['status'] !== 'active') {
            throw new HttpException('只有进行中的消项计划可以新增事项，请先启动计划。', 422);
        }

        $materialIds = $this->normalizeIdList($materialIds);
        if ($materialIds === []) {
            throw new HttpException('请至少选择一项父材料。', 422);
        }

        $options = $this->normalizeItemDefaults($defaults, $projectId);
        $graph = $this->materialGraph($projectId);
        $names = $this->materialNames($projectId);
        $active = $this->activeItems($projectId);
        $this->assertSelectionOnlyTopLevel($projectId, $materialIds, $graph, $names);
        $created = [];

        $this->db->beginTransaction();
        try {
            foreach ($materialIds as $materialId) {
                $material = $this->requireMaterial($projectId, $materialId);
                $conflict = $this->coverageConflict($materialId, $active, $graph, $names);
                if ($conflict !== null) {
                    throw new HttpException($conflict, 422);
                }
                $coverageRoot = $this->coverageRoot($materialId, $options['coverage_mode'], $graph);
                $itemNo = $this->nextNumber($projectId, 'CLO', 'P' . $projectId . '-CLO');
                $insert = $this->db->prepare(
                    'INSERT INTO project_closeout_items
                     (project_id, plan_id, item_no, material_id, coverage_mode, coverage_root_id,
                      title, description, status, responsible_supplier_id, responsible_user_id, responsible_crew_id,
                      planned_start_date, planned_end_date, risk_level, created_by)
                     VALUES
                     (:project_id, :plan_id, :item_no, :material_id, :coverage_mode, :coverage_root_id,
                      :title, :description, \'pending\', :supplier_id, :responsible_user_id, :responsible_crew_id,
                      :start_date, :end_date, :risk_level, :created_by)'
                );
                $insert->execute([
                    'project_id' => $projectId,
                    'plan_id' => $planId,
                    'item_no' => $itemNo,
                    'material_id' => $materialId,
                    'coverage_mode' => $options['coverage_mode'],
                    'coverage_root_id' => $coverageRoot,
                    'title' => '',
                    'description' => $options['description'],
                    'supplier_id' => $options['responsible_supplier_id'],
                    'responsible_user_id' => $options['responsible_user_id'],
                    'responsible_crew_id' => $options['responsible_crew_id'] ?? null,
                    'start_date' => $options['planned_start_date'],
                    'end_date' => $options['planned_end_date'],
                    'risk_level' => $options['risk_level'],
                    'created_by' => $userId,
                ]);
                $itemId = (int) $this->db->lastInsertId();
                $locationCount = $this->createLocationsForItem(
                    $projectId,
                    $planId,
                    $itemId,
                    $materialId,
                    $options,
                    $userId,
                    $spaceKeys
                );
                if ($locationCount === 0 && !$allowUnlocated && $this->projectHasEstimates($projectId)) {
                    throw new HttpException(
                        '父材料「' . (string) $material['material_name'] . '」尚未做材料测算，无法定位空间；'
                        . '请先在材料测算中录入该父材料的楼栋/楼层/房间数量后再纳入。',
                        422
                    );
                }
                $active[] = [
                    'item_id' => $itemId,
                    'item_no' => $itemNo,
                    'material_id' => $materialId,
                    'status' => 'pending',
                ];
                $created[] = ['item_id' => $itemId, 'item_no' => $itemNo];
            }
            $this->db->commit();
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new HttpException('该父材料已存在有效消项事项（并发冲突），请刷新后重试。', 422);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updateItem(int $projectId, int $itemId, array $input, int $userId): void
    {
        $item = $this->requireItem($projectId, $itemId);
        if (in_array($item['status'], ['closed', 'cancelled'], true)) {
            throw new HttpException('已消项或已取消的事项不能修改管理信息。', 422);
        }

        $title = $this->optionalText($input, 'title', 255) ?? '';
        $description = $this->optionalText($input, 'description', 5000);
        $startDate = $this->optionalDate($input['planned_start_date'] ?? null, '计划开始日期');
        $endDate = $this->optionalDate($input['planned_end_date'] ?? null, '计划结束日期');
        $this->assertDateOrder($startDate, $endDate);
        $supplierId = $this->optionalId($input, 'responsible_supplier_id');
        $responsibleUserId = $this->optionalId($input, 'responsible_user_id');
        $responsibleCrewId = $this->optionalId($input, 'responsible_crew_id');
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
        if ($projectId > 0) {
            $this->assertCrew($projectId, $responsibleCrewId);
        }
        $risk = $this->normalizeRisk((string) ($input['risk_level'] ?? $item['risk_level']));
        $coverageMode = (string) ($input['coverage_mode'] ?? $item['coverage_mode']);
        if (!isset(CloseoutLabels::COVERAGE_MODE[$coverageMode])) {
            throw new HttpException('覆盖方式无效。', 422);
        }
        if ($coverageMode !== 'whole') {
            throw new HttpException('消项清单只纳入父材料，子材料不单独纳入，已不再支持拆分覆盖方式。', 422);
        }
        $graph = $this->materialGraph($projectId);
        if ($coverageMode !== $item['coverage_mode']) {
            $active = array_values(array_filter(
                $this->activeItems($projectId),
                static fn (array $row): bool => (int) $row['item_id'] !== $itemId
            ));
            $conflict = $this->coverageConflict(
                (int) $item['material_id'],
                $active,
                $graph,
                $this->materialNames($projectId)
            );
            if ($conflict !== null) {
                throw new HttpException($conflict, 422);
            }
        }
        $coverageRoot = $this->coverageRoot(
            (int) $item['material_id'],
            $coverageMode,
            $graph
        );

        $this->db->prepare(
            'UPDATE project_closeout_items
             SET title = :title, description = :description, risk_level = :risk_level,
                 coverage_mode = :coverage_mode, coverage_root_id = :coverage_root_id,
                 responsible_supplier_id = :supplier_id, responsible_user_id = :responsible_user_id,
                 responsible_crew_id = :responsible_crew_id,
                 planned_start_date = :start_date, planned_end_date = :end_date
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute([
            'title' => $title,
            'description' => $description,
            'risk_level' => $risk,
            'coverage_mode' => $coverageMode,
            'coverage_root_id' => $coverageRoot,
            'supplier_id' => $supplierId,
            'responsible_user_id' => $responsibleUserId,
            'responsible_crew_id' => $responsibleCrewId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'project_id' => $projectId,
            'item_id' => $itemId,
        ]);

        unset($userId);
    }

    /**
     * Applies the same plan dates / owner to the selected open items.
     *
     * @param list<int|string> $itemIds
     * @param array<string, mixed> $input
     * @return array{updated: int, skipped: int}
     */
    public function batchUpdate(int $projectId, array $itemIds, array $input, int $userId): array
    {
        $itemIds = $this->normalizeIdList($itemIds);
        if ($itemIds === []) {
            throw new HttpException('请先选择需要批量调整的事项。', 422);
        }
        $startDate = $this->optionalDate($input['planned_start_date'] ?? null, '计划开始日期');
        $endDate = $this->optionalDate($input['planned_end_date'] ?? null, '计划结束日期');
        $this->assertDateOrder($startDate, $endDate);
        $supplierId = $this->optionalId($input, 'responsible_supplier_id');
        $responsibleUserId = $this->optionalId($input, 'responsible_user_id');
        $responsibleCrewId = $this->optionalId($input, 'responsible_crew_id');
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
        if ($projectId > 0) {
            $this->assertCrew($projectId, $responsibleCrewId);
        }
        if ($startDate === null && $endDate === null && $supplierId === null && $responsibleUserId === null && $responsibleCrewId === null) {
            throw new HttpException('请至少填写一项批量调整内容。', 422);
        }

        $updated = 0;
        $skipped = 0;
        $this->db->beginTransaction();
        try {
            foreach ($itemIds as $itemId) {
                $item = $this->requireItem($projectId, $itemId);
                if (!in_array($item['status'], ['pending', 'ongoing', 'pending_accept'], true)) {
                    $skipped++;
                    continue;
                }
                $this->db->prepare(
                    'UPDATE project_closeout_items
                     SET planned_start_date = COALESCE(:start_date, planned_start_date),
                         planned_end_date = COALESCE(:end_date, planned_end_date),
                         responsible_supplier_id = COALESCE(:supplier_id, responsible_supplier_id),
                         responsible_user_id = COALESCE(:responsible_user_id, responsible_user_id),
                         responsible_crew_id = COALESCE(:responsible_crew_id, responsible_crew_id)
                     WHERE project_id = :project_id AND item_id = :item_id'
                )->execute([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'supplier_id' => $supplierId,
                    'responsible_user_id' => $responsibleUserId,
                    'responsible_crew_id' => $responsibleCrewId,
                    'project_id' => $projectId,
                    'item_id' => $itemId,
                ]);
                $updated++;
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        unset($userId);

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function startItem(int $projectId, int $itemId, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'pending') {
            throw new HttpException('只有待处理的事项可以开始执行。', 422);
        }

        $locations = $this->listLocations($projectId, $itemId);
        if ($locations !== []) {
            // Space-tracked items: "start" means start every space that has not
            // started yet. Submission and acceptance stay per space.
            $started = 0;
            foreach ($locations as $location) {
                if ((string) $location['status'] !== 'pending') {
                    continue;
                }
                $this->db->prepare(
                    "UPDATE project_closeout_locations SET status = 'ongoing'
                     WHERE project_id = :project_id AND location_id = :location_id AND status = 'pending'"
                )->execute(['project_id' => $projectId, 'location_id' => (int) $location['location_id']]);
                $started++;
            }
            if ($started > 0) {
                $this->recalcItemStatus($projectId, $itemId);
            }

            unset($userId);

            return ['item' => $item, 'spaces_started' => $started];
        }

        $this->db->prepare(
            'UPDATE project_closeout_items SET status = \'ongoing\'
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        unset($userId);

        return ['item' => $item, 'spaces_started' => 0];
    }

    public function submitItem(int $projectId, int $itemId, string $note, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'ongoing') {
            throw new HttpException('只有进行中的事项可以提交验收。', 422);
        }
        if ($this->locationCount($projectId, $itemId) > 0) {
            throw new HttpException('该事项已按空间跟踪，请逐个空间提交验收。', 422);
        }
        $note = trim($note);
        if (mb_strlen($note) > 5000) {
            throw new HttpException('提交说明不能超过 5000 个字符。', 422);
        }
        if ($note === '') {
            $note = '提交验收';
        }
        $now = $this->nowUtc();

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE project_closeout_items
                 SET status = \'pending_accept\', submitted_at = :now
                 WHERE project_id = :project_id AND item_id = :item_id'
            )->execute(['now' => $now, 'project_id' => $projectId, 'item_id' => $itemId]);
            $this->insertProgress($projectId, (int) $item['plan_id'], $itemId, 'submit', $note, $userId, $now);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function acceptItem(int $projectId, int $itemId, array $input, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'pending_accept') {
            throw new HttpException('只有待验收的事项可以执行验收。', 422);
        }
        if ($this->locationCount($projectId, $itemId) > 0) {
            return $this->acceptLocatedItem($projectId, $itemId, $input, $userId);
        }
        $result = (string) ($input['result'] ?? '');
        if (!in_array($result, ['passed', 'rejected'], true)) {
            throw new HttpException('请选择验收结论。', 422);
        }
        $remark = $this->optionalText($input, 'remark', 5000) ?? '';
        if ($result === 'rejected' && $remark === '') {
            throw new HttpException('验收不通过时必须填写验收意见。', 422);
        }
        $evidence = $this->optionalText($input, 'evidence_reference', 1000);
        $today = $this->today();
        $submittedDate = $item['submitted_at'] !== null && $item['submitted_at'] !== ''
            ? Support::formatDateTime((string) $item['submitted_at'], 'Y-m-d')
            : $today;
        $actualDate = null;
        if ($result === 'passed') {
            $actualDate = $this->optionalDate($input['actual_complete_date'] ?? null, '实际完成日期') ?? $today;
            if ($actualDate < $submittedDate) {
                throw new HttpException('实际完成日期不得早于提交验收日期（' . $submittedDate . '）。', 422);
            }
        }
        $now = $this->nowUtc();

        $this->db->beginTransaction();
        try {
            if ($result === 'passed') {
                $this->db->prepare(
                    'UPDATE project_closeout_items
                     SET status = \'closed\', actual_complete_date = :actual_date
                     WHERE project_id = :project_id AND item_id = :item_id'
                )->execute([
                    'actual_date' => $actualDate,
                    'project_id' => $projectId,
                    'item_id' => $itemId,
                ]);
            } else {
                $this->db->prepare(
                    'UPDATE project_closeout_items SET status = \'ongoing\'
                     WHERE project_id = :project_id AND item_id = :item_id'
                )->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            }
            $this->db->prepare(
                'INSERT INTO project_closeout_acceptances
                 (project_id, plan_id, item_id, result, remark, evidence_reference, accepted_by, accepted_at)
                 VALUES (:project_id, :plan_id, :item_id, :result, :remark, :evidence, :accepted_by, :accepted_at)'
            )->execute([
                'project_id' => $projectId,
                'plan_id' => (int) $item['plan_id'],
                'item_id' => $itemId,
                'result' => $result,
                'remark' => $remark !== '' ? $remark : null,
                'evidence' => $evidence,
                'accepted_by' => $userId,
                'accepted_at' => $now,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $item;
    }

    public function cancelItem(int $projectId, int $itemId, string $reason, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] === 'closed') {
            throw new HttpException('已消项的事项不能取消，如需返工请使用重新打开。', 422);
        }
        if ($item['status'] === 'cancelled') {
            throw new HttpException('该事项已经是取消状态。', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException('取消消项事项必须填写取消原因。', 422);
        }
        if (mb_strlen($reason) > 5000) {
            throw new HttpException('取消原因不能超过 5000 个字符。', 422);
        }
        $now = $this->nowUtc();
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE project_closeout_items
                 SET status = \'cancelled\', cancel_reason = :reason, cancelled_by = :user_id, cancelled_at = :now
                 WHERE project_id = :project_id AND item_id = :item_id'
            )->execute([
                'reason' => $reason,
                'user_id' => $userId,
                'now' => $now,
                'project_id' => $projectId,
                'item_id' => $itemId,
            ]);
            // V2.0: cancelling the item cancels every still-open space in the
            // same transaction, with the same reason / operator / time.
            $this->db->prepare(
                "UPDATE project_closeout_locations
                 SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :user_id, cancelled_at = :now
                 WHERE project_id = :project_id AND item_id = :item_id
                   AND status NOT IN ('closed', 'cancelled')"
            )->execute([
                'reason' => $reason,
                'user_id' => $userId,
                'now' => $now,
                'project_id' => $projectId,
                'item_id' => $itemId,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $item;
    }

    public function restoreItem(int $projectId, int $itemId, string $reason, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'cancelled') {
            throw new HttpException('只有已取消的事项可以恢复。', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException('恢复消项事项必须填写恢复原因。', 422);
        }
        $this->requireMaterial($projectId, (int) $item['material_id']);
        $active = array_values(array_filter(
            $this->activeItems($projectId),
            static fn (array $row): bool => (int) $row['item_id'] !== $itemId
        ));
        $conflict = $this->coverageConflict(
            (int) $item['material_id'],
            $active,
            $this->materialGraph($projectId),
            $this->materialNames($projectId)
        );
        if ($conflict !== null) {
            throw new HttpException('恢复失败：' . $conflict, 422);
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE project_closeout_items
                 SET status = \'pending\', cancel_reason = NULL, cancelled_by = NULL, cancelled_at = NULL,
                     submitted_at = NULL, actual_complete_date = NULL
                 WHERE project_id = :project_id AND item_id = :item_id'
            )->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            // V2.0: cancelled spaces come back as pending; their baseline stays.
            $this->db->prepare(
                "UPDATE project_closeout_locations
                 SET status = 'pending', cancel_reason = NULL, cancelled_by = NULL, cancelled_at = NULL,
                     submitted_at = NULL, accepted_at = NULL, accepted_by = NULL
                 WHERE project_id = :project_id AND item_id = :item_id AND status = 'cancelled'"
            )->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            $this->recalcItemStatus($projectId, $itemId);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $item;
    }

    public function reopenItem(int $projectId, int $itemId, string $reason, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'closed') {
            throw new HttpException('只有已消项的事项可以重新打开。', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException('重新打开已消项事项必须填写原因。', 422);
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE project_closeout_items
                 SET status = \'ongoing\', actual_complete_date = NULL
                 WHERE project_id = :project_id AND item_id = :item_id'
            )->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            $this->db->prepare(
                "UPDATE project_closeout_locations
                 SET status = 'ongoing', accepted_at = NULL, accepted_by = NULL
                 WHERE project_id = :project_id AND item_id = :item_id AND status = 'closed'"
            )->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        unset($userId, $reason);

        return $item;
    }

    public function addProgress(int $projectId, int $itemId, string $note, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if (in_array($item['status'], ['closed', 'cancelled'], true)) {
            throw new HttpException('已消项或已取消的事项不能再记录进度。', 422);
        }
        $note = trim($note);
        if ($note === '') {
            throw new HttpException('请填写进度说明。', 422);
        }
        if (mb_strlen($note) > 5000) {
            throw new HttpException('进度说明不能超过 5000 个字符。', 422);
        }
        $this->insertProgress($projectId, (int) $item['plan_id'], $itemId, 'note', $note, $userId, $this->nowUtc());

        return $item;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProgress(int $projectId, int $itemId): array
    {
        $statement = $this->db->prepare(
            'SELECT g.progress_id, g.progress_type, g.progress_note, g.recorded_at,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS recorded_by_name
             FROM project_closeout_progress_logs g
             LEFT JOIN Users u ON u.user_id = g.recorded_by
             WHERE g.project_id = :project_id AND g.item_id = :item_id
             ORDER BY g.progress_id DESC'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAcceptances(int $projectId, int $itemId): array
    {
        $statement = $this->db->prepare(
            'SELECT a.acceptance_id, a.result, a.remark, a.evidence_reference, a.accepted_at,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS accepted_by_name
             FROM project_closeout_acceptances a
             LEFT JOIN Users u ON u.user_id = a.accepted_by
             WHERE a.project_id = :project_id AND a.item_id = :item_id
             ORDER BY a.acceptance_id DESC'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(int $projectId, ?int $planId = null): array
    {
        $where = 'project_id = :project_id';
        $params = ['project_id' => $projectId];
        if ($planId !== null && $planId > 0) {
            $where .= ' AND plan_id = :plan_id';
            $params['plan_id'] = $planId;
        }

        $counts = array_fill_keys(array_keys(CloseoutLabels::ITEM_STATUS), 0);
        $statement = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM project_closeout_items WHERE ' . $where . ' GROUP BY status'
        );
        $statement->execute($params);
        $total = 0;
        foreach ($statement->fetchAll() as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $count;
            }
            $total += $count;
        }

        $overdueStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM project_closeout_items
             WHERE " . $where . "
               AND status IN ('pending', 'ongoing', 'pending_accept')
               AND planned_end_date IS NOT NULL
               AND planned_end_date < :today"
        );
        $overdueStatement->execute($params + ['today' => $this->today()]);
        $overdue = (int) $overdueStatement->fetchColumn();

        $denominator = $counts['pending'] + $counts['ongoing'] + $counts['pending_accept'] + $counts['closed'];

        // V2.0: quantity-weighted totals from the space sub-items. Kept
        // separate from the item-count rate so the two yardsticks are never
        // silently mixed in one number.
        $qtyStatement = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN baseline_qty ELSE 0 END), 0) AS qty_total,
                COALESCE(SUM(CASE WHEN status = 'closed' THEN baseline_qty ELSE 0 END), 0) AS qty_closed
             FROM project_closeout_locations
             WHERE " . $where
        );
        $qtyStatement->execute($params);
        $qty = $qtyStatement->fetch() ?: ['qty_total' => 0, 'qty_closed' => 0];
        $qtyTotal = (float) $qty['qty_total'];
        $qtyClosed = (float) $qty['qty_closed'];

        return [
            'total' => $total,
            'counts' => $counts,
            'open' => $counts['pending'] + $counts['ongoing'] + $counts['pending_accept'],
            'overdue' => $overdue,
            'denominator' => $denominator,
            'completion_rate' => $denominator > 0 ? round($counts['closed'] / $denominator * 100, 1) : null,
            'qty_total' => round($qtyTotal, 4),
            'qty_closed' => round($qtyClosed, 4),
            'qty_completion_rate' => $qtyTotal > 0 ? round($qtyClosed / $qtyTotal * 100, 1) : null,
            'today' => $this->today(),
        ];
    }

    /**
     * Chart-board aggregates for one plan (or every plan when $planId is null
     * or 0): status counts from statistics(), risk mix of still-open work,
     * near-term due load and open items without an owner. Closed / cancelled
     * items stay in the status counts for transparency but are excluded from
     * risk / due figures because they no longer carry work.
     *
     * @return array<string, mixed>
     */
    public function boardStatistics(int $projectId, ?int $planId = null): array
    {
        $stats = $this->statistics($projectId, $planId);
        $where = 'project_id = :project_id';
        $params = ['project_id' => $projectId];
        if ($planId !== null && $planId > 0) {
            $where .= ' AND plan_id = :plan_id';
            $params['plan_id'] = $planId;
        }

        $riskCounts = array_fill_keys(array_keys(CloseoutLabels::RISK_LEVEL), 0);
        $riskStatement = $this->db->prepare(
            "SELECT risk_level, COUNT(*) AS total FROM project_closeout_items
             WHERE " . $where . " AND status IN ('pending', 'ongoing', 'pending_accept')
             GROUP BY risk_level"
        );
        $riskStatement->execute($params);
        foreach ($riskStatement->fetchAll() as $row) {
            $risk = (string) $row['risk_level'];
            if (array_key_exists($risk, $riskCounts)) {
                $riskCounts[$risk] = (int) $row['total'];
            }
        }

        $today = $this->today();
        $soon = (new DateTimeImmutable($today, new DateTimeZone('UTC')))
            ->modify('+7 days')
            ->format('Y-m-d');

        $dueStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM project_closeout_items
             WHERE " . $where . "
               AND status IN ('pending', 'ongoing', 'pending_accept')
               AND planned_end_date IS NOT NULL
               AND planned_end_date >= :today
               AND planned_end_date <= :soon"
        );
        $dueStatement->execute($params + ['today' => $today, 'soon' => $soon]);
        $dueSoon = (int) $dueStatement->fetchColumn();

        $unassignedStatement = $this->db->prepare(
            "SELECT COUNT(*) FROM project_closeout_items
             WHERE " . $where . "
               AND status IN ('pending', 'ongoing', 'pending_accept')
               AND responsible_crew_id IS NULL"
        );
        $unassignedStatement->execute($params);
        $unassigned = (int) $unassignedStatement->fetchColumn();

        $stats['risk_counts'] = $riskCounts;
        $stats['due_soon'] = $dueSoon;
        $stats['soon_date'] = $soon;
        $stats['unassigned'] = $unassigned;

        return $stats;
    }

    // ------------------------------------------------------------ BOM source

    /**
     * BOM materials of the project enriched with closeout availability.
     *
     * @return list<array<string, mixed>>
     */
    public function bomCandidates(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT m.material_id, m.material_code, m.material_name, m.specifications, m.unit,
                    c.category_name
             FROM Materials m
             LEFT JOIN Material_Categories c ON c.category_id = m.category_id
             WHERE m.project_id = :project_id
             ORDER BY c.category_name, m.material_name, m.material_id'
        );
        $statement->execute(['project_id' => $projectId]);
        $materials = $statement->fetchAll();

        $graph = $this->materialGraph($projectId);
        $names = $this->materialNames($projectId);
        $active = $this->activeItems($projectId);
        $activeByMaterial = [];
        foreach ($active as $item) {
            $activeByMaterial[(int) $item['material_id']] = $item;
        }

        $rows = [];
        // Projects without any parent material fall back to every top-level
        // material. Once at least one composite parent exists, only those
        // composite parents are selectable and children / standalone materials
        // stay out.
        $hasParentMaterials = $graph['children'] !== [];
        foreach ($materials as $material) {
            $materialId = (int) $material['material_id'];
            if ($hasParentMaterials) {
                if (($graph['parents'][$materialId] ?? []) !== []) {
                    continue;
                }
                if (($graph['children'][$materialId] ?? []) === []) {
                    continue;
                }
            }
            $existing = $activeByMaterial[$materialId] ?? null;
            $conflict = $existing !== null
                ? '已纳入事项 ' . (string) $existing['item_no']
                : $this->coverageConflict($materialId, $active, $graph, $names);
            $parents = [];
            foreach ($this->parentsOf($materialId, $graph) as $parentId) {
                $parents[] = $names[$parentId] ?? ('#' . $parentId);
            }
            $material['parents'] = $parents;
            $material['children_count'] = count($graph['children'][$materialId] ?? []);
            $material['existing_item_id'] = $existing !== null ? (int) $existing['item_id'] : null;
            $material['existing_item_no'] = $existing !== null ? (string) $existing['item_no'] : null;
            $material['blocked_reason'] = $conflict;
            $material['selectable'] = $conflict === null;
            $rows[] = $material;
        }

        return $rows;
    }

    /**
     * Total BOM materials of one project, used to explain hidden rows.
     */
    public function bomMaterialCount(int $projectId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM Materials WHERE project_id = :project_id'
        );
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Whether the project has at least one parent material (a material with a
     * child composition). Self-loops are ignored to stay consistent with
     * materialGraph().
     */
    public function hasParentMaterials(int $projectId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM Material_Compositions
             WHERE project_id = :project_id AND parent_material_id <> child_material_id
             LIMIT 1'
        );
        $statement->execute(['project_id' => $projectId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * BOM parent / child edges of one project, excluding self-loops.
     *
     * @return array{parents: array<int, list<int>>, children: array<int, list<int>>}
     */
    public function materialGraph(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT parent_material_id, child_material_id
             FROM Material_Compositions
             WHERE project_id = :project_id'
        );
        $statement->execute(['project_id' => $projectId]);

        $parents = [];
        $children = [];
        foreach ($statement->fetchAll() as $edge) {
            $parent = (int) $edge['parent_material_id'];
            $child = (int) $edge['child_material_id'];
            if ($parent === $child) {
                continue;
            }
            $parents[$child][] = $parent;
            $children[$parent][] = $child;
        }

        return ['parents' => $parents, 'children' => $children];
    }

    /**
     * Direct parent / child names read live from the current BOM graph.
     *
     * @return array{
     *     parents: list<array{material_id: int, material_name: string}>,
     *     children: list<array{material_id: int, material_name: string}>
     * }
     */
    public function materialRelations(int $projectId, int $materialId): array
    {
        $graph = $this->materialGraph($projectId);
        $names = $this->materialNames($projectId);

        $map = static function (array $ids) use ($names): array {
            $rows = [];
            foreach ($ids as $id) {
                $rows[] = [
                    'material_id' => (int) $id,
                    'material_name' => $names[(int) $id] ?? ('#' . (int) $id),
                ];
            }

            return $rows;
        };

        return [
            'parents' => $map($graph['parents'][$materialId] ?? []),
            'children' => $map($graph['children'][$materialId] ?? []),
        ];
    }

    /** @return list<array{user_id: int, username: string, display_name: string, name: string}> */
    public function activeUsers(): array
    {
        $statement = $this->db->query(
            'SELECT user_id, username, display_name
             FROM Users
             WHERE is_active = 1
             ORDER BY COALESCE(NULLIF(display_name, \'\'), username), username'
        );

        return array_map(
            static function (array $row): array {
                $username = (string) $row['username'];
                $displayName = trim((string) ($row['display_name'] ?? ''));

                return [
                    'user_id' => (int) $row['user_id'],
                    'username' => $username,
                    'display_name' => $displayName,
                    'name' => $displayName !== '' ? $displayName : $username,
                ];
            },
            $statement->fetchAll()
        );
    }

    /**
     * Responsible parties for closeout work are crews, not system accounts: the
     * Users table only holds management logins, while the crews on site are the
     * ones who actually execute and report. Crews are project-scoped.
     *
     * @return list<array{crew_id: int, crew_name: string, leader_name: string, type_name: string, name: string}>
     */
    public function activeCrews(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT c.crew_id, c.crew_name, c.leader_name, t.type_name
             FROM Labor_Crews c
             LEFT JOIN Labor_Crew_Types t ON t.crew_type_id = c.crew_type_id
             WHERE c.project_id = :project_id AND c.is_active = 1
             ORDER BY COALESCE(t.sort_order, 9999), c.crew_name'
        );
        $statement->execute(['project_id' => $projectId]);

        return array_map(
            static function (array $row): array {
                $crewName = trim((string) $row['crew_name']);
                $leader = trim((string) ($row['leader_name'] ?? ''));

                return [
                    'crew_id' => (int) $row['crew_id'],
                    'crew_name' => $crewName,
                    'leader_name' => $leader,
                    'type_name' => trim((string) ($row['type_name'] ?? '')),
                    'name' => $leader !== '' ? $crewName . '（' . $leader . '）' : $crewName,
                ];
            },
            $statement->fetchAll()
        );
    }

    /**
     * Verified against the active project so a crew from another project can
     * never be attached to a closeout item.
     */
    private function assertCrew(int $projectId, ?int $crewId): void
    {
        if ($crewId === null) {
            return;
        }
        $statement = $this->db->prepare(
            'SELECT 1 FROM Labor_Crews WHERE project_id = :project_id AND crew_id = :crew_id AND is_active = 1'
        );
        $statement->execute(['project_id' => $projectId, 'crew_id' => $crewId]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('责任班组不存在或已归档，请从当前项目的班组目录中选择。', 422);
        }
    }

    /**
     * One display string for the responsible party. Crew wins over the legacy
     * user column: new assignments only ever write the crew.
     *
     * @param array<string, mixed> $row
     */
    private function responsibleLabel(array $row): ?string
    {
        $crew = trim((string) ($row['responsible_crew_name'] ?? ''));
        $leader = trim((string) ($row['responsible_crew_leader'] ?? ''));
        if ($crew !== '') {
            return $leader !== '' ? $crew . '（' . $leader . '）' : $crew;
        }
        $user = trim((string) ($row['responsible_user_name'] ?? ''));

        return $user !== '' ? $user : null;
    }

    /** @return list<array{supplier_id: int, supplier_name: string}> */
    public function suppliers(): array
    {
        $statement = $this->db->query(
            'SELECT supplier_id, supplier_name FROM Suppliers ORDER BY supplier_name'
        );

        return array_map(
            static fn (array $row): array => [
                'supplier_id' => (int) $row['supplier_id'],
                'supplier_name' => (string) $row['supplier_name'],
            ],
            $statement->fetchAll()
        );
    }

    // ------------------------------------------------------------- internals

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeItemDefaults(array $input, int $projectId = 0): array
    {
        $startDate = $this->optionalDate($input['planned_start_date'] ?? null, '计划开始日期');
        $endDate = $this->optionalDate($input['planned_end_date'] ?? null, '计划结束日期');
        $this->assertDateOrder($startDate, $endDate);
        $supplierId = $this->optionalId($input, 'responsible_supplier_id');
        $responsibleUserId = $this->optionalId($input, 'responsible_user_id');
        $responsibleCrewId = $this->optionalId($input, 'responsible_crew_id');
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
        if ($projectId > 0) {
            $this->assertCrew($projectId, $responsibleCrewId);
        }
        $mode = (string) ($input['coverage_mode'] ?? 'whole');
        if (!isset(CloseoutLabels::COVERAGE_MODE[$mode])) {
            throw new HttpException('覆盖方式无效。', 422);
        }
        if ($mode !== 'whole') {
            throw new HttpException('消项清单只纳入父材料，子材料不单独纳入，已不再支持拆分覆盖方式。', 422);
        }

        return [
            'planned_start_date' => $startDate,
            'planned_end_date' => $endDate,
            'responsible_supplier_id' => $supplierId,
            'responsible_user_id' => $responsibleUserId,
            'responsible_crew_id' => $responsibleCrewId,
            'risk_level' => $this->normalizeRisk((string) ($input['risk_level'] ?? 'medium')),
            'coverage_mode' => $mode,
            'description' => null,
        ];
    }

    private function normalizeRisk(string $risk): string
    {
        if (!isset(CloseoutLabels::RISK_LEVEL[$risk])) {
            throw new HttpException('风险等级无效。', 422);
        }

        return $risk;
    }

    private function assertSupplier(?int $supplierId): void
    {
        if ($supplierId === null) {
            return;
        }
        $statement = $this->db->prepare('SELECT 1 FROM Suppliers WHERE supplier_id = :supplier_id');
        $statement->execute(['supplier_id' => $supplierId]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('责任单位不存在。', 422);
        }
    }

    private function assertActiveUser(?int $userId): void
    {
        if ($userId === null) {
            return;
        }
        $statement = $this->db->prepare('SELECT 1 FROM Users WHERE user_id = :user_id AND is_active = 1');
        $statement->execute(['user_id' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('责任人不是有效的在用账号。', 422);
        }
    }

    /**
     * Resolves the root material used for coverage display and audit.
     *
     * @param array{parents: array<int, list<int>>, children: array<int, list<int>>} $graph
     */
    private function coverageRoot(int $materialId, string $mode, array $graph): int
    {
        $ancestors = $this->ancestorsOf($materialId, $graph);
        if ($mode === 'whole') {
            return $ancestors === [] ? $materialId : $ancestors[count($ancestors) - 1];
        }
        $parents = $this->parentsOf($materialId, $graph);
        if ($parents === []) {
            throw new HttpException('该材料没有上级父材料，不能按拆分方式管控。', 422);
        }

        return $parents[0];
    }

    /**
     * One active item per material and no ancestor/descendant pair. Returns the
     * operator-facing reason when the material cannot be included.
     *
     * @param list<array<string, mixed>> $active
     * @param array{parents: array<int, list<int>>, children: array<int, list<int>>} $graph
     * @param array<int, string> $names
     */
    private function coverageConflict(int $materialId, array $active, array $graph, array $names): ?string
    {
        $ancestors = $this->ancestorsOf($materialId, $graph);
        $descendants = $this->descendantsOf($materialId, $graph);

        foreach ($active as $item) {
            $otherId = (int) $item['material_id'];
            $otherName = $names[$otherId] ?? ('#' . $otherId);
            $otherNo = (string) $item['item_no'];
            if ($otherId === $materialId) {
                return '该材料已纳入事项 ' . $otherNo . '，不能重复纳入。';
            }
            if (in_array($otherId, $ancestors, true)) {
                return '上级材料《' . $otherName . '》已纳入事项 ' . $otherNo . '，父子材料不能同时作为独立消项对象。';
            }
            if (in_array($otherId, $descendants, true)) {
                return '下级材料《' . $otherName . '》已纳入事项 ' . $otherNo . '，父子材料不能同时作为独立消项对象。';
            }
        }

        return null;
    }

    /**
     * All ancestors, nearest level first, farthest level last.
     *
     * @param array{parents: array<int, list<int>>} $graph
     * @return list<int>
     */
    private function ancestorsOf(int $materialId, array $graph): array
    {
        $chain = [];
        $visited = [$materialId => true];
        $queue = $graph['parents'][$materialId] ?? [];
        while ($queue !== []) {
            $current = (int) array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $chain[] = $current;
            foreach ($graph['parents'][$current] ?? [] as $parent) {
                $queue[] = (int) $parent;
            }
        }

        return $chain;
    }

    /**
     * With at least one parent material, the list is a composite-parent list:
     * every selected material must have a child component and must not be a
     * child itself. Projects without any parent material fall back to any
     * top-level material, so ownership is still checked but parent rules do
     * not apply.
     *
     * @param list<int> $materialIds
     * @param array{parents: array<int, list<int>>, children: array<int, list<int>>} $graph
     * @param array<int, string> $names
     */
    private function assertSelectionOnlyTopLevel(int $projectId, array $materialIds, array $graph, array $names): void
    {
        $hasParentMaterials = $graph['children'] !== [];
        foreach ($materialIds as $materialId) {
            // Resolve ownership first so a material from another project stays
            // a 404 and never leaks into the validation message.
            $this->requireMaterial($projectId, $materialId);
            if (!$hasParentMaterials) {
                continue;
            }
            $parents = $this->parentsOf($materialId, $graph);
            if ($parents !== []) {
                throw new HttpException(
                    '《' . ($names[$materialId] ?? $materialId) . '》是子材料，不纳入消项清单；请纳入其父材料《'
                    . ($names[$parents[0]] ?? $parents[0]) . '》。',
                    422
                );
            }
            if (($graph['children'][$materialId] ?? []) === []) {
                throw new HttpException(
                    '《' . ($names[$materialId] ?? $materialId) . '》没有下级组成，不是父材料，不纳入消项清单。',
                    422
                );
            }
        }
    }

    /**
     * @param array{parents: array<int, list<int>>, children: array<int, list<int>>} $graph
     * @return list<int>
     */
    private function descendantsOf(int $materialId, array $graph): array
    {
        $found = [];
        $visited = [$materialId => true];
        $queue = $graph['children'][$materialId] ?? [];
        while ($queue !== []) {
            $current = (int) array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $found[] = $current;
            foreach ($graph['children'][$current] ?? [] as $child) {
                $queue[] = (int) $child;
            }
        }

        return $found;
    }

    /**
     * @param array{parents: array<int, list<int>>, children: array<int, list<int>>} $graph
     * @return list<int>
     */
    private function parentsOf(int $materialId, array $graph): array
    {
        return array_values(array_map('intval', $graph['parents'][$materialId] ?? []));
    }

    /**
     * @return list<array{item_id: int, item_no: string, material_id: int, status: string}>
     */
    private function activeItems(int $projectId): array
    {
        $statement = $this->db->prepare(
            "SELECT item_id, item_no, material_id, status
             FROM project_closeout_items
             WHERE project_id = :project_id AND status <> 'cancelled'"
        );
        $statement->execute(['project_id' => $projectId]);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'item_id' => (int) $row['item_id'],
                'item_no' => (string) $row['item_no'],
                'material_id' => (int) $row['material_id'],
                'status' => (string) $row['status'],
            ];
        }

        return $rows;
    }

    /** @return array<int, string> material id => name */
    private function materialNames(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT material_id, material_name FROM Materials WHERE project_id = :project_id'
        );
        $statement->execute(['project_id' => $projectId]);

        $names = [];
        foreach ($statement->fetchAll() as $row) {
            $names[(int) $row['material_id']] = (string) $row['material_name'];
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireMaterial(int $projectId, int $materialId): array
    {
        $statement = $this->db->prepare(
            'SELECT m.material_id, m.material_code, m.material_name, m.unit, m.specifications,
                    m.material_description_detailed, m.category_id, c.category_name
             FROM Materials m
             LEFT JOIN Material_Categories c ON c.category_id = m.category_id
             WHERE m.project_id = :project_id AND m.material_id = :material_id
             LIMIT 1'
        );
        $statement->execute(['project_id' => $projectId, 'material_id' => $materialId]);
        $material = $statement->fetch();
        if ($material === false) {
            throw new HttpException('BOM 材料不存在或不属于当前项目。', 404);
        }

        return $material;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insertProgress(
        int $projectId,
        int $planId,
        int $itemId,
        string $type,
        string $note,
        int $userId,
        string $nowUtc,
        ?int $locationId = null
    ): void {
        $this->db->prepare(
            'INSERT INTO project_closeout_progress_logs
             (project_id, plan_id, item_id, location_id, progress_type, progress_note, recorded_by, recorded_at)
             VALUES (:project_id, :plan_id, :item_id, :location_id, :type, :note, :user_id, :now)'
        )->execute([
            'project_id' => $projectId,
            'plan_id' => $planId,
            'item_id' => $itemId,
            'location_id' => $locationId,
            'type' => $type,
            'note' => $note,
            'user_id' => $userId,
            'now' => $nowUtc,
        ]);
    }

    private function nextNumber(int $projectId, string $documentType, string $prefix): string
    {
        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insert = $this->db->prepare(
            $driver === 'mysql'
                ? 'INSERT IGNORE INTO document_number_sequences (project_id, document_type, last_number)
                   VALUES (:project_id, :document_type, 0)'
                : 'INSERT OR IGNORE INTO document_number_sequences (project_id, document_type, last_number)
                   VALUES (:project_id, :document_type, 0)'
        );
        $insert->execute(['project_id' => $projectId, 'document_type' => $documentType]);

        $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $select = $this->db->prepare(
            'SELECT last_number FROM document_number_sequences
             WHERE project_id = :project_id AND document_type = :document_type' . $lock
        );
        $select->execute(['project_id' => $projectId, 'document_type' => $documentType]);
        $next = ((int) $select->fetchColumn()) + 1;
        $update = $this->db->prepare(
            'UPDATE document_number_sequences SET last_number = :last_number
             WHERE project_id = :project_id AND document_type = :document_type'
        );
        $update->execute([
            'last_number' => $next,
            'project_id' => $projectId,
            'document_type' => $documentType,
        ]);

        return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function decorateItems(array $items): array
    {
        $today = $this->today();
        $progress = $items === [] ? [] : $this->locationAggregates($items);
        foreach ($items as &$item) {
            $item['status_label'] = CloseoutLabels::itemStatus((string) $item['status']);
            $item['responsible_label'] = $this->responsibleLabel($item);
            $item['risk_label'] = CloseoutLabels::risk((string) $item['risk_level']);
            $item['is_overdue'] = in_array((string) $item['status'], CloseoutLabels::OPEN_STATUSES, true)
                && !empty($item['planned_end_date'])
                && (string) $item['planned_end_date'] < $today;
            $row = $progress[(int) $item['item_id']] ?? null;
            $item['has_locations'] = $row !== null && (int) $row['location_total'] > 0;
            $item['location_total'] = (int) ($row['location_total'] ?? 0);
            $item['location_closed'] = (int) ($row['location_closed'] ?? 0);
            $item['location_open'] = (int) ($row['location_open'] ?? 0);
            $item['location_overdue'] = (int) ($row['location_overdue'] ?? 0);
            $qtyTotal = (float) ($row['qty_total'] ?? 0);
            $item['qty_total'] = round($qtyTotal, 4);
            $item['qty_closed'] = round((float) ($row['qty_closed'] ?? 0), 4);
            $item['progress_verified'] = $qtyTotal > 0
                ? round((float) $row['qty_closed'] / $qtyTotal * 100, 1)
                : null;
            $item['progress_submitted'] = $qtyTotal > 0
                ? round((float) $row['qty_submitted'] / $qtyTotal * 100, 1)
                : null;
        }
        unset($item);

        // Locations travel with the row so the workspace can expand it in place.
        $projectId = (int) ($items[0]['project_id'] ?? 0);
        if ($projectId > 0) {
            $itemIds = [];
            foreach ($items as $item) {
                if (!empty($item['has_locations'])) {
                    $itemIds[] = (int) $item['item_id'];
                }
            }
            $grouped = $this->listLocationsForItems($projectId, $itemIds);
            foreach ($items as &$item) {
                $item['locations'] = $grouped[(int) $item['item_id']] ?? [];
            }
            unset($item);
        }

        return $items;
    }

    private function today(): string
    {
        return Support::nowLocal('Y-m-d');
    }

    private function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requiredText(array $input, string $key, string $label, int $maxLength): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            throw new HttpException('请填写' . $label . '。', 422);
        }
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException($label . '不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optionalText(array $input, string $key, int $maxLength): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException('文本不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optionalId(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('关联编号无效。', 422);
        }

        return (int) $id;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new HttpException($label . '格式无效，请使用 YYYY-MM-DD。', 422);
        }

        return $value;
    }

    private function assertDateOrder(?string $startDate, ?string $endDate): void
    {
        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw new HttpException('计划开始日期不能晚于计划结束日期。', 422);
        }
    }

    /**
     * @param list<int|string|mixed> $values
     * @return list<int>
     */
    private function normalizeIdList(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_string($value)) {
                continue;
            }
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                continue;
            }
            $ids[(int) $id] = true;
        }

        return array_keys($ids);
    }

    // ------------------------------------------------- locations (V2.0)

    /**
     * Transitional rule: a project that never used material estimates keeps the
     * V1 behaviour (unlocated item, status handled by hand). Once a project has
     * estimate rows, every new item must carry at least one space.
     */
    private function projectHasEstimates(int $projectId): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             WHERE b.project_id = :project_id'
        );
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Every parent material that already carries estimate rows, with its space
     * count and measured quantity. Estimate rows are the source of closeout
     * scope: no BOM line takes part here.
     *
     * @return list<array{material_id: int, space_count: int, total_qty: float}>
     */
    private function estimateParentRows(int $projectId): array
    {
        $statement = $this->db->prepare(
            'SELECT e.material_id, COUNT(DISTINCT CONCAT_WS("-", e.building_id, e.floor_id, e.room_id)) AS space_count,
                    SUM(e.estimated_quantity) AS total_qty
             FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             WHERE b.project_id = :project_id
             GROUP BY e.material_id
             ORDER BY e.material_id'
        );
        $statement->execute(['project_id' => $projectId]);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'material_id' => (int) $row['material_id'],
                'space_count' => (int) $row['space_count'],
                'total_qty' => (float) $row['total_qty'],
            ];
        }

        return $rows;
    }

    /**
     * One-shot provisioning: every measured parent material becomes one item,
     * its measured spaces become locations. There is no manual picking step,
     * and calling it again only fills in what is still missing (the
     * (project_id, material_id) uniqueness is checked before inserting, so it
     * is also safe under concurrent page loads).
     *
     * A material that appears in the estimate table but is not a parent in the
     * BOM graph is skipped and reported: estimates must be entered on the
     * parent material, and silently creating an item for a child would break
     * the "one parent, one item" rule.
     *
     * @param array<string, mixed> $defaults
     * @return array{created: list<array{item_id: int, item_no: string, material_id: int}>, existing: int, ignored: list<string>, plan_active: bool}
     */
    public function autoProvision(int $projectId, int $planId, int $userId, array $defaults = []): array
    {
        $plan = $this->requirePlan($projectId, $planId);
        if ($plan['status'] !== 'active') {
            return ['created' => [], 'existing' => 0, 'ignored' => [], 'plan_active' => false];
        }

        $rows = $this->estimateParentRows($projectId);
        if ($rows === []) {
            return ['created' => [], 'existing' => 0, 'ignored' => [], 'plan_active' => true];
        }

        $graph = $this->materialGraph($projectId);
        $names = $this->materialNames($projectId);
        $hasCompositeParents = $graph['children'] !== [];
        $activeByMaterial = [];
        foreach ($this->activeItems($projectId) as $item) {
            $activeByMaterial[(int) $item['material_id']] = true;
        }

        $todo = [];
        $existing = 0;
        $ignored = [];
        foreach ($rows as $row) {
            $materialId = $row['material_id'];
            // Projects without any composition edge have no parent concept at
            // all; there every measured material is top level by definition.
            if ($hasCompositeParents && ($graph['children'][$materialId] ?? []) === []) {
                $ignored[] = (string) ($names[$materialId] ?? ('#' . $materialId));
                continue;
            }
            if (isset($activeByMaterial[$materialId])) {
                $existing++;
                continue;
            }
            $todo[] = $materialId;
        }

        $created = $todo === []
            ? []
            : $this->createItems($projectId, $planId, $todo, $defaults, $userId);

        return ['created' => $created, 'existing' => $existing, 'ignored' => $ignored, 'plan_active' => true];
    }

    /**
     * V1 items were created before the space table existed, so they carry no
     * locations and show "空间 0/0" with no progress bar. This fills the gap
     * from the very same source the new flow uses: the parent material's
     * estimate rows. It only adds structure, never quantity, and never touches
     * item status beyond the normal bucket recalc.
     *
     * Nothing is invented: an item whose material has no estimate row is
     * reported as missing estimates, and a material that has a parent in the
     * composition graph is reported as not-parent (the legacy item was built on
     * a child material and must be re-entered on its parent).
     *
     * @return array{
     *     filled: list<array{item_id: int, item_no: string, spaces: int, status: string}>,
     *     existing: int,
     *     missing_estimates: list<string>,
     *     not_parent: list<string>,
     *     plan_inactive: list<string>,
     *     closed_skipped: list<string>,
     *     locations_created: int
     * }
     */
    public function backfillLocations(int $projectId, int $userId): array
    {
        $graph = $this->materialGraph($projectId);
        $names = $this->materialNames($projectId);
        $hasCompositeParents = $graph['children'] !== [];

        $statement = $this->db->prepare(
            'SELECT i.item_id, i.item_no, i.material_id, i.plan_id, i.status,
                    i.planned_start_date, i.planned_end_date,
                    i.responsible_supplier_id, i.responsible_user_id, i.responsible_crew_id,
                    p.status AS plan_status,
                    (SELECT COUNT(*) FROM project_closeout_locations l
                      WHERE l.project_id = i.project_id AND l.item_id = i.item_id
                        AND l.status <> \'cancelled\') AS loc_count
             FROM project_closeout_items i
             JOIN project_closeout_plans p ON p.plan_id = i.plan_id
             WHERE i.project_id = :project_id AND i.status <> \'cancelled\'
             ORDER BY i.item_id'
        );
        $statement->execute(['project_id' => $projectId]);

        $filled = [];
        $missing = [];
        $notParent = [];
        $planInactive = [];
        $closedSkipped = [];
        $existing = 0;
        $created = 0;
        $now = $this->nowUtc();

        foreach ($statement->fetchAll() as $item) {
            if ((int) $item['loc_count'] > 0) {
                $existing++;
                continue;
            }
            $itemNo = (string) $item['item_no'];
            // A closed item is already done: adding spaces would either reopen
            // it through the bucket recalc or invent acceptance evidence. Leave
            // it alone and report it instead.
            if ((string) $item['status'] === 'closed') {
                $closedSkipped[] = $itemNo;
                continue;
            }
            if ((string) $item['plan_status'] !== 'active') {
                $planInactive[] = $itemNo;
                continue;
            }

            $materialId = (int) $item['material_id'];
            if ($hasCompositeParents && ($graph['parents'][$materialId] ?? []) !== []) {
                $notParent[] = $itemNo . ' ' . ($names[$materialId] ?? ('#' . $materialId));
                continue;
            }

            $rows = $this->estimateRowsForMaterial($projectId, $materialId);
            if ($rows === []) {
                $missing[] = $itemNo . ' ' . ($names[$materialId] ?? ('#' . $materialId));
                continue;
            }

            $defaults = [
                'planned_start_date' => $item['planned_start_date'],
                'planned_end_date' => $item['planned_end_date'],
                'responsible_supplier_id' => $item['responsible_supplier_id'],
                'responsible_user_id' => $item['responsible_user_id'],
                'responsible_crew_id' => $item['responsible_crew_id'] ?? null,
            ];
            // Mirror the legacy item status instead of resetting it: "ongoing"
            // means work started, so the spaces start as ongoing too. Nothing
            // is claimed as submitted — a submission needs a real timestamp
            // that only the submit action can write.
            $itemStatus = (string) $item['status'];
            $spaceStatus = in_array($itemStatus, ['ongoing', 'pending_accept'], true) ? 'ongoing' : 'pending';
            $itemId = (int) $item['item_id'];
            $spaces = 0;
            foreach ($rows as $row) {
                $this->insertLocation($projectId, (int) $item['plan_id'], $itemId, $row, $defaults, $userId, $now, $spaceStatus);
                $spaces++;
            }

            $created += $spaces;
            $filled[] = ['item_id' => $itemId, 'item_no' => $itemNo, 'spaces' => $spaces, 'status' => $itemStatus];
        }

        return [
            'filled' => $filled,
            'existing' => $existing,
            'missing_estimates' => $missing,
            'not_parent' => $notParent,
            'plan_inactive' => $planInactive,
            'closed_skipped' => $closedSkipped,
            'locations_created' => $created,
        ];
    }

    /**
     * How many legacy items the backfill could actually fill right now: no
     * space rows, plan active, and the parent material does have estimate rows
     * in this project. Unfillable items are excluded so the workspace button
     * disappears once the real work is done.
     */
    public function backfillCandidateCount(int $projectId): int
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*)
             FROM project_closeout_items i
             JOIN project_closeout_plans p ON p.plan_id = i.plan_id
             WHERE i.project_id = :project_id
               AND i.status NOT IN ('cancelled', 'closed')
               AND p.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM project_closeout_locations l
                    WHERE l.project_id = i.project_id AND l.item_id = i.item_id
                      AND l.status <> 'cancelled')
               AND EXISTS (SELECT 1 FROM Initial_Cost_Estimate_Detail e
                    JOIN Buildings b ON b.building_id = e.building_id
                    WHERE e.material_id = i.material_id AND b.project_id = i.project_id)"
        );
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Spaces of one parent material taken from the material estimate detail.
     * The estimate table has no project_id column, so project isolation runs
     * through Buildings.project_id — never trust the caller's material id alone.
     *
     * @return list<array{building_id: int, floor_id: ?int, room_id: ?int, qty: float, estimate_detail_id: ?int}>
     */
    private function estimateRowsForMaterial(int $projectId, int $materialId): array
    {
        $statement = $this->db->prepare(
            'SELECT e.estimate_detail_id, e.building_id, e.floor_id, e.room_id, e.estimated_quantity
             FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             WHERE e.material_id = :material_id AND b.project_id = :project_id'
        );
        $statement->execute(['material_id' => $materialId, 'project_id' => $projectId]);

        $spaces = [];
        foreach ($statement->fetchAll() as $row) {
            $key = $this->spaceKey($row);
            if (!isset($spaces[$key])) {
                $spaces[$key] = [
                    'building_id' => (int) $row['building_id'],
                    'floor_id' => $row['floor_id'] !== null ? (int) $row['floor_id'] : null,
                    'room_id' => $row['room_id'] !== null ? (int) $row['room_id'] : null,
                    'qty' => 0.0,
                    'estimate_detail_id' => (int) $row['estimate_detail_id'],
                    'rows' => 0,
                ];
            }
            $spaces[$key]['qty'] += (float) $row['estimated_quantity'];
            $spaces[$key]['rows']++;
        }
        foreach ($spaces as &$space) {
            // Several estimate rows in one space cannot be traced back to a
            // single source row, so the trace pointer degrades to null.
            if ($space['rows'] > 1) {
                $space['estimate_detail_id'] = null;
            }
            unset($space['rows']);
        }
        unset($space);

        return array_values($spaces);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function spaceKey(array $row): string
    {
        return (int) $row['building_id']
            . '-' . (int) ($row['floor_id'] ?? 0)
            . '-' . (int) ($row['room_id'] ?? 0);
    }

    /**
     * Build the space sub-items of a freshly created item.
     *
     * @param array<string, mixed> $defaults
     * @param list<string>|null $spaceKeys null = every measured space
     * @return int number of locations created
     */
    private function createLocationsForItem(
        int $projectId,
        int $planId,
        int $itemId,
        int $materialId,
        array $defaults,
        int $userId,
        ?array $spaceKeys = null
    ): int {
        $rows = $this->estimateRowsForMaterial($projectId, $materialId);
        if ($spaceKeys !== null) {
            $allowed = [];
            foreach ($spaceKeys as $key) {
                $allowed[(string) $key] = true;
            }
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => isset($allowed[$this->spaceKey($row)])
            ));
        }

        $now = $this->nowUtc();
        $created = 0;
        foreach ($rows as $row) {
            $this->insertLocation($projectId, $planId, $itemId, $row, $defaults, $userId, $now);
            $created++;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $defaults
     */
    private function insertLocation(
        int $projectId,
        int $planId,
        int $itemId,
        array $row,
        array $defaults,
        int $userId,
        string $now,
        string $status = 'pending'
    ): void {
        $this->db->prepare(
            'INSERT INTO project_closeout_locations
             (project_id, plan_id, item_id, estimate_detail_id, building_id, floor_id, room_id,
              baseline_qty, baseline_at, status, planned_start_date, planned_end_date,
              responsible_supplier_id, responsible_user_id, responsible_crew_id, created_by)
             VALUES
             (:project_id, :plan_id, :item_id, :estimate_detail_id, :building_id, :floor_id, :room_id,
              :baseline_qty, :baseline_at, :status, :start_date, :end_date,
              :supplier_id, :responsible_user_id, :responsible_crew_id, :created_by)'
        )->execute([
            'project_id' => $projectId,
            'plan_id' => $planId,
            'item_id' => $itemId,
            'estimate_detail_id' => $row['estimate_detail_id'],
            'building_id' => $row['building_id'],
            'floor_id' => $row['floor_id'],
            'room_id' => $row['room_id'],
            'baseline_qty' => round((float) $row['qty'], 4),
            'baseline_at' => $now,
            'status' => $status,
            'start_date' => $defaults['planned_start_date'] ?? null,
            'end_date' => $defaults['planned_end_date'] ?? null,
            'supplier_id' => $defaults['responsible_supplier_id'] ?? null,
            'responsible_user_id' => $defaults['responsible_user_id'] ?? null,
            'responsible_crew_id' => $defaults['responsible_crew_id'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Append spaces to an existing item.
     *
     * @param list<string> $spaceKeys building-floor-room keys
     * @return array{created: int, skipped: int}
     */
    public function addLocations(int $projectId, int $itemId, array $spaceKeys, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if (in_array((string) $item['status'], ['closed', 'cancelled'], true)) {
            throw new HttpException('已消项或已取消的事项不能再追加空间。', 422);
        }
        if ((string) $item['plan_status'] !== 'active') {
            throw new HttpException('只有进行中的消项计划可以追加空间，请先启动计划。', 422);
        }
        if ($spaceKeys === []) {
            throw new HttpException('请至少选择一个空间。', 422);
        }

        $rows = $this->estimateRowsForMaterial($projectId, (int) $item['material_id']);
        $existing = $this->listLocations($projectId, $itemId);
        $existingKeys = [];
        foreach ($existing as $location) {
            if ((string) $location['status'] === 'cancelled') {
                continue;
            }
            $existingKeys[$this->spaceKey($location)] = true;
        }

        $defaults = [
            'planned_start_date' => $item['planned_start_date'],
            'planned_end_date' => $item['planned_end_date'],
            'responsible_supplier_id' => $item['responsible_supplier_id'],
            'responsible_user_id' => $item['responsible_user_id'],
        ];
        $now = $this->nowUtc();
        $created = 0;
        $skipped = 0;
        foreach ($spaceKeys as $key) {
            $row = null;
            foreach ($rows as $candidate) {
                if ($this->spaceKey($candidate) === (string) $key) {
                    $row = $candidate;
                    break;
                }
            }
            if ($row === null) {
                throw new HttpException('空间「' . (string) $key . '」在该父材料的测算明细中不存在。', 422);
            }
            if (isset($existingKeys[(string) $key])) {
                $skipped++;
                continue;
            }
            $this->insertLocation($projectId, (int) $item['plan_id'], $itemId, $row, $defaults, $userId, $now);
            $created++;
        }
        $this->recalcItemStatus($projectId, $itemId);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLocations(int $projectId, int $itemId): array
    {
        $statement = $this->db->prepare(
            'SELECT l.location_id, l.project_id, l.plan_id, l.item_id, l.estimate_detail_id,
                    l.building_id, l.floor_id, l.room_id, l.baseline_qty, l.baseline_at, l.status,
                    l.submitted_at, l.accepted_at, l.accepted_by,
                    l.planned_start_date, l.planned_end_date,
                    l.responsible_supplier_id, l.responsible_user_id, l.responsible_crew_id,
                    l.cancel_reason, l.cancelled_by, l.cancelled_at, l.created_at, l.updated_at,
                    b.building_name, f.floor_name, f.floor_number, r.room_number,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS responsible_user_name,
                    lc.crew_name AS responsible_crew_name,
                    lc.leader_name AS responsible_crew_leader,
                    COALESCE(NULLIF(au.display_name, \'\'), au.username) AS accepted_by_name
             FROM project_closeout_locations l
             LEFT JOIN Buildings b ON b.building_id = l.building_id
             LEFT JOIN Building_Floors f ON f.floor_id = l.floor_id AND f.building_id = l.building_id
             LEFT JOIN Rooms r ON r.room_id = l.room_id AND r.building_id = l.building_id AND r.floor_id = l.floor_id
             LEFT JOIN Users u ON u.user_id = l.responsible_user_id
             LEFT JOIN Labor_Crews lc ON lc.crew_id = l.responsible_crew_id
             LEFT JOIN Users au ON au.user_id = l.accepted_by
             WHERE l.project_id = :project_id AND l.item_id = :item_id
             ORDER BY b.building_name, f.floor_number, r.room_number, l.location_id'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);
        $rows = $statement->fetchAll();

        $today = $this->today();
        foreach ($rows as &$row) {
            $row['status_label'] = CloseoutLabels::itemStatus((string) $row['status']);
            $row['responsible_label'] = $this->responsibleLabel($row);
            $row['is_overdue'] = in_array((string) $row['status'], CloseoutLabels::OPEN_STATUSES, true)
                && !empty($row['planned_end_date'])
                && (string) $row['planned_end_date'] < $today;
            $row['space_label'] = $this->spaceLabel($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Locations of several items in one query, keyed by item_id. Used by the
     * flat workspace list so expanding a row never issues N queries.
     *
     * @param list<int> $itemIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listLocationsForItems(int $projectId, array $itemIds): array
    {
        $itemIds = $this->normalizeIdList($itemIds);
        if ($itemIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['project_id' => $projectId];
        foreach (array_values($itemIds) as $index => $id) {
            $placeholders[] = ':item_' . $index;
            $params['item_' . $index] = $id;
        }

        $statement = $this->db->prepare(
            'SELECT l.location_id, l.project_id, l.plan_id, l.item_id, l.estimate_detail_id,
                    l.building_id, l.floor_id, l.room_id, l.baseline_qty, l.baseline_at, l.status,
                    l.submitted_at, l.accepted_at, l.accepted_by,
                    l.planned_start_date, l.planned_end_date,
                    l.responsible_supplier_id, l.responsible_user_id, l.responsible_crew_id,
                    l.cancel_reason, l.cancelled_by, l.cancelled_at, l.created_at, l.updated_at,
                    b.building_name, f.floor_name, f.floor_number, r.room_number,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS responsible_user_name,
                    lc.crew_name AS responsible_crew_name,
                    lc.leader_name AS responsible_crew_leader,
                    COALESCE(NULLIF(au.display_name, \'\'), au.username) AS accepted_by_name
             FROM project_closeout_locations l
             LEFT JOIN Buildings b ON b.building_id = l.building_id
             LEFT JOIN Building_Floors f ON f.floor_id = l.floor_id AND f.building_id = l.building_id
             LEFT JOIN Rooms r ON r.room_id = l.room_id AND r.building_id = l.building_id AND r.floor_id = l.floor_id
             LEFT JOIN Users u ON u.user_id = l.responsible_user_id
             LEFT JOIN Labor_Crews lc ON lc.crew_id = l.responsible_crew_id
             LEFT JOIN Users au ON au.user_id = l.accepted_by
             WHERE l.project_id = :project_id AND l.item_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY l.item_id, b.building_name, f.floor_number, r.room_number, l.location_id'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, $key === 'project_id' || strpos($key, 'item_') === 0 ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        $today = $this->today();
        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $row['status_label'] = CloseoutLabels::itemStatus((string) $row['status']);
            $row['responsible_label'] = $this->responsibleLabel($row);
            $row['is_overdue'] = in_array((string) $row['status'], CloseoutLabels::OPEN_STATUSES, true)
                && !empty($row['planned_end_date'])
                && (string) $row['planned_end_date'] < $today;
            $row['space_label'] = $this->spaceLabel($row);
            $grouped[(int) $row['item_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function spaceLabel(array $row): string
    {
        $parts = [(string) ($row['building_name'] ?? ('#' . (int) $row['building_id']))];
        if (!empty($row['floor_id'])) {
            $parts[] = (string) (($row['floor_name'] ?? '') !== '' ? $row['floor_name'] : ($row['floor_number'] ?? ('#' . (int) $row['floor_id'])));
        }
        if (!empty($row['room_id'])) {
            $parts[] = (string) (($row['room_number'] ?? '') !== '' ? $row['room_number'] : ('#' . (int) $row['room_id']));
        }

        return implode(' / ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function requireLocation(int $projectId, int $locationId): array
    {
        foreach ($this->listLocationsForItem($projectId, $locationId) as $row) {
            return $row;
        }

        throw new HttpException('消项空间不存在或不属于当前项目。', 404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLocationsForItem(int $projectId, int $locationId): array
    {
        $statement = $this->db->prepare(
            'SELECT item_id FROM project_closeout_locations
             WHERE project_id = :project_id AND location_id = :location_id'
        );
        $statement->execute(['project_id' => $projectId, 'location_id' => $locationId]);
        $itemId = $statement->fetchColumn();
        if ($itemId === false) {
            return [];
        }

        $rows = $this->listLocations($projectId, (int) $itemId);
        foreach ($rows as $row) {
            if ((int) $row['location_id'] === $locationId) {
                return [$row];
            }
        }

        return [];
    }

    private function locationCount(int $projectId, int $itemId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM project_closeout_locations WHERE project_id = :project_id AND item_id = :item_id'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        return (int) $statement->fetchColumn();
    }

    public function startLocation(int $projectId, int $locationId, int $userId): array
    {
        $location = $this->requireLocation($projectId, $locationId);
        if ((string) $location['status'] !== 'pending') {
            throw new HttpException('只有待处理的空间可以开始执行。', 422);
        }
        $this->db->prepare(
            "UPDATE project_closeout_locations SET status = 'ongoing'
             WHERE project_id = :project_id AND location_id = :location_id"
        )->execute(['project_id' => $projectId, 'location_id' => $locationId]);
        $this->recalcItemStatus($projectId, (int) $location['item_id']);

        unset($userId);

        return $location;
    }

    public function submitLocation(int $projectId, int $locationId, string $note, int $userId): array
    {
        $location = $this->requireLocation($projectId, $locationId);
        if (!in_array((string) $location['status'], ['pending', 'ongoing'], true)) {
            throw new HttpException('只有待处理或进行中的空间可以提交验收。', 422);
        }
        $note = trim($note);
        if (mb_strlen($note) > 5000) {
            throw new HttpException('提交说明不能超过 5000 个字符。', 422);
        }
        if ($note === '') {
            $note = '提交验收';
        }
        $now = $this->nowUtc();

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE project_closeout_locations
                 SET status = 'pending_accept', submitted_at = :now
                 WHERE project_id = :project_id AND location_id = :location_id"
            )->execute(['now' => $now, 'project_id' => $projectId, 'location_id' => $locationId]);
            $this->insertProgress(
                $projectId,
                (int) $location['plan_id'],
                (int) $location['item_id'],
                'submit',
                $note,
                $userId,
                $now,
                $locationId
            );
            $this->recalcItemStatus($projectId, (int) $location['item_id']);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $location;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function acceptLocation(int $projectId, int $locationId, array $input, int $userId): array
    {
        $location = $this->requireLocation($projectId, $locationId);
        if ((string) $location['status'] !== 'pending_accept') {
            throw new HttpException('只有待验收的空间可以执行验收。', 422);
        }
        $result = (string) ($input['result'] ?? '');
        if (!in_array($result, ['passed', 'rejected'], true)) {
            throw new HttpException('请选择验收结论。', 422);
        }
        $remark = $this->optionalText($input, 'remark', 5000) ?? '';
        if ($result === 'rejected' && $remark === '') {
            throw new HttpException('验收不通过时必须填写验收意见。', 422);
        }
        $evidence = $this->optionalText($input, 'evidence_reference', 1000);
        $today = $this->today();
        $submittedDate = !empty($location['submitted_at'])
            ? Support::formatDateTime((string) $location['submitted_at'], 'Y-m-d')
            : $today;
        $actualDate = null;
        if ($result === 'passed') {
            $actualDate = $this->optionalDate($input['actual_complete_date'] ?? null, '实际完成日期') ?? $today;
            if ($actualDate < $submittedDate) {
                throw new HttpException('实际完成日期不得早于提交验收日期（' . $submittedDate . '）。', 422);
            }
        }
        $now = $this->nowUtc();

        $this->db->beginTransaction();
        try {
            $this->applyLocationAcceptance(
                $projectId,
                (int) $location['plan_id'],
                (int) $location['item_id'],
                $locationId,
                $result,
                $remark,
                $evidence,
                $actualDate,
                $now,
                $userId
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $location;
    }

    private function applyLocationAcceptance(
        int $projectId,
        int $planId,
        int $itemId,
        int $locationId,
        string $result,
        string $remark,
        ?string $evidence,
        ?string $actualDate,
        string $now,
        int $userId
    ): void {
        if ($result === 'passed') {
            $this->db->prepare(
                "UPDATE project_closeout_locations
                 SET status = 'closed', accepted_at = :now, accepted_by = :user_id
                 WHERE project_id = :project_id AND location_id = :location_id"
            )->execute([
                'now' => $now,
                'user_id' => $userId,
                'project_id' => $projectId,
                'location_id' => $locationId,
            ]);
        } else {
            $this->db->prepare(
                "UPDATE project_closeout_locations SET status = 'ongoing'
                 WHERE project_id = :project_id AND location_id = :location_id"
            )->execute(['project_id' => $projectId, 'location_id' => $locationId]);
        }

        $this->db->prepare(
            'INSERT INTO project_closeout_acceptances
             (project_id, plan_id, item_id, location_id, result, remark, evidence_reference, accepted_by, accepted_at)
             VALUES (:project_id, :plan_id, :item_id, :location_id, :result, :remark, :evidence, :accepted_by, :accepted_at)'
        )->execute([
            'project_id' => $projectId,
            'plan_id' => $planId,
            'item_id' => $itemId,
            'location_id' => $locationId,
            'result' => $result,
            'remark' => $remark !== '' ? $remark : null,
            'evidence' => $evidence,
            'accepted_by' => $userId,
            'accepted_at' => $now,
        ]);

        $this->recalcItemStatus($projectId, $itemId);
        unset($actualDate);
    }

    /**
     * Item-level acceptance for a space-tracked item: accept every space
     * waiting for acceptance with the same conclusion. This keeps the legacy
     * row action ("整条验收") usable without bypassing per-space records.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function acceptLocatedItem(int $projectId, int $itemId, array $input, int $userId): array
    {
        $result = (string) ($input['result'] ?? '');
        if (!in_array($result, ['passed', 'rejected'], true)) {
            throw new HttpException('请选择验收结论。', 422);
        }
        $remark = $this->optionalText($input, 'remark', 5000) ?? '';
        if ($result === 'rejected' && $remark === '') {
            throw new HttpException('验收不通过时必须填写验收意见。', 422);
        }
        $evidence = $this->optionalText($input, 'evidence_reference', 1000);
        $pending = array_values(array_filter(
            $this->listLocations($projectId, $itemId),
            static fn (array $row): bool => (string) $row['status'] === 'pending_accept'
        ));
        if ($pending === []) {
            throw new HttpException('该事项没有待验收的空间。', 422);
        }
        $today = $this->today();
        $now = $this->nowUtc();

        $this->db->beginTransaction();
        try {
            foreach ($pending as $location) {
                $submittedDate = !empty($location['submitted_at'])
                    ? Support::formatDateTime((string) $location['submitted_at'], 'Y-m-d')
                    : $today;
                $actualDate = null;
                if ($result === 'passed') {
                    $actualDate = $this->optionalDate($input['actual_complete_date'] ?? null, '实际完成日期') ?? $today;
                    if ($actualDate < $submittedDate) {
                        throw new HttpException(
                            '实际完成日期不得早于提交验收日期（空间 ' . (string) $location['space_label']
                            . '：' . $submittedDate . '）。',
                            422
                        );
                    }
                }
                $this->applyLocationAcceptance(
                    $projectId,
                    (int) $location['plan_id'],
                    $itemId,
                    (int) $location['location_id'],
                    $result,
                    $remark,
                    $evidence,
                    $actualDate,
                    $now,
                    $userId
                );
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $this->requireItem($projectId, $itemId);
    }

    public function cancelLocation(int $projectId, int $locationId, string $reason, int $userId): array
    {
        $location = $this->requireLocation($projectId, $locationId);
        if ((string) $location['status'] === 'closed') {
            throw new HttpException('已消项的空间不能取消，如需返工请重新打开事项。', 422);
        }
        if ((string) $location['status'] === 'cancelled') {
            throw new HttpException('该空间已经是取消状态。', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException('取消空间必须填写取消原因。', 422);
        }
        if (mb_strlen($reason) > 5000) {
            throw new HttpException('取消原因不能超过 5000 个字符。', 422);
        }
        $this->db->prepare(
            "UPDATE project_closeout_locations
             SET status = 'cancelled', cancel_reason = :reason, cancelled_by = :user_id, cancelled_at = :now
             WHERE project_id = :project_id AND location_id = :location_id"
        )->execute([
            'reason' => $reason,
            'user_id' => $userId,
            'now' => $this->nowUtc(),
            'project_id' => $projectId,
            'location_id' => $locationId,
        ]);
        $this->recalcItemStatus($projectId, (int) $location['item_id']);

        return $location;
    }

    /**
     * Re-freeze the baseline quantity from the current estimate rows of this
     * space. Explicit action only: estimate edits never move the denominator
     * on their own.
     */
    public function rebuildBaseline(int $projectId, int $locationId, string $reason, int $userId): array
    {
        $location = $this->requireLocation($projectId, $locationId);
        if ((string) $location['status'] === 'closed') {
            throw new HttpException('已验收的空间不能重定基线，请先退回完成状态。', 422);
        }
        if ((string) $location['status'] === 'cancelled') {
            throw new HttpException('已取消的空间不能重定基线。', 422);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException('重定基线必须填写变更原因。', 422);
        }

        $item = $this->requireItem($projectId, (int) $location['item_id']);
        $rows = $this->estimateRowsForMaterial($projectId, (int) $item['material_id']);
        $qty = null;
        foreach ($rows as $row) {
            if ($this->spaceKey($row) === $this->spaceKey($location)) {
                $qty = (float) $row['qty'];
                break;
            }
        }
        if ($qty === null) {
            throw new HttpException('该空间在当前测算明细中已不存在，无法重定基线。', 422);
        }

        $this->db->prepare(
            'UPDATE project_closeout_locations
             SET baseline_qty = :qty, baseline_at = :now
             WHERE project_id = :project_id AND location_id = :location_id'
        )->execute([
            'qty' => round($qty, 4),
            'now' => $this->nowUtc(),
            'project_id' => $projectId,
            'location_id' => $locationId,
        ]);

        unset($userId, $reason);

        return $this->requireLocation($projectId, $locationId);
    }

    /**
     * Bucket rule: the item only reaches a stage when every live space does.
     * Local progress is expressed by the weighted bar, never by the status.
     */
    private function recalcItemStatus(int $projectId, int $itemId): void
    {
        $statement = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM project_closeout_locations
             WHERE project_id = :project_id AND item_id = :item_id GROUP BY status'
        );
        $statement->execute(['project_id' => $projectId, 'item_id' => $itemId]);
        $counts = [];
        $live = 0;
        foreach ($statement->fetchAll() as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['total'];
            if ($status !== 'cancelled') {
                $live += (int) $row['total'];
            }
        }
        if ($live === 0) {
            return;
        }

        $itemStatus = 'closed';
        if (($counts['pending'] ?? 0) > 0) {
            $itemStatus = 'pending';
        } elseif (($counts['ongoing'] ?? 0) > 0) {
            $itemStatus = 'ongoing';
        } elseif (($counts['pending_accept'] ?? 0) > 0) {
            $itemStatus = 'pending_accept';
        }

        $submitted = $this->db->prepare(
            'SELECT MAX(submitted_at) FROM project_closeout_locations
             WHERE project_id = :project_id AND item_id = :item_id AND status <> \'cancelled\''
        );
        $submitted->execute(['project_id' => $projectId, 'item_id' => $itemId]);
        $submittedAt = $submitted->fetchColumn();

        $actual = null;
        if ($itemStatus === 'closed') {
            $accepted = $this->db->prepare(
                'SELECT MAX(accepted_at) FROM project_closeout_locations
                 WHERE project_id = :project_id AND item_id = :item_id AND status = \'closed\''
            );
            $accepted->execute(['project_id' => $projectId, 'item_id' => $itemId]);
            $maxAccepted = $accepted->fetchColumn();
            if ($maxAccepted !== false && $maxAccepted !== null && $maxAccepted !== '') {
                $actual = substr((string) $maxAccepted, 0, 10);
            }
        }

        $current = $this->db->prepare(
            'SELECT status FROM project_closeout_items WHERE project_id = :project_id AND item_id = :item_id'
        );
        $current->execute(['project_id' => $projectId, 'item_id' => $itemId]);
        if ((string) $current->fetchColumn() === 'cancelled') {
            return;
        }

        $this->db->prepare(
            'UPDATE project_closeout_items
             SET status = :status, submitted_at = :submitted_at, actual_complete_date = :actual_date
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute([
            'status' => $itemStatus,
            'submitted_at' => $submittedAt !== false ? $submittedAt : null,
            'actual_date' => $actual,
            'project_id' => $projectId,
            'item_id' => $itemId,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function locationAggregates(array $items): array
    {
        $itemIds = [];
        $projectId = 0;
        foreach ($items as $item) {
            $itemIds[] = (int) $item['item_id'];
            $projectId = (int) $item['project_id'];
        }
        if ($itemIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $statement = $this->db->prepare(
            'SELECT item_id,
                    COUNT(*) AS location_total,
                    SUM(CASE WHEN status = \'closed\' THEN 1 ELSE 0 END) AS location_closed,
                    SUM(CASE WHEN status NOT IN (\'closed\', \'cancelled\') THEN 1 ELSE 0 END) AS location_open,
                    SUM(CASE WHEN status NOT IN (\'closed\', \'cancelled\')
                              AND planned_end_date IS NOT NULL
                              AND planned_end_date < ? THEN 1 ELSE 0 END) AS location_overdue,
                    SUM(CASE WHEN status <> \'cancelled\' THEN baseline_qty ELSE 0 END) AS qty_total,
                    SUM(CASE WHEN status = \'closed\' THEN baseline_qty ELSE 0 END) AS qty_closed,
                    SUM(CASE WHEN status IN (\'pending_accept\', \'closed\') THEN baseline_qty ELSE 0 END) AS qty_submitted
             FROM project_closeout_locations
             WHERE project_id = ? AND item_id IN (' . $placeholders . ')
             GROUP BY item_id'
        );
        $params = array_merge([$this->today(), $projectId], $itemIds);
        $statement->execute($params);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[(int) $row['item_id']] = $row;
        }

        return $rows;
    }
}

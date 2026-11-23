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
       i.responsible_supplier_id, i.responsible_user_id,
       i.planned_start_date, i.planned_end_date, i.submitted_at, i.actual_complete_date,
       i.risk_level, i.cancel_reason, i.cancelled_by, i.cancelled_at,
       i.created_by, i.created_at, i.updated_at,
       p.plan_no, p.plan_name, p.status AS plan_status,
       m.material_code, m.material_name, m.unit, m.specifications,
       c.category_name,
       s.supplier_name,
       u.username AS responsible_user_name,
       cu.username AS created_by_name,
       xu.username AS cancelled_by_name
FROM project_closeout_items i
JOIN project_closeout_plans p ON p.plan_id = i.plan_id AND p.project_id = i.project_id
LEFT JOIN Materials m ON m.material_id = i.material_id AND m.project_id = i.project_id
LEFT JOIN Material_Categories c ON c.category_id = m.category_id
LEFT JOIN Suppliers s ON s.supplier_id = i.responsible_supplier_id
LEFT JOIN Users u ON u.user_id = i.responsible_user_id
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
                    p.created_at, p.updated_at, u.username AS created_by_name
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

            return (int) $this->db->lastInsertId();
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

        $sql = self::ITEM_SELECT
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY (i.planned_end_date IS NULL), i.planned_end_date, i.item_id DESC';
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
     * @param list<int|string> $materialIds
     * @param array<string, mixed> $defaults
     * @return list<array{item_id: int, item_no: string}>
     */
    public function createItems(int $projectId, int $planId, array $materialIds, array $defaults, int $userId): array
    {
        $plan = $this->requirePlan($projectId, $planId);
        if ($plan['status'] !== 'active') {
            throw new HttpException('只有进行中的消项计划可以新增事项，请先启动计划。', 422);
        }

        $materialIds = $this->normalizeIdList($materialIds);
        if ($materialIds === []) {
            throw new HttpException('请至少选择一项 BOM 材料。', 422);
        }

        $options = $this->normalizeItemDefaults($defaults);
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
                      title, description, status, responsible_supplier_id, responsible_user_id,
                      planned_start_date, planned_end_date, risk_level, created_by)
                     VALUES
                     (:project_id, :plan_id, :item_no, :material_id, :coverage_mode, :coverage_root_id,
                      :title, :description, \'pending\', :supplier_id, :responsible_user_id,
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
                    'start_date' => $options['planned_start_date'],
                    'end_date' => $options['planned_end_date'],
                    'risk_level' => $options['risk_level'],
                    'created_by' => $userId,
                ]);
                $itemId = (int) $this->db->lastInsertId();
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
                throw new HttpException('该 BOM 材料已存在有效消项事项（并发冲突），请刷新后重新选择。', 422);
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
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
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
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
        if ($startDate === null && $endDate === null && $supplierId === null && $responsibleUserId === null) {
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
                         responsible_user_id = COALESCE(:responsible_user_id, responsible_user_id)
                     WHERE project_id = :project_id AND item_id = :item_id'
                )->execute([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'supplier_id' => $supplierId,
                    'responsible_user_id' => $responsibleUserId,
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
        $this->db->prepare(
            'UPDATE project_closeout_items SET status = \'ongoing\'
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        unset($userId);

        return $item;
    }

    public function submitItem(int $projectId, int $itemId, string $note, int $userId): array
    {
        $item = $this->requireItem($projectId, $itemId);
        if ($item['status'] !== 'ongoing') {
            throw new HttpException('只有进行中的事项可以提交验收。', 422);
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
        $this->db->prepare(
            'UPDATE project_closeout_items
             SET status = \'cancelled\', cancel_reason = :reason, cancelled_by = :user_id, cancelled_at = :now
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute([
            'reason' => $reason,
            'user_id' => $userId,
            'now' => $this->nowUtc(),
            'project_id' => $projectId,
            'item_id' => $itemId,
        ]);

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

        $this->db->prepare(
            'UPDATE project_closeout_items
             SET status = \'pending\', cancel_reason = NULL, cancelled_by = NULL, cancelled_at = NULL,
                 submitted_at = NULL, actual_complete_date = NULL
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute(['project_id' => $projectId, 'item_id' => $itemId]);

        unset($userId, $reason);

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

        $this->db->prepare(
            'UPDATE project_closeout_items
             SET status = \'ongoing\', actual_complete_date = NULL
             WHERE project_id = :project_id AND item_id = :item_id'
        )->execute(['project_id' => $projectId, 'item_id' => $itemId]);

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
            'SELECT g.progress_id, g.progress_type, g.progress_note, g.recorded_at, u.username AS recorded_by_name
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
                    u.username AS accepted_by_name
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

        return [
            'total' => $total,
            'counts' => $counts,
            'open' => $counts['pending'] + $counts['ongoing'] + $counts['pending_accept'],
            'overdue' => $overdue,
            'denominator' => $denominator,
            'completion_rate' => $denominator > 0 ? round($counts['closed'] / $denominator * 100, 1) : null,
            'today' => $this->today(),
        ];
    }

    /**
     * Chart-board aggregates for one plan (or every plan when $planId is null
     * or 0): status counts from statistics(), risk mix, near-term due load and
     * open items without an owner. Cancelled items stay in the status counts
     * for transparency but are excluded from risk / due figures because they no
     * longer carry work.
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
             WHERE " . $where . " AND status <> 'cancelled'
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
               AND responsible_user_id IS NULL"
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

    /** @return list<array{user_id: int, username: string}> */
    public function activeUsers(): array
    {
        $statement = $this->db->query(
            'SELECT user_id, username FROM Users WHERE is_active = 1 ORDER BY username'
        );

        return array_map(
            static fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'username' => (string) $row['username'],
            ],
            $statement->fetchAll()
        );
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
    private function normalizeItemDefaults(array $input): array
    {
        $startDate = $this->optionalDate($input['planned_start_date'] ?? null, '计划开始日期');
        $endDate = $this->optionalDate($input['planned_end_date'] ?? null, '计划结束日期');
        $this->assertDateOrder($startDate, $endDate);
        $supplierId = $this->optionalId($input, 'responsible_supplier_id');
        $responsibleUserId = $this->optionalId($input, 'responsible_user_id');
        $this->assertSupplier($supplierId);
        $this->assertActiveUser($responsibleUserId);
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
        string $nowUtc
    ): void {
        $this->db->prepare(
            'INSERT INTO project_closeout_progress_logs
             (project_id, plan_id, item_id, progress_type, progress_note, recorded_by, recorded_at)
             VALUES (:project_id, :plan_id, :item_id, :type, :note, :user_id, :now)'
        )->execute([
            'project_id' => $projectId,
            'plan_id' => $planId,
            'item_id' => $itemId,
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
        foreach ($items as &$item) {
            $item['status_label'] = CloseoutLabels::itemStatus((string) $item['status']);
            $item['risk_label'] = CloseoutLabels::risk((string) $item['risk_level']);
            $item['is_overdue'] = in_array((string) $item['status'], CloseoutLabels::OPEN_STATUSES, true)
                && !empty($item['planned_end_date'])
                && (string) $item['planned_end_date'] < $today;
        }
        unset($item);

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
}

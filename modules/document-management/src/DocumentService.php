<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use DateTimeImmutable;
use PDO;
use ProjectO\HttpException;
use ProjectO\Support;
use Throwable;

final class DocumentService
{
    public const ISSUE_STATUSES = ['open', 'in_review', 'resolved', 'closed'];
    public const RFI_STATUSES = ['draft', 'submitted', 'under_review', 'responded', 'closed'];

    private const ISSUE_TRANSITIONS = [
        'open' => ['in_review'],
        'in_review' => ['open', 'resolved'],
        'resolved' => ['in_review', 'closed'],
        'closed' => ['open'],
    ];

    private const RFI_TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review'],
        'under_review' => ['submitted', 'responded'],
        'responded' => ['under_review', 'closed'],
        'closed' => ['submitted'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function listIssues(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.issue_id, i.project_id, i.issue_number, i.description, i.severity, i.status,
                    i.location, i.reported_by, i.reported_at, i.resolution_result,
                    i.verification_status, i.verification_note, i.evidence_reference,
                    i.closed_at, i.closed_by, i.created_at, i.updated_at,
                    u.username AS reported_by_name
             FROM document_issues i
             LEFT JOIN Users u ON u.user_id = i.reported_by
             WHERE i.project_id = :project_id
             ORDER BY i.updated_at DESC, i.issue_id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        $issues = $stmt->fetchAll();
        if ($issues === []) {
            return [];
        }

        [$idList, $idParams] = $this->idList($issues, 'issue_id');
        $params = ['project_id' => $projectId] + $idParams;

        $followUps = $this->db->prepare(
            "SELECT follow_up_id, issue_id, title, is_completed, created_by, completed_by, completed_at
             FROM document_issue_follow_ups
             WHERE project_id = :project_id AND issue_id IN ({$idList})
             ORDER BY follow_up_id"
        );
        $followUps->execute($params);
        $followUpRows = $followUps->fetchAll();
        $followUpsByIssue = $this->groupBy($followUpRows, 'issue_id');
        if ($followUpRows !== []) {
            [$followUpIds, $followUpParams] = $this->idList($followUpRows, 'follow_up_id');
            $followUpHistory = $this->db->prepare(
                "SELECT history_id, follow_up_id, old_status, new_status, reason,
                        evidence_reference, changed_by, changed_at
                 FROM document_issue_follow_up_history
                 WHERE project_id = :project_id AND follow_up_id IN ({$followUpIds})
                 ORDER BY history_id DESC"
            );
            $followUpHistory->execute(['project_id' => $projectId] + $followUpParams);
            $historyByFollowUp = $this->groupBy($followUpHistory->fetchAll(), 'follow_up_id');
            foreach ($followUpRows as &$followUp) {
                $followUp['status_history'] = $historyByFollowUp[(int) $followUp['follow_up_id']] ?? [];
            }
            unset($followUp);
            $followUpsByIssue = $this->groupBy($followUpRows, 'issue_id');
        }

        $history = $this->db->prepare(
            "SELECT history_id, issue_id, old_status, new_status, reason,
                    evidence_reference, transition_data, changed_by, changed_at
             FROM document_issue_status_history
             WHERE project_id = :project_id AND issue_id IN ({$idList})
             ORDER BY history_id DESC"
        );
        $history->execute($params);
        $historyByIssue = $this->groupBy($history->fetchAll(), 'issue_id');

        foreach ($issues as &$issue) {
            $id = (int) $issue['issue_id'];
            $issue['follow_ups'] = $followUpsByIssue[$id] ?? [];
            $issue['status_history'] = $historyByIssue[$id] ?? [];
        }
        unset($issue);

        return $issues;
    }

    public function listRfis(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.rfi_id, r.project_id, r.primary_issue_id, r.rfi_number,
                    r.assigned_to, r.priority, r.due_date, r.description, r.status,
                    r.submitted_by, r.submitted_at, r.response_text, r.response_complete,
                    r.evidence_required, r.evidence_reference, r.closed_at, r.closed_by,
                    r.created_at, r.updated_at, u.username AS assignee_name,
                    i.issue_number AS primary_issue_number,
                    i.description AS primary_issue_description,
                    i.status AS primary_issue_status
             FROM document_rfis r
             JOIN Users u ON u.user_id = r.assigned_to
             JOIN document_issues i
               ON i.issue_id = r.primary_issue_id AND i.project_id = r.project_id
             WHERE r.project_id = :project_id
             ORDER BY r.updated_at DESC, r.rfi_id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        $rfis = $stmt->fetchAll();
        if ($rfis === []) {
            return [];
        }

        [$idList, $idParams] = $this->idList($rfis, 'rfi_id');
        $params = ['project_id' => $projectId] + $idParams;

        $related = $this->db->prepare(
            "SELECT ri.rfi_id, ri.issue_id, i.issue_number, i.description, i.status
             FROM document_rfi_related_issues ri
             JOIN document_issues i
               ON i.issue_id = ri.issue_id AND i.project_id = ri.project_id
             WHERE ri.project_id = :project_id AND ri.rfi_id IN ({$idList})
             ORDER BY ri.rfi_id, ri.issue_id"
        );
        $related->execute($params);
        $relatedByRfi = $this->groupBy($related->fetchAll(), 'rfi_id');

        $followUps = $this->db->prepare(
            "SELECT follow_up_id, rfi_id, title, is_completed, created_by, completed_by, completed_at
             FROM document_rfi_follow_ups
             WHERE project_id = :project_id AND rfi_id IN ({$idList})
             ORDER BY follow_up_id"
        );
        $followUps->execute($params);
        $followUpRows = $followUps->fetchAll();
        $followUpsByRfi = $this->groupBy($followUpRows, 'rfi_id');
        if ($followUpRows !== []) {
            [$followUpIds, $followUpParams] = $this->idList($followUpRows, 'follow_up_id');
            $followUpHistory = $this->db->prepare(
                "SELECT history_id, follow_up_id, old_status, new_status, reason,
                        evidence_reference, changed_by, changed_at
                 FROM document_rfi_follow_up_history
                 WHERE project_id = :project_id AND follow_up_id IN ({$followUpIds})
                 ORDER BY history_id DESC"
            );
            $followUpHistory->execute(['project_id' => $projectId] + $followUpParams);
            $historyByFollowUp = $this->groupBy($followUpHistory->fetchAll(), 'follow_up_id');
            foreach ($followUpRows as &$followUp) {
                $followUp['status_history'] = $historyByFollowUp[(int) $followUp['follow_up_id']] ?? [];
            }
            unset($followUp);
            $followUpsByRfi = $this->groupBy($followUpRows, 'rfi_id');
        }

        $history = $this->db->prepare(
            "SELECT history_id, rfi_id, old_status, new_status, reason,
                    evidence_reference, transition_data, changed_by, changed_at
             FROM document_rfi_status_history
             WHERE project_id = :project_id AND rfi_id IN ({$idList})
             ORDER BY history_id DESC"
        );
        $history->execute($params);
        $historyByRfi = $this->groupBy($history->fetchAll(), 'rfi_id');

        foreach ($rfis as &$rfi) {
            $id = (int) $rfi['rfi_id'];
            $rfi['related_issues'] = $relatedByRfi[$id] ?? [];
            $rfi['follow_ups'] = $followUpsByRfi[$id] ?? [];
            $rfi['status_history'] = $historyByRfi[$id] ?? [];
        }
        unset($rfi);

        return $rfis;
    }

    public function activeUsers(): array
    {
        return $this->db->query(
            'SELECT user_id, username FROM Users WHERE is_active = 1 ORDER BY username, user_id'
        )->fetchAll();
    }

    public function createIssue(int $projectId, int $userId, array $data): int
    {
        $description = $this->requiredText($data['description'] ?? null, '问题描述', 10000);
        $severity = $this->enumValue(
            $data['severity'] ?? 'medium',
            ['low', 'medium', 'high', 'critical'],
            '问题严重程度'
        );
        $location = $this->optionalText($data['location'] ?? null, '位置', 255);

        return $this->transaction(function () use (
            $projectId,
            $userId,
            $description,
            $severity,
            $location
        ): int {
            $issueNumber = $this->nextDocumentNumber($projectId, 'ISS');
            $stmt = $this->db->prepare(
                "INSERT INTO document_issues
                    (project_id, issue_number, description, severity, status, location, reported_by)
                 VALUES
                    (:project_id, :issue_number, :description, :severity, 'open', :location, :reported_by)"
            );
            $stmt->execute([
                'project_id' => $projectId,
                'issue_number' => $issueNumber,
                'description' => $description,
                'severity' => $severity,
                'location' => $location,
                'reported_by' => $userId,
            ]);
            $issueId = (int) $this->db->lastInsertId();

            $this->recordIssueStatus(
                $projectId,
                $issueId,
                null,
                'open',
                '创建问题',
                null,
                ['description' => $description],
                $userId
            );
            Support::audit($this->db, 'document.issue.create', 'Created issue ' . $issueId . '.');

            return $issueId;
        });
    }

    public function updateIssue(int $projectId, int $issueId, array $data): void
    {
        $description = $this->requiredText($data['description'] ?? null, '问题描述', 10000);
        $severity = $this->enumValue(
            $data['severity'] ?? null,
            ['low', 'medium', 'high', 'critical'],
            '问题严重程度'
        );
        $location = $this->optionalText($data['location'] ?? null, '位置', 255);

        $this->transaction(function () use ($projectId, $issueId, $description, $severity, $location): void {
            $issue = $this->lockIssue($projectId, $issueId);
            $update = $this->db->prepare(
                'UPDATE document_issues
                 SET description = :description, severity = :severity, location = :location,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE issue_id = :issue_id AND project_id = :project_id'
            );
            $update->execute([
                'description' => $description,
                'severity' => $severity,
                'location' => $location,
                'issue_id' => $issueId,
                'project_id' => $projectId,
            ]);

            Support::audit(
                $this->db,
                'document.issue.update',
                'Updated ordinary fields for issue ' . $issueId . '.'
            );
        });
    }

    public function transitionIssue(
        int $projectId,
        int $userId,
        int $issueId,
        string $targetStatus,
        string $reason,
        array $closureData,
        bool $canManage
    ): void {
        $reason = $this->requiredText($reason, '状态变更原因', 2000);
        if (!in_array($targetStatus, self::ISSUE_STATUSES, true)) {
            throw new HttpException('问题状态无效。', 422);
        }

        $this->transaction(function () use (
            $projectId,
            $userId,
            $issueId,
            $targetStatus,
            $reason,
            $closureData,
            $canManage
        ): void {
            $issue = $this->lockIssue($projectId, $issueId);
            $oldStatus = (string) $issue['status'];
            if ($oldStatus === 'closed' && $targetStatus === 'open' && !$canManage) {
                throw new HttpException('只有问题管理员可以重新打开已关闭的问题。', 403);
            }
            if (!in_array($targetStatus, self::ISSUE_TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new HttpException('不允许的问题状态转换。', 409);
            }

            $updates = ['status = :status'];
            $params = [
                'status' => $targetStatus,
                'issue_id' => $issueId,
                'project_id' => $projectId,
            ];
            $resolution = $issue['resolution_result'];
            $verificationStatus = $issue['verification_status'];
            $verificationNote = $issue['verification_note'];
            $evidenceReference = $issue['evidence_reference'];
            $eventEvidence = $this->optionalText(
                $closureData['evidence_reference'] ?? null,
                '证据档案引用',
                1000
            );

            if ($targetStatus === 'resolved' || $targetStatus === 'closed') {
                $resolution = $this->requiredText(
                    $closureData['resolution_result'] ?? $issue['resolution_result'],
                    '处理结果',
                    10000
                );
                $updates[] = 'resolution_result = :resolution_result';
                $params['resolution_result'] = $resolution;
            }

            if ($targetStatus === 'closed') {
                $verificationStatus = $this->enumValue(
                    $closureData['verification_status'] ?? null,
                    ['passed', 'not_required'],
                    '现场验证结果'
                );
                $verificationNote = $this->optionalText(
                    $closureData['verification_note'] ?? null,
                    '现场验证说明',
                    2000
                );
                if ($verificationStatus === 'not_required' && $verificationNote === null) {
                    throw new HttpException('现场验证不适用时必须填写原因。', 422);
                }

                $evidenceReference = $this->requiredText(
                    $closureData['evidence_reference'] ?? null,
                    '证据档案引用',
                    1000
                );
                if ($this->hasOpenIssueFollowUps($projectId, $issueId)) {
                    throw new HttpException('请先完成该问题的所有后续事项。', 409);
                }

                $updates[] = 'verification_status = :verification_status';
                $updates[] = 'verification_note = :verification_note';
                $updates[] = 'evidence_reference = :evidence_reference';
                $updates[] = 'closed_at = CURRENT_TIMESTAMP';
                $updates[] = 'closed_by = :closed_by';
                $params['verification_status'] = $verificationStatus;
                $params['verification_note'] = $verificationNote;
                $params['evidence_reference'] = $evidenceReference;
                $params['closed_by'] = $userId;
                $eventEvidence = $evidenceReference;
            } elseif ($oldStatus === 'closed' && $targetStatus === 'open') {
                $updates[] = "verification_status = 'pending'";
                $updates[] = 'verification_note = NULL';
                $updates[] = 'evidence_reference = NULL';
                $updates[] = 'resolution_result = NULL';
                $updates[] = 'closed_at = NULL';
                $updates[] = 'closed_by = NULL';
            }

            $stmt = $this->db->prepare(
                'UPDATE document_issues
                 SET ' . implode(', ', $updates) . '
                 WHERE issue_id = :issue_id AND project_id = :project_id'
            );
            $stmt->execute($params);

            $transitionData = [
                'resolution_result' => $resolution,
                'verification_status' => $verificationStatus,
                'verification_note' => $verificationNote,
                'evidence_reference' => $eventEvidence ?? $evidenceReference,
            ];
            $this->recordIssueStatus(
                $projectId,
                $issueId,
                $oldStatus,
                $targetStatus,
                $reason,
                $eventEvidence,
                $transitionData,
                $userId
            );
            Support::audit(
                $this->db,
                'document.issue.status_change',
                "Issue {$issueId}: {$oldStatus} -> {$targetStatus}. Reason: {$reason}"
            );
        });
    }

    public function addIssueFollowUp(int $projectId, int $userId, int $issueId, string $title): int
    {
        $title = $this->requiredText($title, '后续事项', 255);

        return $this->transaction(function () use ($projectId, $userId, $issueId, $title): int {
            $issue = $this->lockIssue($projectId, $issueId);
            if ($issue['status'] === 'closed') {
                throw new HttpException('已关闭的问题不能新增后续事项。', 409);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO document_issue_follow_ups (project_id, issue_id, title, created_by)
                 VALUES (:project_id, :issue_id, :title, :created_by)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'issue_id' => $issueId,
                'title' => $title,
                'created_by' => $userId,
            ]);
            $followUpId = (int) $this->db->lastInsertId();
            Support::audit(
                $this->db,
                'document.issue.follow_up.create',
                "Created issue follow-up {$followUpId} for issue {$issueId}."
            );

            return $followUpId;
        });
    }

    public function completeIssueFollowUp(
        int $projectId,
        int $userId,
        int $followUpId,
        string $reason,
        ?string $evidenceReference
    ): void
    {
        $reason = $this->requiredText($reason, '状态变更原因', 2000);
        $evidenceReference = $this->optionalText($evidenceReference, '证据档案引用', 1000);
        $this->transaction(function () use (
            $projectId,
            $userId,
            $followUpId,
            $reason,
            $evidenceReference
        ): void {
            $stmt = $this->db->prepare(
                'SELECT f.issue_id, f.is_completed, i.status
                 FROM document_issue_follow_ups f
                 JOIN document_issues i
                   ON i.issue_id = f.issue_id AND i.project_id = f.project_id
                 WHERE f.follow_up_id = :follow_up_id AND f.project_id = :project_id'
                . $this->lockClause()
            );
            $stmt->execute(['follow_up_id' => $followUpId, 'project_id' => $projectId]);
            $followUp = $stmt->fetch();
            if (!$followUp) {
                throw new HttpException('问题后续事项不存在。', 404);
            }
            if ($followUp['status'] === 'closed') {
                throw new HttpException('已关闭的问题不能修改后续事项。', 409);
            }
            if ((int) $followUp['is_completed'] === 1) {
                return;
            }

            $update = $this->db->prepare(
                'UPDATE document_issue_follow_ups
                 SET is_completed = 1, completed_by = :user_id, completed_at = CURRENT_TIMESTAMP
                 WHERE follow_up_id = :follow_up_id AND project_id = :project_id'
            );
            $update->execute([
                'user_id' => $userId,
                'follow_up_id' => $followUpId,
                'project_id' => $projectId,
            ]);
            $history = $this->db->prepare(
                "INSERT INTO document_issue_follow_up_history
                    (project_id, follow_up_id, old_status, new_status, reason,
                     evidence_reference, changed_by)
                 VALUES
                    (:project_id, :follow_up_id, 'open', 'completed', :reason,
                     :evidence_reference, :changed_by)"
            );
            $history->execute([
                'project_id' => $projectId,
                'follow_up_id' => $followUpId,
                'reason' => $reason,
                'evidence_reference' => $evidenceReference,
                'changed_by' => $userId,
            ]);
            Support::audit(
                $this->db,
                'document.issue.follow_up.complete',
                "Completed issue follow-up {$followUpId}. Reason: {$reason}"
            );
        });
    }

    public function createRfi(int $projectId, int $userId, array $data): int
    {
        $primaryIssueId = $this->positiveId($data['primary_issue_id'] ?? null, '主问题');
        $assignedTo = $this->positiveId($data['assigned_to'] ?? null, '负责人');
        $description = $this->requiredText($data['description'] ?? null, 'RFI 描述', 10000);
        $priority = $this->enumValue(
            $data['priority'] ?? 'medium',
            ['low', 'medium', 'high', 'urgent'],
            'RFI 优先级'
        );
        $dueDate = $this->normalizeDateTime($data['due_date'] ?? null);
        $evidenceRequired = $this->booleanValue($data['evidence_required'] ?? false, '必要证据选项');
        $relatedIssueIds = $this->normalizeIds($data['related_issue_ids'] ?? []);

        return $this->transaction(function () use (
            $projectId,
            $userId,
            $primaryIssueId,
            $assignedTo,
            $description,
            $priority,
            $dueDate,
            $evidenceRequired,
            $relatedIssueIds
        ): int {
            $this->assertIssueInProject($projectId, $primaryIssueId);
            foreach ($relatedIssueIds as $relatedIssueId) {
                if ($relatedIssueId !== $primaryIssueId) {
                    $this->assertIssueInProject($projectId, $relatedIssueId);
                }
            }
            $user = $this->db->prepare('SELECT 1 FROM Users WHERE user_id = :user_id AND is_active = 1');
            $user->execute(['user_id' => $assignedTo]);
            if ($user->fetchColumn() === false) {
                throw new HttpException('RFI 负责人不存在或已停用。', 422);
            }
            $rfiNumber = $this->nextDocumentNumber($projectId, 'RFI');

            $insert = $this->db->prepare(
                "INSERT INTO document_rfis
                    (project_id, primary_issue_id, rfi_number, assigned_to, priority, due_date,
                     description, status, submitted_by, evidence_required)
                 VALUES
                    (:project_id, :primary_issue_id, :rfi_number, :assigned_to, :priority, :due_date,
                     :description, 'draft', :submitted_by, :evidence_required)"
            );
            $insert->execute([
                'project_id' => $projectId,
                'primary_issue_id' => $primaryIssueId,
                'rfi_number' => $rfiNumber,
                'assigned_to' => $assignedTo,
                'priority' => $priority,
                'due_date' => $dueDate,
                'description' => $description,
                'submitted_by' => $userId,
                'evidence_required' => $evidenceRequired ? 1 : 0,
            ]);
            $rfiId = (int) $this->db->lastInsertId();

            $relatedInsert = $this->db->prepare(
                'INSERT INTO document_rfi_related_issues (project_id, rfi_id, issue_id)
                 VALUES (:project_id, :rfi_id, :issue_id)'
            );
            foreach (array_unique($relatedIssueIds) as $relatedIssueId) {
                if ($relatedIssueId === $primaryIssueId) {
                    continue;
                }
                $relatedInsert->execute([
                    'project_id' => $projectId,
                    'rfi_id' => $rfiId,
                    'issue_id' => $relatedIssueId,
                ]);
            }

            $this->recordRfiStatus(
                $projectId,
                $rfiId,
                null,
                'draft',
                '创建 RFI 草稿',
                null,
                ['primary_issue_id' => $primaryIssueId, 'rfi_number' => $rfiNumber],
                $userId
            );
            Support::audit($this->db, 'document.rfi.create', 'Created RFI ' . $rfiNumber . '.');

            return $rfiId;
        });
    }

    public function updateRfi(int $projectId, int $rfiId, array $data): void
    {
        $assignedTo = $this->positiveId($data['assigned_to'] ?? null, '负责人');
        $description = $this->requiredText($data['description'] ?? null, 'RFI 描述', 10000);
        $priority = $this->enumValue(
            $data['priority'] ?? null,
            ['low', 'medium', 'high', 'urgent'],
            'RFI 优先级'
        );
        $dueDate = $this->normalizeDateTime($data['due_date'] ?? null);
        $evidenceRequired = $this->booleanValue($data['evidence_required'] ?? false, '必要证据选项');
        $hasIssueLinks = array_key_exists('primary_issue_id', $data)
            || array_key_exists('related_issue_ids', $data);
        $primaryIssueId = array_key_exists('primary_issue_id', $data)
            ? $this->positiveId($data['primary_issue_id'], '主问题')
            : null;
        $relatedIssueIds = array_key_exists('related_issue_ids', $data)
            ? $this->normalizeIds($data['related_issue_ids'])
            : [];

        $this->transaction(function () use (
            $projectId,
            $rfiId,
            $assignedTo,
            $description,
            $priority,
            $dueDate,
            $evidenceRequired,
            $hasIssueLinks,
            $primaryIssueId,
            $relatedIssueIds
        ): void {
            $rfi = $this->lockRfi($projectId, $rfiId);
            if ($rfi['status'] === 'closed') {
                throw new HttpException('已关闭的 RFI 不可编辑。', 409);
            }
            if ($hasIssueLinks && $rfi['status'] !== 'draft') {
                throw new HttpException('RFI 提交后不可修改关联问题。', 409);
            }

            $assignedUser = $this->db->prepare(
                'SELECT 1 FROM Users WHERE user_id = :user_id AND is_active = 1'
            );
            $assignedUser->execute(['user_id' => $assignedTo]);
            if ($assignedUser->fetchColumn() === false) {
                throw new HttpException('RFI 负责人不存在或已停用。', 422);
            }

            $primaryIssueId = $primaryIssueId ?? (int) $rfi['primary_issue_id'];
            if ($hasIssueLinks) {
                $this->assertIssueInProject($projectId, $primaryIssueId);
                foreach ($relatedIssueIds as $relatedIssueId) {
                    if ($relatedIssueId !== $primaryIssueId) {
                        $this->assertIssueInProject($projectId, $relatedIssueId);
                    }
                }
            }

            $update = $this->db->prepare(
                'UPDATE document_rfis
                 SET primary_issue_id = :primary_issue_id, assigned_to = :assigned_to,
                     priority = :priority, due_date = :due_date, description = :description,
                     evidence_required = :evidence_required, updated_at = CURRENT_TIMESTAMP
                 WHERE rfi_id = :rfi_id AND project_id = :project_id'
            );
            $update->execute([
                'primary_issue_id' => $primaryIssueId,
                'assigned_to' => $assignedTo,
                'priority' => $priority,
                'due_date' => $dueDate,
                'description' => $description,
                'evidence_required' => $evidenceRequired ? 1 : 0,
                'rfi_id' => $rfiId,
                'project_id' => $projectId,
            ]);

            if ($hasIssueLinks) {
                $deleteRelated = $this->db->prepare(
                    'DELETE FROM document_rfi_related_issues
                     WHERE project_id = :project_id AND rfi_id = :rfi_id'
                );
                $deleteRelated->execute(['project_id' => $projectId, 'rfi_id' => $rfiId]);

                $insertRelated = $this->db->prepare(
                    'INSERT INTO document_rfi_related_issues (project_id, rfi_id, issue_id)
                     VALUES (:project_id, :rfi_id, :issue_id)'
                );
                foreach (array_unique($relatedIssueIds) as $relatedIssueId) {
                    if ($relatedIssueId === $primaryIssueId) {
                        continue;
                    }
                    $insertRelated->execute([
                        'project_id' => $projectId,
                        'rfi_id' => $rfiId,
                        'issue_id' => $relatedIssueId,
                    ]);
                }
            }

            Support::audit(
                $this->db,
                'document.rfi.update',
                'Updated RFI ' . $rfi['rfi_number'] . '.'
            );
        });
    }

    public function transitionRfi(
        int $projectId,
        int $userId,
        int $rfiId,
        string $targetStatus,
        string $reason,
        array $transitionData,
        bool $canManage
    ): void {
        $reason = $this->requiredText($reason, '状态变更原因', 2000);
        if (!in_array($targetStatus, self::RFI_STATUSES, true)) {
            throw new HttpException('RFI 状态无效。', 422);
        }

        $this->transaction(function () use (
            $projectId,
            $userId,
            $rfiId,
            $targetStatus,
            $reason,
            $transitionData,
            $canManage
        ): void {
            $rfi = $this->lockRfi($projectId, $rfiId);
            $oldStatus = (string) $rfi['status'];
            if ($oldStatus === 'closed' && $targetStatus === 'submitted' && !$canManage) {
                throw new HttpException('只有问题管理员可以重新打开已关闭的 RFI。', 403);
            }
            if (!in_array($targetStatus, self::RFI_TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new HttpException('不允许的 RFI 状态转换。', 409);
            }

            $updates = ['status = :status'];
            $params = [
                'status' => $targetStatus,
                'rfi_id' => $rfiId,
                'project_id' => $projectId,
            ];
            $responseText = $rfi['response_text'];
            $responseComplete = (int) $rfi['response_complete'] === 1;
            $evidenceRequired = (int) $rfi['evidence_required'] === 1;
            $evidenceReference = $rfi['evidence_reference'];
            $eventEvidence = $this->optionalText(
                $transitionData['evidence_reference'] ?? null,
                '证据档案引用',
                1000
            );

            if ($targetStatus === 'responded') {
                $responseText = $this->requiredText(
                    $transitionData['response_text'] ?? null,
                    '正式答复',
                    10000
                );
                $responseComplete = $this->booleanValue(
                    $transitionData['response_complete'] ?? false,
                    '答复完整性'
                );
                if (!$responseComplete) {
                    throw new HttpException('答复未确认完整，不能提交为正式答复。', 422);
                }
                $evidenceReference = $this->optionalText(
                    $transitionData['evidence_reference'] ?? null,
                    '证据档案引用',
                    1000
                );
                $updates[] = 'response_text = :response_text';
                $updates[] = 'response_complete = 1';
                $updates[] = 'evidence_reference = :evidence_reference';
                $updates[] = 'responded_at = CURRENT_TIMESTAMP';
                $params['response_text'] = $responseText;
                $params['evidence_reference'] = $evidenceReference;
                $eventEvidence = $evidenceReference;
            }

            if ($targetStatus === 'closed') {
                if (trim((string) $responseText) === '' || !$responseComplete) {
                    throw new HttpException('RFI 必须先提交完整正式答复。', 409);
                }
                $evidenceReference = $this->optionalText(
                    $transitionData['evidence_reference'] ?? $evidenceReference,
                    '证据档案引用',
                    1000
                );
                if ($evidenceRequired && trim((string) $evidenceReference) === '') {
                    throw new HttpException('此 RFI 需要证据档案引用后才能关闭。', 422);
                }
                if ($this->hasOpenRfiFollowUps($projectId, $rfiId)) {
                    throw new HttpException('请先完成该 RFI 的所有后续事项。', 409);
                }

                $updates[] = 'closed_at = CURRENT_TIMESTAMP';
                $updates[] = 'closed_by = :closed_by';
                $updates[] = 'evidence_reference = :evidence_reference';
                $params['closed_by'] = $userId;
                $params['evidence_reference'] = $evidenceReference;
                $eventEvidence = $evidenceReference;
            } elseif ($oldStatus === 'closed' && $targetStatus === 'submitted') {
                $updates[] = 'response_text = NULL';
                $updates[] = 'response_complete = 0';
                $updates[] = 'evidence_reference = NULL';
                $updates[] = 'responded_at = NULL';
                $updates[] = 'closed_at = NULL';
                $updates[] = 'closed_by = NULL';
            }

            $update = $this->db->prepare(
                'UPDATE document_rfis
                 SET ' . implode(', ', $updates) . '
                 WHERE rfi_id = :rfi_id AND project_id = :project_id'
            );
            $update->execute($params);

            $historyData = [
                'response_text' => $responseText,
                'response_complete' => $responseComplete,
                'evidence_required' => $evidenceRequired,
                'evidence_reference' => $evidenceReference,
            ];
            $this->recordRfiStatus(
                $projectId,
                $rfiId,
                $oldStatus,
                $targetStatus,
                $reason,
                $eventEvidence,
                $historyData,
                $userId
            );

            $action = $targetStatus === 'responded'
                ? 'document.rfi.respond'
                : 'document.rfi.status_change';
            Support::audit(
                $this->db,
                $action,
                "RFI {$rfi['rfi_number']}: {$oldStatus} -> {$targetStatus}. Reason: {$reason}"
            );
        });
    }

    public function addRfiFollowUp(int $projectId, int $userId, int $rfiId, string $title): int
    {
        $title = $this->requiredText($title, '后续事项', 255);

        return $this->transaction(function () use ($projectId, $userId, $rfiId, $title): int {
            $rfi = $this->lockRfi($projectId, $rfiId);
            if ($rfi['status'] === 'closed') {
                throw new HttpException('已关闭的 RFI 不能新增后续事项。', 409);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO document_rfi_follow_ups (project_id, rfi_id, title, created_by)
                 VALUES (:project_id, :rfi_id, :title, :created_by)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'rfi_id' => $rfiId,
                'title' => $title,
                'created_by' => $userId,
            ]);
            $followUpId = (int) $this->db->lastInsertId();
            Support::audit(
                $this->db,
                'document.rfi.follow_up.create',
                "Created RFI follow-up {$followUpId} for RFI {$rfi['rfi_number']}."
            );

            return $followUpId;
        });
    }

    public function completeRfiFollowUp(
        int $projectId,
        int $userId,
        int $followUpId,
        string $reason,
        ?string $evidenceReference
    ): void
    {
        $reason = $this->requiredText($reason, '状态变更原因', 2000);
        $evidenceReference = $this->optionalText($evidenceReference, '证据档案引用', 1000);
        $this->transaction(function () use (
            $projectId,
            $userId,
            $followUpId,
            $reason,
            $evidenceReference
        ): void {
            $stmt = $this->db->prepare(
                'SELECT f.rfi_id, f.is_completed, r.status, r.rfi_number
                 FROM document_rfi_follow_ups f
                 JOIN document_rfis r ON r.rfi_id = f.rfi_id AND r.project_id = f.project_id
                 WHERE f.follow_up_id = :follow_up_id AND f.project_id = :project_id'
                . $this->lockClause()
            );
            $stmt->execute(['follow_up_id' => $followUpId, 'project_id' => $projectId]);
            $followUp = $stmt->fetch();
            if (!$followUp) {
                throw new HttpException('RFI 后续事项不存在。', 404);
            }
            if ($followUp['status'] === 'closed') {
                throw new HttpException('已关闭的 RFI 不能修改后续事项。', 409);
            }
            if ((int) $followUp['is_completed'] === 1) {
                return;
            }

            $update = $this->db->prepare(
                'UPDATE document_rfi_follow_ups
                 SET is_completed = 1, completed_by = :user_id, completed_at = CURRENT_TIMESTAMP
                 WHERE follow_up_id = :follow_up_id AND project_id = :project_id'
            );
            $update->execute([
                'user_id' => $userId,
                'follow_up_id' => $followUpId,
                'project_id' => $projectId,
            ]);
            $history = $this->db->prepare(
                "INSERT INTO document_rfi_follow_up_history
                    (project_id, follow_up_id, old_status, new_status, reason,
                     evidence_reference, changed_by)
                 VALUES
                    (:project_id, :follow_up_id, 'open', 'completed', :reason,
                     :evidence_reference, :changed_by)"
            );
            $history->execute([
                'project_id' => $projectId,
                'follow_up_id' => $followUpId,
                'reason' => $reason,
                'evidence_reference' => $evidenceReference,
                'changed_by' => $userId,
            ]);
            Support::audit(
                $this->db,
                'document.rfi.follow_up.complete',
                "Completed RFI follow-up {$followUpId}. Reason: {$reason}"
            );
        });
    }

    public function availableIssueTransitions(string $status, bool $canManage): array
    {
        $transitions = self::ISSUE_TRANSITIONS[$status] ?? [];
        if (!$canManage) {
            return array_values(array_filter($transitions, static fn (string $target): bool => $target !== 'open' || $status !== 'closed'));
        }

        return $transitions;
    }

    public function availableRfiTransitions(string $status, bool $canManage): array
    {
        $transitions = self::RFI_TRANSITIONS[$status] ?? [];
        if (!$canManage) {
            return array_values(array_filter($transitions, static fn (string $target): bool => $target !== 'submitted' || $status !== 'closed'));
        }

        return $transitions;
    }

    private function lockIssue(int $projectId, int $issueId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM document_issues
             WHERE issue_id = :issue_id AND project_id = :project_id'
            . $this->lockClause()
        );
        $stmt->execute(['issue_id' => $issueId, 'project_id' => $projectId]);
        $issue = $stmt->fetch();
        if (!$issue) {
            throw new HttpException('问题不存在于当前项目。', 404);
        }

        return $issue;
    }

    private function lockRfi(int $projectId, int $rfiId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM document_rfis
             WHERE rfi_id = :rfi_id AND project_id = :project_id'
            . $this->lockClause()
        );
        $stmt->execute(['rfi_id' => $rfiId, 'project_id' => $projectId]);
        $rfi = $stmt->fetch();
        if (!$rfi) {
            throw new HttpException('RFI 不存在于当前项目。', 404);
        }

        return $rfi;
    }

    private function assertIssueInProject(int $projectId, int $issueId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM document_issues WHERE issue_id = :issue_id AND project_id = :project_id'
        );
        $stmt->execute(['issue_id' => $issueId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('关联问题不存在于当前项目。', 422);
        }
    }

    private function recordIssueStatus(
        int $projectId,
        int $issueId,
        ?string $oldStatus,
        string $newStatus,
        string $reason,
        ?string $evidenceReference,
        array $transitionData,
        int $userId
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO document_issue_status_history
                (project_id, issue_id, old_status, new_status, reason,
                 evidence_reference, transition_data, changed_by)
             VALUES
                (:project_id, :issue_id, :old_status, :new_status, :reason,
                 :evidence_reference, :transition_data, :changed_by)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'issue_id' => $issueId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
            'transition_data' => json_encode($transitionData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'changed_by' => $userId,
        ]);
    }

    private function recordRfiStatus(
        int $projectId,
        int $rfiId,
        ?string $oldStatus,
        string $newStatus,
        string $reason,
        ?string $evidenceReference,
        array $transitionData,
        int $userId
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO document_rfi_status_history
                (project_id, rfi_id, old_status, new_status, reason,
                 evidence_reference, transition_data, changed_by)
             VALUES
                (:project_id, :rfi_id, :old_status, :new_status, :reason,
                 :evidence_reference, :transition_data, :changed_by)'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'rfi_id' => $rfiId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
            'transition_data' => json_encode($transitionData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'changed_by' => $userId,
        ]);
    }

    private function hasOpenIssueFollowUps(int $projectId, int $issueId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM document_issue_follow_ups
             WHERE project_id = :project_id AND issue_id = :issue_id AND is_completed = 0
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId, 'issue_id' => $issueId]);

        return $stmt->fetchColumn() !== false;
    }

    private function hasOpenRfiFollowUps(int $projectId, int $rfiId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM document_rfi_follow_ups
             WHERE project_id = :project_id AND rfi_id = :rfi_id AND is_completed = 0
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId, 'rfi_id' => $rfiId]);

        return $stmt->fetchColumn() !== false;
    }

    private function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function lockClause(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function nextDocumentNumber(int $projectId, string $documentType): string
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ensureSequence = $driver === 'mysql'
            ? $this->db->prepare(
                'INSERT IGNORE INTO document_number_sequences (project_id, document_type, last_number)
                 VALUES (:project_id, :document_type, 0)'
            )
            : $this->db->prepare(
                'INSERT INTO document_number_sequences (project_id, document_type, last_number)
                 VALUES (:project_id, :document_type, 0)
                 ON CONFLICT(project_id, document_type) DO NOTHING'
            );
        $ensureSequence->execute([
            'project_id' => $projectId,
            'document_type' => $documentType,
        ]);

        $sequence = $this->db->prepare(
            'SELECT last_number
             FROM document_number_sequences
             WHERE project_id = :project_id AND document_type = :document_type'
            . $this->lockClause()
        );
        $sequence->execute([
            'project_id' => $projectId,
            'document_type' => $documentType,
        ]);
        $nextNumber = (int) $sequence->fetchColumn() + 1;
        $updateSequence = $this->db->prepare(
            'UPDATE document_number_sequences
             SET last_number = :last_number
             WHERE project_id = :project_id AND document_type = :document_type'
        );
        $updateSequence->execute([
            'last_number' => $nextNumber,
            'project_id' => $projectId,
            'document_type' => $documentType,
        ]);

        return 'P' . $projectId . '-' . $documentType . '-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    private function requiredText(mixed $value, string $field, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new HttpException($field . '格式无效。', 422);
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new HttpException($field . '不能为空，且长度不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value;
    }

    private function optionalText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($field . '格式无效。', 422);
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException($field . '不能超过 ' . $maxLength . ' 个字符。', 422);
        }

        return $value;
    }

    private function enumValue(mixed $value, array $allowed, string $field): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new HttpException($field . '无效。', 422);
        }

        return $value;
    }

    private function positiveId(mixed $value, string $field): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new HttpException($field . '编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($field . '编号无效。', 422);
        }

        return $id;
    }

    private function normalizeIds(mixed $values): array
    {
        if (!is_array($values)) {
            throw new HttpException('关联问题格式无效。', 422);
        }
        $ids = [];
        foreach ($values as $value) {
            $ids[] = $this->positiveId($value, '关联问题');
        }

        return array_values(array_unique($ids));
    }

    private function booleanValue(mixed $value, string $field): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === null || $value === '') {
            return false;
        }

        throw new HttpException($field . '格式无效。', 422);
    }

    private function normalizeDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new HttpException('必须填写 RFI 截止时间。', 422);
        }
        if (!is_string($value)) {
            throw new HttpException('RFI 截止时间格式无效。', 422);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, \ProjectO\Support::displayTimezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            throw new HttpException('RFI 截止时间格式无效。', 422);
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function idList(array $records, string $field): array
    {
        $placeholders = [];
        $params = [];
        foreach ($records as $index => $record) {
            $key = 'record_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $record[$field];
        }

        return [implode(', ', $placeholders), $params];
    }

    private function groupBy(array $rows, string $field): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row[$field]][] = $row;
        }

        return $grouped;
    }
}

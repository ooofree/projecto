<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use DateTimeImmutable;
use PDO;
use ProjectO\Contracts\ProjectSpaceCatalog;
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

    public function __construct(
        private readonly PDO $db,
        private readonly ?ProjectSpaceCatalog $spaces = null
    ) {
    }

    /**
     * Selectable project spaces for the issue location control.
     *
     * Returns an empty catalogue when the space plugin contract is unavailable,
     * so the page can explain the situation instead of failing.
     *
     * @return array{options: list<array{value: string, label: string, type: string, id: int}>, labels: array<string, string>}
     */
    public function issueLocationCatalog(int $projectId): array
    {
        if ($this->spaces === null) {
            return ['options' => [], 'labels' => []];
        }

        return $this->spaces->locationCatalog($projectId);
    }

    /**
     * Resolve a submitted location into storage columns.
     *
     * An empty selection clears both the structured reference and the legacy
     * text. The `legacy` marker keeps an existing free-text location untouched,
     * so editing unrelated fields never discards a historical location. A
     * `type:id` selection is validated against the current project by the space
     * catalogue; free text is only accepted when no space catalogue is
     * available, which keeps behaviour explicit rather than silently dropping
     * the submitted value.
     *
     * @return array{
     *     location: string|null,
     *     location_type: string|null,
     *     location_building_id: int|null,
     *     location_floor_id: int|null,
     *     location_area_id: int|null,
     *     location_room_id: int|null
     * }
     */
    private function resolveIssueLocation(int $projectId, mixed $value, ?string $legacyText = null): array
    {
        $empty = [
            'location' => null,
            'location_type' => null,
            'location_building_id' => null,
            'location_floor_id' => null,
            'location_area_id' => null,
            'location_room_id' => null,
        ];
        if ($value === null || $value === '') {
            return $empty;
        }
        if (!is_string($value)) {
            throw new HttpException('位置格式无效。', 422);
        }
        $value = trim($value);
        if ($value === '') {
            return $empty;
        }

        if ($value === 'legacy') {
            if ($legacyText === null) {
                throw new HttpException('没有可保留的原位置。', 422);
            }

            return ['location' => $this->optionalText($legacyText, '位置', 255)]
                + array_slice($empty, 1, null, true);
        }

        if (preg_match('/^(building|floor|area|room):([1-9]\d*)$/', $value, $matches) === 1) {
            if ($this->spaces === null) {
                throw new HttpException('项目空间暂不可用，无法按空间登记位置。', 503);
            }
            $space = $this->spaces->requireLocation($projectId, $matches[1], (int) $matches[2]);

            return [
                'location' => null,
                'location_type' => $space['type'],
                'location_building_id' => $space['building_id'],
                'location_floor_id' => $space['floor_id'],
                'location_area_id' => $space['area_id'],
                'location_room_id' => $space['room_id'],
            ];
        }

        if ($this->spaces !== null) {
            throw new HttpException('请从当前项目的空间中选择位置。', 422);
        }

        return ['location' => $this->optionalText($value, '位置', 255)] + array_slice($empty, 1, null, true);
    }

    public function listIssues(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.issue_id, i.project_id, i.issue_number, i.description, i.severity, i.status,
                    i.location, i.location_type, i.location_building_id, i.location_floor_id,
                    i.location_area_id, i.location_room_id, i.location_note,
                    i.reported_by, i.reported_at, i.resolution_result,
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

        $labels = $this->spaces === null ? [] : $this->spaces->locationCatalog($projectId)['labels'];
        foreach ($issues as &$issue) {
            $id = (int) $issue['issue_id'];
            $issue['follow_ups'] = $followUpsByIssue[$id] ?? [];
            $issue['status_history'] = $historyByIssue[$id] ?? [];
            $issue['location_value'] = $issue['location_type'] === null
                ? ''
                : $issue['location_type'] . ':' . $this->issueLocationEntityId($issue);
            $issue['location_name'] = $this->issueLocationName($issue, $labels);
            $issue['location_note'] = $issue['location_note'] === null ? '' : (string) $issue['location_note'];
            $issue['location_display'] = $this->issueLocationDisplay($issue);
        }
        unset($issue);

        return $issues;
    }

    /** @param array<string, mixed> $issue */
    private function issueLocationEntityId(array $issue): int
    {
        return (int) match ((string) $issue['location_type']) {
            'building' => $issue['location_building_id'],
            'floor' => $issue['location_floor_id'],
            'area' => $issue['location_area_id'],
            'room' => $issue['location_room_id'],
            default => 0,
        };
    }

    /**
     * Display name for one issue location.
     *
     * Structured references resolve through the space catalogue (archived spaces
     * keep their name). Records without a structured reference fall back to the
     * legacy free-text column, so historical entries stay readable.
     *
     * @param array<string, mixed> $issue
     * @param array<string, string> $labels
     */
    private function issueLocationName(array $issue, array $labels): string
    {
        if ($issue['location_type'] !== null) {
            $value = $issue['location_type'] . ':' . $this->issueLocationEntityId($issue);

            return $labels[$value] ?? '位置已删除';
        }

        return (string) ($issue['location'] ?? '');
    }

    /**
     * Space name plus the optional detail note, as shown in lists and exports.
     *
     * The note carries precision the space catalogue cannot express (grid axes,
     * lobbies, risers), so it is appended rather than replacing the space.
     *
     * @param array<string, mixed> $issue
     */
    private function issueLocationDisplay(array $issue): string
    {
        $name = (string) ($issue['location_name'] ?? '');
        $note = trim((string) ($issue['location_note'] ?? ''));
        if ($note === '') {
            return $name;
        }

        return $name === '' ? $note : $name . ' · ' . $note;
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
        return $this->transaction(function () use (
            $projectId,
            $userId,
            $description,
            $severity,
            $data
        ): int {
            // Resolved after the caller's permission and project checks so an
            // unauthorized request cannot probe which spaces exist.
            $location = $this->resolveIssueLocation($projectId, $data['location'] ?? null);
            $locationNote = $this->optionalText($data['location_note'] ?? null, '位置补充说明', 255);
            $issueNumber = $this->nextDocumentNumber($projectId, 'ISS');
            $stmt = $this->db->prepare(
                "INSERT INTO document_issues
                    (project_id, issue_number, description, severity, status, location,
                     location_type, location_building_id, location_floor_id,
                     location_area_id, location_room_id, location_note, reported_by)
                 VALUES
                    (:project_id, :issue_number, :description, :severity, 'open', :location,
                     :location_type, :location_building_id, :location_floor_id,
                     :location_area_id, :location_room_id, :location_note, :reported_by)"
            );
            $stmt->execute([
                'project_id' => $projectId,
                'issue_number' => $issueNumber,
                'description' => $description,
                'severity' => $severity,
                'location' => $location['location'],
                'location_type' => $location['location_type'],
                'location_building_id' => $location['location_building_id'],
                'location_floor_id' => $location['location_floor_id'],
                'location_area_id' => $location['location_area_id'],
                'location_room_id' => $location['location_room_id'],
                'location_note' => $locationNote,
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
        $this->transaction(function () use ($projectId, $issueId, $description, $severity, $data): void {
            $issue = $this->lockIssue($projectId, $issueId);
            // After the record lock: a foreign issue stays a plain 404 and the
            // submitted space is never probed.
            $location = $this->resolveIssueLocation(
                $projectId,
                $data['location'] ?? null,
                (string) ($issue['location'] ?? '')
            );
            $locationNote = $this->optionalText($data['location_note'] ?? null, '位置补充说明', 255);
            $update = $this->db->prepare(
                'UPDATE document_issues
                 SET description = :description, severity = :severity, location = :location,
                     location_type = :location_type, location_building_id = :location_building_id,
                     location_floor_id = :location_floor_id, location_area_id = :location_area_id,
                     location_room_id = :location_room_id, location_note = :location_note,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE issue_id = :issue_id AND project_id = :project_id'
            );
            $update->execute([
                'description' => $description,
                'severity' => $severity,
                'location' => $location['location'],
                'location_type' => $location['location_type'],
                'location_building_id' => $location['location_building_id'],
                'location_floor_id' => $location['location_floor_id'],
                'location_area_id' => $location['location_area_id'],
                'location_room_id' => $location['location_room_id'],
                'location_note' => $locationNote,
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
        $this->transaction(function () use (
            $projectId,
            $userId,
            $issueId,
            $targetStatus,
            $reason,
            $closureData,
            $canManage
        ): void {
            $this->applyIssueTransition(
                $projectId,
                $userId,
                $issueId,
                $targetStatus,
                $reason,
                $closureData,
                $canManage
            );
        });
    }

    /**
     * Applies one issue transition inside the caller's transaction.
     *
     * `$systemAdvance` marks transitions derived from RFI milestones. Those may
     * enter `resolved` without a human-written resolution (an RFI answer is
     * reference material, never an automatic conclusion) and never require the
     * manage permission reserved for reopening a closed issue.
     */
    private function applyIssueTransition(
        int $projectId,
        int $userId,
        int $issueId,
        string $targetStatus,
        string $reason,
        array $closureData,
        bool $canManage,
        bool $systemAdvance = false
    ): void {
        $reason = $this->requiredText($reason, '状态变更原因', 2000);
        if (!in_array($targetStatus, self::ISSUE_STATUSES, true)) {
            throw new HttpException('问题状态无效。', 422);
        }

        $issue = $this->lockIssue($projectId, $issueId);
        $oldStatus = (string) $issue['status'];
        if (!$systemAdvance && $oldStatus === 'closed' && $targetStatus === 'open' && !$canManage) {
            throw new HttpException('只有问题管理员可以重新打开已关闭的问题。', 403);
        }
        if (!in_array($targetStatus, self::ISSUE_TRANSITIONS[$oldStatus] ?? [], true)) {
            throw new HttpException('不允许的问题状态转换。', 409);
        }

        $updates = ['status = :status', 'updated_at = CURRENT_TIMESTAMP'];
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
            // A system advance only moves the issue into the review queue; the
            // person closing it still has to author the resolution.
            if (!$systemAdvance || $targetStatus === 'closed') {
                $resolution = $this->requiredText(
                    $closureData['resolution_result'] ?? $issue['resolution_result'],
                    '处理结果',
                    10000
                );
                $updates[] = 'resolution_result = :resolution_result';
                $params['resolution_result'] = $resolution;
            }
        }

        if ($targetStatus === 'closed') {
            $this->assertLinkedRfisClosed($projectId, $issueId);
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
    }

    /**
     * @return list<array{rfi_id:int, rfi_number:string, status:string, is_primary:bool}>
     */
    private function linkedRfisForIssue(int $projectId, int $issueId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.rfi_id, r.rfi_number, r.status,
                    CASE WHEN r.primary_issue_id = :primary_match THEN 1 ELSE 0 END AS is_primary
             FROM document_rfis r
             WHERE r.project_id = :project_id
               AND (r.primary_issue_id = :owner_match
                    OR EXISTS (
                        SELECT 1 FROM document_rfi_related_issues ri
                        WHERE ri.project_id = r.project_id
                          AND ri.rfi_id = r.rfi_id
                          AND ri.issue_id = :related_match
                    ))
             ORDER BY r.rfi_id'
        );
        $stmt->execute([
            'primary_match' => $issueId,
            'project_id' => $projectId,
            'owner_match' => $issueId,
            'related_match' => $issueId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'rfi_id' => (int) $row['rfi_id'],
                'rfi_number' => (string) ($row['rfi_number'] ?? ''),
                'status' => (string) $row['status'],
                'is_primary' => (int) $row['is_primary'] === 1,
            ];
        }

        return $rows;
    }

    private function hasOpenLinkedRfis(int $projectId, int $issueId): bool
    {
        foreach ($this->linkedRfisForIssue($projectId, $issueId) as $rfi) {
            if ($rfi['status'] !== 'closed') {
                return true;
            }
        }

        return false;
    }

    private function assertLinkedRfisClosed(int $projectId, int $issueId): void
    {
        $open = array_values(array_filter(
            $this->linkedRfisForIssue($projectId, $issueId),
            static fn (array $rfi): bool => $rfi['status'] !== 'closed'
        ));
        if ($open === []) {
            return;
        }

        $labels = [
            'draft' => '草稿',
            'submitted' => '已提交',
            'under_review' => '审核中',
            'responded' => '已答复',
            'closed' => '已关闭',
        ];
        $sample = array_slice($open, 0, 5);
        $parts = [];
        foreach ($sample as $rfi) {
            $number = $rfi['rfi_number'] !== '' ? $rfi['rfi_number'] : '#' . $rfi['rfi_id'];
            $parts[] = $number . '（' . ($labels[$rfi['status']] ?? $rfi['status']) . '）';
        }

        $suffix = count($open) > count($sample) ? ' 等 ' . count($open) . ' 条' : '';
        throw new HttpException(
            '该问题仍有未关闭的 RFI：' . implode('、', $parts) . $suffix . '；请先完成答复并关闭 RFI 后再关闭问题。',
            409
        );
    }

    /**
     * @return list<int>
     */
    private function rfiLinkedIssueIds(int $projectId, int $rfiId): array
    {
        $stmt = $this->db->prepare(
            'SELECT primary_issue_id AS issue_id
             FROM document_rfis
             WHERE project_id = :project_id AND rfi_id = :rfi_id
             UNION
             SELECT issue_id
             FROM document_rfi_related_issues
             WHERE project_id = :link_project_id AND rfi_id = :link_rfi_id'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'rfi_id' => $rfiId,
            'link_project_id' => $projectId,
            'link_rfi_id' => $rfiId,
        ]);

        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * Keeps the issue workflow in step with its RFI milestones without ever
     * closing an issue on its own: closing the last RFI moves an in-review
     * issue to `resolved` (the review queue), and reopening a closed RFI sends a
     * `resolved` issue back to `in_review`.
     */
    private function syncIssuesAfterRfiTransition(
        int $projectId,
        int $userId,
        int $rfiId,
        string $oldStatus,
        string $newStatus,
        string $rfiNumber
    ): void {
        $reasonPrefix = '关联 RFI ' . $rfiNumber;
        foreach ($this->rfiLinkedIssueIds($projectId, $rfiId) as $issueId) {
            $issue = $this->lockIssue($projectId, $issueId);
            $issueStatus = (string) $issue['status'];

            if ($newStatus === 'closed' && $oldStatus !== 'closed' && $issueStatus === 'in_review') {
                if ($this->hasOpenLinkedRfis($projectId, $issueId)) {
                    continue;
                }
                $this->applyIssueTransition(
                    $projectId,
                    $userId,
                    $issueId,
                    'resolved',
                    $reasonPrefix . ' 已关闭，该问题名下 RFI 已全部收口，系统自动转为待关闭，等待复核。',
                    [],
                    false,
                    true
                );
                continue;
            }

            if ($oldStatus === 'closed' && $newStatus === 'submitted' && $issueStatus === 'resolved') {
                $this->applyIssueTransition(
                    $projectId,
                    $userId,
                    $issueId,
                    'in_review',
                    $reasonPrefix . ' 重新打开，问题依据失效，系统自动退回处理中。',
                    [],
                    false,
                    true
                );
            }
        }
    }

    /**
     * @return array<int, list<array{rfi_id:int, rfi_number:string, status:string, is_primary:bool}>>
     */
    public function issueLinkedRfis(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rfi_id, rfi_number, status, primary_issue_id
             FROM document_rfis
             WHERE project_id = :project_id
             ORDER BY rfi_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        $byId = [];
        $links = [];
        foreach ($stmt->fetchAll() as $row) {
            $rfiId = (int) $row['rfi_id'];
            $primaryIssueId = (int) $row['primary_issue_id'];
            $byId[$rfiId] = $row;
            $links[$primaryIssueId][] = [
                'rfi_id' => $rfiId,
                'rfi_number' => (string) ($row['rfi_number'] ?? ''),
                'status' => (string) $row['status'],
                'is_primary' => true,
            ];
        }

        $relStmt = $this->db->prepare(
            'SELECT rfi_id, issue_id
             FROM document_rfi_related_issues
             WHERE project_id = :project_id'
        );
        $relStmt->execute(['project_id' => $projectId]);
        foreach ($relStmt->fetchAll() as $row) {
            $rfiId = (int) $row['rfi_id'];
            $issueId = (int) $row['issue_id'];
            if (!isset($byId[$rfiId]) || (int) $byId[$rfiId]['primary_issue_id'] === $issueId) {
                continue;
            }
            $links[$issueId][] = [
                'rfi_id' => $rfiId,
                'rfi_number' => (string) ($byId[$rfiId]['rfi_number'] ?? ''),
                'status' => (string) $byId[$rfiId]['status'],
                'is_primary' => false,
            ];
        }

        foreach ($links as $issueId => $rows) {
            usort($rows, static fn (array $a, array $b): int => $a['rfi_id'] <=> $b['rfi_id']);
            $links[$issueId] = $rows;
        }

        return $links;
    }

    /**
     * Row-level shortcuts used by the ledger quick actions. The step owns the
     * target status and a default audit reason; every validation of the normal
     * transition still applies.
     */
    public function quickIssue(
        int $projectId,
        int $userId,
        int $issueId,
        string $step,
        array $data,
        bool $canManage
    ): void {
        $steps = [
            'start' => ['in_review', '快捷操作：开始处理。'],
            'resolve' => ['resolved', '快捷操作：转待关闭。'],
            'close' => ['closed', '快捷操作：关闭问题。'],
            'reopen' => ['open', '快捷操作：重新打开问题。'],
        ];
        if (!isset($steps[$step])) {
            throw new HttpException('不支持的问题快捷操作。', 422);
        }

        [$targetStatus, $defaultReason] = $steps[$step];
        $reason = $this->optionalText($data['reason'] ?? null, '状态变更原因', 2000) ?? $defaultReason;
        $this->transitionIssue(
            $projectId,
            $userId,
            $issueId,
            $targetStatus,
            $reason,
            [
                'resolution_result' => $data['resolution_result'] ?? null,
                'verification_status' => $data['verification_status'] ?? null,
                'verification_note' => $data['verification_note'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
            ],
            $canManage
        );
    }

    public function quickRfi(
        int $projectId,
        int $userId,
        int $rfiId,
        string $step,
        array $data,
        bool $canManage
    ): void {
        $steps = [
            'submit' => ['submitted', '快捷操作：提交 RFI。'],
            'review' => ['under_review', '快捷操作：开始审核。'],
            'respond' => ['responded', '快捷操作：答复 RFI。'],
            'close' => ['closed', '快捷操作：关闭 RFI。'],
            'reopen' => ['submitted', '快捷操作：重新打开 RFI。'],
        ];
        if (!isset($steps[$step])) {
            throw new HttpException('不支持的 RFI 快捷操作。', 422);
        }

        [$targetStatus, $defaultReason] = $steps[$step];
        $reason = $this->optionalText($data['reason'] ?? null, '状态变更原因', 2000) ?? $defaultReason;
        $this->transitionRfi(
            $projectId,
            $userId,
            $rfiId,
            $targetStatus,
            $reason,
            [
                'response_text' => $data['response_text'] ?? null,
                'response_complete' => $data['response_complete'] ?? false,
                'evidence_reference' => $data['evidence_reference'] ?? null,
            ],
            $canManage
        );
    }

    /**
     * Advances every RFI currently sitting in the step's source status so a
     * whole submitted batch can move with one click. Each record keeps its own
     * transition and audit entry; a record that fails validation is reported
     * instead of blocking the rest of the batch.
     *
     * @return array{advanced: int, failed: list<string>}
     */
    public function bulkAdvanceRfis(
        int $projectId,
        int $userId,
        string $step,
        bool $canManage
    ): array {
        $sourceStatuses = [
            'submit' => 'draft',
            'review' => 'submitted',
        ];
        if (!isset($sourceStatuses[$step])) {
            throw new HttpException('不支持的 RFI 批量操作。', 422);
        }

        $stmt = $this->db->prepare(
            'SELECT rfi_id, rfi_number FROM document_rfis
             WHERE project_id = :project_id AND status = :status
             ORDER BY rfi_id'
        );
        $stmt->execute(['project_id' => $projectId, 'status' => $sourceStatuses[$step]]);
        $rows = $stmt->fetchAll();

        $advanced = 0;
        $failed = [];
        foreach ($rows as $row) {
            try {
                $this->quickRfi(
                    $projectId,
                    $userId,
                    (int) $row['rfi_id'],
                    $step,
                    [],
                    $canManage
                );
                $advanced++;
            } catch (HttpException $exception) {
                if ($exception->statusCode >= 500) {
                    throw $exception;
                }
                $failed[] = $row['rfi_number'] . '：' . $exception->getMessage();
            }
        }

        return ['advanced' => $advanced, 'failed' => $failed];
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
            $primaryIssue = $this->lockIssue($projectId, $primaryIssueId);
            if ((string) $primaryIssue['status'] === 'closed') {
                throw new HttpException('已关闭的问题不能新增 RFI；请先由问题管理员重新打开该问题。', 409);
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

            if ((string) $primaryIssue['status'] === 'open') {
                $this->applyIssueTransition(
                    $projectId,
                    $userId,
                    $primaryIssueId,
                    'in_review',
                    '创建 RFI ' . $rfiNumber . '，问题自动进入处理中。',
                    [],
                    false,
                    true
                );
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

            $updates = ['status = :status', 'updated_at = CURRENT_TIMESTAMP'];
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

            $this->syncIssuesAfterRfiTransition(
                $projectId,
                $userId,
                $rfiId,
                $oldStatus,
                $targetStatus,
                (string) $rfi['rfi_number']
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

<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use ProjectO\Contracts\ProjectSpaceCatalog;
use ProjectO\Export\ExportImage;
use ProjectO\Export\XlsxWorkbook;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Plugin\SidebarNavigationProvider;
use ProjectO\Storage\CloudStorageSettings;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;

final class DocumentManagementPlugin implements PluginInterface, SidebarNavigationProvider
{
    private const ISSUE_STATUS_LABELS = [
        'open' => '待评估',
        'in_review' => '处理中',
        'resolved' => '待关闭',
        'closed' => '已关闭',
    ];
    private const ISSUE_SEVERITY_LABELS = [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'critical' => '紧急',
    ];
    private const RFI_STATUS_LABELS = [
        'draft' => '草稿',
        'submitted' => '已提交',
        'under_review' => '审核中',
        'responded' => '已答复',
        'closed' => '已关闭',
    ];
    private const RFI_PRIORITY_LABELS = [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'urgent' => '紧急',
    ];

    private DocumentService $documents;
    private DocumentImageService $images;
    private ObjectStorageInterface $imageStorage;

    public function __construct(private readonly PluginContext $context)
    {
        $this->documents = new DocumentService($context->db, $this->spaceCatalog());
        $this->images = new DocumentImageService($context->db);
        $this->imageStorage = $context->objectStorage() ?? new R2ObjectStorage($context->db);
    }

    /**
     * Cache-busting stamps for this page's own assets, resolved by the registry
     * so the view never touches the filesystem.
     *
     * @return array<string, string>
     */
    private function locationAssetVersions(): array
    {
        $versions = [];
        foreach (['issue-location.css', 'issue-location.js'] as $file) {
            $versions[$file] = $this->context->assetVersion('document.management', $file);
        }

        return $versions;
    }

    /**
     * The project space catalogue published by project.foundation.
     *
     * Returns null when the contract has no enabled provider, so issue records
     * stay maintainable and the page can explain that space selection is
     * unavailable instead of failing.
     */
    private function spaceCatalog(): ?ProjectSpaceCatalog
    {
        if (!$this->context->hasService(ProjectSpaceCatalog::class)) {
            return null;
        }

        return $this->context->service(ProjectSpaceCatalog::class);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'documents' => $this->documentsIndex(),
            'issues' => $this->issuesIndex(),
            'rfis' => $this->rfisIndex(),
            'settings' => $this->settingsIndex(),
            'issue.create' => $this->createIssue($input),
            'issue.update' => $this->updateIssue($input),
            'issue.transition' => $this->transitionIssue($input),
            'issue.quick' => $this->quickIssue($input),
            'issue.followup.create' => $this->createIssueFollowUp($input),
            'issue.followup.complete' => $this->completeIssueFollowUp($input),
            'rfi.create' => $this->createRfi($input),
            'rfi.update' => $this->updateRfi($input),
            'rfi.transition' => $this->transitionRfi($input),
            'rfi.quick' => $this->quickRfi($input),
            'rfi.followup.create' => $this->createRfiFollowUp($input),
            'rfi.followup.complete' => $this->completeRfiFollowUp($input),
            'settings.save' => $this->saveSettings($input),
            'issue.image.upload' => $this->uploadIssueImage($input),
            'issue.image.delete' => $this->deleteIssueImage($input),
            'rfi.image.upload' => $this->uploadRfiImage($input),
            'rfi.image.delete' => $this->deleteRfiImage($input),
            'document.image.view' => $this->viewDocumentImage($input),
            'issue.export' => $this->exportIssues($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function documentsIndex(): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $issues = $this->documents->listIssues((int) $project['project_id']);
        $rfis = $this->documents->listRfis((int) $project['project_id']);

        // The command board is a projection of the two lists this page already
        // loads (both ordered by updated_at DESC). No new SQL, no new data
        // source: project scope and visibility stay exactly what the service
        // enforces for this user.
        $metrics = $this->documentsHomeMetrics($issues, $rfis);

        return $this->context->view('documents.php', [
            'title' => '文档管理',
            'currentProject' => $project,
            'canManageSettings' => $this->canManageSettings(),
            'canCreate' => Support::hasPermission($this->context->db, 'create_issues'),
            'issueCount' => count($issues),
            'rfiCount' => count($rfis),
            'openIssueCount' => $metrics['pending_issue_count'],
            'openRfiCount' => $metrics['pending_rfi_count'],
            'homeMetrics' => $metrics,
        ]);
    }

    /**
     * Read-only projections for the document overview board.
     *
     * Every figure is a display of the records the page already loads: closed
     * counts, coverage ratios, overdue RFIs, age of waiting items. Nothing is
     * persisted and no business rule is evaluated here, so the board can never
     * claim progress the records do not support.
     *
     * @param array<int, array<string, mixed>> $issues
     * @param array<int, array<string, mixed>> $rfis
     * @return array<string, mixed>
     */
    private function documentsHomeMetrics(array $issues, array $rfis): array
    {
        $issueCounts = ['open' => 0, 'in_review' => 0, 'resolved' => 0, 'closed' => 0];
        $rfiCounts = ['draft' => 0, 'submitted' => 0, 'under_review' => 0, 'responded' => 0, 'closed' => 0];
        $completedFollowUps = 0;
        $closureDays = [];
        $queue = [];
        $recent = [];

        $today = Support::nowLocal('Y-m-d');
        $todayDate = new \DateTimeImmutable($today);
        $weekStart = $todayDate->modify('-6 days')->format('Y-m-d');
        $severityRank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $priorityRank = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

        foreach ($issues as $issue) {
            $status = (string) ($issue['status'] ?? '');
            if (isset($issueCounts[$status])) {
                $issueCounts[$status]++;
            }
            foreach (($issue['follow_ups'] ?? []) as $followUp) {
                if ((int) ($followUp['is_completed'] ?? 0) === 1) {
                    $completedFollowUps++;
                }
            }
            if ($status === 'closed') {
                $day = Support::formatDateTime((string) ($issue['closed_at'] ?? ''), 'Y-m-d');
                if ($day !== '') {
                    $closureDays[$day] = ($closureDays[$day] ?? 0) + 1;
                }
            }

            $updatedDay = Support::formatDateTime((string) ($issue['updated_at'] ?? ''), 'Y-m-d');
            $ageDays = $updatedDay === '' ? 0 : (int) (new \DateTimeImmutable($updatedDay))->diff($todayDate)->days;

            $recent[] = [
                'kind' => 'issue',
                'number' => (string) ($issue['issue_number'] ?: '#' . $issue['issue_id']),
                'summary' => (string) $issue['description'],
                'status' => $status,
                'updated_at' => (string) $issue['updated_at'],
            ];

            if ($status === 'open' || $status === 'in_review' || $status === 'resolved') {
                $severity = (string) ($issue['severity'] ?? 'medium');
                $queue[] = [
                    'kind' => 'issue',
                    'number' => (string) ($issue['issue_number'] ?: '#' . $issue['issue_id']),
                    'title' => (string) $issue['description'],
                    'status' => $status,
                    'urgency' => $severity,
                    'urgency_label' => self::ISSUE_SEVERITY_LABELS[$severity] ?? $severity,
                    'rank' => $severityRank[$severity] ?? 2,
                    'overdue' => false,
                    'overdue_days' => 0,
                    'due_date' => '',
                    'age_days' => $ageDays,
                    'updated_at' => (string) $issue['updated_at'],
                    'href' => '?page=issues',
                ];
            }
        }

        $overdueRfis = 0;
        foreach ($rfis as $rfi) {
            $status = (string) ($rfi['status'] ?? '');
            if (isset($rfiCounts[$status])) {
                $rfiCounts[$status]++;
            }
            foreach (($rfi['follow_ups'] ?? []) as $followUp) {
                if ((int) ($followUp['is_completed'] ?? 0) === 1) {
                    $completedFollowUps++;
                }
            }

            $updatedDay = Support::formatDateTime((string) ($rfi['updated_at'] ?? ''), 'Y-m-d');
            $ageDays = $updatedDay === '' ? 0 : (int) (new \DateTimeImmutable($updatedDay))->diff($todayDate)->days;
            $dueDate = trim((string) ($rfi['due_date'] ?? ''));
            $dueDay = $dueDate === '' ? '' : substr($dueDate, 0, 10);
            $overdue = $status !== 'closed' && $dueDay !== '' && $dueDay < $today;
            $overdueDays = $overdue ? (int) (new \DateTimeImmutable($dueDay))->diff($todayDate)->days : 0;
            if ($overdue) {
                $overdueRfis++;
            }

            $recent[] = [
                'kind' => 'rfi',
                'number' => (string) ($rfi['rfi_number'] ?: '#' . $rfi['rfi_id']),
                'summary' => (string) $rfi['description'],
                'status' => $status,
                'updated_at' => (string) $rfi['updated_at'],
            ];

            if ($status !== 'closed') {
                $priority = (string) ($rfi['priority'] ?? 'medium');
                $queue[] = [
                    'kind' => 'rfi',
                    'number' => (string) ($rfi['rfi_number'] ?: '#' . $rfi['rfi_id']),
                    'title' => (string) $rfi['description'],
                    'status' => $status,
                    'urgency' => $priority,
                    'urgency_label' => self::RFI_PRIORITY_LABELS[$priority] ?? $priority,
                    'rank' => $priorityRank[$priority] ?? 2,
                    'overdue' => $overdue,
                    'overdue_days' => $overdueDays,
                    'due_date' => $dueDay,
                    'age_days' => $ageDays,
                    'updated_at' => (string) $rfi['updated_at'],
                    'href' => '?page=rfis',
                ];
            }
        }

        // Overdue first, then declared urgency, then the record that has waited
        // longest — the board answers "what should I touch next", not "what is new".
        usort($queue, static function (array $a, array $b): int {
            $byOverdue = (int) $b['overdue'] <=> (int) $a['overdue'];
            if ($byOverdue !== 0) {
                return $byOverdue;
            }
            $byRank = $b['rank'] <=> $a['rank'];
            if ($byRank !== 0) {
                return $byRank;
            }

            return strcmp((string) $a['updated_at'], (string) $b['updated_at']);
        });
        $queue = array_slice($queue, 0, 6);

        usort($recent, static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));

        $closedIssues = $issueCounts['closed'];
        $resolvedIssues = $issueCounts['resolved'];
        $answeredRfis = $rfiCounts['responded'] + $rfiCounts['closed'];

        $issueTotal = count($issues);
        $rfiTotal = count($rfis);
        $pendingIssueCount = $issueCounts['open'] + $issueCounts['in_review'];
        $pendingRfiCount = $rfiTotal - $rfiCounts['closed'];
        $completionTotal = $issueTotal + $rfiTotal;
        $completionClosed = $closedIssues + $rfiCounts['closed'];
        $completionPercent = $completionTotal > 0 ? (int) round($completionClosed / $completionTotal * 100) : 0;

        $goals = [
            [
                'key' => 'issues',
                'label' => '问题闭环',
                'hint' => '把问题推进到已关闭',
                'done' => $closedIssues,
                'total' => $issueTotal,
                'remaining' => max(0, $issueTotal - $closedIssues),
                'href' => '?page=issues',
            ],
            [
                'key' => 'rfis',
                'label' => 'RFI 应答',
                'hint' => '提交并答复 RFI',
                'done' => $answeredRfis,
                'total' => $rfiTotal,
                'remaining' => max(0, $rfiTotal - $answeredRfis),
                'href' => '?page=rfis',
            ],
            [
                'key' => 'verify',
                'label' => '复核收口',
                'hint' => '待关闭问题完成验证后关闭',
                'done' => $closedIssues,
                'total' => $closedIssues + $resolvedIssues,
                'remaining' => $resolvedIssues,
                'href' => '?page=issues',
            ],
        ];

        $bestDayClosures = $closureDays === [] ? 0 : max($closureDays);
        $cleared = ($issueTotal + $rfiTotal) > 0 && $closedIssues === $issueTotal && $rfiCounts['closed'] === $rfiTotal;
        $milestones = [
            ['key' => 'first', 'name' => '首次闭环', 'rule' => '至少 1 条问题闭环', 'done' => $closedIssues, 'target' => 1],
            ['key' => 'five', 'name' => '累计闭环', 'rule' => '累计 5 条问题闭环', 'done' => $closedIssues, 'target' => 5],
            ['key' => 'answer', 'name' => 'RFI 答复', 'rule' => '累计答复 3 份 RFI', 'done' => $answeredRfis, 'target' => 3],
            ['key' => 'clean', 'name' => '全部结清', 'rule' => '所有问题与 RFI 均已关闭', 'done' => $cleared ? 1 : 0, 'target' => 1],
            ['key' => 'burst', 'name' => '单日闭环', 'rule' => '同一天闭环 2 条问题', 'done' => $bestDayClosures, 'target' => 2],
        ];
        foreach ($milestones as &$milestone) {
            $milestone['reached'] = $milestone['done'] >= $milestone['target'];
        }
        unset($milestone);

        $weekClosures = 0;
        foreach ($closureDays as $day => $count) {
            if ($day >= $weekStart) {
                $weekClosures += $count;
            }
        }

        return [
            'completion' => [
                'closed' => $completionClosed,
                'total' => $completionTotal,
                'percent' => $completionPercent,
            ],
            'week_closures' => $weekClosures,
            'best_day_closures' => $bestDayClosures,
            'completed_follow_ups' => $completedFollowUps,
            'issue' => ['total' => $issueTotal, 'counts' => $issueCounts],
            'rfi' => [
                'total' => $rfiTotal,
                'counts' => $rfiCounts,
                'answered' => $answeredRfis,
                'overdue' => $overdueRfis,
            ],
            'pending_issue_count' => $pendingIssueCount,
            'pending_rfi_count' => $pendingRfiCount,
            'goals' => $goals,
            'milestones' => $milestones,
            'queue' => $queue,
            'recent' => array_slice($recent, 0, 6),
        ];
    }

    private function issuesIndex(): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $canManage = Support::hasPermission($this->context->db, 'manage_issues');
        $issues = $this->documents->listIssues((int) $project['project_id']);
        $linkedRfis = $this->documents->issueLinkedRfis((int) $project['project_id']);
        foreach ($issues as &$issue) {
            $issue['available_transitions'] = $this->documents->availableIssueTransitions(
                (string) $issue['status'],
                $canManage
            );
            $issue['linked_rfis'] = $linkedRfis[(int) $issue['issue_id']] ?? [];
        }
        unset($issue);
        $imageStorageReady = $this->imageStorage->isConfigured();
        $images = $this->images->issueImagesForProject((int) $project['project_id']);
        foreach ($issues as &$issue) {
            $issue['images'] = $images[(int) $issue['issue_id']] ?? [];
            if ($imageStorageReady) {
                foreach ($issue['images'] as &$image) {
                    $image['url'] = '?page=document-image&type=issue&issue_id='
                        . (int) $issue['issue_id'] . '&image_id=' . (int) $image['image_id'];
                }
                unset($image);
            }
        }
        unset($issue);

        return $this->context->view('issues.php', [
            'title' => '问题管理',
            'currentProject' => $project,
            'canManageSettings' => $this->canManageSettings(),
            'issues' => $issues,
            'locationCatalog' => $this->documents->issueLocationCatalog((int) $project['project_id']),
            'spaceSelectionReady' => $this->spaceCatalog() !== null,
            'moduleAssetVersions' => $this->locationAssetVersions(),
            'canCreate' => Support::hasPermission($this->context->db, 'create_issues'),
            'canEdit' => $this->canEdit(),
            'canManage' => $canManage,
            'imageStorageReady' => $imageStorageReady,
        ]);
    }

    private function rfisIndex(): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $canManage = Support::hasPermission($this->context->db, 'manage_issues');
        $rfis = $this->documents->listRfis($projectId);
        foreach ($rfis as &$rfi) {
            $rfi['available_transitions'] = $this->documents->availableRfiTransitions(
                (string) $rfi['status'],
                $canManage
            );
        }
        unset($rfi);
        $imageStorageReady = $this->imageStorage->isConfigured();
        $images = $this->images->rfiImagesForProject($projectId);
        foreach ($rfis as &$rfi) {
            $rfi['images'] = $images[(int) $rfi['rfi_id']] ?? [];
            if ($imageStorageReady) {
                foreach ($rfi['images'] as &$image) {
                    $image['url'] = '?page=document-image&type=rfi&rfi_id='
                        . (int) $rfi['rfi_id'] . '&image_id=' . (int) $image['image_id'];
                }
                unset($image);
            }
        }
        unset($rfi);

        return $this->context->view('rfis.php', [
            'title' => 'RFI 管理',
            'currentProject' => $project,
            'canManageSettings' => $this->canManageSettings(),
            'rfis' => $rfis,
            'issues' => $this->documents->listIssues($projectId),
            'users' => $this->documents->activeUsers(),
            'canCreate' => Support::hasPermission($this->context->db, 'create_issues'),
            'canEdit' => $this->canEdit(),
            'canManage' => $canManage,
            'imageStorageReady' => $imageStorageReady,
        ]);
    }

    private function createIssue(array $input): PluginResponse
    {
        $this->requireDocumentAccess();
        Support::requirePermission($this->context->db, 'create_issues');
        $project = $this->context->requireCurrentProject();
        $issueId = $this->documents->createIssue(
            (int) $project['project_id'],
            $this->userId(),
            [
                'description' => $input['description'] ?? null,
                'severity' => $input['severity'] ?? 'medium',
                'location' => $input['location'] ?? null,
                'location_note' => $input['location_note'] ?? null,
            ]
        );

        Support::flash('问题已登记。');
        return PluginResponse::redirect('?page=issues#issue-' . $issueId);
    }

    private function transitionIssue(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $issueId = $this->requiredId($input, 'issue_id', '问题');
        $targetStatus = $this->requiredString($input, 'status', '问题状态');
        $reason = $this->requiredString($input, 'reason', '状态变更原因');
        try {
            $this->documents->transitionIssue(
                (int) $project['project_id'],
                $this->userId(),
                $issueId,
                $targetStatus,
                $reason,
                [
                    'resolution_result' => $input['resolution_result'] ?? null,
                    'verification_status' => $input['verification_status'] ?? null,
                    'verification_note' => $input['verification_note'] ?? null,
                    'evidence_reference' => $input['evidence_reference'] ?? null,
                ],
                Support::hasPermission($this->context->db, 'manage_issues')
            );
        } catch (HttpException $exception) {
            return $this->transitionFailure($exception, 'issues', $issueId, 'issue-');
        }

        Support::flash('问题状态已更新。');
        return PluginResponse::redirect('?page=issues#issue-' . $issueId);
    }

    private function quickIssue(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $issueId = $this->requiredId($input, 'issue_id', '问题');
        $step = $this->requiredString($input, 'step', '快捷操作');
        $flashes = [
            'start' => '问题已转处理中。',
            'resolve' => '问题已转待关闭，请补齐现场验证与证据后关闭。',
            'close' => '问题已关闭。',
        ];
        try {
            $this->documents->quickIssue(
                (int) $project['project_id'],
                $this->userId(),
                $issueId,
                $step,
                [
                    'reason' => $input['reason'] ?? null,
                    'resolution_result' => $input['resolution_result'] ?? null,
                    'verification_status' => $input['verification_status'] ?? null,
                    'verification_note' => $input['verification_note'] ?? null,
                    'evidence_reference' => $input['evidence_reference'] ?? null,
                ],
                Support::hasPermission($this->context->db, 'manage_issues')
            );
        } catch (HttpException $exception) {
            return $this->transitionFailure($exception, 'issues', $issueId, 'issue-');
        }

        Support::flash($flashes[$step] ?? '问题状态已更新。');
        return PluginResponse::redirect('?page=issues#issue-' . $issueId);
    }

    private function updateIssue(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $this->documents->updateIssue(
            (int) $project['project_id'],
            $this->requiredId($input, 'issue_id', '问题'),
            [
                'description' => $input['description'] ?? null,
                'severity' => $input['severity'] ?? null,
                'location' => $input['location'] ?? null,
                'location_note' => $input['location_note'] ?? null,
            ]
        );

        Support::flash('问题信息已更新。');
        return PluginResponse::redirect('?page=issues');
    }

    private function createIssueFollowUp(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $issueId = $this->requiredId($input, 'issue_id', '问题');
        $this->documents->addIssueFollowUp(
            (int) $project['project_id'],
            $this->userId(),
            $issueId,
            $this->requiredString($input, 'title', '后续事项')
        );

        Support::flash('问题后续事项已添加。');
        return PluginResponse::redirect('?page=issues#issue-' . $issueId);
    }

    private function completeIssueFollowUp(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $this->documents->completeIssueFollowUp(
            (int) $project['project_id'],
            $this->userId(),
            $this->requiredId($input, 'follow_up_id', '后续事项'),
            $this->requiredString($input, 'reason', '状态变更原因'),
            $this->optionalString($input, 'evidence_reference', '证据档案引用')
        );

        Support::flash('问题后续事项已完成。');
        return PluginResponse::redirect('?page=issues');
    }

    private function createRfi(array $input): PluginResponse
    {
        $this->requireDocumentAccess();
        Support::requirePermission($this->context->db, 'create_issues');
        $project = $this->context->requireCurrentProject();
        $relatedIssueIds = $input['related_issue_ids'] ?? [];
        if (!is_array($relatedIssueIds)) {
            throw new HttpException('关联问题格式无效。', 422);
        }

        $rfiId = $this->documents->createRfi(
            (int) $project['project_id'],
            $this->userId(),
            [
                'primary_issue_id' => $input['primary_issue_id'] ?? null,
                'related_issue_ids' => $relatedIssueIds,
                'assigned_to' => $input['assigned_to'] ?? null,
                'priority' => $input['priority'] ?? 'medium',
                'due_date' => $input['due_date'] ?? null,
                'description' => $input['description'] ?? null,
                'evidence_required' => $this->checkbox($input, 'evidence_required'),
            ]
        );

        Support::flash('RFI 草稿已创建，可在详情中提交。');
        return PluginResponse::redirect('?page=rfis#rfi-' . $rfiId);
    }

    private function transitionRfi(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $rfiId = $this->requiredId($input, 'rfi_id', 'RFI');
        $targetStatus = $this->requiredString($input, 'status', 'RFI 状态');
        $reason = $this->requiredString($input, 'reason', '状态变更原因');
        try {
            $this->documents->transitionRfi(
                (int) $project['project_id'],
                $this->userId(),
                $rfiId,
                $targetStatus,
                $reason,
                [
                    'response_text' => $input['response_text'] ?? null,
                    'response_complete' => $this->checkbox($input, 'response_complete'),
                    'evidence_reference' => $input['evidence_reference'] ?? null,
                ],
                Support::hasPermission($this->context->db, 'manage_issues')
            );
        } catch (HttpException $exception) {
            return $this->transitionFailure($exception, 'rfis', $rfiId, 'rfi-');
        }

        $message = $targetStatus === 'responded'
            ? 'RFI 已答复；请重新评估主问题，系统未自动改变问题状态。'
            : 'RFI 状态已更新。';
        Support::flash($message);
        return PluginResponse::redirect('?page=rfis#rfi-' . $rfiId);
    }

    private function quickRfi(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $rfiId = $this->requiredId($input, 'rfi_id', 'RFI');
        $step = $this->requiredString($input, 'step', '快捷操作');
        $flashes = [
            'submit' => 'RFI 已提交。',
            'review' => 'RFI 已进入审核中。',
            'respond' => 'RFI 已答复，请评估主问题是否可收口。',
            'close' => 'RFI 已关闭；若这是该问题最后一份 RFI，问题会自动转为待关闭。',
        ];
        try {
            $this->documents->quickRfi(
                (int) $project['project_id'],
                $this->userId(),
                $rfiId,
                $step,
                [
                    'reason' => $input['reason'] ?? null,
                    'response_text' => $input['response_text'] ?? null,
                    'response_complete' => $this->checkbox($input, 'response_complete'),
                    'evidence_reference' => $input['evidence_reference'] ?? null,
                ],
                Support::hasPermission($this->context->db, 'manage_issues')
            );
        } catch (HttpException $exception) {
            return $this->transitionFailure($exception, 'rfis', $rfiId, 'rfi-');
        }

        Support::flash($flashes[$step] ?? 'RFI 状态已更新。');
        return PluginResponse::redirect('?page=rfis#rfi-' . $rfiId);
    }

    private function transitionFailure(
        HttpException $exception,
        string $page,
        int $recordId,
        string $anchorPrefix
    ): PluginResponse {
        if ($exception->statusCode >= 500) {
            throw $exception;
        }
        Support::flash($exception->getMessage(), 'error');
        return PluginResponse::redirect('?page=' . $page . '#' . $anchorPrefix . $recordId);
    }

    private function updateRfi(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $data = [
            'assigned_to' => $input['assigned_to'] ?? null,
            'priority' => $input['priority'] ?? null,
            'due_date' => $input['due_date'] ?? null,
            'description' => $input['description'] ?? null,
            'evidence_required' => $this->checkbox($input, 'evidence_required'),
        ];
        if (array_key_exists('primary_issue_id', $input)) {
            $data['primary_issue_id'] = $input['primary_issue_id'];
        }
        if (array_key_exists('related_issue_ids', $input)) {
            if (!is_array($input['related_issue_ids'])) {
                throw new HttpException('关联问题格式无效。', 422);
            }
            $data['related_issue_ids'] = $input['related_issue_ids'];
        }
        $this->documents->updateRfi(
            (int) $project['project_id'],
            $this->requiredId($input, 'rfi_id', 'RFI'),
            $data
        );

        Support::flash('RFI 信息已更新。');
        return PluginResponse::redirect('?page=rfis');
    }

    private function createRfiFollowUp(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $rfiId = $this->requiredId($input, 'rfi_id', 'RFI');
        $this->documents->addRfiFollowUp(
            (int) $project['project_id'],
            $this->userId(),
            $rfiId,
            $this->requiredString($input, 'title', '后续事项')
        );

        Support::flash('RFI 后续事项已添加。');
        return PluginResponse::redirect('?page=rfis#rfi-' . $rfiId);
    }

    private function completeRfiFollowUp(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $this->documents->completeRfiFollowUp(
            (int) $project['project_id'],
            $this->userId(),
            $this->requiredId($input, 'follow_up_id', '后续事项'),
            $this->requiredString($input, 'reason', '状态变更原因'),
            $this->optionalString($input, 'evidence_reference', '证据档案引用')
        );

        Support::flash('RFI 后续事项已完成。');
        return PluginResponse::redirect('?page=rfis');
    }

    private function settingsIndex(): PluginResponse
    {
        $this->requireSettingsAccess();
        $storage = new R2ObjectStorage($this->context->db);
        $config = (new CloudStorageSettings($this->context->db))->read();

        return $this->context->view('settings.php', [
            'title' => '系统设置',
            'canManageSettings' => true,
            'storageStatus' => $storage->configurationStatus(),
            'imageCacheStatus' => $storage->imageCacheStatus(),
            'storageConfig' => [
                'exists' => $config['exists'],
                'enabled' => $config['enabled'],
                'account_id' => $config['account_id'],
                'bucket' => $config['bucket'],
                'access_key_id' => $config['access_key_id'],
                'secret_configured' => $config['secret_access_key'] !== '',
            ],
        ]);
    }

    private function saveSettings(array $input): PluginResponse
    {
        $this->requireSettingsAccess();
        $enabled = array_key_exists('enabled', $input)
            && is_string($input['enabled'])
            && $input['enabled'] === '1';
        (new CloudStorageSettings($this->context->db))->save($input);
        Support::audit(
            $this->context->db,
            'document.settings.storage.save',
            'Updated image object storage configuration (enabled=' . ($enabled ? '1' : '0') . ').'
        );
        Support::flash('图片存储配置已保存。');

        return PluginResponse::redirect('?page=document-settings');
    }

    private function canManageSettings(): bool
    {
        return Support::hasPermission($this->context->db, 'manage_document_settings')
            || Support::hasPermission($this->context->db, 'manage_platform');
    }

    /**
     * V2.1 project sidebar: 工程协同 entries (文档 / 问题 / RFI). Gates mirror
     * requireDocumentAccess()/requireSettingsAccess(); the in-page module nav
     * keeps its own markup, this only feeds the global sidebar. 文档设置 is
     * platform configuration and therefore lives in 基础设置.
     */
    public function sidebarItems(): array
    {
        $items = [];
        if (
            Support::hasPermission($this->context->db, 'view_projects')
            && Support::hasPermission($this->context->db, 'view_issues')
        ) {
            $items[] = ['group' => 'collab', 'page' => 'documents', 'label' => '文档管理', 'icon' => '▣', 'order' => 10];
            $items[] = ['group' => 'collab', 'page' => 'issues', 'label' => '问题管理', 'icon' => '⚑', 'order' => 20];
            $items[] = ['group' => 'collab', 'page' => 'rfis', 'label' => 'RFI 管理', 'icon' => '↗', 'order' => 30];
        }
        if ($this->canManageSettings()) {
            $items[] = ['group' => 'settings', 'page' => 'document-settings', 'label' => '文档设置', 'icon' => '⚙', 'order' => 20];
        }

        return $items;
    }

    private function requireSettingsAccess(): void
    {
        if (!$this->canManageSettings()) {
            throw new HttpException('您没有权限执行此操作。', 403);
        }
    }

    private function uploadIssueImage(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $issueId = $this->requiredId($input, 'issue_id', '问题');
        $this->images->requireIssue($projectId, $issueId);
        $this->requireImageStorage();
        $file = $this->uploadedFile($input);
        $this->images->addIssueImage(
            $projectId,
            $issueId,
            $this->userId(),
            $file['path'],
            $file['name'],
            $this->imageStorage
        );
        Support::audit(
            $this->context->db,
            'document.issue.image.upload',
            'Added an image to issue ' . $issueId . '.'
        );
        Support::flash('问题图片已上传至私有对象存储。');

        return PluginResponse::redirect('?page=issues');
    }

    private function deleteIssueImage(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $issueId = $this->requiredId($input, 'issue_id', '问题');
        $imageId = $this->requiredId($input, 'image_id', '图片');
        $this->images->requireIssue($projectId, $issueId);
        $this->requireImageStorage();
        $this->images->deleteIssueImage($projectId, $issueId, $imageId, $this->imageStorage);
        Support::audit(
            $this->context->db,
            'document.issue.image.delete',
            'Removed image ' . $imageId . ' from issue ' . $issueId . '.'
        );
        Support::flash('问题图片已删除。');

        return PluginResponse::redirect('?page=issues');
    }

    private function uploadRfiImage(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $rfiId = $this->requiredId($input, 'rfi_id', 'RFI');
        $this->images->requireRfi($projectId, $rfiId);
        $this->requireImageStorage();
        $file = $this->uploadedFile($input);
        $this->images->addRfiImage(
            $projectId,
            $rfiId,
            $this->userId(),
            $file['path'],
            $file['name'],
            $this->imageStorage
        );
        Support::audit(
            $this->context->db,
            'document.rfi.image.upload',
            'Added an image to RFI ' . $rfiId . '.'
        );
        Support::flash('RFI 图片已上传至私有对象存储。');

        return PluginResponse::redirect('?page=rfis');
    }

    private function deleteRfiImage(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $rfiId = $this->requiredId($input, 'rfi_id', 'RFI');
        $imageId = $this->requiredId($input, 'image_id', '图片');
        $this->images->requireRfi($projectId, $rfiId);
        $this->requireImageStorage();
        $this->images->deleteRfiImage($projectId, $rfiId, $imageId, $this->imageStorage);
        Support::audit(
            $this->context->db,
            'document.rfi.image.delete',
            'Removed image ' . $imageId . ' from RFI ' . $rfiId . '.'
        );
        Support::flash('RFI 图片已删除。');

        return PluginResponse::redirect('?page=rfis');
    }

    private function requireImageStorage(): void
    {
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException(
                '尚未配置图片存储：请在文件模块“系统设置”完成设置，或核对部署环境变量与 AWS SDK 安装。',
                503
            );
        }
    }

    /**
     * @return array{path: string, name: string}
     */
    private function uploadedFile(array $input): array
    {
        $files = $input['_files'] ?? null;
        $file = is_array($files) ? ($files['image'] ?? null) : null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException('请选择一张有效的图片后重试。', 422);
        }
        $temporaryPath = $file['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new HttpException('上传文件校验失败。', 422);
        }
        $originalName = $file['name'] ?? '';
        if (!is_string($originalName)) {
            $originalName = '';
        }

        return ['path' => $temporaryPath, 'name' => $originalName];
    }

    private function viewDocumentImage(array $input): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $type = $input['type'] ?? '';
        if (!is_string($type) || !in_array($type, ['issue', 'rfi'], true)) {
            throw new HttpException('图片类型无效。', 422);
        }
        $imageId = $this->requiredId($input, 'image_id', '图片');
        if ($type === 'issue') {
            $ownerId = $this->requiredId($input, 'issue_id', '问题');
            $this->images->requireIssue($projectId, $ownerId);
            $label = '问题图片';
        } else {
            $ownerId = $this->requiredId($input, 'rfi_id', 'RFI');
            $this->images->requireRfi($projectId, $ownerId);
            $label = 'RFI图片';
        }
        $this->requireImageStorage();
        $image = $this->images->requireImage($type, $projectId, $ownerId, $imageId);
        $contentType = (string) $image['content_type'];
        $extension = ImageUpload::EXTENSIONS[$contentType] ?? 'img';
        // Same contract as the sample images: the key embeds 16 random bytes and
        // is never rewritten, so it is a faithful validator for the bytes the
        // browser cached. Answering 304 here avoids the remote read entirely.
        $etag = '"' . hash('sha256', (string) $image['object_key']) . '"';
        // This route only reads the session; release the lock before any slow
        // remote work so a page full of attachments cannot queue navigation.
        Support::csrfToken();
        session_write_close();
        if (self::etagMatches($etag)) {
            return PluginResponse::notModified([
                'ETag' => $etag,
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ]);
        }
        try {
            $body = $this->imageStorage->read((string) $image['object_key']);
        } catch (Throwable $exception) {
            error_log('Unable to read document image ' . $imageId . ': ' . get_class($exception));
            throw new HttpException('图片读取失败，请稍后重试。', 502);
        }

        return PluginResponse::inline(
            $label . '-' . $ownerId . '-' . $imageId . '.' . $extension,
            $contentType,
            $body,
            $etag
        );
    }

    /**
     * Whether the browser's conditional request already holds these exact bytes.
     * Handles a comma-separated If-None-Match list, "*" and weak validators.
     */
    private static function etagMatches(string $etag): bool
    {
        $header = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        if (!is_string($header) || trim($header) === '') {
            return false;
        }
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*') {
                return true;
            }
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }
            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    private function exportIssues(array $input): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $issues = $this->documents->listIssues($projectId);
        $imageStorageReady = $this->imageStorage->isConfigured();
        $imagesByIssue = $this->images->issueImagesForProject($projectId);
        foreach ($issues as &$issue) {
            $issue['images'] = $imagesByIssue[(int) $issue['issue_id']] ?? [];
            if ($imageStorageReady) {
                foreach ($issue['images'] as &$image) {
                    $image['url'] = $this->imageStorage->signedReadUrl(
                        (string) $image['object_key'],
                        (string) $image['content_type']
                    );
                }
                unset($image);
            }
        }
        unset($issue);

        $headers = [
            '问题编号', '问题描述', '严重程度', '状态', '位置', '图片',
            '登记人', '登记时间', '更新时间', '处理结果', '后续事项', '关闭时间',
        ];
        $imageColumn = 5;
        $images = [];
        $rows = [];
        $statusCounts = ['open' => 0, 'in_review' => 0, 'resolved' => 0, 'closed' => 0];
        $firstDataRow = 7;
        foreach ($issues as $issue) {
            $rowNumber = $firstDataRow + count($rows);
            $status = (string) $issue['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $followUps = $issue['follow_ups'] ?? [];
            $completedFollowUps = count(array_filter(
                $followUps,
                static fn (array $followUp): bool => (int) $followUp['is_completed'] === 1
            ));
            $rows[] = [
                self::exportText($issue['issue_number'] ?? null),
                self::exportText($issue['description'] ?? null),
                self::ISSUE_SEVERITY_LABELS[$issue['severity']] ?? (string) $issue['severity'],
                self::ISSUE_STATUS_LABELS[$status] ?? $status,
                self::exportText($issue['location_display'] ?? null),
                $this->exportIssueImageCell($issue, $images, $rowNumber, $imageColumn),
                self::exportText($issue['reported_by_name'] ?? null),
                Support::formatDateTime($issue['reported_at'] ?? null),
                Support::formatDateTime($issue['updated_at'] ?? null),
                self::exportText($issue['resolution_result'] ?? null),
                $followUps === [] ? '-' : $completedFollowUps . ' / ' . count($followUps),
                Support::formatDateTime($issue['closed_at'] ?? null),
            ];
        }

        $exportDay = Support::nowLocal('Ymd');
        $exportSequence = Support::nextDailySequence(
            $this->context->db,
            'document.issue.export_sequence',
            $exportDay
        );
        $body = XlsxWorkbook::build('问题信息追踪表', $headers, $rows, [
            'title' => '📦 ' . $project['project_name'] . '问题信息追踪表',
            'meta' => [
                '导出时间: ' . Support::nowLocal('Y-m-d H:i:s'),
                '记录总数: ' . count($rows),
                '内容统计: 待评估 ' . $statusCounts['open']
                    . '，处理中 ' . $statusCounts['in_review']
                    . '，待关闭 ' . $statusCounts['resolved']
                    . '，已关闭 ' . $statusCounts['closed'],
            ],
            'images' => $images,
            'fixedWidths' => [$imageColumn => 15.0],
        ]);
        Support::audit(
            $this->context->db,
            'document.issue.export',
            'Exported ' . count($rows) . ' issue rows for project ' . $projectId . '.'
        );

        return PluginResponse::download(
            '问题信息追踪表_' . $exportDay . '_' . $exportSequence . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $body
        );
    }

    /**
     * @param array<string, mixed> $issue
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink?: string}> $images
     */
    private function exportIssueImageCell(
        array $issue,
        array &$images,
        int $rowNumber,
        int $imageColumn
    ): string {
        if (!$this->imageStorage->isConfigured()) {
            return '-';
        }
        foreach ($issue['images'] ?? [] as $image) {
            if (!is_array($image) || !isset($image['url']) || !is_string($image['url']) || $image['url'] === '') {
                continue;
            }
            $payload = ExportImage::fetch($image['url'], (string) ($image['content_type'] ?? ''));
            if ($payload === null) {
                continue;
            }
            $images[] = [
                'row' => $rowNumber,
                'column' => $imageColumn,
                'bytes' => $payload['bytes'],
                'extension' => $payload['extension'],
                'hyperlink' => $this->issueImageProxyUrl(
                    (int) $issue['issue_id'],
                    (int) $image['image_id']
                ),
            ];

            return '';
        }

        return '-';
    }

    private function issueImageProxyUrl(int $issueId, int $imageId): string
    {
        $query = '?page=document-image&type=issue&issue_id=' . $issueId . '&image_id=' . $imageId;
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return $query;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $scheme . '://' . $host . $base . '/' . $query;
    }

    private static function exportText(mixed $value): string
    {
        $text = $value === null ? '' : trim((string) $value);

        return $text === '' ? '-' : $text;
    }

    private function requireEdit(): void
    {
        $this->requireDocumentAccess();
        if (!$this->canEdit()) {
            throw new HttpException('您没有权限执行此操作。', 403);
        }
    }

    private function requireDocumentAccess(): void
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_issues');
    }

    private function canEdit(): bool
    {
        return Support::hasPermission($this->context->db, 'edit_issues')
            || Support::hasPermission($this->context->db, 'manage_issues');
    }

    private function userId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            throw new HttpException('当前登录用户无效。', 401);
        }

        return $userId;
    }

    private function requiredId(array $input, string $key, string $label): int
    {
        $value = $input[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new HttpException($label . '编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($label . '编号无效。', 422);
        }

        return $id;
    }

    private function requiredString(array $input, string $key, string $label): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new HttpException($label . '不能为空。', 422);
        }

        return trim($value);
    }

    private function optionalString(array $input, string $key, string $label): ?string
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function checkbox(array $input, string $key): bool
    {
        $value = $input[$key] ?? '0';
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new HttpException('表单选项格式无效。', 422);
    }
}

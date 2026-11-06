<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\CloudStorageSettings;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;

final class DocumentManagementPlugin implements PluginInterface
{
    private DocumentService $documents;

    public function __construct(private readonly PluginContext $context)
    {
        $this->documents = new DocumentService($context->db);
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
            'issue.followup.create' => $this->createIssueFollowUp($input),
            'issue.followup.complete' => $this->completeIssueFollowUp($input),
            'rfi.create' => $this->createRfi($input),
            'rfi.update' => $this->updateRfi($input),
            'rfi.transition' => $this->transitionRfi($input),
            'rfi.followup.create' => $this->createRfiFollowUp($input),
            'rfi.followup.complete' => $this->completeRfiFollowUp($input),
            'settings.save' => $this->saveSettings($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function documentsIndex(): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $issues = $this->documents->listIssues((int) $project['project_id']);
        $rfis = $this->documents->listRfis((int) $project['project_id']);

        return $this->context->view('documents.php', [
            'title' => '文档管理',
            'currentProject' => $project,
            'canManageSettings' => $this->canManageSettings(),
            'issueCount' => count($issues),
            'rfiCount' => count($rfis),
            'openIssueCount' => count(array_filter(
                $issues,
                static fn (array $issue): bool => in_array($issue['status'], ['open', 'in_review'], true)
            )),
            'openRfiCount' => count(array_filter(
                $rfis,
                static fn (array $rfi): bool => $rfi['status'] !== 'closed'
            )),
        ]);
    }

    private function issuesIndex(): PluginResponse
    {
        $this->requireDocumentAccess();
        $project = $this->context->requireCurrentProject();
        $canManage = Support::hasPermission($this->context->db, 'manage_issues');
        $issues = $this->documents->listIssues((int) $project['project_id']);
        foreach ($issues as &$issue) {
            $issue['available_transitions'] = $this->documents->availableIssueTransitions(
                (string) $issue['status'],
                $canManage
            );
        }
        unset($issue);

        return $this->context->view('issues.php', [
            'title' => '问题管理',
            'currentProject' => $project,
            'canManageSettings' => $this->canManageSettings(),
            'issues' => $issues,
            'canCreate' => Support::hasPermission($this->context->db, 'create_issues'),
            'canEdit' => $this->canEdit(),
            'canManage' => $canManage,
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
            ]
        );

        Support::flash('问题已登记。');
        return PluginResponse::redirect('?page=issues#issue-' . $issueId);
    }

    private function transitionIssue(array $input): PluginResponse
    {
        $this->requireEdit();
        $project = $this->context->requireCurrentProject();
        $targetStatus = $this->requiredString($input, 'status', '问题状态');
        $this->documents->transitionIssue(
            (int) $project['project_id'],
            $this->userId(),
            $this->requiredId($input, 'issue_id', '问题'),
            $targetStatus,
            $this->requiredString($input, 'reason', '状态变更原因'),
            [
                'resolution_result' => $input['resolution_result'] ?? null,
                'verification_status' => $input['verification_status'] ?? null,
                'verification_note' => $input['verification_note'] ?? null,
                'evidence_reference' => $input['evidence_reference'] ?? null,
            ],
            Support::hasPermission($this->context->db, 'manage_issues')
        );

        Support::flash('问题状态已更新。');
        return PluginResponse::redirect('?page=issues');
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
        $targetStatus = $this->requiredString($input, 'status', 'RFI 状态');
        $this->documents->transitionRfi(
            (int) $project['project_id'],
            $this->userId(),
            $this->requiredId($input, 'rfi_id', 'RFI'),
            $targetStatus,
            $this->requiredString($input, 'reason', '状态变更原因'),
            [
                'response_text' => $input['response_text'] ?? null,
                'response_complete' => $this->checkbox($input, 'response_complete'),
                'evidence_reference' => $input['evidence_reference'] ?? null,
            ],
            Support::hasPermission($this->context->db, 'manage_issues')
        );

        $message = $targetStatus === 'responded'
            ? 'RFI 已答复；请重新评估主问题，系统未自动改变问题状态。'
            : 'RFI 状态已更新。';
        Support::flash($message);
        return PluginResponse::redirect('?page=rfis');
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

    private function requireSettingsAccess(): void
    {
        if (!$this->canManageSettings()) {
            throw new HttpException('您没有权限执行此操作。', 403);
        }
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

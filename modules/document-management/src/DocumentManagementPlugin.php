<?php
declare(strict_types=1);

namespace ProjectO\Modules\DocumentManagement;

use ProjectO\Export\ExportImage;
use ProjectO\Export\XlsxWorkbook;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\CloudStorageSettings;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;

final class DocumentManagementPlugin implements PluginInterface
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

    private DocumentService $documents;
    private DocumentImageService $images;
    private ObjectStorageInterface $imageStorage;

    public function __construct(private readonly PluginContext $context)
    {
        $this->documents = new DocumentService($context->db);
        $this->images = new DocumentImageService($context->db);
        $this->imageStorage = $context->objectStorage() ?? new R2ObjectStorage($context->db);
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
        try {
            $body = $this->imageStorage->read((string) $image['object_key']);
        } catch (Throwable $exception) {
            error_log('Unable to read document image ' . $imageId . ': ' . get_class($exception));
            throw new HttpException('图片读取失败，请稍后重试。', 502);
        }

        return PluginResponse::inline(
            $label . '-' . $ownerId . '-' . $imageId . '.' . $extension,
            $contentType,
            $body
        );
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
                self::exportText($issue['location'] ?? null),
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

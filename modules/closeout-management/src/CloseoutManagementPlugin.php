<?php
declare(strict_types=1);

namespace ProjectO\Modules\CloseoutManagement;

use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Plugin\ProjectProgressProvider;
use ProjectO\Plugin\SidebarNavigationProvider;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;
use Throwable;

/**
 * Engineering closeout controller.
 *
 * Controllers only resolve the server-side project, gate permissions, translate
 * input into service calls, write audit rows and render views. Every business
 * rule (coverage uniqueness, status machine, acceptance, numbering) lives in
 * CloseoutService. The BOM is a read-only source: this module never writes
 * Materials / Material_Compositions.
 */
final class CloseoutManagementPlugin implements PluginInterface, SidebarNavigationProvider, ProjectProgressProvider
{
    private CloseoutService $closeouts;
    private CloseoutImageService $images;
    private ObjectStorageInterface $imageStorage;

    public function __construct(private readonly PluginContext $context)
    {
        $this->closeouts = new CloseoutService($context->db);
        $this->images = new CloseoutImageService($context->db);
        // Shares the platform image storage configured on the document module's
        // settings page (System_Settings cloud_storage.*); APP_R2_* remains a
        // deployment fallback, and APP_OBJECT_STORAGE_ADAPTER overrides both.
        $this->imageStorage = $context->objectStorage() ?? new R2ObjectStorage($context->db);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'closeouts' => $this->overview($input),
            'closeout-items' => $this->itemsIndex($input),
            'closeout-select' => $this->selectIndex($input),
            'closeout-item' => $this->itemDetail($input),
            'closeout-image' => $this->viewImage($input),
            'closeout.plan.save' => $this->savePlan($input),
            'closeout.plan.status' => $this->changePlanStatus($input),
            'closeout.items.create' => $this->createItems($input),
            'closeout.items.sync' => $this->syncItems($input),
            'closeout.locations.backfill' => $this->backfillLocations($input),
            'closeout.location.submit' => $this->submitLocation($input),
            'closeout.location.accept' => $this->acceptLocation($input),
            'closeout.location.cancel' => $this->cancelLocation($input),
            'closeout.item.update' => $this->updateItem($input),
            'closeout.batch.update' => $this->batchUpdate($input),
            'closeout.item.start' => $this->startItem($input),
            'closeout.item.submit' => $this->submitItem($input),
            'closeout.item.accept' => $this->acceptItem($input),
            'closeout.item.cancel' => $this->cancelItem($input),
            'closeout.item.restore' => $this->restoreItem($input),
            'closeout.item.reopen' => $this->reopenItem($input),
            'closeout.progress.add' => $this->addProgress($input),
            'closeout.image.upload' => $this->uploadImage($input),
            'closeout.image.delete' => $this->deleteImage($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    /**
     * V2.1 sidebar: one entry in the construction group, gated by permission.
     */
    public function sidebarItems(): array
    {
        if (!$this->can('view_closeouts')) {
            return [];
        }

        return [[
            'group' => 'construction',
            'page' => 'closeouts',
            'label' => '工程消项',
            'icon' => '✓',
            'order' => 10,
        ]];
    }

    /**
     * Project-home cards: quantity-weighted verified/submitted baselines and
     * item counts for every project, gated by view_closeouts. A viewer without
     * the permission gets no entry, so no progress bar is rendered at all.
     */
    public function projectProgress(): array
    {
        if (!$this->can('view_closeouts')) {
            return [];
        }

        return $this->closeouts->projectProgress();
    }

    // ------------------------------------------------------------------ GET

    private function overview(array $input): PluginResponse
    {
        return $this->workspace($input, 'overview');
    }

    private function itemsIndex(array $input): PluginResponse
    {
        return $this->workspace($input, 'items');
    }

    /**
     * The single flat closeout workspace: plan chips + chart board + item list
     * with the selected item's detail side by side. Both the legacy overview
     * and items routes render it, so old bookmarks never fork the layout.
     */
    private function workspace(array $input, string $mode): PluginResponse
    {
        $project = $this->requireAccess('view_closeouts');
        $projectId = (int) $project['project_id'];
        $plans = $this->closeouts->listPlans($projectId);

        // Missing plan_id means "the plan to work on" (active first); an explicit
        // plan_id=0 means "all plans" and stays that way across links.
        $planParam = array_key_exists('plan_id', $input) ? (int) $input['plan_id'] : null;
        $planId = 0;
        if ($planParam !== null && $planParam > 0) {
            $planId = $planParam;
        } elseif ($planParam === null) {
            foreach ($plans as $plan) {
                if ($plan['status'] === 'active') {
                    $planId = (int) $plan['plan_id'];
                    break;
                }
            }
        }

        $selectedPlan = null;
        if ($planId > 0) {
            foreach ($plans as $plan) {
                if ((int) $plan['plan_id'] === $planId) {
                    $selectedPlan = $plan;
                    break;
                }
            }
            if ($selectedPlan === null) {
                // Stale plan id from an old bookmark: fall back to the first
                // active plan instead of failing the whole workspace.
                $planId = 0;
                foreach ($plans as $plan) {
                    if ($plan['status'] === 'active') {
                        $planId = (int) $plan['plan_id'];
                        $selectedPlan = $plan;
                        break;
                    }
                }
            }
        }

        $filters = $this->readFilters($input);
        $filters['plan_id'] = $planId;
        $items = $this->closeouts->listItems($projectId, $filters);
        $stats = $this->closeouts->boardStatistics($projectId, $planId > 0 ? $planId : null);

        $selectedItem = null;
        $detail = null;
        $itemId = (int) ($input['item_id'] ?? 0);
        if ($itemId > 0) {
            $item = $this->closeouts->requireItem($projectId, $itemId);
            // Keep the list and the open item on the same plan.
            if ($planId > 0 && (int) $item['plan_id'] !== $planId) {
                $planId = (int) $item['plan_id'];
                $filters['plan_id'] = $planId;
                $items = $this->closeouts->listItems($projectId, $filters);
                $stats = $this->closeouts->boardStatistics($projectId, $planId);
                $selectedPlan = null;
                foreach ($plans as $plan) {
                    if ((int) $plan['plan_id'] === $planId) {
                        $selectedPlan = $plan;
                        break;
                    }
                }
            }
            $selectedItem = $item;
            $detail = $this->detailData($projectId, $item);
        }

        $selectPanel = (string) ($input['panel'] ?? '');
        $canCreate = $this->can('create_closeouts');
        $selectData = null;
        if ($selectPanel === 'select' && $canCreate && $selectedPlan !== null && $selectedPlan['status'] === 'active') {
            $candidates = $this->closeouts->bomCandidates($projectId);
            $selectData = [
                'plan' => $selectedPlan,
                'candidates' => $candidates,
                'hiddenMaterialCount' => $this->closeouts->bomMaterialCount($projectId) - count($candidates),
                'hasParentMaterials' => $this->closeouts->hasParentMaterials($projectId),
                'users' => $this->closeouts->activeUsers(),
                'crews' => $this->closeouts->activeCrews($projectId),
            'crews' => $this->closeouts->activeCrews($projectId),
                'suppliers' => $this->closeouts->suppliers(),
            ];
        }

        return $this->context->view('workspace.php', [
            'nav' => $this->nav(),
            'project' => $project,
            'mode' => $mode,
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'selectedItem' => $selectedItem,
            'detail' => $detail,
            'items' => $items,
            'filters' => $filters,
            'stats' => $stats,
            'users' => $this->closeouts->activeUsers(),
            'crews' => $this->closeouts->activeCrews($projectId),
            'suppliers' => $this->closeouts->suppliers(),
            'canCreate' => $canCreate,
            'canEdit' => $this->can('edit_closeouts'),
            'canAccept' => $this->can('accept_closeouts'),
            'canManage' => $this->can('manage_closeouts'),
            'canManageSettings' => $this->can('manage_document_settings') || $this->can('manage_platform'),
            'legacyBackfillCount' => $this->closeouts->backfillCandidateCount($projectId),
            'selectData' => $selectData,
            'today' => $stats['today'],
        ]);
    }

    /**
     * Shared detail payload for the workspace side panel and the standalone
     * item page, so both always show the same records and storage state.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function detailData(int $projectId, array $item): array
    {
        $storageReady = $this->imageStorage->isConfigured();
        $images = $storageReady ? $this->images->listForItem($projectId, (int) $item['item_id']) : [];
        $imageUrls = [];
        foreach ($images as $image) {
            $imageUrls[(int) $image['image_id']] = '?page=closeout-image&item_id='
                . (int) $item['item_id'] . '&image_id=' . (int) $image['image_id'];
        }

        return [
            'progress' => $this->closeouts->listProgress($projectId, (int) $item['item_id']),
            'acceptances' => $this->closeouts->listAcceptances($projectId, (int) $item['item_id']),
            'locations' => $this->closeouts->listLocations($projectId, (int) $item['item_id']),
            'images' => $images,
            'imageUrls' => $imageUrls,
            'relations' => $this->closeouts->materialRelations($projectId, (int) $item['material_id']),
            'storageReady' => $storageReady,
            'storageStatus' => $this->imageStorage instanceof R2ObjectStorage
                ? $this->imageStorage->configurationStatus()
                : null,
        ];
    }

    private function selectIndex(array $input): PluginResponse
    {
        $project = $this->requireAccess('create_closeouts');
        $projectId = (int) $project['project_id'];
        $planId = $this->requiredId($input, 'plan_id', '消项计划');
        $plan = $this->closeouts->requirePlan($projectId, $planId);
        if ($plan['status'] !== 'active') {
            throw new HttpException('只有进行中的消项计划可以纳入事项，请先启动计划。', 422);
        }
        $candidates = $this->closeouts->bomCandidates($projectId);

        return $this->context->view('select.php', [
            'nav' => $this->nav(),
            'project' => $project,
            'plan' => $plan,
            'candidates' => $candidates,
            'hiddenMaterialCount' => $this->closeouts->bomMaterialCount($projectId) - count($candidates),
            'hasParentMaterials' => $this->closeouts->hasParentMaterials($projectId),
            'users' => $this->closeouts->activeUsers(),
            'crews' => $this->closeouts->activeCrews($projectId),
            'suppliers' => $this->closeouts->suppliers(),
            'canCreate' => true,
            'today' => Support::nowLocal('Y-m-d'),
        ]);
    }

    private function itemDetail(array $input): PluginResponse
    {
        $project = $this->requireAccess('view_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $item = $this->closeouts->requireItem($projectId, $itemId);
        $detail = $this->detailData($projectId, $item);

        return $this->context->view('item.php', [
            'nav' => $this->nav(),
            'project' => $project,
            'item' => $item,
            'detail' => $detail,
            'progress' => $detail['progress'],
            'acceptances' => $detail['acceptances'],
            'images' => $detail['images'],
            'imageUrls' => $detail['imageUrls'],
            'relations' => $detail['relations'],
            'storageReady' => $detail['storageReady'],
            'storageStatus' => $detail['storageStatus'],
            'canManageSettings' => $this->can('manage_document_settings') || $this->can('manage_platform'),
            'users' => $this->closeouts->activeUsers(),
            'crews' => $this->closeouts->activeCrews($projectId),
            'suppliers' => $this->closeouts->suppliers(),
            'canEdit' => $this->can('edit_closeouts'),
            'canAccept' => $this->can('accept_closeouts'),
            'canManage' => $this->can('manage_closeouts'),
            'canCreate' => $this->can('create_closeouts'),
            'today' => Support::nowLocal('Y-m-d'),
        ]);
    }

    private function viewImage(array $input): PluginResponse
    {
        $project = $this->requireAccess('view_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $imageId = $this->requiredId($input, 'image_id', '附件');
        $this->closeouts->requireItem($projectId, $itemId);
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException('附件存储未配置，暂不能查看照片。', 503);
        }
        $image = $this->images->requireImage($projectId, $itemId, $imageId);
        Support::csrfToken();
        session_write_close();
        try {
            $body = $this->imageStorage->read((string) $image['object_key']);
        } catch (Throwable $exception) {
            error_log('Unable to read closeout image ' . $imageId . ': ' . get_class($exception));
            throw new HttpException('附件读取失败，请稍后重试。', 502);
        }

        return PluginResponse::inline(
            (string) $image['original_name'],
            (string) $image['content_type'],
            $body
        );
    }

    // ----------------------------------------------------------------- POST

    private function savePlan(array $input): PluginResponse
    {
        $project = $this->requireAccess('create_closeouts');
        $projectId = (int) $project['project_id'];
        $planId = $this->closeouts->savePlan($projectId, $input, $this->userId());
        $plan = $this->closeouts->requirePlan($projectId, $planId);
        $planIdInput = trim((string) ($input['plan_id'] ?? ''));
        $this->audit(
            $projectId,
            'closeout_plan',
            $planId,
            ($planIdInput !== '' && $planIdInput !== '0') ? 'closeout.plan.update' : 'closeout.plan.create',
            ['plan_no' => $plan['plan_no'], 'plan_name' => $plan['plan_name']]
        );
        Support::flash('消项计划已保存。');

        return PluginResponse::redirect($this->workspaceRedirect($planId));
    }

    private function changePlanStatus(array $input): PluginResponse
    {
        $project = $this->requireAccess('manage_closeouts');
        $projectId = (int) $project['project_id'];
        $planId = $this->requiredId($input, 'plan_id', '消项计划');
        $action = (string) ($input['status_action'] ?? '');
        $result = $this->closeouts->setPlanStatus($projectId, $planId, $action, $this->userId());
        $this->audit($projectId, 'closeout_plan', $planId, 'closeout.plan.' . $action, [
            'from' => $result['before']['status'],
            'to' => $result['after']['status'],
        ]);
        Support::flash('消项计划状态已更新为「'
            . CloseoutLabels::planStatus((string) $result['after']['status']) . '」。');

        return PluginResponse::redirect($this->workspaceRedirect($planId));
    }

    private function createItems(array $input): PluginResponse
    {
        $project = $this->requireAccess('create_closeouts');
        $projectId = (int) $project['project_id'];
        $planId = $this->requiredId($input, 'plan_id', '消项计划');
        $materialIds = $input['material_ids'] ?? [];
        if (!is_array($materialIds)) {
            $materialIds = [];
        }
        $created = $this->closeouts->createItems($projectId, $planId, $materialIds, $input, $this->userId());
        $plan = $this->closeouts->requirePlan($projectId, $planId);
        foreach ($created as $row) {
            $item = $this->closeouts->requireItem($projectId, (int) $row['item_id']);
            $this->audit($projectId, 'closeout_item', (int) $row['item_id'], 'closeout.item.create', [
                'item_no' => $row['item_no'],
                'plan_no' => $plan['plan_no'],
                'material_id' => $item['material_id'],
            ]);
        }
        Support::flash('已纳入 ' . count($created) . ' 项消项事项。');

        return PluginResponse::redirect($this->workspaceRedirect($planId));
    }

    /**
     * Give the V1 items their space sub-items back, from the same parent
     * material estimate rows the new flow uses. Structure only: no quantity is
     * invented and item status is left to the normal bucket recalc.
     */
    private function backfillLocations(array $input): PluginResponse
    {
        unset($input);
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $result = $this->closeouts->backfillLocations($projectId, $this->userId());

        $legacy = 0;
        $appended = 0;
        foreach ($result['filled'] as $row) {
            if (($row['kind'] ?? 'created') === 'created') {
                $legacy++;
            } else {
                $appended++;
            }
            $this->audit($projectId, 'closeout_item', (int) $row['item_id'], 'closeout.locations.backfill', [
                'item_no' => $row['item_no'],
                'spaces' => (int) $row['spaces'],
                'kind' => $row['kind'] ?? 'created',
                'source' => 'estimate-detail',
            ]);
        }

        $message = '已补建 ' . $result['locations_created'] . ' 个空间';
        if ($legacy > 0 || $appended > 0) {
            $message .= '（旧事项 ' . $legacy . ' 条、新增区域 ' . $appended . ' 条）';
        }
        if ($result['existing'] > 0) {
            $message .= '；' . $result['existing'] . ' 条空间已齐';
        }
        if ($result['missing_estimates'] !== []) {
            $message .= '；缺测算（需先录父材料数量）：' . implode('、', $result['missing_estimates']);
        }
        if ($result['not_parent'] !== []) {
            $message .= '；非父材料（需按父材料重录）：' . implode('、', $result['not_parent']);
        }
        if ($result['plan_inactive'] !== []) {
            $message .= '；计划非进行中未补：' . implode('、', $result['plan_inactive']);
        }
        if ($result['closed_skipped'] !== []) {
            $message .= '；已消项未补：' . implode('、', $result['closed_skipped']);
        }
        Support::flash($message . '。');

        return PluginResponse::redirect($this->workspaceRedirect(0));
    }

    /**
     * Fill in items for parent materials that gained estimate rows after the
     * plan was provisioned. Idempotent: existing items are counted, not
     * duplicated.
     */
    private function syncItems(array $input): PluginResponse
    {
        $project = $this->requireAccess('create_closeouts');
        $projectId = (int) $project['project_id'];
        $planId = $this->requiredId($input, 'plan_id', '消项计划');
        $result = $this->closeouts->autoProvision($projectId, $planId, $this->userId());
        $spaces = $this->closeouts->syncSpaces($projectId, $this->userId(), $planId);
        $plan = $this->closeouts->requirePlan($projectId, $planId);
        foreach ($result['created'] as $row) {
            $this->audit($projectId, 'closeout_item', (int) $row['item_id'], 'closeout.item.create', [
                'item_no' => $row['item_no'],
                'plan_no' => $plan['plan_no'],
                'material_id' => $row['material_id'],
                'source' => 'auto-provision-sync',
            ]);
        }
        foreach ($spaces['items'] as $row) {
            $this->audit($projectId, 'closeout_item', (int) $row['item_id'], 'closeout.locations.append', [
                'item_no' => $row['item_no'],
                'spaces' => (int) $row['spaces'],
                'source' => 'estimate-detail',
            ]);
        }
        $message = '已补入 ' . count($result['created']) . ' 条新事项';
        if ($spaces['created'] > 0) {
            $message .= '、' . $spaces['created'] . ' 个新增区域';
        }
        if ($result['existing'] > 0) {
            $message .= '，' . $result['existing'] . ' 条父材料已有事项';
        }
        if ($result['ignored'] !== []) {
            $message .= '；以下材料有测算但不是父材料，已跳过：' . implode('、', $result['ignored']);
        }
        Support::flash($message . '。');

        return PluginResponse::redirect($this->workspaceRedirect($planId));
    }

    /**
     * Checkbox in the expanded space list = "this space is done": submits the
     * space for acceptance. Acceptance stays a separate, permission-guarded
     * action — ticking a box never closes anything by itself.
     */
    private function submitLocation(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $locationId = $this->requiredId($input, 'location_id', '空间子项');
        $note = trim((string) ($input['remark'] ?? ''));
        if ($note === '') {
            $note = '空间完成，提交验收';
        }
        $location = $this->closeouts->submitLocation($projectId, $locationId, $note, $this->userId());
        $this->audit($projectId, 'closeout_location', $locationId, 'closeout.location.submit', [
            'item_id' => (int) $location['item_id'],
            'space' => (string) $location['space_label'],
        ]);
        Support::flash('空间「' . (string) $location['space_label'] . '」已提交验收。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $location['plan_id'], (int) $location['item_id'], $input));
    }

    private function acceptLocation(array $input): PluginResponse
    {
        $project = $this->requireAccess('accept_closeouts');
        $projectId = (int) $project['project_id'];
        $locationId = $this->requiredId($input, 'location_id', '空间子项');
        $location = $this->closeouts->requireLocation($projectId, $locationId);
        $passed = (string) ($input['result'] ?? 'passed') === 'passed';
        $this->closeouts->acceptLocation($projectId, $locationId, $input, $this->userId());
        $after = $this->closeouts->requireLocation($projectId, $locationId);
        $this->audit($projectId, 'closeout_location', $locationId, 'closeout.location.accept', [
            'item_id' => (int) $location['item_id'],
            'space' => (string) $location['space_label'],
            'result' => (string) ($input['result'] ?? 'passed'),
        ]);
        Support::flash($passed
            ? '空间「' . (string) $after['space_label'] . '」验收通过。'
            : '空间「' . (string) $after['space_label'] . '」已驳回，等待整改。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $after['plan_id'], (int) $after['item_id'], $input));
    }

    private function cancelLocation(array $input): PluginResponse
    {
        $project = $this->requireAccess('manage_closeouts');
        $projectId = (int) $project['project_id'];
        $locationId = $this->requiredId($input, 'location_id', '空间子项');
        $reason = trim((string) ($input['cancel_reason'] ?? ''));
        if ($reason === '') {
            throw new HttpException('请填写取消空间的原因。', 422);
        }
        $location = $this->closeouts->cancelLocation($projectId, $locationId, $reason, $this->userId());
        $after = $this->closeouts->requireLocation($projectId, $locationId);
        $this->audit($projectId, 'closeout_location', $locationId, 'closeout.location.cancel', [
            'item_id' => (int) $after['item_id'],
            'space' => (string) $after['space_label'],
            'reason' => $reason,
        ]);
        Support::flash('空间「' . (string) $after['space_label'] . '」已退出消项范围。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $after['plan_id'], (int) $after['item_id'], $input));
    }

    private function updateItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $before = $this->closeouts->requireItem($projectId, $itemId);
        $this->closeouts->updateItem($projectId, $itemId, $input, $this->userId());
        $after = $this->closeouts->requireItem($projectId, $itemId);
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.update', [
            'item_no' => $after['item_no'],
            'before_title' => $before['title'],
            'after_title' => $after['title'],
        ]);
        Support::flash('消项事项管理信息已保存。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $after['plan_id'], $itemId, $input));
    }

    private function batchUpdate(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemIds = $input['item_ids'] ?? [];
        if (!is_array($itemIds)) {
            $itemIds = [];
        }
        $result = $this->closeouts->batchUpdate($projectId, $itemIds, $input, $this->userId());
        $this->audit($projectId, 'closeout_item', 0, 'closeout.batch.update', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);
        $message = '已批量调整 ' . $result['updated'] . ' 项';
        if ($result['skipped'] > 0) {
            $message .= '，跳过 ' . $result['skipped'] . ' 项（已消项或已取消）';
        }
        Support::flash($message . '。');

        return PluginResponse::redirect($this->workspaceRedirect(
            (int) ($input['plan_id'] ?? 0),
            null,
            $input
        ));
    }

    private function startItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $result = $this->closeouts->startItem($projectId, $itemId, $this->userId());
        $item = $result['item'];
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.start', [
            'item_no' => $item['item_no'],
            'from' => 'pending',
            'to' => 'ongoing',
            'spaces_started' => (int) $result['spaces_started'],
        ]);
        Support::flash($result['spaces_started'] > 0
            ? '已开始 ' . (int) $result['spaces_started'] . ' 个空间，事项进入执行。'
            : '事项已进入执行。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function submitItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $note = (string) ($input['progress_note'] ?? '');
        $item = $this->closeouts->submitItem($projectId, $itemId, $note, $this->userId());
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.submit', [
            'item_no' => $item['item_no'],
            'note' => trim($note),
        ]);
        Support::flash('事项已提交验收。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function acceptItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('accept_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $item = $this->closeouts->acceptItem($projectId, $itemId, $input, $this->userId());
        $result = (string) ($input['result'] ?? '');
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.accept', [
            'item_no' => $item['item_no'],
            'result' => $result,
            'remark' => trim((string) ($input['remark'] ?? '')),
            'actual_complete_date' => $input['actual_complete_date'] ?? null,
        ]);
        Support::flash($result === 'passed' ? '验收通过，事项已消项。' : '验收不通过，事项已退回执行。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function cancelItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('manage_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $reason = (string) ($input['cancel_reason'] ?? '');
        $item = $this->closeouts->cancelItem($projectId, $itemId, $reason, $this->userId());
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.cancel', [
            'item_no' => $item['item_no'],
            'from_status' => $item['status'],
            'reason' => trim($reason),
        ]);
        Support::flash('事项已取消，可稍后恢复。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function restoreItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('manage_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $reason = (string) ($input['restore_reason'] ?? '');
        $item = $this->closeouts->restoreItem($projectId, $itemId, $reason, $this->userId());
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.restore', [
            'item_no' => $item['item_no'],
            'reason' => trim($reason),
        ]);
        Support::flash('事项已恢复为待处理。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function reopenItem(array $input): PluginResponse
    {
        $project = $this->requireAccess('manage_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $reason = (string) ($input['reopen_reason'] ?? '');
        $item = $this->closeouts->reopenItem($projectId, $itemId, $reason, $this->userId());
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.item.reopen', [
            'item_no' => $item['item_no'],
            'reason' => trim($reason),
        ]);
        Support::flash('已消项事项已重新打开。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function addProgress(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $note = (string) ($input['progress_note'] ?? '');
        $item = $this->closeouts->addProgress($projectId, $itemId, $note, $this->userId());
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.progress.add', [
            'item_no' => $item['item_no'],
            'note' => trim($note),
        ]);
        Support::flash('进度已记录。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function uploadImage(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $item = $this->closeouts->requireItem($projectId, $itemId);
        if ($item['status'] === 'cancelled') {
            throw new HttpException('已取消的事项不能上传附件。', 422);
        }
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException('附件存储未配置，暂不能上传照片。', 503);
        }
        $uploaded = 0;
        foreach ($this->uploadedFiles($input) as $file) {
            $this->images->add(
                $projectId,
                $itemId,
                $this->userId(),
                $file['path'],
                $file['name'],
                $this->imageStorage
            );
            $uploaded++;
        }
        if ($uploaded === 0) {
            throw new HttpException('请选择一张有效的图片后重试。', 422);
        }
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.image.upload', [
            'item_no' => $item['item_no'],
            'uploaded' => $uploaded,
        ]);
        Support::flash('已上传 ' . $uploaded . ' 张附件照片。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    private function deleteImage(array $input): PluginResponse
    {
        $project = $this->requireAccess('edit_closeouts');
        $projectId = (int) $project['project_id'];
        $itemId = $this->requiredId($input, 'item_id', '消项事项');
        $imageId = $this->requiredId($input, 'image_id', '附件');
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException('附件存储未配置。', 503);
        }
        $item = $this->closeouts->requireItem($projectId, $itemId);
        $this->images->delete($projectId, $itemId, $imageId, $this->imageStorage);
        $this->audit($projectId, 'closeout_item', $itemId, 'closeout.image.delete', [
            'item_no' => $item['item_no'],
            'image_id' => $imageId,
        ]);
        Support::flash('附件已删除。');

        return PluginResponse::redirect($this->workspaceRedirect((int) $item['plan_id'], $itemId, $input));
    }

    // -------------------------------------------------------------- internals

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function readFilters(array $input): array
    {
        return [
            'plan_id' => (int) ($input['plan_id'] ?? 0),
            'status' => (string) ($input['status'] ?? ''),
            'risk_level' => (string) ($input['risk_level'] ?? ''),
            'responsible_user_id' => (int) ($input['responsible_user_id'] ?? 0),
            'responsible_crew_id' => (int) ($input['responsible_crew_id'] ?? 0),
            'overdue' => (int) ($input['overdue'] ?? 0),
            'keyword' => trim((string) ($input['keyword'] ?? '')),
        ];
    }

    /**
     * Every write goes back to the flat workspace with the plan and (for item
     * actions) the item still open, so an action never dumps the user on an
     * overview page or loses the working filter.
     *
     * @param array<string, mixed> $input
     */
    private function workspaceRedirect(int $planId, ?int $itemId = null, array $input = []): string
    {
        $url = '?page=closeouts&plan_id=' . max(0, $planId);
        if ($itemId !== null && $itemId > 0) {
            $url .= '&item_id=' . $itemId;
        }
        $status = (string) ($input['status'] ?? '');
        if ($status !== '' && isset(CloseoutLabels::ITEM_STATUS[$status])) {
            $url .= '&status=' . $status;
        }

        // No "#fragment" here on purpose: the browser would scroll to it on load,
        // after the script has already restored the exact position, causing a
        // visible second jump. The row id stays available for the script.
        return $url;
    }

    private function requireAccess(string $permission): array
    {
        Support::requireLogin();
        Support::requirePermission($this->context->db, $permission);
        Support::requirePermission($this->context->db, 'view_projects');

        return $this->context->requireCurrentProject();
    }

    private function can(string $permission): bool
    {
        return Support::hasPermission($this->context->db, $permission);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requiredId(array $input, string $key, string $label): int
    {
        $value = $input[$key] ?? null;
        $id = is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1) {
            throw new HttpException('请选择有效的' . $label . '。', 422);
        }

        return (int) $id;
    }

    /**
     * Normalises single or multiple uploads under name="image".
     *
     * @return list<array{path: string, name: string}>
     */
    private function uploadedFiles(array $input): array
    {
        $files = $input['_files'] ?? null;
        $file = is_array($files) ? ($files['image'] ?? null) : null;
        if (!is_array($file) || !isset($file['error'])) {
            return [];
        }

        $names = is_array($file['name']) ? array_values($file['name']) : [$file['name']];
        $temporary = is_array($file['tmp_name']) ? array_values($file['tmp_name']) : [$file['tmp_name']];
        $errors = is_array($file['error']) ? array_values($file['error']) : [$file['error']];

        $result = [];
        foreach ($names as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            $path = (string) ($temporary[$index] ?? '');
            if ($error !== UPLOAD_ERR_OK || $path === '' || !is_uploaded_file($path)) {
                continue;
            }
            $result[] = [
                'path' => $path,
                'name' => is_string($name) ? $name : '',
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(int $projectId, string $entityType, int $entityId, string $action, array $details): void
    {
        $statement = $this->context->db->prepare(
            'INSERT INTO audit_logs
             (user_id, project_id, entity_type, entity_id, action, details, ip_address, user_agent, created_at)
             VALUES (:user_id, :project_id, :entity_type, :entity_id, :action, :details, :ip, :agent, :created_at)'
        );
        $statement->execute([
            'user_id' => $this->userId(),
            'project_id' => $projectId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'agent' => isset($_SERVER['HTTP_USER_AGENT'])
                ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 400)
                : null,
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function nav(): array
    {
        return [
            'overview' => $this->can('view_closeouts'),
            'items' => $this->can('view_closeouts'),
            'create' => $this->can('create_closeouts'),
        ];
    }
}

<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Plugin\SidebarNavigationProvider;
use ProjectO\Support;

final class BomCostingPlugin implements PluginInterface, SidebarNavigationProvider
{
    private CostingService $costings;
    private RecalcEngine $recalc;
    private ActualCostService $actuals;
    private ResourceCatalogService $catalog;
    private CostPriceService $costPrices;
    private PriceResolver $prices;

    public function __construct(private readonly PluginContext $context)
    {
        $this->prices = new PriceResolver($context->db);
        $this->costings = new CostingService($context->db);
        $this->recalc = new RecalcEngine($context->db, $this->prices);
        $this->actuals = new ActualCostService($context->db);
        $this->catalog = new ResourceCatalogService($context->db);
        $this->costPrices = new CostPriceService($context->db);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'costings' => $this->costingsIndex($input),
            'costing.detail' => $this->costingDetail($input),
            'catalog.index' => $this->catalogIndex(),
            'prices.index' => $this->pricesIndex(),
            'actual.index' => $this->actualIndex(),
            'costing.create' => $this->createCosting($input),
            'resource.save' => $this->saveResource($input),
            'resource.delete' => $this->deleteResource($input),
            'costing.confirm' => $this->confirmCosting($input),
            'costing.recalc' => $this->recalcCosting($input),
            'catalog.save' => $this->saveCatalog($input),
            'catalog.delete' => $this->deleteCatalog($input),
            'price.save' => $this->savePrice($input),
            'price.delete' => $this->deletePrice($input),
            'actual.record.save' => $this->saveRecord($input),
            'actual.reversal.save' => $this->saveReversal($input),
            'actual.adjustment.save' => $this->saveAdjustment($input),
            'actual.allocation.save' => $this->saveAllocation($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function costingsIndex(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];

        // 右栏成本预览：清单页只读当前态，不触发重算（重算发生在打开详情时）。
        // 未选中时右栏给出引导，和旧系统的“请点击左侧记录查看成本分析”一致。
        $selectedCosting = null;
        $preview = null;
        $previewNotice = null;
        $rawCostingId = $input['costing_id'] ?? null;
        if ($rawCostingId !== null && $rawCostingId !== '') {
            try {
                [$selectedCosting, $preview] = $this->costingWithPreview(
                    $projectId,
                    $this->requiredId($rawCostingId),
                    Support::nowLocal('Y-m-d')
                );
            } catch (HttpException $exception) {
                // 链接可能指向已删除的组价：清单页不该整页 404，右栏提示即可。
                if ($exception->statusCode !== 404) {
                    throw $exception;
                }
                $previewNotice = '该组价已不存在或已被删除，请在左侧清单中选择其他组价。';
            }
        }

        return $this->context->view('costings.php', [
            'title' => 'BOM 组价',
            'currentProject' => $project,
            'costings' => $this->costings->listCostings($projectId),
            'availableMaterials' => $this->costings->listAvailableMaterials($projectId),
            'selectedCosting' => $selectedCosting,
            'preview' => $preview,
            'previewNotice' => $previewNotice,
            'canEdit' => Support::hasPermission($this->context->db, 'edit_costings'),
            'nav' => $this->nav(),
        ]);
    }

    private function costingDetail(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $costingId = $this->requiredId($input['costing_id'] ?? null);

        // 打开详情即按当前有效输入补算一次（输入驱动自动重算，不退草稿）；
        // 并发冲突/锁占用不阻断页面渲染，保留当前态供查看，可手动再算。
        $today = Support::nowLocal('Y-m-d');
        try {
            $this->recalc->recalc($projectId, $costingId, $this->userId(), $today);
        } catch (HttpException $exception) {
            error_log('BOM detail auto-recalc skipped: ' . $exception->getMessage());
        }

        [$costing, $preview] = $this->costingWithPreview($projectId, $costingId, $today);

        return $this->context->view('costing-detail.php', [
            'title' => '组价详情',
            'currentProject' => $project,
            'costing' => $costing,
            'preview' => $preview,
            'catalogOptions' => $this->catalog->groupedOptions(),
            'auxiliaries' => $this->projectAuxiliaries($projectId),
            'materials' => $this->projectMaterials($projectId),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_costings'),
            'nav' => $this->nav(),
        ]);
    }

    /**
     * 读取组价并装配“参考金额分解”（饼图/汇总/明细共用同一份明细行）。
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function costingWithPreview(int $projectId, int $costingId, string $today): array
    {
        $costing = $this->costings->requireCosting($projectId, $costingId);
        $costing['detail_rows'] = $this->buildDetailRows($projectId, $costing, $today);

        return [$costing, CostBreakdown::fromRows($costing['detail_rows'])];
    }

    /**
     * 装配详情明细视图模型：已计价结果行 ＋ 未命中有效取价的组件/资源缺项行（带缺价原因）。
     * @return list<array<string, mixed>>
     */
    private function buildDetailRows(int $projectId, array $costing, string $today): array
    {
        $resultsByComposition = [];
        $resultsByResource = [];
        foreach ($costing['results'] as $result) {
            if ($result['composition_id'] !== null) {
                $resultsByComposition[(int) $result['composition_id']] = $result;
            }
            if ($result['resource_line_id'] !== null) {
                $resultsByResource[(int) $result['resource_line_id']] = $result;
            }
        }

        $rows = [];
        foreach ($costing['compositions'] as $comp) {
            $result = $resultsByComposition[(int) $comp['composition_id']] ?? null;
            if ($result !== null) {
                $rows[] = [
                    'kind' => 'composition',
                    'composition_id' => (int) $comp['composition_id'],
                    'object_name' => $result['comp_material_name'] ?? $comp['material_name'],
                    'object_code' => $result['comp_material_code'] ?? $comp['material_code'],
                    'unit' => $result['comp_unit'] ?? $comp['unit'],
                    'quantity' => (string) $result['quantity'],
                    'unit_price' => (string) $result['unit_price'],
                    'currency_code' => (string) $result['currency_code'],
                    'pricing_unit' => (string) $result['pricing_unit'],
                    'tax_status' => (string) $result['source_tax_status'],
                    'source' => $this->resultSourceLabel($result),
                    'status' => 'priced',
                    'reason' => null,
                ];
                continue;
            }
            $resolved = $this->prices->forMaterial($projectId, (int) $comp['child_material_id'], $today);
            $rows[] = [
                'kind' => 'composition',
                'composition_id' => (int) $comp['composition_id'],
                'object_name' => $comp['material_name'],
                'object_code' => $comp['material_code'],
                'unit' => $comp['unit'],
                'quantity' => $this->coefficientQuantity((string) $comp['quantity']),
                'unit_price' => null,
                'currency_code' => null,
                'pricing_unit' => null,
                'tax_status' => 'unknown',
                'source' => '缺价',
                'status' => 'missing',
                'reason' => $resolved['reason'],
            ];
        }

        foreach ($costing['resources'] as $resource) {
            $result = $resultsByResource[(int) $resource['resource_line_id']] ?? null;
            if ($result !== null) {
                $rows[] = [
                    'kind' => 'resource:' . $resource['resource_type'],
                    'resource_line_id' => (int) $resource['resource_line_id'],
                    'object_name' => $result['aux_name'] ?? $result['catalog_name'] ?? $result['res_material_name'] ?? '—',
                    'object_code' => $result['res_material_code'] ?? null,
                    'unit' => $result['aux_unit'] ?? $result['catalog_unit'] ?? $result['res_material_unit'] ?? null,
                    'quantity' => (string) $result['quantity'],
                    'unit_price' => (string) $result['unit_price'],
                    'currency_code' => (string) $result['currency_code'],
                    'pricing_unit' => (string) $result['pricing_unit'],
                    'tax_status' => (string) $result['source_tax_status'],
                    'source' => $this->resultSourceLabel($result),
                    'status' => 'priced',
                    'reason' => null,
                ];
                continue;
            }
            $resolved = $this->recalc->resolveResourceLine($projectId, $resource, $today);
            $rows[] = [
                'kind' => 'resource:' . $resource['resource_type'],
                'resource_line_id' => (int) $resource['resource_line_id'],
                'object_name' => $resource['aux_name'] ?? $resource['catalog_name'] ?? $resource['material_name'] ?? '—',
                'object_code' => $resource['material_code'] ?? null,
                'unit' => $resource['aux_unit'] ?? $resource['catalog_unit'] ?? $resource['material_unit'] ?? null,
                'quantity' => (string) $resource['quantity'],
                'unit_price' => null,
                'currency_code' => null,
                'pricing_unit' => null,
                'tax_status' => 'unknown',
                'source' => '缺价',
                'status' => 'missing',
                'reason' => $resolved['reason'],
            ];
        }

        return $rows;
    }

    private function resultSourceLabel(array $result): string
    {
        if ($result['quotation_id'] !== null) {
            return '报价 #' . (int) $result['quotation_id'];
        }
        if ($result['project_cost_price_id'] !== null) {
            return '项目成本价 #' . (int) $result['project_cost_price_id'];
        }
        if ($result['manual_price'] !== null) {
            return '人工确认价';
        }

        return '缺价';
    }

    private function coefficientQuantity(string $coefficient): string
    {
        return number_format((float) $coefficient, 6, '.', '');
    }

    private function catalogIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_cost_catalog');
        return $this->context->view('catalog.php', [
            'title' => '资源目录',
            'catalog' => $this->catalog->list(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_cost_catalog'),
            'nav' => $this->nav(),
        ]);
    }

    private function pricesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];

        return $this->context->view('cost-prices.php', [
            'title' => '项目成本价',
            'currentProject' => $project,
            'prices' => $this->costPrices->list($projectId),
            'auxiliaries' => $this->projectAuxiliaries($projectId),
            'catalogOptions' => $this->catalog->groupedOptions(),
            'currencies' => \ProjectO\CurrencyCatalog::options(),
            'defaultCurrency' => 'CNY',
            'canEdit' => Support::hasPermission($this->context->db, 'edit_costings'),
            'nav' => $this->nav(),
        ]);
    }

    private function createCosting(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $materialId = $this->requiredId($input['material_id'] ?? null);

        $costingId = $this->costings->ensureCosting($projectId, $materialId, $this->userId());
        Support::audit($this->context->db, 'bom.costing.create', 'Costing ' . $costingId . ' created for material ' . $materialId . '.');

        // 建单后按当前有效输入补算一次；冲突不阻断进入详情。
        try {
            $this->recalc->recalc($projectId, $costingId, $this->userId(), Support::nowLocal('Y-m-d'));
        } catch (HttpException $exception) {
            error_log('BOM create auto-recalc skipped: ' . $exception->getMessage());
        }

        Support::flash('组价已建立，可在详情中维护资源行并取价。');

        return PluginResponse::redirect('?page=costing-detail&costing_id=' . $costingId);
    }

    private function actualIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_actual_costs');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];

        return $this->context->view('actual-costs.php', [
            'title' => '实际费用',
            'currentProject' => $project,
            'statistics' => $this->actuals->statistics($projectId),
            'records' => $this->actualRecords($projectId),
            'costings' => $this->costings->listCostings($projectId),
            'materials' => $this->projectMaterials($projectId),
            'auxiliaries' => $this->projectAuxiliaries($projectId),
            'catalogOptions' => $this->catalog->groupedOptions(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_actual_costs'),
            'nav' => $this->nav(),
        ]);
    }

    private function saveResource(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $costingId = $this->requiredId($input['costing_id'] ?? null);
        $this->costings->saveResource($projectId, $costingId, $input, $this->userId());
        Support::audit($this->context->db, 'bom.resource.save', 'Costing ' . $costingId . ' resource updated.');
        Support::flash('资源行已保存。');

        return PluginResponse::redirect('?page=costing-detail&costing_id=' . $costingId);
    }

    private function deleteResource(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $costingId = $this->requiredId($input['costing_id'] ?? null);
        $lineId = $this->requiredId($input['resource_line_id'] ?? null);
        $this->costings->deleteResource($projectId, $costingId, $lineId);
        Support::audit($this->context->db, 'bom.resource.delete', 'Costing ' . $costingId . ' resource ' . $lineId . ' deleted.');
        Support::flash('资源行已删除。');

        return PluginResponse::redirect('?page=costing-detail&costing_id=' . $costingId);
    }

    private function confirmCosting(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $costingId = $this->requiredId($input['costing_id'] ?? null);
        $this->costings->confirm($projectId, $costingId, $this->userId());
        Support::audit($this->context->db, 'bom.costing.confirm', 'Costing ' . $costingId . ' confirmed.');
        Support::flash('组价已确认。');

        return PluginResponse::redirect('?page=costing-detail&costing_id=' . $costingId);
    }

    private function recalcCosting(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_costings');
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $costingId = $this->requiredId($input['costing_id'] ?? null);
        $today = Support::nowLocal('Y-m-d');
        $committed = $this->recalc->recalc($projectId, $costingId, $this->userId(), $today);
        Support::audit($this->context->db, 'bom.costing.recalc', 'Costing ' . $costingId . ' recalculated (committed=' . ($committed ? '1' : '0') . ').');
        Support::flash($committed ? '组价已按当前有效输入重算。' : '期间结构有变动，本轮重算已丢弃，请稍后重试。');

        return PluginResponse::redirect('?page=costing-detail&costing_id=' . $costingId);
    }

    private function saveCatalog(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_cost_catalog');
        Support::requirePermission($this->context->db, 'edit_cost_catalog');
        $id = $this->catalog->save($input);
        Support::audit($this->context->db, 'bom.catalog.save', 'Resource catalog ' . $id . ' saved.');
        Support::flash('目录项已保存。');

        return PluginResponse::redirect('?page=cost-catalog');
    }

    private function deleteCatalog(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_cost_catalog');
        Support::requirePermission($this->context->db, 'edit_cost_catalog');
        $id = $this->requiredId($input['catalog_id'] ?? null);
        $this->catalog->delete($id);
        Support::audit($this->context->db, 'bom.catalog.delete', 'Resource catalog ' . $id . ' deleted.');
        Support::flash('目录项已删除。');

        return PluginResponse::redirect('?page=cost-catalog');
    }

    private function savePrice(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $priceId = $this->costPrices->save($projectId, $input, $this->userId());
        Support::audit($this->context->db, 'bom.cost_price.save', 'Cost price ' . $priceId . ' saved.');
        Support::flash('项目成本价已保存。');

        return PluginResponse::redirect('?page=cost-prices');
    }

    private function deletePrice(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'edit_costings');
        $projectId = $this->currentProjectId();
        $priceId = $this->requiredId($input['price_id'] ?? null);
        $this->costPrices->delete($projectId, $priceId);
        Support::audit($this->context->db, 'bom.cost_price.delete', 'Cost price ' . $priceId . ' deleted.');
        Support::flash('成本价已删除。');

        return PluginResponse::redirect('?page=cost-prices');
    }

    private function saveRecord(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_actual_costs');
        Support::requirePermission($this->context->db, 'edit_actual_costs');
        $id = $this->actuals->recordNormal($this->currentProjectId(), $input, $this->userId());
        Support::audit($this->context->db, 'actual.record.save', 'Actual record ' . $id . '.');
        Support::flash('费用已登记。');

        return PluginResponse::redirect('?page=actual-costs');
    }

    private function saveReversal(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_actual_costs');
        Support::requirePermission($this->context->db, 'edit_actual_costs');
        $id = $this->actuals->recordReversal($this->currentProjectId(), $input, $this->userId());
        Support::audit($this->context->db, 'actual.reversal.save', 'Reversal ' . $id . '.');
        Support::flash('冲销已登记。');

        return PluginResponse::redirect('?page=actual-costs');
    }

    private function saveAdjustment(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_actual_costs');
        Support::requirePermission($this->context->db, 'edit_actual_costs');
        $id = $this->actuals->recordAdjustment($this->currentProjectId(), $input, $this->userId());
        Support::audit($this->context->db, 'actual.adjustment.save', 'Adjustment ' . $id . '.');
        Support::flash('调整已登记。');

        return PluginResponse::redirect('?page=actual-costs');
    }

    private function saveAllocation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_actual_costs');
        Support::requirePermission($this->context->db, 'edit_actual_costs');
        $this->actuals->allocate($this->currentProjectId(), $input, $this->userId());
        Support::audit($this->context->db, 'actual.allocation.save', 'Allocation for record ' . ($input['record_id'] ?? '?') . '.');
        Support::flash('分摊已保存。');

        return PluginResponse::redirect('?page=actual-costs');
    }

    /** @return list<array<string, mixed>> */
    private function actualRecords(int $projectId): array
    {
        $stmt = $this->context->db->prepare(
            'SELECT r.*, m.material_name, a.aux_name, c.name AS catalog_name
             FROM actual_cost_records r
             LEFT JOIN Materials m ON m.material_id = r.material_id AND m.project_id = r.project_id
             LEFT JOIN Auxiliary_Materials a ON a.aux_id = r.aux_id AND a.project_id = r.project_id
             LEFT JOIN resource_catalog c ON c.catalog_id = r.catalog_id
             WHERE r.project_id = :project_id
             ORDER BY r.occurred_at DESC, r.record_id DESC'
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function projectMaterials(int $projectId): array
    {
        $stmt = $this->context->db->prepare(
            'SELECT material_id, material_code, material_name, unit
             FROM Materials WHERE project_id = :project_id ORDER BY material_name, material_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private function projectAuxiliaries(int $projectId): array
    {
        $stmt = $this->context->db->prepare(
            'SELECT aux_id, aux_name, unit FROM Auxiliary_Materials
             WHERE project_id = :project_id ORDER BY aux_name, aux_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        return $stmt->fetchAll();
    }

    private function nav(): array
    {
        return [
            'costings' => Support::hasPermission($this->context->db, 'view_costings')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'prices' => Support::hasPermission($this->context->db, 'view_costings')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'catalog' => Support::hasPermission($this->context->db, 'view_cost_catalog'),
            'actuals' => Support::hasPermission($this->context->db, 'view_actual_costs')
                && Support::hasPermission($this->context->db, 'view_projects'),
        ];
    }

    public function sidebarItems(): array
    {
        $nav = $this->nav();
        $items = [];
        foreach ([
            ['costings', 'costings', 'BOM 组价', '◧', 'quotes'],
            ['prices', 'cost-prices', '成本价', '¥', 'quotes'],
            ['catalog', 'cost-catalog', '资源目录', '⚙', 'quotes'],
            ['actuals', 'actual-costs', '实际费用', '￥', 'quotes'],
        ] as [$key, $page, $label, $icon, $group]) {
            if (!empty($nav[$key])) {
                $items[] = ['group' => $group, 'page' => $page, 'label' => $label, 'icon' => $icon];
            }
        }

        return $items;
    }

    private function currentProjectId(): int
    {
        Support::requirePermission($this->context->db, 'view_projects');

        return (int) $this->context->requireCurrentProject()['project_id'];
    }

    private function requiredId(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException('记录编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('记录编号无效。', 422);
        }

        return $id;
    }

    private function userId(): int
    {
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($userId === false || $userId < 1) {
            throw new HttpException('登录状态已失效，请重新登录。', 401);
        }

        return $userId;
    }
}

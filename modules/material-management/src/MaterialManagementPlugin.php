<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use ProjectO\CurrencyCatalog;
use ProjectO\Export\ExportImage;
use ProjectO\Export\XlsxWorkbook;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\ImageUpload;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\R2ObjectStorage;
use ProjectO\Support;
use Throwable;

final class MaterialManagementPlugin implements PluginInterface
{
    private MaterialCatalogService $catalog;
    private ProjectMaterialService $projectMaterials;
    private TenderListService $tenderList;
    private ?ObjectStorageInterface $imageStorage;

    public function __construct(private readonly PluginContext $context)
    {
        $this->catalog = new MaterialCatalogService($context->db);
        $this->projectMaterials = new ProjectMaterialService($context->db);
        $this->tenderList = new TenderListService($context->db);
        // Sample images use the shared platform object storage; without an
        // environment-injected adapter, fall back to the database-configured
        // R2 adapter managed from the document module's system settings.
        $this->imageStorage = $context->objectStorage() ?? new R2ObjectStorage($context->db);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'materials' => $this->materialsIndex(),
            'brands' => $this->brandsIndex(),
            'auxiliaries' => $this->auxiliariesIndex(),
            'suppliers' => $this->suppliersIndex(),
            'supplier-materials' => $this->supplierMaterialsIndex(),
            'quotations' => $this->quotationsIndex(),
            'samples' => $this->samplesIndex(),
            'estimates' => $this->estimatesIndex(),
            'tender-list' => $this->tenderListIndex(),
            'category.save' => $this->saveCategory($input),
            'category.delete' => $this->deleteCategory($input),
            'material.save' => $this->saveMaterial($input),
            'material.delete' => $this->deleteMaterial($input),
            'material.export' => $this->exportMaterials($input),
            'brand.save' => $this->saveBrand($input),
            'brand.delete' => $this->deleteBrand($input),
            'auxiliary.save' => $this->saveAuxiliary($input),
            'auxiliary.delete' => $this->deleteAuxiliary($input),
            'supplier.save' => $this->saveSupplier($input),
            'supplier.delete' => $this->deleteSupplier($input),
            'supplier_material.save' => $this->saveSupplierMaterial($input),
            'supplier_material.delete' => $this->deleteSupplierMaterial($input),
            'quotation.save' => $this->saveQuotation($input),
            'quotation.default' => $this->setDefaultQuotation($input),
            'quotation.clear_default' => $this->clearDefaultQuotation($input),
            'quotation.delete' => $this->deleteQuotation($input),
            'sample.save' => $this->saveSample($input),
            'sample.archive' => $this->archiveSample($input),
            'sample.image.upload' => $this->uploadSampleImage($input),
            'sample.image.view' => $this->viewSampleImage($input),
            'sample.export' => $this->exportSamples($input),
            'estimate.save' => $this->saveEstimate($input),
            'estimate.delete' => $this->deleteEstimate($input),
            'tender.save' => $this->saveTenderItem($input),
            'tender.delete' => $this->deleteTenderItem($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function materialsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        return $this->context->view('materials.php', [
            'title' => '材料管理',
            'currentProject' => $project,
            'data' => $this->catalog->materialData((int) $project['project_id']),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_materials'),
            'canDelete' => Support::hasPermission($this->context->db, 'delete_materials'),
            'nav' => $this->nav(),
        ]);
    }

    private function brandsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_brands');
        return $this->context->view('brands.php', [
            'title' => '品牌管理',
            'brands' => $this->catalog->brandData(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_brands'),
            'canDelete' => Support::hasPermission($this->context->db, 'delete_brands'),
            'nav' => $this->nav(),
        ]);
    }

    private function auxiliariesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        $projectId = $this->currentProjectId();
        return $this->context->view('auxiliaries.php', [
            'title' => '辅材管理',
            'auxiliaries' => $this->catalog->auxiliaryData($projectId),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_auxiliary_materials'),
            'nav' => $this->nav(),
        ]);
    }

    private function suppliersIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_suppliers');
        $data = $this->catalog->supplierData();
        return $this->context->view('suppliers.php', [
            'title' => '供应商管理',
            'suppliers' => $data['suppliers'],
            'canEdit' => Support::hasPermission($this->context->db, 'edit_suppliers'),
            'nav' => $this->nav(),
        ]);
    }

    private function supplierMaterialsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_supplier_materials');
        $data = $this->catalog->supplierData();
        return $this->context->view('supplier-materials.php', [
            'title' => '供应商材料',
            'suppliers' => $data['suppliers'],
            'brands' => $data['brands'],
            'supplierMaterials' => $data['supplierMaterials'],
            'currencies' => CurrencyCatalog::options(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_supplier_materials'),
            'nav' => $this->nav(),
        ]);
    }

    private function quotationsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        return $this->context->view('quotations.php', [
            'title' => '材料报价',
            'currentProject' => $project,
            'data' => $this->catalog->quotationData((int) $project['project_id']),
            'currencies' => CurrencyCatalog::options(),
            'defaultCurrency' => 'CNY',
            'canEdit' => Support::hasPermission($this->context->db, 'edit_material_quotations'),
            'nav' => $this->nav(),
        ]);
    }

    private function samplesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $canViewQuotations = Support::hasPermission($this->context->db, 'view_material_quotations');
        $samples = $this->projectMaterials->listSamples($projectId, $canViewQuotations);
        $this->attachImageProxyUrls($samples);

        $canManageSettings = Support::hasPermission($this->context->db, 'manage_document_settings')
            || Support::hasPermission($this->context->db, 'manage_platform');
        return $this->context->view('samples.php', [
            'title' => '材料样品跟踪',
            'currentProject' => $project,
            'samples' => $samples,
            'choices' => $this->projectMaterials->sampleChoices($projectId, $canViewQuotations),
            'locations' => $this->projectMaterials->locations($projectId),
            'canCreate' => Support::hasPermission($this->context->db, 'add_sample'),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_sample'),
            'canViewQuotations' => $canViewQuotations,
            'canEditSampleQuotation' => $canViewQuotations
                && Support::hasPermission($this->context->db, 'edit_material_quotations'),
            'canArchive' => Support::hasPermission($this->context->db, 'delete_sample'),
            'canManageSettings' => $canManageSettings,
            'storageStatus' => $this->imageStorage instanceof R2ObjectStorage
                ? $this->imageStorage->configurationStatus()
                : null,
            'imageStorageReady' => $this->imageStorage->isConfigured(),
            'nav' => $this->nav(),
        ]);
    }

    private function estimatesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_estimates');
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_materials');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        return $this->context->view('estimates.php', [
            'title' => '材料估算明细',
            'currentProject' => $project,
            'estimates' => $this->projectMaterials->estimateData($projectId),
            'choices' => $this->projectMaterials->estimateChoices($projectId),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_material_estimates'),
            'nav' => $this->nav(),
        ]);
    }

    private function tenderListIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_tenders');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        return $this->context->view('tenders.php', [
            'title' => '招标清单',
            'currentProject' => $project,
            'items' => $this->tenderList->items($projectId),
            'materials' => $this->tenderList->materials($projectId),
            'currencies' => CurrencyCatalog::options(),
            'defaultCurrency' => 'CNY',
            'canEdit' => Support::hasPermission($this->context->db, 'edit_tender_list'),
            'nav' => $this->nav(),
        ]);
    }

    private function saveCategory(array $input): PluginResponse
    {
        $projectId = $this->requireMaterialEdit();
        $id = $this->catalog->saveCategory($projectId, $input);
        return $this->complete('material.category.save', '材料分类已保存。', $id, '?page=materials');
    }

    private function deleteCategory(array $input): PluginResponse
    {
        $projectId = $this->requireMaterialEdit();
        Support::requirePermission($this->context->db, 'delete_materials');
        $id = $this->requiredId($input, 'category_id');
        $this->catalog->deleteCategory($projectId, $id);
        return $this->complete('material.category.delete', '材料分类已删除。', $id, '?page=materials');
    }

    private function saveMaterial(array $input): PluginResponse
    {
        $projectId = $this->requireMaterialEdit();
        $id = $this->catalog->saveMaterial($projectId, $input);
        return $this->complete('material.save', '材料已保存。', $id, '?page=materials');
    }

    private function deleteMaterial(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'delete_materials');
        $projectId = $this->currentProjectId();
        $id = $this->requiredId($input, 'material_id');
        $this->catalog->deleteMaterial($projectId, $id);
        return $this->complete('material.delete', '材料已删除。', $id, '?page=materials');
    }

    private function saveBrand(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_brands');
        Support::requirePermission($this->context->db, 'edit_brands');
        $id = $this->catalog->saveBrand($input);
        return $this->complete('material.brand.save', '品牌已保存。', $id, '?page=brands');
    }

    private function deleteBrand(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_brands');
        Support::requirePermission($this->context->db, 'delete_brands');
        $id = $this->requiredId($input, 'brand_id');
        $this->catalog->deleteBrand($id);
        return $this->complete('material.brand.delete', '品牌已删除。', $id, '?page=brands');
    }

    private function saveAuxiliary(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'edit_auxiliary_materials');
        $id = $this->catalog->saveAuxiliary($this->currentProjectId(), $input);
        return $this->complete('material.auxiliary.save', '辅材已保存。', $id, '?page=auxiliary-materials');
    }

    private function deleteAuxiliary(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'edit_auxiliary_materials');
        $id = $this->requiredId($input, 'aux_id');
        $this->catalog->deleteAuxiliary($this->currentProjectId(), $id);
        return $this->complete('material.auxiliary.delete', '辅材已删除。', $id, '?page=auxiliary-materials');
    }

    private function saveSupplier(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_suppliers');
        Support::requirePermission($this->context->db, 'edit_suppliers');
        $id = $this->catalog->saveSupplier($input);
        return $this->complete('material.supplier.save', '供应商已保存。', $id, '?page=suppliers');
    }

    private function deleteSupplier(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_suppliers');
        Support::requirePermission($this->context->db, 'edit_suppliers');
        $id = $this->requiredId($input, 'supplier_id');
        $this->catalog->deleteSupplier($id);
        return $this->complete('material.supplier.delete', '供应商已删除。', $id, '?page=suppliers');
    }

    private function saveSupplierMaterial(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_supplier_materials');
        Support::requirePermission($this->context->db, 'edit_supplier_materials');
        $id = $this->catalog->saveSupplierMaterial($input);
        return $this->complete('material.supplier_material.save', '供应商材料已保存。', $id, '?page=supplier-materials');
    }

    private function deleteSupplierMaterial(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_supplier_materials');
        Support::requirePermission($this->context->db, 'edit_supplier_materials');
        $id = $this->requiredId($input, 'supplier_material_id');
        $this->catalog->deleteSupplierMaterial($id);
        return $this->complete('material.supplier_material.delete', '供应商材料已删除。', $id, '?page=supplier-materials');
    }

    private function saveQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $id = $this->catalog->saveQuotation($this->currentProjectId(), $input);
        return $this->complete('material.quotation.save', '报价已保存，版本已更新。', $id, '?page=quotations');
    }

    private function setDefaultQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $materialId = $this->requiredId($input, 'material_id');
        $quotationId = $this->requiredId($input, 'quotation_id');
        $this->catalog->setDefaultQuotation($this->currentProjectId(), $materialId, $quotationId);
        return $this->complete('material.quotation.default', '默认报价已设置。', $quotationId, '?page=quotations');
    }

    private function clearDefaultQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $materialId = $this->requiredId($input, 'material_id');
        $this->catalog->clearDefaultQuotation($this->currentProjectId(), $materialId);
        return $this->complete('material.quotation.clear_default', '默认报价已清除。', $materialId, '?page=quotations');
    }

    private function deleteQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $id = $this->requiredId($input, 'quotation_id');
        $this->catalog->deleteQuotation($this->currentProjectId(), $id);
        return $this->complete('material.quotation.delete', '报价已删除。', $id, '?page=quotations');
    }

    private function saveSample(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        $isEdit = isset($input['sample_id']) && $input['sample_id'] !== '';
        if (
            (isset($input['quotation_id']) && $input['quotation_id'] !== '')
            || ($isEdit && array_key_exists('quotation_id', $input))
        ) {
            Support::requirePermission($this->context->db, 'view_material_quotations');
            Support::requirePermission($this->context->db, 'edit_material_quotations');
        }
        if ($isEdit) {
            $this->requireSampleEdit();
        } else {
            $this->requireSampleCreate();
        }
        $project = $this->context->requireCurrentProject();
        $location = $input['location'] ?? null;
        if (!is_string($location) || !preg_match('/^(building|floor|area|room):([1-9]\d*)$/', $location, $matches)) {
            throw new HttpException('请选择当前项目中的有效样品位置。', 422);
        }
        $input['location_type'] = $matches[1];
        $input['location_id'] = $matches[2];
        $sampleId = $this->projectMaterials->saveSample(
            (int) $project['project_id'],
            $this->userId(),
            $input
        );
        return $this->complete('material.sample.save', '样品跟踪记录已保存。', $sampleId, '?page=samples');
    }

    private function archiveSample(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'delete_sample');
        $project = $this->context->requireCurrentProject();
        $sampleId = $this->requiredId($input, 'sample_id');
        $this->projectMaterials->archiveSample(
            (int) $project['project_id'],
            $sampleId,
            $this->userId()
        );
        return $this->complete('material.sample.archive', '样品已删除，跟踪记录与图片保留（软删除）。', $sampleId, '?page=samples');
    }

    private function uploadSampleImage(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'edit_sample');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $sampleId = $this->requiredId($input, 'sample_id');
        $this->projectMaterials->requireActiveSample($projectId, $sampleId);
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException(
                '尚未配置图片存储：请在文件模块“系统设置”完成设置，或核对部署环境变量与 AWS SDK。',
                503
            );
        }

        $file = $this->uploadedFile($input);
        $image = ImageUpload::inspect($file['path']);
        $originalName = ImageUpload::originalName($file['name'], $image['extension']);
        $objectKey = 'samples/' . $projectId . '/' . $sampleId . '/'
            . bin2hex(random_bytes(16)) . '.' . $image['extension'];

        $this->imageStorage->upload($objectKey, $file['path'], $image['mime']);
        try {
            $imageId = $this->projectMaterials->addImage(
                $projectId,
                $sampleId,
                $this->userId(),
                $objectKey,
                $originalName,
                $image['mime'],
                $image['size']
            );
        } catch (Throwable $exception) {
            try {
                $this->imageStorage->delete($objectKey);
            } catch (\RuntimeException $cleanupException) {
                error_log('Unable to remove an unreferenced R2 sample image: ' . get_class($cleanupException));
            }
            throw $exception;
        }

        Support::audit($this->context->db, 'material.sample.image.upload', 'Added image ' . $imageId . ' to sample ' . $sampleId . '.');
        Support::flash('样品图片已上传至私有对象存储。');
        return PluginResponse::redirect('?page=samples');
    }

    private function exportSamples(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $canViewQuotations = Support::hasPermission($this->context->db, 'view_material_quotations');
        $samples = $this->projectMaterials->listSamples($projectId, $canViewQuotations, null);
        $this->attachImageUrls($samples);

        // Column labels and layout follow the legacy 样品信息追踪表 export.
        $headers = [
            '材料代号', '材料名称', '适用范围', '供应商', '样品图片', '样品数量', '颜色',
            '文档状态', '寄送状态', '寄送日期', '业主审批状态', '业主审批日期', '备注',
        ];
        if ($canViewQuotations) {
            array_splice($headers, 4, 0, ['品牌']);
        }
        $imageColumn = $canViewQuotations ? 5 : 4;

        $rows = [];
        $images = [];
        $totalQuantity = 0;
        $sentQuantity = 0;
        $approvedQuantity = 0;
        // Report layout: title row 1, three metadata lines, blank spacer, header row 6, data from row 7.
        $firstDataRow = 7;
        foreach ($samples as $index => $sample) {
            $rowNumber = $firstDataRow + count($rows);
            $quantity = (int) $sample['sample_quantity'];
            $totalQuantity += $quantity;
            if ($sample['sent_status'] === 'sent') {
                $sentQuantity += $quantity;
            }
            if ($sample['owner_approval_status'] === 'approved') {
                $approvedQuantity += $quantity;
            }
            $row = [
                self::exportText($sample['material_code'] ?? null),
                self::exportText($sample['material_name'] ?? null),
                isset($sample['location_name']) && $sample['location_name'] !== ''
                    ? (string) $sample['location_name']
                    : '未指定',
                self::exportText($sample['supplier_name'] ?? null),
            ];
            if ($canViewQuotations) {
                $row[] = self::exportText($sample['quotation_brand_name'] ?? null);
            }
            $row[] = $this->exportImageCell($sample, $images, $rowNumber, $imageColumn);
            $row[] = (int) $sample['sample_quantity'];
            $row[] = isset($sample['color']) && $sample['color'] !== null && $sample['color'] !== ''
                ? '█ ' . $sample['color']
                : '-';
            $row[] = SampleStatusLabels::document((string) $sample['document_status']);
            $row[] = SampleStatusLabels::sent((string) $sample['sent_status']);
            $row[] = self::exportText($sample['sent_date'] ?? null);
            $row[] = SampleStatusLabels::approval((string) $sample['owner_approval_status']);
            $row[] = self::exportText($sample['owner_approval_date'] ?? null);
            $row[] = self::exportText($sample['remarks'] ?? null);
            $rows[] = $row;
        }

        $exportDay = Support::nowLocal('Ymd');
        $exportSequence = Support::nextDailySequence($this->context->db, 'material.sample.export_sequence', $exportDay);
        $body = XlsxWorkbook::build('样品信息追踪表', $headers, $rows, [
            'title' => '📦 ' . $project['project_name'] . '材料样品信息追踪表',
            'meta' => [
                '导出时间: ' . Support::nowLocal('Y-m-d H:i:s'),
                '记录总数: ' . count($rows),
                '内容统计: 总样品数量 ' . $totalQuantity
                    . '，已送样品数量 ' . $sentQuantity
                    . '，已审批样品数量 ' . $approvedQuantity,
            ],
            'images' => $images,
            'fixedWidths' => [$imageColumn => 15.0],
        ]);
        Support::audit(
            $this->context->db,
            'material.sample.export',
            'Exported ' . count($rows) . ' sample rows for project ' . $projectId . '.'
        );

        return PluginResponse::download(
            '材料样品信息表_' . $exportDay . '_' . $exportSequence . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $body
        );
    }

    /**
     * Excel export of the current project's material catalogue, optionally
     * limited to the category selected in the list filter. File name follows the
     * confirmed pattern 项目名材料_分类名_日期.xlsx (全部 when no category is
     * selected), so no daily sequence is appended.
     */
    private function exportMaterials(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $categoryId = $this->optionalCategoryId($input['category_id'] ?? null);
        $category = $categoryId === null ? null : $this->catalog->requireCategory($projectId, $categoryId);
        $materials = $this->catalog->exportRows($projectId, $categoryId);

        $headers = ['序号', '材料代号', '材料名称', '单位', '分类', '规格', '详细说明'];
        $rows = [];
        foreach ($materials as $index => $material) {
            $categoryName = trim((string) ($material['category_name'] ?? ''));
            $rows[] = [
                $index + 1,
                self::exportText($material['material_code'] ?? null),
                (string) $material['material_name'],
                (string) $material['unit'],
                $categoryName === '' ? '未分类' : $categoryName,
                self::exportText($material['specifications'] ?? null),
                self::exportText($material['material_description_detailed'] ?? null),
            ];
        }

        $projectLabel = Support::safeFileSegment((string) $project['project_name']);
        if ($projectLabel === '') {
            $projectLabel = '项目';
        }
        $categoryLabel = $category === null
            ? '全部'
            : (Support::safeFileSegment((string) $category['category_name']) ?: ('分类' . $categoryId));
        $fileName = $projectLabel . '材料_' . $categoryLabel . '_' . Support::nowLocal('Ymd') . '.xlsx';

        $body = XlsxWorkbook::build('材料清单', $headers, $rows, [
            'title' => '📋 ' . $project['project_name'] . '材料清单',
            'meta' => [
                '导出时间: ' . Support::nowLocal('Y-m-d H:i:s'),
                '记录总数: ' . count($rows),
                '导出范围: ' . ($category === null ? '全部材料' : '分类：' . $category['category_name']),
            ],
            // Auto width under-sizes Chinese text; keep the catalogue readable.
            'fixedWidths' => [
                0 => 6.0,
                1 => 16.0,
                2 => 28.0,
                3 => 8.0,
                4 => 14.0,
                5 => 30.0,
                6 => 45.0,
            ],
        ]);
        Support::audit(
            $this->context->db,
            'material.export',
            'Exported ' . count($rows) . ' material rows for project ' . $projectId
                . ($categoryId === null ? ' (all categories).' : ' (category ' . $categoryId . ').')
        );

        return PluginResponse::download(
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $body
        );
    }

    /**
     * Adds short-lived signed read URLs to the sample images so the list
     * thumbnails and the Excel export can embed them without exposing the
     * private bucket.
     *
     * @param list<array<string, mixed>> $samples
     */
    private function viewSampleImage(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $sampleId = $this->requiredId($input, 'sample_id');
        $imageId = $this->requiredId($input, 'image_id');
        if (!$this->imageStorage->isConfigured()) {
            throw new HttpException(
                '尚未配置图片存储：请在文件模块“系统设置”完成设置，或核对部署环境变量与 AWS SDK 安装。',
                503
            );
        }
        $image = $this->projectMaterials->requireImage($projectId, $sampleId, $imageId);
        $contentType = (string) $image['content_type'];
        $extension = ImageUpload::EXTENSIONS[$contentType] ?? 'img';
        try {
            $body = $this->imageStorage->read((string) $image['object_key']);
        } catch (Throwable $exception) {
            error_log('Unable to read sample image ' . $imageId . ': ' . get_class($exception));
            throw new HttpException('图片读取失败，请稍后重试。', 502);
        }

        return PluginResponse::inline(
            '样品图片-' . $sampleId . '-' . $imageId . '.' . $extension,
            $contentType,
            $body
        );
    }

    /**
     * The browser loads images through the application so it never needs
     * direct access to the private object-storage bucket.
     *
     * @param list<array<string, mixed>> $samples
     */
    private function attachImageProxyUrls(array &$samples): void
    {
        if (!$this->imageStorage->isConfigured()) {
            return;
        }
        foreach ($samples as &$sample) {
            foreach ($sample['images'] as &$image) {
                $image['url'] = '?page=sample-image&sample_id=' . (int) $sample['sample_id']
                    . '&image_id=' . (int) $image['image_id'];
            }
            unset($image);
        }
        unset($sample);
    }

    private function imageProxyUrl(int $sampleId, int $imageId): string
    {
        $query = '?page=sample-image&sample_id=' . $sampleId . '&image_id=' . $imageId;
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return $query;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        return $scheme . '://' . $host . $base . '/' . $query;
    }

    private function attachImageUrls(array &$samples): void
    {
        if (!$this->imageStorage->isConfigured()) {
            return;
        }
        foreach ($samples as &$sample) {
            foreach ($sample['images'] as &$image) {
                $image['url'] = $this->imageStorage->signedReadUrl(
                    (string) $image['object_key'],
                    (string) $image['content_type']
                );
            }
            unset($image);
        }
        unset($sample);
    }

    private static function exportText(mixed $value): string
    {
        $text = $value === null ? '' : trim((string) $value);

        return $text === '' ? '-' : $text;
    }

    /**
     * Embeds the first readable sample image into its cell, like the legacy
     * export; the cell stays "-" when storage is unconfigured or an image
     * cannot be fetched.
     *
     * @param array<string, mixed> $sample
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink?: string}> $images
     */
    private function exportImageCell(array $sample, array &$images, int $rowNumber, int $imageColumn): string
    {
        if (!$this->imageStorage->isConfigured()) {
            return '-';
        }
        foreach ($sample['images'] ?? [] as $image) {
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
                'hyperlink' => $this->imageProxyUrl((int) $sample['sample_id'], (int) $image['image_id']),
            ];

            return ''; // the drawing overlays the cell
        }

        return '-';
    }

    private function saveEstimate(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_estimates');
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'edit_material_estimates');
        $project = $this->context->requireCurrentProject();
        $id = $this->projectMaterials->saveEstimate((int) $project['project_id'], $input);
        return $this->complete('material.estimate.save', '材料估算明细已保存。', $id, '?page=estimates');
    }

    private function deleteEstimate(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_estimates');
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'edit_material_estimates');
        $project = $this->context->requireCurrentProject();
        $id = $this->requiredId($input, 'estimate_detail_id');
        $this->projectMaterials->deleteEstimate((int) $project['project_id'], $id);
        return $this->complete('material.estimate.delete', '材料估算明细已删除。', $id, '?page=estimates');
    }

    private function saveTenderItem(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_tenders');
        Support::requirePermission($this->context->db, 'edit_tender_list');
        $id = $this->tenderList->save($this->currentProjectId(), $input);
        return $this->complete('material.tender.save', '招标清单项已保存。', $id, '?page=tender-list');
    }

    private function deleteTenderItem(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_tenders');
        Support::requirePermission($this->context->db, 'edit_tender_list');
        $id = $this->requiredId($input, 'tender_item_id');
        $this->tenderList->delete($this->currentProjectId(), $id);
        return $this->complete('material.tender.delete', '招标清单项已删除。', $id, '?page=tender-list');
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

    private function requireMaterialEdit(): int
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'edit_materials');
        return $this->currentProjectId();
    }

    private function currentProjectId(): int
    {
        Support::requirePermission($this->context->db, 'view_projects');
        return (int) $this->context->requireCurrentProject()['project_id'];
    }

    private function requireSampleCreate(): void
    {
        Support::requirePermission($this->context->db, 'add_sample');
    }

    private function requireSampleEdit(): void
    {
        Support::requirePermission($this->context->db, 'edit_sample');
    }

    private function nav(): array
    {
        return [
            'projects' => Support::hasPermission($this->context->db, 'view_projects'),
            'materials' => Support::hasPermission($this->context->db, 'view_materials')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'brands' => Support::hasPermission($this->context->db, 'view_brands'),
            'auxiliaries' => Support::hasPermission($this->context->db, 'view_materials')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'suppliers' => Support::hasPermission($this->context->db, 'view_suppliers'),
            'supplierMaterials' => Support::hasPermission($this->context->db, 'view_supplier_materials'),
            'quotations' => Support::hasPermission($this->context->db, 'view_material_quotations')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'samples' => Support::hasPermission($this->context->db, 'view_material_samples')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'estimates' => Support::hasPermission($this->context->db, 'view_material_estimates')
                && Support::hasPermission($this->context->db, 'view_materials')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'tenders' => Support::hasPermission($this->context->db, 'view_tenders')
                && Support::hasPermission($this->context->db, 'view_projects'),
        ];
    }

    private function complete(string $action, string $message, int $id, string $location): PluginResponse
    {
        Support::audit($this->context->db, $action, 'Record ' . $id . '.');
        Support::flash($message);
        return PluginResponse::redirect($location);
    }

    /** Null means "all categories"; anything submitted must be a positive id. */
    private function optionalCategoryId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException('材料分类无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('材料分类无效。', 422);
        }

        return $id;
    }

    private function requiredId(array $input, string $key): int
    {
        $value = $input[$key] ?? null;
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

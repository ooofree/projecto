<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use finfo;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Support;
use Throwable;

final class MaterialManagementPlugin implements PluginInterface
{
    private MaterialCatalogService $catalog;
    private ProjectMaterialService $projectMaterials;
    private ?ObjectStorageInterface $imageStorage;

    public function __construct(private readonly PluginContext $context)
    {
        $this->catalog = new MaterialCatalogService($context->db);
        $this->projectMaterials = new ProjectMaterialService($context->db);
        $this->imageStorage = $context->objectStorage();
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
            'category.save' => $this->saveCategory($input),
            'category.delete' => $this->deleteCategory($input),
            'material.save' => $this->saveMaterial($input),
            'material.delete' => $this->deleteMaterial($input),
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
            'estimate.save' => $this->saveEstimate($input),
            'estimate.delete' => $this->deleteEstimate($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function materialsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        return $this->context->view('materials.php', [
            'title' => '材料目录',
            'data' => $this->catalog->materialData(),
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
        return $this->context->view('auxiliaries.php', [
            'title' => '辅材管理',
            'auxiliaries' => $this->catalog->auxiliaryData(),
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
            'canEdit' => Support::hasPermission($this->context->db, 'edit_supplier_materials'),
            'nav' => $this->nav(),
        ]);
    }

    private function quotationsIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        return $this->context->view('quotations.php', [
            'title' => '材料报价',
            'data' => $this->catalog->quotationData(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_material_quotations'),
            'nav' => $this->nav(),
        ]);
    }

    private function samplesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $samples = $this->projectMaterials->listSamples($projectId);
        if ($this->imageStorage?->isConfigured()) {
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

        return $this->context->view('samples.php', [
            'title' => '材料样品跟踪',
            'currentProject' => $project,
            'samples' => $samples,
            'quotations' => $this->projectMaterials->sampleChoices()['quotations'],
            'locations' => $this->projectMaterials->locations($projectId),
            'canCreate' => Support::hasPermission($this->context->db, 'add_sample'),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_sample'),
            'canArchive' => Support::hasPermission($this->context->db, 'delete_sample'),
            'imageStorageReady' => $this->imageStorage?->isConfigured() ?? false,
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

    private function saveCategory(array $input): PluginResponse
    {
        $this->requireMaterialEdit();
        $id = $this->catalog->saveCategory($input);
        return $this->complete('material.category.save', '材料分类已保存。', $id, '?page=materials');
    }

    private function deleteCategory(array $input): PluginResponse
    {
        $this->requireMaterialEdit();
        Support::requirePermission($this->context->db, 'delete_materials');
        $id = $this->requiredId($input, 'category_id');
        $this->catalog->deleteCategory($id);
        return $this->complete('material.category.delete', '材料分类已删除。', $id, '?page=materials');
    }

    private function saveMaterial(array $input): PluginResponse
    {
        $this->requireMaterialEdit();
        $id = $this->catalog->saveMaterial($input);
        return $this->complete('material.save', '材料已保存。', $id, '?page=materials');
    }

    private function deleteMaterial(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'delete_materials');
        $id = $this->requiredId($input, 'material_id');
        $this->catalog->deleteMaterial($id);
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
        $id = $this->catalog->saveAuxiliary($input);
        return $this->complete('material.auxiliary.save', '辅材已保存。', $id, '?page=auxiliary-materials');
    }

    private function deleteAuxiliary(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'edit_auxiliary_materials');
        $id = $this->requiredId($input, 'aux_id');
        $this->catalog->deleteAuxiliary($id);
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
        $id = $this->catalog->saveQuotation($input);
        return $this->complete('material.quotation.save', '报价已保存，版本已更新。', $id, '?page=quotations');
    }

    private function setDefaultQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $materialId = $this->requiredId($input, 'material_id');
        $quotationId = $this->requiredId($input, 'quotation_id');
        $this->catalog->setDefaultQuotation($materialId, $quotationId);
        return $this->complete('material.quotation.default', '默认报价已设置。', $quotationId, '?page=quotations');
    }

    private function clearDefaultQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $materialId = $this->requiredId($input, 'material_id');
        $this->catalog->clearDefaultQuotation($materialId);
        return $this->complete('material.quotation.clear_default', '默认报价已清除。', $materialId, '?page=quotations');
    }

    private function deleteQuotation(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'edit_material_quotations');
        $id = $this->requiredId($input, 'quotation_id');
        $this->catalog->deleteQuotation($id);
        return $this->complete('material.quotation.delete', '报价已删除。', $id, '?page=quotations');
    }

    private function saveSample(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_material_samples');
        Support::requirePermission($this->context->db, 'view_material_quotations');
        Support::requirePermission($this->context->db, 'view_projects');
        if (isset($input['sample_id']) && $input['sample_id'] !== '') {
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
        return $this->complete('material.sample.archive', '样品已归档，图片和跟踪记录保留。', $sampleId, '?page=samples');
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
        if ($this->imageStorage === null || !$this->imageStorage->isConfigured()) {
            throw new HttpException('尚未配置 R2 图片存储，请联系部署管理员。', 503);
        }

        $files = $input['_files'] ?? null;
        $file = is_array($files) ? ($files['image'] ?? null) : null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException('请选择一张有效的样品图片后重试。', 422);
        }
        $temporaryPath = $file['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new HttpException('上传文件校验失败。', 422);
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new HttpException('图片大小必须大于 0 且不超过 5 MB。', 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new HttpException('样品图片仅支持 PNG、JPEG、GIF、BMP 和 WebP 格式。', 422);
        }
        $originalName = $file['name'] ?? '';
        if (!is_string($originalName)) {
            $originalName = '';
        }
        $originalName = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $originalName))) ?? '');
        if ($originalName === '') {
            $originalName = 'sample-image.' . $extensions[$mime];
        }
        $originalName = mb_substr($originalName, 0, 255);
        $objectKey = 'samples/' . $projectId . '/' . $sampleId . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];

        $this->imageStorage->upload($objectKey, $temporaryPath, $mime);
        try {
            $imageId = $this->projectMaterials->addImage(
                $projectId,
                $sampleId,
                $this->userId(),
                $objectKey,
                $originalName,
                $mime,
                $size
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

    private function requireMaterialEdit(): void
    {
        Support::requirePermission($this->context->db, 'view_materials');
        Support::requirePermission($this->context->db, 'edit_materials');
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
            'materials' => Support::hasPermission($this->context->db, 'view_materials'),
            'brands' => Support::hasPermission($this->context->db, 'view_brands'),
            'auxiliaries' => Support::hasPermission($this->context->db, 'view_materials'),
            'suppliers' => Support::hasPermission($this->context->db, 'view_suppliers'),
            'supplierMaterials' => Support::hasPermission($this->context->db, 'view_supplier_materials'),
            'quotations' => Support::hasPermission($this->context->db, 'view_material_quotations'),
            'samples' => Support::hasPermission($this->context->db, 'view_material_samples')
                && Support::hasPermission($this->context->db, 'view_material_quotations')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'estimates' => Support::hasPermission($this->context->db, 'view_material_estimates')
                && Support::hasPermission($this->context->db, 'view_materials')
                && Support::hasPermission($this->context->db, 'view_projects'),
        ];
    }

    private function complete(string $action, string $message, int $id, string $location): PluginResponse
    {
        Support::audit($this->context->db, $action, 'Record ' . $id . '.');
        Support::flash($message);
        return PluginResponse::redirect($location);
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

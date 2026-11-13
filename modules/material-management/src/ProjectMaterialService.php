<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use DateTimeImmutable;
use PDO;
use ProjectO\HttpException;
use Throwable;

final class ProjectMaterialService
{
    public const DOCUMENT_STATUSES = ['pending_submission', 'submitted', 'reviewed'];
    public const SENT_STATUSES = ['not_sent', 'sent', 'partially_sent'];
    public const APPROVAL_STATUSES = ['pending', 'approved', 'rejected'];
    public const LOCATION_TYPES = ['building', 'floor', 'area', 'room'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function sampleChoices(int $projectId, bool $includeQuotations): array
    {
        $materials = $this->db->prepare(
            'SELECT material_id, material_code, material_name
             FROM Materials WHERE project_id = :project_id
             ORDER BY material_name, material_id'
        );
        $materials->execute(['project_id' => $projectId]);
        $quotations = $this->db->prepare(
            'SELECT q.quotation_id, q.material_id, q.currency_code, q.purchase_unit_price,
                    q.quotation_date, sm.supplier_id, m.material_name, m.material_code,
                    sm.supplier_material_name, s.supplier_name
             FROM Material_Quotations q
             JOIN Materials m ON m.material_id = q.material_id AND m.project_id = q.project_id
             JOIN Supplier_Materials sm ON sm.supplier_material_id = q.supplier_material_id
             JOIN Suppliers s ON s.supplier_id = sm.supplier_id
             WHERE q.project_id = :project_id
             ORDER BY m.material_name, q.quotation_date DESC'
        );
        $quotations->execute(['project_id' => $projectId]);
        return [
            'materials' => $materials->fetchAll(),
            'suppliers' => $this->db->query(
                'SELECT supplier_id, supplier_name
                 FROM Suppliers ORDER BY supplier_name, supplier_id'
            )->fetchAll(),
            'quotations' => $includeQuotations ? $quotations->fetchAll() : [],
        ];
    }

    public function locations(int $projectId): array
    {
        $buildings = $this->db->prepare(
            'SELECT building_id, building_number, building_name
             FROM Buildings b WHERE project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
             ORDER BY building_number'
        );
        $buildings->execute(['project_id' => $projectId]);
        $floors = $this->db->prepare(
            'SELECT f.floor_id, f.floor_number, f.floor_name, b.building_number, b.building_name
             FROM Building_Floors f JOIN Buildings b ON b.building_id = f.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                     AND a.project_id = b.project_id
               )
             ORDER BY b.building_number, f.floor_number'
        );
        $floors->execute(['project_id' => $projectId]);
        $areas = $this->db->prepare(
            'SELECT a.area_id, a.area_name, b.building_number, b.building_name
             FROM Construction_Areas a JOIN Buildings b ON b.building_id = a.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                     AND x.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                     AND x.project_id = b.project_id
               )
             ORDER BY b.building_number, a.area_name'
        );
        $areas->execute(['project_id' => $projectId]);
        $rooms = $this->db->prepare(
            'SELECT r.room_id, r.room_number, f.floor_number, f.floor_name, b.building_number, b.building_name
             FROM Rooms r
             JOIN Building_Floors f ON f.floor_id = r.floor_id AND f.building_id = r.building_id
             JOIN Buildings b ON b.building_id = r.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                     AND x.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'floor\' AND x.entity_id = f.floor_id
                     AND x.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'room\' AND x.entity_id = r.room_id
                     AND x.project_id = b.project_id
               )
             ORDER BY b.building_number, f.floor_number, r.room_number'
        );
        $rooms->execute(['project_id' => $projectId]);

        return [
            'buildings' => $buildings->fetchAll(),
            'floors' => $floors->fetchAll(),
            'areas' => $areas->fetchAll(),
            'rooms' => $rooms->fetchAll(),
        ];
    }

    public function listSamples(int $projectId, bool $includeCommercialDetails = true, ?int $limit = 200): array
    {
        $commercialColumns = $includeCommercialDetails
            ? 'q.purchase_unit_price, q.quotation_date, q.currency_code, qbr.brand_name AS quotation_brand_name'
            : 'NULL AS purchase_unit_price, NULL AS quotation_date, NULL AS currency_code, NULL AS quotation_brand_name';
        $quotationJoin = $includeCommercialDetails
            ? 'LEFT JOIN Material_Quotations q
                 ON q.quotation_id = s.quotation_id AND q.project_id = s.project_id
               LEFT JOIN Supplier_Materials sm ON sm.supplier_material_id = q.supplier_material_id
               LEFT JOIN Brands qbr ON qbr.brand_id = sm.brand_id'
            : '';
        $limitClause = $limit === null ? '' : ' LIMIT ' . max(0, $limit);
        $stmt = $this->db->prepare(
            "SELECT s.sample_id, s.project_id, s.material_id, s.supplier_id, s.quotation_id,
                    s.sample_quantity, s.color,
                    s.document_status, s.sent_status, s.sent_date, s.owner_approval_status,
                    s.owner_approval_date, s.remarks, s.created_at, s.updated_at,
                    {$commercialColumns},
                    m.material_name, m.material_code, m.unit,
                    sup.supplier_name,
                    lm.location_type, lm.location_id,
                    b.building_number AS location_building_number,
                    b.building_name AS location_building_name,
                    bf.building_number AS floor_building_number,
                    bf.building_name AS floor_building_name,
                    f.floor_number AS location_floor_number,
                    f.floor_name AS location_floor_name,
                    ba.building_number AS area_building_number,
                    ba.building_name AS area_building_name,
                    a.area_name AS location_area_name,
                    br.building_number AS room_building_number,
                    br.building_name AS room_building_name,
                    rf.floor_number AS room_floor_number,
                    rf.floor_name AS room_floor_name,
                    r.room_number AS location_room_number
             FROM Material_Samples s
             JOIN Sample_Location_Map lm
               ON lm.sample_id = s.sample_id AND lm.project_id = s.project_id
             JOIN Materials m ON m.material_id = s.material_id AND m.project_id = s.project_id
             JOIN Suppliers sup ON sup.supplier_id = s.supplier_id
             {$quotationJoin}
             LEFT JOIN Buildings b
               ON lm.location_type = 'building' AND b.building_id = lm.location_id AND b.project_id = s.project_id
             LEFT JOIN Building_Floors f
               ON lm.location_type = 'floor' AND f.floor_id = lm.location_id
             LEFT JOIN Buildings bf ON bf.building_id = f.building_id AND bf.project_id = s.project_id
             LEFT JOIN Construction_Areas a
               ON lm.location_type = 'area' AND a.area_id = lm.location_id
             LEFT JOIN Buildings ba ON ba.building_id = a.building_id AND ba.project_id = s.project_id
             LEFT JOIN Rooms r ON lm.location_type = 'room' AND r.room_id = lm.location_id
             LEFT JOIN Building_Floors rf ON rf.floor_id = r.floor_id AND rf.building_id = r.building_id
             LEFT JOIN Buildings br ON br.building_id = r.building_id AND br.project_id = s.project_id
             WHERE s.project_id = :project_id AND s.archived_at IS NULL
             ORDER BY s.updated_at DESC, s.sample_id DESC
             {$limitClause}"
        );
        $stmt->execute(['project_id' => $projectId]);
        $samples = $stmt->fetchAll();
        if ($samples === []) {
            return [];
        }

        $images = $this->db->prepare(
            'SELECT image_id, sample_id, object_key, original_name, content_type, file_size, created_at
             FROM Material_Sample_Images
             WHERE project_id = :project_id AND sample_id = :sample_id
             ORDER BY created_at, image_id'
        );
        foreach ($samples as &$sample) {
            $sample['owner_approval_status'] = $this->normalizeApprovalStatus(
                (string) $sample['owner_approval_status']
            );
            $sample['location_name'] = match ($sample['location_type']) {
                'building' => self::joinLocation([$sample['location_building_name']]),
                'floor' => self::joinLocation([
                    $sample['floor_building_name'],
                    self::floorLabel($sample['location_floor_name'] ?? null, $sample['location_floor_number'] ?? null),
                ]),
                'area' => self::joinLocation([$sample['area_building_name'], $sample['location_area_name']]),
                'room' => self::joinLocation([
                    $sample['room_building_name'],
                    self::floorLabel($sample['room_floor_name'] ?? null, $sample['room_floor_number'] ?? null),
                    $sample['location_room_number'],
                ]),
                default => '',
            };
            $images->execute([
                'project_id' => $projectId,
                'sample_id' => (int) $sample['sample_id'],
            ]);
            $sample['images'] = $images->fetchAll();
        }
        unset($sample);
        return $samples;
    }

    /**
     * Joins the non-empty location parts with a single space.
     *
     * @param list<string|null> $parts
     */
    private static function joinLocation(array $parts): string
    {
        $names = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $names[] = $part;
            }
        }

        return implode(' ', $names);
    }

    /**
     * Floors are displayed by name; their number is only a fallback when a
     * floor has no name.
     */
    private static function floorLabel(?string $floorName, ?string $floorNumber): string
    {
        $name = trim((string) $floorName);

        return $name !== '' ? $name : trim((string) $floorNumber);
    }

    public function saveSample(int $projectId, int $userId, array $data): int
    {
        $sampleId = $this->optionalId($data['sample_id'] ?? null, '样品');
        $materialId = $this->requiredId($data['material_id'] ?? null, '材料');
        $supplierId = $this->requiredId($data['supplier_id'] ?? null, '供应商');
        $this->requireProjectMaterial($projectId, $materialId, '材料');
        $this->requireExists('Suppliers', 'supplier_id', $supplierId, '供应商');
        $quotationProvided = array_key_exists('quotation_id', $data);
        $quotationId = $quotationProvided ? $this->optionalId($data['quotation_id'], '报价') : null;
        $quantity = $this->integer($data['sample_quantity'] ?? '0', 0, 100000000, '送样数量');
        $color = $this->optionalText($data['color'] ?? null, 50, '颜色');
        $documentStatus = $this->enum($data['document_status'] ?? 'pending_submission', self::DOCUMENT_STATUSES, '资料状态');
        $sentStatus = $this->enum($data['sent_status'] ?? 'not_sent', self::SENT_STATUSES, '送样状态');
        $approvalStatus = $this->normalizeApprovalStatus(
            $this->enum($data['owner_approval_status'] ?? 'pending', self::APPROVAL_STATUSES, '业主审批状态')
        );
        $sentDate = $this->optionalDate($data['sent_date'] ?? null, '送样日期');
        $approvalDate = $this->optionalDate($data['owner_approval_date'] ?? null, '业主审批日期');
        $remarks = $this->optionalText($data['remarks'] ?? null, 20000, '备注');
        $locationType = $this->enum($data['location_type'] ?? '', self::LOCATION_TYPES, '样品位置类型');
        $locationId = $this->requiredId($data['location_id'] ?? null, '样品位置');
        $this->requireProjectLocation($projectId, $locationType, $locationId);

        $this->db->beginTransaction();
        try {
            if ($sampleId === null) {
                if ($quotationId !== null) {
                    $this->requireSampleQuotation($projectId, $quotationId, $materialId, $supplierId);
                }
                $insert = $this->db->prepare(
                    'INSERT INTO Material_Samples
                        (project_id, material_id, supplier_id, quotation_id, sample_quantity,
                         color, document_status, sent_status, sent_date, owner_approval_status,
                         owner_approval_date, remarks, created_by)
                     VALUES
                        (:project_id, :material_id, :supplier_id, :quotation_id, :quantity,
                         :color, :document_status, :sent_status, :sent_date, :approval_status,
                         :approval_date, :remarks, :created_by)'
                );
                $insert->execute([
                    'project_id' => $projectId,
                    'material_id' => $materialId,
                    'supplier_id' => $supplierId,
                    'quotation_id' => $quotationId,
                    'quantity' => $quantity,
                    'color' => $color,
                    'document_status' => $documentStatus,
                    'sent_status' => $sentStatus,
                    'sent_date' => $sentDate,
                    'approval_status' => $approvalStatus,
                    'approval_date' => $approvalDate,
                    'remarks' => $remarks,
                    'created_by' => $userId,
                ]);
                $sampleId = (int) $this->db->lastInsertId();
                $location = $this->db->prepare(
                    'INSERT INTO Sample_Location_Map
                        (sample_id, project_id, location_type, location_id, remarks)
                     VALUES (:sample_id, :project_id, :location_type, :location_id, :remarks)'
                );
                $location->execute([
                    'sample_id' => $sampleId,
                    'project_id' => $projectId,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'remarks' => $remarks,
                ]);
            } else {
                $this->requireActiveSample($projectId, $sampleId);
                if (!$quotationProvided) {
                    $currentQuotation = $this->db->prepare(
                        'SELECT quotation_id FROM Material_Samples
                         WHERE sample_id = :sample_id AND project_id = :project_id'
                    );
                    $currentQuotation->execute(['sample_id' => $sampleId, 'project_id' => $projectId]);
                    $quotationId = $currentQuotation->fetchColumn();
                    $quotationId = $quotationId === false || $quotationId === null
                        ? null
                        : (int) $quotationId;
                }
                if ($quotationId !== null) {
                    $this->requireSampleQuotation($projectId, $quotationId, $materialId, $supplierId);
                }
                $update = $this->db->prepare(
                    'UPDATE Material_Samples
                     SET material_id = :material_id, supplier_id = :supplier_id,
                         quotation_id = :quotation_id, sample_quantity = :quantity, color = :color,
                         document_status = :document_status, sent_status = :sent_status,
                         sent_date = :sent_date, owner_approval_status = :approval_status,
                         owner_approval_date = :approval_date, remarks = :remarks
                     WHERE sample_id = :sample_id AND project_id = :project_id AND archived_at IS NULL'
                );
                $update->execute([
                    'material_id' => $materialId,
                    'supplier_id' => $supplierId,
                    'quotation_id' => $quotationId,
                    'quantity' => $quantity,
                    'color' => $color,
                    'document_status' => $documentStatus,
                    'sent_status' => $sentStatus,
                    'sent_date' => $sentDate,
                    'approval_status' => $approvalStatus,
                    'approval_date' => $approvalDate,
                    'remarks' => $remarks,
                    'sample_id' => $sampleId,
                    'project_id' => $projectId,
                ]);
                $location = $this->db->prepare(
                    'UPDATE Sample_Location_Map
                     SET location_type = :location_type, location_id = :location_id, remarks = :remarks
                     WHERE sample_id = :sample_id AND project_id = :project_id'
                );
                $location->execute([
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'remarks' => $remarks,
                    'sample_id' => $sampleId,
                    'project_id' => $projectId,
                ]);
            }
            $this->db->commit();
            return $sampleId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function archiveSample(int $projectId, int $sampleId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE Material_Samples
             SET archived_at = CURRENT_TIMESTAMP, archived_by = :user_id
             WHERE sample_id = :sample_id AND project_id = :project_id AND archived_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId, 'sample_id' => $sampleId, 'project_id' => $projectId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('样品不存在、已归档或不属于当前项目。', 404);
        }
    }

    public function requireActiveSample(int $projectId, int $sampleId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Material_Samples
             WHERE sample_id = :sample_id AND project_id = :project_id AND archived_at IS NULL'
        );
        $stmt->execute(['sample_id' => $sampleId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('样品不存在或不属于当前项目。', 404);
        }
    }

    public function addImage(
        int $projectId,
        int $sampleId,
        int $userId,
        string $objectKey,
        string $originalName,
        string $contentType,
        int $fileSize
    ): int {
        $this->requireActiveSample($projectId, $sampleId);
        $stmt = $this->db->prepare(
            'INSERT INTO Material_Sample_Images
                (sample_id, project_id, object_key, original_name, content_type, file_size, uploaded_by)
             VALUES
                (:sample_id, :project_id, :object_key, :original_name, :content_type, :file_size, :uploaded_by)'
        );
        $stmt->execute([
            'sample_id' => $sampleId,
            'project_id' => $projectId,
            'object_key' => $objectKey,
            'original_name' => $originalName,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'uploaded_by' => $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Loads an image of the given sample scoped to the current project.
     *
     * @return array<string, mixed>
     */
    public function requireImage(int $projectId, int $sampleId, int $imageId): array
    {
        $stmt = $this->db->prepare(
            'SELECT image_id, object_key, original_name, content_type
             FROM Material_Sample_Images
             WHERE image_id = :image_id AND sample_id = :sample_id AND project_id = :project_id'
        );
        $stmt->execute([
            'image_id' => $imageId,
            'sample_id' => $sampleId,
            'project_id' => $projectId,
        ]);
        $image = $stmt->fetch();
        if ($image === false) {
            throw new HttpException('图片记录不存在。', 404);
        }

        return $image;
    }

    public function estimateData(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.estimate_detail_id, e.building_id, e.floor_id, e.room_id,
                    e.material_id, e.estimated_quantity, e.remarks,
                    b.building_number, b.building_name, f.floor_number, f.floor_name,
                    r.room_number, m.material_code, m.material_name, m.unit,
                    m.category_id, c.category_name
             FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             JOIN Materials m ON m.material_id = e.material_id AND m.project_id = b.project_id
             LEFT JOIN Material_Categories c
               ON c.category_id = m.category_id AND c.project_id = m.project_id
             LEFT JOIN Building_Floors f ON f.floor_id = e.floor_id AND f.building_id = e.building_id
             LEFT JOIN Rooms r
               ON r.room_id = e.room_id AND r.floor_id = e.floor_id AND r.building_id = e.building_id
             WHERE b.project_id = :project_id
             ORDER BY e.updated_at DESC, e.estimate_detail_id DESC
             LIMIT 500'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function estimateChoices(int $projectId): array
    {
        $buildings = $this->db->prepare(
            'SELECT building_id, building_number, building_name
             FROM Buildings b WHERE project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
             ORDER BY building_number'
        );
        $buildings->execute(['project_id' => $projectId]);
        $floors = $this->db->prepare(
            'SELECT f.floor_id, f.building_id, f.floor_number, f.floor_name
             FROM Building_Floors f JOIN Buildings b ON b.building_id = f.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                     AND a.project_id = b.project_id
               )
             ORDER BY b.building_number, f.floor_number'
        );
        $floors->execute(['project_id' => $projectId]);
        $rooms = $this->db->prepare(
            'SELECT r.room_id, r.building_id, r.floor_id, r.room_number, f.floor_number
             FROM Rooms r
             JOIN Building_Floors f ON f.floor_id = r.floor_id AND f.building_id = r.building_id
             JOIN Buildings b ON b.building_id = r.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                     AND a.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'room\' AND a.entity_id = r.room_id
                     AND a.project_id = b.project_id
               )
             ORDER BY b.building_number, f.floor_number, r.room_number'
        );
        $rooms->execute(['project_id' => $projectId]);
        $materials = $this->db->prepare(
            'SELECT material_id, material_code, material_name, unit
             FROM Materials WHERE project_id = :project_id
             ORDER BY material_name, material_id'
        );
        $materials->execute(['project_id' => $projectId]);
        return [
            'buildings' => $buildings->fetchAll(),
            'floors' => $floors->fetchAll(),
            'rooms' => $rooms->fetchAll(),
            'materials' => $materials->fetchAll(),
        ];
    }

    public function saveEstimate(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['estimate_detail_id'] ?? null, '测算明细');
        $buildingId = $this->requiredId($data['building_id'] ?? null, '楼栋');
        $floorId = $this->optionalId($data['floor_id'] ?? null, '楼层');
        $roomId = $this->optionalId($data['room_id'] ?? null, '房间');
        $materialId = $this->requiredId($data['material_id'] ?? null, '材料');
        $quantity = $this->decimal($data['estimated_quantity'] ?? '0', 8, 4, '测算数量');
        $remarks = $this->optionalText($data['remarks'] ?? null, 20000, '备注');
        $this->requireProjectBuilding($projectId, $buildingId);
        if ($floorId !== null) {
            $this->requireProjectFloor($projectId, $buildingId, $floorId);
        }
        if ($roomId !== null) {
            if ($floorId === null) {
                throw new HttpException('选择房间时必须同时选择楼层。', 422);
            }
            $this->requireProjectRoom($projectId, $buildingId, $floorId, $roomId);
        }
        $this->requireProjectMaterial($projectId, $materialId, '材料');

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Initial_Cost_Estimate_Detail
                    (building_id, floor_id, room_id, material_id, estimated_quantity, remarks)
                 VALUES (:building_id, :floor_id, :room_id, :material_id, :quantity, :remarks)'
            );
            $stmt->execute([
                'building_id' => $buildingId,
                'floor_id' => $floorId,
                'room_id' => $roomId,
                'material_id' => $materialId,
                'quantity' => $quantity,
                'remarks' => $remarks,
            ]);
            return (int) $this->db->lastInsertId();
        }

        $this->requireProjectEstimate($projectId, $id);
        $stmt = $this->db->prepare(
            'UPDATE Initial_Cost_Estimate_Detail
             SET building_id = :building_id, floor_id = :floor_id, room_id = :room_id,
                 material_id = :material_id, estimated_quantity = :quantity, remarks = :remarks
             WHERE estimate_detail_id = :id'
        );
        $stmt->execute([
            'building_id' => $buildingId,
            'floor_id' => $floorId,
            'room_id' => $roomId,
            'material_id' => $materialId,
            'quantity' => $quantity,
            'remarks' => $remarks,
            'id' => $id,
        ]);
        return $id;
    }

    public function deleteEstimate(int $projectId, int $estimateId): void
    {
        $this->requireProjectEstimate($projectId, $estimateId);
        $stmt = $this->db->prepare(
            'DELETE FROM Initial_Cost_Estimate_Detail WHERE estimate_detail_id = :id'
        );
        $stmt->execute(['id' => $estimateId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('测算明细不存在或不属于当前项目。', 404);
        }
    }

    private function requireProjectLocation(int $projectId, string $type, int $locationId): void
    {
        $queries = [
            'building' => 'SELECT 1 FROM Buildings b
                           WHERE b.building_id = :id AND b.project_id = :project_id
                             AND NOT EXISTS (
                                 SELECT 1 FROM project_foundation_archive_records a
                                 WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                                   AND a.project_id = b.project_id
                             )',
            'floor' => 'SELECT 1 FROM Building_Floors f
                        JOIN Buildings b ON b.building_id = f.building_id
                        WHERE f.floor_id = :id AND b.project_id = :project_id
                          AND NOT EXISTS (
                              SELECT 1 FROM project_foundation_archive_records a
                              WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                                AND a.project_id = b.project_id
                          )
                          AND NOT EXISTS (
                              SELECT 1 FROM project_foundation_archive_records a
                              WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                                AND a.project_id = b.project_id
                          )',
            'area' => 'SELECT 1 FROM Construction_Areas a
                       JOIN Buildings b ON b.building_id = a.building_id
                       WHERE a.area_id = :id AND b.project_id = :project_id
                         AND NOT EXISTS (
                             SELECT 1 FROM project_foundation_archive_records x
                             WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                               AND x.project_id = b.project_id
                         )
                         AND NOT EXISTS (
                             SELECT 1 FROM project_foundation_archive_records x
                             WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                               AND x.project_id = b.project_id
                         )',
            'room' => 'SELECT 1 FROM Rooms r
                       JOIN Buildings b ON b.building_id = r.building_id
                       JOIN Building_Floors f ON f.floor_id = r.floor_id AND f.building_id = r.building_id
                       WHERE r.room_id = :id AND b.project_id = :project_id
                         AND NOT EXISTS (
                             SELECT 1 FROM project_foundation_archive_records x
                             WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                               AND x.project_id = b.project_id
                         )
                         AND NOT EXISTS (
                             SELECT 1 FROM project_foundation_archive_records x
                             WHERE x.entity_type = \'floor\' AND x.entity_id = f.floor_id
                               AND x.project_id = b.project_id
                         )
                         AND NOT EXISTS (
                             SELECT 1 FROM project_foundation_archive_records x
                             WHERE x.entity_type = \'room\' AND x.entity_id = r.room_id
                               AND x.project_id = b.project_id
                         )',
        ];
        $sql = $queries[$type];
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $locationId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('样品位置不属于当前项目。', 404);
        }
    }

    private function requireProjectBuilding(int $projectId, int $buildingId): void
    {
        $this->requireProjectEntity(
            'SELECT 1 FROM Buildings b
             WHERE b.building_id = :id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )',
            $projectId,
            $buildingId,
            '楼栋'
        );
    }

    private function requireProjectFloor(int $projectId, int $buildingId, int $floorId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Building_Floors f
             JOIN Buildings b ON b.building_id = f.building_id
             WHERE f.floor_id = :floor_id AND f.building_id = :building_id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                     AND a.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                     AND a.project_id = b.project_id
               )'
        );
        $stmt->execute(['floor_id' => $floorId, 'building_id' => $buildingId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('楼层不属于当前项目或所选楼栋。', 404);
        }
    }

    private function requireProjectRoom(int $projectId, int $buildingId, int $floorId, int $roomId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Rooms r JOIN Buildings b ON b.building_id = r.building_id
             JOIN Building_Floors f ON f.floor_id = r.floor_id AND f.building_id = r.building_id
             WHERE r.room_id = :room_id AND r.building_id = :building_id
               AND r.floor_id = :floor_id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                     AND x.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'floor\' AND x.entity_id = f.floor_id
                     AND x.project_id = b.project_id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'room\' AND x.entity_id = r.room_id
                     AND x.project_id = b.project_id
               )'
        );
        $stmt->execute([
            'room_id' => $roomId,
            'building_id' => $buildingId,
            'floor_id' => $floorId,
            'project_id' => $projectId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('房间不属于当前项目、楼栋或楼层。', 404);
        }
    }

    private function requireProjectEstimate(int $projectId, int $estimateId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             WHERE e.estimate_detail_id = :id AND b.project_id = :project_id'
        );
        $stmt->execute(['id' => $estimateId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('测算明细不存在或不属于当前项目。', 404);
        }
    }

    private function requireProjectEntity(string $sql, int $projectId, int $id, string $label): void
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException($label . '不属于当前项目。', 404);
        }
    }

    private function requireExists(string $table, string $column, int $id, string $label): void
    {
        $stmt = $this->db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException($label . '不存在。', 404);
        }
    }

    private function requireSampleQuotation(
        int $projectId,
        int $quotationId,
        int $materialId,
        int $supplierId
    ): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM Material_Quotations q
             JOIN Supplier_Materials sm ON sm.supplier_material_id = q.supplier_material_id
             WHERE q.project_id = :project_id
               AND q.quotation_id = :quotation_id
               AND q.material_id = :material_id
               AND sm.supplier_id = :supplier_id'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'quotation_id' => $quotationId,
            'material_id' => $materialId,
            'supplier_id' => $supplierId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('所选报价与材料或供应商不匹配。', 422);
        }
    }

    private function requireProjectMaterial(int $projectId, int $materialId, string $label): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Materials WHERE project_id = :project_id AND material_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException($label . '不存在或不属于当前项目。', 404);
        }
    }

    private function optionalId(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->requiredId($value, $label);
    }

    private function requiredId(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException($label . '编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($label . '编号无效。', 422);
        }
        return $id;
    }

    private function integer(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $minimum || $number > $maximum) {
            throw new HttpException($label . '超出允许范围。', 422);
        }
        return $number;
    }

    private function decimal(mixed $value, int $integerDigits, int $scale, string $label): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim((string) $value);
        $pattern = '/^(?:0|[1-9]\d{0,' . ($integerDigits - 1) . '})(?:\.\d{1,' . $scale . '})?$/';
        if (!preg_match($pattern, $value)) {
            throw new HttpException($label . '精度或格式无效。', 422);
        }
        return $value;
    }

    private function optionalText(mixed $value, int $maximum, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim($value);
        if (mb_strlen($value) > $maximum) {
            throw new HttpException($label . '不能超过 ' . $maximum . ' 个字符。', 422);
        }
        return $value === '' ? null : $value;
    }

    private function enum(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new HttpException($label . '无效。', 422);
        }
        return $value;
    }

    private function normalizeApprovalStatus(string $status): string
    {
        return match ($status) {
            '已审批通过', '已通过' => 'approved',
            '已拒绝' => 'rejected',
            '待审批' => 'pending',
            default => $status,
        };
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '无效。', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException($label . '须为有效日期。', 422);
        }
        return $value;
    }
}

<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use DateTimeImmutable;
use PDO;
use ProjectO\CurrencyCatalog;
use ProjectO\HttpException;
use Throwable;

final class MaterialCatalogService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function catalog(int $projectId): array
    {
        return [
            'categories' => $this->projectQuery(
                'SELECT c.category_id, c.category_name, c.parent_category_id, c.sort_order,
                        p.category_name AS parent_name
                 FROM Material_Categories c
                 LEFT JOIN Material_Categories p
                   ON p.category_id = c.parent_category_id AND p.project_id = c.project_id
                 WHERE c.project_id = :project_id
                 ORDER BY c.sort_order, c.category_name, c.category_id',
                $projectId
            ),
            'materials' => $this->projectQuery(
                'SELECT m.material_id, m.material_code, m.material_name, m.unit,
                        m.specifications, m.material_description_detailed, m.category_id,
                        m.default_quotation_id, m.sample_required, c.category_name,
                        (SELECT COUNT(*) FROM Material_Compositions cc
                          WHERE cc.project_id = m.project_id AND cc.parent_material_id = m.material_id) AS child_count,
                        (SELECT COUNT(*) FROM Material_Compositions pc
                          WHERE pc.project_id = m.project_id AND pc.child_material_id = m.material_id) AS parent_count
                 FROM Materials m
                 LEFT JOIN Material_Categories c
                   ON c.category_id = m.category_id AND c.project_id = m.project_id
                 WHERE m.project_id = :project_id
                 ORDER BY m.material_name, m.material_id',
                $projectId
            ),
            'brands' => $this->db->query(
                'SELECT brand_id, brand_name, external_link FROM Brands ORDER BY brand_name, brand_id'
            )->fetchAll(),
            'auxiliaries' => $this->projectQuery(
                'SELECT aux_id, aux_name, unit, description
                 FROM Auxiliary_Materials WHERE project_id = :project_id
                 ORDER BY aux_name, aux_id',
                $projectId
            ),
        ];
        $params['project_id'] = $projectId;
    }

    public function materialData(int $projectId): array
    {
        return [
            'categories' => $this->projectQuery(
                'SELECT c.category_id, c.category_name, c.parent_category_id, c.sort_order,
                        p.category_name AS parent_name
                 FROM Material_Categories c
                 LEFT JOIN Material_Categories p
                   ON p.category_id = c.parent_category_id AND p.project_id = c.project_id
                 WHERE c.project_id = :project_id
                 ORDER BY c.sort_order, c.category_name, c.category_id',
                $projectId
            ),
            'materials' => $this->projectQuery(
                'SELECT m.material_id, m.material_code, m.material_name, m.unit,
                        m.specifications, m.material_description_detailed, m.category_id,
                        m.default_quotation_id, m.sample_required, c.category_name,
                        (SELECT COUNT(*) FROM Material_Compositions cc
                          WHERE cc.project_id = m.project_id AND cc.parent_material_id = m.material_id) AS child_count,
                        (SELECT COUNT(*) FROM Material_Compositions pc
                          WHERE pc.project_id = m.project_id AND pc.child_material_id = m.material_id) AS parent_count
                 FROM Materials m
                 LEFT JOIN Material_Categories c
                   ON c.category_id = m.category_id AND c.project_id = m.project_id
                 WHERE m.project_id = :project_id
                 ORDER BY m.material_name, m.material_id',
                $projectId
            ),
        ];
    }

    /**
     * Material rows for the Excel export, optionally narrowed to one category of
     * the current project (the same category the list page filters by).
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(int $projectId, ?int $categoryId = null): array
    {
        $sql = 'SELECT m.material_id, m.material_code, m.material_name, m.unit,
                       m.specifications, m.material_description_detailed,
                       m.category_id, c.category_name
                FROM Materials m
                LEFT JOIN Material_Categories c
                  ON c.category_id = m.category_id AND c.project_id = m.project_id
                WHERE m.project_id = :project_id';
        $params = ['project_id' => $projectId];
        if ($categoryId !== null) {
            $sql .= ' AND m.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        $sql .= ' ORDER BY c.sort_order, c.category_name, m.material_name, m.material_id';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>
     */
    public function requireCategory(int $projectId, int $categoryId): array
    {
        $statement = $this->db->prepare(
            'SELECT category_id, category_name
             FROM Material_Categories
             WHERE project_id = :project_id AND category_id = :id'
        );
        $statement->execute(['project_id' => $projectId, 'id' => $categoryId]);
        $category = $statement->fetch();
        if ($category === false) {
            throw new HttpException('材料分类不存在或不属于当前项目。', 404);
        }

        return $category;
    }

    public function brandData(): array
    {
        return $this->db->query(
            'SELECT brand_id, brand_name, external_link FROM Brands ORDER BY brand_name, brand_id'
        )->fetchAll();
    }

    public function auxiliaryData(int $projectId): array
    {
        return $this->projectQuery(
            'SELECT aux_id, aux_name, unit, description
             FROM Auxiliary_Materials WHERE project_id = :project_id
             ORDER BY aux_name, aux_id',
            $projectId
        );
    }

    public function supplierData(): array
    {
        return [
            'suppliers' => $this->db->query(
                'SELECT supplier_id, supplier_name, contact_person, phone_number, address,
                        country_code, website_url
                 FROM Suppliers ORDER BY supplier_name, supplier_id'
            )->fetchAll(),
            'supplierMaterials' => $this->db->query(
                'SELECT sm.supplier_material_id, sm.supplier_id, sm.brand_id,
                        sm.supplier_material_code, sm.supplier_material_name,
                        sm.lead_time_days, sm.material_details, sm.specification,
                        sm.coverage_quantity, sm.coverage_unit, sm.price_total, sm.currency_code,
                        s.supplier_name, b.brand_name
                 FROM Supplier_Materials sm
                 JOIN Suppliers s ON s.supplier_id = sm.supplier_id
                 LEFT JOIN Brands b ON b.brand_id = sm.brand_id
                 ORDER BY s.supplier_name, sm.supplier_material_name, sm.supplier_material_id'
            )->fetchAll(),
            'brands' => $this->db->query(
                'SELECT brand_id, brand_name FROM Brands ORDER BY brand_name'
            )->fetchAll(),
        ];
    }

    public function quotationData(int $projectId): array
    {
        return [
            'materials' => $this->projectQuery(
                'SELECT material_id, material_code, material_name, unit, default_quotation_id
                 FROM Materials WHERE project_id = :project_id
                 ORDER BY material_name, material_id',
                $projectId
            ),
            'supplierMaterials' => $this->db->query(
                'SELECT sm.supplier_material_id, sm.supplier_id, sm.supplier_material_code,
                        sm.supplier_material_name, s.supplier_name
                 FROM Supplier_Materials sm
                 JOIN Suppliers s ON s.supplier_id = sm.supplier_id
                 ORDER BY s.supplier_name, sm.supplier_material_name'
            )->fetchAll(),
            'quotations' => $this->projectQuery(
                'SELECT q.quotation_id, q.material_id, q.supplier_material_id,
                        q.purchase_unit_price, q.quotation_date, q.currency_code,
                        q.min_order_quantity, q.confirmed_tax_rate, q.version,
                        m.material_name, m.material_code, sm.supplier_material_name,
                        sm.supplier_material_code, s.supplier_name
                 FROM Material_Quotations q
                 JOIN Materials m ON m.material_id = q.material_id
                 JOIN Supplier_Materials sm ON sm.supplier_material_id = q.supplier_material_id
                 JOIN Suppliers s ON s.supplier_id = sm.supplier_id
                 WHERE q.project_id = :project_id AND m.project_id = q.project_id
                 ORDER BY m.material_name, q.quotation_date DESC, q.quotation_id DESC',
                $projectId
            ),
        ];
    }

    public function saveCategory(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['category_id'] ?? null);
        $name = $this->requiredText($data, 'category_name', 255, '材料分类名称');
        $parentId = $this->optionalId($data['parent_category_id'] ?? null);
        $sortOrder = $this->integer($data['sort_order'] ?? '0', 0, 1000000, '排序值');
        if ($parentId !== null) {
            if ($id === $parentId) {
                throw new HttpException('分类不能作为自己的上级。', 422);
            }
            $this->requireProjectCategory($projectId, $parentId, '上级分类');
            $cursor = $parentId;
            while ($cursor !== null) {
                $stmt = $this->db->prepare(
                    'SELECT parent_category_id FROM Material_Categories
                     WHERE category_id = :id AND project_id = :project_id'
                );
                $stmt->execute(['id' => $cursor, 'project_id' => $projectId]);
                $next = $stmt->fetchColumn();
                if ($next === false) {
                    break;
                }
                $cursor = $next === null ? null : (int) $next;
                if ($id !== null && $cursor === $id) {
                    throw new HttpException('分类不能移动到自己的下级分类。', 422);
                }
            }
        }

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Material_Categories (project_id, category_name, parent_category_id, sort_order)
                 VALUES (:project_id, :name, :parent_id, :sort_order)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'name' => $name,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
            ]);
            return (int) $this->db->lastInsertId();
        }
        $this->requireProjectCategory($projectId, $id, '材料分类');
        $stmt = $this->db->prepare(
            'UPDATE Material_Categories
             SET category_name = :name, parent_category_id = :parent_id, sort_order = :sort_order
             WHERE category_id = :id AND project_id = :project_id'
        );
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'id' => $id,
            'project_id' => $projectId,
        ]);
        return $id;
    }

    public function deleteCategory(int $projectId, int $categoryId): void
    {
        $this->requireProjectCategory($projectId, $categoryId, '材料分类');
        foreach ([
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Categories WHERE project_id = :project_id AND parent_category_id = :id',
                'message' => '该分类仍有下级分类，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Materials WHERE project_id = :project_id AND category_id = :id',
                'message' => '该分类仍有材料，不能删除。',
            ],
        ] as $check) {
            $stmt = $this->db->prepare($check['sql']);
            $stmt->execute(['project_id' => $projectId, 'id' => $categoryId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new HttpException($check['message'], 409);
            }
        }
        $stmt = $this->db->prepare(
            'DELETE FROM Material_Categories WHERE category_id = :id AND project_id = :project_id'
        );
        $stmt->execute(['id' => $categoryId, 'project_id' => $projectId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('材料分类不存在或不属于当前项目。', 404);
        }
    }

    public function saveMaterial(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['material_id'] ?? null);
        $code = $this->optionalText($data, 'material_code', 50, '材料编码');
        $name = $this->requiredText($data, 'material_name', 255, '材料名称');
        $unit = $this->requiredText($data, 'unit', 50, '计量单位');
        $specifications = $this->optionalText($data, 'specifications', 20000, '规格');
        $description = $this->optionalText($data, 'material_description_detailed', 20000, '材料说明');
        $categoryId = $this->optionalId($data['category_id'] ?? null);
        if ($categoryId !== null) {
            $this->requireProjectCategory($projectId, $categoryId, '材料分类');
        }
        // 送样要求：显式传值优先；新建未传默认需送样；编辑未传保留原值
        // （快速录入等不带该字段的入口不改变既有口径）。
        $sampleRequired = $this->optionalSampleRequirement($data['sample_required'] ?? null);

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Materials
                    (project_id, material_code, material_name, unit, specifications, material_description_detailed, category_id, sample_required, sample_requirement_explicit)
                 VALUES (:project_id, :code, :name, :unit, :specifications, :description, :category_id, :sample_required, :explicit)'
            );
            try {
                $stmt->execute([
                    'project_id' => $projectId,
                    'code' => $code,
                    'name' => $name,
                    'unit' => $unit,
                    'specifications' => $specifications,
                    'description' => $description,
                    'category_id' => $categoryId,
                    'sample_required' => $sampleRequired ?? 'required',
                    // 新建即显式选择送样要求视为人工决定，组成不再覆盖。
                    'explicit' => $sampleRequired !== null ? 1 : 0,
                ]);
            } catch (\PDOException $exception) {
                $this->throwIfDuplicateMaterialCode($exception, $projectId, $code, $id);
                throw $exception;
            }
            return (int) $this->db->lastInsertId();
        }

        $this->requireProjectMaterial($projectId, $id, '材料');
        $sql = 'UPDATE Materials
             SET material_code = :code, material_name = :name, unit = :unit,
                 specifications = :specifications, material_description_detailed = :description,
                 category_id = :category_id';
        $params = [
            'code' => $code,
            'name' => $name,
            'unit' => $unit,
            'specifications' => $specifications,
            'description' => $description,
            'category_id' => $categoryId,
            'id' => $id,
            'project_id' => $projectId,
        ];
        if ($sampleRequired !== null) {
            // 人工显式设置持久化：此后组成增删不再自动覆盖送样要求。
            $sql .= ', sample_required = :sample_required, sample_requirement_explicit = 1';
            $params['sample_required'] = $sampleRequired;
        }
        $sql .= ' WHERE material_id = :id AND project_id = :project_id';
        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (\PDOException $exception) {
            $this->throwIfDuplicateMaterialCode($exception, $projectId, $code, $id);
            throw $exception;
        }
        return $id;
    }

    public function deleteMaterial(int $projectId, int $materialId): void
    {
        $this->requireProjectMaterial($projectId, $materialId, '材料');
        foreach ([
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Quotations WHERE project_id = :project_id AND material_id = :id',
                'message' => '该材料已用于报价，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Initial_Cost_Estimate_Detail e
                          JOIN Buildings b ON b.building_id = e.building_id
                          WHERE b.project_id = :project_id AND e.material_id = :id',
                'message' => '该材料已用于材料测算明细，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Samples WHERE project_id = :project_id AND material_id = :id',
                'message' => '该材料已用于材料样品，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Tender_List WHERE project_id = :project_id AND material_id = :id',
                'message' => '该材料已用于招标清单，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Compositions WHERE project_id = :project_id AND parent_material_id = :id',
                'message' => '该材料仍有组成子材料，请先移除组成关系再删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Compositions WHERE project_id = :project_id AND child_material_id = :id',
                'message' => '该材料已被其他材料的组成引用，请先移除组成关系再删除。',
            ],
        ] as $check) {
            $stmt = $this->db->prepare($check['sql']);
            $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new HttpException($check['message'], 409);
            }
        }
        $stmt = $this->db->prepare(
            'DELETE FROM Materials WHERE material_id = :id AND project_id = :project_id'
        );
        $stmt->execute(['id' => $materialId, 'project_id' => $projectId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('材料不存在或不属于当前项目。', 404);
        }
    }

    /**
     * Composition rows of one parent material (BOM-style, single level). The
     * quantity coefficient mirrors the legacy BOM_Components.quantity: how many
     * child units one parent unit consumes; the unit comes from Materials.unit.
     *
     * @return list<array<string, mixed>>
     */
    public function compositionsForParent(int $projectId, int $parentMaterialId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cc.composition_id, cc.parent_material_id, cc.child_material_id, cc.quantity,
                    m.material_code, m.material_name, m.unit, m.sample_required
             FROM Material_Compositions cc
             JOIN Materials m ON m.material_id = cc.child_material_id AND m.project_id = cc.project_id
             WHERE cc.project_id = :project_id AND cc.parent_material_id = :parent_id
             ORDER BY m.material_name, m.material_id'
        );
        $stmt->execute(['project_id' => $projectId, 'parent_id' => $parentMaterialId]);
        return $stmt->fetchAll();
    }

    /**
     * Create or update one parent→child composition edge. Enforces the confirmed
     * single-level rule in both directions inside a transaction: a material that
     * already has children cannot become a child, and a material already used as
     * a child cannot gain children. Composition never derives sample obligations;
     * only the material's first-ever edge flips sample_required to exempt, gated
     * by the persistent sample_requirement_explicit flag so a manual choice is
     * never overwritten again (even after all edges are removed and re-added).
     *
     * Concurrency: both endpoint material rows are locked FOR UPDATE in ascending
     * id order (MySQL) before any re-check, so conflicting requests serialise on
     * the same locks regardless of direction; the single-level and duplicate
     * checks then run on committed state. Unique keys remain as a last resort and
     * their violations are translated into business errors.
     */
    public function saveComposition(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['composition_id'] ?? null);
        $parentId = $this->requiredId($data['parent_material_id'] ?? null, '父材料');
        $childId = $this->requiredId($data['child_material_id'] ?? null, '子材料');
        $quantity = $this->requiredDecimal($data['quantity'] ?? null, 8, 2, '数量系数');
        if ((float) $quantity <= 0) {
            throw new HttpException('数量系数必须大于 0。', 422);
        }
        if ($parentId === $childId) {
            throw new HttpException('材料不能作为自己的组成子材料。', 422);
        }
        $this->requireProjectMaterial($projectId, $parentId, '父材料');
        $this->requireProjectMaterial($projectId, $childId, '子材料');

        $this->db->beginTransaction();
        try {
            // 锁住两端材料行（升序，避免死锁），随后在已提交状态上复查。
            $this->lockCompositionMaterials($projectId, $parentId, $childId);
            $existing = null;
            if ($id !== null) {
                $stmt = $this->db->prepare(
                    'SELECT composition_id, parent_material_id, child_material_id
                     FROM Material_Compositions
                     WHERE composition_id = :id AND project_id = :project_id'
                );
                $stmt->execute(['id' => $id, 'project_id' => $projectId]);
                $existing = $stmt->fetch();
                if ($existing === false) {
                    throw new HttpException('组成关系不存在或不属于当前项目。', 404);
                }
                if ((int) $existing['parent_material_id'] !== $parentId) {
                    throw new HttpException('组成关系的父材料不能变更；请删除后重新添加。', 422);
                }
                if ((int) $existing['child_material_id'] !== $childId) {
                    // 换绑子件同样要锁定新旧两端，防止与并发写入交错。
                    $this->lockCompositionMaterials($projectId, (int) $existing['child_material_id'], $childId);
                }
            }
            $this->requireSingleLevelComposition($projectId, $parentId, $childId);
            $duplicate = $this->db->prepare(
                'SELECT composition_id FROM Material_Compositions
                 WHERE project_id = :project_id AND parent_material_id = :parent_id AND child_material_id = :child_id'
            );
            $duplicate->execute(['project_id' => $projectId, 'parent_id' => $parentId, 'child_id' => $childId]);
            $duplicateId = $duplicate->fetchColumn();
            if ($duplicateId !== false && ($id === null || (int) $duplicateId !== $id)) {
                throw new HttpException('该子材料已在组成清单中，请直接修改其数量系数。', 409);
            }

            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Material_Compositions (project_id, parent_material_id, child_material_id, quantity)
                     VALUES (:project_id, :parent_id, :child_id, :quantity)'
                );
                try {
                    $stmt->execute([
                        'project_id' => $projectId,
                        'parent_id' => $parentId,
                        'child_id' => $childId,
                        'quantity' => $quantity,
                    ]);
                } catch (\PDOException $exception) {
                    $this->throwIfDuplicateComposition($exception);
                    throw $exception;
                }
                $id = (int) $this->db->lastInsertId();
                // 首次组成默认翻转：以持久化标记为准，而非当前边数。
                // 人工改回需送样会置 explicit=1，此后任何组成增删都不再覆盖。
                $flip = $this->db->prepare(
                    "UPDATE Materials SET sample_required = 'exempt'
                     WHERE project_id = :project_id AND material_id = :id
                       AND sample_required = 'required' AND sample_requirement_explicit = 0"
                );
                $flip->execute(['project_id' => $projectId, 'id' => $parentId]);
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE Material_Compositions
                     SET child_material_id = :child_id, quantity = :quantity
                     WHERE composition_id = :id AND project_id = :project_id'
                );
                try {
                    $stmt->execute([
                        'child_id' => $childId,
                        'quantity' => $quantity,
                        'id' => $id,
                        'project_id' => $projectId,
                    ]);
                } catch (\PDOException $exception) {
                    $this->throwIfDuplicateComposition($exception);
                    throw $exception;
                }
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteComposition(int $projectId, int $compositionId): void
    {
        $this->db->beginTransaction();
        try {
            $edge = $this->db->prepare(
                'SELECT parent_material_id, child_material_id FROM Material_Compositions
                 WHERE composition_id = :id AND project_id = :project_id'
            );
            $edge->execute(['id' => $compositionId, 'project_id' => $projectId]);
            $row = $edge->fetch();
            if ($row === false) {
                throw new HttpException('组成关系不存在或不属于当前项目。', 404);
            }
            // 与创建/改绑共用同一组行锁，避免删除与写入交错产生层级或重复。
            $this->lockCompositionMaterials($projectId, (int) $row['parent_material_id'], (int) $row['child_material_id']);
            $stmt = $this->db->prepare(
                'DELETE FROM Material_Compositions WHERE composition_id = :id AND project_id = :project_id'
            );
            $stmt->execute(['id' => $compositionId, 'project_id' => $projectId]);
            if ($stmt->rowCount() !== 1) {
                throw new HttpException('组成关系不存在或不属于当前项目。', 404);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        // 删除组成不自动翻转送样要求：是否恢复送样由人工在材料表单决定。
    }

    /**
     * Lock the endpoint material rows in ascending id order so any two requests
     * touching the same materials acquire locks in the same order (no deadlock).
     * MySQL only: SQLite serialises writers at commit time and the service
     * re-checks plus unique keys keep the invariants there.
     */
    private function lockCompositionMaterials(int $projectId, int ...$materialIds): void
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $ids = array_values(array_unique(array_map('intval', $materialIds)));
        sort($ids);
        $stmt = $this->db->prepare(
            'SELECT material_id FROM Materials
             WHERE project_id = :project_id AND material_id = :id FOR UPDATE'
        );
        foreach ($ids as $materialId) {
            $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
            $stmt->fetchColumn();
        }
    }

    /**
     * Translate a composition unique-key violation (the last-resort guard under
     * concurrency) into the same business error as the pre-check.
     */
    private function throwIfDuplicateComposition(\PDOException $exception): void
    {
        if ((string) $exception->getCode() !== '23000') {
            return;
        }
        $message = $exception->getMessage();
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isDuplicate = ($driver === 'mysql' && str_contains($message, 'uq_composition_project_parent_child'))
            || ($driver === 'sqlite' && str_contains($message, 'UNIQUE constraint failed'));
        if ($isDuplicate) {
            throw new HttpException('该子材料已在组成清单中，请直接修改其数量系数。', 409);
        }
    }

    /**
     * Single-level guard in both directions. Callers hold the endpoint row locks
     * (MySQL) so these checks run on committed state; the unique key remains the
     * final backstop.
     */
    private function requireSingleLevelComposition(int $projectId, int $parentId, int $childId): void
    {
        $childHasChildren = $this->db->prepare(
            'SELECT COUNT(*) FROM Material_Compositions
             WHERE project_id = :project_id AND parent_material_id = :id'
        );
        $childHasChildren->execute(['project_id' => $projectId, 'id' => $childId]);
        if ((int) $childHasChildren->fetchColumn() > 0) {
            throw new HttpException('该子材料自身已有组成子项；仅支持父—子单层，不能多层嵌套。', 422);
        }
        $parentIsChild = $this->db->prepare(
            'SELECT COUNT(*) FROM Material_Compositions
             WHERE project_id = :project_id AND child_material_id = :id'
        );
        $parentIsChild->execute(['project_id' => $projectId, 'id' => $parentId]);
        if ((int) $parentIsChild->fetchColumn() > 0) {
            throw new HttpException('父材料已是其他材料的组成子项；仅支持父—子单层，不能多层嵌套。', 422);
        }
    }

    private function optionalSampleRequirement(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !in_array($value, ['required', 'exempt'], true)) {
            throw new HttpException('送样要求无效。', 422);
        }
        return $value;
    }

    public function saveBrand(array $data): int
    {
        $id = $this->optionalId($data['brand_id'] ?? null);
        $name = $this->requiredText($data, 'brand_name', 255, '品牌名称');
        $url = $this->optionalUrl($data, 'external_link', '品牌链接');
        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Brands (brand_name, external_link) VALUES (:name, :url)'
            );
            $stmt->execute(['name' => $name, 'url' => $url]);
            return (int) $this->db->lastInsertId();
        }
        $this->requireExists('Brands', 'brand_id', $id, '品牌');
        $stmt = $this->db->prepare(
            'UPDATE Brands SET brand_name = :name, external_link = :url WHERE brand_id = :id'
        );
        $stmt->execute(['name' => $name, 'url' => $url, 'id' => $id]);
        return $id;
    }

    public function deleteBrand(int $brandId): void
    {
        $this->requireUnused(
            'SELECT COUNT(*) FROM Supplier_Materials WHERE brand_id = :id',
            $brandId,
            '该品牌已被供应商材料使用，不能删除。'
        );
        $this->deleteById('Brands', 'brand_id', $brandId, '品牌');
    }

    public function saveSupplier(array $data): int
    {
        $id = $this->optionalId($data['supplier_id'] ?? null);
        $name = $this->requiredText($data, 'supplier_name', 255, '供应商名称');
        $contact = $this->optionalText($data, 'contact_person', 255, '联系人');
        $phone = $this->optionalText($data, 'phone_number', 100, '联系电话');
        $address = $this->optionalText($data, 'address', 20000, '地址');
        $country = $this->optionalText($data, 'country_code', 2, '国家代码');
        if ($country !== null) {
            $country = strtoupper($country);
            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                throw new HttpException('国家代码须为两位 ISO 格式。', 422);
            }
        }
        $url = $this->optionalUrl($data, 'website_url', '供应商网站');
        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Suppliers
                    (supplier_name, contact_person, phone_number, address, country_code, website_url)
                 VALUES (:name, :contact, :phone, :address, :country, :url)'
            );
        } else {
            $this->requireExists('Suppliers', 'supplier_id', $id, '供应商');
            $stmt = $this->db->prepare(
                'UPDATE Suppliers SET supplier_name = :name, contact_person = :contact,
                    phone_number = :phone, address = :address, country_code = :country, website_url = :url
                 WHERE supplier_id = :id'
            );
        }
        $params = [
            'name' => $name,
            'contact' => $contact,
            'phone' => $phone,
            'address' => $address,
            'country' => $country,
            'url' => $url,
        ];
        if ($id !== null) {
            $params['id'] = $id;
        }
        $stmt->execute($params);
        return $id ?? (int) $this->db->lastInsertId();
    }

    public function deleteSupplier(int $supplierId): void
    {
        $this->requireUnused(
            'SELECT COUNT(*) FROM Supplier_Materials WHERE supplier_id = :id',
            $supplierId,
            '该供应商仍有供应商材料，不能删除。'
        );
        $this->deleteById('Suppliers', 'supplier_id', $supplierId, '供应商');
    }

    public function saveSupplierMaterial(array $data): int
    {
        $id = $this->optionalId($data['supplier_material_id'] ?? null);
        $supplierId = $this->requiredId($data['supplier_id'] ?? null, '供应商');
        $this->requireExists('Suppliers', 'supplier_id', $supplierId, '供应商');
        $brandId = $this->optionalId($data['brand_id'] ?? null);
        if ($brandId !== null) {
            $this->requireExists('Brands', 'brand_id', $brandId, '品牌');
        }
        $code = $this->requiredText($data, 'supplier_material_code', 100, '供应商材料编号');
        $name = $this->requiredText($data, 'supplier_material_name', 255, '供应商材料名称');
        $leadTime = $this->optionalInt($data['lead_time_days'] ?? null, 0, 36500, '交期');
        $details = $this->optionalText($data, 'material_details', 20000, '材料详情');
        $specification = $this->optionalText($data, 'specification', 20000, '规格');
        $coverageQuantity = $this->optionalDecimal($data['coverage_quantity'] ?? null, 12, 4, '覆盖数量');
        $coverageUnit = $this->optionalText($data, 'coverage_unit', 50, '覆盖单位');
        $price = $this->optionalDecimal($data['price_total'] ?? null, 14, 4, '总价');
        $currency = $this->optionalCurrency($data['currency_code'] ?? null);
        $fields = [
            'supplier_id' => $supplierId,
            'brand_id' => $brandId,
            'supplier_material_code' => $code,
            'supplier_material_name' => $name,
            'lead_time_days' => $leadTime,
            'material_details' => $details,
            'specification' => $specification,
            'coverage_quantity' => $coverageQuantity,
            'coverage_unit' => $coverageUnit,
            'price_total' => $price,
            'currency_code' => $currency,
        ];
        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Supplier_Materials
                    (supplier_id, brand_id, supplier_material_code, supplier_material_name,
                     lead_time_days, material_details, specification, coverage_quantity,
                     coverage_unit, price_total, currency_code)
                 VALUES
                    (:supplier_id, :brand_id, :supplier_material_code, :supplier_material_name,
                     :lead_time_days, :material_details, :specification, :coverage_quantity,
                     :coverage_unit, :price_total, :currency_code)'
            );
            $stmt->execute($fields);
            return (int) $this->db->lastInsertId();
        }

        $this->requireExists('Supplier_Materials', 'supplier_material_id', $id, '供应商材料');
        $fields['id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE Supplier_Materials SET supplier_id = :supplier_id, brand_id = :brand_id,
                supplier_material_code = :supplier_material_code,
                supplier_material_name = :supplier_material_name, lead_time_days = :lead_time_days,
                material_details = :material_details, specification = :specification,
                coverage_quantity = :coverage_quantity, coverage_unit = :coverage_unit,
                price_total = :price_total, currency_code = :currency_code
             WHERE supplier_material_id = :id'
        );
        $stmt->execute($fields);
        return $id;
    }

    public function deleteSupplierMaterial(int $supplierMaterialId): void
    {
        $this->requireUnused(
            'SELECT COUNT(*) FROM Material_Quotations WHERE supplier_material_id = :id',
            $supplierMaterialId,
            '该供应商材料已用于报价，不能删除。'
        );
        $this->deleteById('Supplier_Materials', 'supplier_material_id', $supplierMaterialId, '供应商材料');
    }

    public function saveQuotation(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['quotation_id'] ?? null);
        $materialId = $this->requiredId($data['material_id'] ?? null, '材料');
        $supplierMaterialId = $this->requiredId($data['supplier_material_id'] ?? null, '供应商材料');
        $this->requireProjectMaterial($projectId, $materialId, '材料');
        $this->requireExists('Supplier_Materials', 'supplier_material_id', $supplierMaterialId, '供应商材料');
        $price = $this->requiredDecimal($data['purchase_unit_price'] ?? null, 14, 4, '采购单价');
        $date = $this->requiredDate($data['quotation_date'] ?? null, '报价日期');
        $currency = $this->requiredCurrency($data['currency_code'] ?? null);
        $minimum = $this->optionalDecimal($data['min_order_quantity'] ?? null, 8, 4, '最小起订量');
        $taxPercent = $this->optionalPercentage($data['confirmed_tax_rate'] ?? '0');
        $taxRate = number_format(((float) ($taxPercent ?? '0')) / 100, 4, '.', '');

        $this->db->beginTransaction();
        try {
            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Material_Quotations
                        (project_id, supplier_material_id, material_id, purchase_unit_price, quotation_date,
                         currency_code, min_order_quantity, confirmed_tax_rate, version)
                     VALUES
                        (:project_id, :supplier_material_id, :material_id, :price, :quotation_date,
                         :currency, :minimum, :tax_rate, 1)'
                );
                $stmt->execute([
                    'project_id' => $projectId,
                    'supplier_material_id' => $supplierMaterialId,
                    'material_id' => $materialId,
                    'price' => $price,
                    'quotation_date' => $date,
                    'currency' => $currency,
                    'minimum' => $minimum,
                    'tax_rate' => $taxRate,
                ]);
                $id = (int) $this->db->lastInsertId();
            } else {
                $expectedVersion = $this->requiredId($data['version'] ?? null, '报价版本');
                $check = $this->db->prepare(
                    'SELECT version, material_id FROM Material_Quotations
                     WHERE quotation_id = :id AND project_id = :project_id'
                );
                $check->execute(['id' => $id, 'project_id' => $projectId]);
                $current = $check->fetch();
                if (!$current) {
                    throw new HttpException('报价不存在。', 404);
                }
                if ((int) $current['material_id'] !== $materialId) {
                    throw new HttpException('报价所属材料不能更新变更；请为其他材料新建报价。', 422);
                }
                if ((int) $current['version'] !== $expectedVersion) {
                    throw new HttpException('报价已被其他用户更新，请刷新页面后重试。', 409);
                }
                $stmt = $this->db->prepare(
                    'UPDATE Material_Quotations
                     SET supplier_material_id = :supplier_material_id, material_id = :material_id,
                         purchase_unit_price = :price, quotation_date = :quotation_date,
                         currency_code = :currency, min_order_quantity = :minimum,
                         confirmed_tax_rate = :tax_rate, version = version + 1
                     WHERE quotation_id = :id AND project_id = :project_id
                       AND version = :expected_version'
                );
                $stmt->execute([
                    'project_id' => $projectId,
                    'supplier_material_id' => $supplierMaterialId,
                    'material_id' => $materialId,
                    'price' => $price,
                    'quotation_date' => $date,
                    'currency' => $currency,
                    'minimum' => $minimum,
                    'tax_rate' => $taxRate,
                    'id' => $id,
                    'expected_version' => $expectedVersion,
                ]);
                if ($stmt->rowCount() !== 1) {
                    throw new HttpException('报价已被其他用户更新，请刷新页面后重试。', 409);
                }
            }

            $findMapping = $this->db->prepare(
                'SELECT mapping_id FROM Material_Mappings
                 WHERE project_id = :project_id AND quotation_id = :quotation_id'
            );
            $findMapping->execute(['project_id' => $projectId, 'quotation_id' => $id]);
            $mappingId = $findMapping->fetchColumn();
            if ($mappingId === false) {
                $mapping = $this->db->prepare(
                    'INSERT INTO Material_Mappings
                        (project_id, material_id, quotation_id, supplier_material_id)
                     VALUES (:project_id, :material_id, :quotation_id, :supplier_material_id)'
                );
                $mapping->execute([
                    'project_id' => $projectId,
                    'material_id' => $materialId,
                    'quotation_id' => $id,
                    'supplier_material_id' => $supplierMaterialId,
                ]);
            } else {
                $mapping = $this->db->prepare(
                    'UPDATE Material_Mappings
                     SET material_id = :material_id, supplier_material_id = :supplier_material_id
                     WHERE mapping_id = :mapping_id'
                );
                $mapping->execute([
                    'material_id' => $materialId,
                    'supplier_material_id' => $supplierMaterialId,
                    'mapping_id' => (int) $mappingId,
                ]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function setDefaultQuotation(int $projectId, int $materialId, int $quotationId): void
    {
        $this->requireProjectMaterial($projectId, $materialId, '材料');
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Material_Quotations
             WHERE project_id = :project_id AND quotation_id = :quotation_id AND material_id = :material_id'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'quotation_id' => $quotationId,
            'material_id' => $materialId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('只能将该材料自己的报价设为默认报价。', 422);
        }
        $update = $this->db->prepare(
            'UPDATE Materials
             SET default_quotation_id = :quotation_id
             WHERE project_id = :project_id AND material_id = :material_id'
        );
        $update->execute([
            'quotation_id' => $quotationId,
            'project_id' => $projectId,
            'material_id' => $materialId,
        ]);
    }

    public function clearDefaultQuotation(int $projectId, int $materialId): void
    {
        $this->requireProjectMaterial($projectId, $materialId, '材料');
        $stmt = $this->db->prepare(
            'UPDATE Materials SET default_quotation_id = NULL
             WHERE project_id = :project_id AND material_id = :material_id'
        );
        $stmt->execute(['project_id' => $projectId, 'material_id' => $materialId]);
    }

    public function deleteQuotation(int $projectId, int $quotationId): void
    {
        $this->requireProjectQuotation($projectId, $quotationId);
        $sampleCheck = $this->db->prepare(
            'SELECT COUNT(*) FROM Material_Samples WHERE project_id = :project_id AND quotation_id = :id'
        );
        $sampleCheck->execute(['project_id' => $projectId, 'id' => $quotationId]);
        if ((int) $sampleCheck->fetchColumn() > 0) {
            throw new HttpException('该报价已关联材料样品，不能删除。', 409);
        }
        $this->db->beginTransaction();
        try {
            $clearDefault = $this->db->prepare(
                'UPDATE Materials SET default_quotation_id = NULL
                 WHERE project_id = :project_id AND default_quotation_id = :id'
            );
            $clearDefault->execute(['project_id' => $projectId, 'id' => $quotationId]);
            $mapping = $this->db->prepare(
                'DELETE FROM Material_Mappings WHERE project_id = :project_id AND quotation_id = :id'
            );
            $mapping->execute(['project_id' => $projectId, 'id' => $quotationId]);
            $delete = $this->db->prepare(
                'DELETE FROM Material_Quotations WHERE project_id = :project_id AND quotation_id = :id'
            );
            $delete->execute(['project_id' => $projectId, 'id' => $quotationId]);
            if ($delete->rowCount() !== 1) {
                throw new HttpException('报价不存在或不属于当前项目。', 404);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function saveAuxiliary(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['aux_id'] ?? null);
        $name = $this->requiredText($data, 'aux_name', 255, '辅材名称');
        $unit = $this->requiredText($data, 'unit', 50, '计量单位');
        $description = $this->optionalText($data, 'description', 20000, '说明');
        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Auxiliary_Materials (project_id, aux_name, unit, description)
                 VALUES (:project_id, :name, :unit, :description)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'name' => $name,
                'unit' => $unit,
                'description' => $description,
            ]);
            return (int) $this->db->lastInsertId();
        }
        $exists = $this->db->prepare(
            'SELECT 1 FROM Auxiliary_Materials WHERE aux_id = :id AND project_id = :project_id'
        );
        $exists->execute(['id' => $id, 'project_id' => $projectId]);
        if ($exists->fetchColumn() === false) {
            throw new HttpException('辅材不存在或不属于当前项目。', 404);
        }
        $stmt = $this->db->prepare(
            'UPDATE Auxiliary_Materials SET aux_name = :name, unit = :unit, description = :description
             WHERE aux_id = :id AND project_id = :project_id'
        );
        $stmt->execute([
            'name' => $name,
            'unit' => $unit,
            'description' => $description,
            'id' => $id,
            'project_id' => $projectId,
        ]);
        return $id;
    }

    public function deleteAuxiliary(int $projectId, int $auxId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM Auxiliary_Materials WHERE aux_id = :id AND project_id = :project_id'
        );
        $stmt->execute(['id' => $auxId, 'project_id' => $projectId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('辅材不存在或不属于当前项目。', 404);
        }
    }

    private function requiredText(array $data, string $key, int $maxLength, string $label): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new HttpException($label . '不能为空且不能超过 ' . $maxLength . ' 个字符。', 422);
        }
        return $value;
    }

    private function optionalText(array $data, string $key, int $maxLength, string $label): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException($label . '格式无效。', 422);
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new HttpException($label . '不能超过 ' . $maxLength . ' 个字符。', 422);
        }
        return $value === '' ? null : $value;
    }

    private function optionalUrl(array $data, string $key, string $label): ?string
    {
        $url = $this->optionalText($data, $key, 2048, $label);
        if ($url === null) {
            return null;
        }
        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new HttpException($label . '须为不含账号信息的 HTTP(S) 地址。', 422);
        }
        return $url;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->requiredId($value, '编号');
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

    private function optionalInt(mixed $value, int $minimum, int $maximum, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->integer($value, $minimum, $maximum, $label);
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

    private function optionalDecimal(mixed $value, int $integerDigits, int $scale, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->requiredDecimal($value, $integerDigits, $scale, $label);
    }

    private function requiredDecimal(mixed $value, int $integerDigits, int $scale, string $label): string
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

    private function optionalPercentage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        $number = $this->requiredDecimal($value, 3, 2, '确认税率');
        if ((float) $number > 100) {
            throw new HttpException('确认税率须在 0 到 100 之间。', 422);
        }
        return $number;
    }

    private function optionalCurrency(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException('币种代码格式无效。', 422);
        }
        $value = strtoupper(trim($value));
        if (!CurrencyCatalog::supports($value)) {
            throw new HttpException('请选择 ISO 4217 标准目录中的币种。', 422);
        }
        return $value;
    }

    private function requiredCurrency(mixed $value): string
    {
        $currency = $this->optionalCurrency($value);
        if ($currency === null) {
            throw new HttpException('报价币种必填。', 422);
        }
        return $currency;
    }

    private function requiredDate(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new HttpException($label . '无效。', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new HttpException($label . '须为有效日期。', 422);
        }
        return $value;
    }

    private function projectQuery(string $sql, int $projectId): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    private function requireProjectCategory(int $projectId, int $categoryId, string $label): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Material_Categories WHERE project_id = :project_id AND category_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $categoryId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException($label . '不存在或不属于当前项目。', 404);
        }
    }

    /**
     * Translate the (project_id, material_code) unique-key violation into a
     * business error, mirroring the labor-crew duplicate-registration style.
     * Any other database failure is rethrown unchanged by the caller.
     */
    private function throwIfDuplicateMaterialCode(\PDOException $exception, int $projectId, ?string $code, ?int $excludeId): void
    {
        if ($code === null || (string) $exception->getCode() !== '23000') {
            return;
        }
        $message = $exception->getMessage();
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isDuplicate = ($driver === 'mysql' && str_contains($message, 'uq_material_project_code'))
            || ($driver === 'sqlite' && str_contains($message, 'UNIQUE constraint failed'));
        if (!$isDuplicate) {
            return;
        }
        // Native MySQL prepares reject a named placeholder bound twice, so the
        // self-exclusion for updates is appended conditionally instead.
        $sql = 'SELECT material_name FROM Materials
             WHERE project_id = :project_id AND material_code = :code';
        $params = ['project_id' => $projectId, 'code' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND material_id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $existing = $stmt->fetchColumn();
        if (!is_string($existing) || $existing === '') {
            return;
        }
        throw new HttpException(
            '材料编码「' . $code . '」已被材料「' . $existing . '」使用，请改用其他编码或直接编辑已有材料。',
            409
        );
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

    private function requireProjectQuotation(int $projectId, int $quotationId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Material_Quotations WHERE project_id = :project_id AND quotation_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $quotationId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('报价不存在或不属于当前项目。', 404);
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

    private function requireUnused(string $sql, int $id, string $message): void
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new HttpException($message, 409);
        }
    }

    private function deleteById(string $table, string $column, int $id, string $label): void
    {
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `{$column}` = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException($label . '不存在。', 404);
        }
    }
}

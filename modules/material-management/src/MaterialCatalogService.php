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
                        m.default_quotation_id, c.category_name
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
                        m.default_quotation_id, c.category_name
                 FROM Materials m
                 LEFT JOIN Material_Categories c
                   ON c.category_id = m.category_id AND c.project_id = m.project_id
                 WHERE m.project_id = :project_id
                 ORDER BY m.material_name, m.material_id',
                $projectId
            ),
        ];
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

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Materials
                    (project_id, material_code, material_name, unit, specifications, material_description_detailed, category_id)
                 VALUES (:project_id, :code, :name, :unit, :specifications, :description, :category_id)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'code' => $code,
                'name' => $name,
                'unit' => $unit,
                'specifications' => $specifications,
                'description' => $description,
                'category_id' => $categoryId,
            ]);
            return (int) $this->db->lastInsertId();
        }

        $this->requireProjectMaterial($projectId, $id, '材料');
        $stmt = $this->db->prepare(
            'UPDATE Materials
             SET material_code = :code, material_name = :name, unit = :unit,
                 specifications = :specifications, material_description_detailed = :description,
                 category_id = :category_id
             WHERE material_id = :id AND project_id = :project_id'
        );
        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'unit' => $unit,
            'specifications' => $specifications,
            'description' => $description,
            'category_id' => $categoryId,
            'id' => $id,
            'project_id' => $projectId,
        ]);
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
                'message' => '该材料已用于材料估算明细，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Material_Samples WHERE project_id = :project_id AND material_id = :id',
                'message' => '该材料已用于材料样品，不能删除。',
            ],
            [
                'sql' => 'SELECT COUNT(*) FROM Tender_List WHERE project_id = :project_id AND material_id = :id',
                'message' => '该材料已用于招标清单，不能删除。',
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

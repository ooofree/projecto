<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use PDO;
use ProjectO\CurrencyCatalog;
use ProjectO\HttpException;

final class TenderListService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function items(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.tender_item_id, t.project_id, t.material_id, t.tender_quantity,
                    t.tender_unit_price, t.currency_code, t.status, t.updated_at,
                    m.material_code, m.material_name, m.unit, m.category_id,
                    c.category_name
             FROM Tender_List t
             JOIN Materials m ON m.material_id = t.material_id AND m.project_id = t.project_id
             LEFT JOIN Material_Categories c
               ON c.category_id = m.category_id AND c.project_id = m.project_id
             WHERE t.project_id = :project_id
             ORDER BY c.sort_order, c.category_name, m.material_code, m.material_name, t.tender_item_id'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function materials(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT material_id, material_code, material_name, unit
             FROM Materials WHERE project_id = :project_id
             ORDER BY material_name, material_id'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function save(int $projectId, array $data): int
    {
        $id = $this->optionalId($data['tender_item_id'] ?? null, '招标清单项');
        $materialId = $this->requiredId($data['material_id'] ?? null, '材料');
        $quantity = $this->decimal($data['tender_quantity'] ?? null, 10, 2, '招标数量');
        $unitPrice = $this->decimal($data['tender_unit_price'] ?? null, 14, 2, '招标单价');
        $currency = $data['currency_code'] ?? 'CNY';
        if (!is_string($currency)) {
            throw new HttpException('请选择 ISO 4217 标准目录中的币种。', 422);
        }
        $currency = strtoupper(trim($currency));
        if (!CurrencyCatalog::supports($currency)) {
            throw new HttpException('请选择 ISO 4217 标准目录中的币种。', 422);
        }
        $this->requireProjectMaterial($projectId, $materialId);

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO Tender_List
                    (project_id, material_id, tender_quantity, tender_unit_price, currency_code)
                 VALUES (:project_id, :material_id, :quantity, :unit_price, :currency)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'material_id' => $materialId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'currency' => $currency,
            ]);
            return (int) $this->db->lastInsertId();
        }

        $this->requireProjectItem($projectId, $id);
        $stmt = $this->db->prepare(
            'UPDATE Tender_List
             SET material_id = :material_id, tender_quantity = :quantity,
                 tender_unit_price = :unit_price, currency_code = :currency
             WHERE project_id = :project_id AND tender_item_id = :id'
        );
        $stmt->execute([
            'material_id' => $materialId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'project_id' => $projectId,
            'id' => $id,
        ]);
        if ($stmt->rowCount() !== 1) {
            $this->requireProjectItem($projectId, $id);
        }
        return $id;
    }

    public function delete(int $projectId, int $itemId): void
    {
        $this->requireProjectItem($projectId, $itemId);
        $stmt = $this->db->prepare(
            'DELETE FROM Tender_List WHERE project_id = :project_id AND tender_item_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $itemId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('招标清单项不存在或不属于当前项目。', 404);
        }
    }

    private function requireProjectMaterial(int $projectId, int $materialId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Materials WHERE project_id = :project_id AND material_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $materialId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('材料不存在或不属于当前项目。', 404);
        }
    }

    private function requireProjectItem(int $projectId, int $itemId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Tender_List WHERE project_id = :project_id AND tender_item_id = :id'
        );
        $stmt->execute(['project_id' => $projectId, 'id' => $itemId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('招标清单项不存在或不属于当前项目。', 404);
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
}

<?php
declare(strict_types=1);

namespace ProjectO\Modules\BomCosting;

use PDO;
use ProjectO\HttpException;

/**
 * 统一资源目录（DR-2）：processing/mechanical/labor 单表类型字段，共享归属（B16），不带价格。
 * 价格在 project_cost_prices，按项目隔离。目录项被资源行/成本价/实际费用引用时受 RESTRICT 保护删除。
 */
final class ResourceCatalogService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $type = null): array
    {
        $allowed = ['processing', 'mechanical', 'labor'];
        $sql = 'SELECT catalog_id, catalog_type, name, unit, description, created_at
                FROM resource_catalog';
        $params = [];
        if ($type !== null && in_array($type, $allowed, true)) {
            $sql .= ' WHERE catalog_type = :type';
            $params['type'] = $type;
        }
        $sql .= ' ORDER BY catalog_type, name, catalog_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, array<int, array<string, mixed>>> 按类型分组的选项 */
    public function groupedOptions(): array
    {
        $grouped = ['processing' => [], 'mechanical' => [], 'labor' => []];
        foreach ($this->list() as $row) {
            $grouped[(string) $row['catalog_type']][] = $row;
        }

        return $grouped;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): int
    {
        $id = $this->optionalId($data['catalog_id'] ?? null);
        $type = $this->validateType($data['catalog_type'] ?? null);
        $name = trim((string) ($data['name'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? ''));
        $description = is_string($data['description'] ?? null) ? trim($data['description']) : null;

        if ($name === '' || mb_strlen($name) > 255) {
            throw new HttpException('目录名称不能为空且不超过 255 字。', 422);
        }
        if ($unit === '' || mb_strlen($unit) > 50) {
            throw new HttpException('单位不能为空。', 422);
        }

        $this->assertNameUnique($type, $name, $id);

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO resource_catalog (catalog_type, name, unit, description)
                 VALUES (:type, :name, :unit, :description)'
            );
            $stmt->execute(['type' => $type, 'name' => $name, 'unit' => $unit, 'description' => $description]);

            return (int) $this->db->lastInsertId();
        }

        $stmt = $this->db->prepare(
            'UPDATE resource_catalog SET catalog_type = :type, name = :name, unit = :unit, description = :description
             WHERE catalog_id = :id'
        );
        $stmt->execute(['type' => $type, 'name' => $name, 'unit' => $unit, 'description' => $description, 'id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('目录项不存在。', 404);
        }

        return $id;
    }

    public function delete(int $catalogId): void
    {
        // RESTRICT 由数据库强制；被引用时删除会抛外键异常，服务端转为可读错误。
        $stmt = $this->db->prepare('DELETE FROM resource_catalog WHERE catalog_id = :id');
        try {
            $stmt->execute(['id' => $catalogId]);
        } catch (\PDOException $exception) {
            throw new HttpException('该目录项已被资源行、成本价或费用引用，不能删除。', 422);
        }
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('目录项不存在。', 404);
        }
    }

    private function validateType(mixed $value): string
    {
        $allowed = ['processing', 'mechanical', 'labor'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new HttpException('目录类型无效。', 422);
        }

        return $value;
    }

    private function assertNameUnique(string $type, string $name, ?int $excludeId): void
    {
        $sql = 'SELECT catalog_id FROM resource_catalog WHERE catalog_type = :type AND name = :name';
        $params = ['type' => $type, 'name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND catalog_id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new HttpException('同类型下目录名称已存在。', 422);
        }
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException('编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('编号无效。', 422);
        }

        return $id;
    }
}

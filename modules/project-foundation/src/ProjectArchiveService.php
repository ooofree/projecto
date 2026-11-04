<?php
declare(strict_types=1);

namespace ProjectO\Modules\ProjectFoundation;

use PDO;
use ProjectO\HttpException;

final class ProjectArchiveService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function hasReferences(string $table, string $column, int $id): bool
    {
        $references = $this->referencingColumns($table, $column);
        foreach ($references as [$childTable, $childColumn]) {
            $sql = 'SELECT 1 FROM ' . $this->quoteIdentifier($childTable)
                . ' WHERE ' . $this->quoteIdentifier($childColumn) . ' = :id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    public function tableExists(string $table): bool
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table_name"
            );
            $stmt->execute(['table_name' => $table]);
            return $stmt->fetchColumn() !== false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    public function isArchived(string $type, int $entityId, int $projectId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM project_foundation_archive_records
             WHERE entity_type = :entity_type AND entity_id = :entity_id AND project_id = :project_id'
        );
        $stmt->execute([
            'entity_type' => $type,
            'entity_id' => $entityId,
            'project_id' => $projectId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function archive(string $type, int $entityId, int $projectId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO project_foundation_archive_records
                (entity_type, entity_id, project_id, archived_by)
             VALUES (:entity_type, :entity_id, :project_id, :archived_by)'
        );
        $stmt->execute([
            'entity_type' => $type,
            'entity_id' => $entityId,
            'project_id' => $projectId,
            'archived_by' => $userId,
        ]);
    }

    public function restore(string $type, int $entityId, int $projectId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM project_foundation_archive_records
             WHERE entity_type = :entity_type AND entity_id = :entity_id AND project_id = :project_id'
        );
        $stmt->execute([
            'entity_type' => $type,
            'entity_id' => $entityId,
            'project_id' => $projectId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('已归档记录不存在或不属于当前项目。', 404);
        }
    }

    private function referencingColumns(string $parentTable, string $parentColumn): array
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return $this->sqliteColumns($parentTable, $parentColumn);
        }

        $stmt = $this->db->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = :column_name
               AND TABLE_NAME <> :table_name'
                . ' UNION
             SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = :referenced_table
               AND REFERENCED_COLUMN_NAME = :referenced_column'
        );
        $stmt->execute([
            'table_name' => $parentTable,
            'column_name' => $parentColumn,
            'referenced_table' => $parentTable,
            'referenced_column' => $parentColumn,
        ]);

        return array_map(
            static fn (array $row): array => [$row['TABLE_NAME'], $row['COLUMN_NAME']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function sqliteColumns(string $parentTable, string $parentColumn): array
    {
        $tables = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $references = [];
        foreach ($tables as $table) {
            if ($table === $parentTable) {
                continue;
            }
            $quotedTable = $this->quoteIdentifier((string) $table);
            $columns = $this->db->query('PRAGMA table_info(' . $quotedTable . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if ($column['name'] === $parentColumn) {
                    $references[(string) $table . ':' . (string) $column['name']] = [
                        (string) $table,
                        (string) $column['name'],
                    ];
                }
            }
            $foreignKeys = $this->db->query('PRAGMA foreign_key_list(' . $quotedTable . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey['table'] === $parentTable && $foreignKey['to'] === $parentColumn) {
                    $references[(string) $table . ':' . (string) $foreignKey['from']] = [
                        (string) $table,
                        (string) $foreignKey['from'],
                    ];
                }
            }
        }

        return array_values($references);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new HttpException('数据库关联定义无效。', 500);
        }

        return '`' . $identifier . '`';
    }
}

<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use PDO;

/**
 * 子材料数量汇总（材料测算 → BOM 拆解）。
 *
 * 口径（2026-11-23 用户确认）：
 * - 测算只管「父材料 + 数量」，父材料必须在材料管理里已有 BOM 组成；
 * - 本服务把每条测算行按 Material_Compositions 的用量系数展开，得到子材料需求量；
 * - 页面/导出按「材料编号」平铺明细，同一编号的明细后面紧跟一条汇总行，
 *   不做材料大类小计（大类只用于筛选）。
 *
 * 数量用 4 位小数的定点整数累加，避免浮点漂移；系数自身也是 2~4 位小数。
 */
final class MaterialDemandService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array{category_id?:?int,building_id?:?int,floor_id?:?int,room_id?:?int,search?:string} $filters
     * @return array{groups:list<array<string,mixed>>,detail_count:int,group_count:int,skipped_rows:int}
     */
    public function summary(int $projectId, array $filters = []): array
    {
        $sql = "SELECT e.estimate_detail_id, e.estimated_quantity, e.remarks,
                       b.building_id, b.building_number, b.building_name,
                       f.floor_id, f.floor_number, f.floor_name,
                       r.room_id, r.room_number,
                       pm.material_id AS parent_material_id, pm.material_code AS parent_code,
                       pm.material_name AS parent_name, pm.unit AS parent_unit,
                       cm.material_id AS child_material_id, cm.material_code AS child_code,
                       cm.material_name AS child_name, cm.specifications AS child_spec,
                       cm.unit AS child_unit, cm.category_id AS child_category_id,
                       cat.category_name AS child_category_name,
                       cc.quantity AS coefficient,
                       (SELECT COUNT(*) FROM project_foundation_archive_records a
                         WHERE a.project_id = b.project_id AND a.entity_type = 'building'
                           AND a.entity_id = b.building_id) AS building_archived,
                       (SELECT COUNT(*) FROM project_foundation_archive_records a
                         WHERE a.project_id = b.project_id AND a.entity_type = 'floor'
                           AND a.entity_id = f.floor_id) AS floor_archived,
                       (SELECT COUNT(*) FROM project_foundation_archive_records a
                         WHERE a.project_id = b.project_id AND a.entity_type = 'room'
                           AND a.entity_id = r.room_id) AS room_archived
                FROM Initial_Cost_Estimate_Detail e
                JOIN Buildings b ON b.building_id = e.building_id
                JOIN Materials pm ON pm.material_id = e.material_id AND pm.project_id = b.project_id
                JOIN Material_Compositions cc
                  ON cc.project_id = b.project_id
                     AND cc.parent_material_id = pm.material_id
                     AND cc.child_material_id <> cc.parent_material_id
                JOIN Materials cm ON cm.material_id = cc.child_material_id AND cm.project_id = b.project_id
                LEFT JOIN Material_Categories cat
                  ON cat.category_id = cm.category_id AND cat.project_id = b.project_id
                LEFT JOIN Building_Floors f
                  ON f.floor_id = e.floor_id AND f.building_id = e.building_id
                LEFT JOIN Rooms r
                  ON r.room_id = e.room_id AND r.floor_id = e.floor_id AND r.building_id = e.building_id
                WHERE b.project_id = :project_id";
        $params = ['project_id' => $projectId];

        if (($filters['category_id'] ?? null) !== null) {
            $sql .= ' AND cm.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (($filters['building_id'] ?? null) !== null) {
            $sql .= ' AND b.building_id = :building_id';
            $params['building_id'] = (int) $filters['building_id'];
        }
        if (($filters['floor_id'] ?? null) !== null) {
            $sql .= ' AND e.floor_id = :floor_id';
            $params['floor_id'] = (int) $filters['floor_id'];
        }
        if (($filters['room_id'] ?? null) !== null) {
            $sql .= ' AND e.room_id = :room_id';
            $params['room_id'] = (int) $filters['room_id'];
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // 命名占位符不能重复使用（模拟预处理关闭），四个字段各用独立参数。
            $sql .= ' AND (cm.material_code LIKE :search_code
                            OR cm.material_name LIKE :search_name
                            OR cm.specifications LIKE :search_spec
                            OR pm.material_name LIKE :search_parent)';
            $like = '%' . $search . '%';
            $params['search_code'] = $like;
            $params['search_name'] = $like;
            $params['search_spec'] = $like;
            $params['search_parent'] = $like;
        }

        // 编号为空的行排在最后；其余按编号升序，编号内部按空间、父项、录入顺序稳定排列。
        $sql .= " ORDER BY (cm.material_code IS NULL OR cm.material_code = ''), cm.material_code,
                         cm.material_id, b.building_number, f.floor_number, r.room_number,
                         pm.material_name, e.estimate_detail_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $groups = [];
        $detailCount = 0;
        foreach ($rows as $row) {
            $quantity = self::scaled((string) $row['estimated_quantity']);
            $coefficient = self::scaled((string) $row['coefficient']);
            // 4 位小数定点：先整数相乘再四舍五入回 4 位，12.3456 × 2.50 = 30.8640。
            $demand = intdiv($quantity * $coefficient + 5000, 10000);

            $code = trim((string) ($row['child_code'] ?? ''));
            $key = $code !== '' ? 'code:' . $code : 'material:' . (int) $row['child_material_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'code' => $code,
                    'name' => (string) $row['child_name'],
                    'spec' => trim((string) ($row['child_spec'] ?? '')),
                    'unit' => (string) $row['child_unit'],
                    'category_name' => trim((string) ($row['child_category_name'] ?? '')),
                    'total_scaled' => 0,
                    'rows' => [],
                ];
            }
            $groups[$key]['total_scaled'] += $demand;
            $groups[$key]['rows'][] = [
                'estimate_detail_id' => (int) $row['estimate_detail_id'],
                'space' => self::spaceLabel($row),
                'parent_code' => trim((string) ($row['parent_code'] ?? '')),
                'parent_name' => (string) $row['parent_name'],
                'parent_unit' => (string) $row['parent_unit'],
                'quantity' => self::format($quantity),
                'coefficient' => self::format($coefficient),
                'demand' => self::format($demand),
                'demand_scaled' => $demand,
                'remarks' => trim((string) ($row['remarks'] ?? '')),
            ];
            $detailCount++;
        }

        foreach ($groups as &$group) {
            $group['total'] = self::format((int) $group['total_scaled']);
        }
        unset($group);

        return [
            'groups' => array_values($groups),
            'detail_count' => $detailCount,
            'group_count' => count($groups),
            'skipped_rows' => $this->unmeasurableEstimateCount($projectId),
        ];
    }

    /**
     * 还没建 BOM 组成的测算行数：这些行不会在拆解页面出现，页头据此提示整改。
     */
    public function unmeasurableEstimateCount(int $projectId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM Initial_Cost_Estimate_Detail e
             JOIN Buildings b ON b.building_id = e.building_id
             WHERE b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM Material_Compositions cc
                   WHERE cc.project_id = b.project_id
                     AND cc.parent_material_id = e.material_id
                     AND cc.child_material_id <> cc.parent_material_id
               )'
        );
        $stmt->execute(['project_id' => $projectId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 筛选下拉：分类取项目分类全量；楼栋/楼层/房间只取测算行实际用到的空间（含已归档）。
     *
     * @return array{categories:list<array<string,mixed>>,buildings:list<array<string,mixed>>,floors:list<array<string,mixed>>,rooms:list<array<string,mixed>>}
     */
    public function options(int $projectId): array
    {
        $categories = $this->db->prepare(
            'SELECT category_id, category_name FROM Material_Categories
             WHERE project_id = :project_id
             ORDER BY sort_order, category_name, category_id'
        );
        $categories->execute(['project_id' => $projectId]);

        $buildings = $this->db->prepare(
            "SELECT b.building_id, b.building_number, b.building_name,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'building'
                        AND a.entity_id = b.building_id) AS archived
             FROM Buildings b
             WHERE b.project_id = :project_id
               AND EXISTS (SELECT 1 FROM Initial_Cost_Estimate_Detail e WHERE e.building_id = b.building_id)
             ORDER BY b.building_number"
        );
        $buildings->execute(['project_id' => $projectId]);

        $floors = $this->db->prepare(
            "SELECT f.floor_id, f.building_id, f.floor_number, f.floor_name,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'floor'
                        AND a.entity_id = f.floor_id) AS archived,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'building'
                        AND a.entity_id = b.building_id) AS building_archived
             FROM Building_Floors f
             JOIN Buildings b ON b.building_id = f.building_id
             WHERE b.project_id = :project_id
               AND EXISTS (SELECT 1 FROM Initial_Cost_Estimate_Detail e
                            WHERE e.floor_id = f.floor_id AND e.building_id = f.building_id)
             ORDER BY b.building_number, f.floor_number"
        );
        $floors->execute(['project_id' => $projectId]);

        $rooms = $this->db->prepare(
            "SELECT r.room_id, r.building_id, r.floor_id, r.room_number, f.floor_number,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'room'
                        AND a.entity_id = r.room_id) AS archived,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'floor'
                        AND a.entity_id = r.floor_id) AS floor_archived,
                    (SELECT COUNT(*) FROM project_foundation_archive_records a
                      WHERE a.project_id = b.project_id AND a.entity_type = 'building'
                        AND a.entity_id = r.building_id) AS building_archived
             FROM Rooms r
             JOIN Building_Floors f ON f.floor_id = r.floor_id AND f.building_id = r.building_id
             JOIN Buildings b ON b.building_id = r.building_id
             WHERE b.project_id = :project_id
               AND EXISTS (SELECT 1 FROM Initial_Cost_Estimate_Detail e
                            WHERE e.room_id = r.room_id AND e.floor_id = r.floor_id AND e.building_id = r.building_id)
             ORDER BY b.building_number, f.floor_number, r.room_number"
        );
        $rooms->execute(['project_id' => $projectId]);

        return [
            'categories' => $categories->fetchAll(),
            'buildings' => $buildings->fetchAll(),
            'floors' => $floors->fetchAll(),
            'rooms' => $rooms->fetchAll(),
        ];
    }

    public static function spaceLabel(array $row): string
    {
        $building = trim((string) $row['building_number'] . ' · ' . (string) ($row['building_name'] ?? ''), ' ·');
        if ($building === '') {
            $building = '未指定楼栋';
        }
        $floor = trim((string) ($row['floor_number'] ?? '') . ' · ' . (string) ($row['floor_name'] ?? ''), ' ·');
        $room = trim((string) ($row['room_number'] ?? ''));
        $parts = [$building];
        $parts[] = $floor !== '' ? $floor : '未指定楼层';
        if ($room !== '') {
            $parts[] = $room;
        }
        $label = implode(' · ', $parts);
        $archived = (int) ($row['building_archived'] ?? 0) + (int) ($row['floor_archived'] ?? 0) + (int) ($row['room_archived'] ?? 0);
        if ($archived > 0) {
            $label .= '（已归档）';
        }
        return $label;
    }

    /** 4 位小数定点整数（12.3456 → 123456）。 */
    private static function scaled(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            return (int) round((float) $value * 10000);
        }
        $fraction = substr(str_pad($matches[3] ?? '', 4, '0'), 0, 4);
        $digits = ltrim($matches[2] . $fraction, '0');
        $number = $digits === '' ? 0 : (int) $digits;
        return $matches[1] === '-' ? -$number : $number;
    }

    /** 定点整数转 4 位小数字符串（123456 → 12.3456）。 */
    private static function format(int $scaled): string
    {
        $sign = $scaled < 0 ? '-' : '';
        $scaled = abs($scaled);
        return $sign . intdiv($scaled, 10000) . '.' . str_pad((string) ($scaled % 10000), 4, '0', STR_PAD_LEFT);
    }
}

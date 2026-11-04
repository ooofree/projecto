<?php
declare(strict_types=1);

namespace ProjectO\Modules\ProjectFoundation;

use PDO;
use ProjectO\HttpException;
use Throwable;

final class ProjectSpaceService
{
    private ProjectArchiveService $archive;

    public function __construct(private readonly PDO $db)
    {
        $this->archive = new ProjectArchiveService($db);
    }

    public function list(int $projectId, bool $includeArchived = false): array
    {
        $buildings = $this->db->prepare(
            'SELECT b.building_id, b.building_number, b.building_name,
                    EXISTS (
                        SELECT 1 FROM project_foundation_archive_records a
                        WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                          AND a.project_id = b.project_id
                    ) AS is_archived
             FROM Buildings b WHERE b.project_id = :project_id'
                . ($includeArchived ? '' : ' AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records a
                    WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                      AND a.project_id = b.project_id
                )')
                . ' ORDER BY b.building_number, b.building_id'
        );
        $buildings->execute(['project_id' => $projectId]);
        $rows = $buildings->fetchAll();

        foreach ($rows as &$building) {
            $buildingId = (int) $building['building_id'];
            $floors = $this->db->prepare(
                'SELECT f.floor_id, f.floor_number, f.floor_name, f.usage_type,
                        EXISTS (
                            SELECT 1 FROM project_foundation_archive_records a
                            WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                              AND a.project_id = :project_id
                        ) AS is_archived
                 FROM Building_Floors f WHERE f.building_id = :building_id'
                    . ($includeArchived ? '' : ' AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records a
                        WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                          AND a.project_id = :active_project_id
                    )')
                    . ' ORDER BY f.floor_number, f.floor_id'
            );
            $floorParams = ['building_id' => $buildingId, 'project_id' => $projectId];
            if (!$includeArchived) {
                $floorParams['active_project_id'] = $projectId;
            }
            $floors->execute($floorParams);
            $building['floors'] = $floors->fetchAll();

            $areas = $this->db->prepare(
                'SELECT a.area_id, a.area_name, a.description,
                        EXISTS (
                            SELECT 1 FROM project_foundation_archive_records x
                            WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                              AND x.project_id = :project_id
                        ) AS is_archived
                 FROM Construction_Areas a WHERE a.building_id = :building_id'
                    . ($includeArchived ? '' : ' AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                          AND x.project_id = :active_project_id
                    )')
                    . ' ORDER BY a.area_name, a.area_id'
            );
            $areaParams = ['building_id' => $buildingId, 'project_id' => $projectId];
            if (!$includeArchived) {
                $areaParams['active_project_id'] = $projectId;
            }
            $areas->execute($areaParams);
            $building['areas'] = $areas->fetchAll();

            $rooms = $this->db->prepare(
                'SELECT r.room_id, r.floor_id, r.room_number, r.room_type, r.room_area,
                        r.room_height, r.room_status, f.floor_number
                 FROM Rooms r
                 JOIN Building_Floors f
                   ON f.floor_id = r.floor_id AND f.building_id = r.building_id
                 WHERE r.building_id = :building_id'
                    . ($includeArchived ? '' : ' AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'room\' AND x.entity_id = r.room_id
                          AND x.project_id = :project_id
                    ) AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'floor\' AND x.entity_id = r.floor_id
                          AND x.project_id = :floor_project_id
                    )')
                    . '
                 ORDER BY f.floor_number, r.room_number, r.room_id'
            );
            $roomParams = ['building_id' => $buildingId];
            if (!$includeArchived) {
                $roomParams['project_id'] = $projectId;
                $roomParams['floor_project_id'] = $projectId;
            }
            $rooms->execute($roomParams);
            $building['rooms'] = $rooms->fetchAll();

            $mappings = $this->db->prepare(
                'SELECT ar.area_room_id, ar.area_id, ar.room_id, ar.floor_id,
                        ar.room_number, ar.remarks, a.area_name, f.floor_number
                 FROM Construction_Area_Rooms ar
                 JOIN Construction_Areas a
                   ON a.area_id = ar.area_id AND a.building_id = :area_building_id
                 JOIN Building_Floors f
                   ON f.floor_id = ar.floor_id AND f.building_id = :floor_building_id
                 WHERE 1 = 1'
                    . ($includeArchived ? '' : ' AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'area\' AND x.entity_id = ar.area_id
                          AND x.project_id = :project_id
                    ) AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'floor\' AND x.entity_id = ar.floor_id
                          AND x.project_id = :floor_project_id
                    ) AND NOT EXISTS (
                        SELECT 1 FROM project_foundation_archive_records x
                        WHERE x.entity_type = \'room\' AND x.entity_id = ar.room_id
                          AND x.project_id = :room_project_id
                    )')
                    . ' ORDER BY a.area_name, f.floor_number, ar.room_number'
            );
            $mappingParams = [
                'area_building_id' => $buildingId,
                'floor_building_id' => $buildingId,
            ];
            if (!$includeArchived) {
                $mappingParams['project_id'] = $projectId;
                $mappingParams['floor_project_id'] = $projectId;
                $mappingParams['room_project_id'] = $projectId;
            }
            $mappings->execute($mappingParams);
            $building['area_rooms'] = $mappings->fetchAll();
        }
        unset($building);

        return $rows;
    }

    public function save(int $projectId, array $data): int
    {
        $type = $this->string($data, 'type');
        $id = $this->id($data['id'] ?? null, false);

        if ($type === 'building') {
            $name = $this->requiredText($data, 'name', 255, '楼栋名称');
            $number = trim($this->string($data, 'number'));
            if ($number === '' || mb_strlen($number) > 50) {
                throw new HttpException('楼栋编号不能为空且不能超过 50 个字符。', 422);
            }
            if ($id !== null) {
                $this->requireBuilding($projectId, $id);
            }
            $this->requireBuildingNumberAvailable($projectId, $number, $id);
            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Buildings (project_id, building_number, building_name)
                     VALUES (:project_id, :number, :name)'
                );
                $stmt->execute(['project_id' => $projectId, 'number' => $number, 'name' => $name]);
                return (int) $this->db->lastInsertId();
            }
            $stmt = $this->db->prepare(
                'UPDATE Buildings SET building_number = :number, building_name = :name
                 WHERE building_id = :id AND project_id = :project_id'
            );
            $stmt->execute(['number' => $number, 'name' => $name, 'id' => $id, 'project_id' => $projectId]);
            return $id;
        }

        $buildingId = $this->id($data['building_id'] ?? null);
        $this->requireBuilding($projectId, $buildingId);
        if ($type === 'floor') {
            $number = trim($this->string($data, 'number'));
            $name = $this->optionalText($data, 'name', 50, '楼层名称');
            $usage = $this->nullableString($data, 'usage_type');
            if ($number === '' || mb_strlen($number) > 50) {
                throw new HttpException('楼层编号不能为空且不能超过 50 个字符。', 422);
            }
            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Building_Floors (building_id, floor_number, floor_name, usage_type)
                     VALUES (:building_id, :number, :name, :usage_type)'
                );
                $stmt->execute([
                    'building_id' => $buildingId,
                    'number' => $number,
                    'name' => $name,
                    'usage_type' => $usage,
                ]);
                return (int) $this->db->lastInsertId();
            }
            $this->requireFloor($projectId, $buildingId, $id);
            $stmt = $this->db->prepare(
                'UPDATE Building_Floors
                 SET floor_number = :number, floor_name = :name, usage_type = :usage_type
                 WHERE floor_id = :id AND building_id = :building_id'
            );
            $stmt->execute([
                'number' => $number,
                'name' => $name,
                'usage_type' => $usage,
                'id' => $id,
                'building_id' => $buildingId,
            ]);
            return $id;
        }

        if ($type === 'area') {
            $name = $this->requiredText($data, 'name', 100, '区域名称');
            $description = $this->nullableString($data, 'description');
            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Construction_Areas (building_id, area_name, description)
                     VALUES (:building_id, :name, :description)'
                );
                $stmt->execute([
                    'building_id' => $buildingId,
                    'name' => $name,
                    'description' => $description,
                ]);
                return (int) $this->db->lastInsertId();
            }
            $this->requireArea($projectId, $buildingId, $id);
            $stmt = $this->db->prepare(
                'UPDATE Construction_Areas SET area_name = :name, description = :description
                 WHERE area_id = :id AND building_id = :building_id'
            );
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'id' => $id,
                'building_id' => $buildingId,
            ]);
            return $id;
        }

        if ($type === 'room') {
            $floorId = $this->id($data['floor_id'] ?? null);
            $this->requireFloor($projectId, $buildingId, $floorId);
            $roomNumber = trim($this->string($data, 'number'));
            if ($roomNumber === '' || mb_strlen($roomNumber) > 50) {
                throw new HttpException('房间号不能为空且不能超过 50 个字符。', 422);
            }
            $roomType = $this->nullableString($data, 'room_type');
            if ($id === null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO Rooms (building_id, floor_id, room_number, room_type)
                     VALUES (:building_id, :floor_id, :number, :room_type)'
                );
                $stmt->execute([
                    'building_id' => $buildingId,
                    'floor_id' => $floorId,
                    'number' => $roomNumber,
                    'room_type' => $roomType,
                ]);
                return (int) $this->db->lastInsertId();
            }
            $this->requireRoom($projectId, $buildingId, $floorId, $id);
            $stmt = $this->db->prepare(
                'UPDATE Rooms SET room_number = :number, room_type = :room_type
                 WHERE room_id = :id AND building_id = :building_id AND floor_id = :floor_id'
            );
            $stmt->execute([
                'number' => $roomNumber,
                'room_type' => $roomType,
                'id' => $id,
                'building_id' => $buildingId,
                'floor_id' => $floorId,
            ]);
            return $id;
        }

        throw new HttpException('空间类型无效。', 422);
    }

    public function mapRoom(int $projectId, array $data): void
    {
        $areaId = $this->id($data['area_id'] ?? null);
        $floorId = $this->id($data['floor_id'] ?? null);
        $roomId = $this->id($data['room_id'] ?? null);
        $area = $this->db->prepare(
            'SELECT a.building_id
             FROM Construction_Areas a
             JOIN Buildings b ON b.building_id = a.building_id
             WHERE a.area_id = :area_id AND b.project_id = :project_id'
                . ' AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records x
                    WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                      AND x.project_id = b.project_id
                )'
        );
        $area->execute(['area_id' => $areaId, 'project_id' => $projectId]);
        $buildingId = $area->fetchColumn();
        if ($buildingId === false) {
            throw new HttpException('施工区域不属于当前项目。', 404);
        }
        $this->requireFloor($projectId, (int) $buildingId, $floorId);
        $this->requireRoom($projectId, (int) $buildingId, $floorId, $roomId);

        $stmt = $this->db->prepare(
            'INSERT INTO Construction_Area_Rooms
                (room_id, area_id, floor_id, building_id, room_number, remarks)
             SELECT r.room_id, a.area_id, f.floor_id, r.building_id, r.room_number, :remarks
             FROM Rooms r
             JOIN Construction_Areas a ON a.area_id = :area_id
             JOIN Building_Floors f ON f.floor_id = :floor_id
             WHERE r.room_id = :room_id'
        );
        $stmt->execute([
            'remarks' => $this->nullableString($data, 'remarks'),
            'area_id' => $areaId,
            'floor_id' => $floorId,
            'room_id' => $roomId,
        ]);
    }

    public function removeRoomMapping(int $projectId, int $mappingId): void
    {
        $check = $this->db->prepare(
            'SELECT 1 FROM Construction_Area_Rooms ar
             JOIN Construction_Areas a ON a.area_id = ar.area_id
             JOIN Buildings b ON b.building_id = a.building_id
             WHERE ar.area_room_id = :mapping_id AND b.project_id = :project_id'
                . ' AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records x
                    WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                      AND x.project_id = b.project_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records x
                    WHERE x.entity_type = \'area\' AND x.entity_id = ar.area_id
                      AND x.project_id = b.project_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records x
                    WHERE x.entity_type = \'floor\' AND x.entity_id = ar.floor_id
                      AND x.project_id = b.project_id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM project_foundation_archive_records x
                    WHERE x.entity_type = \'room\' AND x.entity_id = ar.room_id
                      AND x.project_id = b.project_id
                )'
        );
        $check->execute(['mapping_id' => $mappingId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('区域房间关联不存在或不属于当前项目。', 404);
        }
        $stmt = $this->db->prepare('DELETE FROM Construction_Area_Rooms WHERE area_room_id = :mapping_id');
        $stmt->execute(['mapping_id' => $mappingId]);
        if ($stmt->rowCount() !== 1) {
            throw new HttpException('区域房间关联不存在或不属于当前项目。', 404);
        }
    }

    public function deleteOrArchive(int $projectId, string $type, int $entityId, int $userId): string
    {
        return $this->transaction(
            fn (): string => $this->deleteOrArchiveWithinTransaction($projectId, $type, $entityId, $userId)
        );
    }

    private function deleteOrArchiveWithinTransaction(int $projectId, string $type, int $entityId, int $userId): string
    {
        $entities = [
            'building' => ['Buildings', 'building_id'],
            'floor' => ['Building_Floors', 'floor_id'],
            'area' => ['Construction_Areas', 'area_id'],
            'room' => ['Rooms', 'room_id'],
        ];
        if (!isset($entities[$type])) {
            throw new HttpException('空间类型无效。', 422);
        }

        $ownership = match ($type) {
            'building' => 'SELECT 1 FROM Buildings WHERE building_id = :id AND project_id = :project_id',
            'floor' =>
                'SELECT 1 FROM Building_Floors f JOIN Buildings b ON b.building_id = f.building_id
                 WHERE f.floor_id = :id AND b.project_id = :project_id
                   AND NOT EXISTS (
                       SELECT 1 FROM project_foundation_archive_records a
                       WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                         AND a.project_id = b.project_id
                   )',
            'area' =>
                'SELECT 1 FROM Construction_Areas a JOIN Buildings b ON b.building_id = a.building_id
                 WHERE a.area_id = :id AND b.project_id = :project_id
                   AND NOT EXISTS (
                       SELECT 1 FROM project_foundation_archive_records x
                       WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                         AND x.project_id = b.project_id
                   )',
            'room' =>
                'SELECT 1 FROM Rooms r JOIN Buildings b ON b.building_id = r.building_id
                 WHERE r.room_id = :id AND b.project_id = :project_id
                   AND NOT EXISTS (
                       SELECT 1 FROM project_foundation_archive_records x
                       WHERE x.entity_type = \'building\' AND x.entity_id = b.building_id
                         AND x.project_id = b.project_id
                   )',
        };
        $lockClause = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $check = $this->db->prepare($ownership . $lockClause);
        $check->execute(['id' => $entityId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('空间记录不存在或不属于当前项目。', 404);
        }
        if ($this->archive->isArchived($type, $entityId, $projectId)) {
            throw new HttpException('空间记录已归档。', 409);
        }

        [$table, $column] = $entities[$type];
        if (
            $this->archive->hasReferences($table, $column, $entityId)
            || $this->hasSampleLocationReferences($projectId, $type, $entityId)
        ) {
            $this->archive->archive($type, $entityId, $projectId, $userId);
            return 'archived';
        }

        $delete = $this->db->prepare(
            'DELETE FROM ' . $table . ' WHERE ' . $column . ' = :id'
        );
        $delete->execute(['id' => $entityId]);
        if ($delete->rowCount() !== 1) {
            throw new HttpException('空间记录不存在或已被其他操作修改。', 409);
        }

        return 'deleted';
    }

    private function hasSampleLocationReferences(int $projectId, string $type, int $entityId): bool
    {
        if (!$this->archive->tableExists('Sample_Location_Map')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Sample_Location_Map
             WHERE project_id = :project_id
               AND location_type = :location_type
               AND location_id = :location_id
             LIMIT 1'
        );
        $stmt->execute([
            'project_id' => $projectId,
            'location_type' => $type,
            'location_id' => $entityId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function restore(int $projectId, string $type, int $entityId): void
    {
        $this->transaction(function () use ($projectId, $type, $entityId): void {
            $this->restoreWithinTransaction($projectId, $type, $entityId);
        });
    }

    private function restoreWithinTransaction(int $projectId, string $type, int $entityId): void
    {
        $entities = [
            'building' => 'Buildings',
            'floor' => 'Building_Floors',
            'area' => 'Construction_Areas',
            'room' => 'Rooms',
        ];
        if (!isset($entities[$type])) {
            throw new HttpException('空间类型无效。', 422);
        }
        $ownership = match ($type) {
            'building' => 'SELECT 1 FROM Buildings WHERE building_id = :id AND project_id = :project_id',
            'floor' => 'SELECT 1 FROM Building_Floors f JOIN Buildings b ON b.building_id = f.building_id WHERE f.floor_id = :id AND b.project_id = :project_id',
            'area' => 'SELECT 1 FROM Construction_Areas a JOIN Buildings b ON b.building_id = a.building_id WHERE a.area_id = :id AND b.project_id = :project_id',
            'room' => 'SELECT 1 FROM Rooms r JOIN Buildings b ON b.building_id = r.building_id WHERE r.room_id = :id AND b.project_id = :project_id',
        };
        $check = $this->db->prepare($ownership);
        $check->execute(['id' => $entityId, 'project_id' => $projectId]);
        if ($check->fetchColumn() === false) {
            throw new HttpException('空间记录不存在或不属于当前项目。', 404);
        }

        $this->archive->restore($type, $entityId, $projectId);
    }

    private function transaction(callable $operation): mixed
    {
        if ($this->db->inTransaction()) {
            return $operation();
        }

        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function requireBuilding(int $projectId, int $buildingId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Buildings b WHERE b.building_id = :id AND b.project_id = :project_id
             AND NOT EXISTS (
                 SELECT 1 FROM project_foundation_archive_records a
                 WHERE a.entity_type = \'building\' AND a.entity_id = b.building_id
                   AND a.project_id = b.project_id
             )'
        );
        $stmt->execute(['id' => $buildingId, 'project_id' => $projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('楼栋不属于当前项目。', 404);
        }
    }

    private function requireBuildingNumberAvailable(int $projectId, string $number, ?int $exceptBuildingId): void
    {
        $sql = 'SELECT 1 FROM Buildings
                WHERE project_id = :project_id AND building_number = :building_number';
        $parameters = ['project_id' => $projectId, 'building_number' => $number];
        if ($exceptBuildingId !== null) {
            $sql .= ' AND building_id <> :building_id';
            $parameters['building_id'] = $exceptBuildingId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($parameters);
        if ($stmt->fetchColumn() !== false) {
            throw new HttpException('当前项目已存在相同的楼栋编号。', 422);
        }
    }

    private function requireFloor(int $projectId, int $buildingId, int $floorId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Building_Floors f
             JOIN Buildings b ON b.building_id = f.building_id
             WHERE f.floor_id = :floor_id AND f.building_id = :building_id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'floor\' AND a.entity_id = f.floor_id
                     AND a.project_id = b.project_id
               )'
        );
        $stmt->execute([
            'floor_id' => $floorId,
            'building_id' => $buildingId,
            'project_id' => $projectId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('楼层不属于当前项目或所选楼栋。', 404);
        }
    }

    private function requireArea(int $projectId, int $buildingId, int $areaId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Construction_Areas a
             JOIN Buildings b ON b.building_id = a.building_id
             WHERE a.area_id = :area_id AND a.building_id = :building_id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'area\' AND x.entity_id = a.area_id
                     AND x.project_id = b.project_id
               )'
        );
        $stmt->execute([
            'area_id' => $areaId,
            'building_id' => $buildingId,
            'project_id' => $projectId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('施工区域不属于当前项目或所选楼栋。', 404);
        }
    }

    private function requireRoom(int $projectId, int $buildingId, int $floorId, int $roomId): void
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM Rooms r
             JOIN Buildings b ON b.building_id = r.building_id
             WHERE r.room_id = :room_id AND r.floor_id = :floor_id
               AND r.building_id = :building_id AND b.project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records x
                   WHERE x.entity_type = \'room\' AND x.entity_id = r.room_id
                     AND x.project_id = b.project_id
               )'
        );
        $stmt->execute([
            'room_id' => $roomId,
            'floor_id' => $floorId,
            'building_id' => $buildingId,
            'project_id' => $projectId,
        ]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException('房间不属于当前项目、楼栋或楼层。', 404);
        }
    }

    private function id(mixed $value, bool $required = true): ?int
    {
        if (!$required && ($value === null || $value === '')) {
            return null;
        }
        if (!is_int($value) && !is_string($value)) {
            throw new HttpException('空间编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('空间编号无效。', 422);
        }
        return $id;
    }

    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value)) {
            throw new HttpException('空间字段格式无效。', 422);
        }
        return trim($value);
    }

    private function requiredText(array $data, string $key, int $maximum, string $label): string
    {
        $value = $this->string($data, $key);
        if ($value === '' || mb_strlen($value) > $maximum) {
            throw new HttpException($label . '不能为空且不能超过 ' . $maximum . ' 个字符。', 422);
        }
        return $value;
    }

    private function optionalText(array $data, string $key, int $maximum, string $label): ?string
    {
        $value = $this->nullableString($data, $key);
        if ($value !== null && mb_strlen($value) > $maximum) {
            throw new HttpException($label . '不能超过 ' . $maximum . ' 个字符。', 422);
        }
        return $value;
    }

    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException('空间字段格式无效。', 422);
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}

<?php
declare(strict_types=1);

namespace ProjectO\Modules\ProjectFoundation;

use PDO;
use ProjectO\HttpException;
use ProjectO\Support;
use Throwable;

final class ProjectService
{
    private const STATUSES = ['planning', 'ongoing', 'completed', 'on-hold'];
    private ProjectArchiveService $archive;

    public function __construct(private readonly PDO $db)
    {
        $this->archive = new ProjectArchiveService($db);
    }

    public function list(bool $archivedOnly = false): array
    {
        $sql = 'SELECT p.project_id, p.project_name, p.start_date, p.end_date, p.status
             FROM Projects p
             WHERE ' . ($archivedOnly ? 'EXISTS' : 'NOT EXISTS') . ' (
                 SELECT 1 FROM project_foundation_archive_records a
                 WHERE a.entity_type = \'project\'
                   AND a.entity_id = p.project_id AND a.project_id = p.project_id
             )
             ORDER BY p.project_name, p.project_id';
        return $this->db->query(
            $sql
        )->fetchAll();
    }

    public function create(
        string $name,
        ?string $startDate,
        ?string $endDate,
        string $status
    ): int {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new HttpException('项目名称不能为空，且不能超过 255 个字符。', 422);
        }

        $startDate = $this->normalizeDate($startDate, 'start date');
        $endDate = $this->normalizeDate($endDate, 'end date');
        if (!in_array($status, self::STATUSES, true)) {
            throw new HttpException('项目状态无效。', 422);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO Projects (project_name, start_date, end_date, status)
             VALUES (:project_name, :start_date, :end_date, :status)'
        );
        $stmt->execute([
            'project_name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function select(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT project_id, project_name, start_date, end_date, status
             FROM Projects
             WHERE project_id = :project_id
               AND NOT EXISTS (
                   SELECT 1 FROM project_foundation_archive_records a
                   WHERE a.entity_type = \'project\'
                     AND a.entity_id = Projects.project_id AND a.project_id = Projects.project_id
               )
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            throw new HttpException('项目不存在。', 404);
        }

        Support::audit($this->db, 'project.select', 'Selected project ' . $project['project_id'] . '.');
        $_SESSION['active_project_id'] = (int) $project['project_id'];

        return $project;
    }

    public function deleteOrArchive(int $projectId, int $userId): string
    {
        return $this->transaction(function () use ($projectId, $userId): string {
            $lockClause = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare(
                'SELECT project_id FROM Projects WHERE project_id = :project_id' . $lockClause
            );
            $stmt->execute(['project_id' => $projectId]);
            if ($stmt->fetchColumn() === false) {
                throw new HttpException('项目不存在。', 404);
            }
            if ($this->archive->isArchived('project', $projectId, $projectId)) {
                throw new HttpException('项目已归档。', 409);
            }

            if ($this->archive->hasReferences('Projects', 'project_id', $projectId)) {
                $this->archive->archive('project', $projectId, $projectId, $userId);
                return 'archived';
            }

            $delete = $this->db->prepare('DELETE FROM Projects WHERE project_id = :project_id');
            $delete->execute(['project_id' => $projectId]);
            if ($delete->rowCount() !== 1) {
                throw new HttpException('项目不存在或已被其他操作修改。', 409);
            }

            return 'deleted';
        });
    }

    public function restore(int $projectId): void
    {
        $this->transaction(function () use ($projectId): void {
            $stmt = $this->db->prepare('SELECT 1 FROM Projects WHERE project_id = :project_id');
            $stmt->execute(['project_id' => $projectId]);
            if ($stmt->fetchColumn() === false) {
                throw new HttpException('项目不存在。', 404);
            }
            $this->archive->restore('project', $projectId, $projectId);
        });
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

    private function normalizeDate(?string $date, string $field): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new HttpException(($field === 'start date' ? '开始日期' : '结束日期') . '无效。', 422);
        }

        return $date;
    }
}

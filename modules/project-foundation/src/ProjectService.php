<?php
declare(strict_types=1);

namespace ProjectO\Modules\ProjectFoundation;

use PDO;
use ProjectO\HttpException;
use ProjectO\Support;

final class ProjectService
{
    private const STATUSES = ['planning', 'ongoing', 'completed', 'on-hold'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function list(): array
    {
        return $this->db->query(
            'SELECT project_id, project_name, start_date, end_date, status
             FROM Projects
             ORDER BY project_name, project_id'
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

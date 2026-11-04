<?php
declare(strict_types=1);

namespace ProjectO;

use PDO;

final class ProjectContext
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function current(): ?array
    {
        $sessionProjectId = $_SESSION['active_project_id'] ?? null;
        if (!is_int($sessionProjectId) && !is_string($sessionProjectId)) {
            unset($_SESSION['active_project_id']);
            return null;
        }

        $projectId = filter_var($sessionProjectId, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId === null || $projectId < 1) {
            unset($_SESSION['active_project_id']);
            return null;
        }

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
            unset($_SESSION['active_project_id']);
            return null;
        }

        return $project;
    }

    public function requireCurrent(): array
    {
        $project = $this->current();
        if ($project === null) {
            throw new HttpException('请先选择一个项目，再继续操作。', 409);
        }

        return $project;
    }
}

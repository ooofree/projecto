<?php
declare(strict_types=1);

namespace ProjectO\Modules\ProjectFoundation;

use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Support;
use Throwable;

final class ProjectFoundationPlugin implements PluginInterface
{
    private ProjectService $projects;

    public function __construct(private readonly PluginContext $context)
    {
        $this->projects = new ProjectService($context->db);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'index' => $this->index(),
            'create' => $this->create($input),
            'select' => $this->select($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function index(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');

        return $this->context->view('projects.php', [
            'title' => '项目',
            'projects' => $this->projects->list(),
            'currentProject' => $this->context->currentProject(),
            'canCreate' => Support::hasPermission($this->context->db, 'create_projects'),
            'canViewDocuments' => Support::hasPermission($this->context->db, 'view_issues'),
        ]);
    }

    private function create(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'create_projects');

        $db = $this->context->db;
        $db->beginTransaction();
        try {
            $projectId = $this->projects->create(
                $this->requiredString($input, 'project_name'),
                $this->nullableString($input, 'start_date'),
                $this->nullableString($input, 'end_date'),
                $this->requiredString($input, 'status', 'planning')
            );
            Support::audit($db, 'project.create', 'Created project ' . $projectId . '.');
            $this->projects->select($projectId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            unset($_SESSION['active_project_id']);
            throw $exception;
        }

        Support::flash('项目已创建并设为当前项目。');
        return PluginResponse::redirect('?page=projects');
    }

    private function select(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        $projectId = $input['project_id'] ?? null;
        if (!is_int($projectId) && !is_string($projectId)) {
            throw new HttpException('项目编号无效。', 422);
        }
        $projectId = filter_var($projectId, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1) {
            throw new HttpException('项目编号无效。', 422);
        }

        $project = $this->projects->select($projectId);
        Support::flash('当前项目：' . $project['project_name']);

        return PluginResponse::redirect('?page=projects');
    }

    private function requiredString(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        if (!is_string($value)) {
            throw new HttpException('项目字段格式无效。', 422);
        }

        return $value;
    }

    private function nullableString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new HttpException('项目日期格式无效。', 422);
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}

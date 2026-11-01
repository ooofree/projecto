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
    private ProjectSpaceService $spaces;

    public function __construct(private readonly PluginContext $context)
    {
        $this->projects = new ProjectService($context->db);
        $this->spaces = new ProjectSpaceService($context->db);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'index' => $this->index(),
            'create' => $this->create($input),
            'select' => $this->select($input),
            'spaces' => $this->spacesIndex(),
            'space.save' => $this->saveSpace($input),
            'space.room.map' => $this->mapRoom($input),
            'space.room.unmap' => $this->unmapRoom($input),
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
            'canViewMaterials' => Support::hasPermission($this->context->db, 'view_materials'),
            'canViewQuotations' => Support::hasPermission($this->context->db, 'view_material_quotations'),
            'canViewSpaces' => Support::hasPermission($this->context->db, 'view_project_spaces'),
            'canViewSamples' => Support::hasPermission($this->context->db, 'view_projects')
                && Support::hasPermission($this->context->db, 'view_material_samples')
                && Support::hasPermission($this->context->db, 'view_material_quotations'),
            'canViewEstimates' => Support::hasPermission($this->context->db, 'view_material_estimates'),
        ]);
    }

    private function spacesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];

        return $this->context->view('spaces.php', [
            'title' => '项目空间',
            'currentProject' => $project,
            'buildings' => $this->spaces->list($projectId),
            'canManage' => Support::hasPermission($this->context->db, 'manage_project_spaces'),
            'canViewMaterials' => Support::hasPermission($this->context->db, 'view_materials'),
            'canViewSamples' => Support::hasPermission($this->context->db, 'view_projects')
                && Support::hasPermission($this->context->db, 'view_material_samples')
                && Support::hasPermission($this->context->db, 'view_material_quotations'),
            'canViewEstimates' => Support::hasPermission($this->context->db, 'view_material_estimates'),
        ]);
    }

    private function saveSpace(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        Support::requirePermission($this->context->db, 'manage_project_spaces');
        $project = $this->context->requireCurrentProject();
        $id = $this->spaces->save((int) $project['project_id'], $input);
        Support::audit($this->context->db, 'project_space.save', 'Saved project space record ' . $id . '.');
        Support::flash('项目空间已保存。');

        return PluginResponse::redirect('?page=spaces');
    }

    private function mapRoom(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        Support::requirePermission($this->context->db, 'manage_project_spaces');
        $project = $this->context->requireCurrentProject();
        $this->spaces->mapRoom((int) $project['project_id'], $input);
        Support::audit($this->context->db, 'project_space.room_map', 'Mapped a room to a project area.');
        Support::flash('区域房间关联已保存。');

        return PluginResponse::redirect('?page=spaces');
    }

    private function unmapRoom(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        Support::requirePermission($this->context->db, 'manage_project_spaces');
        $project = $this->context->requireCurrentProject();
        $mappingId = $this->requiredId($input, 'mapping_id');
        $this->spaces->removeRoomMapping((int) $project['project_id'], $mappingId);
        Support::audit($this->context->db, 'project_space.room_unmap', 'Removed project area-room mapping ' . $mappingId . '.');
        Support::flash('区域房间关联已移除。');

        return PluginResponse::redirect('?page=spaces');
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

    private function requiredId(array $input, string $key): int
    {
        $value = $input[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new HttpException('空间编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('空间编号无效。', 422);
        }
        return $id;
    }
}

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
            'index' => $this->index($input),
            'create' => $this->create($input),
            'select' => $this->select($input),
            'project.delete' => $this->deleteProject($input),
            'project.restore' => $this->restoreProject($input),
            'spaces' => $this->spacesIndex($input),
            'space.save' => $this->saveSpace($input),
            'space.delete' => $this->deleteSpace($input),
            'space.restore' => $this->restoreSpace($input),
            'space.room.map' => $this->mapRoom($input),
            'space.room.unmap' => $this->unmapRoom($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function index(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        $archivedView = ($input['archived'] ?? null) === '1';

        return $this->context->view('projects.php', [
            'title' => '项目',
            'projects' => $this->projects->list($archivedView),
            'currentProject' => $this->context->currentProject(),
            'archivedView' => $archivedView,
            'canCreate' => Support::hasPermission($this->context->db, 'create_projects'),
            'canDeleteProjects' => Support::hasPermission($this->context->db, 'delete_projects'),
            'canViewDocuments' => Support::hasPermission($this->context->db, 'view_issues'),
            'canViewMaterials' => Support::hasPermission($this->context->db, 'view_materials'),
            'canViewQuotations' => Support::hasPermission($this->context->db, 'view_material_quotations'),
            'canViewSpaces' => Support::hasPermission($this->context->db, 'view_project_spaces'),
            'canViewSamples' => Support::hasPermission($this->context->db, 'view_projects')
                && Support::hasPermission($this->context->db, 'view_material_samples'),
            'canViewEstimates' => Support::hasPermission($this->context->db, 'view_material_estimates'),
            'canViewLaborCrews' => Support::hasPermission($this->context->db, 'view_labor_crews')
                && Support::hasPermission($this->context->db, 'view_projects'),
        ]);
    }

    private function spacesIndex(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $archivedView = ($input['archived'] ?? null) === '1';

        return $this->context->view('spaces.php', [
            'title' => '项目空间',
            'currentProject' => $project,
            'buildings' => $this->spaces->list($projectId, $archivedView),
            'archivedView' => $archivedView,
            'canManage' => Support::hasPermission($this->context->db, 'manage_project_spaces'),
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

    private function deleteSpace(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        Support::requirePermission($this->context->db, 'manage_project_spaces');
        $project = $this->context->requireCurrentProject();
        [$type, $entityId, $result] = $this->transaction(function () use ($input, $project): array {
            $type = $input['type'] ?? null;
            if (!is_string($type)) {
                throw new HttpException('空间类型无效。', 422);
            }
            $entityId = $this->requiredId($input, 'id', '空间');
            $result = $this->spaces->deleteOrArchive(
                (int) $project['project_id'],
                $type,
                $entityId,
                $this->userId()
            );
            Support::audit(
                $this->context->db,
                'project_space.delete',
                ucfirst($result) . ' ' . $type . ' ' . $entityId . '.'
            );
            return [$type, $entityId, $result];
        });
        Support::flash($result === 'deleted' ? '无关联的空间记录已删除。' : '空间记录含有关联数据，已归档并保留记录。');

        return PluginResponse::redirect('?page=spaces');
    }

    private function restoreSpace(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'view_project_spaces');
        Support::requirePermission($this->context->db, 'manage_project_spaces');
        $project = $this->context->requireCurrentProject();
        $this->transaction(function () use ($input, $project): void {
            $type = $input['type'] ?? null;
            if (!is_string($type)) {
                throw new HttpException('空间类型无效。', 422);
            }
            $entityId = $this->requiredId($input, 'id', '空间');
            $this->spaces->restore((int) $project['project_id'], $type, $entityId);
            Support::audit($this->context->db, 'project_space.restore', 'Restored ' . $type . ' ' . $entityId . '.');
        });
        Support::flash('空间记录已恢复。');

        return PluginResponse::redirect('?page=spaces&archived=1');
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

    private function deleteProject(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'delete_projects');
        [$projectId, $result] = $this->transaction(function () use ($input): array {
            $projectId = $this->requiredId($input, 'project_id', '项目');
            $result = $this->projects->deleteOrArchive($projectId, $this->userId());
            Support::audit(
                $this->context->db,
                'project.delete',
                ucfirst($result) . ' project ' . $projectId . '.'
            );
            return [$projectId, $result];
        });
        if ((int) ($_SESSION['active_project_id'] ?? 0) === $projectId) {
            unset($_SESSION['active_project_id']);
        }
        // Announce after commit so subscribers (e.g. public share pages) can
        // withdraw artifacts that must no longer be reachable.
        $this->context->emit(
            $result === 'deleted' ? 'project.deleted' : 'project.archived',
            ['project_id' => $projectId]
        );
        Support::flash($result === 'deleted' ? '无关联数据的项目已删除。' : '项目含有业务数据，已归档并保留记录。');

        return PluginResponse::redirect('?page=projects');
    }

    private function restoreProject(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_projects');
        Support::requirePermission($this->context->db, 'delete_projects');
        $projectId = $this->transaction(function () use ($input): int {
            $projectId = $this->requiredId($input, 'project_id', '项目');
            $this->projects->restore($projectId);
            Support::audit($this->context->db, 'project.restore', 'Restored project ' . $projectId . '.');
            return $projectId;
        });
        $this->context->emit('project.restored', ['project_id' => $projectId]);
        Support::flash('项目已恢复，请在项目列表中重新进入。');

        return PluginResponse::redirect('?page=projects&archived=1');
    }

    private function userId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            throw new HttpException('当前登录用户无效。', 401);
        }
        return $userId;
    }

    private function transaction(callable $operation): mixed
    {
        if ($this->context->db->inTransaction()) {
            return $operation();
        }

        $this->context->db->beginTransaction();
        try {
            $result = $operation();
            $this->context->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->context->db->inTransaction()) {
                $this->context->db->rollBack();
            }
            throw $exception;
        }
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

    private function requiredId(array $input, string $key, string $label = '空间'): int
    {
        $value = $input[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new HttpException($label . '编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException($label . '编号无效。', 422);
        }
        return $id;
    }
}

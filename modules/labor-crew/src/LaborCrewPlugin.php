<?php
declare(strict_types=1);

namespace ProjectO\Modules\LaborCrew;

use ProjectO\Export\XlsxWorkbook;
use ProjectO\HttpException;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginInterface;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Security\FieldCipher;
use ProjectO\Support;

/**
 * Construction crew contact directory.
 *
 * Controllers only orchestrate the request/permission/response boundary; every
 * validation rule, uniqueness check and encryption concern lives in
 * CrewDirectoryService. All crew reads and writes are scoped to the project
 * resolved from the server-side session — a client-submitted project id is never
 * trusted.
 */
final class LaborCrewPlugin implements PluginInterface
{
    private CrewDirectoryService $directory;
    private bool $encryptionReady;

    public function __construct(private readonly PluginContext $context)
    {
        $cipher = FieldCipher::fromEnvironment();
        $this->encryptionReady = $cipher !== null;
        $this->directory = new CrewDirectoryService($context->db, $cipher);
    }

    public function dispatch(string $handler, array $input): PluginResponse
    {
        return match ($handler) {
            'crews' => $this->crewsIndex($input),
            'types' => $this->typesIndex(),
            'crew.save' => $this->saveCrew($input),
            'crew.export' => $this->exportCrews(),
            'crew.archive' => $this->archiveCrew($input),
            'crew.restore' => $this->restoreCrew($input),
            'type.save' => $this->saveType($input),
            'type.delete' => $this->deleteType($input),
            default => throw new HttpException('未找到对应操作。', 404),
        };
    }

    private function crewsIndex(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crews');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $archivedView = ($input['archived'] ?? null) === '1';
        if ($archivedView) {
            Support::requirePermission($this->context->db, 'archive_labor_crews');
        }

        $data = $this->directory->directoryData($projectId, $archivedView);
        $canViewIdNumbers = Support::hasPermission($this->context->db, 'view_labor_crew_id_numbers');
        if ($canViewIdNumbers) {
            // Full digits are decrypted per row and only for holders of the
            // dedicated permission; everyone else sees the masked form alone.
            foreach ($data['crews'] as &$crewRow) {
                $crewRow['id_card_full'] = $this->directory->decryptIdCard(
                    $crewRow['id_card_number_encrypted'] ?? null
                );
            }
            unset($crewRow);
        }

        return $this->context->view('crews.php', [
            'title' => '施工班组',
            'currentProject' => $project,
            'crews' => $data['crews'],
            'types' => $data['types'],
            'typeFilter' => $this->optionalFilterId($input['type'] ?? null),
            'keyword' => is_string($input['keyword'] ?? null) ? trim($input['keyword']) : '',
            'archivedView' => $archivedView,
            'canEdit' => Support::hasPermission($this->context->db, 'edit_labor_crews'),
            'canArchive' => Support::hasPermission($this->context->db, 'archive_labor_crews'),
            'canViewIdNumbers' => $canViewIdNumbers,
            'canManageTypes' => Support::hasPermission($this->context->db, 'edit_labor_crew_types'),
            'encryptionReady' => $this->encryptionReady,
            'nav' => $this->nav(),
        ]);
    }

    private function typesIndex(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crew_types');

        return $this->context->view('types.php', [
            'title' => '工种目录',
            'types' => $this->directory->types(),
            'canEdit' => Support::hasPermission($this->context->db, 'edit_labor_crew_types'),
            'canDelete' => Support::hasPermission($this->context->db, 'edit_labor_crew_types'),
            'nav' => $this->nav(),
        ]);
    }

    private function saveCrew(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crews');
        Support::requirePermission($this->context->db, 'edit_labor_crews');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];

        $crewId = $this->directory->saveCrew($projectId, $input);
        $name = '';
        foreach ($this->directory->crews($projectId, true) as $row) {
            if ((int) $row['crew_id'] === $crewId) {
                $name = (string) $row['crew_name'];
                break;
            }
        }
        // Audit stores the crew name only: never the identity number, its
        // ciphertext or its fingerprint.
        Support::audit($this->context->db, 'labor.crew.save', 'Crew ' . $crewId . ' (' . $name . ') in project ' . $projectId . '.');
        Support::flash('班组「' . $name . '」已保存。');

        // Return to the same view the form came from, otherwise editing an
        // archived crew would land the user on a list that no longer shows it.
        $cameFromArchived = ($input['from_archived'] ?? null) === '1';

        return PluginResponse::redirect(
            '?page=labor-crews' . ($cameFromArchived ? '&archived=1' : '')
        );
    }

    /**
     * Excel export styled like the other module exports: merged title row with
     * the project name, metadata lines (time / total / per-trade counts), then
     * the header block and data rows. Columns are sequence number, craft type,
     * crew name, leader name, phone and remarks; identity numbers and WeChat ids
     * never enter the file, and archived crews are excluded. The download name
     * carries the project name, the export date and the daily sequence.
     */
    private function exportCrews(): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crews');
        Support::requirePermission($this->context->db, 'view_projects');
        $project = $this->context->requireCurrentProject();
        $projectId = (int) $project['project_id'];
        $rows = $this->directory->exportRows($projectId);

        // Phone numbers are plain strings here: XlsxWorkbook writes string cells
        // as inlineStr, so Excel keeps a +86 number as text without an escape
        // prefix (which would be shown to the reader).
        $typeCounts = [];
        $body = [];
        foreach ($rows as $row) {
            $typeName = (string) $row['type_name'];
            $typeCounts[$typeName] = ($typeCounts[$typeName] ?? 0) + 1;
            $body[] = [
                count($body) + 1,
                $typeName,
                (string) $row['crew_name'],
                (string) $row['leader_name'],
                (string) $row['leader_phone'],
                (string) ($row['notes'] ?? ''),
            ];
        }
        ksort($typeCounts, SORT_NATURAL);
        $statistics = [];
        foreach ($typeCounts as $typeName => $count) {
            $statistics[] = $typeName . ' ' . $count;
        }

        $exportDay = Support::nowLocal('Ymd');
        $exportSequence = Support::nextDailySequence($this->context->db, 'labor.crew.export_sequence', $exportDay);
        $projectLabel = Support::safeFileSegment((string) $project['project_name']);
        $fileName = '施工班组联系表_' . ($projectLabel !== '' ? $projectLabel : '项目')
            . '_' . $exportDay . '_' . $exportSequence . '.xlsx';
        $xlsx = XlsxWorkbook::build('施工班组联系表', [
            '序号', '工种', '班组名称', '负责人姓名', '负责人电话', '备注',
        ], $body, [
            'title' => '👷 ' . $project['project_name'] . '施工班组负责人联系表',
            'meta' => [
                '导出时间: ' . Support::nowLocal('Y-m-d H:i:s'),
                '记录总数: ' . count($body),
                '内容统计: 涉及工种 ' . count($typeCounts) . ' 个（' . implode('，', $statistics) . '）',
            ],
            // Keep the sequence column narrow and give the craft type, crew name
            // and remarks columns more room than the content-based default.
            'fixedWidths' => [
                0 => 6.0,
                1 => 16.0,
                2 => 30.0,
                5 => 40.0,
            ],
        ]);
        Support::audit(
            $this->context->db,
            'labor.crew.export',
            'Exported ' . count($body) . ' crew rows for project ' . $projectId . '.'
        );

        return PluginResponse::download(
            $fileName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $xlsx
        );
    }

    private function archiveCrew(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crews');
        Support::requirePermission($this->context->db, 'archive_labor_crews');
        $project = $this->context->requireCurrentProject();
        $crewId = $this->requiredId($input['crew_id'] ?? null);
        $this->directory->archiveCrew((int) $project['project_id'], $crewId);
        Support::audit($this->context->db, 'labor.crew.archive', 'Crew ' . $crewId . ' archived in project ' . (int) $project['project_id'] . '.');
        Support::flash('班组已归档，记录保留，可在「已归档」中恢复。');

        return PluginResponse::redirect('?page=labor-crews');
    }

    private function restoreCrew(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crews');
        Support::requirePermission($this->context->db, 'archive_labor_crews');
        $project = $this->context->requireCurrentProject();
        $crewId = $this->requiredId($input['crew_id'] ?? null);
        $this->directory->restoreCrew((int) $project['project_id'], $crewId);
        Support::audit($this->context->db, 'labor.crew.restore', 'Crew ' . $crewId . ' restored in project ' . (int) $project['project_id'] . '.');
        Support::flash('班组已恢复为在用。');

        return PluginResponse::redirect('?page=labor-crews');
    }

    private function saveType(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crew_types');
        Support::requirePermission($this->context->db, 'edit_labor_crew_types');
        $typeId = $this->directory->saveType($input);
        Support::audit($this->context->db, 'labor.crew_type.save', 'Type ' . $typeId . '.');
        Support::flash('工种已保存。');

        return PluginResponse::redirect('?page=labor-crew-types');
    }

    private function deleteType(array $input): PluginResponse
    {
        Support::requirePermission($this->context->db, 'view_labor_crew_types');
        Support::requirePermission($this->context->db, 'edit_labor_crew_types');
        $typeId = $this->requiredId($input['crew_type_id'] ?? null);
        $this->directory->deleteType($typeId);
        Support::audit($this->context->db, 'labor.crew_type.delete', 'Type ' . $typeId . '.');
        Support::flash('工种已删除。');

        return PluginResponse::redirect('?page=labor-crew-types');
    }

    /** Optional toolbar filter; anything unusable simply means "all types". */
    private function optionalFilterId(mixed $value): int
    {
        if ($value === null || $value === '' || !is_string($value)) {
            return 0;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return ($id === false || $id < 1) ? 0 : $id;
    }

    private function requiredId(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException('记录编号无效。', 422);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('记录编号无效。', 422);
        }

        return $id;
    }

    private function nav(): array
    {
        return [
            'projects' => Support::hasPermission($this->context->db, 'view_projects'),
            'crews' => Support::hasPermission($this->context->db, 'view_labor_crews')
                && Support::hasPermission($this->context->db, 'view_projects'),
            'types' => Support::hasPermission($this->context->db, 'view_labor_crew_types'),
        ];
    }
}

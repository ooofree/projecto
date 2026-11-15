<?php
declare(strict_types=1);

namespace ProjectO\Contracts;

use ProjectO\HttpException;

/**
 * Read-only public contract for the project space catalogue.
 *
 * The core owns this interface so business plugins can offer space selection
 * without reading another plugin's tables. `project.foundation` provides the
 * implementation; consumers depend on this interface only.
 *
 * Every method is scoped to a server-verified project ID. Implementations must
 * never accept a project ID from client input.
 */
interface ProjectSpaceCatalog
{
    /**
     * Selectable spaces plus display labels for one project.
     *
     * `options` lists only active (non-archived) spaces and is safe to feed into
     * a selection control. `labels` also covers archived spaces so records that
     * already reference them keep a readable name.
     *
     * Each option also carries `path` (ancestor names, outermost first, ending
     * with its own name) and `depth`, so a consumer can render an indented
     * hierarchy and a breadcrumb without knowing the space schema.
     *
     * @return array{
     *     options: list<array{
     *         value: string,
     *         label: string,
     *         type: string,
     *         id: int,
     *         name: string,
     *         path: list<string>,
     *         depth: int,
     *         building_id: int,
     *         floor_id: int|null,
     *         area_id: int|null
     *     }>,
     *     labels: array<string, string>
     * }
     */
    public function locationCatalog(int $projectId): array;

    /**
     * Validate one space reference and resolve it to storage columns.
     *
     * @param string $type one of building, floor, area, room
     * @return array{
     *     type: string,
     *     id: int,
     *     label: string,
     *     building_id: int,
     *     floor_id: int|null,
     *     area_id: int|null,
     *     room_id: int|null
     * }
     * @throws HttpException when the space is outside the project or archived
     */
    public function requireLocation(int $projectId, string $type, int $id): array;
}

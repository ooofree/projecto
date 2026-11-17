<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * Optional plugin capability (V2.1): publish the project directory used by the
 * top-bar project switcher. Implemented by project.foundation only; the core
 * aggregates the list for display and never queries business tables itself.
 * The provider applies its own permission gate (view_projects); the switcher
 * is UX only — selecting a project still goes through the guarded POST route.
 */
interface ProjectDirectoryProvider
{
    /** @return list<array{project_id: int, project_name: string, status: string}> */
    public function projectDirectory(): array;
}

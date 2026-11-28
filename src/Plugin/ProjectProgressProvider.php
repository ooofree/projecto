<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * Optional plugin capability: publish a read-only progress summary per project
 * for the project-home cards. The core only aggregates and validates the
 * numbers; the yardstick itself (which rows count, how the rate is weighted)
 * stays owned by the plugin that reports it.
 *
 * Providers apply their own permission gate and return an empty list when the
 * viewer may not see their figures, so a missing entry simply renders no bar.
 */
interface ProjectProgressProvider
{
    /**
     * @return list<array{
     *     project_id: int,
     *     plan_count: int,
     *     item_total: int,
     *     item_closed: int,
     *     qty_total: float,
     *     qty_closed: float,
     *     qty_submitted: float
     * }>
     */
    public function projectProgress(): array;
}

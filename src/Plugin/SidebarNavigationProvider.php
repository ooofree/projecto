<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

/**
 * Optional plugin capability (V2.1): declare project-level sidebar entries.
 *
 * A plugin that implements this interface publishes its own menu items for the
 * global project sidebar. The core registry only aggregates and renders what
 * plugins declare — it never hardcodes business menus, and plugins never read
 * each other's internals. Permission gating stays owned by the declaring
 * plugin; hiding an entry is UX only, every route keeps its server-side checks.
 *
 * Item shape: ['group' => string, 'page' => string, 'label' => string,
 *              'icon' => string (optional single glyph),
 *              'order' => int (optional; sorts entries inside the group)]
 * Groups are display buckets; the layout renders them in a fixed business
 * order and sorts each bucket by 'order' (default 500).
 */
interface SidebarNavigationProvider
{
    /** @return list<array{group: string, page: string, label: string, icon?: string, order?: int}> */
    public function sidebarItems(): array;
}

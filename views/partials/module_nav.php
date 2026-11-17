<?php
/**
 * Shared module navigation renderer (UI Design System V2, TASK-02).
 *
 * Plugins keep declaring their own menu items and permission gates; this
 * partial only owns the markup so structure, current-page highlighting and
 * spacing stay identical across modules. It adds no authorization: an item
 * the plugin filtered out never reaches here, and the server still enforces
 * every route's permissions.
 *
 * Expected variables:
 *   $navItems   list of ['page' => string, 'label' => string] in display order
 *   $navCurrent the ?page= value of the current view (one match at most)
 *   $navLabel   aria-label for the <nav> landmark
 *   $escape     the standard escaping callable
 */
$navItems = $navItems ?? [];
$navCurrent = (string) ($navCurrent ?? '');
$navLabel = (string) ($navLabel ?? '模块导航');
?>
<nav class="module-nav" aria-label="<?= $escape($navLabel) ?>">
<?php foreach ($navItems as $navItem): ?>
    <?php if (($navItem['page'] ?? '') === $navCurrent): ?>
    <a class="button button-primary" href="?page=<?= $escape($navItem['page']) ?>" aria-current="page"><?= $escape($navItem['label']) ?></a>
    <?php else: ?>
    <a class="button button-secondary" href="?page=<?= $escape($navItem['page']) ?>"><?= $escape($navItem['label']) ?></a>
    <?php endif; ?>
<?php endforeach; ?>
</nav>
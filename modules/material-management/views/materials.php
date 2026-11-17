<?php
ob_start();
require __DIR__ . '/_nav.php';

$materials = $data['materials'];
$categories = $data['categories'];

// Category rail order follows the service query (sort_order, name, id). Each
// bucket keeps that order; uncategorised materials collect at the end so the
// rail always mirrors what the table shows for the selected category.
$categoryBuckets = [];
foreach ($categories as $category) {
    $categoryBuckets[(string) (int) $category['category_id']] = [
        'id' => (int) $category['category_id'],
        'name' => (string) $category['category_name'],
        'count' => 0,
    ];
}
$uncategorisedCount = 0;
foreach ($materials as $material) {
    $key = (string) (int) ($material['category_id'] ?? 0);
    if (isset($categoryBuckets[$key])) {
        $categoryBuckets[$key]['count']++;
    } else {
        $uncategorisedCount++;
    }
}

// Common units first, then whatever the project already uses. The datalist
// turns the unit field into a one-click pick instead of repeated typing.
$unitSuggestions = ['m2', 'm', 'kg', 't', '个', '套', '项', '张', '块', '台', '只', '米', '樘'];
foreach ($materials as $material) {
    $unit = trim((string) $material['unit']);
    if ($unit !== '' && !in_array($unit, $unitSuggestions, true)) {
        $unitSuggestions[] = $unit;
    }
}
?>
<section class="page-heading report-intro">
    <div>
        <p class="eyebrow">项目材料主数据</p>
        <h1 class="report-heading">材料管理</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。分类和材料仅属于当前项目。</p>
    </div>
    <div class="project-list-actions">
        <?php if ($canEdit): ?>
            <button class="button button-primary" type="button" data-entry-focus>新增材料</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($canEdit): ?>
    <details class="panel material-entry-panel" id="material-entry" data-entry-disclosure>
        <summary class="material-entry-summary">
            <span class="material-entry-summary-title">快速录入</span>
            <span class="material-entry-summary-hint">分类 → 编码、名称、单位 → 回车即存；分类与单位自动沿用，编码尾号自动递增</span>
            <span class="entry-counter" data-material-entry-counter aria-live="polite">本次已录 <strong>0</strong> 项</span>
        </summary>
        <form method="post" action="" class="material-entry" id="material-entry-form" data-material-add data-project-id="<?= (int) $currentProject['project_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="material.save">
            <div class="entry-context">
                <label class="entry-context-field"><span>材料分类</span>
                    <select name="category_id" data-entry-category>
                        <option value="">未分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="entry-context-stat" data-entry-category-stat aria-live="polite"></span>
                <label class="entry-context-toggle"><input type="checkbox" data-entry-follow checked><span>录入分类跟随左侧所选</span></label>
                <span class="entry-context-hint">保存后分类、单位保留，只清空编码、名称、规格、说明</span>
            </div>
            <div class="entry-grid">
                <label class="entry-field entry-field-code">
                    <span class="entry-field-label">材料编码 <span class="entry-field-hint">可选 · 尾号自动递增</span></span>
                    <input name="material_code" maxlength="50" placeholder="如 DG-001" autocomplete="off" data-entry-code>
                </label>
                <label class="entry-field entry-field-name">
                    <span class="entry-field-label">材料名称</span>
                    <input name="material_name" maxlength="255" required placeholder="如 筒灯" autocomplete="off" data-entry-name>
                </label>
                <label class="entry-field entry-field-unit">
                    <span class="entry-field-label">计量单位</span>
                    <input name="unit" maxlength="50" required list="material-unit-options" placeholder="个" autocomplete="off" data-entry-unit>
                </label>
                <button class="button button-primary entry-submit" type="submit">保存并继续<span class="entry-submit-key" aria-hidden="true">Enter ↵</span></button>
            </div>
            <div class="entry-code-tools">
                <button class="button button-secondary button-mini" type="button" data-entry-code-next hidden>沿用分类内尾号 <strong data-entry-code-preview></strong></button>
                <span class="entry-code-note" data-entry-code-note hidden></span>
            </div>
            <datalist id="material-unit-options">
                <?php foreach ($unitSuggestions as $unit): ?>
                    <option value="<?= $escape($unit) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <div class="entry-grid entry-grid-secondary">
                <label class="entry-field">规格（可选）<textarea name="specifications" rows="1" maxlength="20000" placeholder="尺寸 / 型号 / 材质等，回车保存" data-entry-single-line></textarea></label>
                <label class="entry-field">材料说明（可选）<textarea name="material_description_detailed" rows="1" maxlength="20000" placeholder="补充说明，回车保存" data-entry-single-line></textarea></label>
            </div>
            <p class="entry-status" data-entry-status role="status" aria-live="polite" hidden></p>
        </form>
        <div class="entry-recent" data-entry-recent hidden>
            <span class="entry-recent-label">本次录入</span>
            <div class="entry-recent-chips" data-entry-recent-chips></div>
        </div>
    </details>
<?php endif; ?>

<div class="material-workspace<?= $canEdit ? ' has-entry' : '' ?>" id="material-workspace" data-material-workspace>
    <aside class="material-rail" id="material-rail" aria-label="材料分类导航">
        <div class="rail-head">
            <span>材料分类</span>
            <?php if ($canEdit): ?><button class="rail-manage" type="button" data-disclosure-open="material-categories">管理</button><?php endif; ?>
        </div>
        <div class="rail-list" role="group" aria-label="选择材料分类">
            <button class="rail-item is-active" type="button" data-rail-category="" aria-pressed="true">
                <span class="rail-name">全部材料</span>
                <span class="rail-count" data-rail-count=""><?= count($materials) ?></span>
            </button>
            <?php foreach ($categoryBuckets as $bucket): ?>
                <button class="rail-item" type="button" data-rail-category="<?= $bucket['id'] ?>" aria-pressed="false">
                    <span class="rail-name"><?= $escape($bucket['name']) ?></span>
                    <span class="rail-count" data-rail-count="<?= $bucket['id'] ?>"><?= $bucket['count'] ?></span>
                </button>
            <?php endforeach; ?>
            <?php if ($uncategorisedCount > 0): ?>
                <button class="rail-item" type="button" data-rail-category="0" aria-pressed="false">
                    <span class="rail-name">未分类</span>
                    <span class="rail-count" data-rail-count="0"><?= $uncategorisedCount ?></span>
                </button>
            <?php endif; ?>
        </div>
        <p class="rail-hint">选中分类后清单与录入都保持该分类，同类编号一目了然。</p>
    </aside>

    <section class="panel material-list-panel">
        <div class="panel-heading">
            <h2>材料清单</h2>
            <span class="muted" data-material-total><?= count($materials) ?> 项</span>
        </div>
        <div class="toolbar" role="search" aria-label="筛选材料">
            <label>
                搜索目录
                <input id="material-search" type="search" placeholder="名称、编码或规格" autocomplete="off">
            </label>
            <label>
                材料分类
                <select id="material-category" name="category_id" form="material-export-form">
                    <option value="">全部分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                    <?php endforeach; ?>
                    <option value="0">未分类</option>
                </select>
            </label>
            <span class="toolbar-count" id="material-count" data-unit=" 项材料" aria-live="polite"><?= count($materials) ?> 项材料</span>
            <form method="post" action="" id="material-export-form" class="inline-form toolbar-export">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.export">
                <button class="button button-secondary" type="submit">导出 Excel</button>
            </form>
        </div>

        <div class="table-wrap" id="materials-table-wrap">
            <table class="report-table" id="materials-table">
                <thead>
                <tr>
                    <th scope="col">编码</th>
                    <th scope="col">材料 / 规格</th>
                    <th scope="col">分类</th>
                    <th scope="col">单位</th>
                    <th scope="col" class="actions">操作</th>
                </tr>
                </thead>
                <tbody data-materials-body>
                <?php foreach ($materials as $material): ?>
                    <?php
                    $searchText = implode(' ', [
                        $material['material_name'],
                        $material['material_code'] ?? '',
                        $material['specifications'] ?? '',
                        $material['material_description_detailed'] ?? '',
                        $material['category_name'] ?? '',
                    ]);
                    ?>
                    <tr class="material-row"
                        data-material-id="<?= (int) $material['material_id'] ?>"
                        data-search="<?= $escape(mb_strtolower($searchText, 'UTF-8')) ?>"
                        data-category="<?= (int) ($material['category_id'] ?? 0) ?>">
                        <td><span class="report-code"><?= $escape($material['material_code'] ?: '—') ?></span></td>
                        <td>
                            <span class="material-primary"><?= $escape($material['material_name']) ?></span>
                            <span class="material-mobile-meta"><?= $escape($material['material_code'] ?: '—') ?> · <?= $escape($material['category_name'] ?? '未分类') ?></span>
                            <?php if (trim((string) ($material['specifications'] ?? '')) !== ''): ?>
                                <span class="material-secondary"><?= $escape($material['specifications']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($material['category_name'] ?? '未分类') ?></td>
                        <td><?= $escape($material['unit']) ?></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($canEdit): ?>
                                    <button class="button button-secondary" type="button" data-dialog-open="material-edit-<?= (int) $material['material_id'] ?>">编辑</button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="" data-confirm="确定删除材料「<?= $escape($material['material_name']) ?>」？已被报价、测算、样品或招标引用的材料无法删除。">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="material.delete">
                                        <input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                                        <button class="button button-quiet" type="submit">删除</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="report-empty" id="materials-no-match"<?= $materials === [] ? '' : ' hidden' ?>><?= $materials === [] ? '目录尚无材料。' : '没有符合条件的材料。' ?><?php if ($canEdit): ?>可在上方快速录入第一项。<?php endif; ?></p>
        </div>
    </section>
</div>

<?php if ($data['categories'] !== [] || $canEdit): ?>
    <details class="disclosure category-disclosure" id="material-categories">
        <summary>管理材料分类<span class="muted"><?= count($categories) ?> 个分类</span></summary>
        <div class="disclosure-content">
            <?php if ($canEdit): ?>
                <form method="post" action="" class="inline-editor">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.category.save">
                    <label>新增分类<input name="category_name" maxlength="255" required></label>
                    <label>上级分类
                        <select name="parent_category_id">
                            <option value="">无</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>排序值<input type="number" name="sort_order" min="0" max="1000000" value="0"></label>
                    <button class="button button-primary" type="submit">新增分类</button>
                </form>
            <?php endif; ?>

            <?php if ($categories !== []): ?>
                <div class="table-wrap">
                    <table class="category-table">
                        <thead>
                        <tr><th scope="col">分类名称</th><th scope="col">上级分类</th><th scope="col">排序</th><th scope="col"><span class="sr-only">操作</span></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="category-name"><?= $escape($category['category_name']) ?></td>
                                <td><?= $escape($category['parent_name'] ?? '—') ?></td>
                                <td><?= (int) $category['sort_order'] ?></td>
                                <td>
                                    <div class="row-actions">
                                        <?php if ($canEdit): ?>
                                            <details class="category-editor">
                                                <summary>编辑</summary>
                                                <form method="post" action="" class="inline-editor">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="material.category.save">
                                                    <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">
                                                    <label>分类名称<input name="category_name" maxlength="255" value="<?= $escape($category['category_name']) ?>" required></label>
                                                    <label>上级分类
                                                        <select name="parent_category_id">
                                                            <option value="">无</option>
                                                            <?php foreach ($categories as $parent): if ((int) $parent['category_id'] === (int) $category['category_id']) { continue; } ?>
                                                                <option value="<?= (int) $parent['category_id'] ?>" <?= (int) $parent['category_id'] === (int) $category['parent_category_id'] ? 'selected' : '' ?>><?= $escape($parent['category_name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>排序值<input type="number" name="sort_order" min="0" max="1000000" value="<?= (int) $category['sort_order'] ?>"></label>
                                                    <button class="button button-secondary" type="submit">保存分类</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <form method="post" action="" data-confirm="确定删除分类「<?= $escape($category['category_name']) ?>」？仍有材料或下级分类的分类无法删除。">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="material.category.delete">
                                                <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">
                                                <button class="button button-quiet" type="submit">删除</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="report-empty">暂无分类。</p>
            <?php endif; ?>
        </div>
    </details>
<?php endif; ?>

<?php if ($canEdit): ?>
    <div data-material-dialogs>
    <?php foreach ($materials as $material): ?>
        <dialog class="edit-dialog" id="material-edit-<?= (int) $material['material_id'] ?>" aria-labelledby="material-edit-title-<?= (int) $material['material_id'] ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">材料主数据</p>
                    <h2 id="material-edit-title-<?= (int) $material['material_id'] ?>">编辑材料</h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <form method="post" action="" class="form-grid" data-material-edit>
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.save">
                <input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                <label>材料编码<input name="material_code" maxlength="50" value="<?= $escape($material['material_code'] ?? '') ?>"></label>
                <label>材料名称<input name="material_name" maxlength="255" value="<?= $escape($material['material_name']) ?>" required></label>
                <label>计量单位<input name="unit" maxlength="50" list="material-unit-options" value="<?= $escape($material['unit']) ?>" required></label>
                <label>分类
                    <select name="category_id">
                        <option value="">未分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $material['category_id'] ? 'selected' : '' ?>><?= $escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">规格<textarea name="specifications" rows="3" maxlength="20000"><?= $escape($material['specifications'] ?? '') ?></textarea></label>
                <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="3" maxlength="20000"><?= $escape($material['material_description_detailed'] ?? '') ?></textarea></label>
                <p class="entry-status field-wide" data-edit-status role="status" aria-live="polite" hidden></p>
                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改<span class="entry-submit-key" aria-hidden="true">Ctrl+Enter</span></button></div>
            </form>
        </dialog>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>
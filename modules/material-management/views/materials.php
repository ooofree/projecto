<?php
ob_start();
require __DIR__ . '/_nav.php';

$materials = $data['materials'];
$categories = $data['categories'];

// 组成子材料候选（按父材料）：规则与 MaterialCatalogService::compositionCandidates
// 完全一致——单层规则，已有组成子项的材料不能再当子件；排除自身与已添加子件；
// 被其他定制项引用的子件仍可共享。这里用已加载的材料做等价筛选，避免逐材料再查库；
// 服务端 saveComposition 仍按同一规则强制校验。
$compositionsByParent = is_array($compositionsByParent ?? null) ? $compositionsByParent : [];
// 「CP-子项编码」建议值（键为材料 ID）：父项默认沿用子件编码，可自定义或留空。
$assemblyCodeSuggestions = is_array($assemblyCodeSuggestions ?? null) ? $assemblyCodeSuggestions : [];
$compositionChildOptions = [];
foreach ($materials as $parentMaterial) {
    $parentId = (int) $parentMaterial['material_id'];
    $existingChildIds = array_map(
        static fn (array $row): int => (int) $row['child_material_id'],
        $compositionsByParent[$parentId] ?? []
    );
    $options = [];
    foreach ($materials as $candidate) {
        $candidateId = (int) $candidate['material_id'];
        if ($candidateId === $parentId || (int) ($candidate['child_count'] ?? 0) > 0 || in_array($candidateId, $existingChildIds, true)) {
            continue;
        }
        $options[] = $candidate;
    }
    $compositionChildOptions[$parentId] = $options;
}

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
                    <input name="material_code" maxlength="50" placeholder="如 DG-001" autocomplete="off" data-entry-code inputmode="text" autocapitalize="off" spellcheck="false" enterkeyhint="done">
                </label>
                <label class="entry-field entry-field-name">
                    <span class="entry-field-label">材料名称</span>
                    <input name="material_name" maxlength="255" required placeholder="如 筒灯" autocomplete="off" data-entry-name enterkeyhint="done">
                </label>
                <label class="entry-field entry-field-unit">
                    <span class="entry-field-label">计量单位</span>
                    <input name="unit" maxlength="50" required list="material-unit-options" placeholder="个" autocomplete="off" data-entry-unit autocapitalize="off" spellcheck="false" enterkeyhint="done">
                </label>
                <div class="entry-unit-quick" data-unit-quick role="group" aria-label="常用单位快捷选择" hidden></div>
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
                <label class="entry-field">规格（可选）<textarea name="specifications" rows="1" maxlength="20000" placeholder="尺寸 / 型号 / 材质等，回车保存" data-entry-single-line autocapitalize="off" enterkeyhint="done"></textarea></label>
                <label class="entry-field">材料说明（可选）<textarea name="material_description_detailed" rows="1" maxlength="20000" placeholder="补充说明，回车保存" data-entry-single-line autocapitalize="off" enterkeyhint="done"></textarea></label>
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
        <div class="toolbar material-toolbar" role="search" aria-label="筛选材料">
            <label class="material-toolbar-search">
                搜索目录
                <input id="material-search" type="search" placeholder="名称、编码或规格" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search">
            </label>
            <label class="material-toolbar-category">
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
                    <th scope="col">送样 / 组成</th>
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
                        <td class="material-cell-code"><span class="report-code"><?= $escape($material['material_code'] ?: '—') ?></span></td>
                        <td class="material-cell-main">
                            <span class="material-primary"><?= $escape($material['material_name']) ?></span>
                            <span class="material-mobile-meta"><?= $escape($material['material_code'] ?: '—') ?> · <?= $escape($material['category_name'] ?? '未分类') ?> · <?= $escape($material['unit']) ?></span>
                            <?php if (trim((string) ($material['specifications'] ?? '')) !== ''): ?>
                                <span class="material-secondary"><?= $escape($material['specifications']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="material-cell-category"><?= $escape($material['category_name'] ?? '未分类') ?></td>
                        <td class="material-cell-unit"><?= $escape($material['unit']) ?></td>
                        <td class="material-cell-status">
                            <?php $childCount = (int) ($material['child_count'] ?? 0); ?>
                            <?php if (($material['sample_required'] ?? 'required') === 'exempt'): ?>
                                <span class="status-badge is-neutral">无需送样</span>
                            <?php else: ?>
                                <span class="status-badge is-green">需送样</span>
                            <?php endif; ?>
                            <?php if ($childCount > 0): ?>
                                <button type="button" class="material-secondary material-link"
                                        data-dialog-open="material-edit-<?= (int) $material['material_id'] ?>"
                                        data-focus-composition>定制项 · <?= $childCount ?> 个组件</button>
                            <?php elseif ((int) ($material['parent_count'] ?? 0) > 0): ?>
                                <span class="material-secondary">组成子件 · 被 <?= (int) $material['parent_count'] ?> 个父项引用</span>
                            <?php endif; ?>
                        </td>
                        <td class="material-cell-actions">
                            <div class="row-actions">
                                <?php if ($canEdit): ?>
                                    <button class="button button-secondary" type="button" data-dialog-open="material-edit-<?= (int) $material['material_id'] ?>" aria-label="编辑材料「<?= $escape($material['material_name']) ?>」">编辑</button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="" data-confirm="确定删除材料「<?= $escape($material['material_name']) ?>」？已被报价、测算、样品、招标或材料组成引用的材料无法删除。">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="material.delete">
                                        <input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                                        <button class="button button-quiet" type="submit" aria-label="删除材料「<?= $escape($material['material_name']) ?>」">删除</button>
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

<?php if ($canEdit): ?>
    <div class="material-mobile-bar" data-mobile-actions role="group" aria-label="材料快捷操作" hidden>
        <button class="button button-primary" type="button" data-entry-focus>＋ 新增材料</button>
        <button class="button button-secondary" type="button" data-disclosure-open="material-categories">管理分类</button>
    </div>
<?php endif; ?>

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
                            <tr class="category-row">
                                <td class="category-cell-name">
                                    <?= $escape($category['category_name']) ?>
                                    <span class="category-mobile-meta">上级 <?= $escape($category['parent_name'] ?? '—') ?> · 排序 <?= (int) $category['sort_order'] ?></span>
                                </td>
                                <td class="category-cell-parent"><?= $escape($category['parent_name'] ?? '—') ?></td>
                                <td class="category-cell-sort"><?= (int) $category['sort_order'] ?></td>
                                <td class="category-cell-actions">
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
    <?php /* 行模板：连续添加子材料时由 JS 克隆插行，字段与既有行完全一致。 */ ?>
    <template data-composition-row-template>
        <?php /* table/tbody wrapper keeps the row parseable inside a template; only the tr is cloned. */ ?>
        <table><tbody>
        <tr data-composition-row>
            <td><span data-composition-name></span> <span class="muted" data-composition-unit></span></td>
            <td>
                <form method="post" action="" class="inline-form" data-composition-quantity>
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.composition.save">
                    <input type="hidden" name="composition_id" value="">
                    <input type="hidden" name="parent_material_id" value="">
                    <input type="hidden" name="child_material_id" value="">
                    <input type="number" name="quantity" min="0.01" step="0.01" value="1.00" required aria-label="数量系数" inputmode="decimal">
                    <button class="button button-secondary" type="submit">保存系数</button>
                </form>
            </td>
            <td>
                <form method="post" action="" data-confirm="确定移除该组成子材料？子材料本身的样品记录不受影响。" data-composition-delete>
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.composition.delete">
                    <input type="hidden" name="composition_id" value="">
                    <button class="button button-quiet" type="submit">移除</button>
                </form>
            </td>
        </tr>
        </tbody></table>
    </template>
    <div data-material-dialogs>
    <?php foreach ($materials as $material): ?>
        <?php
        $selfId = (int) $material['material_id'];
        $materialChildCount = (int) ($material['child_count'] ?? 0);
        $materialParentCount = (int) ($material['parent_count'] ?? 0);
        $assemblyParents = [];
        foreach ($materials as $candidateParent) {
            $candidateId = (int) $candidateParent['material_id'];
            if ($candidateId === $selfId || (int) ($candidateParent['child_count'] ?? 0) === 0) {
                continue;
            }
            $alreadyLinked = false;
            foreach ($compositionsByParent[$candidateId] ?? [] as $edge) {
                if ((int) $edge['child_material_id'] === $selfId) {
                    $alreadyLinked = true;
                    break;
                }
            }
            if (!$alreadyLinked) {
                $assemblyParents[] = $candidateParent;
            }
        }
        $assemblyCode = (string) ($assemblyCodeSuggestions[$selfId] ?? '');
        ?>
        <dialog class="edit-dialog material-edit-dialog" id="material-edit-<?= (int) $material['material_id'] ?>" aria-labelledby="material-edit-title-<?= (int) $material['material_id'] ?>">
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
                <label>材料编码<input name="material_code" maxlength="50" value="<?= $escape($material['material_code'] ?? '') ?>" autocapitalize="off" spellcheck="false"></label>
                <label>材料名称<input name="material_name" maxlength="255" value="<?= $escape($material['material_name']) ?>" required></label>
                <label>计量单位<input name="unit" maxlength="50" list="material-unit-options" value="<?= $escape($material['unit']) ?>" required autocapitalize="off" spellcheck="false"></label>
                <label>分类
                    <select name="category_id">
                        <option value="">未分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $material['category_id'] ? 'selected' : '' ?>><?= $escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>送样要求
                    <select name="sample_required">
                        <option value="required" <?= ($material['sample_required'] ?? 'required') === 'required' ? 'selected' : '' ?>>需送样</option>
                        <option value="exempt" <?= ($material['sample_required'] ?? 'required') === 'exempt' ? 'selected' : '' ?>>无需送样（定制整合产品）</option>
                    </select>
                </label>
                <label class="field-wide">规格<textarea name="specifications" rows="3" maxlength="20000"><?= $escape($material['specifications'] ?? '') ?></textarea></label>
                <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="3" maxlength="20000"><?= $escape($material['material_description_detailed'] ?? '') ?></textarea></label>
                <p class="entry-status field-wide" data-edit-status role="status" aria-live="polite" hidden></p>
                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改<span class="entry-submit-key" aria-hidden="true">Ctrl+Enter</span></button></div>
            </form>
            <div class="dialog-section composition-section"
                 data-composition-section
                 data-parent-id="<?= (int) $material['material_id'] ?>"
                 data-parent-unit="<?= $escape($material['unit']) ?>">
                <h3>组成关系（定制项 · 父—子单层）</h3>
                <?php $materialCompositions = $compositionsByParent[(int) $material['material_id']] ?? []; ?>
                <p class="muted">定制整合产品（如玻璃门）由子材料组成，本身默认无需送样，子材料各自独立送样。数量系数 = 每 1 <?= $escape($material['unit']) ?> 父项所需子材料数量（单位取子材料自身单位）；仅支持单层，不能嵌套。组成关系不改变子材料的送样要求。</p>
                <p class="entry-status composition-status" data-composition-status role="status" aria-live="polite" hidden></p>
                <?php /* 子材料清单常驻页面：异步添加时直接插行、清空选择器，弹窗保持打开连续录入。 */ ?>
                <div class="table-wrap" data-composition-table<?= $materialCompositions === [] ? ' hidden' : '' ?>>
                    <table class="report-table">
                        <thead>
                        <tr><th scope="col">子材料</th><th scope="col">数量系数</th><th scope="col" class="actions">操作</th></tr>
                        </thead>
                        <tbody data-composition-body>
                        <?php foreach ($materialCompositions as $composition): ?>
                            <tr data-composition-row
                                data-composition-id="<?= (int) $composition['composition_id'] ?>"
                                data-child-id="<?= (int) $composition['child_material_id'] ?>">
                                <td><span data-composition-name><?= $escape(($composition['material_code'] ? $composition['material_code'] . ' · ' : '') . $composition['material_name']) ?></span> <span class="muted" data-composition-unit><?= $escape($composition['unit']) ?></span></td>
                                <td>
                                    <form method="post" action="" class="inline-form" data-composition-quantity>
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="material.composition.save">
                                        <input type="hidden" name="composition_id" value="<?= (int) $composition['composition_id'] ?>">
                                        <input type="hidden" name="parent_material_id" value="<?= (int) $material['material_id'] ?>">
                                        <input type="hidden" name="child_material_id" value="<?= (int) $composition['child_material_id'] ?>">
                                        <input type="number" name="quantity" min="0.01" step="0.01" value="<?= $escape((string) $composition['quantity']) ?>" required aria-label="数量系数" inputmode="decimal">
                                        <button class="button button-secondary" type="submit">保存系数</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" action="" data-confirm="确定移除该组成子材料？子材料本身的样品记录不受影响。" data-composition-delete>
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="material.composition.delete">
                                        <input type="hidden" name="composition_id" value="<?= (int) $composition['composition_id'] ?>">
                                        <button class="button button-quiet" type="submit">移除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted" data-composition-empty<?= $materialCompositions === [] ? '' : ' hidden' ?>>尚未添加组成子材料。</p>
                <?php if ((int) ($material['parent_count'] ?? 0) > 0): ?>
                    <p class="muted">该材料已是其他定制项的组成子件；按单层规则，不能再为它添加组成。</p>
                <?php else: ?>
                    <?php $childCandidates = $compositionChildOptions[(int) $material['material_id']] ?? []; ?>
                    <form method="post" action="" class="inline-editor" data-composition-add<?= $childCandidates === [] ? ' hidden' : '' ?>>
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="material.composition.save">
                        <input type="hidden" name="parent_material_id" value="<?= (int) $material['material_id'] ?>">
                        <label>子材料
                            <select name="child_material_id" required>
                                <option value="">请选择子材料</option>
                                <?php foreach ($childCandidates as $candidate): ?>
                                    <option value="<?= (int) $candidate['material_id'] ?>"><?= $escape(($candidate['material_code'] ? $candidate['material_code'] . ' · ' : '') . $candidate['material_name']) ?>（<?= $escape($candidate['unit']) ?>）</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>数量系数<input type="number" name="quantity" min="0.01" step="0.01" value="1.00" required inputmode="decimal"></label>
                        <button class="button button-primary" type="submit">添加组成</button>
                    </form>
                    <p class="muted" data-composition-note<?= ($materialCompositions === [] && $childCandidates !== []) ? '' : ' hidden' ?>>添加首条组成后，本材料默认转为“无需送样”，可在上方送样要求中改回。保存后弹窗保持打开，选下一条即可继续添加。</p>
                    <p class="muted" data-composition-exhausted<?= $childCandidates === [] ? '' : ' hidden' ?>>暂无可添加的子材料（子材料不能是已有组成的定制项）。</p>
                <?php endif; ?>
            </div>
            <?php if ($canEdit): ?>
                <div class="dialog-section assembly-section"
                     data-assembly-section
                     data-child-id="<?= $selfId ?>"
                     data-child-unit="<?= $escape($material['unit']) ?>"
                     data-child-name="<?= $escape($material['material_name']) ?>">
                    <h3>归入父项（子件 → 父项）</h3>
                    <p class="muted">从当前子材料向上建父项：可直接加入已有集成件，或新建一个父项并把本材料作为它的第一条子件。组成仍是父—子单层；子件可以被多个父项共用。</p>
                    <p class="entry-status" data-assembly-status role="status" aria-live="polite" hidden></p>
                    <?php if ($materialChildCount > 0): ?>
                        <p class="muted">该材料已有组成子项，是父项；按单层规则不能再归入其他父项。</p>
                    <?php else: ?>
                        <?php if ($materialParentCount > 0): ?>
                            <p class="muted">当前已被 <?= $materialParentCount ?> 个父项引用；仍可加入其他父项。</p>
                        <?php endif; ?>
                        <?php if ($assemblyParents === []): ?>
                            <p class="muted">暂无可加入的其他父项，可直接在下方新建父项。</p>
                        <?php else: ?>
                            <form method="post" action="" class="inline-editor" data-assembly-attach>
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="material.composition.save">
                                <input type="hidden" name="child_material_id" value="<?= $selfId ?>">
                                <label>加入已有父项
                                    <select name="parent_material_id" required>
                                        <option value="">请选择父项</option>
                                        <?php foreach ($assemblyParents as $parentOption): ?>
                                            <option value="<?= (int) $parentOption['material_id'] ?>"><?= $escape(($parentOption['material_code'] ? $parentOption['material_code'] . ' · ' : '') . $parentOption['material_name']) ?>（<?= $escape($parentOption['unit']) ?>，已有 <?= (int) ($parentOption['child_count'] ?? 0) ?> 个子件）</option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>数量系数<input type="number" name="quantity" min="0.01" step="0.01" value="1.00" required inputmode="decimal"></label>
                                <button class="button button-secondary" type="submit">加入已有父项</button>
                            </form>
                            <p class="muted">数量系数 = 每 1 父项单位所需本子件数量（单位取本材料自身单位），父子单位不同时按实际用量填。</p>
                        <?php endif; ?>
                        <details class="assembly-create"<?= $assemblyParents === [] ? ' open' : '' ?>>
                            <summary>＋ 新建父项并纳入</summary>
                            <form method="post" action="" class="form-grid assembly-create-form" data-assembly-create>
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="material.parent.createWithChild">
                                <input type="hidden" name="child_material_id" value="<?= $selfId ?>">
                                <label>父项名称<input name="material_name" maxlength="255" value="<?= $escape($material['material_name']) ?>" required autocomplete="off"></label>
                                <label>父项单位<input name="unit" maxlength="50" list="material-unit-options" value="<?= $escape($material['unit']) ?>" required data-assembly-unit autocapitalize="off" spellcheck="false"></label>
                                <label>分类
                                    <select name="category_id">
                                        <option value="">未分类</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) ($material['category_id'] ?? 0) ? 'selected' : '' ?>><?= $escape($category['category_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>材料编码 <span class="entry-field-hint">建议 · 可自定义或留空</span>
                                    <input name="material_code" maxlength="50" value="<?= $escape($assemblyCode) ?>" data-assembly-code autocapitalize="off" spellcheck="false">
                                </label>
                                <label>数量系数<input type="number" name="quantity" min="0.01" step="0.01" value="1.00" required inputmode="decimal" data-assembly-quantity></label>
                                <p class="muted field-wide" data-assembly-quantity-hint>数量系数 = 每 1 个父项单位需要多少本子件；父子单位不同时请按实际用量填写（如 每 1 套 = 3.90 m²）。</p>
                                <p class="muted field-wide">建议把父项名称改成整合品名（如「12厚超白钢化玻璃双开门」），避免父子同名；编码默认「CP-」+ 本子件编码，可自行修改。保存后本材料自动成为第一条子件，父项默认转为「无需送样」。</p>
                                <div class="field-wide dialog-actions">
                                    <button class="button button-primary" type="submit">新建父项并纳入</button>
                                </div>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </dialog>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>
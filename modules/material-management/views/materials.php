<?php
ob_start();
require __DIR__ . '/_nav.php';
?>
<section class="page-heading report-intro">
    <div>
        <p class="eyebrow">材料主数据 · 全局目录</p>
        <h1 class="report-heading">材料目录</h1>
    </div>
    <?php if ($canEdit): ?>
        <button class="button button-quiet" type="button" data-disclosure-open="new-material">新增材料</button>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>材料清单</h2>
        <span class="muted"><?= count($data['materials']) ?> 项</span>
    </div>
    <div class="toolbar" role="search" aria-label="筛选材料">
        <label>
            搜索目录
            <input id="material-search" type="search" placeholder="名称、编码或规格" autocomplete="off">
        </label>
        <label>
            材料分类
            <select id="material-category">
                <option value="">全部分类</option>
                <?php foreach ($data['categories'] as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <span class="toolbar-count" id="material-count" data-unit=" 项材料" aria-live="polite"><?= count($data['materials']) ?> 项材料</span>
    </div>

    <?php if ($data['materials'] === []): ?>
        <p class="report-empty">目录尚无材料。可从页面右上方新增第一项。</p>
    <?php else: ?>
        <div class="table-wrap">
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
                <tbody>
                <?php foreach ($data['materials'] as $material): ?>
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
                                    <form method="post" action="">
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
            <p class="report-empty" id="materials-no-match" hidden>没有符合条件的材料。</p>
        </div>
    <?php endif; ?>
</section>

<?php if ($canEdit): ?>
    <details class="disclosure" id="new-material">
        <summary>新增材料<span class="muted">登记主数据后，可在报价、样品和估算中引用</span></summary>
        <div class="disclosure-content">
            <form method="post" action="" class="form-grid record-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.save">
                <label>材料编码<input name="material_code" maxlength="50"></label>
                <label>材料名称<input name="material_name" maxlength="255" required></label>
                <label>计量单位<input name="unit" maxlength="50" required></label>
                <label>分类
                    <select name="category_id">
                        <option value="">无</option>
                        <?php foreach ($data['categories'] as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">规格<textarea name="specifications" rows="2" maxlength="20000"></textarea></label>
                <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="2" maxlength="20000"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存材料</button></div>
            </form>
        </div>
    </details>
<?php endif; ?>

<?php if ($data['categories'] !== [] || $canEdit): ?>
    <details class="disclosure category-disclosure">
        <summary>管理材料分类<span class="muted"><?= count($data['categories']) ?> 个分类</span></summary>
        <div class="disclosure-content">
            <?php if ($canEdit): ?>
                <form method="post" action="" class="inline-editor">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="material.category.save">
                    <label>新增分类<input name="category_name" maxlength="255" required></label>
                    <label>上级分类
                        <select name="parent_category_id">
                            <option value="">无</option>
                            <?php foreach ($data['categories'] as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>"><?= $escape($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>排序值<input type="number" name="sort_order" min="0" max="1000000" value="0"></label>
                    <button class="button button-primary" type="submit">新增分类</button>
                </form>
            <?php endif; ?>

            <?php if ($data['categories'] !== []): ?>
                <div class="table-wrap">
                    <table class="category-table">
                        <thead>
                        <tr><th scope="col">分类名称</th><th scope="col">上级分类</th><th scope="col">排序</th><th scope="col"><span class="sr-only">操作</span></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['categories'] as $category): ?>
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
                                                            <?php foreach ($data['categories'] as $parent): if ((int) $parent['category_id'] === (int) $category['category_id']) { continue; } ?>
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
                                            <form method="post" action="">
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
    <?php foreach ($data['materials'] as $material): ?>
        <dialog class="edit-dialog" id="material-edit-<?= (int) $material['material_id'] ?>" aria-labelledby="material-edit-title-<?= (int) $material['material_id'] ?>">
            <div class="dialog-heading">
                <div>
                    <p class="eyebrow">材料主数据</p>
                    <h2 id="material-edit-title-<?= (int) $material['material_id'] ?>">编辑材料</h2>
                </div>
                <button class="button button-quiet" type="button" data-dialog-close aria-label="关闭">关闭</button>
            </div>
            <form method="post" action="" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="material.save">
                <input type="hidden" name="material_id" value="<?= (int) $material['material_id'] ?>">
                <label>材料编码<input name="material_code" maxlength="50" value="<?= $escape($material['material_code'] ?? '') ?>"></label>
                <label>材料名称<input name="material_name" maxlength="255" value="<?= $escape($material['material_name']) ?>" required></label>
                <label>计量单位<input name="unit" maxlength="50" value="<?= $escape($material['unit']) ?>" required></label>
                <label>分类
                    <select name="category_id">
                        <option value="">无</option>
                        <?php foreach ($data['categories'] as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $material['category_id'] ? 'selected' : '' ?>><?= $escape($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-wide">规格<textarea name="specifications" rows="3" maxlength="20000"><?= $escape($material['specifications'] ?? '') ?></textarea></label>
                <label class="field-wide">材料说明<textarea name="material_description_detailed" rows="3" maxlength="20000"><?= $escape($material['material_description_detailed'] ?? '') ?></textarea></label>
                <div class="field-wide dialog-actions"><button class="button button-primary" type="submit">保存更改</button></div>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

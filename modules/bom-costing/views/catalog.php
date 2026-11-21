<?php
ob_start();
require __DIR__ . '/_nav.php';

$typeLabels = ['processing' => '加工', 'mechanical' => '机械', 'labor' => '人工'];
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">共享资源目录</p>
        <h1>资源目录</h1>
        <p class="muted">加工 / 机械 / 人工工种与台班跨项目通用；目录不带价格，项目各自在成本价中定价。</p>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>目录项</h2><span class="muted"><?= count($catalog) ?> 项</span></div>
    <?php if ($catalog === []): ?>
        <p class="empty-state">暂无目录项。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>类型</th><th>名称</th><th>单位</th><th>说明</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($catalog as $item): ?>
                    <tr>
                        <td><span class="badge"><?= $escape($typeLabels[$item['catalog_type']] ?? $item['catalog_type']) ?></span></td>
                        <td><?= $escape($item['name']) ?></td>
                        <td><?= $escape($item['unit']) ?></td>
                        <td><?= $escape($item['description'] ?? '—') ?></td>
                        <td>
                            <?php if ($canEdit): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="cost.catalog.delete">
                                <input type="hidden" name="catalog_id" value="<?= (int) $item['catalog_id'] ?>">
                                <button class="button button-quiet" type="submit">删除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
        <details class="entry-disclosure">
            <summary>新增目录项</summary>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="cost.catalog.save">
                <label>类型
                    <select name="catalog_type" required>
                        <option value="processing">加工</option>
                        <option value="mechanical">机械</option>
                        <option value="labor">人工</option>
                    </select>
                </label>
                <label>名称<input type="text" name="name" maxlength="255" required></label>
                <label>单位<input type="text" name="unit" maxlength="50" required></label>
                <label class="field-wide">说明<textarea name="description" rows="2"></textarea></label>
                <div class="field-wide"><button class="button button-primary" type="submit">保存目录项</button></div>
            </form>
        </details>
    <?php endif; ?>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

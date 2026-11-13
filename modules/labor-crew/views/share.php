<?php
/**
 * Public crew contact page (token-authorized, no login).
 *
 * Receives only export-equivalent columns: type_name, crew_name, leader_name,
 * leader_phone, notes. Identity numbers, WeChat ids and emergency contacts are
 * never queried for this view. Phone links use tel: so a tap dials directly.
 */
$shareCrews = $crews ?? [];
$typeCounts = [];
foreach ($shareCrews as $shareCrew) {
    $shareTypeName = (string) $shareCrew['type_name'];
    $typeCounts[$shareTypeName] = ($typeCounts[$shareTypeName] ?? 0) + 1;
}
ob_start();
?>
<div class="share-page">
    <header class="share-hero">
        <p class="share-eyebrow">施工班组 · 公开联系页</p>
        <h1><?= $escape($project['project_name']) ?>班组联系人</h1>
        <p class="share-sub">共 <?= count($shareCrews) ?> 个在用班组<?php if ($typeCounts !== []): ?> · 涉及 <?= count($typeCounts) ?> 个工种<?php endif; ?>。点击电话即可直接拨号。</p>
        <?php if ($typeCounts !== []): ?>
            <ul class="share-chips" aria-label="工种统计">
                <?php foreach ($typeCounts as $shareTypeName => $shareTypeCount): ?>
                    <li><?= $escape($shareTypeName) ?> <strong><?= (int) $shareTypeCount ?></strong></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </header>

    <?php if ($shareCrews === []): ?>
        <section class="share-empty">
            <p>当前项目暂无在用班组。</p>
            <p class="muted">请联系项目管理员登记班组后再查看。</p>
        </section>
    <?php else: ?>
        <div class="share-toolbar">
            <label class="share-search">
                <span class="share-search-label">查找</span>
                <input type="search" id="share-search" placeholder="班组、负责人、电话或备注" autocomplete="off" aria-label="搜索班组联系人">
            </label>
            <span class="share-count" id="share-count" data-unit=" 个班组" aria-live="polite"><?= count($shareCrews) ?> 个班组</span>
        </div>
        <p class="share-no-match" id="share-no-match" hidden>没有符合搜索条件的班组。</p>
        <ul class="share-list" id="share-list">
            <?php foreach ($shareCrews as $shareCrew): ?>
                <?php
                $sharePhone = (string) $shareCrew['leader_phone'];
                // Stored phones are normalised to digits with an optional +,
                // so the tel: target is safe; strip anything else defensively.
                $shareTel = preg_replace('/[^0-9+]/', '', $sharePhone);
                $shareHaystack = mb_strtolower(
                    $shareCrew['crew_name'] . ' ' . $shareCrew['leader_name'] . ' ' . $sharePhone
                    . ' ' . $shareCrew['type_name'] . ' ' . ($shareCrew['notes'] ?? ''),
                    'UTF-8'
                );
                ?>
                <li class="share-card" data-search="<?= $escape($shareHaystack) ?>">
                    <div class="share-card-main">
                        <div class="share-card-heading">
                            <h2><?= $escape($shareCrew['crew_name']) ?></h2>
                            <span class="share-type"><?= $escape($shareCrew['type_name']) ?></span>
                        </div>
                        <p class="share-leader">负责人 · <?= $escape($shareCrew['leader_name']) ?></p>
                        <?php if (($shareCrew['notes'] ?? '') !== ''): ?>
                            <p class="share-notes"><?= $escape($shareCrew['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a class="share-call" href="tel:<?= $escape($shareTel) ?>" aria-label="拨打 <?= $escape($shareCrew['leader_name']) ?> 的电话 <?= $escape($sharePhone) ?>">
                        <svg class="share-call-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.85 21 3 13.15 3 3.5a1 1 0 0 1 1-1H7.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z"/></svg>
                        <span class="share-call-text">
                            <span class="share-call-number"><?= $escape($sharePhone) ?></span>
                            <span class="share-call-hint">点击拨号</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <footer class="share-footer">
        <p>本页为公开分享视图，仅展示在用班组的联系方式；数据更新后刷新即可看到最新内容。</p>
        <p class="muted">页面生成时间：<?= $escape($generatedAt ?? '') ?>（<?= $escape(\ProjectO\Support::displayTimezone()->getName()) ?>）</p>
    </footer>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 3) . '/views/layout.php';

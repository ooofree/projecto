<?php
/**
 * Standalone static public contact page (tile layout).
 *
 * Rendered by CrewSharePage into storage/labor-crew-shares/<token>.html and
 * streamed as-is to anonymous visitors. Everything is inline: no external
 * CSS/JS/fonts/images, so the page is a single small request. Receives only
 * export-equivalent columns (type_name, crew_name, leader_name, leader_phone,
 * notes); identity numbers, WeChat ids and emergency contacts never appear.
 *
 * Each tile is one <a href="tel:"> — tapping anywhere on it dials. On small
 * screens the number text is hidden and a phone icon marks the dial action.
 */
$crews = $crews ?? [];
$generatedAt = $generatedAt ?? '';
$timezone = $timezone ?? 'Asia/Shanghai';
$typeCounts = [];
foreach ($crews as $crew) {
    $typeName = (string) $crew['type_name'];
    $typeCounts[$typeName] = ($typeCounts[$typeName] ?? 0) + 1;
}
$phoneIcon = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.85 21 3 13.15 3 3.5a1 1 0 0 1 1-1H7.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z"/></svg>';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1d4734">
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23286247'/%3E%3Cpath fill='%23fff' d='M8.6 11.8a10.5 10.5 0 0 0 4.6 4.6l1.5-1.5a.7.7 0 0 1 .7-.2c.8.3 1.6.4 2.5.4a.7.7 0 0 1 .7.7v2.4a.7.7 0 0 1-.7.7C11.6 18.9 5.1 12.4 5.1 4.5a.7.7 0 0 1 .7-.7h2.4a.7.7 0 0 1 .7.7c0 .9.1 1.7.4 2.5a.7.7 0 0 1-.2.7l-1.5 1.5Z'/%3E%3C/svg%3E">
<title><?= $escape($project['project_name']) ?>班组联系人</title>
<style>
:root{color-scheme:light;--page:#f4f6f4;--surface:#fff;--muted:#6b7770;--text:#202b25;--border:#e3e9e4;--accent:#286247;--accent-soft:#e9f2ec}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{background:var(--page)}
body{margin:0;min-height:100vh;color:var(--text);font-family:system-ui,-apple-system,"PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;line-height:1.55;background:radial-gradient(ellipse at 50% -18%,#e8f0ea 0,var(--page) 54rem)}
.wrap{width:min(960px,100%);margin:0 auto;padding:16px 14px calc(28px + env(safe-area-inset-bottom))}
.hero{position:relative;overflow:hidden;padding:22px 20px 18px;color:#f5faf6;background:linear-gradient(118deg,#1d4734 0%,#286247 58%,#357553 100%);border-radius:18px;box-shadow:0 14px 32px rgb(34 73 49 / 17%)}
.hero::after{position:absolute;top:-110px;right:4%;width:240px;height:240px;border:1px solid rgb(255 255 255 / 9%);border-radius:50%;box-shadow:0 0 0 28px rgb(255 255 255 / 3%),0 0 0 56px rgb(255 255 255 / 2%);content:"";pointer-events:none}
.hero .eyebrow{position:relative;z-index:1;margin:0 0 6px;color:#bad1c1;font-size:.7rem;font-weight:650;letter-spacing:.1em}
.hero h1{position:relative;z-index:1;margin:0 0 6px;color:#fff;font-size:clamp(1.35rem,4.5vw,1.85rem);line-height:1.25;letter-spacing:-.02em;overflow-wrap:anywhere}
.hero .sub{position:relative;z-index:1;margin:0;color:#d9e8dd;font-size:.86rem}
.tools{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:16px 0 10px}
.search{display:flex;flex:1 1 220px;align-items:center;gap:8px;min-height:44px;padding:0 13px;background:var(--surface);border:1px solid var(--border);border-radius:12px}
.search:focus-within{border-color:var(--accent)}
.search svg{flex:0 0 auto;color:var(--muted)}
.search input{flex:1;min-width:0;min-height:42px;padding:0;border:0;outline:none;background:transparent;font:inherit}
.count{color:var(--muted);font-size:.82rem;white-space:nowrap}
.chips{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 14px;padding:0;list-style:none}
.chip{padding:6px 13px;color:var(--muted);background:var(--surface);border:1px solid var(--border);border-radius:999px;font-size:.8rem;font-weight:620;cursor:pointer;transition:background-color .14s ease,color .14s ease,border-color .14s ease}
.chip strong{margin-left:3px;font-weight:720}
.chip[aria-pressed="true"]{color:#fff;background:var(--accent);border-color:var(--accent)}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:12px;padding:0;margin:0;list-style:none}
.tile{position:relative;display:flex;flex-direction:column;min-height:136px;padding:15px 16px 46px;color:inherit;background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:0 6px 18px rgb(34 54 43 / 5%);text-decoration:none;overflow:hidden;transition:transform .14s ease,box-shadow .14s ease,border-color .14s ease}
.tile:hover{transform:translateY(-2px);border-color:#cfdcd3;box-shadow:0 10px 24px rgb(34 54 43 / 10%)}
.tile:active{transform:scale(.985)}
.tile:focus-visible{outline:3px solid var(--accent);outline-offset:2px}
.tiles li[hidden]{display:none}
.tile-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
.tile-type{padding:2px 10px;color:var(--accent);background:var(--accent-soft);border-radius:999px;font-size:.74rem;font-weight:640;white-space:nowrap}
.tile h2{margin:0;font-size:1.06rem;line-height:1.35;overflow-wrap:anywhere}
.tile-leader{margin:6px 0 0;color:var(--muted);font-size:.88rem}
.tile-notes{margin:6px 0 0;color:var(--muted);font-size:.8rem;line-height:1.5;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.tile-call{position:absolute;right:12px;bottom:12px;display:inline-flex;align-items:center;gap:8px;padding:7px 13px;color:#fff;background:linear-gradient(145deg,#347855,#1d5038);border-radius:999px;box-shadow:0 5px 14px rgb(40 98 71 / 24%)}
.tile-call svg{flex:0 0 auto}
.tile-num{font-size:.9rem;font-weight:720;letter-spacing:.02em;font-variant-numeric:tabular-nums}
.tile-hint{display:none}
.no-match,.empty{padding:26px 16px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:14px;color:var(--muted)}
.empty p{margin:0 0 6px}
footer{margin:26px 0 4px;color:var(--muted);font-size:.76rem;text-align:center}
footer p{margin:0 0 4px}
@media (max-width:640px){
.wrap{padding:12px 10px calc(24px + env(safe-area-inset-bottom))}
.hero{padding:18px 16px 15px;border-radius:15px}
.tiles{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.tile{min-height:122px;padding:12px 12px 44px;border-radius:14px}
.tile h2{font-size:.95rem}
.tile-leader{font-size:.8rem}
.tile-notes{-webkit-line-clamp:2;font-size:.74rem}
.tile-num{display:none}
.tile-call{right:9px;bottom:9px;gap:6px;padding:8px 11px}
.tile-call svg{width:18px;height:18px}
.tile-hint{display:inline;font-size:.7rem;font-weight:640;color:#cfe3d4}
}
@media (max-width:350px){.tiles{grid-template-columns:1fr}}
@media (prefers-color-scheme:dark){
:root{color-scheme:dark;--page:#202321;--surface:#2a2e2b;--muted:#a8b3ac;--text:#e6ebe7;--border:#3a423c;--accent:#5aa87f;--accent-soft:#2b3a31}
body{background:radial-gradient(ellipse at 50% -18%,#2d3831 0,var(--page) 54rem)}
.tile:hover{border-color:#4a544d}
.chip[aria-pressed="true"]{color:#fff;background:#286247;border-color:#286247}
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <p class="eyebrow">施工班组 · 公开联系页</p>
        <h1><?= $escape($project['project_name']) ?>班组联系人</h1>
        <p class="sub">共 <?= count($crews) ?> 个在用班组<?php if ($typeCounts !== []): ?> · <?= count($typeCounts) ?> 个工种<?php endif; ?>。点击卡片即可直接拨号。</p>
    </header>

    <?php if ($crews === []): ?>
        <section class="empty" style="margin-top:16px">
            <p>当前项目暂无在用班组。</p>
            <p>请联系项目管理员登记班组后再查看。</p>
        </section>
    <?php else: ?>
        <div class="tools">
            <label class="search">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 2a8 8 0 1 0 4.9 14.3l5.4 5.4 1.4-1.4-5.4-5.4A8 8 0 0 0 10 2Zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z"/></svg>
                <input type="search" id="share-search" placeholder="搜索班组、负责人或备注" autocomplete="off" aria-label="搜索班组联系人">
            </label>
            <span class="count" id="share-count" data-unit=" 个班组" aria-live="polite"><?= count($crews) ?> 个班组</span>
        </div>
        <?php if (count($typeCounts) > 1): ?>
            <ul class="chips" id="share-chips" aria-label="按工种筛选">
                <li><button type="button" class="chip" data-type="" aria-pressed="true">全部 <strong><?= count($crews) ?></strong></button></li>
                <?php foreach ($typeCounts as $typeName => $typeCount): ?>
                    <li><button type="button" class="chip" data-type="<?= $escape($typeName) ?>" aria-pressed="false"><?= $escape($typeName) ?> <strong><?= (int) $typeCount ?></strong></button></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="no-match" id="share-no-match" hidden>没有符合搜索条件的班组。</p>
        <ul class="tiles" id="share-list">
            <?php foreach ($crews as $crew): ?>
                <?php
                $phone = (string) $crew['leader_phone'];
                // Stored phones are normalised to digits with an optional +;
                // strip anything else defensively before building the tel: URI.
                $tel = preg_replace('/[^0-9+]/', '', $phone);
                $haystack = mb_strtolower(
                    $crew['crew_name'] . ' ' . $crew['leader_name'] . ' ' . $phone
                    . ' ' . $crew['type_name'] . ' ' . ($crew['notes'] ?? ''),
                    'UTF-8'
                );
                ?>
                <li>
                    <a class="tile" href="tel:<?= $escape($tel) ?>" data-search="<?= $escape($haystack) ?>" data-type="<?= $escape($crew['type_name']) ?>" aria-label="拨打 <?= $escape($crew['crew_name']) ?> 负责人 <?= $escape($crew['leader_name']) ?> 的电话 <?= $escape($phone) ?>">
                        <span class="tile-head"><span class="tile-type"><?= $escape($crew['type_name']) ?></span></span>
                        <h2><?= $escape($crew['crew_name']) ?></h2>
                        <p class="tile-leader">负责人 · <?= $escape($crew['leader_name']) ?></p>
                        <?php if (($crew['notes'] ?? '') !== ''): ?>
                            <p class="tile-notes"><?= $escape($crew['notes']) ?></p>
                        <?php endif; ?>
                        <span class="tile-call"><?= $phoneIcon ?><span class="tile-num"><?= $escape($phone) ?></span><span class="tile-hint">拨号</span></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <footer>
        <p>本页为公开分享视图，仅展示在用班组的联系方式；信息更新后本页自动同步。</p>
        <p>页面生成时间：<?= $escape($generatedAt) ?>（<?= $escape($timezone) ?>）</p>
    </footer>
</div>
<script>
(function () {
    var search = document.getElementById('share-search');
    var tiles = [].slice.call(document.querySelectorAll('#share-list .tile'));
    var count = document.getElementById('share-count');
    var noMatch = document.getElementById('share-no-match');
    var chips = [].slice.call(document.querySelectorAll('#share-chips .chip'));
    var activeType = '';
    function apply() {
        var q = (search && search.value || '').trim().toLocaleLowerCase();
        var visible = 0;
        for (var i = 0; i < tiles.length; i++) {
            var tile = tiles[i];
            var matches = (tile.dataset.search || '').indexOf(q) !== -1
                && (activeType === '' || tile.dataset.type === activeType);
            tile.parentNode.hidden = !matches;
            if (matches) visible++;
        }
        if (count) count.textContent = visible + (count.dataset.unit || ' 个班组');
        if (noMatch) noMatch.hidden = visible !== 0;
    }
    if (search) search.addEventListener('input', apply);
    for (var j = 0; j < chips.length; j++) {
        chips[j].addEventListener('click', function () {
            activeType = this.dataset.type || '';
            for (var k = 0; k < chips.length; k++) {
                chips[k].setAttribute('aria-pressed', chips[k] === this ? 'true' : 'false');
            }
            apply();
        });
    }
})();
</script>
</body>
</html>

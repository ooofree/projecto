<?php
/**
 * Standalone static public contact page (continuous tile grid).
 *
 * Rendered by CrewSharePage into storage/labor-crew-shares/<token>.html and
 * streamed as-is to anonymous visitors. Everything is inline: no external
 * CSS/JS/fonts/images, so the page is a single small request. Receives only
 * export-equivalent columns (type_name, crew_name, leader_name, leader_phone,
 * notes); identity numbers, WeChat ids and emergency contacts never appear.
 *
 * All crews flow through ONE continuous grid — no per-trade sections, so a
 * trade with a single crew never leaves an empty slot; the next crews move up.
 * Tiles are ordered trade by trade (first-appearance order, directory order
 * inside a trade) and each tile carries its own coloured trade label. The
 * sticky trade chips are anchor links that jump to the first tile of a trade
 * and flash-highlight it. Each tile is one <a href="tel:"> — tapping anywhere
 * on it dials. Every tile has the same fixed size (long text is truncated).
 * Phones always show two columns; three start at tablet width (768px), four
 * at 1100px. Small screens hide the number and show a phone icon + dial hint.
 */
$crews = $crews ?? [];
$generatedAt = $generatedAt ?? '';
$timezone = $timezone ?? 'Asia/Shanghai';

// Group by craft type, keeping first-appearance order (which follows the
// directory's manual sort) and the crew order inside each group. The groups
// are only used for ordering, chip counts and anchor targets — the rendered
// grid itself is continuous.
$groups = [];
foreach ($crews as $crew) {
    $typeName = (string) $crew['type_name'];
    if (!isset($groups[$typeName])) {
        $groups[$typeName] = [];
    }
    $groups[$typeName][] = $crew;
}
// Construction-site palette: safety amber, blueprint blue, steel teal, brick
// red, site violet, moss green, signal magenta, crane cyan. A trade keeps one
// colour across its chip and its tiles' label/top border.
$palette = ['#d97706', '#2563eb', '#0d9488', '#dc2626', '#7c3aed', '#65a30d', '#db2777', '#0891b2'];
$groupColors = [];
$gi = 0;
foreach (array_keys($groups) as $typeName) {
    $groupColors[$typeName] = $palette[$gi % count($palette)];
    $gi++;
}
$phoneIcon = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.85 21 3 13.15 3 3.5a1 1 0 0 1 1-1H7.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z"/></svg>';
$tileIndex = 0;
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#123527">
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23286247'/%3E%3Cpath fill='%23fff' d='M8.6 11.8a10.5 10.5 0 0 0 4.6 4.6l1.5-1.5a.7.7 0 0 1 .7-.2c.8.3 1.6.4 2.5.4a.7.7 0 0 1 .7.7v2.4a.7.7 0 0 1-.7.7C11.6 18.9 5.1 12.4 5.1 4.5a.7.7 0 0 1 .7-.7h2.4a.7.7 0 0 1 .7.7c0 .9.1 1.7.4 2.5a.7.7 0 0 1-.2.7l-1.5 1.5Z'/%3E%3C/svg%3E">
<title><?= $escape($project['project_name']) ?>班组联系人</title>
<style>
:root{color-scheme:light;--page:#f4f6f4;--surface:#fff;--muted:#64716a;--text:#1f2a24;--border:#e2e8e3;--accent:#286247;--accent-soft:#e9f2ec;--nav-bg:rgba(244,246,244,.92)}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{background:var(--page);scroll-behavior:smooth;scrollbar-color:#286247 #e2e8e3;scrollbar-width:thin}
body{margin:0;min-height:100vh;color:var(--text);font-family:system-ui,-apple-system,"PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;line-height:1.55;background:radial-gradient(ellipse at 50% -18%,#e6f0e9 0,var(--page) 54rem)}
::-webkit-scrollbar{width:10px;height:5px}
::-webkit-scrollbar-track{background:#e2e8e3}
::-webkit-scrollbar-thumb{background:#286247;border-radius:6px;border:2px solid #e2e8e3}
::-webkit-scrollbar-thumb:hover{background:#347855}
.wrap{width:min(1080px,100%);margin:0 auto;padding:16px 14px calc(30px + env(safe-area-inset-bottom))}
.hero{position:relative;overflow:hidden;padding:26px 22px 22px;color:#f2f8f3;background:linear-gradient(135deg,#123527 0%,#1d5038 45%,#2b6a4b 78%,#1f5d52 100%);border-radius:20px;box-shadow:0 18px 40px rgb(18 53 39 / 25%);scroll-margin-top:70px}
.hero::before{position:absolute;inset:0;background-image:linear-gradient(rgb(255 255 255 / 5%) 1px,transparent 1px),linear-gradient(90deg,rgb(255 255 255 / 5%) 1px,transparent 1px);background-size:26px 26px;content:"";pointer-events:none}
.hero::after{position:absolute;top:-110px;right:4%;width:240px;height:240px;border:1px solid rgb(255 255 255 / 10%);border-radius:50%;box-shadow:0 0 0 28px rgb(255 255 255 / 4%),0 0 0 56px rgb(255 255 255 / 3%);content:"";pointer-events:none}
.hero .eyebrow{position:relative;z-index:1;margin:0 0 6px;color:#a9cbb6;font-size:.7rem;font-weight:650;letter-spacing:.12em}
.hero h1{position:relative;z-index:1;margin:0 0 8px;color:#fff;font-size:clamp(1.35rem,4.5vw,1.9rem);line-height:1.25;letter-spacing:-.02em;overflow-wrap:anywhere}
.hero .sub{position:relative;z-index:1;margin:0;color:#d7e7db;font-size:.86rem}
.hero .sub strong{color:#fff}
.trade-nav{position:sticky;top:0;z-index:20;margin:14px -14px 14px;padding:9px 14px;background:var(--nav-bg);border-bottom:1px solid var(--border);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.chips{display:flex;gap:8px;overflow-x:auto;padding:2px;list-style:none;margin:0;scrollbar-width:thin;scrollbar-color:#286247 transparent}
.chips::-webkit-scrollbar{height:4px}
.chips::-webkit-scrollbar-track{background:transparent}
.chips::-webkit-scrollbar-thumb{background:#9db8a8;border:0}
.chip{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:7px 13px;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:999px;font-size:.8rem;font-weight:640;text-decoration:none;white-space:nowrap;transition:border-color .14s ease,transform .14s ease,box-shadow .14s ease}
.chip:hover{border-color:var(--tc,#286247);transform:translateY(-1px);box-shadow:0 4px 10px rgb(34 54 43 / 8%)}
.chip:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.chip .dot{width:9px;height:9px;border-radius:50%;background:var(--tc,#286247)}
.chip strong{color:var(--muted);font-weight:700}
.tools{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 14px}
.search{display:flex;flex:1 1 220px;align-items:center;gap:8px;min-height:44px;padding:0 13px;background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:border-color .14s ease}
.search:focus-within{border-color:var(--accent)}
.search svg{flex:0 0 auto;color:var(--muted)}
.search input{flex:1;min-width:0;min-height:42px;padding:0;border:0;outline:none;background:transparent;font:inherit}
.count{color:var(--muted);font-size:.82rem;white-space:nowrap}
.tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:0;margin:0;list-style:none}
.tiles li{min-width:0}
.tiles li[hidden]{display:none}
.tiles li[id]{scroll-margin-top:76px}
.tile{position:relative;display:flex;flex-direction:column;height:150px;padding:12px 12px 46px;color:inherit;background:var(--surface);border:1px solid var(--border);border-top:3px solid var(--tc);border-radius:14px;box-shadow:0 5px 16px rgb(34 54 43 / 5%);text-decoration:none;overflow:hidden;transition:transform .14s ease,box-shadow .14s ease,border-color .14s ease;animation:rise .45s both;animation-delay:var(--d,0ms)}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.tile:hover{transform:translateY(-2px);border-color:var(--tc);border-top-color:var(--tc);box-shadow:0 10px 24px rgb(34 54 43 / 12%)}
.tile:active{transform:scale(.985)}
.tile:focus-visible{outline:3px solid var(--accent);outline-offset:2px}
.tiles li:target .tile{animation:tile-target 1.6s ease}
@keyframes tile-target{0%,55%{border-color:var(--tc);box-shadow:0 0 0 3px var(--tc-soft),0 10px 24px rgb(34 54 43 / 16%)}100%{border-color:var(--border);border-top-color:var(--tc);box-shadow:0 5px 16px rgb(34 54 43 / 5%)}}
.tile-type{align-self:flex-start;max-width:100%;padding:3px 10px;color:var(--tc);background:var(--tc-soft);border-radius:999px;font-size:.72rem;font-weight:680;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tile h3{margin:8px 0 0;font-size:1rem;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tile-leader{margin:5px 0 0;color:var(--muted);font-size:.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tile-notes{margin:5px 0 0;color:var(--muted);font-size:.78rem;line-height:1.5;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.tile-call{position:absolute;right:10px;bottom:10px;display:inline-flex;align-items:center;gap:7px;padding:7px 12px;color:#fff;background:linear-gradient(145deg,#347855,#1d5038);border-radius:999px;box-shadow:0 5px 14px rgb(40 98 71 / 25%)}
.tile-call svg{flex:0 0 auto;width:18px;height:18px}
.tile-num{font-size:.86rem;font-weight:720;letter-spacing:.02em;font-variant-numeric:tabular-nums}
.tile-hint{display:none}
.no-match,.empty{padding:26px 16px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:14px;color:var(--muted)}
.empty{margin-top:16px}
.empty p{margin:0 0 6px}
footer{margin:28px 0 4px;color:var(--muted);font-size:.76rem;text-align:center}
footer p{margin:0 0 4px}
@media (max-width:640px){
.wrap{padding:12px 10px calc(24px + env(safe-area-inset-bottom))}
.hero{padding:20px 17px 17px;border-radius:16px}
.trade-nav{margin:12px -10px 12px;padding:8px 10px}
.tiles li[id]{scroll-margin-top:64px}
.tiles{gap:9px}
.tile{height:150px;padding:11px 11px 44px;border-radius:13px}
.tile h3{font-size:.93rem}
.tile-leader{font-size:.79rem}
.tile-notes{font-size:.73rem}
.tile-num{display:none}
.tile-hint{display:inline;font-size:.68rem;font-weight:640;color:#cfe3d4}
.tile-call{right:9px;bottom:9px;gap:5px;padding:7px 10px}
}
@media (min-width:768px){
.tiles{grid-template-columns:repeat(3,minmax(0,1fr))}
.tile{height:180px;padding:14px 14px 50px}
.tile h3{font-size:1.02rem}
.tile-notes{-webkit-line-clamp:2}
}
@media (min-width:1100px){.tiles{grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}}
@media (prefers-reduced-motion:reduce){
html{scroll-behavior:auto}
.tile{animation:none}
.tiles li:target .tile{animation:none;border-color:var(--tc);box-shadow:0 0 0 3px var(--tc-soft)}
.tile,.chip{transition:none}
}
@media (prefers-color-scheme:dark){
:root{color-scheme:dark;--page:#1f2320;--surface:#2a2f2b;--muted:#a3b0a7;--text:#e7ece8;--border:#3a423c;--accent:#5aa87f;--accent-soft:#2b3a31;--nav-bg:rgba(31,35,32,.92)}
html{scrollbar-color:#5aa87f #2a2f2b}
body{background:radial-gradient(ellipse at 50% -18%,#2b3830 0,var(--page) 54rem)}
::-webkit-scrollbar-track{background:#2a2f2b}
::-webkit-scrollbar-thumb{background:#5aa87f;border-color:#2a2f2b}
.tile:hover{border-color:var(--tc)}
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero" id="top">
        <p class="eyebrow">施工班组 · 公开联系页</p>
        <h1><?= $escape($project['project_name']) ?>班组联系人</h1>
        <p class="sub">共 <strong><?= count($crews) ?></strong> 个在用班组 · <strong><?= count($groups) ?></strong> 个工种。点击磁贴任意位置即可直接拨号。</p>
    </header>

    <?php if ($crews === []): ?>
        <section class="empty">
            <p>当前项目暂无在用班组。</p>
            <p>请联系项目管理员登记班组后再查看。</p>
        </section>
    <?php else: ?>
        <?php if (count($groups) > 1): ?>
            <nav class="trade-nav" aria-label="按工种导航">
                <ul class="chips">
                    <li><a class="chip" href="#top">全部 <strong><?= count($crews) ?></strong></a></li>
                    <?php $gi = 0; foreach ($groups as $typeName => $groupCrews): ?>
                        <li><a class="chip" href="#trade-<?= $gi ?>" style="--tc:<?= $groupColors[$typeName] ?>"><span class="dot" aria-hidden="true"></span><?= $escape($typeName) ?> <strong><?= count($groupCrews) ?></strong></a></li>
                    <?php $gi++; endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <div class="tools">
            <label class="search">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 2a8 8 0 1 0 4.9 14.3l5.4 5.4 1.4-1.4-5.4-5.4A8 8 0 0 0 10 2Zm0 2a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z"/></svg>
                <input type="search" id="share-search" placeholder="搜索班组、负责人或备注" autocomplete="off" aria-label="搜索班组联系人">
            </label>
            <span class="count" id="share-count" data-unit=" 个班组" aria-live="polite"><?= count($crews) ?> 个班组</span>
        </div>
        <p class="no-match" id="share-no-match" hidden>没有符合搜索条件的班组。</p>
        <ul class="tiles" id="share-list">
            <?php $gi = 0; foreach ($groups as $typeName => $groupCrews): ?>
                <?php foreach ($groupCrews as $ci => $crew): ?>
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
                    $delay = min($tileIndex * 35, 320);
                    $tileIndex++;
                    // The first tile of each trade is the chip's anchor target.
                    $anchor = $ci === 0 ? ' id="trade-' . $gi . '"' : '';
                    ?>
                    <li<?= $anchor ?> style="--tc:<?= $groupColors[$typeName] ?>;--tc-soft:<?= $groupColors[$typeName] ?>22">
                        <a class="tile" href="tel:<?= $escape($tel) ?>" data-search="<?= $escape($haystack) ?>" style="--d:<?= $delay ?>ms" aria-label="拨打 <?= $escape($crew['crew_name']) ?> 负责人 <?= $escape($crew['leader_name']) ?> 的电话 <?= $escape($phone) ?>">
                            <span class="tile-type"><?= $escape($crew['type_name']) ?></span>
                            <h3 title="<?= $escape($crew['crew_name']) ?>"><?= $escape($crew['crew_name']) ?></h3>
                            <p class="tile-leader">负责人 · <?= $escape($crew['leader_name']) ?></p>
                            <?php if (($crew['notes'] ?? '') !== ''): ?>
                                <p class="tile-notes" title="<?= $escape($crew['notes']) ?>"><?= $escape($crew['notes']) ?></p>
                            <?php endif; ?>
                            <span class="tile-call"><?= $phoneIcon ?><span class="tile-num"><?= $escape($phone) ?></span><span class="tile-hint">拨号</span></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php $gi++; endforeach; ?>
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
    if (!search) return;
    var items = [].slice.call(document.querySelectorAll('#share-list > li'));
    var count = document.getElementById('share-count');
    var noMatch = document.getElementById('share-no-match');
    function apply() {
        var q = (search.value || '').trim().toLocaleLowerCase();
        var visible = 0;
        for (var i = 0; i < items.length; i++) {
            var tile = items[i].querySelector('.tile');
            var matches = q === '' || ((tile && tile.dataset.search) || '').indexOf(q) !== -1;
            items[i].hidden = !matches;
            if (matches) visible++;
        }
        if (count) count.textContent = visible + (count.dataset.unit || ' 个班组');
        if (noMatch) noMatch.hidden = visible !== 0;
    }
    search.addEventListener('input', apply);
})();
</script>
</body>
</html>

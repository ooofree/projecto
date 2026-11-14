<?php
/**
 * Standalone static public contact page (continuous tile grid).
 *
 * Rendered by CrewSharePage into public/s/<token>.html and served as-is by the
 * web server to anonymous visitors. Everything is inline: no external
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
<html lang="zh-CN" data-layout="<?= $escape($layoutVersion ?? '') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#123527">
<meta name="format-detection" content="telephone=no">
<?php /* The file is served straight from disk, so the policy travels inside the
         document as well: this works even where mod_headers is disabled.
         Inline CSS/JS allowed, every network source denied. */ ?>
<meta http-equiv="Content-Security-Policy" content="<?= $escape($contentSecurityPolicy ?? "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; form-action 'none'; base-uri 'none'") ?>">
<meta name="referrer" content="same-origin">
<?php /* Revalidate so a data change is visible on the next visit. */ ?>
<meta http-equiv="Cache-Control" content="no-cache">
<?php
// Link-card metadata. WeChat/QQ and other chat clients build the preview card
// from these when the link is shared or opened in their in-app browser.
$cardTitle = (string) ($cardMeta['title'] ?? ($project['project_name'] . ' · 班组联系人'));
$cardDescription = (string) ($cardMeta['description'] ?? '');
$cardSiteName = (string) ($siteName ?? 'Project O');
?>
<title><?= $escape($cardTitle) ?></title>
<meta name="description" content="<?= $escape($cardDescription) ?>">
<meta itemprop="name" content="<?= $escape($cardTitle) ?>">
<meta itemprop="description" content="<?= $escape($cardDescription) ?>">
<?php if (($imageUrl ?? '') !== ''): ?>
<meta itemprop="image" content="<?= $escape($imageUrl) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $escape($cardSiteName) ?>">
<meta property="og:title" content="<?= $escape($cardTitle) ?>">
<meta property="og:description" content="<?= $escape($cardDescription) ?>">
<?php if (($shareUrl ?? '') !== ''): ?>
<meta property="og:url" content="<?= $escape($shareUrl) ?>">
<?php endif; ?>
<?php if (($imageUrl ?? '') !== ''): ?>
<meta property="og:image" content="<?= $escape($imageUrl) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="600">
<meta property="og:image:height" content="600">
<meta property="og:image:alt" content="<?= $escape($cardTitle) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= $escape($cardTitle) ?>">
<meta name="twitter:description" content="<?= $escape($cardDescription) ?>">
<?php if (($imageUrl ?? '') !== ''): ?>
<meta name="twitter:image" content="<?= $escape($imageUrl) ?>">
<?php endif; ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%23286247'/%3E%3Cpath fill='%23fff' d='M8.6 11.8a10.5 10.5 0 0 0 4.6 4.6l1.5-1.5a.7.7 0 0 1 .7-.2c.8.3 1.6.4 2.5.4a.7.7 0 0 1 .7.7v2.4a.7.7 0 0 1-.7.7C11.6 18.9 5.1 12.4 5.1 4.5a.7.7 0 0 1 .7-.7h2.4a.7.7 0 0 1 .7.7c0 .9.1 1.7.4 2.5a.7.7 0 0 1-.2.7l-1.5 1.5Z'/%3E%3C/svg%3E">
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
.hero{position:relative;overflow:hidden;padding:26px 22px 22px;color:#f2f8f3;background:linear-gradient(135deg,#123527 0%,#1d5038 45%,#2b6a4b 78%,#1f5d52 100%);border-radius:20px;box-shadow:0 18px 40px rgb(18 53 39 / 25%);scroll-margin-top:var(--nav-h,76px)}
.hero::before{position:absolute;inset:0;background-image:linear-gradient(rgb(255 255 255 / 5%) 1px,transparent 1px),linear-gradient(90deg,rgb(255 255 255 / 5%) 1px,transparent 1px);background-size:26px 26px;content:"";pointer-events:none}
.hero::after{position:absolute;top:-110px;right:4%;width:240px;height:240px;border:1px solid rgb(255 255 255 / 10%);border-radius:50%;box-shadow:0 0 0 28px rgb(255 255 255 / 4%),0 0 0 56px rgb(255 255 255 / 3%);content:"";pointer-events:none}
.hero .eyebrow{position:relative;z-index:1;margin:0 0 6px;color:#a9cbb6;font-size:.7rem;font-weight:650;letter-spacing:.12em}
.hero h1{position:relative;z-index:1;margin:0 0 8px;color:#fff;font-size:clamp(1.35rem,4.5vw,1.9rem);line-height:1.25;letter-spacing:-.02em;overflow-wrap:anywhere}
.hero .sub{position:relative;z-index:1;margin:0;color:#d7e7db;font-size:.86rem}
.hero .sub strong{color:#fff}
.trade-nav{position:sticky;top:0;z-index:20;margin:14px -14px 14px;padding:9px 14px;background:var(--nav-bg);border-bottom:1px solid var(--border);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.chips{display:flex;flex-wrap:wrap;gap:7px;padding:2px;list-style:none;margin:0}
.chips li{display:flex;min-width:0;max-width:100%}
.chips li[hidden]{display:none}
/* Expanded lists stay inside a capped, scrollable well so a long trade list can
   never take over a small screen; the toggle below it stays put. */
.chips.is-expanded{max-height:min(26vh,190px);overflow-y:auto;overscroll-behavior:contain;padding-right:6px;scrollbar-width:thin;scrollbar-color:var(--accent) transparent}
.chips.is-expanded::-webkit-scrollbar{width:6px}
.chips.is-expanded::-webkit-scrollbar-track{background:transparent}
.chips.is-expanded::-webkit-scrollbar-thumb{background:var(--accent);border:0;border-radius:999px}
.chips.is-expanded::-webkit-scrollbar-thumb:hover{background:#347855}

.chip{flex:0 1 auto;min-width:0;max-width:100%;display:inline-flex;align-items:center;gap:6px;padding:7px 13px;color:var(--text);background:var(--surface);border:1px solid var(--border);border-radius:999px;font-size:.8rem;font-weight:640;text-decoration:none;transition:border-color .14s ease,transform .14s ease,box-shadow .14s ease}
.chip .name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chip:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
/* Touch taps must not leave a sticky hover ring behind: only real pointers get
   the lift/hover treatment. */
@media (hover:hover) and (pointer:fine){
.chip:hover{border-color:var(--tc,#286247);transform:translateY(-1px);box-shadow:0 4px 10px rgb(34 54 43 / 8%)}
}
.chip .dot{flex:0 0 auto;width:9px;height:9px;border-radius:50%;background:var(--tc,#286247)}
.chip strong{flex:0 0 auto;color:var(--muted);font-weight:700}
.chip[aria-current="true"]{color:#fff;background:var(--tc,#286247);border-color:var(--tc,#286247);box-shadow:0 4px 12px rgb(34 54 43 / 18%)}
.chip[aria-current="true"] strong{color:rgb(255 255 255 / 82%)}
.chip[aria-current="true"] .dot{background:#fff}
.chip-toggle{color:var(--accent);background:var(--accent-soft);border-color:transparent;font-weight:700;cursor:pointer}
.chip-toggle:hover{border-color:var(--accent)}
.tools{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 14px}
.search{display:flex;flex:1 1 220px;align-items:center;gap:8px;min-height:44px;padding:0 13px;background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:border-color .14s ease}
.search:focus-within{border-color:var(--accent)}
.search svg{flex:0 0 auto;color:var(--muted)}
.search input{flex:1;min-width:0;min-height:42px;padding:0;border:0;outline:none;background:transparent;font:inherit}
.count{color:var(--muted);font-size:.82rem;white-space:nowrap}
.tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:0;margin:0;list-style:none}
.tiles li{min-width:0}
.tiles li[hidden]{display:none}
.tiles li[id]{scroll-margin-top:var(--nav-h,76px)}
.tile{position:relative;display:flex;flex-direction:column;height:150px;padding:12px 12px 46px;color:inherit;background:var(--surface);border:1px solid var(--border);border-top:3px solid var(--tc);border-radius:14px;box-shadow:0 5px 16px rgb(34 54 43 / 5%);text-decoration:none;overflow:hidden;transition:transform .14s ease,box-shadow .14s ease,border-color .14s ease;animation:rise .45s both;animation-delay:var(--d,0ms)}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media (hover:hover) and (pointer:fine){
.tile:hover{transform:translateY(-2px);border-color:var(--tc);border-top-color:var(--tc);box-shadow:0 10px 24px rgb(34 54 43 / 12%)}
}
.tile:active{transform:scale(.985);border-color:var(--tc)}
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
.chips{gap:6px}
.chip{padding:6px 11px;font-size:.76rem}
.chip .dot{width:8px;height:8px}
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
                <ul class="chips" id="trade-chips">
                    <li><a class="chip" href="#top"><span class="name">全部</span><strong><?= count($crews) ?></strong></a></li>
                    <?php $gi = 0; foreach ($groups as $typeName => $groupCrews): ?>
                        <li><a class="chip" href="#trade-<?= $gi ?>" style="--tc:<?= $groupColors[$typeName] ?>"><span class="dot" aria-hidden="true"></span><span class="name" title="<?= $escape($typeName) ?>"><?= $escape($typeName) ?></span><strong><?= count($groupCrews) ?></strong></a></li>
                    <?php $gi++; endforeach; ?>
                    <li id="chips-toggle-item" hidden><button type="button" class="chip chip-toggle" id="chips-toggle" aria-expanded="false" aria-controls="trade-chips"></button></li>
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
                    <li<?= $anchor ?> data-type="<?= $escape($typeName) ?>" style="--tc:<?= $groupColors[$typeName] ?>;--tc-soft:<?= $groupColors[$typeName] ?>22">
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
    // The trade nav wraps to as many rows as the screen needs, so its height is
    // not a constant. Measure it and publish it as --nav-h: anchor jumps then
    // land just below the sticky bar on every viewport and after any resize.
    var nav = document.querySelector('.trade-nav');
    var chipList = document.getElementById('trade-chips');
    var toggleItem = document.getElementById('chips-toggle-item');
    var toggle = document.getElementById('chips-toggle');
    var chipItems = chipList ? [].slice.call(chipList.children).filter(function (li) { return li !== toggleItem; }) : [];
    var expanded = false;
    // Narrow screens get at most two chip rows so a long trade list cannot eat
    // a quarter of the screen; the rest stay one tap away behind the toggle.
    var MAX_ROWS = 2;

    function syncNavHeight() {
        var h = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--nav-h', (h + 14) + 'px');
    }

    function layoutChips() {
        if (!chipItems.length) { syncNavHeight(); return; }
        for (var i = 0; i < chipItems.length; i++) chipItems[i].hidden = false;
        if (toggleItem) toggleItem.hidden = true;
        if (chipList) chipList.classList.remove('is-expanded');
        if (expanded) {
            if (chipList) chipList.classList.add('is-expanded');
            if (toggle) toggle.textContent = '收起 ▴';
            syncNavHeight();
            return;
        }

        // Measure natural rows, then keep only the first MAX_ROWS so the bar
        // stays compact; everything else is one tap away behind the toggle.
        var rows = [];
        for (var j = 0; j < chipItems.length; j++) {
            var top = Math.round(chipItems[j].getBoundingClientRect().top);
            if (rows.indexOf(top) === -1) rows.push(top);
        }
        if (rows.length <= MAX_ROWS) { syncNavHeight(); return; }

        // Show the toggle inside the flow, then hide overflow rows plus any chip
        // that would push the toggle onto an extra row.
        if (toggleItem) toggleItem.hidden = false;
        if (toggle) toggle.textContent = '更多工种 ▾';
        var toggleWidth = toggleItem ? toggleItem.getBoundingClientRect().width + 7 : 0;
        var limit = (chipList ? chipList.getBoundingClientRect().right : window.innerWidth) - toggleWidth;
        var lastRowTop = rows[MAX_ROWS - 1];
        var hiddenCount = 0;
        for (var k = 0; k < chipItems.length; k++) {
            var rect = chipItems[k].getBoundingClientRect();
            var rowIndex = rows.indexOf(Math.round(rect.top));
            if (rowIndex >= MAX_ROWS || (rowIndex === MAX_ROWS - 1 && rect.right > limit)) {
                chipItems[k].hidden = true;
                hiddenCount++;
            }
        }
        if (toggle) toggle.textContent = '更多工种 ' + hiddenCount + ' ▾';
        syncNavHeight();
    }

    function refresh() { layoutChips(); }
    refresh();
    window.addEventListener('resize', refresh);
    window.addEventListener('orientationchange', refresh);
    if (nav && window.ResizeObserver) {
        // Height changes come from our own layout, so only react to width.
        var lastWidth = nav.getBoundingClientRect().width;
        new ResizeObserver(function () {
            var w = nav.getBoundingClientRect().width;
            if (Math.abs(w - lastWidth) > 1) { lastWidth = w; layoutChips(); }
            else syncNavHeight();
        }).observe(nav);
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            expanded = !expanded;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            for (var i = 0; i < chipItems.length; i++) chipItems[i].hidden = false;
            if (expanded) {
                chipList.classList.add('is-expanded');
                chipList.scrollTop = 0;
                toggle.textContent = '收起 ▴';
                syncNavHeight();
            } else {
                chipList.classList.remove('is-expanded');
                layoutChips();
            }
        });
    }

    // After a touch/pen tap the chip would keep focus (and its focus ring on
    // some engines) while the reader is looking at tiles. Drop it for non-keyboard
    // activation only, so keyboard users keep their visible focus.
    if (chipList) {
        chipList.addEventListener('click', function (event) {
            var chip = event.target.closest ? event.target.closest('.chip') : null;
            if (!chip) return;
            var keyboard = event.detail === 0;
            if (!keyboard && typeof chip.blur === 'function') chip.blur();
        });
    }

    // Highlight the trade the reader is currently looking at.
    var tradeItems = [].slice.call(document.querySelectorAll('#share-list > li[data-type]'));
    var tradeChips = chipItems.filter(function (li) { return li.querySelector('.chip[href^="#trade-"]'); });
    function syncActiveChip() {
        if (!tradeItems.length || !tradeChips.length) return;
        var line = (nav ? nav.getBoundingClientRect().bottom : 0) + 24;
        var current = null;
        for (var i = 0; i < tradeItems.length; i++) {
            if (tradeItems[i].hidden) continue;
            if (tradeItems[i].getBoundingClientRect().top <= line) current = tradeItems[i];
            else break;
        }
        var type = current ? current.dataset.type : null;
        for (var j = 0; j < tradeChips.length; j++) {
            var chip = tradeChips[j].querySelector('.chip');
            var label = chip.querySelector('.name');
            var isOn = type !== null && label && label.textContent === type;
            if (isOn) chip.setAttribute('aria-current', 'true');
            else chip.removeAttribute('aria-current');
        }
    }
    var ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () { ticking = false; syncActiveChip(); });
    }, { passive: true });
    syncActiveChip();

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
        syncActiveChip();
    }
    search.addEventListener('input', apply);
})();
</script>
</body>
</html>

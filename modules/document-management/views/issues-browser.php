<?php
$statusLabels = [
    'open' => '待评估',
    'in_review' => '处理中',
    'resolved' => '待关闭',
    'closed' => '已关闭',
];
$severityLabels = [
    'low' => '低',
    'medium' => '中',
    'high' => '高',
    'critical' => '紧急',
];
$statusBadgeClass = [
    'open' => 'is-blue',
    'in_review' => 'is-amber',
    'resolved' => 'is-green',
    'closed' => 'is-neutral',
];
$severityBadgeClass = [
    'low' => 'is-neutral',
    'medium' => 'is-blue',
    'high' => 'is-orange',
    'critical' => 'is-red',
];
$rfiStatusLabels = [
    'draft' => '草稿',
    'submitted' => '已提交',
    'under_review' => '审核中',
    'responded' => '已答复',
    'closed' => '已关闭',
];
$rfiStatusBadgeClass = [
    'draft' => 'is-neutral',
    'submitted' => 'is-blue',
    'under_review' => 'is-amber',
    'responded' => 'is-green',
    'closed' => 'is-neutral',
];
$canEdit = !empty($canEdit);
$imageStorageReady = !empty($imageStorageReady);
$issueCount = count($issues);
$selectedIssue = null;
foreach ($issues as $issue) {
    if ((int) $issue['issue_id'] === (int) $selectedIssueId) {
        $selectedIssue = $issue;
        break;
    }
}
$issueTitle = static function (string $description): string {
    $parts = preg_split('/[。！？!?\n]/u', trim($description), 2);
    $title = trim((string) ($parts[0] ?? ''));
    if ($title === '') {
        $title = '问题记录';
    }

    return mb_strimwidth($title, 0, 80, '…');
};
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">文档管理 · 问题浏览</p>
        <h1>问题浏览</h1>
        <p class="muted">项目：<?= $escape($currentProject['project_name']) ?>。左侧浏览全部问题，右侧查看完整描述与现场图片；状态变更与编辑仍在问题台账完成。</p>
    </div>
    <div class="ib-head-stats" aria-label="问题数量概览">
        <span class="ib-stat"><b><?= (int) $totalCount ?></b> 全部</span>
        <span class="ib-stat"><b><?= (int) $pendingCount ?></b> 未闭环</span>
        <span class="ib-stat"><b><?= (int) $withImageCount ?></b> 有图</span>
    </div>
</section>
<?php
$navItems = [
    ['page' => 'projects', 'label' => '项目首页'],
    ['page' => 'documents', 'label' => '文档管理首页'],
    ['page' => 'issues', 'label' => '问题管理'],
    ['page' => 'issue-browser', 'label' => '问题浏览'],
    ['page' => 'rfis', 'label' => 'RFI 管理'],
];
if (!empty($canManageSettings)) {
    $navItems[] = ['page' => 'document-settings', 'label' => '系统设置'];
}
$navCurrent = 'issue-browser';
$navLabel = '文档管理';
require dirname(__DIR__, 3) . '/views/partials/module_nav.php';
?>

<div class="ib-shell" data-issue-browser>
    <aside class="panel ib-list-panel" aria-label="问题列表">
        <div class="ib-list-head">
            <h2>全部问题</h2>
            <a class="ib-ledger-link" href="?page=issues">问题台账 →</a>
        </div>
        <div class="toolbar ib-toolbar" role="search" aria-label="筛选问题">
            <label class="search-field">
                <span class="sr-only">搜索问题</span>
                <input type="search" placeholder="搜索描述、编号、位置…" data-list-search="issue-browser-list">
            </label>
            <label>
                状态
                <select data-list-filter="status">
                    <option value="">全部状态</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                严重程度
                <select data-list-filter="severity">
                    <option value="">全部程度</option>
                    <?php foreach ($severityLabels as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="ib-toggle">
                <input type="checkbox" data-list-toggle="images" value="1">
                只看有图
            </label>
            <button class="button button-quiet" type="button" data-list-filter-clear hidden>清空筛选</button>
            <span class="toolbar-count" data-list-count data-unit=" 条" aria-live="polite"><?= $issueCount ?> 条</span>
        </div>

        <?php if ($issues === []): ?>
            <p class="empty-state">当前项目还没有问题记录。</p>
        <?php else: ?>
            <div class="ib-list" id="issue-browser-list" role="list">
                <?php foreach ($issues as $issue): ?>
                    <?php
                    $searchText = mb_strtolower(implode(' ', [
                        $issue['description'],
                        $issue['issue_number'] ?? '',
                        $issue['location_display'] ?? '',
                        $statusLabels[$issue['status']] ?? $issue['status'],
                        $severityLabels[$issue['severity']] ?? $issue['severity'],
                    ]));
                    $isSelected = (int) $issue['issue_id'] === (int) $selectedIssueId;
                    $firstImage = $issue['images'][0] ?? null;
                    ?>
                    <a class="ib-card<?= $isSelected ? ' is-active' : '' ?>" role="listitem"
                       href="?page=issue-browser&amp;issue=<?= (int) $issue['issue_id'] ?>#issue-detail-<?= (int) $issue['issue_id'] ?>"
                       data-list-item data-issue-card="<?= (int) $issue['issue_id'] ?>"
                       data-search="<?= $escape($searchText) ?>"
                       data-status="<?= $escape($issue['status']) ?>"
                       data-severity="<?= $escape($issue['severity']) ?>"
                       data-images="<?= $issue['images'] !== [] ? '1' : '0' ?>"
                       <?= $isSelected ? 'aria-current="true"' : '' ?>>
                        <span class="ib-card-thumb">
                            <?php if ($firstImage !== null && isset($firstImage['url'])): ?>
                                <img src="<?= $escape($firstImage['url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <span class="ib-card-thumb-empty" aria-hidden="true">无图</span>
                            <?php endif; ?>
                        </span>
                        <span class="ib-card-body">
                            <span class="ib-card-top">
                                <b><?= $escape($issue['issue_number'] ?: '#' . $issue['issue_id']) ?></b>
                                <span class="status-badge <?= $escape($statusBadgeClass[$issue['status']] ?? 'is-neutral') ?>"><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span>
                                <span class="status-badge <?= $escape($severityBadgeClass[$issue['severity']] ?? 'is-neutral') ?>"><?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span>
                            </span>
                            <span class="ib-card-desc"><?= $escape($issue['description']) ?></span>
                            <span class="ib-card-meta">
                                <?= $escape(($issue['location_display'] ?? '') !== '' ? $issue['location_display'] : '未指定位置') ?>
                                · <?= count($issue['images']) ?> 图
                                · <?= $escape(\ProjectO\Support::formatDateTime($issue['updated_at'])) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="empty-state" data-list-empty hidden>没有匹配的问题。</p>
        <?php endif; ?>
    </aside>

    <div class="ib-detail-wrap">
        <?php if ($selectedIssue === null): ?>
            <section class="panel ib-empty-detail">
                <h2>暂无问题可查看</h2>
                <p class="muted">当前项目还没有问题记录。可在问题台账登记第一个问题。</p>
                <a class="button button-primary" href="?page=issues">前往问题台账</a>
            </section>
        <?php endif; ?>

        <?php foreach ($issues as $issue): ?>
            <?php
            $issueId = (int) $issue['issue_id'];
            $isSelected = $issueId === (int) $selectedIssueId;
            $images = $issue['images'];
            $openLinkedRfis = 0;
            foreach ($issue['linked_rfis'] as $linkedRfi) {
                if ($linkedRfi['status'] !== 'closed') {
                    $openLinkedRfis++;
                }
            }
            $openFollowUps = 0;
            foreach ($issue['follow_ups'] as $followUp) {
                if ((int) $followUp['is_completed'] !== 1) {
                    $openFollowUps++;
                }
            }
            ?>
            <section class="panel ib-detail" id="issue-detail-<?= $issueId ?>" data-ib-detail="<?= $issueId ?>"<?= $isSelected ? '' : ' hidden' ?>>
                <header class="ib-detail-head">
                    <div class="ib-detail-title">
                        <p class="eyebrow">问题 <?= $escape($issue['issue_number'] ?: '#' . $issueId) ?> · <?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></p>
                        <h2><?= $escape($issueTitle((string) $issue['description'])) ?></h2>
                        <div class="ib-badges">
                            <span class="status-badge <?= $escape($statusBadgeClass[$issue['status']] ?? 'is-neutral') ?>"><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span>
                            <span class="status-badge <?= $escape($severityBadgeClass[$issue['severity']] ?? 'is-neutral') ?>">严重程度：<?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span>
                            <span class="ib-chip"><?= $escape(($issue['location_display'] ?? '') !== '' ? $issue['location_display'] : '未指定位置') ?></span>
                        </div>
                    </div>
                    <div class="ib-detail-actions">
                        <a class="button button-secondary" href="?page=issues#issue-<?= $issueId ?>">返回台账</a>
                        <?php if ($canEdit): ?>
                            <a class="button button-primary" href="?page=issues#edit-issue-<?= $issueId ?>">编辑此问题</a>
                        <?php endif; ?>
                    </div>
                </header>

                <p class="muted ib-detail-meta">
                    登记于 <?= $escape(\ProjectO\Support::formatDateTime($issue['reported_at'])) ?>
                    · 登记人 #<?= (int) $issue['reported_by'] ?>
                    · 最近更新 <?= $escape(\ProjectO\Support::formatDateTime($issue['updated_at'])) ?>
                </p>

                <div class="ib-detail-body">
                <div class="ib-stage" data-ib-stage>
                    <?php if ($images === []): ?>
                        <div class="ib-stage-empty">
                            <p>这个问题还没有图片。</p>
                            <?php if ($canEdit): ?>
                                <p class="muted">在下方“图片管理”上传现场照片，或添加 https 外链图片。</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="ib-slides">
                            <?php foreach ($images as $index => $image): ?>
                                <figure class="ib-slide" data-ib-slide<?= $index === 0 ? '' : ' hidden' ?>>
                                    <?php if (isset($image['url'])): ?>
                                        <img class="ib-stage-image" src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" referrerpolicy="no-referrer" data-ib-zoom-trigger title="点击放大查看">
                                    <?php else: ?>
                                        <div class="ib-slide-missing">图片暂不可读取（存储未配置）</div>
                                    <?php endif; ?>
                                    <figcaption>
                                        <span><?= $escape($image['original_name']) ?></span>
                                        <?php if (!empty($image['external'])): ?>
                                            <span class="ib-slide-tag">外链 · 原站提供</span>
                                        <?php endif; ?>
                                    </figcaption>
                                </figure>
                            <?php endforeach; ?>
                            <?php if (count($images) > 1): ?>
                                <button class="ib-zone is-prev" type="button" data-ib-zone-prev aria-label="上一张图片"><span aria-hidden="true">‹</span></button>
                                <button class="ib-zone is-next" type="button" data-ib-zone-next aria-label="下一张图片"><span aria-hidden="true">›</span></button>
                            <?php endif; ?>
                        </div>
                        <div class="ib-stage-bar">
                            <button class="button button-quiet ib-stage-nav" type="button" data-ib-prev aria-label="上一张">←</button>
                            <span class="ib-stage-counter" data-ib-counter>1 / <?= count($images) ?></span>
                            <button class="button button-quiet ib-stage-nav" type="button" data-ib-next aria-label="下一张">→</button>
                            <span class="ib-stage-spacer"></span>
                            <button class="button button-quiet" type="button" data-ib-zoom-open>放大查看</button>
                            <a class="button button-quiet" href="<?= $escape($images[0]['url'] ?? '?page=issues') ?>" target="_blank" rel="noopener noreferrer" title="在新窗口打开原图" data-ib-original-link>新窗口</a>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <div class="ib-thumbs" aria-label="图片缩略图">
                                <?php foreach ($images as $index => $image): ?>
                                    <button class="ib-thumb<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-ib-thumb="<?= $index ?>" aria-label="查看第 <?= $index + 1 ?> 张图片">
                                        <?php if (isset($image['url'])): ?>
                                            <img src="<?= $escape($image['url']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                        <?php else: ?>
                                            <span class="ib-thumb-empty">图</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="ib-detail-copy">
                <div class="ib-info">
                    <div><span class="ib-info-label">状态</span><span><?= $escape($statusLabels[$issue['status']] ?? $issue['status']) ?></span></div>
                    <div><span class="ib-info-label">严重程度</span><span><?= $escape($severityLabels[$issue['severity']] ?? $issue['severity']) ?></span></div>
                    <div><span class="ib-info-label">位置</span><span><?= $escape(($issue['location_display'] ?? '') !== '' ? $issue['location_display'] : '—') ?></span></div>
                    <div><span class="ib-info-label">图片</span><span><?= count($images) ?> 张</span></div>
                    <div><span class="ib-info-label">关联 RFI</span><span><?= count($issue['linked_rfis']) ?> 份<?= $openLinkedRfis > 0 ? '（' . $openLinkedRfis . ' 份未关闭）' : '' ?></span></div>
                    <div><span class="ib-info-label">后续事项</span><span><?= count($issue['follow_ups']) ?> 项<?= $openFollowUps > 0 ? '（' . $openFollowUps . ' 项未完成）' : '' ?></span></div>
                </div>

                <section class="ib-section">
                    <h3>问题描述</h3>
                    <p class="ib-description"><?= nl2br($escape($issue['description'])) ?></p>
                </section>

                <?php if ($issue['resolution_result']): ?>
                    <section class="ib-section">
                        <h3>处理结果</h3>
                        <p><?= nl2br($escape($issue['resolution_result'])) ?></p>
                        <?php if ($issue['verification_status'] !== null): ?>
                            <p class="muted">现场验证：<?= $issue['verification_status'] === 'passed' ? '已通过' : '不适用' ?><?= $issue['verification_note'] ? ' · ' . $escape($issue['verification_note']) : '' ?></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <details class="record-tools" open>
                    <summary>图片管理（<?= count($images) ?>）</summary>
                    <?php if ($images !== []): ?>
                        <ul class="dialog-images">
                            <?php foreach ($images as $image): ?>
                                <li>
                                    <?php if (isset($image['url'])): ?>
                                        <a class="dialog-image-thumb" href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?= $escape($image['url']) ?>" alt="<?= $escape($image['original_name']) ?>" loading="lazy" referrerpolicy="no-referrer">
                                        </a>
                                        <a href="<?= $escape($image['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($image['original_name']) ?></a>
                                    <?php else: ?>
                                        <span><?= $escape($image['original_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="muted"><?= !empty($image['external']) ? '外链 · 原站提供' : number_format((int) $image['file_size'] / 1024, 1) . ' KB' ?></span>
                                    <?php if ($canEdit): ?>
                                        <form method="post" class="inline-form" data-confirm="<?= !empty($image['external']) ? '删除后仅移除外链记录，原站图片不受影响。' : '删除后图片将从记录和对象存储中移除。' ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="issue.image.delete">
                                            <input type="hidden" name="issue_id" value="<?= $issueId ?>">
                                            <input type="hidden" name="image_id" value="<?= (int) $image['image_id'] ?>">
                                            <input type="hidden" name="return" value="browser">
                                            <button class="button button-quiet" type="submit">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">尚未添加图片。</p>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <?php if ($imageStorageReady): ?>
                            <form method="post" enctype="multipart/form-data" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="issue.image.upload">
                                <input type="hidden" name="issue_id" value="<?= $issueId ?>">
                                <input type="hidden" name="return" value="browser">
                                <label>上传图片<input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/bmp,image/webp" required></label>
                                <button class="button button-secondary" type="submit">上传</button>
                                <span class="muted">PNG/JPEG/GIF/BMP/WebP，最大 5 MB</span>
                            </form>
                        <?php else: ?>
                            <p class="muted">图片存储未配置，暂不可上传本地图片；可直接添加 https 外链。</p>
                        <?php endif; ?>
                        <form method="post" class="inline-form image-link-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="action" value="issue.image.link">
                            <input type="hidden" name="issue_id" value="<?= $issueId ?>">
                            <input type="hidden" name="return" value="browser">
                            <label>外链图片地址（仅 https）<input type="url" name="image_url" maxlength="1024" placeholder="https://…" required></label>
                            <label>显示名称（可选）<input name="image_name" maxlength="255" placeholder="默认取文件名"></label>
                            <button class="button button-secondary" type="submit">添加外链</button>
                            <span class="muted">只保存地址，不占用对象存储；仅支持公网 https 图片。</span>
                        </form>
                    <?php endif; ?>
                </details>

                <details class="record-tools">
                    <summary>关联 RFI（<?= count($issue['linked_rfis']) ?>）</summary>
                    <?php if ($issue['linked_rfis'] === []): ?>
                        <p class="muted">该问题尚未派生 RFI。</p>
                    <?php else: ?>
                        <ul class="follow-up-list">
                            <?php foreach ($issue['linked_rfis'] as $linkedRfi): ?>
                                <li>
                                    <div>
                                        <a href="?page=rfis#rfi-<?= (int) $linkedRfi['rfi_id'] ?>"><?= $escape($linkedRfi['rfi_number'] !== '' ? $linkedRfi['rfi_number'] : '#' . $linkedRfi['rfi_id']) ?></a>
                                        <span class="status-badge <?= $escape($rfiStatusBadgeClass[$linkedRfi['status']] ?? 'is-neutral') ?>"><?= $escape($rfiStatusLabels[$linkedRfi['status']] ?? $linkedRfi['status']) ?></span>
                                        <span class="muted record-meta"><?= $linkedRfi['is_primary'] ? '主问题' : '关联问题' ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </details>

                <details class="record-tools">
                    <summary>后续事项（<?= count($issue['follow_ups']) ?>）</summary>
                    <?php if ($issue['follow_ups'] === []): ?>
                        <p class="muted">暂无后续事项。</p>
                    <?php else: ?>
                        <ul class="follow-up-list">
                            <?php foreach ($issue['follow_ups'] as $followUp): ?>
                                <li>
                                    <div>
                                        <span class="<?= (int) $followUp['is_completed'] === 1 ? 'is-complete' : '' ?>"><?= $escape($followUp['title']) ?></span>
                                        <span class="muted record-meta"><?= (int) $followUp['is_completed'] === 1 ? '已完成' : '未完成' ?></span>
                                        <?php foreach ($followUp['status_history'] as $event): ?>
                                            <span class="muted record-meta"><?= $escape(\ProjectO\Support::formatDateTime($event['changed_at'])) ?> · <?= $escape($event['reason']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="muted record-meta">在问题台账的编辑弹窗中新增或完成后续事项。</p>
                </details>

                <details class="record-tools">
                    <summary>状态历史（<?= count($issue['status_history']) ?>）</summary>
                    <?php if ($issue['status_history'] === []): ?>
                        <p class="muted">暂无状态历史。</p>
                    <?php else: ?>
                        <ol class="history-list">
                            <?php foreach ($issue['status_history'] as $event): ?>
                                <li>
                                    <strong><?= $escape($event['old_status'] === null ? '新建' : ($statusLabels[$event['old_status']] ?? $event['old_status'])) ?> → <?= $escape($statusLabels[$event['new_status']] ?? $event['new_status']) ?></strong>
                                    <span><?= $escape(\ProjectO\Support::formatDateTime($event['changed_at'])) ?> · 操作人 #<?= (int) $event['changed_by'] ?></span>
                                    <p><?= nl2br($escape($event['reason'])) ?></p>
                                    <?php if ($event['evidence_reference']): ?><p>证据档案：<?= $escape($event['evidence_reference']) ?></p><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </details>

                <footer class="ib-detail-foot">
                    <a class="button button-secondary" href="?page=issues#issue-<?= $issueId ?>">返回问题台账</a>
                    <?php if ($canEdit): ?>
                        <a class="button button-primary" href="?page=issues#edit-issue-<?= $issueId ?>">编辑问题 / 处理状态</a>
                    <?php endif; ?>
                </footer>
                </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="ib-zoom" data-ib-zoom hidden role="dialog" aria-modal="true" aria-label="图片放大预览" tabindex="-1">
        <button class="ib-zoom-close" type="button" data-ib-zoom-close aria-label="关闭放大预览">×</button>
        <button class="ib-zoom-nav is-prev" type="button" data-ib-zoom-prev aria-label="上一张图片"><span aria-hidden="true">‹</span></button>
        <img data-ib-zoom-image alt="">
        <button class="ib-zoom-nav is-next" type="button" data-ib-zoom-next aria-label="下一张图片"><span aria-hidden="true">›</span></button>
        <span class="ib-zoom-counter" data-ib-zoom-counter></span>
    </div>
</div>
<?php
$content = ob_get_clean();
$pageStyles = [['module' => 'document.management', 'file' => 'issue-browser.css', 'version' => $moduleAssetVersions['issue-browser.css'] ?? '0']];
$pageScripts = [['module' => 'document.management', 'file' => 'issue-browser.js', 'version' => $moduleAssetVersions['issue-browser.js'] ?? '0']];
require dirname(__DIR__, 3) . '/views/layout.php';
?>

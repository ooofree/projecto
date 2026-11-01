<?php ob_start(); ?>
<section class="auth-card">
    <p class="eyebrow">请求未完成</p>
    <h1>
        <?php if (http_response_code() === 403): ?>
            没有访问权限
        <?php elseif (http_response_code() === 404): ?>
            页面不存在
        <?php else: ?>
            发生错误
        <?php endif; ?>
    </h1>
    <p><?= $escape($message ?? '请求无法完成。') ?></p>
    <a class="button button-secondary" href="?page=projects">返回项目</a>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>

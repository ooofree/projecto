<?php ob_start(); ?>
<section class="auth-card">
    <p class="eyebrow">项目管理平台</p>
    <h1>登录</h1>
    <p class="muted">登录后先选择项目，再进入项目业务。</p>
    <form method="post" action="" class="stack">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="login">
        <label>
            用户名
            <input name="username" autocomplete="username" maxlength="255" required autofocus>
        </label>
        <label>
            密码
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="button button-primary" type="submit">登录</button>
    </form>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>

<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title ?? 'Project O') ?> · Project O</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="theme-<?= $escape($theme) ?>">
<header class="topbar">
    <a class="brand" href="?page=projects">Project O</a>
    <?php if ($username !== ''): ?>
        <div class="account">
            <?php if ($canManagePlatform): ?>
                <a class="button button-quiet" href="?page=admin">平台管理</a>
            <?php endif; ?>
            <span><?= $escape($username) ?></span>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="button button-quiet" type="submit">退出</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
    <?php if (is_array($flash ?? null)): ?>
        <div class="notice notice-<?= $escape($flash['type'] ?? 'success') ?>" role="status">
            <?= $escape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</main>
</body>
</html>

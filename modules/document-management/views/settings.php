<?php
ob_start();
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">文档管理 · 系统设置</p>
        <h1>系统设置</h1>
        <p class="muted">图片存储配置对问题、RFI 与材料样品图片统一生效；数据库配置优先于 .env 环境变量。</p>
    </div>
</section>
<nav class="module-nav" aria-label="文档管理">
    <a class="button button-secondary" href="?page=projects">项目首页</a>
    <a class="button button-secondary" href="?page=documents">文档管理首页</a>
    <a class="button button-secondary" href="?page=issues">问题管理</a>
    <a class="button button-secondary" href="?page=rfis">RFI 管理</a>
    <a class="button button-primary" href="?page=document-settings" aria-current="page">系统设置</a>
</nav>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>图片存储（Cloudflare R2）</h2>
            <p class="muted">私有桶；应用仅为已授权用户生成 5 分钟有效的读取签名 URL。上传限制为 PNG/JPEG/GIF/BMP/WebP、单文件 5 MiB。</p>
        </div>
        <span class="muted">
            <?php if (($storageStatus['configured'] ?? false)): ?>
                已生效
            <?php elseif (($storageConfig['enabled'] ?? false)): ?>
                已启用但不可用
            <?php else: ?>
                未启用
            <?php endif; ?>
        </span>
    </div>
    <p class="muted">
        当前配置来源：
        <?php if (($storageStatus['source'] ?? 'none') === 'database'): ?>
            数据库系统设置
        <?php elseif (($storageStatus['source'] ?? 'none') === 'environment'): ?>
            .env 环境变量
        <?php else: ?>
            尚未配置
        <?php endif; ?>
        ；
        <?php if (($storageStatus['sdk_available'] ?? false)): ?>
            服务器已安装 AWS SDK。
        <?php else: ?>
            服务器尚未安装 AWS SDK（composer require aws/aws-sdk-php），保存配置后图片功能仍不可用。
        <?php endif; ?>
        <?php if (($storageConfig['secret_configured'] ?? false)): ?>
            已保存 Secret Access Key（留空提交表示保留）。
        <?php endif; ?>
    </p>
    <form method="post" action="" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="action" value="document.settings.save">
        <input type="hidden" name="enabled" value="0">
        <label class="field-wide">启用 R2 图片存储
            <input type="checkbox" name="enabled" value="1" <?= ($storageConfig['enabled'] ?? false) ? 'checked' : '' ?>>
        </label>
        <label>R2 Account ID
            <input name="account_id" maxlength="32" value="<?= $escape($storageConfig['account_id'] ?? '') ?>" placeholder="32 位十六进制账户 ID">
        </label>
        <label>存储桶名称
            <input name="bucket" maxlength="63" value="<?= $escape($storageConfig['bucket'] ?? '') ?>" placeholder="保持私有的 bucket 名称">
        </label>
        <label>Access Key ID
            <input name="access_key_id" maxlength="128" value="<?= $escape($storageConfig['access_key_id'] ?? '') ?>">
        </label>
        <label>Secret Access Key
            <input type="password" name="secret_access_key" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ($storageConfig['secret_configured'] ?? false) ? '留空保持已保存的密钥' : '请输入密钥' ?>">
        </label>
        <div class="field-wide muted">
            存储桶应保持私有；密钥只写不回显。保存后配置立即对问题、RFI 与材料样品图片生效。
        </div>
        <div class="field-wide dialog-actions">
            <button class="button button-primary" type="submit">保存图片存储配置</button>
        </div>
    </form>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__, 3) . '/views/layout.php'; ?>

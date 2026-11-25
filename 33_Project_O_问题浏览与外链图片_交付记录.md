# Project O — 文档管理「问题浏览」与外链图片交付记录

日期：2026-11-25

## 本次交付

1. 新增只读「问题浏览」页（`?page=issue-browser`）：左侧可筛选问题列表 + 右侧详情与深色图片舞台，图片为首要信息；支持图片左右切换、缩略图、全屏、新窗口打开。
2. 问题台账「查看」改为进入问题浏览页；「编辑」仍打开原详情模态框，状态流转与快捷操作仍留在台账。台账图片列改为首图缩略图，点击进入详情；无图显示占位。
3. 图片录入新增 https 外链：只保存地址，不下载、不占用 R2；存储未配置时也可用。外链仅接受公网 https，拒绝 http、localhost/.local、内网与保留 IP、带账号信息的 URL。
4. RFI 同步支持外链图片与台账首图缩略图；RFI 的查看/编辑仍在原模态框。
5. Excel 导出支持外链图片：按字节嗅探格式嵌入，超链接指向原站；导出不再要求对象存储已配置。
6. 页面 CSP 放行 https 图片（`img-src 'self' https: data:`），私有桶图片仍走同源代理。

## 主要文件

- `modules/document-management/views/issues-browser.php`（新增）
- `modules/document-management/public/issue-browser.css`、`issue-browser.js`（新增）
- `modules/document-management/src/DocumentManagementPlugin.php`
- `modules/document-management/src/DocumentImageService.php`
- `modules/document-management/views/issues.php`、`rfis.php`、`documents.php`、`settings.php`
- `modules/document-management/plugin.json`
- `public/assets/app.js`（列表复选框过滤 `data-list-toggle`、台账编辑深链 `#edit-issue-<id>`）
- `public/index.php`（CSP）
- `src/Export/ExportImage.php`（外链字节嗅探）
- `modules/document-management/database/migrations/202611250001_issue_rfi_image_external_url.sql`
- `tests/document_management_test.php`、`tests/documents_home_smoke_test.php`

## 数据库

已执行迁移（`php bin/install.php`）：

- `document_issue_images`、`document_rfi_images` 的 `object_key` 放宽为可空，并新增 `external_url varchar(1024) NULL`。
- 仅新增可空列、放宽约束，不修改或删除既有数据。

外链记录约定：`object_key = NULL`、`external_url = https 地址`、`content_type = image/external`、`file_size = 0`。删除外链只删记录，不访问对象存储。

## 验证

- `composer test` 全部通过（含新增外链校验、浏览页渲染、图片动作返回路径断言）。
- 对真实库 `project_o` 只读渲染通过：`?page=issue-browser&issue=10`，桌面 1600px 与手机 480px 截图核对。
- 台账页验证：首图缩略图、查看/编辑拆分、`#edit-issue-<id>` 深链目标。
- JS 语法检查通过（`node --check`）。

## 边界

- 文档管理首页、图纸管理未改动。
- 未新增独立「RFI 浏览」页；RFI 仅同步外链能力与首图缩略图，浏览页可按需后续复用本套视图。
- 外链图片可用性依赖原站可访问与防盗链策略；导出嵌入失败时该单元格退回 `-`，不阻断整份导出。

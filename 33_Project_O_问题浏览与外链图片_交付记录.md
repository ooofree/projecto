# Project O — 文档管理「问题浏览」与外链图片交付记录

日期：2026-11-25

## 本次交付

1. 新增只读「问题浏览」页（`?page=issue-browser`）：左侧可筛选问题列表 + 右侧详情。桌面端详情采用「图片侧栏 + 文字信息」两栏布局，图片不再独占整行，状态、位置、描述与处理结果可与图片同屏查看；图片侧栏在页面滚动时吸附在模块导航下方，右侧长内容独立滚动，不会被图片或导航遮盖；手机端自动上下堆叠。图片点击直接全屏放大（不再跳转原链接）；舞台左右边缘可点击切图，也可用键盘 ←/→ 切图；`Esc`、关闭按钮或点击遮罩空白退出放大。底部保留“新窗口”作为显式原图入口。
2. 问题台账「查看」改为进入问题浏览页；「编辑」仍打开原详情模态框，状态流转与快捷操作仍留在台账。台账图片列保留图片，但缩为首图 36×27px 小缩略图（多图右下角显示 +N），点击进入详情；无图显示占位。相比原 48×36px 明显收敛，行高不再被图片撑高，横向空间留给问题、位置与操作列。
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
- 对真实库 `project_o` 只读渲染通过：问题浏览页桌面 1600px 与手机 480px 截图核对；桌面端图片侧栏占详情 55% 宽，图片高度上限 540px（同时受 60vh 约束），手机端堆叠且图片高度上限 340px。
- 交互验证（真实库双图问题）：点击舞台图片打开放大遮罩；按键盘 `ArrowRight` 计数由 `1/2` 变 `2/2`；点击舞台右边缘热区切到第 2 张；`Esc` 关闭遮罩并解除滚动锁定。
- 吸附验证：桌面端页面滚动 700px 后，模块导航吸附在 58–106px，左侧列表与图片舞台均停在 122px，位于导航下方 16px，不再被导航覆盖；右侧长内容继续滚动。≤1080px 取消吸附，避免遮挡正文。
- 台账页验证：36×27px 首图缩略图、多图 +N 角标、查看/编辑拆分、`#edit-issue-<id>` 深链目标。
- JS 语法检查通过（`node --check`）。

## 边界

- 文档管理首页、图纸管理未改动。
- 未新增独立「RFI 浏览」页；RFI 仅同步外链能力与首图缩略图，浏览页可按需后续复用本套视图。
- 外链图片可用性依赖原站可访问与防盗链策略；导出嵌入失败时该单元格退回 `-`，不阻断整份导出。

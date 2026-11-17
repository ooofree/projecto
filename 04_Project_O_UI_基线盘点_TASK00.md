# TASK-00 基线盘点（UI V2 改造）

日期：2026-11-17。只读盘点，未修改任何代码。依据：03 执行任务书 TASK-00；01 审查报告；02 设计系统 V2。
执行方式：按用户指示不建分支、原地改动（v0.01 已备份）。

## 1. 基线状态

- git：分支 `main`，HEAD `74b80b3`（图片浏览器缓存/ETag 提交），`git describe` = `v0.01`。
- 工作树含大量用户在途未提交改动（迁移、服务、视图、测试等）；本次改造不提交、不还原这些改动。
- PHP 8.3.26（WSL Ubuntu-24.04，项目路径 /var/www/html/o）；composer 可用，`vendor/` 已安装（composer.json/lock 齐全）。
- 基线测试：`composer test` 全部 11 个测试通过（auth / project foundation plugin / project service / project space / document / material / platform administration / profile / labor crew / login smoke / projects smoke）。测试为独立原生 PHP 脚本 + SQLite 内存库，无 PHPUnit，不依赖 .env/MySQL；材料测试需 ext-zip。
- 已知坑：CLI 跑含图片读取的测试会创建 `/tmp/project-o-image-cache`（owner=CLI 用户），导致 Apache(www-data) 不可写；每次跑完测试须清理（本次基线运行后已清理）。
- 工具限制：Windows 端 Edit/Write 不能直接写 UNC；采用临时副本编辑 + PowerShell 写回，验证在 WSL 内执行。

## 2. 页面 → 模板映射

入口 `public/index.php`（session/安全头/autoload）→ `src/Application.php` 分发：POST 按 `action`（:50-104），GET 按 `?page=`（:106-166，默认 projects）。

| 路由 page | 视图 | 所属 |
|---|---|---|
| login | views/login.php | 核心 |
| profile | views/profile.php | 核心 |
| admin（section 参数） | views/admin.php | 核心（manage_platform） |
| projects | modules/project-foundation/views/projects.php (160行) | project.foundation |
| spaces | modules/project-foundation/views/spaces.php (302行) | project.foundation |
| materials | modules/material-management/views/materials.php (344行) | material.management |
| brands / auxiliary-materials / suppliers / supplier-materials / quotations / tender-list / samples / estimates | brands.php(25) / auxiliaries.php(25) / suppliers.php(30) / supplier-materials.php(37) / quotations.php(79) / tenders.php(79) / samples.php(406) / estimates.php(261) | material.management |
| sample-image | 图片内联响应（非视图） | material.management |
| documents / issues / rfis / document-settings | documents.php(36) / issues.php(416) / rfis.php(368) / settings.php(95) | document.management |
| document-image | 图片内联响应 | document.management |
| labor-crews / labor-crew-types | crews.php(213) / types.php(37) | labor.crew |
| labor-crew-share(-image) | 公开分享（静态发布/303），share.php(484) 预渲染 | labor.crew public |
| module-asset | 插件资源流（Application.php:177-207，ETag+immutable+304） | 核心 |

## 3. 导航现状（TASK-02 输入）

- 文档管理导航重复 **4 份**（审查报告说 3 份，实际含 settings.php）：documents.php:12-20、issues.php:123-131、rfis.php:27-35、settings.php:11-17。文案「项目首页/文档管理首页/问题管理/RFI 管理/系统设置」；当前页 button-primary + aria-current 硬编码；前三份「系统设置」受 `$canManageSettings` 门控，settings.php 无条件输出。
- spaces.php:16-20 手写「项目首页/当前空间/空间回收站」。
- material-management/views/_nav.php（12行，10项，`$nav[...]` 权限门控，来自 MaterialManagementPlugin.php:1067-1088），被 9 个视图 require；labor-crew/views/_nav.php（5行，3项，权限来自 LaborCrewPlugin.php:462-470），被 crews/types 引用。两者全部 button-secondary、**无当前页高亮/aria-current**。

## 4. app.css（831 行，57.9KB；另有 app.css.bak 762 行，不动）

- @media 11 处：312(≤900)、319(≤600)、354(prefers-reduced-motion)、480(≤900)、484(≤600)、534(≤900 admin)、571(≤640)、716(≤900)、724(≤600)、793(≤900)、807(≤600)。
- body.theme-dark 约 106 行、8 段：242-310（深色 tokens+基础覆盖）、463-478、521-522、549-555、589-602、734-740、815-831。
- 分区：1-20 :root tokens；22-50 排版/topbar/容器；51-114 面板/表格；116-164 项目首页 hero/卡片；166-240 按钮/表单/徽章/module-nav/dialog；242-310 深色基础；312-356 响应式；358-506 Shared surfaces 精修层（363-379 sticky module-nav）；508-539 dialog 图片/danger/admin 双栏；541-602 状态徽章/画廊/班组分享；607-634 combo；636-740 测算；742-831 材料目录。

## 5. app.js（1146 行）

- 1-451 材料目录 IIFE：applyFilter(:59-70 文本+分类)、nextCodeFor(:94)、selectCategory(:162)、refreshCatalogue(:220 fetch 局部替换)、postMaterial(:254 async=1)、initEntry(:268-382 连续录入，localStorage 记忆分类/单位 :43-56，Enter 提交 :335)、initEditDialogs(:384)。
- 453-476 全局委托：data-disclosure-open / data-dialog-open/close / data-copy-share；628-633 data-confirm。
- 635-656 通用 data-list-search：纯文本子串匹配 data-search + 计数/空态；使用处仅 issues.php:175、rfis.php:109。**无通用下拉/状态筛选机制**（TASK-04 需新增，模式参照材料 applyFilter）。
- 661-1146 测算 IIFE：initCombos(:670)、initCascades(:878)、initLedgerFilter(:958)、initQuickEntry(:1013)。
- 无主题切换 JS；材料模块无专属 JS 文件。

## 6. 主题与资源缓存

- 主题唯一注入点 views/layout.php:54 `<body class="theme-…">`；值来自 System_Settings.theme（PlatformAdministrationService::appearance()，仅 light/dark），后台 admin.php:43-45 下拉设置。**全站开关，非按用户**。
- layout.php:49/80 加载 app.css/app.js(defer)，`$assetVersion` = sha1(filemtime:filesize) 前 10 位 `?v=`（layout.php:11-21）→ 改文件自动换指纹，无需手动 bump。
- 插件资源 `$pageStyles/$pageScripts`（layout.php:50-52/81-83）→ `?page=module-asset&…&v=…`；issue-location.js/css 仅 issues.php:413-414 声明（rfis.php 未加载）；脚本监听 `projecto:content-updated` 增强 fetch 重渲染内容。

## 7. 截图基线清单（待浏览器回归时采集）

页面：projects、materials、documents、issues、rfis、admin(section=系统配置)、labor-crews、suppliers、quotations、samples、estimates、tender-list、spaces。
主题：浅色/深色各一套（切换需后台设置，属全站开关）。
尺寸：1440×900、1280×800、1024×768、768×1024、390×844。
说明：本轮 TASK-00 未做浏览器截图（需登录态）；基线截图在 TASK-01 改动前于浏览器回归阶段补采，或依据 v0.01 备份对照。

## 8. 与审查报告的差异记录

- 文档管理重复导航实为 4 份（含 settings.php），审查报告记 3 份。
- app.css 实际 831 行（审查报告部分行号引用与实际有偏差，以本盘点行号为准）。
- 审查报告称缺 vendor/ 无法跑测试：当前环境 vendor 已装，composer test 全绿，该限制已解除。
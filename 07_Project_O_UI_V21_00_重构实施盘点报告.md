# Project O V2.1 — V21-00 重构实施盘点报告

日期：2026-11-17。本阶段为只读盘点：**未修改任何业务代码、视图、样式、脚本、迁移或配置**；在项目根目录新增本报告一个文件。
依据：《Project_O_UI_V2.1_全站布局重构方案与Agent执行任务书.md》第二节、V21-00 阶段要求；AGENTS.md；README/ARCHITECTURE/FEATURES；既有 V2 改造记录（04/05/06 号文档）。
**2026-11-17 补充**：用户已提供设计参考原型 `Project_O_UI_V2.1_Linear_全页面可交互原型.html`（项目根目录，75,437 字节），并重申"数据及业务不要改动"。原型全文通读分析见 §4.4；原 D1/D2/D3 未决项据此关闭。
**用户硬约束（2026-11-17）**：材料录入方式逻辑不变（"比较好用"）——现有快速录入的交互与字段逻辑整体保留，原型录入条仅作视觉参考，详见 §4.4 落地约束与 §5 保护点。

---

## 1. 基线状态

| 项 | 值 |
|---|---|
| git | 分支 `main`，HEAD `32cd979`（`git describe` = `v0.01-5-g32cd979`，较 TASK-00 时的 `74b80b3` 前进 5 个提交） |
| 工作树 | 43 个未提交改动（用户在途工作）。沿用既定约定：**不提交、不还原** |
| 运行环境 | WSL Ubuntu-24.04，PHP 8.3.26，`/var/www/html/o`；Apache 文档根指向 `public/`；composer/vendor 就绪 |
| 测试基线 | `composer test` **11/11 全部通过**（本次实测，见 §6） |
| 既有 UI 进度 | V2 改造 TASK-00～TASK-10 已完成（06 号交付记录）：设计令牌浅/深双套、共享导航片段、材料录入折叠、问题台账工具栏、文档首页 KPI/队列、后台设置分组。V2.1 在此基础上做**布局架构级**重构，不推翻 V2 令牌与组件成果 |
| V2 备份 | `storage/ui-v2-backup-20261117/`（13 个原件）。V2.1 开工前需另建 `storage/ui-v21-backup-<日期>/` 逐任务备份 |
| 工具通路 | Windows 端编辑工具不能直写 UNC；采用临时副本编辑 + 写回，验证在 WSL 内执行（已验证可行） |
| 已知坑 | CLI 跑含图片的测试会创建 `/tmp/project-o-image-cache`（属主=CLI 用户），导致 Apache 不可写；本次基线测试后已检查并清理（目录为空） |

## 2. 技术架构与请求链路（实测核实）

```
public/index.php                 唯一 Web 入口
  ├─ Environment::load(.env) → composer autoload → UTC
  ├─ session：project_o_session，HttpOnly/SameSite=Lax/strict mode（index.php:17-24）
  ├─ 安全头：nosniff / X-Frame-Options DENY / Referrer-Policy /
  │   CSP default-src 'self'; style-src 'self'; form-action 'self'（index.php:26-29）
  └─ Application::run()
       ├─ POST：CSRF 全局校验（Application.php:52，失败 419）→ action 分发
       │    login/logout/profile.*/admin.* 由核心处理（:60-93）
       │    其余 action → PluginRegistry::dispatch('POST', action)（:99）
       ├─ GET：?page= 分发，默认 projects（:108）
       │    login 匿名；public_routes（班组分享）按清单免登录（:124）
       │    其余 requireLogin（:134）→ module-asset/profile/admin 核心页
       │    → PluginRegistry::dispatch('GET', page)（:160）
       └─ render()：统一注入 escape/csrfToken/username/canManagePlatform/
            theme/platformName/brandMark（:328-353）→ 视图尾部 require views/layout.php
```

- **插件体系**：4 个业务插件（`project.foundation` 必需、`material.management`、`document.management`、`labor.crew`），路由/权限/迁移/资源/事件全部由 `plugin.json` 清单声明；`PluginRegistry` 校验依赖并分发；停用保留数据。
- **项目上下文**：`src/ProjectContext.php`（`current()`/`requireCurrent()`）→ `PluginContext::currentProject()/requireCurrentProject()`（PluginContext.php:86-94）。项目归属由服务端会话解析，客户端 project_id 不构成授权。
- **跨插件契约**：`ProjectO\Contracts\ProjectSpaceCatalog` 由 project.foundation 发布、document.management 声明使用；事件总线（`project.archived/deleted/restored`、`plugin.disabled/enabled`）驱动班组公开分享页的发布/撤下。
- **权限**：`Support::hasPermission`（Support.php:33）按角色权限判定；CSRF `csrfToken/verifyCsrfToken`（:17/:26）；审计 `Support::audit`（:216）。菜单门控只是 UX，服务端每个路由/action 独立校验。
- **前端**：无框架、无构建步骤。共享 `public/assets/app.css`（989 行）+ `app.js`（1198 行，defer），内容指纹 `?v=`（layout.php:11-21）自动破缓存；插件资源经 `?page=module-asset` 白名单流式输出（Application.php:177-207，ETag+immutable）。
- **主题**：全站开关（System_Settings.theme，light/dark），唯一注入点 `layout.php:54 <body class="theme-…">`；深色=令牌反转（app.css:381 `body.theme-dark`）。平台名称可配置并用于顶栏/登录页/标题。
- **CSP 约束（V21-01 关键）**：`style-src 'self'` 意味着新布局**不得引入内联 style/外部字体/CDN**；图标需用内联 SVG 或字符；侧栏收起状态如需免闪烁预置，需评估 nonce/hash 方案或接受首帧闪烁，不得私自放宽 CSP（属安全机制，改动须先经确认）。

## 3. 页面清单（全部真实路由，逐一核实）

### 3.1 核心页（views/）

| 路由 | 视图（行数） | 权限/门禁 | 说明 |
|---|---|---|---|
| `?page=login` | login.php (20) | 匿名 | 登录表单，CSRF |
| `?page=projects`（默认） | project-foundation/views/projects.php (160) | 登录+view_projects | 项目列表+当前项目 hero+快捷入口+新建/进入/删除归档/恢复 |
| `?page=profile` | profile.php (65) | 登录 | 个人时区+改密码 |
| `?page=admin&section=…` | admin.php (340) | manage_platform | 左分区右内容两列：系统配置/人员/角色权限/插件/运行参数 |
| `?page=module-asset` | 非视图 | 登录 | 插件资源流（白名单+ETag） |
| 错误页 | error.php (16) | — | HttpException/500 统一渲染 |

### 3.2 project.foundation（项目基础）

| 路由 | 视图 | 权限 | 主要操作（POST action） |
|---|---|---|---|
| projects | projects.php | view_projects（create/delete_projects 门控按钮） | project.create / project.select / project.delete / project.restore |
| `?page=spaces`（含 `&archived=1` 回收站） | spaces.php (302) | view_project_spaces；manage_project_spaces 门控写 | space.save / space.delete / space.restore / space.room.map / space.room.unmap |

### 3.3 material.management（材料与商务，9 个业务页）

| 路由 | 视图（行数） | 查看权限 | 主要操作 |
|---|---|---|---|
| materials | materials.php (341) | view_materials | material.category.save/delete、material.save/delete、material.export；连续录入/编码递增/分类跟随/单位沿用（app.js 材料 IIFE） |
| brands | brands.php (25) | view_brands | material.brand.save/delete（**共享目录**） |
| auxiliary-materials | auxiliaries.php (25) | view_materials | material.auxiliary.save/delete（项目隔离） |
| suppliers | suppliers.php (30) | view_suppliers | material.supplier.save/delete（**共享目录**） |
| supplier-materials | supplier-materials.php (37) | view_supplier_materials | material.supplier_material.save/delete（**共享目录**） |
| quotations | quotations.php (79) | view_material_quotations | material.quotation.save/default/clear_default/delete（版本并发+税率快照） |
| tender-list | tenders.php (79) | view_tenders | material.tender.save/delete（项目级、默认 CNY） |
| samples | samples.php (406) | view_material_samples | material.sample.save/archive、sample.image.upload、material.sample.export（三状态体系+私有图片） |
| estimates | estimates.php (260) | view_material_estimates | material.estimate.save/delete/export（combo 搜索下拉+级联+快速录入） |
| sample-image | 图片内联响应 | 同样品查看权限 | 私有对象存储经应用输出（immutable+ETag） |

### 3.4 document.management（文档协作，4 个页）

| 路由 | 视图（行数） | 权限 | 主要操作 |
|---|---|---|---|
| documents | documents.php (146) | view_issues | 首页：KPI 条+最近更新+待处理队列（V2 TASK-05 产物，内存派生无新 SQL） |
| issues | issues.php (457) | view_issues（create/edit/manage_issues 门控） | issue.create/update/transition、followup.create/complete、image.upload/delete、issue.export；位置控件（issue-location.js/css，仅本页加载） |
| rfis | rfis.php (373) | view_issues | rfi.create/update/transition、followup.*、image.*；一主多关联 |
| document-settings | settings.php (102) | manage_platform 或 manage_document_settings | document.settings.save（R2 存储配置，密钥只写） |
| document-image | 图片内联响应 | 同问题查看权限 | 同上 |

### 3.5 labor.crew（施工班组，2 个业务页+公开分享）

| 路由 | 视图（行数） | 权限 | 主要操作 |
|---|---|---|---|
| labor-crews | crews.php (213) | view_labor_crews（edit/archive/share/view_labor_crew_id_numbers 细分） | labor.crew.save/export/archive/restore/share/share_revoke；身份证加密脱敏、排序、分享对话框 |
| labor-crew-types | types.php (37) | view_labor_crew_types | labor.crew_type.save/delete（**跨项目共享工种目录**） |
| labor-crew-share(-image) | share.php (484) 预渲染 | **公开**（清单 public_routes，令牌校验） | 303 → `public/s/<令牌>.html(.png)` 静态直出；不套 layout |

### 3.6 导航现状（V21-01 的直接输入）

- **顶栏**（layout.php:55-71）：品牌→projects、系统设置（manage_platform）、个人设置、用户名、退出。**没有项目切换器、没有全局搜索/通知**。
- **无项目级侧边栏**。模块导航为页内横向按钮条，共 4 套来源：
  1. 材料 `_nav.php`（10 项，权限来自 MaterialManagementPlugin::nav() :1067-1088）；
  2. 班组 `_nav.php`（3 项，LaborCrewPlugin::nav() :462-470）；
  3. 文档 4 个视图各自内联 `$navItems`（documents/issues/rfis/settings，settings 项按 canManageSettings 门控）；
  4. spaces.php:16-20 手写 3 项（项目首页/当前空间/空间回收站）。
  全部经 `views/partials/module_nav.php`（28 行）渲染（spaces 除外），已有当前页高亮+aria-current。
- **项目外页面**（projects/profile/admin/login）无模块导航——V2.1 需保持"项目外不显示项目侧栏"的边界。
- 各页项目名称写在页头文案里（"项目：XXX"），顶栏不显示当前项目。

## 4. V2.1 目标模块 ↔ 真实功能映射（含缺口）

任务书第三节 17 个目标模块逐一核对源码：

| 目标模块 | 真实路由 | 结论 |
|---|---|---|
| 项目工作台 | **不存在独立路由**；projects.php 的当前项目 hero 最接近 | ⚠️ 缺口，见 §4.1 |
| 项目空间 | spaces | ✅ 存在，但是**空间结构台账**（楼栋→楼层→房间），不是文件库，见 §4.2 |
| 材料管理 | materials | ✅ |
| 品牌管理 | brands | ✅（共享目录） |
| 辅材管理 | auxiliary-materials | ✅（项目隔离） |
| 供应商管理 | suppliers | ✅（共享目录） |
| 供应商材料 | supplier-materials | ✅（共享目录） |
| 报价管理 | quotations | ✅ |
| 招标清单 | tender-list | ✅ |
| 样品管理 | samples | ✅ |
| 材料测算 | estimates | ✅ |
| 文档中心 | documents | ⚠️ 存在但是问题/RFI 协作首页，**无文件/目录/版本能力**，见 §4.2 |
| 问题管理 | issues | ✅ |
| RFI 管理 | rfis | ✅ |
| 文档设置 | document-settings | ✅（权限门控） |
| 施工班组 | labor-crews | ✅ |
| 工种管理 | labor-crew-types | ✅（共享目录） |

**结论：17 个目标模块中 15 个有真实路由可直接接入侧栏。原"项目工作台/文件能力"缺口已由用户提供的原型确认处理方向（§4.4）：原型按真实功能设计——项目首页=项目列表+当前项目卡片、项目空间=楼栋/楼层/房间层级、文档管理=问题/RFI 协作首页，并明示"不虚构尚未落地的模块"。即按路径 A 执行：只重构布局，不凭空创建进度/文件功能（任务书第二节红线）。**

### 4.1 项目工作台缺口（V21-02 阻塞项）

任务书要求：项目基本信息/状态、**整体进度、关键节点与计划偏差**、待处理事项、重大问题风险、最近动态、协调事项。
现状数据支持度：
- ✅ 可直接支持：项目名称/状态（planning/ongoing/completed/on-hold）/起止日期；待处理问题与 RFI（documents 首页已有派生逻辑可复用）；最近动态（问题/RFI 更新时间）。
- ❌ 无数据源：施工进度、任务/里程碑/关键节点、计划偏差、协调事项——施工任务/进度模块**尚未开发**（FEATURES §1 列为待建；ARCHITECTURE §1.5 明确总进度算法未确认、不得自设公式）。
- 可选路径（待用户选择）：A. 工作台只展示真实已有数据（信息+状态+问题/RFI 待办与动态），进度区块明确"功能建设中"占位；B. 先立项开发进度数据模型再做完整工作台（超出 UI 重构范围，需单独任务书）。**不得用演示数据填充**（执行纪律 5）。

### 4.2 项目空间 / 文档中心的文件能力缺口（V21-02/04 阻塞项）

- 任务书 B 要求"项目目录树+文件列表+版本+上传"；E 要求"文档分类/列表/版本/关联事项"。
- 现状：spaces=楼栋/楼层/房间空间台账（无文件）；documents=问题/RFI 首页（无文件）；全系统**没有通用文件上传/下载/目录/版本功能**（README 明确"文档文件附件…仍待后续开发"；现有上传仅样品/问题/RFI 图片附件，走私有对象存储）。
- 可选路径（待用户选择）：A. V2.1 内"项目空间"保留空间台账本质、按新布局重构，文件库另立开发任务；B. 把文件库开发纳入 V2.1（涉及新表/新路由/存储/权限，**超出"只改界面"边界**，需单独确认数据模型与权限）。
- 另一处差异：Construction_Areas（施工区域）在服务层与 POST 路由（space.room.map/unmap）存在，但 **spaces.php 视图无任何区域维护界面**（全文无"区域/area"字样）。属"服务有、UI 无"的存量差异，是否在 V2.1 补 UI 需业务确认，不默认新增。

### 4.3 其他映射说明

- 全局搜索/通知：任务书标注"如现有能力支持"。现状无跨模块搜索与通知机制；页内搜索已有（材料 applyFilter、通用 data-list-search/data-list-filter）。**V21-01 顶栏不放假入口**；如需全局搜索属新功能，另行确认。
- 品牌/供应商/供应商材料/工种为**跨项目共享目录**，但现行导航把它们混在项目模块条里。V2.1 侧栏分组时必须保留其共享语义（建议"共享目录"分组），不得改成项目隔离。
- 公开分享页（/s/*.html）独立于 layout，V2.1 布局重构**不触碰**该模板与其发布机制。

### 4.4 原型分析：Project_O_UI_V2.1_Linear_全页面可交互原型.html（2026-11-17 收到并通读）

单文件 304 行 / 75,437 字节，22 个演示页（showPage 切换），内联 CSS/JS，无外部依赖。原型自带 note 明示"演示数据、真实字段/校验/提交沿用源码契约、不虚构未落地模块"，与盘点结论一致。

**设计语言（Linear 风格，浅色）**

| 项 | 原型值 | 与任务书第五节对照 |
|---|---|---|
| 主色 | `#5267d8`（靛蓝/periwinkle），logo 渐变 `#6479ed→#3d50c3` | 任务书建议"工程深蓝/深海军蓝导航"；**原型为浅色侧栏 `#fbfcfd`+靛蓝选中态**，非深色导航。以用户提供的原型为准（任务书称其为设计参考），V21-01 按原型落地 |
| 背景/面板 | 页面 `#f7f8fa`、面板 `#fff`、次级 `#fbfcfd` | 一致（浅灰白+白卡片） |
| 边框/圆角 | `#e8ebef`/`#d9dee6`，圆角 10px（按钮 7px） | 一致（6–10px） |
| 顶栏 | 58px，白底 sticky | 任务书 60–64px，差 2–6px；按原型 58px 或微调至 60px，V21-01 定稿 |
| 侧栏 | 248px（≤1050px 时 218px），三分组+底部设置区 | 一致（230–250px） |
| 正文/表格 | 正文 14px、表格 12px、表头 10px 大写风格、行高 49px | 一致（13–14px、高信息密度） |
| 状态色 | 绿 `#16845b`/琥珀 `#a66b12`/红 `#c24c4c`/蓝 `#3869bb`/紫 `#7956bd`（各配浅底） | 一致（红橙绿蓝体系，另加紫用于工种等分类标签） |
| 字体 | Inter,-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei" | 本机字体栈，无外部字体请求，**兼容现有 CSP** |
| 响应式 | ≤1050px 侧栏收窄/统计 2 列/双栏变单栏；≤760px 侧栏变抽屉（left:-260px→0）+☰ 按钮、分类栏横滑、表格局部滚动 | 覆盖桌面/平板/窄屏三档 |

**布局结构**

- 顶栏：☰（窄屏）｜logo+平台名｜分隔线｜**项目切换器**（绿点+项目名+⌄）｜弹性空隙｜**全局搜索框**｜帮助?｜通知♧｜设置⚙｜头像。
- 侧栏三分组：`PROJECT WORKSPACE`（项目首页/项目空间）；`MATERIALS & COMMERCIAL`（材料/辅材/品牌/供应商/供应商材料/报价/招标/测算/样品，9 项）；`DOCUMENTS & SITE`（文档/问题[计数]/RFI[计数]/班组/工种）；底部：系统设置/个人设置（+原型自带的登录预览，仅演示用）。
- 内容区：面包屑（工作区/页名）→ page-head（h1 23px+副标题+右侧操作按钮）→ stats 统计卡（4 列，适用页）→ panel（panel-head+toolbar+table）→ note 说明条。
- 组件：btn（primary/ghost/danger/small）、panel、toolbar（search+select+计数+清空）、table（primary-cell+secondary-cell 双行单元格）、badge（dot 变体）、tabs、form-grid/field、modal（650px）、toast、empty、note、project-card、activity/task-row、kv 键值对、progress/timeline（**仅演示，无数据源，不落地**）。

**逐页映射与差异（原型 → 真实实现）**

| 原型页 | 真实路由 | 差异/落地约束 |
|---|---|---|
| 项目首页 | projects | 原型=项目卡片列表+搜索/状态筛选+当前项目快捷入口，与现状一致；保留创建/进入/删除归档/恢复全部 POST 契约 |
| 项目空间 | spaces | 原型=楼栋→楼层→房间层级+右侧编辑面板；现状为逐楼栋卡片+弹窗编辑。**保留现有分类型维护与归档策略**，布局向原型层级化靠拢；原型右侧"空间编辑"混合表单仅示意，不合并真实分类型表单契约 |
| 材料管理 | materials | 分类栏+快速录入+工具栏+表格，与现状结构一致。**用户硬约束：录入交互逻辑原样保留**（见下） |
| 辅材/品牌/供应商/供应商材料 | auxiliaries/brands/suppliers/supplier-materials | 简单台账页，原型字段与真实表单一致；共享目录语义保留（原型 note 已标注"共享目录·非项目独占"） |
| 材料报价 | quotations | 原型有"报价对比/维护/录入"三 tab；现状单页。版本/税率快照/默认报价字段原型均保留展示；**不定义新规则**，tab 化仅组织现有表单，V21-03 细化 |
| 招标清单 | tender-list | 原型 stats+参考报价列；"参考报价"若需联查报价属新增只读派生，V21-03 先核实现有服务是否已提供，无则不显示，不新增 SQL |
| 材料测算 | estimates | 原型快速录入+按空间/按分类 tab；现状 combo 搜索下拉+级联+快速录入已具备，保留全部交互 |
| 样品跟踪 | samples | 原型 stats+三状态筛选+图片列，与现状一致；导出/图片路由不动 |
| 文档管理 | documents | 原型=统计卡+最近更新+常用入口；现状 KPI+最近更新+待处理（V2 产物）。融合两者：保留待处理队列，视觉按原型 |
| 问题管理 | issues | 原型 stats 四态计数+状态/严重程度/**位置**筛选；现状已有搜索/状态/严重程度工具栏。**位置筛选仍按 V2 结论跳过**（行数据无稳定位置值）；详情弹窗字段（处理结果/现场验证/证据引用）与真实契约一致 |
| RFI 管理 | rfis | 原型"台账/待我处理"tab+优先级筛选；主问题/其他关联/截止时间/答复字段与契约一致。"待我处理"需按负责人过滤，现有数据支持则做，否则仅台账 |
| 施工班组/工种 | labor-crews/labor-crew-types | 原型字段与真实一致；脱敏/分享/归档/排序规则 note 已复述，零变更 |
| 系统设置 | admin | 原型左导航五分区与现状两列布局一致，仅视觉升级 |
| 个人设置/图片存储设置 | profile/document-settings | 字段一致（时区/改密码；R2 密钥只写），零变更 |
| 公开分享页 | /s/*.html | 原型含演示版，但**真实分享页不重构**（预渲染静态+事件发布机制不动） |
| 错误页/登录页 | error/login | 视觉参考，契约不变 |

**原型中不落地/需适配的演示元素**

1. **全局搜索框、帮助?、通知♧**：系统无对应能力（任务书"如现有能力支持"）。默认不放无功能假入口；顶栏保留项目切换器+账户区。如用户坚持保留视觉占位，需明示"建设中"，待确认。
2. **侧栏问题/RFI 计数徽标**：真实数据可派生（documents 首页已有计数逻辑），可做；仅权限内显示，不新增查询负担（随导航渲染一次性统计或省略，V21-01 定）。
3. **progress/timeline/kanban 占位**：无进度数据源，不落地（D2 已关闭）。
4. **深色主题**：原型仅浅色。现有系统主题为全站开关且当前为深色，**必须保留深色支持**——按 V2 令牌反转机制为原型色板补深色映射，V21-01 同步交付，不得删主题功能。
5. **内联样式/onclick**：原型大量内联 style 与 onclick，真实系统 CSP `style-src 'self'` 且脚本外置——落地时全部转为 app.css 类与 app.js 事件委托，不放宽 CSP。
6. 原型"登录页预览"侧栏项为演示专用，真实侧栏不含。

**材料录入硬约束（用户 2026-11-17 明确："材料录入方式逻辑不变，因为比较好用"）**

以下现有行为在 V2.1 中**逐项原样保留**，原型 quick-add 条仅作视觉参考，不得据其简化：
- 快速录入为可折叠面板（details+localStorage 开合记忆），「新增材料」按钮展开并聚焦名称框；
- 材料编码**可选**、未填时分类内尾号自动递增（nextCodeFor），「沿用分类内尾号」一键建议；
- 分类跟随左侧所选（可开关）、保存后分类/单位沿用，只清空编码/名称/规格/说明；
- 回车即存连续录入、Ctrl+Enter 编辑保存、本次录入计数与 chips、单位 datalist 沿用项目已有单位；
- 字段 name（material_code/material_name/unit/category_id/specifications/material_description_detailed）、action=material.save、CSRF、async 局部刷新与编辑弹窗反馈全部不变；
- 原型 quick-add 未含编码输入框与说明字段，**不得据此删减真实字段**。

## 5. 业务保护规则 → 源码落点（重构中零变更）

| # | 任务书保护规则 | 源码证据（本次核实） |
|---|---|---|
| 1 | RFI 必须关联一个主问题、可关联多个其他问题 | 迁移 202611010002:87 `primary_issue_id NOT NULL` + 复合外键 (primary_issue_id, project_id)（:111）；DocumentService.php:698 创建校验、:262-273 联查 |
| 2 | RFI 答复不自动改问题状态 | DocumentService::transitionRfi（:893）独立更新 RFI；问题状态仅 transitionIssue（:463）变更；测试覆盖 |
| 3 | 问题/RFI 独立状态体系 | ISSUES: open→in_review→resolved→closed；RFI_STATUSES（:16）draft→submitted→under_review→responded→closed，转换表 :28-29 |
| 4 | 项目归属范围 | ProjectContext + PluginContext::requireCurrentProject；各服务 WHERE project_id；复合外键 |
| 5 | 服务端权限校验 | Support::hasPermission（:33）；各插件 handler 逐路由校验；nav() 仅 UX 门控 |
| 6 | CSRF/POST action/安全机制 | Application.php:52 全局 CSRF；index.php:17-29 会话+安全头+CSP；isSafeRedirect 防开放跳转（:258） |
| 7 | 导出字段与范围 | material.export/sample.export/estimate.export/issue.export/labor.crew.export 各 handler + XlsxWorkbook；文件名/列/统计口径见 FEATURES §1.4/1.4.1 |
| 8 | 连续录入/编码递增/分类跟随/单位沿用（**用户明确：录入方式逻辑整体不变**） | materials.php:53-109 录入面板；app.js 材料 IIFE（entryForm :20、nextCodeFor :94、localStorage 记忆 :45-56、折叠记忆 :453-464）；逐项清单见 §4.4 硬约束 |
| 9 | 报价版本/并发/币种税率快照 | MaterialCatalogService.php:556 税率百分数校验、:565-568 INSERT version=1、:603 UPDATE version=version+1（乐观并发）；币种原样保存不换算 |
| 10 | 项目材料/辅材/报价/样品/测算不跨项目 | 迁移 202611050001（project_id 范围+复合键）；样品显式 project_id；测算经楼栋父级归属 |
| 11 | 品牌/供应商/供应商材料共享机制 | 三表无 project_id；受保护删除（被引用拒删）；nav 门控仅 view_* 权限 |

补充保护点（盘点中发现、同等对待）：
- 问题/RFI 不硬删除；关闭前置条件（处理结果/现场验证/证据/后续事项）；编号规则 `P<id>-ISS/RFI-<seq>`；状态历史审计。
- 样品三状态体系与"技术样品可独立于报价"；图片私有存储+应用路由输出+immutable 缓存语义（对象键不可变）。
- 班组身份证 AES-256-GCM+HMAC、脱敏与独立查看权限、审计不含密文；分享令牌机制与静态发布/事件联动；`public/s/` 目录安全头。
- 招标默认币种：FEATURES §1.4 写"新记录默认 ETB"，但迁移 202611050002 已 `SET DEFAULT 'CNY'`，README 亦为 CNY——**文档滞后于代码，以迁移/运行行为为准**，已记差异（§8）。
- 时区：UTC 存储、平台默认+个人覆盖；导出文件名/时间按生效时区。
- 资源指纹 `?v=` 与 module-asset ETag：改 app.css/app.js 自动换指纹，**不得移除**（微信内浏览器缓存错配曾致复制 undefined 事故）。

## 6. 测试基线（本次实测）

命令：`wsl.exe -d Ubuntu-24.04 --cd /var/www/html/o -- sh -c 'composer test'`（2026-11-17 执行）

| 测试 | 结果 |
|---|---|
| auth_service_test | ✅ Authentication service tests passed. |
| project_foundation_plugin_test | ✅ Project foundation plugin tests passed. |
| project_service_test | ✅ Project service tests passed. |
| project_space_service_test | ✅ Project space service tests passed. |
| document_management_test | ✅ Document management tests passed.（含导航标记断言，改导航须保兼容或同步更新断言并说明） |
| material_management_test | ✅ Material management tests passed.（需 ext-zip） |
| platform_administration_test | ✅ Platform administration tests passed. |
| profile_service_test | ✅ Profile service tests passed. |
| labor_crew_test | ✅ labor crew tests passed |
| login_page_smoke_test | ✅ Login page smoke test passed. |
| projects_page_smoke_test | ✅ Projects page smoke test passed. |

测试为独立原生 PHP 脚本+SQLite 内存库，不依赖 .env/MySQL。测试后已清理 `/tmp/project-o-image-cache`（本次为空目录）。
**V2.1 每阶段回归口径**：composer test 全绿 + 改动文件 PHP lint + `node --check app.js` + CSS 括号/var() 校验 + 浏览器实测截图。现有测试**不含**侧栏/布局断言，V21-01 需为 layout 增加 smoke 断言（顶栏/侧栏/项目外语义）。

## 7. 待重构文件清单（按阶段，预估范围）

| 阶段 | 文件 | 动作 |
|---|---|---|
| V21-01 | views/layout.php | **核心重构**：顶部全局导航（品牌/项目切换器/账户/管理入口）+ 项目级侧栏容器 + 统一页面容器；保留指纹/CSP/主题注入/flash |
| V21-01 | views/partials/module_nav.php → 演进为侧栏渲染（或新增 partials/sidebar.php + 保留兼容） | 侧栏分组/选中态/收起展开；权限门控仍由插件声明 |
| V21-01 | 新增聚合机制：PluginRegistry/PluginContext 或核心导航收集器 | 侧栏需跨插件菜单：由清单/注册 API 声明汇总，**不得**在核心硬编码业务菜单、不得让插件互读 |
| V21-01 | public/assets/app.css | 新布局令牌（顶栏 60-64px/侧栏 230-250px/工程深蓝/深海军蓝）+ 布局组件；沿用 V2 语义令牌与深色反转机制 |
| V21-01 | public/assets/app.js | 侧栏收起/展开（localStorage 记忆）、移动端抽屉；不破坏材料 IIFE/combo/列表筛选 |
| V21-01 | views/login.php / error.php / profile.php / admin.php | 接入新容器；login/error 无侧栏；admin 保持自身两列分区 |
| V21-02 | projects.php（+ProjectFoundationPlugin 视图数据） | 项目列表重构+项目切换联动；工作台视 §4.1 决策 |
| V21-02 | spaces.php | 新布局接入；文件能力视 §4.2 决策；回收站语义保留 |
| V21-03 | materials/brands/auxiliaries/suppliers/supplier-materials/quotations/tenders/samples/estimates 9 视图 + _nav.php | 逐页接入统一工作区；连续录入/combo/导出/权限门控零变更 |
| V21-04 | documents/issues/rfis/settings 4 视图 | 同上；位置控件资源加载关系保持 |
| V21-05 | crews/types 2 视图 + _nav.php | 同上；分享对话框/导出不动 |
| V21-06 | app.css（响应式断点统一）+ 各视图微调 | 桌面/平板/窄屏验收 |
| V21-07 | 测试补充 + 截图 + 交付报告 | 全站回归 |
| 不触碰 | share.php、public/s/*、全部 src 服务层业务规则、全部迁移、导出字段、plugin.json 路由/权限语义 | 业务保护红线 |

## 8. 差异与未决事项（须用户/业务确认，不猜测实施）

| # | 事项 | 状态/结论 |
|---|---|---|
| D1 | V2.1 HTML 参考模板 | ✅ **已关闭**：用户 2026-11-17 提供 `Project_O_UI_V2.1_Linear_全页面可交互原型.html`，分析见 §4.4。视觉以原型为准（浅色 Linear 风+靛蓝主色），与任务书"深蓝"建议的差异已记录，按原型执行 |
| D2 | 项目工作台进度数据缺口 | ✅ **已关闭**：原型"项目首页"即项目列表+当前项目卡片，无进度/节点演示，且 note 明示不虚构未落地模块。按真实数据重构，不新增进度功能 |
| D3 | 项目空间/文档中心文件能力 | ✅ **已关闭**：原型项目空间=楼栋/楼层/房间层级、文档管理=问题/RFI 协作首页，与现状一致。文件库不在 V2.1 范围 |
| D4 | 施工区域服务层有、UI 无 | 维持默认不补（原型亦无区域界面）；如需补请另行确认 |
| D5 | FEATURES 招标默认币种 ETB 滞后于迁移 CNY | 以迁移/代码 CNY 为准；建议后续修订 FEATURES（本阶段不改） |
| D6 | 侧栏跨插件菜单聚合方式 | 实现细节，按 ARCHITECTURE"菜单由清单/注册 API 声明"原则设计，V21-01 方案中说明 |
| D7 | 顶栏项目切换器 | 复用 project.select 原 action+CSRF，不新增业务语义；原型切换器为演示 toast，落地为真实项目下拉/跳转 |
| D8 | 浅色回归、五档视口、无 JS 验证（V2 遗留） | 纳入 V21-06/07 补齐 |
| D9 | 浏览器自动化通道登录态问题 | 阶段截图优先真实浏览器采集；登录态由用户协助 |
| D10 | **新增**：顶栏全局搜索/帮助/通知为原型演示元素，系统无对应能力 | 默认不放无功能假入口（任务书"如现有能力支持"）；如需保留占位请确认 |
| D11 | **新增**：原型仅浅色，现系统主题为全站开关且当前深色 | 深色主题必须保留：按 V2 令牌反转机制补原型色板深色映射，V21-01 同步交付 |
| D12 | **新增（用户硬约束）**：材料录入方式逻辑不变 | 已升级为保护规则（§4.4/§5 第 8 条）：折叠面板/编码可选递增/分类跟随/单位沿用/回车连录/字段与 action 全部原样保留，原型录入条仅视觉参考 |

## 9. V21-01 实施计划（下一阶段，待本报告验收后启动）

1. 建 `storage/ui-v21-backup-<日期>/`，备份将改动的原件（layout/app.css/app.js/module_nav 等）。
2. 侧栏信息架构**按原型三分组**落地：PROJECT WORKSPACE（项目首页/项目空间）｜MATERIALS & COMMERCIAL（材料/辅材/品牌/供应商/供应商材料/报价/招标/测算/样品）｜DOCUMENTS & SITE（文档/问题/RFI/班组/工种）+ 底部（系统设置[manage_platform]/个人设置）。权限门控沿用插件 nav() 声明；共享目录语义以页内标识保留（原型 note 口径）；问题/RFI 计数徽标视实现成本决定；不新增入口。
3. 核心增加"导航声明聚合"公开接口（插件清单/注册 API 声明菜单，核心只汇总渲染），项目外页面（projects/profile/admin/login/error）不渲染项目侧栏。
4. 重构 layout.php：顶栏（品牌/项目切换器[复用 project.select+CSRF]/账户/管理/退出；不放无功能的全局搜索/通知假入口，D10）+ 侧栏（选中态/收起展开/窄屏抽屉）+ 统一页面容器（面包屑/标题/副标题/操作/统计/工具栏/内容槽位，不强求同构）。
5. app.css 以**原型色板**建立 V2.1 令牌（主色 #5267d8/背景 #f7f8fa/边框 #e8ebef/圆角 10px/顶栏 58px/侧栏 248px/状态五色），沿用 V2 语义令牌与深色反转机制补深色映射（D11）；全部样式外置，不违反 CSP；app.js 侧栏交互（localStorage 记忆+窄屏抽屉），不破坏材料 IIFE/combo/列表筛选。
6. **材料录入逻辑零改动**（D12 硬约束）：materials.php 录入面板与 app.js 材料 IIFE 的行为逐项保留，仅外层布局/视觉接入新体系。
7. 登录/项目列表/项目内页/后台/个人设置逐一验证；新增 layout smoke 断言；composer test 全绿+lint+浏览器实测（深浅双主题）。
8. 按任务书第八节提交阶段交付物（文件清单/差异/截图/测试/保护检查/未决/下阶段计划）。

---

## 附：本阶段交付物对照（任务书第八节）

1. 修改文件清单：仅新增本报告（07 号文件）；业务代码零修改。
2. 修改内容：不适用（只读阶段）。
3. 结构差异：不适用；现状结构见 §2/§3.6。
4. 运行截图：本阶段未采集（只读盘点，沿用 V2 交付记录的实测结论）；基线截图在 V21-01 改动前采集。
5. 核心操作测试：composer test 11/11（§6）。
6. 业务保护检查：11 条规则源码落点逐一核实（§5）。
7. 未决问题：D1–D3 已关闭（原型确认），D4–D12 见 §8；其中 D12 为用户硬约束（材料录入逻辑不变）。
8. 下一阶段计划：§9。

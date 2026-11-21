# Project O BOM 与成本 V3.0 — 落地审核报告（27 号）

> 日期：2026-11-20。依据：`24_Project_O_BOM与成本_V3.0精简设计.md`、`25_Project_O_BOM与成本_规则总纲.md`、`26_Project_O_BOM成本_开发团队与落地计划.md`。
> 范围：把已定稿的 V3.0 结构/机制落地为新插件 `bom.costing`，并在测试环境审核可用。**数值金额公式在税率/抵扣/舍入定案前不实现**（总纲 §五 闸门）。
> 结论先行：结构、取价、组价/重算、成本价维护、费用分摊、视图与测试链路在专用测试库与内存 SQLite 底座上全部通过；生产库 `project_o` 已补跑本插件两条迁移（见 §3.1）。**数值金额公式在税率/抵扣/舍入定案前不实现**（总纲 §五 闸门）。

## 1. 交付物清单

### 1.1 插件骨架（`modules/bom-costing/`）
- `plugin.json`：插件 ID `bom.costing`；依赖 `platform.core` / `project.foundation` / `material.management`；18 条路由（5 GET 页面 + 13 POST 动作）；6 个权限；迁移目录登记。
- 命名空间 `ProjectO\Modules\BomCosting\`，已在 `composer.json` PSR-4 登记 `modules/bom-costing/src/`，`composer dump-autoload` 已刷新。

### 1.2 领域服务（`src/`）
| 文件 | 职责 |
|---|---|
| `PriceResolver.php` | 取价四级：默认有效报价 → 最新有效报价（同日 quotation_id 最大）→ 项目成本价 → 缺价（禁零、禁伪装完整）；材料/辅材/目录三入口 |
| `CostingService.php` | 组价头/资源行 CRUD；一父一份 `ensureCosting`；人工结构修改事务内 version+1；类型与目标匹配校验；缺项派生；确认动作 |
| `RecalcEngine.php` | 项目级串行重算；version 乐观校验；组件实时读 `Material_Compositions`；结果行随组成清理；`calc_status` 三态派生；MySQL/SQLite 方言条件化 |
| `ActualCostService.php` | 实际费用 normal/reversal/adjustment；冲销五规则；调整归属继承；净额校验；分摊锁；三级统计恒等式；方言条件化 |
| `ResourceCatalogService.php` | 加工/机械/人工合一目录；同类型重名校验；分组选项；引用受 RESTRICT 保护删除 |
| `CostPriceService.php` | 项目成本价（⑥）：辅材/目录项类型化目标；同项目同对象一条当前行 upsert；单价>0、计价单位必填、有效期校验；跨项目 404；引用受 RESTRICT 保护删除 |
| `BomCostingPlugin.php` | 接线 18 handler；权限门控；侧边栏四项；审计动作名；重定向；详情缺项视图模型装配；进入详情自动补算 |

### 1.3 迁移（`database/migrations/`）
- `202611200001_bom_costing_core.sql`：8 张新建表 + 组成表复合唯一索引（开头，供结果表复合 FK）。
- `202611200002_extend_existing_tables.sql`：报价有效性/税口径/计价单位扩展；审计项目归属扩展；列/索引存在性判断，可重复安全。

### 1.4 视图（`views/`）
- `costings.php` 组价列表（含新建组价选择父材料入口）；`costing-detail.php` 组价详情（组件实时来源、资源行、合并明细：已计价/缺价原因、对象名称单位）；`cost-prices.php` 项目成本价（辅材/目录项两段定价与删除）；`catalog.php` 资源目录；`actual-costs.php` 实际费用（登记/冲销/调整/分摊 + 来源标记）；`_nav.php` 复用共享 `module_nav.php` partial。
- 五个视图均以 `$content = ob_get_clean(); require layout.php;` 收尾，套入全局顶栏/侧边栏外壳（本轮修复，见 §3.3）。

### 1.5 测试（`tests/`）
- `bom_costing_test.php`：内存 SQLite 底座，服务层用例（取价四级、C1/C3/C4/C5、目录 CRUD、成本价服务、缺项派生与可选材料清单、项目隔离）。
- `bom_costing_web_smoke_test.php`：经真实 `Application` 路由渲染四个 GET 页面，并断言全局布局外壳存在。
- 两者均已登记进 `composer.json` 的 `scripts.test`。

## 2. 审核结论（测试环境实测）

### 2.1 数据库（专用测试库 `project_o_test`，结构基线从生产 `mysqldump --no-data` 灌入，不碰生产数据）
- 8 张新表全部建成；CHECK 约束计数与设计一致：`actual_cost_allocations` 1、`actual_cost_records` 3、`bom_costing_resources` 3、`cost_line_results` 3、`cost_settings` 1、`project_cost_prices` 2。
- 复合项目 FK + RESTRICT；组成表 `UNIQUE(project_id, composition_id)` 与既有 `UNIQUE(project_id, parent_material_id, child_material_id)` 并存；结果表复合 FK 引用到位。
- 6 条负向用例全部被数据库拒绝：成本价双目标、成本价零价、资源行无目标、费用类别对象错配、other 挂材料、分摊负额；正常成本价写入放行。

### 2.2 服务层（`bom_costing_test.php`，全绿）
- **取价四级**：默认报价命中；无默认回退最新有效（同日 id 最大）；无报价判缺价且单价为 null；辅材/目录走项目成本价。
- **C5 组成实时引用**：边增→生成结果行；改系数→重算反映；边删→结果行清理；组价与样品共用同一组成来源。
- **C1 version 乐观锁**：重算不递增 version；人工结构修改 version+1；重算不覆盖人工资源行。
- **C3 分摊超额**：累计分摊超净额拒绝；已分摊/未分摊恒等式成立。
- **C4 冲销/调整联动**：累计冲销超原额拒绝；负向调整致净额不足覆盖分摊拒绝；正向调整成功；冲销不可再被冲销；每步恒等式成立。
- **目录 CRUD / 资源行删除 / 确认**：新增、重名拒绝、更新反映；删行后清单为空且 version+1；draft→confirmed，重复确认拒绝。
- **项目成本价服务**：辅材/目录项保存并带出名称；同对象重复保存 upsert 同一行；双目标/无目标/零价拒绝；跨项目 404；未引用行可删除。
- **缺项派生 / 可选材料清单**：缺价行不落结果表，缺项数按当前组件+资源与已计价结果差集派生；已建组价的父材料不再出现在可选清单。
- **项目隔离**：跨项目读组价 404；跨项目引用材料 404；跨项目辅材定价 404。

### 2.3 Web 入口（`bom_costing_web_smoke_test.php`，全绿）
- 经真实 `Application` 路由，已登录授权会话下渲染 `costings` / `cost-prices` / `cost-catalog` / `actual-costs` 四页，标题与导航正常，无 500/403/404；冒烟断言同时校验全局布局外壳（`app-sidebar` + `<html>`）存在，裸片段不再视为通过。
- 注册表冒烟：`bom.costing` 被自动发现；6 个权限注册；侧边栏 4 个页面（group=quotes）聚合成功，无重复路由/权限冲突。

### 2.4 既有回归（`composer test`，全绿）
- 全部既有测试通过：认证、项目基础/服务/空间、文档管理、材料管理、平台管理、个人资料、班组、BOM 成本（服务层 + Web 冒烟）、登录页、项目页、布局 V2.1。既有功能无回归。
- PHP `-l` 语法检查：插件 13 个 PHP 文件（7 服务/主类 + 5 视图）无语法错误。

## 3. 闸门状态与未落地项

### 3.1 生产库落库记录（2026-11-21）
- 现象：项目侧边栏看不到组价页面。定位为两个叠加原因，均已修复并复测：
  1. **侧边栏分组未注册**：新插件菜单原挂在自定义分组 `costing`，而核心 `views/layout.php` 的 `$sidebarGroups` 只识别 6 个既有分组（workspace/suppliers/materials/quotes/collab/construction），未登记分组被整组跳过。已改为挂到既有 `quotes`（报价与招标）分组，三页：BOM 组价 / 资源目录 / 实际费用。
  2. **生产库缺表**：生产 `project_o` 此前未跑本插件迁移，8 张 BOM 表与报价/审计扩展列全部缺失，进入页面抛 500（`Table 'project_o.bom_costings' doesn't exist`）。
- 处置：先做逻辑备份（`storage/bom-costing-test/backup_project_o_20261121_003606.sql`，46 表结构+数据），再经 `php bin/install.php` 补跑两条插件迁移；已登记台账 `bom.costing/202611200001_bom_costing_core.sql`、`bom.costing/202611200002_extend_existing_tables.sql`。既有 21 条迁移跳过，权限 `INSERT IGNORE` 未改动既有授权，用户未改动。
- 复测：8 表 + 6 扩展列到位；以管理员真实会话渲染 `costings` / `cost-catalog` / `actual-costs` 三页，均为正常空态、无 500/403/404；全量 14 项测试回归通过。
- 备注：管理员角色（role 1）已持有 6 个 BOM 权限；manager 角色（role 3）未授予，如需可见由平台管理侧在角色权限中勾选。

### 3.3 BOM 组价链路补全记录（2026-11-21 第二轮）
针对"组价页面看不到、链路不可用"的复查，定位并修复六项结构/入口缺口（均在已定稿设计范围内，不触碰税率/抵扣/舍入闸门）：

1. **全局布局外壳缺失（P0）**：五个 BOM 视图此前只 `ob_start()`，未收尾进 `layout.php`，浏览器侧无顶栏/侧边栏/HTML 外壳（实测 HTML 无 `<html>`/`app-sidebar`）。已为五个视图补 `ob_get_clean()` + `require layout.php`；冒烟测试新增布局外壳断言防回归。
2. **组价无创建入口（P0）**：`ensureCosting` 此前无路由/表单可达。新增 POST `costing.create`：选父材料建组价 → 自动补算一次 → 跳详情；列表页提供可选父材料清单（已建组价者排除）。
3. **项目成本价无维护入口（P0）**：新增 `CostPriceService` 与 GET `cost-prices` 页、POST `cost.price.save` / `cost.price.delete`；辅材与加工/机械/人工目录项在项目内定价，同对象一条当前行 upsert；取价四级第③级读取链路此前已就位，本轮补齐写入侧。材料级成本价开关仍待决策，不在本页维护。
4. **缺项不可见、结果行无名称（P1）**：缺价行不落结果表，旧缺项计数恒为 0；改为按当前组件+资源与已计价结果差集派生缺项数；详情装配合并明细视图模型，缺价行带出取价原因，已计价行带出材料/辅材/目录名称与单位。
5. **进入详情不自动补算（P2）**：详情打开时按当前有效输入 best-effort 补算一次；并发冲突/锁占用不阻断页面渲染，保留当前态，可手动重算。
6. **实际费用冲销/调整入口缺失（P1）**：服务端规则此前已强制，页面仅有登记与分摊表单；本轮补齐冲销、调整两张表单，并在记录列表展示来源标记（手工录入/采购订单/其他）。

- 本轮**无数据库结构变更**：`project_cost_prices` 等 8 表与扩展列在 §3.1 已落生产库；新代码仅复用既有列。
- 生产复测：项目 1 / 2 下四个 GET 页面标题、布局外壳均正常，无 500/403/404；侧边栏 quotes 分组含 BOM 组价 / 成本价 / 资源目录 / 实际费用四项。

### 3.2 待决策业务项（代码已留接口/默认，未擅自定案）
- 具体税率数值、可抵扣规则、舍入路径；A4 报价状态机；A7 calculable 用途；N7 多行例外；B17 费率继承。
- 材料级"允许项目成本价"开关形态（`PriceResolver::findMaterialCostPrice` 当前返回 null）；调整链/比例自动分摊/同步调减；confirmed 变化显著标记/通知。
- `cost_settings` 费率配置录入界面与人工确认价（行级）入口：表结构与结果字段已就位，因费率/金额公式受 §五 闸门阻塞，本轮未做界面，待公式阶段一并落地。
- 实际费用由订单/收货单据自动归集：首期仍为人工录入（总纲 §七【已确认】）；订单实体在新系统尚未实现，归集范围待决策，未开工。
- 后置不建：`exchange_rates` / `units` / `unit_conversions` / `item_pack_specs`。
- 作废机制禁止复活：快照、多版本、四字段并发协议、组件副本表、资源行快照字段。

## 4. 未覆盖风险与后续建议

1. **数值公式未实现**：结果表费率/金额列已就位但留空（`rounding_spec_version='pending'`）；税率/抵扣/舍入定案后再填充计算，当前不描述为已可计价出金额。
2. **浏览器视觉走查未做**：视图 CSS 类沿用既有材料模块样式；Web 冒烟只断言页面可渲染与标题/导航，未做像素级或交互表单提交走查。
3. **POST 动作未走浏览器端到端**：服务层已覆盖规则与拒绝路径；表单 CSRF/重定向/flash 的真实浏览器提交链路（新建组价、保存/删除成本价、新增资源行、登记/冲销/调整费用、分摊等）建议补一轮页面级走查。
4. **并发仅在服务层/数据库约束层验证**：MySQL `GET_LOCK` + `FOR UPDATE` + version 条件更新在真实多 worker 下的竞争表现，建议在预发环境压测 C2（同项目两事件并发）。
5. **审计归属字段**：核心 `Support::audit()` 当前只写 user_id/action/details；扩展迁移补的 `project_id/entity_type/entity_id` 尚未由核心审计方法填充，属既有核心行为，本轮未改动。
6. **既有 xlsx 导出测试偶发**：`material_management_test.php` 一次运行中测算导出归一化断言曾失败，单独重跑与全量复测均通过；该用例走临时文件 + ZipArchive 读取，疑似环境级临时文件/zip 读取偶发，与本插件改动无关，建议后续观察是否稳定复现。

## 5. 验收红线核对

| 红线 | 状态 |
|---|---|
| 项目隔离数据库强制 | 已落实：复合项目 FK + 服务端 404 校验，跨项目用例通过 |
| 缺价禁零 | 已落实：缺价 `unit_price=null`，结果表 `unit_price>0` CHECK |
| 类型化真实外键 | 已落实：三类型化可空 FK + 恰一/类型匹配 CHECK |
| 审计可解释"1200 为何变 1350" | 部分落实：动作级审计已接线；金额前后对比/触发原因明细待公式阶段补 |
| confirmed × calc_status 双态展示 | 已落实：头表双态分离，详情页双 badge 展示 |
| 未实现不描述为已实现 | 遵守：本报告与代码均标注公式/待决策项未落地 |

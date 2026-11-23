# Project O — 导航信息架构调整交付记录（七大业务区）

日期：2026-11-23。依据：用户本轮导航结构调整指令 + 三项确认（施工模块单设一区；材料预算沿用「材料测算」名称；无独立页面的条目暂不上侧栏）。
范围：仅侧栏信息架构与分组渲染，不改任何路由、权限校验、表单契约、SQL/迁移与页内内容。

## 1. 最终信息架构（侧栏自上而下）

| 序号 | 一级导航 | 侧栏条目（按显示顺序） | 权限门控来源 |
|---|---|---|---|
| 01 | 项目管理 | 项目首页 `?page=projects`、项目空间 `?page=spaces`、招标清单 `?page=tender-list` | view_projects / view_project_spaces / view_tenders |
| 02 | 材料管理 | 材料管理 `?page=materials`、辅材目录 `?page=auxiliary-materials`、样品跟踪 `?page=samples` | view_materials / view_material_samples |
| 03 | 供应链管理 | 供应商管理 `?page=suppliers`、品牌管理 `?page=brands`、供应商材料 `?page=supplier-materials`、供应商报价 `?page=quotations` | view_suppliers / view_brands / view_supplier_materials / view_material_quotations |
| 04 | BOM 与成本管理 | BOM 组价 `?page=costings`、子材料数量汇总 `?page=demand-summary`、材料测算 `?page=estimates`、成本价 `?page=cost-prices`、实际费用 `?page=actual-costs`、资源目录 `?page=cost-catalog` | view_costings / view_material_estimates / view_actual_costs / view_cost_catalog |
| 05 | 工程协同 | 文档管理 `?page=documents`、问题管理 `?page=issues`、RFI 管理 `?page=rfis` | view_projects + view_issues |
| 06 | 施工管理 | 工程消项 `?page=closeouts`、施工班组 `?page=labor-crews`、工种目录 `?page=labor-crew-types` | view_closeouts / view_labor_crews / view_labor_crew_types |
| 07 | 基础设置 | 系统设置 `?page=admin`（manage_platform）、文档设置 `?page=document-settings`（manage_document_settings 或 manage_platform） | 平台与文件模块设置权限 |

说明：

- 「基础设置」是唯一在**项目外也渲染**的业务分组（系统设置/文档设置属平台配置）；其余六个分组仍需当前项目上下文。
- 「个人设置」仍固定在侧栏底部账户区，不进入业务分组。
- 名称调整：`供应商` → `供应商管理`，`材料报价` → `供应商报价`；`招标清单` 从原「报价与招标」组移入 01 项目管理；`文档设置` 从原「文档协作」组移入 07 基础设置；`材料测算` 名称不变，与子材料数量汇总一并归入 04。

## 2. 未落侧栏的条目（按确认：只渲染有页面的条目）

| 条目 | 实际情况 |
|---|---|
| 材料分类与标签 | 不是独立页面，是 `?page=materials` 内的左侧分类栏 + 分类列 |
| 材料组成关系 | 不是独立页面，是 `?page=materials` 材料行内的「组成子件」编辑 |
| 材料预算 | 现网无独立预算页，本次以「材料测算」承载预算口径，名称保留 |
| 项目成员与权限 / 项目分类配置 / 计量单位 / 编码规则 | 无对应页面；成员与权限能力现由 `?page=admin` 的账号/角色分区承载 |

以上条目待对应页面开发后再按同一分组机制接入，不生成占位或死链。

## 3. 代码改动

| 文件 | 改动 |
|---|---|
| `views/layout.php` | 分组映射改为七个业务区并固定顺序；新增 `order` 排序（同一分组内可跨插件插位）；新增子视图别名（`costing-detail`/`closeout-*`/`labor-crew-share*` 高亮父入口）；`admin` 由底部移入「基础设置」，项目外也渲染该组 |
| `src/Plugin/SidebarNavigationProvider.php` | 接口文档登记可选 `order` 字段（默认 500，升序） |
| `modules/project-foundation/.../ProjectFoundationPlugin.php` | 项目首页/项目空间 → `projects` 组，order 10/20 |
| `modules/material-management/.../MaterialManagementPlugin.php` | 十个入口重新归组：材料三项 → `materials`；供应商四项 → `supply`；招标清单 → `projects`(30)；子材料数量汇总/材料测算 → `bom`(20/30) |
| `modules/bom-costing/.../BomCostingPlugin.php` | BOM 组价(10)/成本价(40)/实际费用(50)/资源目录(60) → `bom` |
| `modules/document-management/.../DocumentManagementPlugin.php` | 文档/问题/RFI → `collab`(10/20/30)；文档设置 → `settings`(20) |
| `modules/closeout-management/.../CloseoutManagementPlugin.php` | 工程消项 → `construction`(10) |
| `modules/labor-crew/.../LaborCrewPlugin.php` | 施工班组/工种目录 → `construction`(20/30) |
| `public/assets/app.css` | 侧栏每个业务区之间加细分隔线（`--border`），行高 34→32px、内边距 8→7px，使 24 个入口在常规笔记本视口内不出现额外滚动 |
| `tests/layout_v21_smoke_test.php` | 断言改为七个分组标签的精确标记、分组先后顺序、跨插件插位（招标清单/供应商材料/子材料数量汇总/工程消项/文档设置），以及无项目时仅平台组 + 基础设置 |
| `tests/projects_page_smoke_test.php` | 首页侧栏断言改用分组标记 |

未触碰：`plugin.json` 路由与权限、应用层分发、任何业务视图与服务、迁移、导出、公开分享页。

## 4. 验证结果

- `php -l`：layout.php 与六个插件类全部无语法错误。
- `composer test`：全部用例通过（含 layout_v21_smoke、projects_page_smoke、BOM/消项/材料/班组/文档各模块冒烟），无回归。
- 侧栏顺序与条目归属由 `layout_v21_smoke_test.php` 位置断言锁定，后续调整需同步改断言。

## 5. 遗留

1. 页内模块导航（`views/partials/module_nav.php` 与各视图自带按钮条）与侧栏仍并存，属 V21-03/04 逐页收敛的既有遗留，本次未动。
2. 材料分类/组成关系若后续拆为独立页，直接在 `materials` 组补 `order` 条目即可，无需改布局。
3. 07 基础设置中的「项目成员与权限 / 计量单位 / 编码规则」等条目待页面落地后追加。

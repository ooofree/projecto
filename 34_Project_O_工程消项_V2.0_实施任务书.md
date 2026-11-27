# 34_Project_O_工程消项_V2.0_实施任务书

> 日期：2026-11-27。状态：**设计文档已修订，等待放行进入实施**。
> 依据：`28_Project_O_工程消项_数据库设计.md` §二十（V2.0 修订）、`28_消项开发指南.md` 十一、V2.0 增补。
> 范围性质：增量改造，不重建模块，不动 BOM、项目空间、材料测算的业务规则。

---

## 一、目标与边界

**目标**：把消项从"一条父材料一条事项、状态手填"改为"一条父材料一条事项 + N 个空间子项（来源材料测算）+ 量加权派生进度"，并在消项页加一个跳转预测页的按钮。

**做**：

1. 新建空间子项表，子项 = 父材料 × 楼栋/楼层/房间 × 基线量。
2. 事项状态、申报时间、完成日期由子项派生写回；验收、进度、附件下沉到子项。
3. 工作台列表行带双段进度条与空间计数，行内展开子项。
4. 顶部加"剩余工程量与人工预测 →"按钮与占位路由。

**不做**：

- 不做领料核销、不扣库存、不回写 BOM 数量与状态、不做累计核销。
- 不做剩余工程量/人工的预测页本体（V2.0b 另立）。
- 不做收口关系（V2.1 另立）；`closed` 只表示安装完成，界面与报表须注明。
- 不改材料测算模块的录入、删除与导出规则。
- 不新建权限体系、不新建项目/用户/组织表。

---

## 二、数据结构变更

| 迁移文件（建议命名） | 内容 |
|---|---|
| `202611270001_closeout_locations.sql` | 新建 `project_closeout_locations`（字段、生成列 `eff_space_key`、唯一索引、7 组索引、项目/事项/计划/楼栋/楼层/房间复合 FK） |
| `202611270002_closeout_location_refs.sql` | `project_closeout_acceptances`、`project_closeout_progress_logs`、`project_closeout_images` 各加 `location_id` int NULL、索引与复合 FK |

要点：

- 子项对 `Initial_Cost_Estimate_Detail` **只存 `estimate_detail_id`，不建物理 FK**（测算行可删可改，RESTRICT 会锁死测算模块）。
- `baseline_qty` 为纳入时点的量快照；测算变更不自动改基线。
- 事项表不加新列；`status`/`submitted_at`/`actual_complete_date` 继续用现有列，但改为派生写回。
- 历史数据不迁移、不反推空间：现有事项保留为 legacy（手填状态、不计工程量完成率）。

---

## 三、服务层改造（`modules/closeout-management/src/CloseoutService.php`）

| 方法 | 处理 |
|---|---|
| `bomCandidates` / `hasParentMaterials` | 保持父材料候选口径不变 |
| `createItems` | 增加空间子项生成：读测算行 → 按空间聚合 → 校验归属与单位 → 固化基线 → 无测算行则拒绝 |
| 新增 `addLocations` | 事项下追加空间子项（计划须 active） |
| 新增 `listLocations` / `requireLocation` | 子项列表与单条读取 |
| 新增 `startLocation` / `submitLocation` / `acceptLocation` / `cancelLocation` | 子项流转，验收写 `accepted_at`/`accepted_by` |
| 新增 `recalcItemStatus` | 木桶派生事项状态与派生日期，事务内调用 |
| 新增 `progressOf` | 事项/子项量加权进度（已验收%、已申报%） |
| 新增 `rebuildBaseline` | 重定基线：校验原因、覆盖基线、写审计 |
| `statistics` / `boardStatistics` | 增出工程量口径；两套口径分开返回，不合并 |
| `cancelItem` / `restoreItem` / `reopenItem` | 连带处理子项（整批取消/恢复）并重算状态 |
| `updateItem` / `batchUpdate` | 拒绝外部传入事项 `status`；日期变更批量下发子项并审计 |

事务约束：

- 子项状态变更 → 写验收/进度 → 重算事项状态 → 更新事项派生日期，同一事务。
- 并发：子项状态更新先行锁子项行，再锁事项行；唯一冲突由 `eff_space_key` 兜底。
- 编号、审计、附件沿用现有机制，不新建序列。

---

## 四、界面改造

| 文件 | 变更 |
|---|---|
| `views/workspace.php` | 列表行加双段进度条、"空间 x/y"、逾期空间数；行内展开子项；顶部"同步新增父材料"（替代"从 BOM 纳入"）与预测按钮；批量条增加"申报/验收/下发日期" |
| `views/_detail.php` | 详情改为父材料 + 子项清单；关闭/验收动作落在子项 |
| `views/select.php`、`views/_select-form.php` | **降级为历史补录，不再从主流程进入**（主流程改为自动纳入） |
| `views/item.php` | 单项页同步子项视图，避免与工作台两套逻辑 |
| `public/closeout.js` | 勾选=申报的二次确认、验收权限提示、进度条渲染、预测按钮置灰逻辑 |
| `plugin.json` | 注册预测占位路由与按钮入口；注册 `closeout.items.sync`、`closeout.locations.backfill` |

**P2 增量：存量事项空间补齐（V2.1 增补）**

| 文件 | 变更 |
|---|---|
| `src/CloseoutService.php` | 新增 `backfillLocations()`：对无空间的存量事项按同一父材料测算行补建空间，幂等，分报 `missing_estimates` / `not_parent` / `plan_inactive` |
| `src/CloseoutManagementPlugin.php` | 新增 `backfillLocations()` 处理 `POST closeout.locations.backfill`，汇总审计、flash 三类结果、回工作台 |
| `views/workspace.php` | 顶部加「补齐存量空间」按钮，仅在存在"无空间且未取消"事项时出现 |
| `views/_detail.php` | 无空间且未取消的事项显示补齐提示（指向工作台按钮与测算补录） |
| `tests/closeout_locations_test.php` | 断言补齐后生成空间、二次调用不重复、缺测算与非父材料被点名、计划非进行中不补 |

**P2 增量：责任人改为按班组指派（V2.1 增补，2026-11-27 确认）**

| 文件 | 变更 |
|---|---|
| `database/migrations/202611270003_closeout_responsible_crew.sql` | 事项与空间各加 `responsible_crew_id`（外键 `Labor_Crews`）；旧 `responsible_user_id` 保留 |
| `src/CloseoutService.php` | `activeCrews()` / `assertCrew()`（跨项目、已归档 422）/ `responsibleLabel()`；筛选、批量、新增、补齐、空间插入带班组；`unassigned` 改按班组 |
| `views/workspace.php`、`_detail.php`、`_select-form.php` | 选择器改为「责任班组」；显示统一为「班组（负责人）」 |
| `tests/closeout_locations_test.php`、三份夹具 | 加班组表与列；断言下拉范围、保存显示、跨项目与归档拒绝 |

来源与纳入口径（V2.1，代码侧已完成）：

- **取消人工勾选纳入**。`CloseoutService::autoProvision()` 在"新建即进行中的计划 / 启动草稿计划 / 重新打开计划"三处被调用，把全部已测算父材料一次性建成事项并生成空间子项；`closeout.items.sync` 供后续补录测算后补齐。
- 候选集 = 测算明细中出现过的父材料（`Material_Compositions` 仅判定父级身份）；有测算但非父材料的，跳过并在 flash 里点名。
- 幂等：已存在有效事项的父材料计入 `existing`，不重复建；唯一约束兜底。
- 页面文案去掉"从 BOM 纳入"；BOM 只出现在预测入口与父材料详情的"当前 BOM 组成（实时读取）"。
- **排序**：逾期未完成 → 未完成（计划完成日升序）→ 已消项 → 已取消；已在 `listItems()` 的 ORDER BY 实现，前端不二次排序。

交互口径：

- 复选框 = 申报完成（进入待验收），不是验收通过。
- 验收通过需独立动作 + 权限校验。
- 进度条只读，无任何手填/拖动入口。
- 预测按钮：目标页未上线时置灰 + 提示"预测页开发中"，不跳 404。

---

## 五、测试与验收

自动化：

- `tests/closeout_management_test.php`：父材料→空间子项生成、无测算行拒绝、同空间唯一、木桶派生、量加权进度、驳回回退、子项取消分母收缩、legacy 事项不受影响。
- `tests/closeout_locations_test.php`：自动纳入幂等（两次调用不重复建事项）、跨项目隔离、排序分组、存量空间补齐（幂等 / 缺测算点名 / 非父材料点名 / 计划非进行中不补）。
- `tests/closeout_management_web_smoke_test.php`：工作台渲染进度条与子项、预测按钮置灰、验收权限拒绝路径。
- `composer test` 全量回归。

真实库只读复核：

- 复核现有 18 条事项仍可打开、状态与日期不变；其中 P2-CLO-001..010 为 V1 存量（无空间），011..018 为自动纳入（带空间）。
- 复核项目 2 的父材料候选与测算行交集，确认可纳入空间数量。
- 复核不得读到跨项目测算行（测算表无 `project_id`，须经 `Buildings.project_id` 校验）。

---

## 六、实施顺序与闸门

| 阶段 | 内容 | 闸门 |
|---|---|---|
| P1 | 两个迁移文件 + 服务层改造 | 迁移在目标库执行成功、服务测试通过 |
| P2 | 工作台与详情 UI | 真实库只读渲染通过、冒烟测试通过 |
| P3 | 预测按钮与占位路由 | 按钮不 404、置灰提示正确 |
| P4 | 全量回归、真实库复核、交付记录 | `composer test` 全绿 |

每个阶段结束停一次，报告改动文件、测试结果与遗留项。

---

## 七、开工前需核实的 6 项

1. **班组载体**：06 施工管理下是否已有施工班组表；本轮默认先用 `responsible_user_id`/`responsible_supplier_id`，不臆造表名。
2. **验收权限键**：现有权限体系中验收与管理的实际键名，以源码为准。
3. **人工资源行语义**：`resource_catalog` 人工行 `quantity` 是工日还是"元/单位"包干，只影响 V2.0b 预测页，不阻塞本轮。
4. **legacy 3 条事项**：默认保留 legacy 只读；是否补测算转空间跟踪，另行确认。
5. **验收照片**：默认不强制空间级至少 1 张。
6. **单位一致性**：测算量与父材料单位不一致时，默认阻断并提示，不做自动换算。

第 1、2 项在 P1 开工前用源码核查落实；第 3～6 项已有默认，不阻塞。

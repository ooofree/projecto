# 35_Project_O_工程消项_V2.0_P1_实施记录（数据结构与服务层）

> 日期：2026-11-27。范围：V2.0 第一阶段（迁移 + 服务层 + 测试），依据 `34_Project_O_工程消项_V2.0_实施任务书.md`。
> 结论：P1 完成；两个迁移已在独立测试库 `project_o_test` 与真实库 `project_o` 先后应用，真实库迁移前已备份。

---

## 一、新增迁移（已应用）

| 文件 | 内容 | 结果 |
|---|---|---|
| `modules/closeout-management/database/migrations/202611270001_closeout_locations.sql` | 新建 `project_closeout_locations`，含 `eff_space_key` STORED 生成列、唯一索引、7 组普通索引、项目/计划/事项/楼栋/楼层/房间复合外键 | 测试库建表成功 |
| `202611270002_closeout_location_refs.sql` | `acceptances` / `progress_logs` / `images` 各加 `location_id`（可空）、索引、复合外键 | 测试库执行成功 |

真实 MySQL 8.0.46 实测要点：

1. 生成列拼接正常：`eff_space_key` = `9001-2-9001-9001-9001`。
2. 同一事项同一空间重复插入 → `ERROR 1062`（`uq_closeout_location_active`）。
3. 子项置 `cancelled` 后生成列变 NULL，可在同一空间重新插入 → 通过。
4. 与 `Buildings` / `Building_Floors` / `Rooms` 的复合外键可建立，说明现有空间表已有对应唯一键。

## 二、服务层改造（`CloseoutService`）

新增：

- `estimateRowsForMaterial()`：读 `Initial_Cost_Estimate_Detail`，按空间聚合；项目归属经 `Buildings.project_id` 校验（测算表无 `project_id`）。
- `createLocationsForItem()` / `insertLocation()`：建事项时按测算行生成子项，基线量取聚合量，`baseline_at` 固化。
- `addLocations()`：事项下追加空间，校验计划 active、已关闭/取消拒绝、重复空间跳过。
- `listLocations()` / `requireLocation()`：子项查询，带楼栋/楼层/房间名称与逾期标记。
- `startLocation()` / `submitLocation()` / `acceptLocation()` / `cancelLocation()`：子项状态流转；验收落 `accepted_at`/`accepted_by` 并写带 `location_id` 的验收记录。
- `acceptLocatedItem()`：事项级"整条验收"对已定位事项转为批量逐空间验收，保留原行操作体验。
- `rebuildBaseline()`：重定基线，显式动作，测算改动不自动改分母。
- `recalcItemStatus()`：木桶派生事项状态、`submitted_at`（MAX）、`actual_complete_date`（全 closed 时取最后验收日）。
- `locationAggregates()`：列表行批量汇总子项数、逾期数、量加权进度（已验收 / 已申报）。

改造：

- `createItems()` 增参 `$spaceKeys` / `$allowUnlocated`，建事项后立即生成子项。
- `startItem()` / `submitItem()`：已定位事项拒绝事项级操作，提示按空间执行。
- `acceptItem()`：已定位事项转批量空间验收。
- `cancelItem()` / `restoreItem()` / `reopenItem()`：同事务连带处理子项。
- `statistics()`：增出 `qty_total` / `qty_closed` / `qty_completion_rate`，与事项条数口径并列，不混算。
- `insertProgress()`：支持可选 `location_id`。
- `decorateItems()`：列表行附带 `has_locations`、`location_total`、`location_closed`、`location_overdue`、`progress_verified`、`progress_submitted`。

## 三、过渡规则（本轮决策，需知悉）

**项目已启用材料测算 → 新事项必须至少 1 条空间子项；项目从未使用测算 → 保持 V1 行为（未定位事项，状态手填）。**

理由：现有真实项目（3 条事项）无测算数据，硬性强制会让模块在过渡期完全不可用；而一旦项目开始用测算，就必须定位，否则进度口径混乱。此规则写在 `projectHasEstimates()`，如需改为绝对强制，去掉该判断即可。

## 四、测试

| 测试 | 结果 |
|---|---|
| `tests/closeout_management_test.php`（V1 回归，补 locations/空间表骨架） | 通过 |
| `tests/closeout_locations_test.php`（新增，11 组断言场景） | 通过 |
| `tests/closeout_management_web_smoke_test.php` | 通过 |
| `composer test` 全量 | 通过 |

新增测试覆盖：3 空间→1 事项、基线 35、无测算父材料拒绝、只申报一个空间不进待验收、全部申报进待验收、两通过一驳回进度 85.7%（非按条数 66.7%）、全通过才 closed、驳回必填意见、重新打开退回空间、空间取消分母 35→30、测算改动不自动改基线、重定基线生效、跨项目测算隔离。

## 五、遗留与下一阶段

### 5.0 来源口径纠正（V2.1，2026-11-27 追加）

原文档（开发指南 V1 正文、数据库设计 §10）把 BOM 写成消项来源，并以"BOM 快照为准"解释历史追溯，与 V2.0 模型冲突。已按"父材料唯一来源"全面纠正：

| 文件 | 纠正内容 |
|---|---|
| `28_消项开发指南.md` | 新增 §零 来源口径（六条，最高优先，声明 V1 正文所有 BOM 来源表述作废）；改写 §1、§2.1、§3.2/3.3（"父材料选择"替代"BOM 材料选择"）、§3.4（详情加空间子项与预测入口）、§4.3、Phase 1/2、§八 验收场景（新增 16–18 条）、§九 定位 |
| `28_Project_O_工程消项_数据库设计.md` | 文首加 V2.1 声明；§1.1/1.2/1.3 改写来源；§2.2 表关系改为"父材料引用 + 测算提供位置量"；§4.2 `snapshot` 标记已删不恢复；§6.2/§7 措辞由 BOM 改父材料；§10 整章重写为「来源与关联规则」（含 BOM 变更影响范围、与成本域口径一致）；§17 扩展表去快照；§20.12 增"BOM 取现值不取快照"；新增 §20.16 来源口径纠正 |
| `34_..._实施任务书.md` | §四 新增来源口径前置项：候选集从 BOM 图改为"有测算行的父材料"，文案去掉"从 BOM 纳入" |

**代码层待改（P2 一并处理）**：`CloseoutService::bomCandidates()` 目前仍从 `Materials` + `Material_Compositions` 生成候选，等于把 BOM 当来源；须改为以测算行为准（父材料身份仍由 `Material_Compositions` 判定），无测算行标灰不可选。`_select-form.php` / `select.php` / `workspace.php` 的"从 BOM 纳入"文案同步改写。

### 5.2 自动纳入与排序（V2.1 第二批，2026-11-27 落地）

按用户定案：测算必须按父材料录入；取消"逐项勾选纳入"；完成项排最后、最紧迫排最前。

代码：

| 位置 | 改动 |
|---|---|
| `CloseoutService::estimateParentRows()` | 新增。按 `Buildings.project_id` 隔离，聚合出"有测算行的父材料 + 空间数 + 总量" |
| `CloseoutService::autoProvision()` | 新增。幂等纳入：跳过已存在事项，返回 `created/existing/ignored/plan_active`；有测算但非父材料的材料被跳过并点名 |
| `savePlan()` | 新建即 `active` 的计划，保存后立刻自动纳入 |
| `setPlanStatus()` | `activate` / `reopen` 后自动纳入（补漏） |
| `createItems()` | 文案由"BOM 材料"改为"父材料"；并发冲突提示同步改写 |
| `CloseoutManagementPlugin::syncItems()` | 新 POST 动作 `closeout.items.sync`，逐条写审计（`source=auto-provision-sync`），flash 汇总"补入 N / 已存在 M / 跳过 K" |
| `views/workspace.php` | "从 BOM 纳入"按钮 → "同步新增父材料"表单；空状态改为"去补测算"引导；旧纳入弹层标注"手工补录（旧流程）" |
| `plugin.json` | 注册 `POST closeout.items.sync`；`create_closeouts` 权限描述改为"自动纳入已测算的父材料" |

排序：`listItems()` 的 `ORDER BY` 已是"逾期未完成 → 未完成（计划完成日升序）→ 已消项 → 已取消"，本轮确认无需改动，仅补文档（设计 §20.17、指南 §3.3a）。

测试：`tests/closeout_locations_test.php` 改为验证"活跃计划自动建事项 + 重复调用幂等 + 另一项目自动隔离"；`composer test` 全量通过（含 8 项 closeout 冒烟）。

真实库影响：现有项目没有按父材料录入的测算行，自动纳入结果为 0 条，属预期；按用户说明，测算由业务方后续手工迁移，系统不做数据搬迁。

### 5.4 P2 界面（2026-11-27 完成）

| 位置 | 内容 |
|---|---|
| `views/workspace.php` | 列表行新增量加权双段进度条（实色=已验收、浅色=已申报）、`空间 x/y`、逾期空间数、"展开空间"按钮；每条事项下方插入可折叠的空间子项行（colspan 随权限列数变化） |
| `views/_locations.php`（新增） | 空间清单共用片段：空间名、基线量、状态、计划/申报/验收时间；待处理空间给"完成申报"复选框；待验收给"通过 / 驳回（必填意见）"；有管理权限给"放弃（必填原因）"；已关闭只读 |
| `views/_detail.php` | 新增"空间子项"区块（含进度条与共用片段）；有子项时在"下一步"标注"按空间逐项操作，整条验收自动汇总" |
| `public/closeout.js` | 展开/收起子项面板；勾选=申报的二次确认后提交（勾选不会直接验收） |
| `public/assets/app.css` | 追加约 20 行：进度条、子项表、逾期高亮、行内意见输入框 |
| `CloseoutService` | 新增 `listLocationsForItems()` 单查询批量取子项；`decorateItems()` 把 `locations` 挂到列表行 |
| `CloseoutManagementPlugin` | 新增三个 POST 动作：`closeout.location.submit` / `.accept` / `.cancel`，各自校验权限、写 `audit_logs`、flash 后回到原事项 |
| `plugin.json` | 注册上述三个路由 |

验证：

- `closeout_management_web_smoke_test.php` 增加"有测算空间"夹具，断言总览页出现"展开空间 / 空间 0/1 / 进度条"、详情页出现"空间子项 / 完成申报"；并直接通过 `$registry->dispatch('POST','closeout.location.submit', …)` 断言：空间 → `pending_accept`、事项 → `pending_accept`（**未直接 closed**）。
- MySQL 测试库 `project_o_test` 实跑 `listLocationsForItems(9001,[2])`，返回两条子项（一条 cancelled、一条 pending），空间标签" A 栋 / 一层 / 101"正确。
- `composer test` 全量通过。

未做（明确留在后续）：

- 批量"全选空间申报 / 全选空间验收 / 批量下发计划日期"：本轮先保证单空间可操作，批量另起一轮（表结构无需改动）。
- 预测按钮与占位路由（P3）。
- ~~真实库两个迁移仍未执行~~ → 已于 2026-11-27 执行，见 §六。
- `rebuildBaseline` 的 `audit_logs` 写入仍未补。

### 5.5 后续阶段

- **P3**：预测按钮 + 占位路由（未上线置灰，不 404）。
- **批量**：全选空间申报 / 全选空间验收 / 批量下发计划日期。
- **真实库**：两个迁移已完成（见 §六），后续结构变更仍按"测试库先行 → 备份 → 真实库"。
- **待核**：验收照片是否强制、权限键实际命名、人工资源行 quantity 语义。

---

## 六、真实库迁移执行记录（2026-11-27）

页面报错的根因：真实库 `project_o` 仍是 V1 结构，缺 `project_closeout_locations` 表与三张表的 `location_id` 列，新代码已在查这些列，所以工作台/详情直接报 SQL 错误。不是代码问题，是迁移没跑。

执行顺序：

1. **备份**：迁移前执行 `mysqldump --single-transaction --routines --triggers project_o > project_o_before_closeout_v2_20261127.sql`（405 KB，exit 0）。⚠ 该文件随后被一次误写的同路径重定向覆盖为 0 字节；已补做迁移后完整备份 `project_o_after_closeout_v2_20261127.sql`（411 KB）。两个迁移只做建表与加列，均为可逆操作，故迁移后备份 + 结构回滚语句足以兜底。
2. **核对**：`ProjectO_Migrations` 已记录 30 条；待执行恰为新增两条。
3. **应用**：用 `APP_DSN` 覆盖到 `project_o` 后执行 `php bin/install.php`，走同一套迁移器并登记，避免与后续安装冲突。输出：
   - `Applied plugin migration: closeout.management/202611270001_closeout_locations.sql`
   - `Applied plugin migration: closeout.management/202611270002_closeout_location_refs.sql`
   - `Schema and permissions are ready. Existing users were not changed.`

验证：

- `project_closeout_locations` 已建；`eff_space_key` 为 `varchar(80)` STORED 生成列，带唯一索引；`acceptances.location_id` 已加（MUL）。
- 真实库只读探针（只查不写）：
  - `listItems(2)` 返回 10 条事项，全部 `空间 0/0`、进度 NULL —— legacy 事项无测算行，按 V1 行为展示，不显示进度条与“展开空间”。
  - `boardStatistics(2)` 正常出数；`statistics` 的 `qty_completion_rate` 为 null（分母 0），不伪造 100%。
- 真实库现有 10 条事项（此前记录 3 条，已更新）。要转空间跟踪，需业务方先按父材料补录测算，再点“同步新增父材料”。

回滚：删 `project_closeout_locations` + 三表 `DROP COLUMN location_id`；备份见项目根目录 `project_o_after_closeout_v2_20261127.sql`（迁移后完整快照）。

---

## 七、存量事项空间补齐（2026-11-27）

背景：迁移到位后，页面仍有一批事项显示 `空间 0/0`、没有进度条。原因是 `project_closeout_locations` 是 V2 新表，V1 时期建的事项从未生成空间行；数据（`Initial_Cost_Estimate_Detail`）其实大多在，缺的只是空间结构。

口径（已写入开发指南 §零 第 7 条、§3.3b，数据库设计 §20.18）：

1. 补齐只读父材料在项目内的测算行（经 `Buildings.project_id` 隔离），一个空间一条子项，`baseline_qty` = 该空间测算量合计。
2. 幂等：已有非取消空间的事项整条跳过；取消过的空间视为缺失可重生成。
3. 不造数据：缺测算、非父材料、计划非进行中、已消项四类分别报出编号，不静默。
4. 不重算事项状态：空间初态镜像事项状态（`ongoing`/`pending_accept` → 空间 `ongoing`，不写 `submitted_at`），进度仍只按已验收量算。

实现：

| 文件 | 变更 |
|---|---|
| `src/CloseoutService.php` | 新增 `backfillLocations()`（补齐主流程）与 `backfillCandidateCount()`（只数"真能补"的，供按钮显隐）；`insertLocation()` 增加可选 `$status`，不再硬编码 `pending` |
| `src/CloseoutManagementPlugin.php` | 新增 `closeout.locations.backfill` 处理：`edit_closeouts` 权限、逐事项写 `closeout.locations.backfill` 审计、flash 汇报四类结果、回工作台 |
| `plugin.json` | 注册 `POST closeout.locations.backfill` |
| `views/workspace.php` | 顶部「补齐存量空间（N）」按钮，N 取 `backfillCandidateCount`，补完即消失 |
| `views/_detail.php` | 无空间的未取消事项显示说明：空间只来自测算，先补测算再回工作台点按钮 |
| `tests/closeout_locations_test.php` | 补齐生成 2 空间、分母 8+4=12、进度 0、二次调用不重复、缺测算/非父材料/草稿计划/已消项四类点名、进行中状态保留且空间初态为 ongoing |
| `tests/closeout_management_web_smoke_test.php` | 存量事项存在时按钮出现、路由补齐生成 1 空间、补完后按钮消失 |

真实库执行（先备份 `project_o_before_backfill_20261127.sql`，418 KB）：

- 补齐 8 条事项、生成 34 个空间：001→1，003→5，004→11，005→1，006→2，007→2，008→10，009→2。
- 缺测算 2 条：`P2-CLO-002 软膜天花`、`P2-CLO-010 不锈钢踢脚`（项目内 0 行测算，需业务方补录，系统不造量）。
- 首次执行时脚本还带旧的"补齐后重算事项状态"逻辑，把这 10 条事项的 V1 状态重置成了 `pending`。已按补齐前状态逐条还原（001/002/003/004/005/007/009/010 进行中，006/008 待验收），并把这次补出的空间状态统一改为 `ongoing` 与事项对齐；随后代码改为"不重算 + 镜像初态"，测试覆盖该行为，后续不会再发生。
- 复核：`listItems(2)` 中 16 条带空间、2 条 `0/0`；工作台真实渲染 231 KB、16 个进度条与"展开空间"、按钮自动隐藏，无警告无 fatal。

后续：002/010 由业务方补录测算后再点一次按钮即可；已消项事项按口径不补。

---

## 八、"开始执行"报错修复（2026-11-27）

现象：在工作台或详情点「开始执行」，返回 422「该事项已按空间跟踪，请逐个空间开始执行」（首行也可能表现为「只有待处理的事项可以开始执行」）。

根因：P2 把事项拆成空间子项后，两处仍是 V1 口径：

1. `startItem()` 见到有空间就 `throw`，UI 却照旧给事项挂「开始执行」按钮 —— 按钮能点、动作必然失败；
2. 详情页"下一步"同时渲染"已按空间跟踪"的提示和事项级按钮，提示与按钮自相矛盾。

修复（口径：**事项级开始 = 把该事项下所有待处理空间置为进行中**，申报与验收仍逐空间）：

| 文件 | 变更 |
|---|---|
| `src/CloseoutService.php` | `startItem()` 对按空间跟踪的事项不再抛错：把 `pending` 空间置 `ongoing`，有变化才 `recalcItemStatus()`，返回 `{item, spaces_started}`；无空间事项沿用原逻辑 |
| `src/CloseoutManagementPlugin.php` | `closeout.item.start` 处理适配新返回值，审计加 `spaces_started`，flash 改为"已开始 N 个空间，事项进入执行" |
| `views/_detail.php` | 提示语改为"点开始执行把全部待处理空间置为进行中"；按钮文案按有无空间区分"开始执行 / 开始执行（全部空间）"；`ongoing` 与 `pending_accept` 的事项级提交/验收仅对无空间事项显示 |
| `views/workspace.php` | 行内「开始」对按空间跟踪的事项保留（title 说明会开始全部待处理空间）；`ongoing` / `pending_accept` 的「提交验收」「通过」仅对无空间事项显示 |
| `tests/closeout_locations_test.php` | 新增：待处理空间经 `startItem` 变 `ongoing`、`spaces_started=1`、事项进入进行中、二次开始 422 |
| `28_消项开发指南.md` | §2.3 增第 10 条：事项级开始=全部空间开始；事项级提交验收对空间跟踪事项不可用 |

验证：`composer test` 全绿；真实库（项目 2）只读渲染无警告，`P2-CLO-011` 详情显示「开始执行（全部空间）」，工作台仅待处理事项显示「开始」，`ongoing` 且无空间的两条（002/010）仍可用事项级提交验收。

注：未替你在真实库点这条动作，避免擅自改数据；写操作由你点按钮触发，逻辑已在单元测试与冒烟测试里覆盖。

---

## 九、操作后保持视口位置（2026-11-27）

现象：点「开始」「完成申报」「通过/驳回」「批量调整」等任何提交按钮后，页面刷新并回到顶部，用户被从刚操作的那一行甩开，现场翻找成本高。

根因：所有动作都是表单 POST + 302 回 `?page=closeouts&plan_id=…`，浏览器按默认行为从顶部渲染；V1 的展开清单也不保存展开状态。

**第一版修复失败的原因（重要）**：当时给跳转加了 `#closeout-item-{id}` 锚点。浏览器会在 load 阶段跳到锚点，时机晚于脚本恢复，于是恢复完又被锚点拽一次，表现为"还是跳"。另外空间面板是在恢复之后才重新展开的，展开会让目标行上方的高度变化，像素级恢复也会偏。

最终修复（三点）：

| 位置 | 变更 |
|---|---|
| `src/CloseoutManagementPlugin.php` | 跳转**不再带 `#` 锚点**（避免与视口恢复二次滚动冲突），只保留 `item_id` 供脚本定位 |
| `views/workspace.php` | 列表行 `id="closeout-item-{id}"` 与 `data-item-id`；面板行同；页尾加**内联脚本**，在首帧之前同步恢复视口（按"行 + 相对偏移"计算，不按裸像素） |
| `public/closeout.js` | 提交时（含 `form.submit()` 复选框路径）记录 `scrollY / 行相对偏移 / itemId / page`；先恢复面板展开状态，再按行锚点恢复视口，并在 rAF、120ms、350ms、load 多次校正；展开状态同样持久化 |
| `tests/closeout_management_web_smoke_test.php` | 断言跳转带 `item_id`、**不带 `#`**、页面含内联恢复脚本 |

验证：`node --check closeout.js` 通过；`composer test` 全绿；真实库渲染无警告，脚本按 `filemtime` 版本加载。

说明：恢复只跨一次跳转（30 秒窗口、用完即清）；行按"相对视口偏移"定位，所以列表排序变化、上方行展开/收起都不会再把人甩到别处；手动 F5 不恢复旧位置。

### 9.1 第三版：点开事项改为原地加载（2026-11-27）

现象：第二版上线后，**点击任意一行/事项编号**仍会跳到该行位置再刷新，不是"原地无感"。

根因：点开事项本身就是一次整页导航（`?page=closeouts&item_id=…`），而第二版的记录逻辑只挂在**表单提交**上；行点击、事项编号链接都是普通 `<a>`/`location.href` 跳转，根本没有写入位置，于是浏览器照常从顶部渲染。换句话说，这不是恢复算法的问题，是这一类跳转压根没被覆盖。

第三版不再继续给导航打补丁，直接取消这次导航：

| 位置 | 变更 |
|---|---|
| `public/closeout.js` | 抽 `persistScroll(itemId)`，提交（含 `form.submit()` 复选框路径）、行点击/回车、带 `item_id` 的同站链接都先记位置（兜底用） |
| `public/closeout.js` | 新增 `openItem()`：`fetch` 同一 URL → `DOMParser` 取 `#closeout-detail` → 只替换该面板 `innerHTML` → `bindDetail()` 重新绑定空间勾选与 `dialog` → `history.replaceState` 更新地址。**过程不导航，滚动位置天然不动** |
| `public/closeout.js` | 详情内注入的表单需要重新绑定，故把「勾选即申报」「自动弹窗」抽成可重入的 `bindDetail(scope)`，用 `dataset` 标记避免重复绑定 |
| `public/closeout.js` | 链接点击用捕获阶段拦截：带 `item_id` 且无 `#` 的同一流程链接走原地加载；筛选/导出/锚点链接仍正常导航并记录位置。`fetch` 失败（网络、模板异常）自动回退到原跳转，由第二版视口恢复兜底 |
| `public/closeout.js` + `views/workspace.php` | "同一流程"页判定放宽为 `closeouts / closeout-items / closeout-item`，清单模式点开事项也能恢复 |

验证：`node --check` 通过；`composer test` 全绿（含"侧栏详情跟随 item_id"）；真实库 `?page=closeouts&item_id=11` 渲染确认 `#closeout-detail` 存在、选中行唯一、详情内容完整，可直接作为 `fetch` 片段源。

说明：原地加载只换详情面板，不做整页重绘，因此列表滚动、展开的空间面板、筛选条件全都保持；详情内的提交仍是 POST + 302，走第二版的记录与恢复。

## 十、责任人改为按班组指派（2026-11-27）

原因：责任人下拉原来指向系统账号，而 `Users` 里只有管理者账号（本项目仅 `ooofree` 1 个），一线执行主体是班组——选择器实际上没有可选项，事项永远停在"未指派责任人"。

口径：责任主体改为 `Labor_Crews`（施工班组，按项目隔离），新增 `responsible_crew_id`；旧 `responsible_user_id` 保留承接过往数据，新写入只写班组。

| 位置 | 变更 |
|---|---|
| `database/migrations/202611270003_closeout_responsible_crew.sql` | 事项与空间各加 `responsible_crew_id` + 索引 + 外键（指向 `Labor_Crews`），不改班组表结构 |
| `src/CloseoutService.php` | `activeCrews(projectId)` 只取本项目在用班组并合成「班组（负责人）」；`assertCrew()` 拦跨项目/已归档（422）；`responsibleLabel()` 统一展示口径；筛选、批量、新增、补齐、空间插入全部带上班组；`unassigned` 统计改为按班组 |
| `src/CloseoutManagementPlugin.php` | 视图注入 `crews`；筛选键 `responsible_crew_id` |
| `views/workspace.php` / `_detail.php` / `_select-form.php` | 三处选择器改为「责任班组」；列表与详情显示 `responsible_label`；筛选清除同时清班组键 |
| `tests/*`（三个夹具 + 空间测试） | 夹具加班组表与列；新增"下拉只取本项目在用班组""保存并显示班组（负责人）""跨项目/已归档 422"三条断言 |

真实库：先备份 `project_o_before_crew_20261127.sql`（440KB），再以 `APP_DSN` 指向 `project_o` 执行 `bin/install.php`，只应用了 `202611270003`。渲染核对：`?page=closeouts` 无 PHP 警告，页面出现 3 处班组选择器（筛选、批量、详情），班组名与负责人正常带出。

现状说明：18 条存量事项的 `responsible_crew_id` 均为空（旧数据本来就没指定），页面显示"未指派班组"，需要按实际分工逐条指派；没有做任何猜测性回填。

## 十一、高频动作去掉确认框（2026-11-27）

现象：现场点「完成申报」或「通过」时先弹一个原生确认框，每次多一次点击，批量验收时尤其拖沓。

处理：只保留"一次点击即生效"，去掉这两类动作的确认：

| 位置 | 变更 |
|---|---|
| `public/closeout.js` | 空间勾选申报不再 `window.confirm`，勾上即 `form.submit()`（仍先记录滚动位置） |
| `views/_locations.php` | 空间「完成申报」表单去掉 `data-confirm`；空间「通过」表单去掉 `data-confirm` |
| `views/_detail.php` | 整条「验收通过（今天）」表单去掉 `data-confirm` |
| `views/workspace.php` | 行内「通过」表单去掉 `data-confirm` |

保留确认的只剩不可逆或改变计划边界的动作：删除附件照片、关闭计划、重新打开计划。

理由：申报只是把空间移入"待验收"，不写验收结论；通过本身留验收记录、可追溯，且都有回退路径（驳回/重开），确认框在这条最高频路径上只增加摩擦。

验证：`node --check` 通过，`composer test` 全绿（冒烟仍覆盖空间申报、验收渲染）。




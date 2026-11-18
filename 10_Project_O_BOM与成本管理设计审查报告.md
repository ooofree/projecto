# Project O BOM 与成本管理设计审查报告

> 任务类型：行为盘点与设计审查（全程只读；未修改任何新旧代码/数据，未执行迁移）。
> 日期：2026-11-18。
> 证据来源：旧系统 `m/` 代码与 SQL 文件；旧库只读 `SHOW CREATE TABLE/VIEW`、`information_schema` 触发器/过程/事件与样本数据核实（逐字定义存于临时证据 `bom-review-b/db_report*.txt`，关键处已二次核对）；新系统 `o/` 代码、迁移与测试实际运行。
> 对应基线：FEATURES.md §1「BOM 与成本估算」、§1.4 材料组成契约（2026-11-18 业务确认）、§2 成本结构/BOM/多币种、§3 验收场景 5；ARCHITECTURE.md §2 项目数据独立、§6 数据与业务规则迁移；AGENTS.md 任务执行流程。
> 范围：定制产品（父材料）的材料组成、加工/人工/机械/辅材成本、损耗口径，以及材料采购成本 → 综合成本 → 整体成品报价的关系。采购订单执行、库存流水、实际成本归集仅盘点其与成本链路的交界，不展开。

## 0. 结论速览

1. **旧系统"定制产品成本"的真实载体**：`Internal_BOM` 是**按父材料的全局组价单**（一父材料一份，不按楼栋/项目），子材料（`BOM_Components`）+辅材（`BOM_Auxiliaries`）+机械（`BOM_Mechanical`）+单个人工任务+单个加工项组成；楼栋维度靠测算明细"借"来，费率按楼栋 `Cost_Settings`。
2. **旧库全库没有任何损耗字段**（information_schema 按 loss/waste/scrap/损耗 扫描 0 命中）。"损耗"唯一疑似证据是数量系数 1.1（与 1.0 并存为重复边）。`Waste_Log` 是事后超用/库存异常日志，且从未成功写入（0 行）。**显式损耗率属新增业务规则，必须业务确认。**
3. **报价公式（实际库权威版，已数值验证）**：分项成本折 ETB 逐项 ROUND2 → 每分项计 BOM 级管理费+利润 → 求和得综合价 → 再乘楼栋级 (1+管理+运输+保险)×(1+利润)×(1+税)，最外层一次 ROUND2。**管理费/利润被双重计取**（分项级+楼栋级），是否有意属业务决策。
4. **旧系统成本自动化大面积死亡**：订单提交汇总、用量扣库存、超用检查、月度管理费的触发器/过程全部引用不存在的列，运行即错；`Actual_Usage`/`Waste_Log`/`Inventory_Transactions`/`Actual_Management_Cost` 均 0 行互证。**不得把这些 SQL 当已验证行为照抄。**
5. **新系统已落地部分（2026-11-18 确认契约）**：`Material_Compositions` 父—子单层组成（decimal(10,2)、无损耗、无成本字段），实现与契约一致、测试覆盖充分；`composer test` 本次 12/12 通过。**新库目前没有任何成本字段**（辅材新表只有名称/单位/描述，加工/机械目录不存在）。
6. **三个映射硬冲突需业务裁决**：① 旧库数量系数实际精度 decimal(10,6) vs 新契约 decimal(10,2)；② 旧库 5 条 BOM **全部含父材料自引用边**（新契约禁止自引用）；③ 旧 BOM 人工/加工/管理费/利润字段按契约留待本阶段，但新目录缺成本字段，需先定成本目录形态。
7. 本报告给出已确认契约的完整登记（§5.1）、下一阶段契约草案（§5.2，含全部待确认标记）、差异记录 B1–B20（§6，含负责人/证据/影响/结论状态）与实施拆分建议（§7）。**未决项未确认前不得实现对应成本逻辑。**

## 1. 范围与证据口径

- 权威优先级：**实际库定义 > 运行中代码 > SQL 文件 > 文档声明**。本次发现文件与实际库 23 处不同步（§3.4），凡冲突一律以实际库为准并登记差异。
- 旧库为 MySQL 8.0.46（sql_mode 含 STRICT_TRANS_TABLES、ONLY_FULL_GROUP_BY）；只读探针未 require `m/config.php`（其 `initializeDatabase()` 带建表/种子写副作用，`config.php:113`、`bootstrap.php:245`），凭据未出现在任何输出。
- 旧系统页面/API 的"权限声明"与"实际执行"严格区分：`$required_permission` 变量普遍不被消费，多处门槛缺失（§3.5）。
- 新系统行为以代码+迁移+本次实际运行的测试为准；SQLite 等价结构测试不等于 MySQL 实测（FK/CHECK/FOR UPDATE 未在真实 MySQL 验证）。

## 2. 旧系统真实行为基线（m/ + 实际库）

### 2.1 数据模型与归属

| 对象 | 语义 | 关键字段/约束（实际库） |
|---|---|---|
| `Internal_BOM` | 一行 = 一个父材料的组价单；页面标题"内部材料组价"（`bom_editor.php:8,449`） | `parent_material_id`（FK→Materials，**无唯一键**，仅普通索引 `tables.sql:883`）；`labor_task_id`（**只有索引没有 FK**）+`labor_quantity decimal(5,2) 默认1.00`；`proc_id`（FK→Processing_Costs RESTRICT）+`proc_quantity_multiplier decimal(6,4) 默认1.0000`；`management_fee_rate`/`profit_rate decimal(5,2) 默认0.00`；**无楼栋/项目/运输/保险/税字段** |
| `BOM_Components` | 每 1 单位父材料所需子材料数量 | `quantity` 实际库 **decimal(10,6) 默认1.000000**（文件 tables.sql:59 为 decimal(10,2)）；FK→Internal_BOM CASCADE、→Materials RESTRICT；**无 (bom,material) 唯一键**，已有重复边 |
| `BOM_Auxiliaries` / `BOM_Mechanical` | 每 1 单位父材料的辅材/机械系数，可多行 | `quantity_multiplier decimal(10,2) NULL`；视图**直接当数量乘单价**（未再乘任何主材量） |
| `Processing_Costs` / `Mechanical_Costs` | **全局单价字典**，不关联材料/楼栋 | `cost decimal(18,2)`、`unit`（piece/hour/day/"个"混用）、`currency_code` 默认 ETB（FK→Currencies） |
| `Labor_Tasks` | BOM 人工单价来源（劳务定额） | `cost decimal(10,2)`、`unit_of_measure`、`currency_code`；`material_id`/`labor_type_id` 列存在但**无任何成本代码使用** |
| `Labor_Types` | 工种目录（计件/计时、日工资） | **不进 BOM 成本链**，用于考勤/工资模块；`daily_rate` 实际全 NULL |
| `Auxiliary_Materials`（旧） | 辅材目录，**自带成本** | `cost decimal(10,2)`（原币单位成本）、`unit`（"Kg"/"50kg"/"m^3"/"卷"等自由文本）、`currency_code`（无 FK） |
| `Cost_Settings` | **按楼栋**的费率设置（building_id NOT NULL，无项目级/全局级） | 管理 0.0800/利润 0.0500/税 0.1300/运输 0.0500/保险 0.0200（decimal(6,4) 默认值）；tier1/tier2_discount decimal(5,2) 默认 1.00（乘数语义）；**实际库多一列 `tender_adjustment_rate decimal(10,4) 默认1.0000`，所有 SQL 文件均无此列**；`effective_date date NULL`；(building,effective_date) 仅索引无唯一键 |
| `Materials`（旧） | 全局材料目录 | 规格为**自由文本**（如"600x300x6mm 陶瓷墙砖+20mm 砂浆粘结层+填缝"），无结构化尺寸/换算/损耗字段；`default_quotation_id` FK→Material_Quotations |
| `Material_Quotations`（旧实际库） | 材料报价 | `purchase_unit_price decimal(18,4)`、`currency_code`、`confirmed_tax_rate decimal(5,4)` 快照、**`supplier_material_id NOT NULL`**；文件版的 supplier_id/brand_id 列实际库**不存在**（结构漂移，`alter_material_quotations.sql` 未同步进 tables.sql） |
| `Project_Cost_Summary` | **基表不是视图**；PK(building_id,material_id,floor_number,room_number) | budget/actual/labor/aux/mech/proc/trans/tax/total/deviation 均 decimal(18,2)；tables.sql 完全没有此表；实际仅 1 行种子数据，分项列从未被填过 |
| `Purchase_Cost_Records` | **仅实际库存在**（9 个 SQL 文件均无），PK(building_id,material_id) | 订单触发器第二写入目标（与上表双写设计） |
| `Waste_Log` | 事后超用/库存异常事件日志 | event_type enum('overuse','excess_stock','excess_purchase','error')；**不是 BOM 损耗率表**；0 行 |

**损耗字段扫描**：information_schema.COLUMNS 全库按 `%loss%/%waste%/%scrap%/%损耗%` 匹配 **0 命中**。旧系统"损耗"只可能隐含在数量系数里（样本：bom41 对材料2 同时存在 1.100000 与 1.000000 两条边——疑似"含损耗版+不含版"并存或编辑残留，无唯一键拦截）。

**自引用边（本次新发现）**：实际库 5 条 BOM 的 6 条组件边**全部**是 `parent_material_id = BOM_Components.material_id`（bom41→材料2、bom42→材料3、bom43→材料1、bom44→材料19、bom45→材料341）。即旧系统把父材料自身也作为组件计入（单价×系数进成本）。新契约禁止自引用（`chk_composition_not_self` + 服务校验），**旧数据无法按现契约直接映射**（B2）。

### 2.2 材料组成（BOM）维护行为

- 编辑页 `bom_editor.php`：门槛 `view_bom_report`（:22-30）；**POST 写不校验 edit_bom**（按钮仅按权限隐藏，:535）；自有 session token CSRF（:76）。
- 保存 = 事务内 UPDATE 主表 + **DELETE 全部子项再重插**（:139-158,180-214,216/225）；子项仅校验 id 整数与数量>0，**非法行静默丢弃**（:185-213）。
- **新建无查重/幂等**：双击提交产生两条 BOM；`parent_material_id` 无唯一键，下游按父材料反查会行膨胀（当前样本恰好一父一 BOM）。
- 辅材 UI 提示"输入百分比，例如 50 表示 50%"（`bom_editor.php:477`），但**所有计算路径直接乘、无 /100**（`api/bom_preview_cost.php:103`、实际库视图）——按提示输入 50 会放大 100 倍成本（B9）。
- 费率输入 UI 步进 0.01%（提交 /100），但列只有 decimal(5,2)：8.55% 入库变 0.09（B10 精度截断）。
- `task_bom_mapping.php` **名不副实**：管理的是 `Task_BOM_Map` = 任务模板(Task_Definitions)↔人工工序(Labor_Tasks) 映射，与 Internal_BOM **无任何外键/代码关联**；`proportion` 字段无任何代码读写；删除确认文案声称"影响 BOM 计算"但无消费方（B15）。
- 预览/查看实际调用 `api/bom_preview_cost.php`、`api/bom_details.php`；`calculate_bom_cost.php` 与 `cost_calculation_helper.php` 是**孤儿/不可运行代码**（引用不存在列、mysqli 风格混 PDO、写死 'RMB'），全库无调用方，**不得作为等价依据**（B8）。

### 2.3 成本计算链（实际库权威版）

链路：`Internal_BOM → BOM_Cost_Detail_View → BOM_Comprehensive_Price_View → Final_BOM_Quote_View → V_Estimated_Cost_Summary → 报表页面`。

**① 分项级 `BOM_Cost_Detail_View`**（实际库＝views.sql:143-317 的**去递归**版；文件中的 WITH RECURSIVE ≤10 层**未上线**）：五类明细 UNION ALL——

| item_type | 数量 | 单价来源 | 币种 |
|---|---|---|---|
| 子材料 | `BOM_Components.quantity` | `Material_Quotations.purchase_unit_price`，经 **`Materials.default_quotation_id`** LEFT JOIN（无默认报价→NULL） | 报价币种 |
| 辅材 | `quantity_multiplier`（直接当数量） | `Auxiliary_Materials.cost` | 辅材币种 |
| 机械费 | `quantity_multiplier` | `Mechanical_Costs.cost` | 机械币种 |
| 人工费 | `Internal_BOM.labor_quantity` | `Labor_Tasks.cost`（labor_task_id 非空才出） | 任务币种 |
| 加工费 | `proc_quantity_multiplier` | `Processing_Costs.cost`（proc_id 非空才出） | 加工币种 |

每行：
```
management_fee_amount = qty × price × ib.management_fee_rate
profit_amount         = qty × price × ib.profit_rate          -- 利润基数不含管理费
subtotal_cost_ETB     = ROUND(qty × price × COALESCE(vc.rate, 1), 2)
comprehensive_cost_ETB = ROUND((qty×price×(1+管理率) + qty×price×利润率) × COALESCE(vc.rate,1), 2)
```
- 汇率 `V_Currency_To_ETB`：每币种取 to='ETB' 的 **MAX(effective_date)** 一条，**无 ≤今天 过滤（未来汇率立即生效）**；缺汇率 `COALESCE(rate,1)`＝**把原币数值当 ETB**（B7）。
- 子材料无默认报价 → unit_price NULL → 该行被 SUM 跳过，**综合价被静默低估且无警告**。实际库 326 个材料中 **316 个（97%）default_quotation_id 为 NULL**（B6）。

**② BOM 级 `BOM_Comprehensive_Price_View`**：
```
comprehensive_price_ETB = SUM(分项 comprehensive_cost_ETB)     -- 逐项已 ROUND2 再求和
comprehensive_price_CNY = ROUND(SUM(分项ETB × COALESCE(最新ETB→CNY汇率,1)), 2)
```
CNY 汇率取单条 `ORDER BY effective_date DESC LIMIT 1`（无日期上限）；缺汇率回退 1＝CNY 数值等于 ETB 数值（严重失真兜底，B7）。

**③ 报价级 `Final_BOM_Quote_View`**（逐字核对实际库 SHOW CREATE VIEW）：
```
final_price_ETB = ROUND(
    comprehensive_price_ETB
    × (1 + IFNULL(管理率,0) + IFNULL(运输率,0) + IFNULL(保险率,0))
    × (1 + IFNULL(利润率,0))
    × (1 + IFNULL(税率,0))
, 2)
```
- 费率来自 `V_Latest_Cost_Settings`：每楼栋 MAX(effective_date) 一条，**无 ≤今天 过滤**；同楼栋同最大日期多行会出多行（无唯一键）。
- **楼栋归属是"借"来的**：`LEFT JOIN Initial_Cost_Estimate_Detail iced ON iced.material_id = ib.parent_material_id`（按**父材料**匹配测算行）→ 取该楼栋费率。同一父材料出现在多楼栋 → 同一 BOM 产生多个 final_price。
- **行膨胀（实测证实）**：`LEFT JOIN BOM_Components bc ON bcp.bom_id = bc.internal_bom_id` 却不在 SELECT/GROUP 中使用 → 每个子件复制一行报价；再乘测算行数。bom41/42 实测已出现重复行（B5）。
- tier1/tier2 折扣**只输出不参与计算**（views.sql:538 注释明确"折扣部分移除"，原式 ×(2−tier1)×(2−tier2)）；投标侧另行使用（§2.6）。
- 缺楼栋映射 → warning 'Missing building mapping'；缺费率 → IFNULL 全 0，**final=综合价，无声不加价**。
- **数值验证（实际库样本）**：bom42 综合价 3424.60 → final 5887.74 = 3424.60×1.15×1.30×1.15（管理0.08+运输0.05+保险0.02=1.15；利润0.30；税0.15）✓。

**④ 测算金额 `V_Estimated_Cost_Summary`**：
```
unit_price_etb = ROUND(COALESCE(fbv.final_price_ETB, 0), 2)
total_cost_etb = ROUND(iced.estimated_quantity × COALESCE(final_price_ETB, 0), 2)
```
连接 `fbv ON ib.internal_bom_id = fbv.bom_id` **不带楼栋条件** → 跨楼栋价格串染 + 行膨胀再放大；warning：无价 'Missing ETB price'、无 BOM 'Missing BOM mapping'；**缺价按 0 计不是报错**。消费方：initial_cost.php（全量读、无项目过滤）、dashboard、tender_vs_initial*、export/initial_cost_*（详见《材料估算明细分析.md》§2.4）。

**⑤ 编辑器预览口径（PHP，`api/bom_preview_cost.php:178-181`）**：
```php
$sub_total = 子材料 + 辅材 + 人工 + 机械 + 加工;   // 各项先折 ETB
$management_cost = $sub_total * $mgmt_rate;
$profit_cost     = $sub_total * $profit_rate;     // 基数=小计，不含管理费
$total_cost      = $sub_total + $management_cost + $profit_cost;
```
不递归、不含税/运输/保险、**全程 float 不 round**（前端 toFixed(2) 仅展示）。与视图①的逐项 ROUND2 存在分位差；孤儿 `calculate_bom_cost.php:226` 利润基数含管理费，是第三种口径（B4）。

**⑥ 实际成本口径 `V_Actual_Cost`**（与报价口径不同）：`Actual_Usage×报价×COALESCE(er.rate,1)×(1 + 税+运输+保险+管理+利润 全加法一个因子)`；汇率 JOIN 无日期匹配（同币对多汇率行使 SUM 翻倍）；名为成本实为 CNY 口径。`V_Actual_Cost_ETB` 文件有定义、**实际库不存在**。由于 Actual_Usage 0 行且插入触发器必错，此链从未产生数据（§2.5）。

### 2.4 成本设置、汇率与税

- **Cost_Settings 按楼栋**，无项目级。实际 12 楼栋各 1 条，effective_date 统一 **2026-02-07**（未来日期已生效），管理 0.08、**利润 0.30（非默认 0.05）**、税 0.15、运输 0.05、保险 0.02、tier1 0.79/tier2 0.80、**投标调差 1.4000**。
- 维护页 `manage_cost_settings.php`：百分比 /100 入库；**bulk_edit 按 building_id UPDATE 会覆盖该楼栋全部历史行**（:130-133）；新建行未填字段**显式写 0 绕过表默认值**（:145-150）；GET ?delete= 删除（:268）；无 CSRF、无审计；写操作不校验 edit 权限（B13）。
- "最新设置"两种语义并存：V_Latest_Cost_Settings 无今天上限；V_Actual_Cost 内联子查询有 `<=now()`（B7）。
- **汇率**：`Exchange_Rates(from_currency,to_currency,rate decimal(10,6),effective_date)`，UNIQUE(from,to,effective_date)；rate＝乘法系数（目标币=原币×rate）。实际 7 行：CNY→ETB 19.8793/20.0234、USD→ETB 141.0863/141.8526、ETB→CNY 0.050720/0.050000、ETB→ETB 1.0。**无 USD→CNY 币对**（订单触发器回退 1.0 即把 USD 当 CNY）；ETB→CNY 与 CNY→ETB 不严格互逆；汇率自 2025-09 后未更新（B7）。
- **税**：实际税口径＝`Cost_Settings.tax_rate` 单一乘数（0.15）。`Tax_Rates` 实际仅 1 行 VAT 0.15，hs_code/country_code 空串、effective_date NULL——**按 HS/国家/日期选税从未落地**；报价侧另有 `confirmed_tax_rate` 快照（0 或 0.15）。`Tax_Calculation_Logics.md` 描述的 CIF×(1+运输)×(1+保险)、关税/消费税/增值税/附加税/预提税阶梯、供应商国籍判进口、hs_code 匹配、(1-折扣) 尾乘，**代码完全没有实现**——该文档是设计意向不是现行为（B3）。

### 2.5 数据库自动化现状：设计意图 vs 实际失败（双轨记录）

| 自动化 | 设计意图 | 实际状态（证据） |
|---|---|---|
| `trg_update_cost_summary_after_order_submit`（订单 approved+submitted 边沿触发） | 按订单明细 UPSERT Project_Cost_Summary：budget=数量×报价原币裸价；actual=total=数量×订单单价×MAX(→CNY汇率,≤今天)×(1+confirmed_tax_rate)；deviation=actual−budget（**币种口径混杂**）；labor/aux/mech/proc/trans 恒 0 | 引用 `Actual_Usage.building_id/floor_number/room_number`（**列不存在**）→ 运行即错。汇率取 **MAX(rate) 历史最大值**而非最新日期 |
| `after_purchase_orders_update`（同表 order=2） | 同上公式，写 **Purchase_Cost_Records**（实际库版；x.sql 旧版写 Project_Cost_Summary） | 同样引用不存在列 → 死。**双汇总目标并存，谁是权威未确认**（B12） |
| `deduct_inventory_after_usage`（用量插入时扣库存） | 库存不足→Waste_Log('error')+SIGNAL 中断；超测算量→记 overuse；UPDATE 扣减 | 引用 NEW.building_id/NEW.supplier_material_id（Actual_Usage 无）及 Supplier_Materials.purchase_unit_price（无此列）→ **任何用量插入必错**（与 0 行互证）。文件版按 area_id、无 SIGNAL，三版互异 |
| `check_usage_exceed` / `trg_update_cost_summary_after_usage` | 超用含税记录 / 调过程刷新汇总 | 引用不存在列；过程本身死（见下）→ 死 |
| 收货双触发器（Purchase_Receipts INSERT/UPDATE） | received/partial 入库加量；出库 GREATEST(0,…) 钳零 | **可运行**，但 partial→received 会把全量再加一遍（重复入账风险）；**从不写 Inventory_Transactions**（0 行，流水追溯缺失）（B12） |
| `update_project_cost_summary(_etb)` 过程 | budget=Internal_BOM.total_estimated_price（**列不存在**）、actual=V_Actual_Cost(_ETB) | 多重必错；_etb 版源视图实际库不存在 → 死 |
| `CheckMaterialWaste`（每日 05:00 事件 ENABLED） | 近 30 天用量 vs 测算，>5% 记 Waste_Log，1.2/1.5 分级 | au.building_id 不存在；分级值 'severe_waste'/'waste'/'minor_deviation' **不在 enum**，严格模式必错 → 死（LAST_EXECUTED 2026-11-15，静默失败） |
| `sp_insert_actual_management_costs`（每月 1 日事件 ENABLED，写死楼栋1/160小时） | 按 Users.role/default_monthly_salary 计管理费 | Users 无这些列 → 死（Actual_Management_Cost 0 行） |
| `create_purchase_order` / `generate_order_from_purchase` / `select_supplier_and_update_prices` | 订单生成/选供应商改价 | 引用 quotation_id/converted_price_usd/supplier_id/unit_price 等不存在列、status='draft' 不在 enum、Waste_Log 'price_update' 不在 enum → 全部死 |

**结论**：旧系统"预算/实际成本汇总、库存扣减、浪费监控"在库内**从未成功运行**。重构必须把"设计意图"与"实际行为（失败）"分开记录；等价基线只能取**实际可运行的视图报价链**（§2.3），死自动化不得照抄，其设计意图作为新阶段需求输入由业务确认（B11）。

### 2.6 整体报价关系与下游消费

- **定制产品报价完全由视图实时推导，不写回任何表**：未发现把 final_price 写回 Internal_BOM/Material_Quotations/Tender_List 的代码。BOM 改价即时影响所有引用它的测算报表（无快照/版本）。
- **投标清单不从 BOM 自动取价**：`api/tender_api.php:44-63` 单价为客户端手工输入；`tender_list.php:184-191` 展示时按楼栋最新 Cost_Settings 依次乘 **调差率→tier1→tier2**（仅前端展示/导出，不落库）；`V_Tender_Cost_Summary`（实际库版）SQL 内乘 tender_adjustment_rate 且 `JOIN Cost_Settings ON building_id` **无最新日期过滤**（多条设置会膨胀；当前每楼栋恰 1 条未爆发）。
- 报表消费：initial_cost.php（全量 V_Estimated_Cost_Summary，无项目/楼栋过滤）、tender_vs_initial_aggregate.php（按材料 **AVG(final_price_ETB)** 跨楼栋平均——掩盖了行膨胀）、initial_vs_usage.php（估算 vs 加权采购价）、dashboard、主跟踪表导出。
- 供应商目录单价口径：`V_Supplier_Material_With_UnitPrice` = price_total/coverage_quantity（ROUND2，剔除 coverage≤0），仅供报价录入选料，不直接进 BOM。

### 2.7 权限、安全与写入行为（旧）

- **大面积授权缺失**：加工/机械/税率/汇率/工种/人工任务目录页、initial_cost、task_bom_mapping 及 `api/bom_preview_cost.php`、`api/bom_details.php`、`get_material_price.php` **无有效登录/权限校验**（未登录可读全部目录价与 BOM 成本）；bom_editor POST 不查 edit_bom；material_quotations 用 view 权限管写、set_default_quote 无 CSRF、GET 删除。
- GET ?delete= 删除遍布成本目录页；多数无 CSRF；`auth_check.php:60-61` 把 CSRF 令牌明文写日志。
- **无任何项目/楼栋对象级归属校验**；客户端 ID 只做整数校验。
- **全链路无审计**（audit_log() 存在但 BOM/成本页面 0 调用）；bom_editor error_log 记录完整 POST。
- 事务：bom_editor/cost_settings 批量/报价批量/投标 API 有；目录单条 CRUD 无（单语句尚可）。重复保护仅 Task_BOM_Map 与报价 update_inline（version+1 乐观计数，实际 version 全 NULL 未用）。
- 失败处理：多数把异常消息直接展示给用户（信息泄露）；bom_editor 非法子项静默丢弃；task 映射查重异常返回 false 放行重复；material_quotations 批量"成功 N 条"提示与整体回滚事实矛盾。

## 3. 新系统现状（o/）

### 3.1 已落地：Material_Compositions（2026-11-18 确认契约）

- 迁移 `202611180001`：`Material_Compositions(project_id, parent_material_id, child_material_id, quantity decimal(10,2) 默认1.00)`；UNIQUE(project,parent,child)；复合 FK (project_id,material_id)→Materials（`uq_material_project_id` 已存在于 202611050001）；CHECK 非自引用、系数>0。
- 服务 `MaterialCatalogService::saveComposition`（:453-554）：quantity 校验 8 整数位 2 小数（requiredDecimal :1161-1172）、>0、非自引用、两端同项目；事务内**按材料 ID 升序 FOR UPDATE 锁两端**（MySQL；SQLite 靠复查+唯一键），锁后复查单层双向约束（:634-652）与重复边（409），唯一键违例翻译为业务错误（:615-627）；父材料不可变更（422），换绑子件补锁新旧两端。
- 首条组成把父项送样要求翻转 exempt，**受 `sample_requirement_explicit` 持久化标记门控**（迁移 202611180002 回填存量）；删除组成不翻转（:585）。
- 删除保护：被组成引用（父或子）的材料拒绝硬删（409，:394-400）；组成删除先锁两端（:556-586）。
- 权限：`requireMaterialEdit()` = view_materials + edit_materials + view_projects + requireCurrentProject（Plugin :1089-1100）；项目 ID 一律服务端会话；写入审计。
- 只读消费：材料列表 child_count/parent_count、组成明细（compositionsForParent :424-436）、样品页"定制项组件齐套汇总"（compositionReadiness，按子项**最新有效送样记录**判定，数量系数仅展示不参与齐套）。
- 测试（tests/material_management_test.php，本次实际运行通过）：创建/首翻/显式覆盖/删后重加不覆盖/新建显式/更新系数/重复 409/自引用/0/负数/超精度 422/跨项目/双向单层/删除保护/齐套多场景/路由分发/权限拒绝/历史样品父项翻转/共享子件/HTML 标记/重复删除 404。

### 3.2 未落地（本阶段对象）

- **新库没有任何成本字段**：全部迁移 grep cost 0 命中；新 `Auxiliary_Materials` 只有 aux_name/unit/description（**旧辅材的 cost/currency_code 未迁入**）；加工/机械目录不存在；无 Internal_BOM/Cost_Settings/Exchange_Rates/Tax_Rates 对应物。
- 报价（Material_Quotations 新）：项目内、版本并发校验、税率快照、人工选默认；**首期不自动换算币种、不算税/成本**（FEATURES §1.4）。
- 测算只记数量无金额；招标清单项目级、数量/单价两位小数、不自动调差/折扣/税。
- 成本/BOM 预览、按栋成本设置、税/费/汇率、成本汇总与差异：均未实现（README 明示待开发）。

### 3.3 本次审查发现的新系统问题

| # | 发现 | 证据 | 处置建议 |
|---|---|---|---|
| N1 | `catalog()` 在 return 之后残留死代码 `$params['project_id'] = $projectId;` | MaterialCatalogService.php:55-56 | 无行为影响；下次触碰该文件时清理（本次只读未改） |
| N2 | 组成并发保护（FOR UPDATE 串行化、锁序）仅在 SQLite 等价结构验证；MySQL 行锁/死锁行为未实测 | lockCompositionMaterials :594-609 注释自述 | 纳入 MySQL 实测清单（§7） |
| N3 | 新契约 decimal(10,2) 与旧库实际 decimal(10,6) 不一致（契约注释称"对齐旧 BOM_Components.quantity"，对齐的是文件版不是库版） | 迁移 202611180001 注释；实际库 SHOW CREATE | 登记 B1，迁移前业务裁决 |

## 4. 新旧映射对照

| 维度 | 旧（实际库/运行代码） | 新（已确认契约/现状） | 判定 |
|---|---|---|---|
| 定制产品实体 | Internal_BOM 头（全局，按父材料，无唯一键） | 父材料行即产品，无独立实体；组成边挂材料目录 | 已确认（FEATURES §1.4）；未来"单表行→Internal_BOM 头+BOM_Components 组件"映射 |
| 材料组成边 | BOM_Components，decimal(10,6)，无唯一键，含自引用与重复边 | Material_Compositions，decimal(10,2)，唯一键，禁自引用 | **冲突 B1/B2**；单层约束为新增收紧（旧文件递归未上线，实际即单层） |
| 数量系数语义 | 每 1 父项单位所需子材料数量（含疑似损耗 1.1） | 同语义，无损耗率 | 等价；损耗显式化待确认（B14） |
| 单位 | 子材料 Materials.unit，自由文本，无换算 | 同 | 等价；结构化规格/换算未实现（旧亦无） |
| 辅材 | 目录自带 cost/currency，BOM 多行系数 | 目录无成本字段；未进组成 | 缺口：成本字段与 BOM 辅材行待本阶段（B16） |
| 机械 | 全局字典 cost/unit/currency，BOM 多行系数 | 不存在 | 缺口（B16） |
| 加工 | 全局字典，每 BOM 单项 proc_id×系数 | 不存在 | 缺口；单项 vs 多项待确认（B16） |
| 人工 | Labor_Tasks.cost×labor_quantity，每 BOM 单任务；Labor_Types 不进成本 | 不存在（labor.crew 是新增对象，不复制旧表） | 缺口；单任务 vs 多任务待确认（B16） |
| 分项管理费/利润 | Internal_BOM 费率 decimal(5,2)，逐项计取 | 不在本期 | 待本阶段；双重计取是否保留待确认（B4） |
| 楼栋级费率 | Cost_Settings 按楼栋，MAX(effective_date) 无今天上限 | 不存在 | 待本阶段；作用域/生效日语义待确认（B7/B17） |
| 取价 | default_quotation_id（明细）vs 最新报价（价格视图）分裂；97% 无默认报价 | 报价项目内、人工选默认 | 口径统一待确认（B6）；缺价策略待确认 |
| 汇率 | from→to 乘法，MAX 日期无上限，缺省 1.0 兜底 | 不自动换算（已确认首期边界） | 本阶段需确认方向/日期/精度/兜底（B7） |
| 税 | Cost_Settings.tax_rate 单乘数；confirmed_tax_rate 快照；HS/国籍设计未落地 | 报价税率快照已有 | 税链待确认（B3） |
| 报价推导 | 视图实时推导、不写回、无快照；行膨胀 | 无 | 本阶段核心；快照/版本策略待确认（B5/B18） |
| 投标 | 手工单价；展示乘调差→tier1→tier2；视图调差无最新过滤 | 招标清单项目级、不自动调差 | 调差/折扣是否进新系统待确认（B19） |
| 成本汇总/实际 | 触发器/过程全死；双汇总目标；分项列从未填 | 无 | 不得照抄；设计意图转需求确认（B11/B12） |
| 损耗 | 无字段；Waste_Log 事后日志（0 行） | 无 | 显式损耗率＝新增规则（B14） |
| 权限/CSRF/审计 | 大面积缺失 | 组成链完整（权限+项目+CSRF+审计） | 收紧（符合规范）；成本链沿用同模式 |
| 任务关联 | Task_BOM_Map 与 BOM 无关 | 无 | 不迁移为 BOM 关联（B15） |

## 5. 契约定义

### 5.1 已确认契约登记（2026-11-18，不得擅改）

材料组成与送样要求（FEATURES §1.4 全文为准）：父材料即定制产品；`Material_Compositions` 单层父—子；quantity decimal(10,2) >0，单位取子材料 unit；复合 FK 同项目、唯一键防重、禁自引用；双向单层约束；事务+升序行锁并发保护；首条组成翻转送样要求（explicit 标记门控、删边不翻转）；组成边不派生送样义务；齐套按最新有效送样记录判定、系数不参与；被引用材料拒硬删；权限复用 view/edit_materials；写入审计；材料导出列不变。**旧 Internal_BOM 人工/加工/管理费/利润字段不在该期，未来按"单表行→Internal_BOM 头+BOM_Components 组件"映射，不提前复制旧触发器或成本副作用。**

### 5.2 BOM 与成本阶段契约草案（供业务确认；未确认项一律不实现）

**用例边界**：项目内为定制父材料维护"成本组成"（材料子项已有；辅材/机械/加工/人工待建），维护成本设置与汇率/税参数，预览分项成本与整体成品报价；报价与测算/招标的关系按确认口径衔接。插件建议：新增 `bom.cost`（依赖 project.foundation + material.management 公开接口），或经业务确认后在 material.management 内扩展——**不得直读他插件私有表**（ARCHITECTURE §3）。

**输入与前置状态**
- 有效当前项目（服务端会话）+ 业务权限（建议新增 view_bom_cost/edit_bom_cost 等，权限名映射待确认 B20）；父/子材料、辅材均属当前项目。
- 父材料已有组成边（或本期新建）；成本组成行引用本项目目录。

**状态与不变量（草案）**
- 成本头与父材料 1:1（旧无唯一键是缺陷还是需求待确认 B13）；组成边唯一键沿用。
- 成本头/费率/汇率变更**不自动改写已确认报价**；是否引入报价快照/版本待确认（B18）。
- 同一规则单一权威执行位置：成本计算放应用服务（可测试），**不复制旧触发器**；旧死自动化一律不迁移为副作用。

**计算公式（旧行为基线，供对照；是否原样保留逐项待确认）**
```
分项成本(原币) = 数量系数 × 目录单价
分项成本(本位币) = ROUND(分项成本 × 汇率, 2)          -- 汇率缺失行为待确认(B7)
分项综合 = ROUND(分项×(1+头管理率) + 分项×头利润率, 2) -- 利润基数不含管理费(B4)
BOM 综合价 = SUM(分项综合)                            -- 逐项舍入后求和
成品报价 = ROUND(综合价 × (1+栋管理+栋运输+栋保险) × (1+栋利润) × (1+栋税), 2)
测算金额 = ROUND(测算数量 × 成品报价, 2)               -- 楼栋匹配与缺价策略待确认(B5/B6)
```
- 本位币：旧 ETB vs 新平台基准 CNY——**成本本位币必须业务确认**（B7），未确认前不自动换算（沿用已确认边界）。
- 精度：数量系数沿用 decimal(10,2)（B1 裁决前不改）；金额建议 decimal(18,4) 存储、展示 2 位（旧报价 18,4/目录 18,2/视图 ROUND2 的混合精度需统一口径，待确认）。
- 损耗：若确认引入显式损耗率，公式变为 数量系数×(1+损耗率)×单价，损耗率精度/上限/是否分项级待确认（B14）；**未确认前不加字段**。

**数据库副作用**：仅写本插件迁移声明的新表（成本头/辅材机械行/加工人工引用/成本设置/汇率/税参数）；不写旧表、不写共享目录的商业字段；事务包裹；关键写入重复请求保护（唯一键+幂等键）；审计含项目 ID 与字段前后值（补齐估算阶段 D9 同类缺口）。

**失败行为**：缺目录价/缺汇率/缺费率 → 明确报错或带警告的降级（**策略待确认 B6/B7**；旧"静默按 0/按 1:1"是缺陷行为，不得默认复刻）；跨项目/无权 → 404/403；并发冲突 → 409；所有失败可观察、详细上下文入受控日志。

**对外输出**：成本预览（分项+汇总，标明币种/费率来源/生效日）、成品报价、测算金额衔接；导出与页面同口径同权限；只读报表经查询契约，不反向写业务。

## 6. 差异记录（B1–B20）

> 状态均为「待确认」，除非注明。负责人为建议角色；结论不得由代码提交默认解决（AGENTS.md）。

| # | 差异/决策点 | 证据 | 影响 | 建议负责人 | 结论 |
|---|---|---|---|---|---|
| B1 | 数量系数精度：旧库 decimal(10,6)（有 1.1 数据）vs 新契约 decimal(10,2) | 实际库 SHOW CREATE；迁移 202611180001 | 迁移截断/拒收；契约"对齐旧精度"表述与库不符 | 业务方+开发 | 待确认：维持 10,2 还是放宽 |
| B2 | 旧 5 条 BOM 全含父材料自引用边；新契约禁自引用 | 实际库样本 id14/15/21/24/27/28 | 旧 BOM 无法直接映射；自引用在旧公式中计入父材料自身采购价，语义需澄清 | 业务方 | 待确认：自引用是数据错误还是"父项自身价"表达 |
| B3 | 税设计文档（CIF+五税阶梯+HS/国籍）vs 实际单一 tax_rate 乘数；confirmed_tax_rate 快照并存 | Tax_Calculation_Logics.md:27-132；实际库视图；Tax_Rates 1 行 | 照文档实现＝改变现行为；税链权威口径未定 | 业务方 | 待确认 |
| B4 | 管理费/利润双重计取（分项级+楼栋级）；利润基数三口径（小计/含管理费/楼栋再乘） | 实际库视图①③；api/bom_preview_cost.php:180；孤儿 calculate_bom_cost.php:226 | 报价数值口径核心决策 | 业务方 | 待确认：是否有意设计 |
| B5 | Final_BOM_Quote_View 行膨胀（无用 BOM_Components JOIN×测算行）；V_Estimated_Cost_Summary 按 bom_id 不带楼栋→跨楼栋串价；AVG 掩盖 | 实际库视图③④；bom41/42 重复行实测 | 报表金额失真；"复现 bug"还是修正需逐页面核对 | 业务方+开发 | 待确认 |
| B6 | 取价口径分裂：default_quotation_id（97% NULL→静默低估）vs 最新 updated_at 报价 | 实际库 BOM_Cost_Detail_View vs Material_Price_View；316/326 NULL | 预览与报表价不同；缺价策略（报错/警告/按0）未定 | 业务方 | 待确认 |
| B7 | 汇率/费率生效日无 ≤今天 上限（未来立即生效）；缺汇率 COALESCE 1.0 静默混币；无 USD→CNY；ETB↔CNY 不互逆；**成本本位币 ETB vs 新基准 CNY** | V_Currency_To_ETB/V_Latest_Cost_Settings；Exchange_Rates 7 行样本 | 金额级失真风险；本位币未定则全链公式无法定稿 | 业务方 | 待确认（阻塞公式定稿） |
| B8 | 孤儿/坏代码 calculate_bom_cost.php、cost_calculation_helper.php（不存在列、mysqli、'RMB'） | 两文件全文；grep 无调用方 | 不得作为等价依据 | 开发 | 已判定：排除（记录在案） |
| B9 | 辅材系数 UI 称百分比、代码直接乘（50→50 倍） | bom_editor.php:477 vs 视图/预览 API | 旧数据可能已按错误口径录入 | 业务方 | 待确认：旧辅材系数真实语义 |
| B10 | 精度截断：头费率 decimal(5,2) vs UI 0.01% 步进；Labor_Tasks.cost(10,2) vs step 0.0001；逐项 ROUND vs float | tables.sql:289-290,342；bom_editor.php:527；labor_task_manager.php:229 | 8.55%→9%；分位差累积 | 业务方+开发 | 待确认：新精度规范 |
| B11 | 死自动化清单（订单汇总/用量扣库存/超用/月度管理费/选供应商改价）全部运行即错；事件 ENABLED 静默失败 | §2.5 表；Actual_Usage/Waste_Log/Inventory_Transactions/Actual_Management_Cost 0 行 | 设计意图≠已验证行为；库存扣减时点/审批触发条件需重新确认 | 业务方+开发 | 待确认：哪些意图进新系统 |
| B12 | 双汇总目标 Project_Cost_Summary vs Purchase_Cost_Records（后者仅库内有）；分项列从未填；收货双触发器重复入账、无流水 | 实际库 SHOW CREATE/触发器；Inventory_Transactions 0 行 | 成本汇总权威来源未定 | 业务方 | 待确认 |
| B13 | Internal_BOM parent 无唯一键（可多 BOM）；BOM_Components 无唯一键已有重复边；Cost_Settings bulk_edit 覆盖历史、新建写 0 绕过默认 | tables.sql:883；样本 id27/28；manage_cost_settings.php:130-150 | 唯一性/历史追溯业务规则 | 业务方 | 待确认 |
| B14 | 损耗：全库无字段；唯一疑似 1.1 系数；Waste_Log 是事后日志且 0 行 | information_schema 扫描；样本 | 显式损耗率＝新增业务规则（用户本次点名"损耗"） | 业务方 | 待确认：是否引入、口径（系数内含 vs 显式率）、精度 |
| B15 | Task_BOM_Map 与 BOM 无关；proportion 死字段；删除警告文案失实 | tables.sql:617-625；includes/task_bom_mapping.php:80-82 | 勿当 BOM 关联迁移 | 业务方 | 待确认：proportion 语义/是否保留该映射 |
| B16 | 成本目录形态：新辅材无 cost/currency；加工/机械/人工任务目录不存在；旧加工/人工每 BOM 单项、机械/辅材多项 | 新迁移 grep cost 0 命中；旧 Internal_BOM 结构 | 本阶段建表前置决策；单项 vs 多项影响公式 | 业务方+开发 | 待确认 |
| B17 | Cost_Settings 作用域按楼栋 vs 新架构项目优先；effective_date 未来生效；同日期多行无唯一键 | 实际库；V_Latest_Cost_Settings | 费率作用域与生效语义 | 业务方 | 待确认 |
| B18 | 报价实时推导不写回、无快照/版本；BOM 改价即时波及全部报表 | grep 无写回代码 | 是否引入报价快照/确认态 | 业务方 | 待确认 |
| B19 | 投标调差率（仅实际库有列）+tier1/tier2 折扣（Final 已移除、投标展示仍乘）；V_Tender_Cost_Summary JOIN 无最新过滤 | manage_cost_settings.php:63；tender_list.php:184-191；实际库视图 | 调差/折扣是否进新招标/成本链 | 业务方 | 待确认 |
| B20 | 权限名映射：旧 view_bom_report/edit_bom/view_cost_settings/manage_cost_settings 等 vs 新权限集；旧大面积授权缺失属安全修复 | §2.7；新 plugin.json | 新权限受众覆盖需确认；修复不得照抄旧缺失 | 业务方+开发 | 待确认 |

**旧库文件 vs 实际库不同步备忘**（迁移前必须逐项核实，详见子任务证据 db_report*.txt）：BOM_Components 精度、Cost_Settings.tender_adjustment_rate、Material_Quotations 供应商路径、Tax_Rates 结构、Project_Cost_Summary/Purchase_Cost_Records 存在性、收货/用量触发器三版互异、BOM_Cost_Detail_View 递归未上线、V_Actual_Cost_ETB/ConvertCurrency 不存在、Material_Quotation_Mapping_View 硬编码外部库名、zz.sql 纯历史残留、events.sql 时间平移等 23 处。

## 7. 实施建议与验收

**拆分（一次任务一个边界，AGENTS.md）**
1. 业务确认会：B1/B2/B4/B6/B7/B14/B16/B17 为公式定稿阻塞项，优先裁决；其余可并行推进盘点。
2. 成本目录契约与建表（辅材成本字段/加工/机械/人工引用形态）——依赖 B16；迁移可审阅、增量安全（沿用 ProjectO_Migrations 台账）。
3. 成本头+组成成本行+预览计算服务（纯函数可测试）——依赖 B3/B4/B6/B7/B10/B14；固定输入/期望输出对照测试（用旧库 bom42 数值 3424.60→5887.74 作锚点样本，费率取样本 Cost_Settings）。
4. 成本设置/汇率/税参数维护（作用域与生效日按 B7/B17 结论）+权限/审计/并发保护。
5. 报价衔接与测算金额（B5/B18/B19 结论后）；报表只读契约。
6. 旧数据映射方案（B1/B2/B9/B13 清洗规则确认后单独审批执行；只进新空库，不碰旧生产库）。

**最小测试集（每步必含）**：正常/边界（精度、0/负系数、缺价缺汇率）/无权限/跨项目/失败回滚/重复请求/并发（MySQL FOR UPDATE 实测，补 N2）/迁移重复安全；历史计算固定对照（bom41/42/45 样本）。

**本次验证记录**
- 实际命令：`composer test`（WSL PHP 8.3，o/ 内）→ 12/12 通过（auth/project×3/document/material/platform/profile/labor-crew/login/projects/layout）。
- 旧库核实：只读 SHOW CREATE TABLE/VIEW、information_schema 触发器/过程/事件、COUNT/样本聚合；Final_BOM_Quote_View 公式经样本数值验证（3424.60×1.15×1.30×1.15=5887.74 ✓）。
- 新系统核对：迁移 202611180001/0002、plugin.json 路由/权限、MaterialCatalogService 组成全链、Plugin 权限门禁、测试断言逐条比对契约。
- **未覆盖/未验证**：MySQL 真实迁移与 FK/CHECK/行锁行为；旧页面未登录可达性的运行验证（代码证据已记，不据此断言生产被越权）；旧报表页面展示与视图行膨胀的逐页对照；B1–B20 全部业务结论。
- 本次未修改任何新旧代码/数据，未执行迁移；临时证据在会话临时目录（bom-review-a/b/main），不进项目仓库。

## 8. 未决事项汇总（需用户/业务方回复）

阻塞公式定稿：**B7（本位币/汇率规则）、B4（双重加成与利润基数）、B6（取价口径与缺价策略）、B14（损耗是否显式化）、B16（成本目录形态）、B3（税链）**。
阻塞数据映射：B1（精度）、B2（自引用边）、B9（辅材系数语义）、B13（唯一性/重复边清洗）。
其余 B5/B10–B12/B15/B17–B20 按 §7 顺序推进。以上未确认前，不实现对应成本逻辑、不新建成本字段、不执行任何迁移。
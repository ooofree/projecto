# Project O BOM 与成本 V2.1.2：定点修订稿

> 日期：2026-11-19。任务性质：只读定点修订（禁止开发与落库）。基准：《16_Project_O_BOM与成本_V2.1.1定点修订完整结构方案》；依据：用户对 V2.1.1 的审查结论（4 个 P0 问题＋4 项细节完善）。
> 本轮只处理审查列明的问题，不重新设计整体架构，不增加快照体系，不改变九条冻结原则，不碰 Material_Compositions 与样品管理契约，不决定任何未确认的成本公式/税率/利润/管理费/损耗/汇率规则/B16/B17。
> 未修改任何源码/数据库，未创建迁移文件/迁移 SQL/安装脚本，未开发任何功能。标注体系沿用：【设计提案】【源码已存在】【待业务确认】【待开发验证】。
> 本轮新核实事实：新系统 Material_Quotations 现有税相关字段仅 confirmed_tax_rate decimal(5,4)（迁移 202611010005:83），**无 tax_inclusive 列**；旧库 Material_Quotations 同样仅 confirmed_tax_rate（第一轮 SHOW CREATE 核实）。V2.1 的 tax_inclusive tinyint 是**未落库提案**，不是既有字段——P0-4 据此修正表述（§1.4）。

## 0. 修订范围对照

| 审查问题 | 落实位置 | 状态 |
|---|---|---|
| P0-1 跨类型唯一键冲突 | §1.1：删除 target_key，改类型化唯一索引 | 【设计提案】完成 |
| P0-2 汇率同日修正与唯一索引冲突 | §1.2：当前记录 UPDATE＋同事务审计 | 【设计提案】完成；B7 策略仍开放 |
| P0-3 指纹未入表＋输入不完整 | §1.3：入 bom_costings 字段表＋完整规范化输入集 | 【设计提案】完成 |
| P0-4 含税历史转换不能宣称无兼容问题 | §1.4：分来源转换原则＋事实澄清 | 【设计提案】完成；转换执行属 G3 |
| 细节 1 结果保存实际税口径 | §1.5：source_tax_status 入结果表 | 【设计提案】完成 |
| 细节 2 来源删除后同步规则 | §1.6：五条规则定稿 | 【设计提案】完成 |
| 细节 3 指纹/审计/结果职责 | §1.7：三件套职责边界 | 完成 |
| 细节 4 N7 唯一性非最终业务规则 | §1.8：保持待确认＋例外路径草案 | 【待业务确认】 |

## 1. 定点修订

### 1.1 P0-1：类型化唯一索引替换 target_key

**问题确认**：不同目录表自增 ID 可相同（Materials 15 / Auxiliary_Materials 15 / processing_catalog 15），COALESCE 生成的 target_key 无法区分类型，UNIQUE(project_id,target_key,…) 会把不同类型对象误判为同一对象。bom_costing_resources 的 resource_key 因唯一键含 resource_type 不受影响，保留。

**修订（item_pack_specs）**：
- **删除生成列 target_key 与 UNIQUE(project_id,target_key,pack_unit)**。
- 改为五个类型化唯一索引【设计提案】：
  - UNIQUE uq_pack_material (project_id,material_id,pack_unit)
  - UNIQUE uq_pack_aux (project_id,aux_id,pack_unit)
  - UNIQUE uq_pack_proc (project_id,proc_id,pack_unit)
  - UNIQUE uq_pack_mech (project_id,mech_id,pack_unit)
  - UNIQUE uq_pack_labor (project_id,labor_task_id,pack_unit)
- MySQL 唯一索引含 NULL 不去重：某行只有 material_id 非空时，其余四个索引因对应列为 NULL 不参与去重，**不会跨类型误判**；同类型同包装单位的重复仍被对应索引拦截。
- CHECK 五列恰一非空保留（§2.9）；类型化真实外键保留；不重新引入多态字段。

**修订（project_cost_prices）**：
- **删除生成列 target_key 与 UNIQUE(project_id,target_key)**。
- 改为四个类型化唯一索引【设计提案】：
  - UNIQUE uq_price_aux (project_id,aux_id)
  - UNIQUE uq_price_proc (project_id,proc_id)
  - UNIQUE uq_price_mech (project_id,mech_id)
  - UNIQUE uq_price_labor (project_id,labor_task_id)
- NULL 语义同上；UNIQUE(project_id,price_id)（供结果表复合 FK）保留。
- proc/mech/labor 三个索引的最终形态随 B16（目录项目化时列与外键同步升级）【待业务确认】。

**连带修订**：生成列清单（V2.1.1 §3.4）删除两行 target_key，仅余 cost_settings.building_key 与 bom_costing_resources.resource_key；唯一索引清单按 §2 更新。

### 1.2 P0-2：汇率改为"当前记录＋修正留痕"

**问题确认**：UNIQUE(from_currency,to_currency,effective_date) 与"行不可变、更正＝新行"互斥——同币对同生效日的修正无法插入。

**修订（exchange_rates）**【设计提案】：
- 唯一键保留：UNIQUE(from_currency,to_currency,effective_date)——语义改为**每币对每生效日仅一条当前有效记录**。
- **删除"行不可变"规则**；修正汇率＝UPDATE 原记录（rate/生效日等），同事务写 audit_logs：修改前后值、修改人、修改时间、修改原因、币对与生效日。
- 不新建汇率历史版本体系（与轻量化方向一致）；历史追溯由 audit_logs 承担（F7）。
- 已确认组价**接受新汇率值**时按既定规则同事务退 draft（§4.2 两类重算）；仅汇率行被修正但组价尚未接受（未重算）时，组价状态不变，页面提示"所用汇率已变化"。
- 明细行 fx_rate_id＋fx_rate_value 仍记录**计算时点实际使用值**（F8）：汇率行后续被 UPDATE 不影响已落明细的当时值；重算接受新值时更新明细并留痕。
- **B7 仍开放**：汇率方向、取值规则、缺汇率兜底、未来日期策略不在本轮决定【待业务确认】。
- 字段表其余不变（rate_id/from_currency/to_currency/rate/effective_date/created_by/时间戳）；增 updated_by int NOT NULL（FK→Users）以记录最近修改人【设计提案】。

### 1.3 P0-3：input_fingerprint 正式入表＋完整输入集

**修订一（文档一致性）**：bom_costings 字段表正式加入【设计提案】：
| 字段 | 类型 | 说明 |
|---|---|---|
| input_fingerprint | char(64) NULL | 最近一次计算所用**规范化完整输入集**的 SHA-256；未计算过为 NULL；仅当前态摘要，不是历史记录 |

**修订二（输入范围补全）**：指纹由**实际参与计算的全部输入**规范化生成，任何一项变化必须导致指纹变化；页面展示字段（名称快照/描述等）不纳入。规范化输入集【设计提案】：
1. 公式版本 formula_version、舍入规范版本 rounding_spec_version；
2. 每个组件行：child_material_id、**composition_factor（原始系数）**、**calculated_quantity（计算数量）**、计价/使用单位、所用包装规格（pack_size/pack_unit/base_unit）与量纲换算（from/to/factor）；
3. 每个资源行：resource_type＋目标 id、**quantity**、loss_rate（启用后）、计价单位、包装/换算输入；
4. 每行价格来源：quotation_id＋**该报价 tax_status**＋purchase_unit_price＋currency_code＋pricing_unit；或 project_cost_price_id＋unit_price＋currency＋pricing_unit＋status；或 manual_price＋manual_confirmed_by；
5. 每行实际采用的**税率与税务参数**（tax_rate 及公式组确认后定义的其他税参数）；
6. 汇率输入：fx_rate_id＋**rate 值**（汇率行被 UPDATE 时值变化→指纹变化，即使 id 不变）＋币对；
7. 费率输入：所采用 cost_settings 的 setting_id＋**各费率当前值**（管理/利润/税/运输/保险；配置 UPDATE 时值变化→指纹变化）＋作用域；
8. 其他实际参与计算的规则输入（公式组确认后逐项列入，如损耗策略开关、本位币）。
- **值与 id 双纳入**：凡上游行可被 UPDATE 的输入（汇率/费率/报价），指纹必须含其当前值，不能只含 id——否则"接受新值"会被误判为纯重算。
- 规范化规则：固定字段顺序、固定精度文本表示、行按稳定键排序后拼接哈希【设计提案，细节随开发评审】。
- 指纹不建快照表/历史账本；历史变更走 audit_logs（§1.7）。

### 1.4 P0-4：含税状态历史转换原则（修正"无迁移兼容问题"表述）

**事实澄清（本轮核实）**：
- 新系统 Material_Quotations **现有字段无 tax_inclusive**（仅 confirmed_tax_rate，迁移 202611010005:83）；旧库同样无此列。
- V2.1 提出的 tax_inclusive tinyint 是**未落库提案**；V2.1.1 已用 tax_status 三态取代该提案。因此"新库直接加 tax_status 列"确实不涉及 tax_inclusive 数据转换——但 V2.1.1"无迁移兼容问题"的表述**范围过宽**：旧系统报价数据导入（G3）仍存在税口径歧义，必须定义转换原则。

**修订（未来数据转换原则，本轮不创建迁移）**【设计提案＋待业务确认】：
| 来源情形 | tax_status 处理 |
|---|---|
| 假想的 tax_inclusive=1（若任何环境曾落库该提案字段） | inclusive |
| tax_inclusive=0 且有可靠证据确认不含税（人工确认记录/供应商书面口径） | exclusive |
| tax_inclusive=0 且无法确认真实口径（含旧系统默认值） | **unknown**，不得自动转 exclusive |
| 旧库实际数据：仅有 confirmed_tax_rate 快照（0 或 0.15 等），无含税标记 | 税率快照**不证明**价内含税与否；一律 unknown，除非有可靠证据；confirmed_tax_rate 原样保留供核对 |
| 新录入报价 | 必须明确 inclusive/exclusive；是否允许暂存 unknown 待 A4【待业务确认】 |
- unknown 不得被系统静默转换为 exclusive（V2.1.1 规则保留）；正式计算需税口径而遇 unknown→该行不完整/阻断（§4.1）。
- 转换执行属 G3 迁移闸门：需逐条证据核验与业务签收，本轮仅定原则。
- 税率、税基、可抵扣规则本轮不决定【待业务确认】。

### 1.5 细节 1：结果表保存实际采用的税口径

cost_line_results 增列【设计提案】：
| 字段 | 类型 | 说明 |
|---|---|---|
| source_tax_status | enum('unknown','inclusive','exclusive') NULL | **本次计算实际采用的报价税口径**（计算时点从来源报价读取）；人工价格/项目成本价来源时按各自税口径字段取值（其税口径字段随 A4/B16 评审定义，未定义前 NULL＋不完整标记） |
- 作用：报价记录后续变化时，当前结果仍有明确税口径依据（F8）；**不替代** Material_Quotations.tax_status（来源当前态）——两者一个是"当时用了什么"，一个是"来源现在是什么"。
- 纳入指纹输入集（§1.3 第 4 项）；税基/税率/可抵扣规则仍不决定。
- CHECK：quotation_id 非空时 source_tax_status 必须非空（报价来源必有口径记录，unknown 也是记录）【设计提案】。

### 1.6 细节 2：组成来源删除后的同步规则（定稿）

在 V2.1.1 §4.4 基础上明确五条【设计提案】：
1. 来源组成被删除（Material_Compositions 行删除）→ FK SET NULL 仅清 source_composition_id；**不自动删除 BOM 副本**。
2. 副本保留原始系数 composition_factor、名称/单位快照、source_composition_key 文本（来源不可变标识永存）。
3. 下次主动同步时，差异预览**明示"来源已不存在"**（按 source_composition_id IS NULL 且 key 有值识别）。
4. 对应副本行**仅在用户确认后删除**；未确认则保留（保留行继续参与计算，其来源追溯靠 key 文本）。
5. 同步操作记录前后差异（含来源删除项的处理决定）入 audit_logs；同步属实质修改，confirmed 组价同事务退 draft。
- SET NULL 仍是成本域唯一外键删除例外，范围仅限来源追溯字段；业务引用全 RESTRICT 不变。该例外维持"待技术评审确认"状态。

### 1.7 细节 3：指纹/审计/结果三件套职责边界

| 机制 | 职责 | 不承担 |
|---|---|---|
| input_fingerprint（组价头） | 判断**当前计算输入是否变化**（纯重算 vs 实质修改的唯一判据） | 不记录谁改的/改了什么历史；不是快照 |
| audit_logs | 记录**谁在什么时候做了什么修改**（前后关键值/原因/作用域） | 不判断输入一致性；不存当前结果 |
| cost_line_results | 保存**当前计算结果**（含来源/汇率值/费率值/税口径/公式版本） | 不存历史序列；不判断变更 |
- 三者不可相互替代；不新增快照/版本链/独立历史账本（F4/F5/F7）。
- 事务关系：实质修改事务内同时更新三者（结果重算＋指纹更新＋审计写入），失败整体回滚（V2.1.1 §4.2 保留）。

### 1.8 细节 4：N7 唯一性保持待业务确认

- UNIQUE(costing_id,child_material_id) 与 UNIQUE(costing_id,resource_type,resource_key) **是设计默认，不是最终业务规则**【待业务确认】。
- 已知可能不适用的场景：同种辅材在不同施工部位采用不同数量/计算依据；同一加工项分工序段计费等。
- 若业务确认允许多行：唯一键加入行序号（如 line_no）或部位/依据标识，具体形态随 N7 结论定；**本轮不擅自放开或收紧**。
- 开发阶段在 N7 确认前按默认唯一实现，但测试与界面不把"唯一"宣传为业务定论。

## 2. 修订后完整数据库结构（13 张新建＋2 张扩展）

> 未列明的字段/约束与 V2.1.1 §2 完全一致（本轮不改动）；下文给出受影响表的完整字段表，其余表以"同 V2.1.1"标注其完整定义位置。通用约定不变：MySQL 8.0.16+、项目复合 FK、成本域 RESTRICT（例外 FK-C2 SET NULL）、UTC、精度约定。

### 2.1 bom_costings（组价头；本轮增 input_fingerprint）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| costing_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects |
| parent_material_id | int NOT NULL | 复合 FK (project_id,parent_material_id)→Materials(project_id,material_id) |
| status | enum('draft','confirmed') NOT NULL DEFAULT 'draft' | F5/F6 |
| calc_status | enum('incomplete','calculable','complete') NOT NULL DEFAULT 'incomplete' | 与 status 分离 |
| **input_fingerprint** | **char(64) NULL** | **本轮正式入表（P0-3）；规范化完整计算输入 SHA-256；仅当前态** |
| confirmed_by | int NULL | FK→Users |
| confirmed_at | timestamp NULL | |
| created_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | updated_at 兼乐观锁 |
- UNIQUE (project_id,parent_material_id)；UNIQUE (project_id,costing_id)。

### 2.2 bom_costing_components（同 V2.1.1 §2.2，含来源删除规则 §1.6）
字段：component_line_id PK；costing_id/project_id（复合 FK→组价头）；child_material_id（复合 FK→Materials）；source_composition_id int NULL（FK→Material_Compositions.composition_id **ON DELETE SET NULL**）；source_composition_key varchar(120) NULL；composition_factor decimal(10,2) NOT NULL CHECK >0；calculated_quantity decimal(18,6) NOT NULL CHECK >0；calc_basis varchar(255) NULL；quantity_adjusted_by int NULL（FK→Users）/quantity_adjusted_at；unit_snapshot/name_snapshot；synced_at/synced_by（FK→Users）；created_at/updated_at。
- UNIQUE (project_id,costing_id,component_line_id)；UNIQUE (costing_id,child_material_id)【N7 待确认，§1.8】。

### 2.3 bom_costing_resources（同 V2.1.1 §2.3，resource_key 保留）
字段：resource_line_id PK；costing_id/project_id（复合 FK）；resource_type enum('auxiliary','processing','mechanical','labor','parent_self')；aux_id/proc_id/mech_id/labor_task_id/material_id 可空类型化 FK（aux/material 复合项目 FK；proc/mech/labor 待 B16）；resource_key 生成列 COALESCE(五列) STORED；quantity decimal(18,6) CHECK >0；loss_rate decimal(6,4) NULL【B14 前恒 NULL】；unit_snapshot/name_snapshot；created_at/updated_at。
- CHECK 恰一非空＋类型匹配；UNIQUE (project_id,costing_id,resource_line_id)；UNIQUE (costing_id,resource_type,resource_key)【N7 待确认】；parent_self 受控写入（服务从头表取值＋写前校验，可选守卫触发器）【待开发验证】。

### 2.4 cost_line_results（本轮增 source_tax_status）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| result_id | int AI | PK |
| costing_id / project_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings(project_id,costing_id) |
| component_line_id | int NULL | 复合 FK (project_id,costing_id,component_line_id)→组件表 |
| resource_line_id | int NULL | 复合 FK (project_id,costing_id,resource_line_id)→资源表 |
| quantity | decimal(18,6) NOT NULL | 实际计算数量 |
| unit_price | decimal(18,4) NOT NULL | CHECK >0；实际使用单价 |
| currency_code | char(3) NOT NULL | |
| pricing_unit | varchar(20) NULL | 计价单位 |
| quotation_id | int NULL | 复合 FK (project_id,quotation_id)→Material_Quotations(project_id,quotation_id) |
| project_cost_price_id | int NULL | 复合 FK (project_id,project_cost_price_id)→project_cost_prices(project_id,price_id) |
| manual_price | decimal(18,4) NULL | 人工价 |
| manual_confirmed_by | int NULL | FK→Users |
| **source_tax_status** | **enum('unknown','inclusive','exclusive') NULL** | **本轮新增（细节 1）：计算时点实际采用的税口径；quotation_id 非空时必须非空（CHECK）** |
| fx_rate_id | int NULL | FK→exchange_rates(rate_id) |
| fx_rate_value | decimal(18,8) NULL | 计算时点实际汇率值 |
| loss_rate / mgmt_rate / profit_rate / tax_rate / trans_rate / ins_rate | decimal(6,4) NULL | 实际所用值 |
| subtotal / mgmt_amount / profit_amount / tax_amount / comprehensive | decimal(18,4) NOT NULL DEFAULT 0 | 公式组确认后实现计算 |
| formula_version / rounding_spec_version | varchar(20) NOT NULL | |
| calculated_at | timestamp NOT NULL | |
| calculated_by | int NOT NULL | FK→Users |
- CHECK：行引用恰一；价格来源恰一（报价/项目成本价/人工＋确认人互斥）；fx 成对；unit_price>0；**quotation_id 非空→source_tax_status 非空**。
- UNIQUE (costing_id,component_line_id)；UNIQUE (costing_id,resource_line_id)（NULL 不去重，分别唯一）。

### 2.5 cost_settings（同 V2.1.1 §2.5，无版本链）
setting_id PK；project_id FK；scope_type enum('project','building')；building_id NULL（复合 FK (building_id,project_id)→Buildings）；building_key 生成列 IFNULL(building_id,0) STORED；management_fee_rate/profit_rate/tax_rate/transportation_rate/insurance_rate decimal(6,4) NOT NULL DEFAULT 0【数值语义待公式组】；created_by/updated_by FK→Users；created_at/updated_at。
- UNIQUE (project_id,building_key)；CHECK scope↔building_id；修改＝UPDATE＋同事务审计；继承待 B17。

### 2.6 exchange_rates（本轮修订：当前记录＋修正留痕）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| rate_id | int AI | PK |
| from_currency / to_currency | char(3) NOT NULL | |
| rate | decimal(18,8) NOT NULL | CHECK >0 |
| effective_date | date NOT NULL | |
| created_by | int NOT NULL | FK→Users |
| **updated_by** | **int NOT NULL** | **本轮新增：最近修改人 FK→Users** |
| created_at / updated_at | timestamp | |
- UNIQUE (from_currency,to_currency,effective_date)：**每币对每生效日一条当前记录**。
- **修正＝UPDATE 原记录＋同事务 audit_logs（前后值/人/时间/原因/币对/生效日）**；不建汇率历史版本体系。
- 已确认组价接受新汇率值→同事务退 draft；明细 fx_rate_value 保留计算时点值。
- 方向/取值/兜底/未来日期策略待 B7【待业务确认】。

### 2.7 units / 2.8 unit_conversions（同 V2.1.1，无改动）
units：unit_code PK/unit_name/dimension enum。unit_conversions：conversion_id PK/from_unit/to_unit FK→units/factor decimal(18,8) CHECK >0/UNIQUE(from_unit,to_unit)。

### 2.9 item_pack_specs（本轮修订：类型化唯一索引）
字段：spec_id PK；project_id FK；material_id/aux_id/proc_id/mech_id/labor_task_id 可空类型化 FK（material/aux 复合项目 FK；proc/mech/labor 待 B16）；pack_unit varchar(20) NOT NULL；base_unit varchar(20) NOT NULL；pack_size decimal(18,6) NOT NULL CHECK >0；created_by/updated_by FK→Users；created_at/updated_at。
- **删除 target_key 生成列**。
- CHECK 五列恰一非空。
- **五个类型化唯一索引**：(project_id,material_id,pack_unit)/(project_id,aux_id,pack_unit)/(project_id,proc_id,pack_unit)/(project_id,mech_id,pack_unit)/(project_id,labor_task_id,pack_unit)。

### 2.10 project_cost_prices（本轮修订：类型化唯一索引）
字段：price_id PK；project_id FK；aux_id/proc_id/mech_id/labor_task_id 可空类型化 FK（aux 复合项目 FK；proc/mech/labor 待 B16）；unit_price decimal(18,4) NOT NULL CHECK >0；currency_code char(3) NOT NULL；pricing_unit varchar(20) NOT NULL；valid_from/valid_to date NULL；status enum('active','suspended') DEFAULT 'active'；created_by/updated_by FK→Users；created_at/updated_at。
- **删除 target_key 生成列**。
- CHECK 四列恰一非空。
- **四个类型化唯一索引**：(project_id,aux_id)/(project_id,proc_id)/(project_id,mech_id)/(project_id,labor_task_id)。
- UNIQUE (project_id,price_id)（供结果表复合 FK）。

### 2.11–2.13 资源目录三张（同 V2.1.1，所有权待 B16）
processing_catalog(proc_id PK)/mechanical_catalog(mech_id PK)/labor_task_catalog(labor_task_id PK)：name/unit/description/created_by/updated_by/时间戳；不带价格；共享 vs 项目化待 B16。

### 2.14 既有表扩展（2 张）
**Material_Quotations**：valid_from/valid_to date NULL；status enum('active','suspended','expired') DEFAULT 'active'【A4 待确认】；**tax_status enum('unknown','inclusive','exclusive') NOT NULL DEFAULT 'unknown'**；pricing_unit varchar(20) NULL。既有字段不动；历史转换原则见 §1.4（G3 执行）。
**audit_logs**：project_id int NULL＋KEY(project_id,created_at)；entity_type varchar(50) NULL；entity_id int NULL＋KEY(entity_type,entity_id)；details JSON {before,after,reason,scope}；Support::audit 上下文扩展，既有调用兼容【待开发验证】。

## 3. 约束清单变更（相对 V2.1.1 §3）

| 类别 | 变更 |
|---|---|
| 生成列 | **删除** item_pack_specs.target_key、project_cost_prices.target_key；保留 cost_settings.building_key、bom_costing_resources.resource_key |
| 唯一索引 | **删除** UQ-P1(project_id,target_key,pack_unit)、UQ-PC1(project_id,target_key)；**新增** uq_pack_material/aux/proc/mech/labor（5 个）、uq_price_aux/proc/mech/labor（4 个）；其余不变 |
| CHECK | **新增** CK-X5：quotation_id 非空→source_tax_status 非空；其余不变 |
| 外键 | exchange_rates 增 updated_by→Users；其余不变（FK-C2 SET NULL 例外保留，范围仅来源追溯字段） |
| 字段 | bom_costings 增 input_fingerprint；cost_line_results 增 source_tax_status；exchange_rates 增 updated_by |
- 完整外键/唯一/CHECK 清单以 V2.1.1 §3 为底叠加本表变更；复合 FK 列序与目标唯一键对齐结论不变（Materials/Aux/Quotation/Buildings 目标键已核实）。

## 4. 状态与事务规则（继承 V2.1.1 §4，本轮强化点）

- 两类重算规则表不变（纯重算保持 confirmed；接受新价格/成本价/汇率/费率/改数量/改引用→同事务退 draft；仅查看不变）。
- **指纹判据强化**：输入一致性以 §1.3 完整规范化输入集为准；汇率/费率/报价行被 UPDATE 时**值变化即指纹变化**（id 不变也判实质修改）；任何参与计算的输入变化而指纹未变属设计缺陷，测试必须覆盖（§6 验收）。
- 三件套职责（§1.7）：指纹判变化、审计记变更、结果存当前；同事务更新、失败整体回滚；不得半完成态。
- 组成同步按 §1.6 五条规则执行；来源删除项须用户确认才删副本。
- calc_status 与 status 严格分离；缺价/缺汇率/单位不匹配/需税口径而 unknown→不完整/阻断，禁静默零价。
- 并发：updated_at 乐观锁＋确认单行原子＋事务；沿用报价 version 乐观锁。

## 5. 源码差异与迁移清单（继承 V2.1.1 §5/§6，本轮变更点）

- 新建 **13 张**＋既有扩展 **2 张**，统计不变；创建依赖顺序不变。
- 本轮结构变更不改变 B16/公式组依赖关系：目录三表与 proc/mech/labor 引用键仍待 B16；金额计算/费率语义/损耗/舍入仍待公式组；**结构可先评审，计算实现仍被 G2 阻止**。
- 源码差异新增点：exchange_rates 修正路径（UPDATE＋审计）替代"新增行"；报价保存需采集税口径（新录入明确 inclusive/exclusive，暂存 unknown 待 A4）；重算服务需实现完整规范化指纹（含上游当前值读取）。
- 本轮全部仍为设计，未创建任何迁移文件/SQL/脚本。

## 6. 逐项验收与下一阶段条件

### 6.1 本轮修订验收（设计自检）
| 项 | 验收点 | 结果 |
|---|---|---|
| P0-1 | 跨类型同 ID 场景（Materials/Aux/proc 均 id=15）不再冲突；同类型重复仍被拦截；无多态字段 | ✅ 设计满足；MySQL 实测【待开发验证】 |
| P0-2 | 同币对同日修正可执行（UPDATE）；审计含前后值/原因；接受新值退 draft；无历史版本体系 | ✅ 设计满足 |
| P0-3 | 指纹在 §2.1 字段表；输入集覆盖系数/计算数量/资源数量/计价单位/换算/含税状态/税参数/汇率值/费率值；值变指纹变 | ✅ 设计满足；规范化细节随开发评审 |
| P0-4 | 不再宣称"无迁移兼容问题"；分来源转换原则（1→inclusive；0 有证据→exclusive；0 无证据→unknown；旧库仅税率快照→unknown）；本轮不创建迁移 | ✅ 满足 |
| 细节 1 | source_tax_status 入结果表＋CHECK＋入指纹；不替代来源当前态 | ✅ |
| 细节 2 | 来源删除五条规则（不自动删副本/保留快照/key/明示来源不存在/用户确认才删/差异审计） | ✅ |
| 细节 3 | 三件套职责表，互不替代，无快照/账本 | ✅ |
| 细节 4 | N7 保持待确认，唯一键标注为设计默认 | ✅ |

### 6.2 进入最终数据库结构审查的条件
1. 本稿（V2.1.2）提交最终结构审查：重点核对类型化唯一索引的 NULL 语义、指纹输入集完整性、SET NULL 例外、含税转换原则。
2. 审查通过后才进入 DDL 设计阶段；**本轮与最终审查前均不编写 DDL、不创建迁移**。
3. 公式组（B3/B4/B6/B7/B10/B14）与 B16 按各自闸门单独控制，不因结构审查通过而提前实现计算。
4. 开发阶段 MySQL 实测（类型化唯一索引跨类型场景/指纹值变检测/UPDATE 汇率审计/SET NULL 追溯）为退出条件【待开发验证】。

## 7. 仍开放的业务决策（不变）
公式组 B3/B4/B6/B7/B10/B14；B16 目录所有权；B17 费率继承；A4 报价状态/税口径录入（含暂存 unknown）；A7 calculable 可否确认；N7 多行例外；单位换算规则；迁移组 B1/B2/B9/B13/B5（G3）。本轮未越权确定任何一项。

## 8. 边界声明
- 本轮只读定点修订：未修改任何源码/数据库/迁移，未创建迁移文件/SQL/脚本，未开发功能，未执行数据转换。
- 只处理审查列明的 4＋4 问题；九条冻结原则、Material_Compositions/样品/默认报价/招标契约零改动；未增加快照体系；公式/税率/利润/管理费/损耗/汇率规则/B16/B17 未定案。
- 全文按【设计提案】【源码已存在】【待业务确认】【待开发验证】标注；未把未验证设计描述为已实现或已通过 MySQL 实测。
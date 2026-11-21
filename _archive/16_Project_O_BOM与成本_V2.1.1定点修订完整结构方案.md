# Project O BOM 与成本 V2.1.1：定点修订完整结构方案

> 日期：2026-11-19。任务性质：只读设计修订（禁止开发与落库）。基准：《15_Project_O_BOM与成本_V2.1数据库结构定稿与源码差异分析》；依据：用户《V2.1.1 定点修订 Agent 任务书》十项修订要求。
> 本轮未修改任何 PHP/SQL/JS/CSS 或数据库，未创建迁移文件/迁移 SQL/安装脚本，未开发任何插件/页面/服务/接口/计算逻辑，未执行数据迁移或清洗。成本公式、税率、利润、管理费、损耗、汇率规则、B16 所有权均未代为决定。Material_Compositions、样品管理、默认报价人工选择、招标首期契约零改动。
> **标注体系（全文强制）**：【设计提案】＝本轮新设计，未实现；【源码已存在】＝已只读核实的现有能力；【待业务确认】＝开放决策；【待开发验证】＝需开发阶段实测（含全部 MySQL 实测）。
> 任务书附件在 §4.7（exchange_rates）处截断；§4.7 之后的目标结构按任务书 §1.1 输出要求与 §3 十项修订补全，补全处均标注【设计提案】。
> 关键 DDL 事实（前轮已只读核实，本轮直接引用）：Material_Compositions 主键 **composition_id int AI**，UNIQUE(project_id,parent_material_id,child_material_id)，quantity decimal(10,2)（迁移 202611180001）；Materials UNIQUE(project_id,material_id)、Auxiliary_Materials UNIQUE(project_id,aux_id)、Material_Quotations UNIQUE(project_id,quotation_id) 与 (project_id,material_id,quotation_id)、Buildings UNIQUE(building_id,project_id)（uq_building_id_project）；audit_logs(id,user_id,action,details text,ip_address,user_agent,created_at)（202611010001_core.sql:64-74）；Support::audit(PDO,action,details)（Support.php:216-235）；PluginServiceRegistry 契约 uses 声明强制；MySQL 8.0.46 支持 CHECK/生成列。**复合外键列序必须与目标唯一键列序一致**，本轮已逐项对齐（§3）。

## 0. 十项修订落实对照

| 修订 | 要求 | 落实位置 | 状态 |
|---|---|---|---|
| 一 | cost_line_results 去多态（component_line_id/resource_line_id 恰一＋复合 FK） | §2.4、§3 R 组 | 【设计提案】完成 |
| 二 | 价格来源去多态（quotation_id/project_cost_price_id/manual_price＋确认人） | §2.4、§3 R 组 | 【设计提案】完成 |
| 三 | item_pack_specs/project_cost_prices 类型化外键 | §2.9/§2.10、§3 P/C 组 | 【设计提案】完成；proc/mech/labor 目标待 B16 |
| 四 | composition_factor(10,2) 与 calculated_quantity(18,6) 分离 | §2.2、§4.4 | 【设计提案】完成；换算公式待单位规则确认 |
| 五 | 报价含税三态 unknown/inclusive/exclusive | §2.14 | 【设计提案】完成；暂存 unknown 策略待 A4 |
| 六 | cost_settings 当前配置＋审计，删版本链 | §2.5、§4.5 | 【设计提案】完成；继承待 B17 |
| 七 | bom_costings(project_id,costing_id) 唯一索引 | §2.1、§3 | 【设计提案】完成 |
| 八 | 纯重算 vs 接受新输入重算的状态差异 | §4.1–§4.3 | 【设计提案】完成 |
| 九 | 组成同步来源与差异审计 | §2.2、§4.4 | 【设计提案】完成；来源键已核实 composition_id |
| 十 | 新建 13 张＋既有扩展 2 张统计与迁移清单 | §5 | 完成 |

## 1. 九条冻结原则保持声明

F1–F9 全部保持（任务书 §二），结构落点：F1→uq_costing_project_parent；F2→组件行与 parent_self 资源行并存、无 cost_mode、系统仅提示可能重复计价不阻止；F3→组件/资源分表；F4→无快照表、当前态明细；F5→draft/confirmed 两态；F6→实质修改同事务退草稿；F7→cost_line_results＋audit_logs 复用；F8→来源/汇率值/费率值/公式与舍入版本落明细；F9→project_id NOT NULL＋复合 FK＋RESTRICT。补充保持：组价创建复制 Material_Compositions；目录变化不自动覆盖组价（主动同步）；不复制旧系统错误触发器/过程/视图/跨楼栋串价。

## 2. 修订后完整数据库结构（13 张新建表，逐表全字段）

> 通用【设计提案】：MySQL 8.0.16+；utf8mb4_unicode_ci；UTC 时间戳；成本域外键一律 ON DELETE RESTRICT、ON UPDATE RESTRICT（无 CASCADE）；created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP，updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP（各表相同，下文省略重复描述）；用户外键目标 Users(user_id)【源码已存在】。精度沿用 V2.1：金额 18,4、费率 6,4、汇率 18,8、计算数量 18,6、原始系数 10,2。

### 2.1 bom_costings（组价头）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| costing_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects |
| parent_material_id | int NOT NULL | 复合 FK (project_id,parent_material_id)→Materials(project_id,material_id) |
| status | enum('draft','confirmed') NOT NULL DEFAULT 'draft' | F5；F6 实质修改同事务退 draft |
| calc_status | enum('incomplete','calculable','complete') NOT NULL DEFAULT 'incomplete' | 计算完整性，与 status 严格分离（§4.1） |
| confirmed_by | int NULL | FK→Users |
| confirmed_at | timestamp NULL | |
| created_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | updated_at 兼作乐观锁依据【设计提案】 |
- UNIQUE uq_costing_project_parent **(project_id,parent_material_id)**（F1）。
- UNIQUE uq_costing_project_id **(project_id,costing_id)**（修订七，供下游复合 FK；列序与下游引用一致）。

### 2.2 bom_costing_components（技术 BOM 组件副本）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| component_line_id | int AI | PK |
| costing_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings(project_id,costing_id) |
| project_id | int NOT NULL | 同上 |
| child_material_id | int NOT NULL | 复合 FK (project_id,child_material_id)→Materials(project_id,material_id) |
| source_composition_id | int NULL | **来源 Material_Compositions.composition_id（已核实主键名）**；FK→Material_Compositions(composition_id) **ON DELETE SET NULL**（修订九第 8 条：来源删除不丢副本来源信息，见下） |
| source_composition_key | varchar(120) NULL | 来源不可变文本：`composition_id#父材料id#子材料id#原系数`（创建/同步时写入）；来源行删除后仍可追溯【设计提案】 |
| composition_factor | decimal(10,2) NOT NULL | **原始组成系数，冻结契约精度**；CHECK >0；只能来自 Material_Compositions 或同步，不被换算覆盖（修订四） |
| calculated_quantity | decimal(18,6) NOT NULL | 计算/换算后数量；CHECK >0；默认＝composition_factor（无换算时）；换算依据落 calc_basis |
| calc_basis | varchar(255) NULL | 计算数量依据文本（换算链/人工调整说明）；**单位换算规则未确认前不得推导换算公式**【待业务确认】 |
| quantity_adjusted_by | int NULL | 人工调整计算数量的操作人（FK→Users）；区分"同步变化"与"人工调整"（修订九） |
| quantity_adjusted_at | timestamp NULL | |
| unit_snapshot | varchar(50) NOT NULL | 子材料单位文本快照 |
| name_snapshot | varchar(255) NOT NULL | 子材料名称快照 |
| synced_at | timestamp NULL | 最近同步时间（创建时＝创建时间） |
| synced_by | int NULL | FK→Users |
| created_at / updated_at | timestamp | |
- UNIQUE uq_component_line **(project_id,costing_id,component_line_id)**（供结果表复合 FK）。
- UNIQUE uq_component_child **(costing_id,child_material_id)**（N7 默认唯一保留【待业务确认】多行例外）。
- **来源删除追溯规则（修订九第 8 条）**：source_composition_id 外键 SET NULL＋source_composition_key 文本永存；副本的 child_material_id/composition_factor/快照独立存活，不因来源删除丢失。SET NULL 是来源追溯字段的唯一例外，业务引用（材料/组价）仍全 RESTRICT【设计提案，需评审确认该例外】。

### 2.3 bom_costing_resources（成本资源明细）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| resource_line_id | int AI | PK |
| costing_id / project_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings(project_id,costing_id) |
| resource_type | enum('auxiliary','processing','mechanical','labor','parent_self') NOT NULL | |
| aux_id | int NULL | 复合 FK (project_id,aux_id)→Auxiliary_Materials(project_id,aux_id)【源码已存在目标键】 |
| proc_id | int NULL | FK→processing_catalog(proc_id)；**目标表所有权/是否加项目复合 FK 待 B16**【待业务确认】 |
| mech_id | int NULL | FK→mechanical_catalog(mech_id)；同上待 B16 |
| labor_task_id | int NULL | FK→labor_task_catalog(labor_task_id)；同上待 B16 |
| material_id | int NULL | parent_self 用；复合 FK (project_id,material_id)→Materials(project_id,material_id) |
| resource_key | int GENERATED ALWAYS AS (COALESCE(aux_id,proc_id,mech_id,labor_task_id,material_id)) STORED | 唯一性辅助 |
| quantity | decimal(18,6) NOT NULL | CHECK >0 |
| loss_rate | decimal(6,4) NULL | **B14 未确认前恒 NULL**【待业务确认】 |
| unit_snapshot / name_snapshot | varchar(50)/varchar(255) NOT NULL | |
| created_at / updated_at | timestamp | |
- CHECK chk_resource_exactly_one：五列恰一非空。
- CHECK chk_resource_type_match：resource_type 与非空列对应（auxiliary↔aux_id、processing↔proc_id、mechanical↔mech_id、labor↔labor_task_id、parent_self↔material_id）。
- UNIQUE uq_resource_line **(project_id,costing_id,resource_line_id)**（供结果表复合 FK）。
- UNIQUE uq_resource_key **(costing_id,resource_type,resource_key)**（N7 默认保留【待业务确认】）。
- **parent_self 一致性（修订三/任务书 4.4）**：material_id 必须＝组价头 parent_material_id。纯声明式约束无法跨表表达，采用**受控写入机制**：服务层写入时从头表读取父材料 id 赋值（不接受客户端传入）＋写入前 SELECT 头表校验；评审可选追加轻量守卫触发器（仅拒绝不一致写入，不承载业务计算）。**标注【设计提案＋待开发验证】，不声称纯 DB 声明式保证**。
- 目录与价格分离：本表只引用目录项，不含价格字段；辅材沿用 Auxiliary_Materials，**不增成本字段**（任务书明确）。

### 2.4 cost_line_results（当前态计算结果；修订一/二核心）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| result_id | int AI | PK |
| costing_id / project_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings(project_id,costing_id) |
| component_line_id | int NULL | 复合 FK **(project_id,costing_id,component_line_id)**→bom_costing_components(project_id,costing_id,component_line_id) |
| resource_line_id | int NULL | 复合 FK **(project_id,costing_id,resource_line_id)**→bom_costing_resources(project_id,costing_id,resource_line_id) |
| quantity | decimal(18,6) NOT NULL | 实际计算数量（来自组件 calculated_quantity 或资源 quantity） |
| unit_price | decimal(18,4) NOT NULL | **实际使用单价**（报价/项目成本价/人工三选一的值） |
| currency_code | char(3) NOT NULL | 币种 |
| pricing_unit | varchar(20) NULL | 计价单位（修订二第 4 条） |
| quotation_id | int NULL | 复合 FK **(project_id,quotation_id)**→Material_Quotations(project_id,quotation_id)【源码已存在目标键】 |
| project_cost_price_id | int NULL | 复合 FK **(project_id,project_cost_price_id)**→project_cost_prices(project_id,price_id)（目标表需建对应列序唯一键，§2.10） |
| manual_price | decimal(18,4) NULL | 人工录入价格数值；**不伪造报价 ID** |
| manual_confirmed_by | int NULL | FK→Users(user_id)；人工价格必须有人工确认人 |
| fx_rate_id | int NULL | FK→exchange_rates(rate_id)；非空必须引用真实记录 |
| fx_rate_value | decimal(18,8) NULL | 实际使用汇率值（与 fx_rate_id 成对） |
| loss_rate / mgmt_rate / profit_rate / tax_rate / trans_rate / ins_rate | decimal(6,4) NULL | 该行实际所用值（F8） |
| subtotal / mgmt_amount / profit_amount / tax_amount / comprehensive | decimal(18,4) NOT NULL DEFAULT 0 | 分项结果；**公式未确认前不实现计算**【待业务确认】 |
| formula_version / rounding_spec_version | varchar(20) NOT NULL | 按什么算的（F8） |
| calculated_at | timestamp NOT NULL | |
| calculated_by | int NOT NULL | FK→Users |
- CHECK chk_result_line_exactly_one：component_line_id/resource_line_id 恰一非空（任务书示意式）。
- CHECK chk_price_source_exactly_one：
  - 报价来源：quotation_id 非空 ∧ project_cost_price_id/manual_price/manual_confirmed_by 全空；
  - 项目成本价来源：project_cost_price_id 非空 ∧ quotation_id/manual_price/manual_confirmed_by 全空；
  - 人工来源：manual_price 非空 ∧ manual_confirmed_by 非空 ∧ quotation_id/project_cost_price_id 全空。
- CHECK chk_fx_pair：fx_rate_id 与 fx_rate_value 同空同非空。
- CHECK chk_unit_price_positive：unit_price >0（**缺价不得静默按零**；无有效价格的行不落结果，组价 calc_status=incomplete，§4.1）。
- UNIQUE uq_result_component **(costing_id,component_line_id)**：同组价同组件行仅一条当前结果（component_line_id 为 NULL 的资源行不受此键约束——MySQL 唯一键含 NULL 不去重，恰好满足"分别定义"）。
- UNIQUE uq_result_resource **(costing_id,resource_line_id)**：同理资源行唯一。
- 复合 FK 携带 project_id/costing_id，数据库强制结果行与目标行同项目同组价（修订一）。

### 2.5 cost_settings（当前费率配置；修订六）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| setting_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects |
| scope_type | enum('project','building') NOT NULL | |
| building_id | int NULL | 复合 FK **(building_id,project_id)**→Buildings(building_id,project_id)（uq_building_id_project 列序已核实） |
| building_key | int GENERATED ALWAYS AS (IFNULL(building_id,0)) STORED | NULL 唯一性解决方案保留 |
| management_fee_rate / profit_rate / tax_rate / transportation_rate / insurance_rate | decimal(6,4) NOT NULL DEFAULT 0 | **数值语义/默认值待公式组**【待业务确认】；结构先行 |
| created_by / updated_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
- **删除 setting_version/effective_date/版本链/版本选择逻辑**（修订六）。
- UNIQUE uq_setting_scope **(project_id,building_key)**：每项目每作用域仅一条当前配置。
- CHECK chk_scope_building：scope='building'→building_id 非空；scope='project'→building_id 为空。
- 修改＝UPDATE 当前行＋同事务 audit_logs（before/after 关键值、项目、楼栋作用域、操作者、原因）；不建费率历史表。
- **继承/整行覆盖/未来日期规则不写入本稿**——B17/B7 待确认【待业务确认】；计算时如何取项目级/楼栋级配置在公式组决策后定义。

### 2.6 exchange_rates（汇率；任务书截断处，按 V2.1 基础结构补全【设计提案】）
| 字段 | 类型 | 约束 |
|---|---|---|
| rate_id | int AI | PK |
| from_currency / to_currency | char(3) NOT NULL | |
| rate | decimal(18,8) NOT NULL | CHECK >0 |
| effective_date | date NOT NULL | 保留（任务书 4.7 明示"币种、汇率、生效日期"） |
| created_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
- UNIQUE uq_rate_pair_date (from_currency,to_currency,effective_date)。
- 行不可变（更正＝新行）；**方向/选择规则/兜底/未来日期策略待 B7**【待业务确认】；计算时选定行 id＋值落明细（F8）。

### 2.7 units（共享量纲目录【设计提案】）
unit_code varchar(20) PK；unit_name varchar(50) NOT NULL；dimension enum('length','area','volume','mass','count','time') NOT NULL；created_at/updated_at。

### 2.8 unit_conversions（量纲换算【设计提案】）
conversion_id int AI PK；from_unit/to_unit varchar(20) NOT NULL（各 FK→units(unit_code) RESTRICT）；factor decimal(18,8) NOT NULL CHECK >0；UNIQUE (from_unit,to_unit)；created_at/updated_at。包装换算不在此表（N6 分层，§2.9）。

### 2.9 item_pack_specs（包装规格；修订三类型化外键【设计提案】）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| spec_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects（包装规格按项目维护；共享目录条目的包装归属随 B16 评审） |
| material_id | int NULL | 复合 FK (project_id,material_id)→Materials(project_id,material_id) |
| aux_id | int NULL | 复合 FK (project_id,aux_id)→Auxiliary_Materials(project_id,aux_id) |
| proc_id | int NULL | FK→processing_catalog(proc_id)【待 B16：目标表/项目隔离】 |
| mech_id | int NULL | FK→mechanical_catalog(mech_id)【待 B16】 |
| labor_task_id | int NULL | FK→labor_task_catalog(labor_task_id)【待 B16】 |
| target_key | int GENERATED ALWAYS AS (COALESCE(material_id,aux_id,proc_id,mech_id,labor_task_id)) STORED | |
| pack_unit | varchar(20) NOT NULL | 包装单位（如"袋"） |
| base_unit | varchar(20) NOT NULL | 基本单位（如 kg） |
| pack_size | decimal(18,6) NOT NULL | CHECK >0（每包装含基本单位量） |
| created_by / updated_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
- CHECK chk_pack_exactly_one：五列恰一非空（**无 item_type 字段，类型由非空列表达**）。
- UNIQUE uq_pack_target **(project_id,target_key,pack_unit)**：同项目同条目同包装单位一条当前规格（修改留审计；V2.1 的 spec_version 移除，与修订六轻量化一致【设计提案】）。
- 目标键核实：Materials/Auxiliary_Materials 复合键【源码已存在】；proc/mech/labor 目标表主键为本稿定义（§2.11–13），所有权待 B16。

### 2.10 project_cost_prices（项目成本价；修订三类型化外键【设计提案】）
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| price_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects |
| aux_id | int NULL | 复合 FK (project_id,aux_id)→Auxiliary_Materials(project_id,aux_id) |
| proc_id | int NULL | FK→processing_catalog(proc_id)【待 B16】 |
| mech_id | int NULL | FK→mechanical_catalog(mech_id)【待 B16】 |
| labor_task_id | int NULL | FK→labor_task_catalog(labor_task_id)【待 B16】 |
| target_key | int GENERATED ALWAYS AS (COALESCE(aux_id,proc_id,mech_id,labor_task_id)) STORED | |
| unit_price | decimal(18,4) NOT NULL | CHECK >0 |
| currency_code | char(3) NOT NULL | |
| pricing_unit | varchar(20) NOT NULL | 计价单位 |
| valid_from / valid_to | date NULL | 有效期（A4 细则【待业务确认】） |
| status | enum('active','suspended') NOT NULL DEFAULT 'active' | |
| created_by / updated_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
- CHECK chk_price_exactly_one：四列恰一非空（**无 catalog_type 字段**）。
- UNIQUE uq_price_target **(project_id,target_key)**：每项目每目录项一条当前价（修改留审计；V2.1 的 price_version 移除【设计提案，与修订六同口径】）。
- UNIQUE uq_price_project_id **(project_id,price_id)**：供 cost_line_results 复合 FK（列序对齐）。
- 目录与价格分离：本表是项目价格，目录表（§2.11–13/Auxiliary_Materials）不含价格。

### 2.11–2.13 资源目录三张（processing_catalog / mechanical_catalog / labor_task_catalog；修订十独立统计【设计提案】）
三表同构：
| 字段 | 类型 | 约束 |
|---|---|---|
| proc_id / mech_id / labor_task_id | int AI | PK（各表主键名与引用列一致） |
| name | varchar(255) NOT NULL | |
| unit | varchar(20) NOT NULL | 计量单位（引用 units 与否随单位体系评审） |
| description | text NULL | |
| created_by / updated_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
- **不带价格字段**（价格在 project_cost_prices）。
- **所有权待 B16**【待业务确认】：共享→无 project_id（现状草案）；项目化→加 project_id NOT NULL＋UNIQUE(project_id,id)，届时 resources/pack/prices 的 proc/mech/labor 引用升级为复合 FK。两种形态的外键位置已在上表标明，**不用多态字段临时替代**。
- 辅材目录不新建（沿用 Auxiliary_Materials【源码已存在】，不增成本字段）。

### 2.14 既有表扩展（2 张，不计入新建）

**Material_Quotations 增列【设计提案】**：
- valid_from date NULL；valid_to date NULL；
- status enum('active','suspended','expired') NOT NULL DEFAULT 'active'【状态机细则待 A4】；
- **tax_status enum('unknown','inclusive','exclusive') NOT NULL DEFAULT 'unknown'**（修订五：历史报价默认 unknown，不默认不含税；unknown 不得静默转 exclusive；新报价录入须明确口径，是否允许暂存 unknown【待业务确认 A4】；正式计算需税口径而遇 unknown→标记不完整或阻断，§4.1；税率/税基/可抵扣规则本轮不决定）；
- pricing_unit varchar(20) NULL。
- 替代 V2.1 的 tax_inclusive tinyint（该提案未落库，直接以三态取代，无迁移兼容问题【设计提案】）。
- 既有字段（purchase_unit_price/confirmed_tax_rate/version/quotation_date/默认报价人工选择契约）不动【源码已存在】。

**audit_logs 增列【设计提案】**：project_id int NULL＋KEY(project_id,created_at)；entity_type varchar(50) NULL；entity_id int NULL＋KEY(entity_type,entity_id)。details 约定 JSON：{before:{关键值},after:{关键值},reason?,scope?}。Support::audit 增可选上下文参数或新增 auditEntity() 助手；既有三参调用兼容（新列可空）【源码已存在＋待开发验证：全量调用点兼容扫描】。

### 2.15 明确不新建的表
快照族全部、版本确认记录、幂等键、变更影响索引、独立修改日志/费率历史/组成同步历史表（F4/F5/F7、修订六/九：历史一律复用 audit_logs）。

## 3. 约束总清单（外键/复合外键/唯一索引/CHECK/生成列）

> 全部【设计提案】；目标键存在性：标注〔已核实〕者来自现有迁移，其余目标为本稿新表。列序＝引用列序与目标唯一键列序逐项对齐。

### 3.1 外键（全部 ON DELETE RESTRICT，例外仅 FK-C2）
| 编号 | 表 | 列 | 目标 | 备注 |
|---|---|---|---|---|
| FK-H1 | bom_costings | project_id | Projects(project_id) | |
| FK-H2 | bom_costings | (project_id,parent_material_id) | Materials(project_id,material_id)〔已核实〕 | |
| FK-H3/H4 | bom_costings | confirmed_by/created_by | Users(user_id) | |
| FK-C1 | bom_costing_components | (project_id,costing_id) | bom_costings(project_id,costing_id) | 修订七 |
| FK-C2 | bom_costing_components | source_composition_id | Material_Compositions(composition_id)〔已核实〕 | **ON DELETE SET NULL**（修订九第 8 条例外，配 source_composition_key 文本追溯） |
| FK-C3 | bom_costing_components | (project_id,child_material_id) | Materials(project_id,material_id)〔已核实〕 | |
| FK-C4 | bom_costing_components | synced_by/quantity_adjusted_by | Users(user_id) | |
| FK-R1 | bom_costing_resources | (project_id,costing_id) | bom_costings(project_id,costing_id) | |
| FK-R2 | bom_costing_resources | (project_id,aux_id) | Auxiliary_Materials(project_id,aux_id)〔已核实〕 | |
| FK-R3/R4/R5 | bom_costing_resources | proc_id/mech_id/labor_task_id | 三目录表主键 | **B16 后可能升级复合 FK**【待业务确认】 |
| FK-R6 | bom_costing_resources | (project_id,material_id) | Materials(project_id,material_id)〔已核实〕 | parent_self |
| FK-X1 | cost_line_results | (project_id,costing_id) | bom_costings(project_id,costing_id) | |
| FK-X2 | cost_line_results | (project_id,costing_id,component_line_id) | bom_costing_components(project_id,costing_id,component_line_id) | 修订一 |
| FK-X3 | cost_line_results | (project_id,costing_id,resource_line_id) | bom_costing_resources(project_id,costing_id,resource_line_id) | 修订一 |
| FK-X4 | cost_line_results | (project_id,quotation_id) | Material_Quotations(project_id,quotation_id)〔已核实〕 | 修订二 |
| FK-X5 | cost_line_results | (project_id,project_cost_price_id) | project_cost_prices(project_id,price_id) | 修订二 |
| FK-X6 | cost_line_results | manual_confirmed_by/calculated_by | Users(user_id) | |
| FK-X7 | cost_line_results | fx_rate_id | exchange_rates(rate_id) | |
| FK-S1 | cost_settings | project_id | Projects(project_id) | |
| FK-S2 | cost_settings | (building_id,project_id) | Buildings(building_id,project_id)〔已核实 uq_building_id_project〕 | 列序对齐 |
| FK-S3 | cost_settings | created_by/updated_by | Users(user_id) | |
| FK-E1 | exchange_rates | created_by | Users(user_id) | |
| FK-U1/U2 | unit_conversions | from_unit/to_unit | units(unit_code) | |
| FK-P1 | item_pack_specs | project_id | Projects(project_id) | |
| FK-P2 | item_pack_specs | (project_id,material_id) | Materials(project_id,material_id)〔已核实〕 | |
| FK-P3 | item_pack_specs | (project_id,aux_id) | Auxiliary_Materials(project_id,aux_id)〔已核实〕 | |
| FK-P4/P5/P6 | item_pack_specs | proc_id/mech_id/labor_task_id | 三目录表主键 | 待 B16 |
| FK-P7 | item_pack_specs | created_by/updated_by | Users(user_id) | |
| FK-PC1 | project_cost_prices | project_id | Projects(project_id) | |
| FK-PC2 | project_cost_prices | (project_id,aux_id) | Auxiliary_Materials(project_id,aux_id)〔已核实〕 | |
| FK-PC3/4/5 | project_cost_prices | proc_id/mech_id/labor_task_id | 三目录表主键 | 待 B16 |
| FK-PC6 | project_cost_prices | created_by/updated_by | Users(user_id) | |
| FK-CAT | 三目录表 | created_by/updated_by | Users(user_id) | |

### 3.2 唯一索引
| 编号 | 表 | 列 | 用途 |
|---|---|---|---|
| UQ-H1 | bom_costings | (project_id,parent_material_id) | F1 |
| UQ-H2 | bom_costings | (project_id,costing_id) | 修订七，下游复合 FK 目标 |
| UQ-C1 | bom_costing_components | (project_id,costing_id,component_line_id) | 结果表 FK 目标 |
| UQ-C2 | bom_costing_components | (costing_id,child_material_id) | N7 默认 |
| UQ-R1 | bom_costing_resources | (project_id,costing_id,resource_line_id) | 结果表 FK 目标 |
| UQ-R2 | bom_costing_resources | (costing_id,resource_type,resource_key) | N7 默认 |
| UQ-X1 | cost_line_results | (costing_id,component_line_id) | 组件结果唯一（NULL 不去重，资源行不受约束） |
| UQ-X2 | cost_line_results | (costing_id,resource_line_id) | 资源结果唯一 |
| UQ-S1 | cost_settings | (project_id,building_key) | 修订六，每作用域一条 |
| UQ-E1 | exchange_rates | (from_currency,to_currency,effective_date) | |
| UQ-U1 | unit_conversions | (from_unit,to_unit) | |
| UQ-P1 | item_pack_specs | (project_id,target_key,pack_unit) | |
| UQ-PC1 | project_cost_prices | (project_id,target_key) | 每项目每目录项一条当前价 |
| UQ-PC2 | project_cost_prices | (project_id,price_id) | 结果表 FK 目标 |

### 3.3 CHECK 约束
| 编号 | 表 | 内容 |
|---|---|---|
| CK-C1 | components | composition_factor>0 ∧ calculated_quantity>0 |
| CK-R1 | resources | 五引用列恰一非空 |
| CK-R2 | resources | resource_type 与非空列匹配 |
| CK-R3 | resources | quantity>0 |
| CK-X1 | results | component_line_id/resource_line_id 恰一非空 |
| CK-X2 | results | 价格来源恰一种（报价/项目成本价/人工＋确认人，互斥规则见 §2.4） |
| CK-X3 | results | fx_rate_id 与 fx_rate_value 同空同非空 |
| CK-X4 | results | unit_price>0（禁静默零价） |
| CK-S1 | settings | scope='building'↔building_id 非空；scope='project'↔building_id 空 |
| CK-E1 | exchange_rates | rate>0 |
| CK-U1 | unit_conversions | factor>0 |
| CK-P1 | item_pack_specs | 五目标列恰一非空 ∧ pack_size>0 |
| CK-PC1 | project_cost_prices | 四目标列恰一非空 ∧ unit_price>0 |

### 3.4 生成列
| 表 | 列 | 表达式 |
|---|---|---|
| cost_settings | building_key | IFNULL(building_id,0) STORED |
| bom_costing_resources | resource_key | COALESCE(aux_id,proc_id,mech_id,labor_task_id,material_id) STORED |
| item_pack_specs | target_key | COALESCE(material_id,aux_id,proc_id,mech_id,labor_task_id) STORED |
| project_cost_prices | target_key | COALESCE(aux_id,proc_id,mech_id,labor_task_id) STORED |

### 3.5 受控写入机制（非声明式约束，明示边界）
- parent_self 的 material_id＝头表父材料：服务层从头表取值＋写前校验（可选守卫触发器仅拒不一致）【设计提案＋待开发验证】。
- 结果行数量与目标行一致性：重算事务内从目标行读取，不接受客户端直填【设计提案】。
- 缺价不落结果行：calc_status 标记＋缺项清单（audit/页面），不以 0 价占位【设计提案】。

## 4. 状态、重算、确认、组成同步事务规则（修订八/九）

### 4.1 概念分离（不得合并）
- **status**（draft/confirmed）：业务确认状态，只由"确认/实质修改"改变。
- **calc_status**（incomplete/calculable/complete）：计算完整性。incomplete＝存在阻断项（缺有效价格/缺汇率/单位不匹配/需税口径而 tax_status=unknown）；calculable＝可试算但有非阻断缺项（如缺费率按业务策略降级——**降级策略待 A7/B6 确认**【待业务确认】）；complete＝全部输入齐备。
- **calculated_at/calculated_by**（明细行级）：最近一次计算时间与操作人。
- 缺价/缺汇率/单位不匹配/unknown 税口径 → 相应行不落结果、calc_status 降级；**禁止静默按零或错误汇率计算**（F8/任务书）。

### 4.2 两类重算（修订八核心）
| 操作 | 输入变化 | status 处理 | 事务内容 |
|---|---|---|---|
| 相同输入纯重算 | 否 | **保持 confirmed** | 更新 cost_line_results＋calculated_at/by＋计算审计；不退草稿 |
| 接受新材料报价 | 是 | **同事务退 draft** | 更新来源引用＋重算＋状态＋审计 |
| 接受新项目成本价 | 是 | 同事务退 draft | 同上 |
| 应用新汇率 | 是 | 同事务退 draft | 同上 |
| 应用新费率配置 | 是 | 同事务退 draft | 同上 |
| 修改组件/资源数量 | 是 | 同事务退 draft | 同上 |
| 修改组件/资源/规则引用 | 是 | 同事务退 draft | 同上 |
| 仅查看/打开 | 否 | 不变 | 无写入 |
- **输入一致性识别（设计提案，不引入快照/历史账本/版本体系）**：组价头维护 `input_fingerprint`（char(64) NULL，全部计算输入的 SHA-256：各行来源 id＋单价＋币种＋汇率 id＋费率 setting_id＋损耗＋公式/舍入版本）。重算请求先按当前输入重算指纹：与头表指纹一致→纯重算；不一致→实质修改路径（退 draft）。指纹只是当前态摘要，不是历史留档；历史变更走 audit_logs（F7）。**该字段为 §2.1 补充【设计提案】**。
- 事务一致性：结果、状态、审计同事务提交；任一环节失败整体回滚；**不得出现"已退草稿但结果未更新"或"结果更新但审计缺失"的半完成态**。
- 并发：组价编辑/重算/确认以 updated_at 乐观锁（提交比对，冲突 409）；确认为单行原子 UPDATE＋事务；沿用报价 version 乐观锁【源码已存在模式】。

### 4.3 确认
- 显式动作；前置：calc_status 达到可确认级别（**complete 必须；calculable 是否允许确认待 A7 降级策略**【待业务确认】）。
- 事务：校验指纹未变（变了→409 要求先重算）→status=confirmed＋confirmed_by/at＋审计。
- 确认后任何实质修改自动退 draft（F6），重新确认必须显式。

### 4.4 组成同步（修订九）
- 触发：仅用户主动；材料目录变化不自动覆盖组价。
- 同步前展示差异（新增/删除/系数变化/计算数量变化/人工调整保留项）。
- 事务内容：
  1. 读取当前 Material_Compositions（project_id＋父材料）与组价副本逐行比对；
  2. 新增边→插入副本（source_composition_id/source_composition_key/composition_factor 来自来源；calculated_quantity 默认＝系数，换算规则未确认不推导）；
  3. 来源删除→副本行按差异预览经用户确认后删除（不自动删）；
  4. 系数变化→更新 composition_factor；**calculated_quantity 若被人工调整过（quantity_adjusted_by 非空）不得无提示覆盖**——差异预览明示，用户选择保留调整或跟随系数；
  5. 更新 synced_at/synced_by；
  6. 实质修改→同事务退 draft（若原 confirmed）；
  7. audit_logs 记录：组价/项目/父材料 id、操作者、时间、原因、新增/删除/系数变化/数量变化/人工保留清单、前后关键值（JSON）；
  8. 任一步失败整体回滚。
- 不建独立同步历史表（复用 audit_logs，F7）。
- 来源记录删除后的追溯：FK SET NULL＋source_composition_key 文本永存（§2.2），副本原始系数/快照独立存活。

## 5. 迁移清单统计（修订十；全部仅为设计，不得创建迁移文件）

### 5.1 数量统计
- **新建表 13 张**：组价与计算 4（bom_costings/bom_costing_components/bom_costing_resources/cost_line_results）＋成本设置与汇率 2（cost_settings/exchange_rates）＋单位与包装 3（units/unit_conversions/item_pack_specs）＋项目成本价 1（project_cost_prices）＋资源目录 3（processing_catalog/mechanical_catalog/labor_task_catalog）。
- **既有表扩展 2 张**（不计入新建）：Material_Quotations（有效期/status/税三态/计价单位）、audit_logs（project_id/entity_type/entity_id＋索引）。

### 5.2 创建依赖顺序（提案）
1. units → 2. unit_conversions → 3. exchange_rates → 4. 三资源目录（B16 形态确认后）→ 5. bom_costings → 6. bom_costing_components / bom_costing_resources（并列，依赖 5）→ 7. project_cost_prices（依赖 4）→ 8. item_pack_specs（依赖 4＋Materials/Aux）→ 9. cost_line_results（依赖 5/6/7＋Material_Quotations 扩展）→ 10. cost_settings（独立，依赖 Buildings）→ 11. 既有表扩展（Material_Quotations 须先于 9 的数据写入；audit_logs 先于成本域审计写入）。
- 实际迁移拆分与台账登记在开发阶段按 bin/install.php 机制评审【待开发验证】。

### 5.3 依赖标注
- **依赖 B16**：三目录表所有权形态（共享/项目化）；resources/pack/prices 中 proc/mech/labor 外键是否升级复合 FK。**B16 未确认前这三张目录表与其引用键不落最终形态**，其余 10 张新表＋2 扩展不受阻。
- **依赖公式组（B3/B4/B6/B7/B14/B10）**：cost_line_results 金额列的计算逻辑、cost_settings 费率语义/默认值、loss_rate 启用、汇率选择规则、舍入路径。**结构可先评审，计算实现被 G2 闸门阻止**。
- **依赖 A4/A7/B17**：报价状态机细则、缺项降级策略、费率继承——对应校验逻辑，不影响表结构评审。
- 本轮全部迁移项目**仅为设计**，未创建任何迁移文件/SQL/脚本。

## 6. 现有源码差异（复用/修改/新增）

### 6.1 可复用【源码已存在】
Support::requirePermission/CSRF/ProjectContext::requireCurrentProject；Support::audit（扩展后兼容）；PluginServiceRegistry（publish/resolve＋uses 声明）；ProjectSpaceCatalog 契约范本；uq_building_id_project/uq_material_project_id/uq_auxiliary_project_id/uq_quotation_project_id（复合 FK 目标键）；Material_Compositions 全部契约与并发保护；报价 version 乐观锁；迁移台账机制。

### 6.2 需修改（开发阶段）
audit_logs DDL＋Support::audit 上下文扩展（全量调用点兼容扫描【待开发验证】）；Material_Quotations 增列＋保存/列表/默认报价选择的有效性校验（税三态：新录入须明确口径，unknown 处理按 §4.1）；material-management plugin.json 发布 MaterialCostCatalog 只读契约（材料/组成/报价/辅材，项目作用域）；Materials 单位角色与包装引用（unit 原字段与组成契约不动）。

### 6.3 需新增（开发阶段）
bom.cost 插件（requires project.foundation/material.management；uses MaterialCostCatalog）；CostingService（创建/同步/确认/两类重算/乐观锁）、CalculationService（公式组确认前只搭骨架）、SettingsService/RateService/UnitService；新权限草案 view/edit/confirm_costings、manage_cost_settings、manage_exchange_rates、view_cost_reports（受众待 B20）；路由与视图草案；测试双轨（SQLite 等价＋MySQL 实测：复合 FK/CHECK/生成列/SET NULL/乐观锁并发/两类重算状态断言/同步差异审计断言）。

### 6.4 明确不做
不复制旧触发器/过程/视图/跨楼栋串价；不建快照/多版本/历史表；不改 Material_Compositions/样品/默认报价/招标契约；不改旧系统；本轮不开发不落库。

## 7. 尚未解决的业务决策

| 组 | 事项 | 阻塞 |
|---|---|---|
| 公式组（G2） | B3 税链/税基；B4 双重加成/利润基数；B6 取价口径/缺价策略；B7 本位币/汇率方向/选择/兜底/未来日期；B14 损耗口径；B10 舍入路径 | 计算实现（结构不受阻） |
| B16 | 加工/机械/人工目录所有权（共享 vs 项目化）→ 三目录表形态与复合 FK 升级 | 目录三表＋相关引用键最终形态 |
| B17 | 项目/楼栋费率继承、整行覆盖与否（cost_settings 已无版本链，继承规则待定） | 计算取费率逻辑 |
| A4 | 报价状态机/审批/暂存 unknown 是否允许/过期降级 | 报价校验逻辑 |
| A7/B6 | calculable 是否允许确认、缺项降级策略 | 确认前置 |
| N7 | 组件/资源多行例外 | 唯一键是否加 line_no |
| 单位规则 | 换算链与 calculated_quantity 推导（修订四：未确认不推导） | 计算数量逻辑 |
| 迁移组（G3） | B1 精度评估；B2 旧自引用边→parent_self 映射；B9 辅材语义；B13 重复边；B5 历史差异签收 | 旧数据进新库 |
| 本轮评审项 | FK-C2 SET NULL 例外、input_fingerprint 方案、parent_self 受控写入、pack/prices 单条当前态（移除 version） | 技术评审确认 |

## 8. 逐项验收结果与下一阶段条件

### 8.1 十项修订逐项验收（设计层面自检）
| 修订 | 验收点 | 结果 |
|---|---|---|
| 一 | 结果表双可空 FK＋恰一 CHECK＋三列复合 FK（project/costing/line 一致）＋分别唯一键 | ✅ 设计满足（§2.4/§3）；MySQL 实测【待开发验证】 |
| 二 | 三来源字段＋恰一 CHECK＋真实 FK（报价/成本价复合、确认人）＋币种/计价单位/实际价＋禁零价 | ✅ 设计满足；人工价不伪造报价 ID |
| 三 | pack/prices 类型化可空 FK＋恰一 CHECK；material/aux 目标键已核实；proc/mech/labor 标明待 B16，无多态临时替代 | ✅ 设计满足（B16 部分明示开放） |
| 四 | composition_factor(10,2) 与 calculated_quantity(18,6) 并存；原 quantity 字段移除；系数不被覆盖；换算未确认不推导 | ✅ 设计满足 |
| 五 | tax_status 三态默认 unknown；不静默转 exclusive；计算遇 unknown 降级/阻断；税公式未定 | ✅ 设计满足 |
| 六 | 每作用域一条当前配置；UNIQUE(project_id,building_key)；删版本链；修改同事务审计；继承未定案 | ✅ 设计满足 |
| 七 | bom_costings(project_id,costing_id) 唯一键；全部复合 FK 目标列序逐项核对（§3 标注） | ✅ 设计满足 |
| 八 | 两类重算状态表＋指纹识别＋同事务退草稿＋结果/状态/审计一致＋整体回滚；status 与 calc_status 分离 | ✅ 设计满足；指纹方案待评审 |
| 九 | source_composition_id（已核实 composition_id）＋key 文本＋synced_at/by＋差异审计全要素＋人工调整不无提示覆盖＋SET NULL 追溯 | ✅ 设计满足；SET NULL 例外待评审 |
| 十 | 13 新建＋2 扩展；依赖顺序/B16/公式组依赖标注；未创建迁移 | ✅ 满足 |

### 8.2 进入下一阶段（技术评审→开发）条件
1. 本稿通过技术评审：重点确认 §7 本轮评审项（SET NULL 例外/指纹/受控写入/单条当前态）与复合 FK 列序。
2. B16 确认 → 目录三表与引用键落最终形态；公式组确认 → G2 开闸。
3. 开发启动后：MySQL 实测（CHECK/生成列/复合 FK/SET NULL/并发）为 P1–P4 退出条件；audit_logs 调用点兼容扫描先行。
4. 未满足上述条件不创建迁移、不开发计算逻辑；本轮全部产出维持【设计提案】状态。

## 9. 边界声明
- 本轮只读设计修订：未修改任何源码/数据库/迁移，未创建迁移文件/SQL/安装脚本，未开发任何功能，未执行数据迁移/清洗/修复。
- 公式/税率/利润/管理费/损耗/汇率规则/B16 未代为决定；九条冻结原则与已确认契约零改动；旧系统错误行为未作为标准。
- 全文按【设计提案】【源码已存在】【待业务确认】【待开发验证】标注；未把未验证设计描述为已实现或已通过 MySQL 实测。
- 任务书附件 §4.7 后截断部分按 §1.1/§3 要求补全并标注；如与任务书原意有出入，以任务书全文为准修订。
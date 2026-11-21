# Project O BOM 与成本 V2.1：数据库结构定稿与现有源码差异分析

> 日期：2026-11-18。任务性质：结构定稿＋源码差异分析（只读）。依据：用户本轮冻结的九条 V2.1 架构原则（§0）；前序 10–14 号文件。
> 本轮未修改任何 PHP/SQL/JS/CSS 或数据库数据，未创建迁移，未执行开发。**本文是结构定稿与设计评审依据，不是迁移 SQL**；所有新表/新字段在落库前仍需按 AGENTS.md 走迁移评审。公式/税率/利润/管理费/损耗/汇率数值未代为决定（公式组决策仍开放）。
> 已确认契约零改动：Material_Compositions、样品管理、默认报价人工选择、招标首期边界。

## 0. V2.1 冻结原则登记（2026-11-18 用户指令）

| # | 原则 | 结构落点 |
|---|---|---|
| F1 | 一父材料一份当前组价 | bom_costings UNIQUE(project_id,parent_material_id) |
| F2 | 材料组成成本与父材料自身成本允许组合 | 组件层与 parent_self 资源行并存计价；cost_mode 字段不再需要（组合已获准） |
| F3 | 技术 BOM 与成本资源分层 | bom_costing_components（材料组成）与 bom_costing_resources（辅材/加工/机械/人工/父项自身）分表 |
| F4 | 不采用成本快照 | 无快照族；当前态明细 cost_line_results |
| F5 | 不采用 BOM 多版本体系 | 组价头仅 draft/confirmed 两态；无版本链/派生/superseded |
| F6 | 已确认组价修改自动退回草稿 | 编辑事务内 status confirmed→draft＋审计；重新确认为显式动作 |
| F7 | 当前计算结果落库，历史修改优先复用审计 | cost_line_results 当前态；历史走既有 audit_logs（结构化 details），不新建历史表 |
| F8 | 价格来源、汇率、费率可复核 | 明细行落 price_source/fx/所用费率值/公式与舍入版本 |
| F9 | 项目和楼栋隔离由数据库强制保障 | 全表 project_id NOT NULL＋复合外键；楼栋级引用 (building_id,project_id) 复合 FK（uq_building_id_project 已存在） |

**F2 对 N1 的关闭**：V1.2/V2.0 的 cost_mode 互斥/组合待决事项，由"允许组合"关闭——组件成本与父项自身成本可同时计入。组合的**计价边界**（父项自身价覆盖什么、避免与组件重复计同一采购内容）属录入责任，系统提供并列展示与合计，不做互斥拦截；旧自引用边（B2）迁移时按"父项自身成本行"表达，不再视为非法。
**F6 细则**：任何对已确认组价的实质修改（组件/资源/规则引用变更）→ 自动退回 draft＋审计；重新确认需显式动作。纯查看/重算不改变状态（重算只更新明细与审计）。

## 1. 数据库结构定稿（提案定稿，非迁移 SQL）

> 通用约定：MySQL 8.0.16+（CHECK/生成列）；项目表 project_id NOT NULL＋复合 FK；成本域外键一律 ON DELETE RESTRICT（无 CASCADE）；UTC 时间戳；utf8mb4_unicode_ci；命名沿用既有风格（表 PascalCase、约束 fk_*_o/uq_*）。精度按 N4/B10 草案（金额 18,4 存储/2 位展示、费率 6,4、汇率 18,8、数量 18,6），舍入路径待公式组评审。

### 1.1 新建表（11 张）

**① bom_costings（组价头）**
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| costing_id | int AI PK | |
| project_id | int NOT NULL | FK→Projects RESTRICT |
| parent_material_id | int NOT NULL | 复合 FK (project_id,parent_material_id)→Materials(project_id,material_id) RESTRICT |
| status | enum('draft','confirmed') NOT NULL DEFAULT 'draft' | F5/F6 |
| calc_status | enum('incomplete','calculable','complete') NOT NULL DEFAULT 'incomplete' | 完整性标记，随重算更新 |
| confirmed_by | int NULL | FK→Users |
| confirmed_at | timestamp NULL | |
| created_by / created_at / updated_at | | updated_at 兼作乐观锁版本 |
| UNIQUE uq_costing_project_parent (project_id,parent_material_id) | | **F1 数据库强制** |

**② bom_costing_components（组件层＝技术 BOM 副本）**
| 字段 | 类型 | 约束 |
|---|---|---|
| component_line_id | int AI PK | |
| costing_id | int NOT NULL | FK→bom_costings RESTRICT |
| project_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings（需头表 UNIQUE(costing_id,project_id)） |
| child_material_id | int NOT NULL | 复合 FK (project_id,child_material_id)→Materials RESTRICT |
| quantity | decimal(18,6) NOT NULL | CHECK >0（契约系数 10,2 的冻结镜像，余量容纳换算） |
| unit_snapshot / name_snapshot | varchar(50)/varchar(255) NOT NULL | 创建时冻结文本 |
| UNIQUE uq_costing_component (costing_id,child_material_id) | | 同版本同材料唯一（N7 默认） |
- 创建组价时从 Material_Compositions 复制；当前组成变化不自动改组价（人工"同步组成"＋审计）。

**③ bom_costing_resources（资源层）**
| 字段 | 类型 | 约束 |
|---|---|---|
| resource_line_id | int AI PK | |
| costing_id / project_id | int NOT NULL | 复合 FK 同② |
| resource_type | enum('auxiliary','processing','mechanical','labor','parent_self') NOT NULL | |
| aux_id / proc_id / mech_id / labor_task_id / material_id | int NULL ×5 | 各带真实 FK：aux 走 (project_id,aux_id)→Auxiliary_Materials 复合；proc/mech/labor 走共享目录单列 FK【B16 所有权确认后落目标表】；parent_self 的 material_id 走 (project_id,material_id) 复合且必须＝头表父材料（守卫校验） |
| resource_key | int GENERATED ALWAYS AS (COALESCE(aux_id,proc_id,mech_id,labor_task_id,material_id)) STORED | CHECK 五列恰一非空且匹配 resource_type |
| quantity | decimal(18,6) NOT NULL | CHECK >0 |
| loss_rate | decimal(6,4) NULL | 【B14 确认启用前恒 NULL】 |
| unit_snapshot / name_snapshot | varchar | |
| UNIQUE uq_costing_resource (costing_id,resource_type,resource_key) | | N7 默认唯一 |

**④ cost_line_results（当前态计算明细，F4/F7/F8）**
| 字段 | 类型 | 约束 |
|---|---|---|
| result_id | int AI PK | |
| costing_id / project_id | int NOT NULL | 复合 FK |
| line_layer | enum('component','resource') NOT NULL | |
| line_id | int NOT NULL | 指向②或③（同组价内；服务校验＋守卫） |
| quantity / unit_price | decimal(18,6)/(18,4) NOT NULL | 可复核基础 |
| currency_code | char(3) NOT NULL | |
| price_source_type | enum('quotation','project_cost_price','manual') NOT NULL | **F8 来源必填，禁止静默零价** |
| price_source_id | int NOT NULL | manual 时＝确认记录引用（确认人落审计） |
| fx_rate_id | int NULL | 所用汇率行（同币 NULL） |
| fx_rate_value | decimal(18,8) NULL | 当时值 |
| loss_rate / mgmt_rate / profit_rate / tax_rate / trans_rate / ins_rate | decimal(6,4) NULL | 该行实际所用值 |
| subtotal / mgmt_amount / profit_amount / tax_amount / comprehensive | decimal(18,4) NOT NULL DEFAULT 0 | 分项结果 |
| formula_version / rounding_spec_version | varchar(20) NOT NULL | 按什么算的可复核 |
| calculated_at / calculated_by | | 重算留痕 |
| UNIQUE uq_result_line (costing_id,line_layer,line_id) | | 每行一条当前结果 |

**⑤ cost_settings（费率版本链，B17 结构）**
| 字段 | 类型 | 约束 |
|---|---|---|
| setting_id | int AI PK | |
| project_id | int NOT NULL | FK |
| scope_type | enum('project','building') NOT NULL | |
| building_id | int NULL | 复合 FK (building_id,project_id)→Buildings(uq_building_id_project) RESTRICT；CHECK scope=building 必填、scope=project 必 NULL |
| building_key | int GENERATED ALWAYS AS (IFNULL(building_id,0)) STORED | 解决 NULL 唯一索引失效 |
| setting_version | int NOT NULL | 同 (project,building_key) 内递增 |
| effective_date | date NOT NULL | 未来日期策略待 B17/B7 |
| management_fee_rate / profit_rate / tax_rate / transportation_rate / insurance_rate | decimal(6,4) NOT NULL DEFAULT 0 | **数值语义待公式组；结构先行** |
| created_by / created_at | | 行不可变（更正＝新版本行） |
| UNIQUE uq_setting_version (project_id,building_key,setting_version) | | 同日多次调整＝多版本 |
- 继承草案：楼栋行整行覆盖项目行（待 B17 确认）；选择规则 effective_date≤基准日 MAX，同日 MAX(setting_version)。

**⑥ exchange_rates**：rate_id PK；from_currency/to_currency char(3) NOT NULL；rate decimal(18,8) NOT NULL CHECK >0；effective_date date NOT NULL；UNIQUE(from_currency,to_currency,effective_date)；行不可变。方向/兜底待 B7。

**⑦ units**：unit_code varchar(20) PK；unit_name varchar(50)；dimension enum('length','area','volume','mass','count','time') NOT NULL。共享量纲目录。
**⑧ unit_conversions**：conversion_id PK；from_unit/to_unit varchar(20) FK→units；factor decimal(18,8) CHECK >0；UNIQUE(from_unit,to_unit)。
**⑨ item_pack_specs（包装换算，N6）**：spec_id PK；project_id NOT NULL（项目内条目；共享目录条目归属随 B16）；item_type enum('material','auxiliary','processing','mechanical','labor')；item_id int NOT NULL（类型化 FK，同资源层模式）；pack_unit varchar(20)；base_unit varchar(20)；pack_size decimal(18,6) CHECK >0（每包装含基本单位量）；spec_version int；UNIQUE(item_type,item_id,pack_unit,spec_version)。

**⑩ project_cost_prices（项目成本价，UD11 三层边界）**：price_id PK；project_id NOT NULL；catalog_type enum('auxiliary','processing','mechanical','labor')；catalog_item_id int NOT NULL（类型化 FK）；unit_price decimal(18,4) NOT NULL；currency_code char(3)；pricing_unit varchar(20)；valid_from/valid_to date NULL；price_version int；status enum('active','suspended')；UNIQUE(project_id,catalog_type,catalog_item_id,price_version)。

**⑪ 资源目录（加工/机械/人工，B16 所有权待确认）**：processing_catalog/mechanical_catalog/labor_task_catalog 三表：id PK；name varchar(255)；unit varchar(20)；description text；created_at/updated_at。**目录不带价格**（价格在⑩）；共享 vs 项目归属待 B16 确认后加 project_id 或保持共享。辅材沿用既有 Auxiliary_Materials（项目内，无价格字段不变）。

### 1.2 既有表扩展（2 张，不动已确认字段语义）

**Material_Quotations 增列**：valid_from date NULL；valid_to date NULL；status enum('active','suspended','expired') NOT NULL DEFAULT 'active'【状态机细则待 A4】；tax_inclusive tinyint(1) NOT NULL DEFAULT 0；pricing_unit varchar(20) NULL。**不改动** purchase_unit_price/confirmed_tax_rate/version（并发语义保留）/默认报价人工选择契约。
**audit_logs 增列（F7 复用审计的结构化支撑）**：project_id int NULL＋KEY(project_id,created_at)；entity_type varchar(50) NULL；entity_id int NULL＋KEY(entity_type,entity_id)。details 文本约定 JSON：{before:{…关键值},after:{…},reason?}。**向后兼容**：既有调用（Support::audit 三参）不受影响，新列可空；成本域写入必须带 project_id/entity 与前后关键值。不新建历史表（F7）。

### 1.3 不新建的表（明确排除）

快照族（cost_snapshots/snapshot_inputs/snapshot_line_results/snapshot_result_inputs/snapshot_totals/snapshot_voids）、版本确认记录表、幂等键表、变更影响索引表、独立修改日志表（cost_change_logs 由 audit_logs 复用替代）——F4/F5/F7 关闭。

### 1.4 完整性与隔离强制清单（F9）

- 全部项目表 project_id NOT NULL；组价/明细/设置/价格/包装对父级与材料/辅材/楼栋一律复合 FK；楼栋引用用既有 uq_building_id_project（已核实存在）。
- 成本域 FK 全 RESTRICT：删材料/楼栋/项目前必须解除引用（沿用受保护删除/归档模式）；**无 CASCADE**，确认后的组价不可能被级联删除。
- 唯一键：F1 一父一份；组件/资源行唯一；结果行唯一；费率版本唯一（生成列）；汇率币对日期唯一。
- CHECK：数量>0、资源恰一列、scope 与 building_id 匹配、汇率>0。
- 关键约束数据库强制，PHP 校验为友好提示（AGENTS.md 要求）。

## 2. 现有源码差异分析

> 证据：本轮只读核对 Support.php:216-235、audit_logs DDL（202611010001_core.sql:64-74）、PluginServiceRegistry.php、ProjectSpaceCatalog.php、project-foundation 迁移（uq_building_id_project）、material-management 迁移与源码（前轮已核实）。

### 2.1 可直接复用（无需改动）

| 现有能力 | 位置 | 复用方式 |
|---|---|---|
| 权限/CSRF/项目上下文 | Support::requirePermission、Application POST 统一 CSRF、ProjectContext::requireCurrentProject | 成本插件沿用同模式 |
| 审计写入 | Support::audit(PDO,action,details) | 成本域扩展 details JSON＋新列（§1.2） |
| 跨插件服务契约 | PluginServiceRegistry（publish/resolve＋清单 uses 声明强制）、ServiceLocator | 成本插件经契约取材料/报价数据，不直读私有表 |
| 空间目录契约范本 | ProjectSpaceCatalog（核心拥有接口、foundation 实现、requireLocation 校验归属） | 新增 MaterialCostCatalog 契约照此模式 |
| 楼栋复合唯一键 | uq_building_id_project (building_id,project_id) | cost_settings 楼栋复合 FK 直接可用 |
| 组成契约与并发保护 | MaterialCatalogService::saveComposition（行锁/单层/唯一键） | 零改动；组价创建时读取副本 |
| 报价 version 乐观锁 | Material_Quotations.version＋编辑校验 | 保留；组价编辑乐观锁照此模式 |
| 迁移台账增量机制 | ProjectO_Migrations＋bin/install.php | 新表走同机制（本轮不创建） |

### 2.2 需扩展（既有文件改动点，开发阶段执行）

| 文件/对象 | 现状 | 差异/改动 |
|---|---|---|
| audit_logs DDL＋Support::audit | details 纯文本、无 project_id/entity 列；audit() 三参 | 增列（§1.2）＋audit() 增加可选上下文参数或新增 auditEntity() 助手；既有调用点兼容 |
| Material_Quotations 迁移＋ProjectMaterialService 报价保存/列表 | 无有效期/状态/含税/计价单位 | 增列＋保存校验＋列表展示；默认报价选择增加有效性校验（A4 细则确认后） |
| material-management plugin.json | 无对外服务契约声明 | 发布 MaterialCostCatalog 契约（材料/组成/报价/辅材只读查询，项目作用域）；清单加 provides |
| Materials 单位字段 | unit 自由文本（"50kg" 类） | 新增 usage/purchase/pricing 单位角色与包装规格引用（§1.1⑨）；**unit 原字段与组成契约语义不动**；历史文本清洗属迁移闸门 |
| Auxiliary_Materials | 无价格字段 | 保持目录无价（价格进 project_cost_prices）——与旧系统"目录带 cost"不同，属有意差异 |

### 2.3 需新增（新插件 bom.cost，开发阶段执行）

- 插件骨架：plugin.json（requires platform.core/project.foundation/material.management；uses MaterialCostCatalog；routes/permissions/migrations/assets）、src/（CostingService/ResourceService/CalculationService/SettingsService/RateService）、views/、tests/。
- 新权限草案：view_costings/edit_costings/confirm_costings/manage_cost_settings/manage_exchange_rates/view_cost_reports（受众待 B20）。
- 路由草案：GET costings/costing-detail/cost-settings/cost-reports；POST costing.save/costing.confirm/costing.reopen/costing.sync-composition/costing.recalculate/resource.save/settings.save/rate.save 等（命名定稿随开发）。
- 计算服务：纯函数可测试；公式组确认前**只搭骨架与接口，不实现数值公式**（G2 闸门）。
- 汇总查询：项目/楼栋/分项实时聚合 cost_line_results×测算数量；楼栋归属经 Initial_Cost_Estimate_Detail/空间父级（复用既有归属模式，不复现旧"借楼栋"）。

### 2.4 明确不做（与旧系统/前版方案的差异声明）

- 不复制旧触发器/过程/视图（死自动化 B11；视图行膨胀 B5 不复现）。
- 不建快照/多版本/不可变账本（F4/F5/F7）。
- 不把 Task_BOM_Map 当 BOM 关联（B15）。
- 不改旧系统任何代码/数据；不执行未经审批迁移。

### 2.5 测试差异

- 现有 composer test 12 脚本（SQLite 等价）：成本域新增测试需**双轨**——SQLite 等价覆盖服务逻辑；MySQL 实测覆盖复合 FK/CHECK/生成列/乐观锁并发（SQLite 无法覆盖，列为 P1–P4 退出条件）。
- 锚点对照：bom42（3424.60→5887.74，旧口径）作固定输入/期望样本；新口径确认后更新期望值并留对照记录。
- 必含场景：跨项目拒绝、F1 重复组价拒绝、资源恰一列违例、确认后修改自动退草稿＋审计、缺价不静默、并发编辑 409、重算事务回滚。

## 3. 仍开放项（结构定稿不代为决策）

| 组 | 事项 | 影响 |
|---|---|---|
| 公式组（G2，阻塞计算实现） | B3 税链/税基；B4 双重加成/利润基数；B6 取价口径/缺价策略；B7 本位币/汇率规则；B14 损耗口径；B10 舍入路径 | cost_line_results 数值列的计算逻辑；结构已定稿不受阻 |
| 结构遗留（G1） | B16 资源目录所有权（⑪目标表形态）；B17 作用域/继承/未来日期；A4 报价状态机细则；N7 多行例外 | ③⑤⑩外键目标与校验细则；其余表不受阻 |
| 迁移组（G3） | B1 精度评估；B2 旧自引用边→parent_self 映射；B9 辅材语义；B13 重复边/全 0 费率；B5 历史差异签收 | 旧数据进新库的清洗规则 |
| 已由本轮冻结关闭 | N1 互斥/组合（F2 允许组合）；一父多份（F1 一份）；确认后修改流程（F6 自动退草稿）；快照存废（F4）；多版本（F5）；历史机制（F7 复用审计） | — |

## 4. 下一步建议（不开发，先评审）

1. 本定稿（§1）提交技术评审：重点核对复合 FK 目标键存在性（Materials/Auxiliary_Materials/Buildings 已核实）、生成列/CHECK 的 MySQL 版本前提、audit_logs 增列兼容性。
2. 公式组六项决策（11 号清单算例已备）与 B16/B17 结构遗留并行确认。
3. 评审通过后按 P1（组价结构，组件层可先行）→P2（规则/单位）→P3（计算，G2 后）→P4（确认/审计/并发）→P5（汇总衔接）→P6（迁移，G3 后）推进；每阶段退出条件见 14 号 §5。
4. 开发启动前不创建任何迁移；本文表结构为评审稿，落库 DDL 在迁移评审时逐字生成。

## 5. 边界声明

- 本轮只读：未修改任何源码/数据库/迁移，未创建迁移 SQL，未执行开发。
- 九条冻结原则按用户指令登记（§0）；其余开放项（§3）未代为决策，公式/费率/税/损耗/汇率数值一律未填。
- 已确认契约（Material_Compositions/样品/默认报价人工选择/招标首期）零改动；旧系统错误行为不作为新系统标准。
- 证据：本轮新核对 Support.php/audit_logs DDL/PluginServiceRegistry/ProjectSpaceCatalog/uq_building_id_project；其余引用 10–14 号已核实结论。
- 未验证：定稿的 MySQL 实测（开发阶段退出条件）；audit_logs 增列对既有调用点的全量兼容扫描（开发前执行）。
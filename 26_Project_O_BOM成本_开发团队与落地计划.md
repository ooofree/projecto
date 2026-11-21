# Project O BOM 与成本 V3.0 — 开发团队组建与落地计划

> 日期：2026-11-20。依据：`24_Project_O_BOM与成本_V3.0精简设计.md`（已确认完成稿）＋`25_Project_O_BOM与成本_规则总纲.md`（现行权威索引）。
> 性质：本轮把已确认的 V3.0 设计转为最小垂直切片开发；结构/机制先行，**数值计算公式在税率/抵扣/舍入定案前不实现**（总纲 §五 闸门）。
> 环境基线（本轮只读核实）：WSL Ubuntu-24.04，PHP 8.3.26（CLI），MySQL（PDO mysql），Composer PSR-4；既有插件 `material.management` / `project.foundation` / `document-management` / `labor-crew`。

## 1. 团队组建（角色与职责）

> 单体内部分工，对应一个新业务插件 `bom.costing`（BOM 组价与成本）。各角色边界互不重叠，共享同一套契约与迁移台账。

| 角色 | 职责（本轮交付） | 对应代码/产物 |
|---|---|---|
| **技术负责人 / 架构** | 插件边界、跨插件契约、DDL 评审闸门、并发模型（串行重算＋version）、冻结架构守住；把关待决策项不被代码默认解决 | 插件清单、迁移、服务接口、本计划与审核报告 |
| **DBA / 数据建模** | 8 新建＋3 扩展表 DDL；类型化可空 FK＋恰一 CHECK；复合项目 FK＋RESTRICT；定点精度；唯一索引；迁移可重复安全 | `database/migrations/*.sql` |
| **领域服务后端（组价）** | 组价头/资源行 CRUD；组件实时引用 Material_Compositions；取价四级优先级；缺项派生 calc_status；version 乐观锁 | `src/CostingService.php`、`src/PriceResolver.php` |
| **重算引擎后端** | 项目级串行锁；重算流程（取锁→读结构/version→当前时间验证有效性→计算→version 校验提交→审计→释放）；组成变化触发重算；结果行随组成清理 | `src/RecalcEngine.php` |
| **费用与分摊后端** | 实际费用登记；冲销五规则；调整归属继承；分摊锁与 Σ≤净额；三级统计恒等式 | `src/ActualCostService.php`（记录/冲销/调整/分摊/统计合一） |
| **前端 / 页面** | 组价列表与详情（双态展示＋缺项原因）；资源行与目录选择；实际费用登记页；目录（加工/机械/人工）维护；只读对比占位 | `views/*.php` |
| **测试 / 质量保证** | C1–C5 并发与规则用例；权限拒绝/失败/回滚；既有测试集回归；输出审核结论与未覆盖风险 | `tests/bom_costing_test.php` |

### 并行与串行边界
- **可并行**：DBA 表结构（契约先定）、费用分摊服务（独立于组价重算链路，总纲 §七 独立隔离）、前端只读占位页。
- **必须串行**：组价服务 → 重算引擎（依赖组价结构与结果表）；DDL 落地 → 服务开发 → C1–C5 实测。
- **不允许**：多角色同时改同一迁移文件/同一共享接口；在未确认业务规则前写默认数值公式。

## 2. 落地切片（对应 V3.0 §5 第 2 步：最小垂直开发）

### 2.1 范围：8 新建表 ＋ 3 扩展表

**新建（插件 `bom.costing` 迁移内）**
1. `bom_costings` 组价头（draft/confirmed × incomplete/calculable/complete，version）
2. `bom_costing_resources` 资源行（auxiliary/processing/mechanical/labor/parent_self，三类型化 FK＋恰一 CHECK，无快照字段）
3. `cost_line_results` 当前态明细（composition_id/resource_line_id 恰一；来源恰一；实际所用费率值；禁零价）
4. `cost_settings` 当前费率配置（project/building 两作用域，五费率，UNIQUE 生成列）
5. `project_cost_prices` 项目成本价（aux_id/catalog_id 两类型化 FK＋恰一 CHECK，类型化唯一索引）
6. `resource_catalog` 统一资源目录（processing/mechanical/labor，共享，不带价格，UNIQUE(catalog_type,name)）
7. `actual_cost_records` 实际费用（三对象类型化 FK＋严格绑定 CHECK；normal/reversal/adjustment 自引用）
8. `actual_cost_allocations` 分摊（record_id/costing_id 复合 FK，Σ≤净额）

**扩展（既有表，契约字段零改动）**
- `Material_Quotations`：补 `valid_from` / `valid_to` / `status` / `tax_status` 三态 / `pricing_unit`
- `audit_logs`：补 `project_id` / `entity_type` / `entity_id`（JSON details 复用现有 details 文本列）
- `Material_Compositions`：补 `UNIQUE(project_id, composition_id)`（仅索引，供结果表复合 FK）

### 2.2 服务链路
- **取价四级**（B6 框架，已确认）：①当前有效默认报价 → ②最新有效报价（先过滤有效性再按生效日排序，同日 quotation_id 最大）→ ③项目成本价（仅材料允许且有有效记录）→ ④缺价，禁零、禁伪装完整。
- **重算**：项目级串行（MySQL `GET_LOCK('cost_recalc_project_{id}')` 或等价单 worker）；人工结构修改事务内 version+1；重算提交条件更新 `WHERE version=:读值`，失败丢弃重试；组成边变化走输入驱动自动重算、不退草稿。
- **费用独立**：组价重算链路永不读写 actual_cost_records；分摊/冲销/调整写入先锁原记录行。
- **审计**：复用 `audit_logs`，最小内容集（来源/单价/数量/费率/金额前后＋触发原因 auto/manual＋actor＋时间）。

### 2.3 前端页面（垂直切片）
- 组价列表（父材料、双态、缺项数、更新时间）
- 组价详情（组件实时来自 Material_Compositions；资源行增删改；缺项原因标注；confirmed/complete 用途限制提示）
- 资源目录维护（加工/机械/人工，共享）
- 实际费用登记与分摊页（含冲销/调整入口，规则在服务端强制）

## 3. 闸门与顺序（总纲 §十二）

1. ~~决策会（五项核心＋V3.0 确认）~~ **已完成（2026-11-20）**
2. **DDL 评审**：本插件迁移 SQL 先评审（类型化 FK/CHECK/复合隔离/精度/索引），再在专用测试库执行
3. **测试库 MySQL 实测＋并发测试 C1–C5**（含组成实时引用）；实测反过来验证精简是否足够
4. 服务开发 → 前端切片 → 回归既有测试集
5. 公式组确认后再填充数值计算（当前仅落结构、取价、状态、规则强制）

## 4. 明确不实现（待决策，未确认前禁止写代码默认解决）

- 具体税率数值、可抵扣规则、舍入路径（总纲 §五/§十一）
- A4 报价状态机/暂存 unknown；A7 calculable 用途；N7 同资源多行例外；B17 费率继承（推荐整行覆盖，待批）
- 材料"允许项目成本价"开关形态；调整链/比例自动分摊/同步调减；confirmed 变化显著标记/通知
- 后置不建：exchange_rates、units、unit_conversions、item_pack_specs（出现真实场景再建）
- 作废机制不得复活：快照/多版本/四字段并发协议/副本组成表/资源行快照字段

## 5. 测试面（V3.0 §3.3，17→4＋1）

| 用例 | 断言 |
|---|---|
| C1 重算中人工编辑 | 重算提交 version 校验失败丢弃，编辑结果为准，下轮重算用新结构 |
| C2 同项目两事件并发 | 串行执行，最终明细＝最新输入，无覆盖丢失 |
| C3 分摊并发超额 | 两事务同原记录各加分摊，锁串行，Σ≤净额 |
| C4 冲销/调整联动 | 负向调整致净额不足→拒绝；恒等式每步成立 |
| C5 组成实时引用 | 组成边增/改→自动重算反映；边删→结果行清理；样品齐套与组价同一来源一致 |

## 6. 本轮交付清单与验收标准

- 新插件 `bom.costing`：`plugin.json`、迁移、领域服务、重算引擎、费用/分摊服务、视图、测试
- Composer PSR-4 登记新命名空间
- 测试：C1–C5 通过；既有 `composer test` 回归不破坏
- 审核报告：已实现范围、待决策项、未覆盖风险、MySQL 实测结论
- 验收红线：项目隔离数据库强制；缺价禁零；类型化真实外键；审计可解释"1200 为何变 1350"；confirmed×calc_status 双态展示；未实现不描述为已实现。

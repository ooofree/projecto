# Project O BOM 与成本 V3.0：精简设计（恰到好处版）【已确认完成稿】

> **定稿状态（2026-11-20）**：用户指令"按推荐"——§4 五项核心决策全部按推荐默认确认（税单层/加成单层/损耗进数量/目录共享/同日取 id 最大）。本稿由"设计提案"转为**已确认完成稿**，是 BOM 与成本新设计的现行定稿。结构/机制细节仍待 DDL 审批与 MySQL 实测（不因此回退为提案）；具体税率数值、抵扣规则、舍入路径仍待业务/评审。
> **决策记录 DR-1（2026-11-20，用户直接批准）**："单一组成、组价实时引用、删副本表"——**删除 bom_costing_components 副本表**，组价组件**实时引用 Material_Compositions**（唯一组成定义）；废除"复制组成/人工同步组成/来源追溯字段"整套机制；组成变化与价格变化同属输入驱动自动重算。新建表 **11→10**。本记录直接修订下文相关条目，不出新版本号。
> **决策记录 DR-2（2026-11-20，用户直接批准）**：①加工/机械/人工三张目录**合成一张 resource_catalog**（catalog_type 区分；单一真实表非多态，共享归属不变）；②资源行**去 unit/name 快照，实时读目录**（与 DR-1 组件口径对齐，一切目录信息单一来源）。新建表 **10→8**。资源行/成本价/实际费用的类型化引用列由 4–5 个减为 2–3 个（catalog_id 统一指向 resource_catalog）。
> 日期：2026-11-19 成稿，2026-11-20 定稿。任务性质：只读设计精简。依据：用户指令"**设计要恰到好处，尽量要高效率**"＋已确认业务背景（全新国内项目、CNY、不迁移旧数据、新库已有真实数据）。基准：22 号 V2.2.4＋23 号终审；本稿是它们的**瘦身替代**，不是新增层。
> 未修改任何源码/数据库，未创建迁移，未开发。标注：【设计提案】【源码已存在】【待业务确认】【待开发验证】。
> 新库实况（本轮只读核实）：2 个项目（火山引擎展厅/Coding办公室装修）、73 材料、**1 条报价（CNY）**、0 组成、0 辅材、0 招标、43 行测算、15 样品；单位干净（m²/个/套/m），无外币、无复合包装单位。

## 0. 精简原则

1. **每个机制必须有真实场景支撑**——为理论风险建的协议，砍。
2. **完整性底线不降**：项目隔离、禁静默零价、修改留痕、类型化真实外键，保留且数据库强制。
3. **十条冻结架构全部保持**（自动取价/自动重算/不人工逐项接受/人工改结构退草稿/费用独立/费用≠消耗/无快照多版本账本/结果＋审计追溯/不完整禁入利润分析/Tender_List 沿用）。
4. 精简的是**实现机制的复杂度**，不是业务能力。

## 1. 砍掉什么、为什么

| 原机制 | 处置 | 理由（基于真实数据与规模） |
|---|---|---|
| input_fingerprint＋input_change_seq＋result_seq＋structure_version 四字段并发协议 | **砍**，改串行重算＋单版本号（§3） | 2 项目/73 材料规模下并发冲突概率极低；四字段协议的边界条件（同代次异常/时间因素/半成品）制造了最近两轮大部分 P0。串行队列从根上消除这些问题类 |
| 17 个双连接测试用例（T-A～T-Q） | **缩到 4 个**（§3.3） | 协议简化后测试面同步缩小 |
| units/unit_conversions/item_pack_specs 三表 | **后置**（不建） | 现有单位全是标准基本单位，无换算需求；出现真实换算场景再建（预留扩展位） |
| exchange_rates 表 | **后置**（不建） | 全 CNY 无外币；出现外币报价再建，届时缺汇率阻断规则随之启用 |
| 三级统计的复杂勾稽展示 | **保留规则、简化实现** | 恒等式本身是防重底线（保留），但无需专门勾稽页面，对比视图一个口径注释即可 |
| 包装/量纲换算门禁 | **简化为单位一致校验** | 报价计价单位≠组价单位→标缺项；不做换算链（无数据支撑） |
| 指纹异常路径/时间因素主动递增代次 | **随四字段协议一并砍** | 串行重算每次读当前有效输入，时间因素天然被覆盖（重算即按今天验证有效性） |
| bom_costing_components 副本表＋同步组成机制＋来源追溯字段（source_composition_id/key/synced_at/by/quantity_adjusted_by） | **砍**（DR-1），组价实时引用 Material_Compositions | 动态组价下价格已是活的、历史靠审计，组成冻结副本是快照时代残留；一父一份组价（F1）下组成本来就是材料定义，无需第二套组成；样品管理读的也是 Material_Compositions，单一来源消除双表不一致风险 |
| 加工/机械/人工三张同构目录表 | **合一**（DR-2）：resource_catalog＋catalog_type | 三表结构完全相同、归属相同（共享）；合一是单表类型字段，外键全部指向真实表，**不是跨表多态**；新建表 10→8，下游引用列同步减少 |
| 资源行 unit/name_snapshot | **砍**（DR-2），实时读目录 | 与 DR-1 组件口径对齐：目录信息单一来源，改名/改单位即时反映；计算时点所用单位/价格仍落结果表（F8 可复核不受影响） |

**保留不动的**：类型化可空外键＋恰一 CHECK（防多态/跨类型）、复合项目外键（隔离）、冲销/调整净额规则与归属继承（防统计失真）、分摊表（一费多组真实需求）、税三态、缺价禁零、审计复用 audit_logs、confirmed×calc_status 双态。

## 2. 精简后表结构（8 张新建＋3 扩展，DR-1/DR-2 后）

> 通用：project_id NOT NULL＋复合 FK；RESTRICT；UTC；金额 decimal(18,4)、费率 decimal(6,4)、数量 decimal(18,6)、原始系数 decimal(10,2)；定点计算。

**① bom_costings（组价头）**：costing_id PK；project_id；parent_material_id（复合 FK→Materials）；status enum('draft','confirmed')；calc_status enum('incomplete','calculable','complete')；**version bigint NOT NULL DEFAULT 0**（唯一并发判据，§3）；confirmed_by/at；created_by/at；updated_at。UNIQUE(project_id,parent_material_id)【F1】；UNIQUE(project_id,costing_id)。
- 对比 V2.2.4：删 input_fingerprint/input_change_seq/result_seq/structure_version/pending_recalc/calc_issues 六字段，换 1 个 version。缺项清单由明细行状态派生查询，不落头表。

**② 组件＝实时引用 Material_Compositions（DR-1，不建表）**：组价组件不落副本表，按头表 parent_material_id＋project_id **实时读取 Material_Compositions 当前边**（child_material_id/quantity）。原始系数即 Material_Compositions.quantity decimal(10,2)（契约不动）；含损耗的计算数量在计算时得出、存入 cost_line_results.quantity。组成增删改→事件触发组价自动重算（与价格同机制，输入驱动不退草稿）；历史组成靠审计前后值重建，不靠冻结副本。样品管理与组价共用这唯一组成来源。
- **组成边删除的连带处理**：边被删→对应 cost_line_results 结果行在重算时移除（当前态明细随组成）；Material_Compositions 删除保护（既有契约：被引用材料拒硬删）不变。结果行引用 composition_id 的 FK 处置见④。

**③ bom_costing_resources（资源行，DR-2 后）**：resource_line_id PK；costing_id/project_id；resource_type enum('auxiliary','processing','mechanical','labor','parent_self')；**三个类型化可空 FK：aux_id（复合项目 FK→Auxiliary_Materials）/catalog_id（FK→resource_catalog）/material_id（复合项目 FK→Materials，parent_self 用）**＋CHECK 恰一＋类型匹配（processing/mechanical/labor 均走 catalog_id，且 resource_type 必须＝目标行 catalog_type，跨行校验事务内做）；resource_key 生成列 COALESCE(aux_id,catalog_id,material_id)；quantity；loss_rate NULL【B14 决策＝不启用】；**无 unit/name 快照（DR-2：实时读目录/材料表）**。UNIQUE(costing_id,resource_type,resource_key)；parent_self 受控写入（服务从头表取父材料 id）。

**④ cost_line_results（当前态明细）**：result_id PK；costing_id/project_id；**composition_id/resource_line_id 恰一**（DR-1：组件结果直接引用 Material_Compositions.composition_id，复合 FK (project_id,composition_id)——需 Material_Compositions 增 UNIQUE(project_id,composition_id)，属既有表扩展，见下）；quantity（含损耗计算数量）/unit_price/currency_code/pricing_unit；quotation_id/project_cost_price_id/manual_price＋manual_confirmed_by 恰一来源＋真实 FK；source_tax_status；mgmt_rate/profit_rate/tax_rate/trans_rate/ins_rate（实际所用值）；subtotal/mgmt_amount/profit_amount/tax_amount/comprehensive；formula_version/rounding_spec_version；calculated_at/by。UNIQUE(costing_id,composition_id)/(costing_id,resource_line_id)；unit_price>0 禁零价。
- 删 fx_rate_id/fx_rate_value（无外币；外币出现时随汇率表一起加回）。
- **组成边删除→结果行清理**：结果行是派生当前态；composition_id 复合外键采用 **ON DELETE CASCADE**（迁移 202611220001），删边即清理对应结果行，随后重算按剩余结构重建。原设想的“RESTRICT＋重算先清结果再删边”不成立：重算发生在删边之后，RESTRICT 会让删边本身报 1451，用户侧表现为删子材料 500。【已确认】

**⑤ cost_settings（当前费率配置）**：setting_id PK；project_id；scope_type enum('project','building')；building_id NULL（复合 FK）；building_key 生成列；五费率 decimal(6,4)；created_by/at；updated_by/at。UNIQUE(project_id,building_key)；修改 UPDATE＋同事务审计。继承规则待 B17（推荐整行覆盖）。

**⑥ project_cost_prices（项目成本价，DR-2 后）**：price_id PK；project_id；**aux_id/catalog_id 两个类型化 FK＋恰一 CHECK**（catalog_id→resource_catalog，覆盖加工/机械/人工）；unit_price/currency_code/pricing_unit；valid_from/to NULL；status；created_by/at；updated_by/at。类型化唯一索引×2：(project_id,aux_id)/(project_id,catalog_id)（NULL 不去重）；UNIQUE(project_id,price_id)。

**⑦ resource_catalog（统一资源目录，DR-2）**：catalog_id PK；**catalog_type enum('processing','mechanical','labor') NOT NULL**；name/unit/description；created_by/at；updated_by/at。**共享归属**（B16 已确认）；不带价格（价格在 project_cost_prices）。UNIQUE(catalog_type,name)（同类型不重名，防重复录入）。目录项被资源行/成本价/实际费用引用时受保护删除（RESTRICT）。
- 辅材目录仍用既有 Auxiliary_Materials（项目内，契约不动）——它带项目归属，与共享的 resource_catalog 性质不同，不合并。

**⑧ actual_cost_records（实际费用，DR-2 后）**：record_id PK；project_id；building_id NULL；cost_category NOT NULL＋**三对象类型化 FK（material_id/aux_id/catalog_id）＋严格绑定 CHECK**（material↔material_id；auxiliary↔aux_id；processing/mechanical/labor↔catalog_id 且 category＝目标 catalog_type 事务校验；other↔三列全空）；cost_object_key 生成列（仅索引）；description；quantity/unit/unit_price/amount（<>0）；record_type enum('normal','reversal','adjustment')；reversal_of_record_id/adjustment_of_record_id 复合自引用 FK＋恰一 CHECK＋目标 normal 事务校验；currency_code/tax_status/occurred_at/source_type/source_ref/recorded_by。UNIQUE(project_id,record_id)。
- 冲销/调整规则全保留（累计≤原额/币种单位归属继承/被引用后归属禁改/负向联动分摊）——这些是统计正确性底线，不是过度设计。

**⑨ actual_cost_allocations（分摊）**：allocation_id PK；project_id；record_id/costing_id 复合 FK；allocated_amount>0；allocated_by；UNIQUE(record_id,costing_id)。锁协议保留但简化表述：**一切分摊/冲销/调整写入先锁原记录行**（六步顺序保持，这是防超额的最低成本方案）。

**扩展 3 张（DR-1 后）**：Material_Quotations（valid_from/to、status、tax_status 三态、pricing_unit）；audit_logs（project_id/entity_type/entity_id＋JSON details）；**Material_Compositions 增 UNIQUE(project_id,composition_id)**（供结果表复合 FK；仅加索引，契约字段/语义零改动）。

**统计：8 新建＋3 扩展**。核对：V2.2.4 共 15 张；后置汇率/单位包装 4 张→11；DR-1 删组件副本表→10；DR-2 三目录合一（3→1）→**8**（组价头 1＋资源行 1＋结果 1＋设置 1＋成本价 1＋resource_catalog 1＋费用 2）。扩展 3 张（Material_Quotations/audit_logs/Material_Compositions 唯一索引）。

## 3. 并发模型：串行重算＋单版本号（替代四字段协议）

### 3.1 机制【设计提案】
- **项目级串行重算队列**：同一项目的重算任务排队执行（应用层互斥：MySQL `GET_LOCK('cost_recalc_project_{id}')` 或等价单 worker 队列），**同一时刻一个项目只有一个重算在跑**。不同项目可并行。
- **触发**：上游保存事件（报价/成本价/费率/默认切换/恢复/暂停/有效期调整/**组成边增删改 DR-1**）→入队；打开组价/列表时若 version 落后于输入变化→补算入队；每日轻量定时扫描兜底。事件丢失不影响正确性（读取时按当前时间验证有效性＋version 比对）。组成变化与价格变化同为输入驱动，自动重算不退草稿。
- **version 语义**：组价头唯一版本号。**组价自身的人工结构修改**（资源行增删改/数量/来源）事务内 version+1（兼乐观锁）；**重算不改 version**；**组成边变化（Material_Compositions）属上游输入，不递增组价 version**，走自动重算（DR-1）。
- **重算流程**：拿项目锁→读组价当前结构与 version→按当前时间验证取价有效性→计算→**提交时校验 version 未变**（变了＝期间有人工编辑→丢弃本轮，重新入队）→更新明细＋审计→释放锁。
- **为什么这样就够**：串行消除了"两个重算互相覆盖"（不存在并发重算）；version 校验消除了"重算覆盖人工编辑"；每次重算读当前有效输入消除了"时间因素/同代次指纹"问题类。**旧不盖新、半成品、同代次异常三个问题类整体消失**，不需要四个字段分别防。
- **人工编辑 vs 重算**：编辑事务对组价头行锁＋version+1；重算提交的条件更新（WHERE version=:读值）失败即丢弃。编辑优先，重算可安全重试。
- **规模论证**：2 项目、73 材料、单项目组价数预计几十个；一次重算毫秒级；串行吞吐远超实际需求。未来若实测不足，再升级并发协议（届时四字段方案作为备选档案保留在 22/23 号）。

### 3.2 保留的底线
- 缺价/单位不匹配/税口径 unknown→该行不落结果、calc_status 降级、禁零价（不变）。
- confirmed×calc_status 双态用途限制（不变）。
- 审计最小内容集（来源/单价/数量/费率/金额前后＋触发原因＋actor，不变；trigger=auto/manual）。
- 三级统计恒等式＋冲销调整净额规则（不变）。

### 3.3 测试面（17→4）
| 用例 | 断言 |
|---|---|
| C1 重算中人工编辑 | 重算提交 version 校验失败丢弃，编辑结果为准，下轮重算用新结构 |
| C2 同项目两事件并发 | 串行执行，最终明细＝最新输入，无覆盖丢失 |
| C3 分摊并发超额 | 两事务同原记录各加分摊，锁串行，Σ≤净额 |
| C4 冲销/调整联动 | 负向调整致净额不足→拒绝；恒等式每步成立 |
| C5 组成实时引用（DR-1） | 组成边增/改→组价自动重算反映；边删→对应结果行清理；样品齐套与组价读同一来源一致 |
- MySQL 实测仍为开发闸门（本轮不执行）。

## 4. 五项核心决策【已确认：2026-11-20 用户"按推荐"】

| # | 决策 | 确认结论（原推荐默认） | 理由 |
|---|---|---|---|
| 1 | 税口径 | **报价录入含税标记；组价成本按不含税口径归集；成品报价税＝单层增值税，不重复计** | 国内增值税场景最简防重口径；具体税率/抵扣规则仍待业务 |
| 2 | 双重加成（B4） | **单层**：只在项目/楼栋级计管理费＋利润，取消分项级加成 | 国内短周期项目无需两层；旧双层是埃塞项目做法，不作标准 |
| 3 | 损耗（B14） | **做进数量系数**（calculated_quantity 含损耗），不建显式 loss_rate 字段 | 新数据从零录，录入时直接含损耗最省事；字段预留不启用 |
| 4 | B16 目录归属 | **加工/机械/人工目录共享**＋项目成本价隔离 | 工种/机械台班单价跨项目通用；项目各自定价互不影响 |
| 5 | 取价同日决胜 | **quotation_id 最大**（录入序） | 确定性、零歧义；当前仅 1 条报价，短期不紧迫 |
- 以上五项**已经用户确认**（2026-11-20"按推荐"）；公式组闸门就此打开，计算逻辑可进入实施准备（DDL 仍需单独审批）。具体税率数值、可抵扣规则、舍入路径仍待业务/评审定案，不在五项之内。

## 5. 落地路径（高效率版）

1. ~~决策会：§4 五项＋确认本精简稿~~ **已完成**（2026-11-20 用户"按推荐"，本稿转已确认完成稿）。
2. **批准最小垂直开发**：8 表 DDL＋3 扩展（专用测试库）→组价 CRUD（组件/资源实时引用）＋串行重算骨架→一条确认公式跑通→C1–C5 实测。**实测反过来验证精简是否足够**；不足再按需加机制（有档案可查）。
3. **并行数据就绪**：72 个材料报价录入（纯业务工作，不依赖开发）；辅材目录/组成关系按项目需要建。
4. 公式组确认后填充计算；实际费用录入界面随垂直切片一起上（工地早一天能记账，对比早一天有数据）。
- 不再出修订版本；本稿＋决策结论即为开发依据（DDL 仍需单独审批）。

## 6. 边界声明
- 只读设计精简：未改源码/数据库/迁移，未开发，未实测。
- 十条冻结架构与已确认契约零改动；精简的是机制复杂度，完整性底线（隔离/禁零价/留痕/类型化外键/统计防重）全保留。
- 五项核心决策已经用户确认（2026-11-20"按推荐"）；具体税率数值/抵扣规则/舍入路径仍未定案。
- 四字段并发协议与 15 表方案作为备选档案保留于 22/23 号，实测证明不足时可回退升级。
- 全文按四类标注；未把未验证设计描述为已实现。
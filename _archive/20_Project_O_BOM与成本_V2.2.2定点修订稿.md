# Project O BOM 与成本 V2.2.2：定点修订稿

> 日期：2026-11-19。任务性质：只读定点修订（禁止开发与落库）。基准：《19_Project_O_BOM与成本_V2.2.1定点修订稿》；依据：用户对 V2.2.1 的审查结论（6 个 P0＋3 个 P1，§0 对照）。
> 本轮未修改任何源码/数据库，未创建迁移文件/SQL/脚本，未开发任何功能，未扩大业务范围。标注体系沿用：【设计提案】【源码已存在】【待业务确认】【待开发验证】。
> 用户建议保留的十条核心架构登记为冻结（§1）；公式/税率/利润/管理费/损耗/汇率规则/B16/B17/B6 剩余细则/A4 仍未定案，未代为决定。

## 0. 审查问题落实对照

| 优先级 | 问题 | 落实位置 | 状态 |
|---|---|---|---|
| P0-1 | 成本对象跨类型归属风险 | §2.1 类别↔对象严格 CHECK（恰一正确） | 【设计提案】完成 |
| P0-2 | 费用归属组价语义/多组价分摊 | §2.2 独立分摊表＋净额上限＋未分摊保留 | 【设计提案】完成 |
| P0-3 | 默认报价恢复后回切与触发范围 | §2.3 恢复即回最高优先级＋触发清单扩展 | 规则按用户指令定稿 |
| P0-4 | pending_recalc 并发丢失标记 | §2.4 input_change_seq 变更代次 | 【设计提案】完成 |
| P0-5 | 部分冲销规则不完整 | §2.5 五条冲销规则＋受控校验 | 【设计提案】完成 |
| P0-6 | 币种/单位转换统一 | §2.6 取价后转换门禁，复用既有换算结构 | 【设计提案】完成 |
| P1-1 | 实际费用金额精度 | §3.1 与成本计算统一精度/舍入规范 | 完成 |
| P1-2 | 招标/测算工程量不一致 | §3.2 双列展示不直接相减 | 完成 |
| P1-3 | 预计最终成本重复计入 | §3.3 互斥构成＋未接入≠0 | 完成 |

## 1. 冻结登记（用户本轮确认保留的十条核心架构）

1. Tender_List 沿用现有投标报价功能，不重建。
2. 技术 BOM 与成本资源分层。
3. 当前组价自动取价、自动重算。
4. 上游价格变化不要求人工逐项接受。
5. 组价结构人工修改仍退回草稿。
6. 实际费用独立于动态组价。
7. 实际费用与实际消耗分层。
8. 不建复杂成本快照、BOM 多版本、历史账本。
9. 通过当前结果、指纹、审计追溯成本变化。
10. 不完整成本不得参与完整利润分析。
- 以上与九条冻结原则（F6 按 V2.2 修订语义）一致，本轮零改动；Material_Compositions/样品/默认报价人工选择/招标首期契约零改动。

## 2. 六项 P0 修订

### 2.1 P0-1：成本对象严格 CHECK（恰一正确，非"至少一个"）

actual_cost_records 的 CHECK 由"恰一非空"升级为**类别与对象列严格绑定**【设计提案】：
```
CHECK (
  (cost_category='material'   AND material_id   IS NOT NULL AND aux_id IS NULL AND proc_id IS NULL AND mech_id IS NULL AND labor_task_id IS NULL)
  OR (cost_category='auxiliary'  AND aux_id        IS NOT NULL AND material_id IS NULL AND proc_id IS NULL AND mech_id IS NULL AND labor_task_id IS NULL)
  OR (cost_category='processing' AND proc_id       IS NOT NULL AND material_id IS NULL AND aux_id IS NULL AND mech_id IS NULL AND labor_task_id IS NULL)
  OR (cost_category='mechanical' AND mech_id       IS NOT NULL AND material_id IS NULL AND aux_id IS NULL AND proc_id IS NULL AND labor_task_id IS NULL)
  OR (cost_category='labor'      AND labor_task_id IS NOT NULL AND material_id IS NULL AND aux_id IS NULL AND proc_id IS NULL AND mech_id IS NULL)
  OR (cost_category='other'      AND material_id IS NULL AND aux_id IS NULL AND proc_id IS NULL AND mech_id IS NULL AND labor_task_id IS NULL)
)
```
- material_id=15 与 aux_id=15 同时有值→**数据库直接拒绝**，不依赖服务层。
- other 类五列全空＋description 必填（既有规则保持）。
- cost_object_key 生成列保持"仅归属索引、不参与唯一性"（V2.2.1 定位不变）；严格 CHECK 下它恒等于该类别唯一对象 id，索引语义更干净。
- 各对象列真实外键保持（material/aux 复合项目 FK；proc/mech/labor 待 B16）。

### 2.2 P0-2：费用↔组价独立分摊关系（替代单 costing_id）

**语义定稿**【设计提案】：一笔实际费用可分摊到多个组价（一批玻璃用于三个玻璃门组价）；原始费用只登记一次；未分摊部分保留在项目实际费用中。

**结构变更**：
- actual_cost_records **移除 costing_id 列**（V2.2.1 的单归属外键由分摊表替代，避免双归属来源）。
- **新增表 actual_cost_allocations（第 15 张新建表）**：
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| allocation_id | int AI | PK |
| project_id | int NOT NULL | FK→Projects |
| record_id | int NOT NULL | 复合 FK (project_id,record_id)→actual_cost_records（需 records 增 UNIQUE(project_id,record_id)） |
| costing_id | int NOT NULL | 复合 FK (project_id,costing_id)→bom_costings(project_id,costing_id) |
| allocated_amount | decimal(18,4) NOT NULL | CHECK >0；币种随原记录（不另存币种，防口径分裂） |
| allocated_by | int NOT NULL | FK→Users |
| created_at / updated_at | timestamp | |
| UNIQUE uq_allocation (record_id,costing_id) | | 同费用同组价一条分摊（调整＝改该行＋审计） |
- **净额上限规则**：Σ(某 record 的分摊金额) ≤ 该 record 的**可分摊净额**＝amount＋Σ(指向它的 reversal/adjustment 金额)。
  - 强制方式：分摊写入/修改事务内对原记录行 FOR UPDATE，读取当前净额与已分摊合计后校验（跨行求和无法用声明式约束表达）【设计提案＋待开发验证】；可选守卫触发器兜底（仅拒绝超额，不承载业务计算）。
  - **冲销联动**：原记录被冲销致净额下降、已分摊超额时，冲销事务必须同步调减分摊（或拒绝冲销并提示先调分摊）——同事务一致性，不允许"净额为负而分摊仍在"。
- 未分摊部分＝净额−已分摊合计，留在项目级实际费用汇总（对比视图项目口径含全部净额，组价口径只含已分摊）。
- 分摊修改/删除＝人工操作＋审计前后值；组价重算链路仍不读写费用/分摊表（隔离保持，冻结第 6 条）。
- 首期 UI 可先支持单分摊，多分摊结构已就位【开发范围，非业务决策】；是否允许"按比例自动分摊"为可选扩展【待业务确认】。

### 2.3 P0-3：默认报价恢复回切＋触发范围扩展（用户规则定稿）

- **回切规则（用户确认）**：默认报价只要重新满足有效条件，即重新取得最高优先级——Q001 失效回退 Q002（130 元）后 Q001 恢复有效→自动切回 Q001（120 元）。取价结果始终符合四级优先级，无"回退粘滞"。
- **来源变化必须可解释**：回切属输入驱动自动重算（不退草稿），审计按 §6（V2.2.1）最小内容集记录 price_source_before/after（"Q002 回退→Q001 恢复"链可见）＋单价前后＋触发原因＝默认报价恢复有效。
- **重算触发清单扩展**（原仅报价保存/默认切换/成本价/汇率/费率）：
  1. 报价**恢复有效**（status 由 suspended/expired→active，或有效期调整使其重新有效）；
  2. 报价**被暂停**（active→suspended）；
  3. 报价**有效期调整**（valid_from/valid_to 编辑，含缩短致失效/延长致生效）；
  4. 时间因素（到期失效/新生效）按 V2.2.1 §3 查询时验证＋定时机制，不依赖保存事件。
- 上述 1–3 均为保存类事件（材料插件发布），4 为时间类（查询时验证兜底）；两类都进指纹比对与审计。

### 2.4 P0-4：input_change_seq 变更代次（防并发丢失标记）

**竞态确认**：重算 A 期间报价 B 变化，A 结束清 pending_recalc=0 会吞掉 B 的待算标记。

**修订**【设计提案】：bom_costings 增列：
| 字段 | 类型 | 说明 |
|---|---|---|
| input_change_seq | bigint NOT NULL DEFAULT 0 | 变更代次：每次影响本组价的上游事件＋1 |
| pending_recalc | tinyint NOT NULL DEFAULT 0 | 保持（V2.2.1），语义改为"代次未追平"标记 |
- **事件侧**：定位受影响组价后，事务内 `UPDATE bom_costings SET input_change_seq=input_change_seq+1, pending_recalc=1 WHERE costing_id=?`（原子自增，与事件处理同事务）。
- **重算侧**：
  1. 开始：读当前 seq_start（与乐观锁 updated_at 同事务）；
  2. 执行重算（读当前有效输入→计算→更新结果/指纹/审计）；
  3. 提交前：`UPDATE … SET pending_recalc=0 WHERE costing_id=? AND input_change_seq=:seq_start`——**条件清除**：期间代次已增（rowCount=0）则不清标记，本轮结果仍提交（它是 seq_start 时点的正确结果），但 pending 保持 1，立即再算一轮（或交下次触发）；
  4. 循环直至追平代次；重试有限次后保留 pending=1 交定时/打开兜底。
- **正确性底线不变**：即使代次机制失效，读取路径指纹比对＋查询时有效性验证仍保证不用过期价（V2.2.1 §3.1）；代次解决的是"及时性标记不丢"。
- 时间因素（无事件）不增代次，由查询时验证/定时扫描覆盖；定时扫描触发重算时同样走代次协议。
- 验收：A 重算中 B 变化的双连接用例必须断言"最终结果含 B 的影响且 pending 归零"【待开发验证】。

### 2.5 P0-5：部分冲销完整规则

**五条规则（用户建议采纳）**【设计提案】：
1. **允许多次部分冲销**：10000 元先冲 2000 再冲 3000 合法。
2. **累计上限**：Σ(指向某原始记录的 reversal 金额绝对值) ≤ 原始记录 amount；完全冲销后不得继续冲销。
3. **冲销不可再冲销**：reversal_of_record_id 必须指向 record_type='normal' 的记录；指向 reversal/adjustment→拒绝。
4. **币种一致**：冲销记录 currency_code 必须与原记录一致。
5. **可追溯**：reversal_of_record_id 非空（既有 FK RESTRICT 保持）＋审计记录冲销链。
- **强制方式**：规则 2/3/4 为跨行校验，冲销写入事务内对原记录行 FOR UPDATE→读 record_type/currency/已有冲销合计→校验→插入；声明式约束无法表达跨行求和【设计提案＋待开发验证】；可选守卫触发器兜底。
- **adjustment 语义区分**：结算调整（±）不占冲销额度，但计入可分摊净额（§2.2）；adjustment 同样不可被冲销（reversal 目标仅限 normal）。
- record_type 与 amount 符号 CHECK 保持（normal>0；reversal<0；adjustment 可正可负但 <>0）。
- 净额统计＝amount＋Σ(reversal)＋Σ(adjustment)，对比视图按此净额（防失真）。

### 2.6 P0-6：取价后币种/单位统一转换门禁

**规则**【设计提案】：取价（四级优先级）完成后、进入组价公式前，必须完成币种与计量单位的有效转换；**无法转换→标记缺项，禁止直接比较或计算**。
| 场景 | 处理 |
|---|---|
| 报价币种≠组价本位币 | 经 exchange_rates 折算（币对/方向/兜底待 B7）；缺有效汇率→缺项阻断，不静默 1:1 |
| 项目成本价币种≠报价币种 | **不做来源间直接比较**：每行按自身币种独立折算到本位币；优先级选择按"有效性"判定，不按折算后价格高低（防汇率波动改变取价优先级）【设计提案】 |
| 报价按套、组价按平方米 | 经 item_pack_specs（包装→基本单位）＋unit_conversions（量纲）换算；无有效换算链→缺项阻断 |
| 包装/量纲换算关系变化 | 进入重算触发范围（单位/包装编辑事件）＋指纹已含换算输入（V2.1.2 §1.3），变化即重算 |
- **统一复用**：换算只走 V2.1.2 已定的 units/unit_conversions/item_pack_specs 结构与 exchange_rates，**不另建换算规则**（用户明示）；换算链与所用系数落明细（F8 可复核）。
- 单位不匹配/缺换算的缺项类型并入 calc_issues（V2.2.1 §4），calc_status 降级。

## 3. 三项 P1 修订

### 3.1 P1-1：实际费用金额精度统一
- actual_cost_records.amount/unit_price、actual_cost_allocations.allocated_amount 与成本域统一：**decimal(18,4) 存储**；展示与最终舍入按币种最小单位（N4：CNY/ETB/USD=2、JPY=0 等）＋统一 rounding_spec_version。
- 实际费用**不设独立舍入规则**；录入值按存储精度保存，汇总舍入与组价同一规范版本，避免 18,4 与最终舍入冲突。
- 冲销/分摊的加减运算全程定点（bcmath），禁 float（N4 保持）。

### 3.2 P1-2：招标/测算工程量不一致的对比展示
- 对比视图**双列展示**招标工程量与测算工程量＋差异标记；**不静默取其一、不直接相减**（用户明示）。
- 投标收入按招标工程量、预计成本按测算工程量分别计算并标注各自基准；"量差"单独列示（工程量差异导致的金额差与价格差异分离）。
- 统一基准的选择仍【待业务确认】；确认前双列＋差异标记为默认展示。

### 3.3 P1-3：预计最终成本互斥构成（防重复计入）
- **构成公式（口径提案，数值公式待公式组）**：项目预计最终成本＝已发生费用净额（费用口径，含已采购未消耗部分）＋尚未发生的预计成本（组价中未被已发生费用覆盖的部分）。
- **已采购未消耗/已消耗材料成本是已发生费用的分解视图，不是可加项**——四桶关系明示：已发生（总）⊃ 已消耗＋已采购未消耗（库存模块接入后才有数据）；不得四者相加。
- **未接入≠0**：采购/库存/消耗数据未接入时显示"未接入"，不参与计算、不以 0 冒充（用户明示）；首期对比页五区块：投标收入 / 当前动态预计成本 / 已登记实际发生费用 / 成本不完整项目清单 / 未接入数据说明。
- "尚未发生预计成本"的覆盖判定（哪些组价行已被实际费用覆盖）依赖分摊关系（§2.2）＋类别对齐；判定细则【待业务确认】，首期可先展示"已发生 vs 预计总额"两列＋阶段标注（V2.2.1 保持）。

## 4. 结构与约束变更汇总（相对 V2.2.1）

| 对象 | 变更 |
|---|---|
| actual_cost_records | **移除 costing_id**；CHECK 升级类别↔对象严格绑定（§2.1）；增 UNIQUE(project_id,record_id)（供分摊复合 FK）；冲销规则受控校验（§2.5） |
| **actual_cost_allocations（新增）** | 第 15 张新建表；复合 FK×2（records/costings）；UNIQUE(record_id,costing_id)；allocated_amount>0；净额上限事务校验 |
| bom_costings | 增 input_change_seq bigint NOT NULL DEFAULT 0；pending_recalc 语义改条件清除（§2.4） |
| cost_line_results | 无字段变更；取价后转换门禁（§2.6）为计算前置 |
| 其余表 | 无变更 |
| 统计 | **15 张新建＋2 张既有扩展**（14→15，新增分摊表）；全部仍为设计，未创建迁移 |
| 触发范围 | 报价恢复/暂停/有效期调整＋单位包装编辑进入事件触发清单（§2.3/§2.6） |
- 新增外键全 RESTRICT；分摊表项目复合 FK 保证跨项目拒绝；冲销自引用 FK 保持。
- B16 依赖不变（proc/mech/labor 目标形态）；公式组依赖不变（转换门禁的本位币/汇率规则待 B7）。

## 5. 待业务确认清单（增量）

| 事项 | 状态 |
|---|---|
| 按比例自动分摊（可选扩展） | 待确认 |
| 冲销时已分摊超额的处置（同步调减 vs 拒绝冲销，提案默认拒绝并提示） | 待确认 |
| "尚未发生预计成本"覆盖判定细则 | 待确认 |
| 工程量统一基准 | 待确认（双列展示为默认） |
| B6 剩余细则/A4/B7/B16/B17/公式组/N7/G3 | 仍开放，未变 |
| 本轮已确认 | 默认恢复回切规则；部分冲销五规则；分摊语义；严格类别绑定；转换门禁；十条核心冻结 |

## 6. 验收与数据库结构终审条件

### 6.1 本轮修订验收（设计自检）
P0-1 严格 CHECK 拒绝双对象/错类别 ✅；P0-2 分摊表＋净额上限＋未分摊保留＋隔离保持 ✅；P0-3 回切＋触发扩展＋审计链 ✅；P0-4 代次条件清除＋双连接验收用例 ✅；P0-5 五规则＋受控校验 ✅；P0-6 转换门禁复用既有结构 ✅；P1 精度统一/双列量差/互斥构成＋未接入≠0 ✅。（均为设计满足，MySQL 实测待开发验证）

### 6.2 终审重点（用户指定六项，作为终审清单）
1. 所有多态外键是否彻底消除（结果行/价格来源/资源/pack/prices/实际费用对象——逐表核对类型化 FK＋恰一 CHECK）；
2. 所有跨项目关联是否有复合外键保护（含新增分摊表/冲销自引用）；
3. 实际费用类型化关联与冲销约束是否成立（严格 CHECK＋净额/累计上限事务校验）；
4. 自动重算是否存在并发丢失（代次协议双连接实测）；
5. 单位/汇率/税口径是否完整支撑动态计算（转换门禁＋unknown 阻断＋指纹覆盖）；
6. 是否仍存在重复统计或跨楼栋串价（冲销净额＋分摊不超额＋汇总按组价/归属精确聚合）。
- **终审通过后才进 DDL；本轮不批准开发**（用户明示）；公式组/B16 闸门独立；B6 细则/A4 定稿前取价逻辑不实现。

## 7. 边界声明
- 本轮只读定点修订：未修改任何源码/数据库/迁移，未创建迁移文件/SQL/脚本，未开发功能，未扩大业务范围。
- 十条核心架构按用户指令冻结登记；回切/冲销/分摊/严格绑定规则来自用户本轮审查，已登记；其余未确认项未代为决定。
- 九条冻结原则（F6 按 V2.2 语义）与 Material_Compositions/样品/默认报价人工选择/招标首期契约零改动；未建快照/多版本/历史账本。
- 全文按四类标注；未把未验证设计描述为已实现或已通过实测。
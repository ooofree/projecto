# Project O BOM 与成本 V2.2.3：定点修订稿

> 日期：2026-11-19。任务性质：只读定点修订（禁止开发与落库）。基准：《20_Project_O_BOM与成本_V2.2.2定点修订稿》；依据：用户对 V2.2.2 的审查结论（4 项待修订，§0 对照）。
> 本轮未修改任何源码/数据库，未创建迁移文件/SQL/脚本，未开发任何功能，**未新增任何成本管理概念**——仅把实际费用、分摊、冲销/调整、并发重算的底层约束做严密。标注体系沿用：【设计提案】【源码已存在】【待业务确认】【待开发验证】。
> 十条冻结架构与全部既有业务边界保持（§4），不重新打开讨论；公式/税率/利润/管理费/损耗/汇率规则/B16/B17/B6 细则/A4 仍未定案。

## 0. 审查问题落实对照

| 优先级 | 问题 | 落实位置 | 状态 |
|---|---|---|---|
| P0-1 | 负向 adjustment 影响分摊净额无联动约束 | §1.1 四条联动规则 | 【设计提案】完成 |
| P0-2 | adjustment 缺少原始记录关联字段 | §1.2 adjustment_of_record_id＋约束 | 【设计提案】完成 |
| P0-3 | 并发重算旧代次覆盖新结果 | §1.3 代次单调写入保护 | 【设计提案】完成 |
| P0-4 | CHECK 三值逻辑与自引用复合外键落地条件 | §1.4 NOT NULL＋复合自引用 FK | 【设计提案】完成 |

## 1. 四项修订

### 1.1 P0-1：负向调整的分摊净额联动（与冲销同规则）

**问题确认**：原费用 10000、已分摊 8000，发生 −3000 结算调整后净额 7000 < 分摊 8000，V2.2.2 只对 reversal 规定了联动，adjustment 遗漏。

**修订规则**【设计提案】：
1. **负向 adjustment 与 reversal 执行完全相同的分摊净额校验**：写入事务内对原记录行 FOR UPDATE→计算调整后净额（amount＋Σreversal＋Σadjustment＋本次）→与现有分摊合计比对。
2. **净额不足以覆盖现有分摊→默认拒绝该调整**，提示"请先调减分摊"（与 V2.2.2 冲销默认处置一致；同步调减作为可选路径仍待业务确认，本轮不改默认）。
3. **正向 adjustment 增加可分摊净额**：明确为可分摊额度上升（已分摊不变，未分摊额度增加）；正向调整同样走事务校验（防止并发下净额读旧值）。
4. **所有调整（正/负）与分摊校验同一事务完成**：调整插入＋净额校验＋（若选择调减路径）分摊更新＋审计，原子提交；失败整体回滚，不允许"净额已变而分摊未校验"的中间态。
- 统一表述：**任何使原记录净额下降的操作（reversal、负向 adjustment）都必须先过分摊覆盖校验**；净额上升操作（正向 adjustment）无覆盖风险但仍事务内读取最新值。
- 可分摊净额公式不变：amount＋Σ(reversal)＋Σ(adjustment)；本轮只是把 adjustment 纳入与 reversal 相同的联动闭环。

### 1.2 P0-2：adjustment_of_record_id 明确关联

**问题确认**：净额按"原记录＋其冲销＋其调整"计算，但 adjustment 没有关联字段，无法定位哪笔原费用的净额变化、哪笔分摊需重校验。

**修订**【设计提案】：actual_cost_records 增列：
| 字段 | 类型 | 约束/说明 |
|---|---|---|
| adjustment_of_record_id | int NULL | **复合 FK (project_id,adjustment_of_record_id)→actual_cost_records(project_id,record_id)** ON DELETE RESTRICT；record_type='adjustment' 时必填（CHECK），normal/reversal 时必须为空（CHECK） |
- **目标类型限制**：只能指向 record_type='normal' 的原始费用；指向 reversal/adjustment→拒绝。**普通外键无法表达目标行类型，由写入事务内读取目标行 record_type 校验**（与冲销同机制）【设计提案＋待开发验证】。
- **跨项目隔离**：复合 FK 携带 project_id，数据库拒绝跨项目调整（目标唯一键 (project_id,record_id) 已在 V2.2.2 建立）。
- **调整链禁止**：adjustment 不得再次被调整（目标仅限 normal，自然封死调整链）；如未来需要调整链，须另行设计明确规则【待业务确认，本轮不建】。
- **与 reversal_of_record_id 互斥**：一条记录只能是 normal/reversal/adjustment 之一，两个关联字段按 record_type 恰一启用（CHECK）：
```
CHECK (
  (record_type='normal'    AND reversal_of_record_id IS NULL AND adjustment_of_record_id IS NULL)
  OR (record_type='reversal'   AND reversal_of_record_id IS NOT NULL AND adjustment_of_record_id IS NULL)
  OR (record_type='adjustment' AND adjustment_of_record_id IS NOT NULL AND reversal_of_record_id IS NULL)
)
```
- 金额符号规则保持：normal>0；reversal<0；adjustment 可正可负但 <>0。
- 净额归集口径由此闭合：项目级汇总＝全部记录净额；原记录可分摊净额＝其自身 amount＋指向它的 reversal＋指向它的 adjustment；分摊覆盖校验（§1.1）依赖此关联定位。
- 备选口径（用户提及）：若业务决定 adjustment 不关联原费用，则必须重定义归集与分摊口径——**本轮按关联方案定稿**，不关联方案不再保留为默认【设计提案，关联语义随终审确认】。

### 1.3 P0-3：并发重算的旧代次禁覆盖（代次单调写入）

**问题确认**：A/B 同读第 10 代，上游进入第 11 代，B 先完成写入第 11 代结果，A 后完成以第 10 代结果覆盖——pending_recalc 仍为 1 也不能防止"新先写旧后盖"。input_change_seq 自增只解决标记丢失，不解决写入顺序。

**修订：结果写入必须代次单调**【设计提案】：
- bom_costings 增列：
| 字段 | 类型 | 说明 |
|---|---|---|
| result_seq | bigint NOT NULL DEFAULT 0 | **当前结果表所对应的输入代次**（已提交结果的有效代次） |
- **写入协议（强制）**：
  1. 重算开始：事务内读 input_change_seq 为 seq_start（与既有乐观锁同事务）；
  2. 计算完成提交时，结果更新与代次推进为**同一条件语句**：
     `UPDATE bom_costings SET result_seq=:seq_start, pending_recalc=(input_change_seq<>:seq_start), … WHERE costing_id=? AND result_seq < :seq_start`
  3. **rowCount=0（已有≥seq_start 的结果提交）→本次结果整体丢弃**（结果行写入同事务回滚），旧代次不得覆盖新代次；
  4. rowCount=1→结果有效；pending 按代次差条件置位（V2.2.2 协议保持）；
  5. 结果明细行（cost_line_results）的更新与头表条件更新同事务——头表条件不满足则明细一并回滚，**不存在"明细已写而头表代次未推进"的半态**。
- **等价替代**（实现可选，语义必须等价）：提交时对组价头行 FOR UPDATE 后比较 result_seq 再写；或计算任务所有权（同一 costing 同时仅一个重算任务持锁）。**无论哪种实现，验收断言一致：最终结果只能由有效代次写入**【待开发验证】。
- 与 V2.2.2 代次协议的关系：input_change_seq 管"标记不丢"，result_seq 管"旧不盖新"，两者叠加闭环；读取路径指纹比对仍是正确性底线。
- **双连接测试扩展（硬性验收）**：
  - T-A：A/B 同读第 10 代，B 先提交第 11 代，A 后提交→断言 A 被拒绝（rowCount=0）、最终结果＝第 11 代、result_seq=11；
  - T-B：pending_recalc 最终归零（V2.2.2 用例保持）；
  - T-C：A 提交瞬间上游再变（第 12 代）→A 可提交第 10 代结果但 pending=1，随后第 12 代重算覆盖，最终 result_seq=12；
  - T-D：旧代次任务重试不得绕过条件更新（直接 UPDATE 明细被事务边界阻止）。

### 1.4 P0-4：CHECK 落地条件与复合自引用外键

**（a）cost_category NOT NULL 与三值逻辑**【设计提案】：
- 明确 **cost_category enum(...) NOT NULL**（V2.2.2 字段表已写 NOT NULL，本轮升级为显式落地条件并说明理由）：CHECK 表达式涉及 NULL 时结果为 UNKNOWN 而非 FALSE，**MySQL 对 UNKNOWN 的 CHECK 视为通过**——若类别可空，严格绑定 CHECK 会被 NULL 绕过。
- 落地条件清单：
  1. cost_category NOT NULL（类别校验的前提）；
  2. 五个对象 ID 列允许 NULL（类型化可空外键形态保持）；
  3. 严格绑定 CHECK 完整覆盖六类别×五列的对应关系（V2.2.2 §2.1 表达式保持，其正确性以类别 NOT NULL 为前提）；
  4. other 类五列全空＋description 必填（description NOT NULL 已有；other 语义由 CHECK 分支保证）；
  5. **不得仅依赖 CHECK 完成类别校验**——服务层写入同样强制类别非空与对象匹配（双层，数据库为底线）。
- 同原则适用全部含可空列的 CHECK（资源恰一/价格来源恰一/行引用恰一）：这些 CHECK 的"恰一"分支以非空列判定，NULL 参与时 UNKNOWN 风险已由"恰一非空"的显式 IS NULL/IS NOT NULL 组合覆盖（每个分支对全部相关列做了空/非空断言，无未约束列）——终审核对项（§3）。

**（b）冲销/调整自引用复合外键**【设计提案】：
- reversal_of_record_id 的外键由单列升级为 **(project_id,reversal_of_record_id)→actual_cost_records(project_id,record_id)** ON DELETE RESTRICT（目标唯一键已存在）。
- adjustment_of_record_id 同形态复合 FK（§1.2）。
- **目标必须为 normal**：复合 FK 只保证存在性与同项目，类型限制由写入事务校验（读目标行 record_type）；可选守卫触发器兜底（仅拒绝非 normal 目标，不承载业务计算）【待开发验证】。
- 跨项目冲销/调整被数据库直接拒绝；自引用删除保护保持（原记录被冲销/调整引用时不可删）。

## 2. 结构与约束变更汇总（相对 V2.2.2）

| 对象 | 变更 |
|---|---|
| actual_cost_records | 增 adjustment_of_record_id（复合自引用 FK RESTRICT）；reversal_of_record_id 升级复合 FK；record_type↔两关联字段恰一 CHECK；cost_category NOT NULL 显式落地条件；负向调整纳入分摊覆盖校验 |
| bom_costings | 增 result_seq bigint NOT NULL DEFAULT 0；结果提交改代次单调条件更新（明细同事务） |
| actual_cost_allocations | 无字段变更；覆盖校验触发源扩展至负向 adjustment |
| 其余表 | 无变更 |
| 统计 | **15 新建＋2 扩展不变**（本轮纯字段/约束/协议修订，无新表、无新概念） |
- 新增外键全 RESTRICT；无新增业务模块；十条冻结架构零改动。

## 3. 数据库结构终审清单（更新，用户指定重点）

终审以 20 号 §6.2 六项为底，叠加本轮四项：
1. 全部多态外键彻底消除（逐表类型化 FK＋恰一 CHECK）；
2. 全部跨项目关联复合外键保护——**含本轮冲销/调整复合自引用 FK、分摊表复合 FK**；
3. 实际费用类型化关联与冲销/调整约束成立——**严格绑定 CHECK 以 cost_category NOT NULL 为前提；目标 normal 类型事务校验；累计冲销≤原额；负向调整分摊联动**；
4. 自动重算并发正确性——**代次标记不丢（input_change_seq）＋旧不盖新（result_seq 单调写入）双协议，T-A～T-D 双连接实测**；
5. 单位/汇率/税口径完整支撑动态计算（转换门禁＋unknown 阻断＋指纹覆盖）；
6. 无重复统计/跨楼栋串价（冲销与调整净额＋分摊不超额＋精确聚合）；
7. **CHECK 三值逻辑审查**：全部 CHECK 逐条核对可空列组合，确认无 UNKNOWN 绕过路径；
8. **净额闭环**：任一原记录的 amount＋Σreversal＋Σadjustment 与分摊合计关系在任意操作序列后恒成立（操作序列测试：登记→分摊→部分冲销→负向调整→正向调整→再分摊）。
- **终审通过后才单独讨论是否批准 DDL**（用户明示）；本轮不批准开发；公式组/B16 闸门独立；B6 细则/A4 定稿前取价逻辑不实现。

## 4. 保留的业务边界（用户本轮重申，零改动）

Tender_List 沿用；动态组价自动取价/重算；上游变化不要求人工逐项接受；人工结构修改退草稿；实际费用独立登记不覆盖组价；实际消耗与采购/入库/费用分开；不建 BOM 多版本/成本快照/历史账本；追溯＝当前结果＋指纹＋审计；缺价/缺汇率/缺换算标记不完整禁止错误计算；未接入数据显示"未接入"不为 0。十条冻结架构与九条原则（F6 按 V2.2 语义）保持。

## 5. 待业务确认（增量极小）

| 事项 | 状态 |
|---|---|
| 调整链（adjustment 再调整）是否需要——本轮默认禁止 | 待确认（如需另行设计） |
| 净额不足时"同步调减分摊"可选路径（默认拒绝保持） | 待确认（V2.2.2 遗留） |
| 其余开放项 | 与 V2.2.2 一致，未变 |

## 6. 边界声明
- 本轮只读定点修订：未修改任何源码/数据库/迁移，未创建迁移文件/SQL/脚本，未开发功能，未新增成本管理概念。
- 四项修订均为底层约束严密化，来自用户本轮审查指令；冻结架构与已确认契约零改动；公式/费率/税/损耗/汇率/B16/B17 未定案。
- 全文按四类标注；代次单调写入、复合自引用 FK、CHECK 落地条件、分摊联动均为设计提案，未声称已实现或已通过 MySQL 实测。
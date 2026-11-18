# Project O BOM 与成本管理架构补充审查 V1.2

> 日期：2026-11-18。任务性质：第三轮架构修订（只读）。基础：V1.1（12 号文件）、决策清单（11 号）、行为基线（10 号）。
> 本轮未修改任何 PHP/SQL/JS/CSS 或数据库数据，未创建迁移，未执行开发。所有新字段、表、触发器、流程均为**提案**，不是已实现能力。
> 保护规则遵守声明：Material_Compositions 已确认契约、样品管理、默认报价人工选择、招标首期边界零改动；税率/利润/管理费/损耗/汇率/成本公式未代为决定；旧系统错误行为不作为新系统标准；A1–A8、B1–B20 原始事项全部保留（§3 矩阵含原结论列）。
> 可行性证据：开发库 MySQL 8.0.46（第一轮已核实）支持 CHECK 约束（8.0.16+）与生成列；新系统目标库为新建 MySQL（README）。下文约束方案以 MySQL 8.0.16+ 为部署前提。

## 0. 结论速览与 V1.1→V1.2 修订对照

| V1.1 遗留问题 | V1.2 修订 |
|---|---|
| 版本确认与快照确认耦合（确认草稿需 complete，版本冻结发生在成本确认事务内） | **彻底解耦**：BOM 版本确认＝纯结构行为，不依赖任何价格/汇率/费率；成本快照必须引用已确认版本，一版本可有多快照；两套独立状态机（§1.1） |
| 统一明细表 bom_version_lines 用 line_type+ref_entity_id 多态引用 | **废弃多态**：组件层与资源层分表（方案 B），资源层用"类型化可空外键列＋CHECK 恰一"，全部真实外键（§1.2/§1.3） |
| snapshot_inputs UNIQUE(snapshot_id,input_type,source_entity_id) 不足以表达语义槽位 | 改为 **input_slot 语义槽位唯一键**；结果与输入用带 role 的关联表＋复合外键（§1.3，N8） |
| cost_settings UNIQUE 含可空 building_id（MySQL 唯一索引对 NULL 失效） | **生成列 building_key=IFNULL(building_id,0)＋唯一键**；同日多次调整用 setting_version；继承覆盖与未来日期策略显式化（§1.4） |
| 确认并发未设计 | 版本行锚点锁＋报价行锁/版本复核＋幂等键＋输入摘要比对＋完整回滚＋MySQL 双连接测试方案（§1.5，N3） |
| 快照不可变仅靠约定 | **数据库级不可变**：冻结表只 INSERT/SELECT 权限＋守卫触发器＋状态机触发器；作废/取代用追加标记表，不 UPDATE 历史（N2） |
| 技术 BOM 与综合成本配方未区分 | **明确不等价**：技术 BOM＝材料组成（已有契约层）；综合成本配方＝技术 BOM＋资源层＋费率/税/汇率输入；版本分两层冻结（§1.2） |
| 阶段计划公式未定阻塞全部结构 | **三闸门 P0–P7**：结构闸门（部分可先行）/公式闸门/迁移闸门分离（§7） |

## 1. 五项核心修订

### 1.1 BOM 版本与成本快照解耦

**规则（提案，状态机细节待业务确认）**
1. BOM 版本确认**不依赖**报价、汇率、费率是否齐全——版本是"结构事实"（组成＋资源清单），不是"金额事实"。缺价材料照样可以确认版本。
2. 成本快照**必须引用一个已确认的 BOM 版本**（数据库级校验：快照插入时版本必须存在确认记录，外键＋守卫触发器双重保证）。
3. **一个 BOM 版本允许存在多个成本快照**（1:N）：价格波动后重新确认、不同费率作用域试算、不同本位币口径（若 B7 确认支持）都产生新快照，旧快照保留。
4. 两套**独立状态机**：
   - BOM 版本：`draft → confirmed`；`superseded` 为**派生状态**（同一父材料存在更新的已确认版本时自动视为被取代，不 UPDATE 旧行）。draft 可编辑可删除；confirmed 不可直接修改、不可删除。
   - 成本快照：**出生即 confirmed**（草稿不落库，实时计算——B18 边界）；无状态列，`voided` 由追加的作废标记表派生。快照行永不 UPDATE/DELETE。
5. 材料**当前组成**（Material_Compositions）变化不得影响历史版本：版本确认时冻结副本，副本与当前组成无外键联动（只保留来源记录），当前组成增删改不触碰副本。
6. 版本确认与快照确认的权限分离（建议 view/edit_bom_versions 与 confirm_cost_snapshots 分设，B20 一并评审）。

**为什么这样拆**：旧系统把"结构"与"金额"混在视图实时推导里，导致改价即改历史（10 号报告 §2.6）。解耦后：结构变更走版本链，价格变更走快照链，两条链各自可追溯；招标/测算可以先引用"结构已确认但价格未确认"的版本做数量工作，不被价格缺失阻塞。

流程图见 §6.1–§6.3。

### 1.2 BOM 组成与成本资源清单分层（方案 A/B/C 对比）

**先回答概念问题：技术 BOM ≠ 综合成本配方。**
- 技术 BOM＝父项的材料组成（子材料＋数量系数＋单位），回答"用什么做"；在新系统即 Material_Compositions（已确认契约，单层）。
- 综合成本配方＝技术 BOM ＋ 辅材/加工/机械/人工 ＋ 父项自身采购成本 ＋ 费率/税/汇率输入，回答"花多少钱"。
- 两者必须分层：技术 BOM 可以独立确认（送样、招标数量、施工备料都只依赖它）；成本配方在技术 BOM 之上叠加资源与价格。V1.1 把六类行塞进一张 bom_version_lines，混淆了这两层，且多态引用无法建真实外键。

**三方案对比**

| 维度 | A 统一明细表（V1.1 原案） | B 组件表＋资源清单分表（推荐） | C 统一成本目录实体＋类型扩展表 |
|---|---|---|---|
| 结构 | 一张 lines 表，line_type+ref_entity_id | bom_version_components（纯材料边）＋ bom_version_resources（类型化可空外键列） | 先建统一 catalog_items 实体＋加工/机械/人工扩展表，lines 只引用 catalog_items |
| 数据库约束能力 | **弱**：ref_entity_id 无法按类型建外键；重复/跨类型错误只能靠 PHP | **强**：组件对 Materials 复合外键；资源每个类型列各自真实外键＋CHECK 恰一＋生成列唯一键 | 强（对 catalog_items 真实外键），但类型语义仍在扩展表，跨表一致性需额外约束 |
| 项目隔离能力 | 弱：多态 id 无法复合项目外键 | **强**：组件 (project_id,child_material_id) 复合 FK；辅材 (project_id,aux_id) 复合 FK；共享目录列按共享规则引用 | 中：统一目录实体横跨共享/项目两种所有权（辅材已确认项目独立），所有权边界被实体合并打破 |
| 未来扩展成本 | 加类型＝加枚举，但约束债越积越多 | 加资源类型＝加一个可空外键列＋CHECK 分支（一次小改表）；组件层永不受影响 | 加类型＝加扩展表，目录统一但迁移既有 Auxiliary_Materials（已确认契约）风险高 |
| 与已确认契约兼容 | 兼容但约束弱 | **完全兼容**：组件层就是 Material_Compositions 的冻结镜像 | 需重构辅材目录，**触碰已确认契约，不可接受** |
| 结论 | 废弃 | **推荐** | 仅共享目录层可选借鉴（加工/机械/人工是否统一目录实体属 B16 范畴），不用于版本行 |

**推荐方案 B 的形态（提案）**
- `bom_version_components`：version_id＋child_material_id＋quantity＋单位/名称快照；UNIQUE(version_id,child_material_id)；复合 FK (project_id,child_material_id)→Materials。语义与当前组成契约一致（单层、系数>0），是确认时点的冻结副本。
- `bom_version_resources`：version_id＋resource_type enum('auxiliary','processing','mechanical','labor','parent_self')＋**五个可空类型列** aux_id/proc_id/mech_id/labor_task_id/material_id（parent_self 用 material_id 指向父项自身）＋quantity/系数＋单位快照。
  - CHECK：五列**恰好一列非空**，且与 resource_type 匹配（MySQL 8.0.16+ 强制）。
  - 每个类型列各自真实外键：辅材走 (project_id,aux_id) 复合 FK；加工/机械/人工走共享目录 FK（所有权待 B16）；parent_self 的 material_id 走 (project_id,material_id) 复合 FK 且必须等于版本的父材料（触发器或生成列校验）。
  - 唯一性：生成列 `resource_key = COALESCE(aux_id,proc_id,mech_id,labor_task_id,material_id)`（CHECK 保证恰一非空），UNIQUE(version_id,resource_type,resource_key)——消除 NULL 唯一索引失效问题，同版本同资源默认仅一行（N7 例外规则待业务确认）。
- 快照对两层分别冻结输入与结果；成本计算＝组件层＋资源层一起进公式（公式未确认，结构先行）。

### 1.3 数据库引用完整性修订

**原则：关键完整性必须由数据库强制，PHP 校验只是第一道友好提示（用户指令）。**

| 问题 | V1.1 缺陷 | V1.2 修订（提案） |
|---|---|---|
| line_type+ref_entity_id 多态引用 | 无法建真实外键，跨类型/跨项目错误只能靠代码 | 废弃多态：方案 B 类型化可空外键列＋CHECK 恰一＋各列真实 FK（§1.2） |
| 同一材料同版本重复 | V1.1 唯一键含可空列，NULL 失效 | 组件层 UNIQUE(version_id,child_material_id)（两列均 NOT NULL）；资源层生成列 resource_key＋UNIQUE(version_id,resource_type,resource_key)；parent_self 每版本至多一行（唯一键自然保证） |
| snapshot_inputs 唯一性 | UNIQUE(snapshot_id,input_type,source_entity_id) 无法区分"同一报价用于多行"与"同一币对汇率" | **input_slot 语义槽位**：如 `line:{line_id}:price`、`fx:{from}:{to}`、`setting:{setting_id}`、`line:{line_id}:loss`；UNIQUE(snapshot_id,input_slot)；每行仍存 source_entity_id＋冻结值 |
| 输入与结果明细关联 | 无关联，重算时不知哪个结果用了哪个输入 | `snapshot_result_inputs(result_id,input_id,role)` 关联表：role enum('price','fx','loss','mgmt_rate','profit_rate','tax_rate','trans_rate','ins_rate')；UNIQUE(result_id,role)；双复合外键（携带 snapshot_id）保证输入与结果同属一个快照 |
| 项目复合外键 | 部分表仅单列 FK | 所有项目内引用一律 (project_id,entity_id) 复合 FK（沿用 uq_material_project_id 既有模式）；共享目录引用按共享规则单列 FK 并显式标注所有权 |
| 删除与归档 | 未定义 | 成本域**全部 FK ON DELETE RESTRICT，无任何 CASCADE**（对齐"不级联清除业务历史"）；draft 版本删除须事务内先删子行（RESTRICT 强制顺序）；confirmed 版本/快照由守卫触发器＋权限禁止删除；归档用状态/标记表达，不用删除 |
| 确认后版本/快照被级联删除 | 存在风险（V1.1 未显式禁止） | 三重防护：① 所有指向版本/快照的 FK RESTRICT；② 冻结表守卫触发器禁止 UPDATE/DELETE；③ 应用数据库账号对冻结表仅 INSERT/SELECT 权限（N2） |

**不可变机制（N2 详述）与 AGENTS.md 的相容性**：守卫触发器只做"拒绝写入"（SIGNAL），不承载业务计算、不产生第二套副作用，属完整性守卫而非业务自动化，不违反"同一规则单一权威执行位置"。业务规则（状态转换合法性）仍在应用服务，触发器只兜底。

### 1.4 费率版本及生效时间（cost_settings 修订）

**MySQL 唯一索引的 NULL 问题（必须显式解决）**：UNIQUE(project_id,scope_type,building_id,setting_version) 中 building_id 为 NULL 时，MySQL 视 NULL 互不相等，**项目级行可无限重复**。方案对比：
- 方案 a（推荐）：**存储生成列** `building_key INT GENERATED ALWAYS AS (IFNULL(building_id,0)) STORED`，UNIQUE(project_id,building_key,setting_version)。building_id 自 1 起，0 专表项目级；building_id 仍保留真实 FK（可空），building_key 仅用于唯一键。
- 方案 b：项目级/楼栋级拆两张表。约束同样强，但继承查询与快照引用要 UNION 两表，复杂度更高。
- 方案 c：哨兵 building_id=0＋FK。0 无对应楼栋行，FK 失败，**不可行**。

**修订后的 cost_settings（提案）**
| 机制 | 设计 |
|---|---|
| 唯一性 | UNIQUE(project_id,building_key,setting_version)；setting_version 在同一 (project,building_key) 内单调递增（事务内取 MAX+1，行锁保护） |
| 同一天多次调整 | 每次调整＝新 setting_version 行（同 effective_date 可多行），**不覆盖历史**（修复旧 bulk_edit 覆盖缺陷）；选择规则确定性：effective_date≤基准日 中取 MAX(effective_date)，同日取 MAX(setting_version) |
| 生效日期重叠 | 不同 effective_date 的多行是正常历史链，不构成冲突；同日期多行由 version 决胜；若业务要求"区间互斥"，需 valid_from/valid_to 区间模型＋服务层重叠校验（MySQL 无排他约束）——**是否采用区间模型待 B17 确认** |
| 项目级继承/楼栋级覆盖 | 草案：**整行覆盖**（楼栋行存在即整行取代项目行，缺项不逐字段合并）——简单、可审计、快照只需冻结一行；逐字段合并为可选扩展，**需业务决策**（B17） |
| 未来日期生效 | 结构允许存未来 effective_date；**是否立即生效待 B7/B17 确认**；选择规则的"基准日"参数化（默认今天），快照冻结所选行与基准日 |
| 历史快照引用 | 快照输入槽位 `setting:{setting_id}` 冻结**具体版本行 id＋各费率值**；费率行本身不可变（新版本＝新行），上游"变更"永远是新增行，不改旧行 → 快照天然不受影响 |
| 不可变 | active 行一经被快照引用即事实不可变；守卫：被引用的 setting 行禁止 UPDATE/DELETE（触发器检查引用计数或权限分离）；未引用行的更正也建议走新版本而非 UPDATE（审计完整） |

### 1.5 成本确认并发与幂等（新增设计）

**场景与对策（提案）**
| 场景 | 对策 |
|---|---|
| 重复点击确认 | 客户端生成 **idempotency_key**（UUID），UNIQUE(project_id,idempotency_key)；重复请求命中已存在快照 → 原样返回（幂等重放），不产生第二份 |
| 两个用户同时确认同一版本 | 事务第一步 **SELECT … FROM bom_versions WHERE version_id=? FOR UPDATE**（锚点行锁）串行化；后到者拿到锁后重新校验输入摘要 |
| 报价在确认期间变化 | 草稿计算时记录各报价的 quotation_id＋version（既有并发列）；确认事务内对涉及的报价行 **FOR UPDATE 短锁**，逐行比对 version/单价与草稿期望；不一致 → **409＋差异清单**，要求刷新草稿后重试（不静默用新价、也不静默用旧价） |
| 汇率在确认期间变化 | 汇率选择规则一次性选定具体 rate_id（不可变历史行）；确认期间新增汇率行不改已选行 → 快照按选定行冻结；是否强制"必须用最新"由确认策略决定（待 B7） |
| 费率在确认期间变化 | 同汇率：cost_settings 版本行不可变，选定 setting_id 后新增行不影响；快照冻结所选行 |
| 失败事务完整回滚 | 单事务边界：锁版本→校验已确认→采集并复核输入→计算→插入快照族（头/输入/结果/关联）→审计→提交；任一步失败整体回滚，**不留半个快照**；快照族插入顺序固定，死锁按固定锁序规避 |
| 同一版本多次成本确认 | 允许（1:N）；每次新 idempotency_key＝新快照；"最新有效快照"为派生查询（未作废中 confirmed_at 最新）；是否自动取代旧快照**待业务确认**（B18 细则），默认并存 |

**锁定范围与顺序（固定，防死锁）**
1. bom_versions 锚点行（FOR UPDATE）；
2. 涉及报价行按 quotation_id 升序 FOR UPDATE；
3. 插入快照族（自增 id，无跨事务锁竞争）；
4. 提交。死锁（1213）/锁等待超时（1205）有限次重试（建议 ≤2 次，重试仍失败返回 503 可诊断错误）。

**输入摘要比对**：草稿展示时计算 expected_input_digest（全部输入槽位值的 SHA-256）；确认请求携带该摘要；服务端锁内重算比对，不一致 → 409。这是"确认期间上游变化"的统一兜底（比逐行比对更完整）。

**MySQL 双连接测试方案（提案，专用测试库，不碰生产）**
| 用例 | 步骤 | 断言 |
|---|---|---|
| T1 双击幂等 | 连接 A/B 同 idempotency_key 并发确认 | 仅 1 个快照；两请求返回同一 snapshot_id |
| T2 双人并发 | A/B 不同 key 同版本同时确认 | 行锁串行；第二个要么成功（输入未变，1:N 允许）要么 409（输入已变）；无死锁残留 |
| T3 确认中改价 | A 持锁确认中，B UPDATE 报价 | B 被报价行锁阻塞至 A 提交；A 用旧价成功或 B 先提交则 A 409；绝不出现"半新半旧"快照 |
| T4 确认中新增汇率/费率行 | A 确认中 B INSERT 新 rate/setting 行 | A 按选定行冻结成功；新行不影响 A；事后影响记录（A8）生成 |
| T5 事务中断 | A 插入快照族中途 KILL 连接 | 回滚完整：头/输入/结果/关联 0 残留；审计无成功记录 |
| T6 死锁注入 | 人为交叉锁序 | 固定锁序下不发生；注入场景验证重试与错误码 |
| T7 未确认版本 | 对 draft 版本发起快照确认 | 409（数据库守卫＋服务校验双拒） |
| T8 提交后重放 | T1 完成后再次同 key 请求 | 返回原快照，不新增行 |
- 测试用真实 MySQL（SQLite 无法覆盖行锁/守卫触发器/生成列），纳入 P4 退出条件；与既有 composer test（SQLite 等价）并行双轨。

## 2. 新增问题 N1–N8

### N1 父项自身采购成本与组件成本的互斥/组合规则
- **当前问题**：旧库 5 条 BOM 全含自引用边（B2），父项自身价与组件价同时计入成本；新契约禁自引用，V1.1 提出 parent_self 行但未定与组件层的关系。
- **实际业务风险**：若 parent_self 与组件并存且都计价，父项采购价被双计（bom42 案例：1264 ETB 自身价＋组件价叠加）；若互斥而业务实际是"外购成品＋现场加工"组合，则漏计。
- **推荐设计**：parent_self 为资源层可选行；**互斥/组合规则显式建模**——版本级字段 cost_mode enum('components_only','self_only','combined')（提案），combined 时业务必须说明各自覆盖范围（如自身价＝外购毛坯，组件＝现场辅材）；默认 components_only。
- **是否需业务决策**：**是**（旧自引用边的真实语义是前提，B2 联动）。
- **优先级**：P0（结构闸门内必须定，否则资源层语义不完整）。
- **验收方法**：三种 cost_mode 各造固定输入算例；双计检测测试（self_only 时组件行禁止计价）；旧 5 条自引用边逐条标注裁决结果。

### N2 快照数据库级不可变机制
- **当前问题**：V1.1 仅约定"只增不改"，无数据库强制；应用账号可 UPDATE/DELETE 历史。
- **实际业务风险**：一次误操作或恶意写入即可篡改已确认报价依据，审计失效。
- **推荐设计**：三重防护——① 冻结表（组件/资源副本、快照输入/结果/关联、确认记录）应用账号仅 INSERT/SELECT 权限（迁移用独立部署账号）；② BEFORE UPDATE/DELETE 守卫触发器 SIGNAL 45000（权限失配时兜底）；③ 头表状态机触发器：bom_versions 仅允许 draft 行的修改/删除，confirmed 行任何写入拒绝；cost_snapshots 出生即不可变。**作废/取代不 UPDATE 历史**：snapshot_voids 追加标记表（snapshot_id PK、原因、操作者、时间）；superseded 为派生状态。
- **是否需业务决策**：否（纯技术机制）；作废权限与流程需业务确认（B18 细则）。
- **优先级**：P0（与快照表同批落地）。
- **验收方法**：用应用账号直接 UPDATE/DELETE 冻结行必须失败；draft→confirmed 后 UPDATE 失败；作废走标记表且快照行字节不变；迁移账号可正常 DDL。

### N3 BOM 与成本确认并发
- **当前问题**：V1.1 无并发设计；旧系统 BOM 新建无查重（双击两条）。
- **实际业务风险**：重复快照、确认期间价格漂移导致"确认的不是看到的"、死锁中断留半个快照。
- **推荐设计**：§1.5 全套（锚点行锁＋报价行锁/版本复核＋幂等键＋输入摘要＋固定锁序＋完整回滚）。
- **是否需业务决策**：部分——"同一版本多快照是否自动取代旧快照"需业务确认；其余纯技术。
- **优先级**：P0（快照确认的前置）。
- **验收方法**：§1.5 T1–T8 双连接测试全过。

### N4 货币精度和舍入
- **当前问题**：V1.1 B10 草案未区分币种最小单位；旧系统 float 计算＋逐项 ROUND 与 PHP 不 round 并存（10 号报告）。
- **实际业务风险**：JPY 等 0 位小数币种按 2 位舍入产生伪精度；中间舍入位置不统一导致预览/快照/报表分位差；float 累积误差。
- **推荐设计**：① 币种目录携带 ISO 4217 minor_units（CNY/ETB/USD=2，JPY=0 等），展示与最终舍入按币种最小单位；② 存储统一 decimal(18,4)，中间量 decimal(18,6)～(18,8)（汇率 18,8）；③ 计算全程定点（bcmath），禁 float；④ 舍入路径唯一且带版本号（快照记录 rounding_spec_version）；⑤ half-up 与 MySQL ROUND 对齐。**具体舍入位置（逐项 vs 汇总后）属公式范畴，待 B4/B10 评审，不预填。**
- **是否需业务决策**：舍入路径与展示精度需确认；币种最小单位是标准事实不需决策。
- **优先级**：P0（公式闸门内，计算服务前置）。
- **验收方法**：bom42 锚点固定对照；JPY 0 位用例；中间精度边界用例；同输入跨路径结果一致性测试。

### N5 BOM 与成本资源清单分层
- **当前问题**：V1.1 统一明细表混淆技术 BOM 与成本配方（§1.2）。
- **实际业务风险**：多态引用无外键→跨项目/跨类型脏数据；技术 BOM 确认被价格缺失阻塞。
- **推荐设计**：方案 B 分表＋类型化可空外键＋CHECK 恰一＋生成列唯一键；技术 BOM≠综合成本配方显式分层。
- **是否需业务决策**：分层本身为技术决策（本轮推荐）；资源类型清单与加工/人工单项多项属 B16。
- **优先级**：P0（结构闸门）。
- **验收方法**：CHECK 违例（两列同填/全空/类型不匹配）被数据库拒绝；跨项目引用被复合 FK 拒绝；重复资源被唯一键拒绝；组件层变更不影响资源层。

### N6 基本单位换算与采购包装换算分层
- **当前问题**：V1.1 A3 只有一张 unit_conversions，把"kg↔g"（量纲换算）与"袋↔kg"（包装换算）混在一起。
- **实际业务风险**：包装规格变更（50kg→25kg/袋）污染全局量纲换算表；同一"袋"在不同材料含量不同，全局换算无法表达；历史系数失真无提示。
- **推荐设计**：**两层分离**——① 基本单位换算 unit_conversions（量纲级，全局共享，如 kg→g=1000，稳定不变）；② 包装规格 item_pack_specs（条目级：材料/辅材/目录项×包装单位×每包装基本单位量，如"水泥-袋-50kg"，随条目维护、带生效版本）。成本计算：采购价（元/袋）→包装换算→基本单位价（元/kg）→量纲换算→使用单位价；两步换算各自留痕进快照输入。
- **是否需业务决策**：包装规格维护责任与历史清洗需确认；分层本身技术决策。
- **优先级**：P1（结构闸门，单位体系）。
- **验收方法**：改包装规格不影响量纲表；缺包装规格时采购单位报价阻断计算；换算链结果与手工算例一致（水泥 725 ETB/袋÷50kg=14.5 ETB/kg）。

### N7 同一材料多次出现在同一版本的规则
- **当前问题**：旧库 bom41 同材料两条边（1.1/1.0，B13）；V1.1 唯一键未覆盖资源层 NULL 问题。
- **实际业务风险**：重复行导致成本双计或"含损耗/不含损耗"版本混淆；无规则时清洗无依据。
- **推荐设计**：组件层**严格唯一**（每子材料每版本一行，对齐当前组成契约）；资源层默认唯一（生成列唯一键）；若业务需要同一资源多行（如不同工序段同名加工），显式引入 line_no 并纳入唯一键——**默认关闭，需业务确认开启**；parent_self 每版本至多一行。历史重复边（1.1/1.0）迁移时逐条裁决保留哪条（B13/B14 联动）。
- **是否需业务决策**：**是**（是否允许多行及语义）。
- **优先级**：P1（结构闸门内定默认规则，例外规则可后置）。
- **验收方法**：重复插入被唯一键拒绝；开启 line_no 的例外路径有独立测试；旧重复边清洗清单业务签收。

### N8 快照输入与计算结果明细关联
- **当前问题**：V1.1 输入与结果无关联，重算/争议核验无法定位"哪个结果用了哪个输入"。
- **实际业务风险**：审计时只能整包重算，无法逐项质证；输入槽位重复或错配无法发现。
- **推荐设计**：input_slot 语义槽位唯一键（§1.3）＋snapshot_result_inputs(result_id,input_id,role) 关联表＋双复合外键（同快照约束）＋UNIQUE(result_id,role)；每个分项结果必须能追溯到价格/汇率/损耗输入，汇总结果追溯到费率/税输入；**完整性校验：结果行的必要 role 缺关联则确认失败**（数据库 NOT NULL 外键＋服务校验）。
- **是否需业务决策**：否（纯技术）。
- **优先级**：P0（与快照表同批）。
- **验收方法**：任一结果可查询到全部输入槽位；删除/错配输入被 FK 拒绝；重算用例逐 role 比对冻结值。

## 3. 综合决策矩阵（A1–A8 ＋ B1–B20 ＋ N1–N8，原始事项全部保留）

> 列义：原结论＝前两轮已登记结论（不删除不替换）；新增风险/影响原设计＝本轮发现；阻塞＝是否阻塞开发及哪个闸门；确认人；确认前允许＝业务确认前可推进的工作（均为评审/草案/测试设计类，不含生产写入与公式实现）。

### 3.1 A1–A8

| # | 原结论（V1.1） | 新增风险/本轮修订 | 影响原设计 | 阻塞 | 确认人 | 确认前允许 |
|---|---|---|---|---|---|---|
| A1 版本管理 | 必须＋业务决策；版本独立于目录 | 版本确认与快照耦合已解耦（§1.1）；superseded 改派生 | 是：状态机与确认流程重绘 | 结构闸门 | 业务方（状态机/派生规则）＋开发评审 | 版本/组件表草案评审、生命周期测试设计 |
| A2 成本快照 | 必须；不可变输入＋结果 | 一版本多快照；input_slot 唯一键；结果关联（N8）；DB 级不可变（N2） | 是：快照族表结构修订 | 结构闸门（快照结构）＋公式闸门（实际确认） | 业务方（作废/取代规则）＋开发 | 快照族 DDL 草案评审、不可变机制验证设计 |
| A3 计量单位 | 必须＋业务决策 | 换算分两层（N6）：量纲 vs 包装 | 是：unit_conversions 拆层＋item_pack_specs | 结构闸门 | 业务方（包装维护责任/历史清洗） | 单位目录/换算草案评审 |
| A4 报价有效性 | 必须＋业务决策 | 确认并发需报价 version 复核（§1.5）；快照引用不受后续状态变化影响 | 部分：扩展字段不变，增加并发语义 | 结构闸门 | 业务方（状态机/审批/降级策略） | 字段草案评审、有效性校验测试设计 |
| A5 税费边界 | 必须＋业务决策 | 快照须逐层记录税基/税率/税额（N8 role 扩展） | 部分：边界结构不变 | 公式闸门 | 业务方（税链/税基口径） | 四层边界结构评审（不填税率） |
| A6 目录所有权 | 必须＋业务决策 | 资源层类型化外键依赖目录实体先定（B16） | 是：资源表外键目标待定 | 结构闸门（资源层） | 业务方（所有权归类） | 组件层先行；资源层草案待 B16 |
| A7 成本完整性 | 必须 | 版本确认**不做**完整性检查（解耦）；完整性只约束快照确认 | 是：检查时点从版本移到快照 | 公式闸门（降级策略） | 业务方（缺项降级策略） | 状态机草案评审 |
| A8 变更影响 | 必须＋可选扩展 | 影响记录需区分"版本结构变更"与"价格输入变更"两类源 | 部分：事件分类细化 | 不阻塞结构；P5 落地 | 业务方（复核流程）＋开发 | 引用索引草案评审 |

### 3.2 B1–B20

| # | 原结论（11/12 号） | 新增风险/本轮修订 | 影响原设计 | 阻塞 | 确认人 | 确认前允许 |
|---|---|---|---|---|---|---|
| B1 系数精度 | 契约暂不变；评估未来需求 | 版本组件副本精度可高于契约（存换算中间值）需与契约区分说明 | 否 | 迁移闸门 | 业务方 | 现有数据无损核验（已完成） |
| B2 自引用边 | 不迁移不删除；独立成本行 | cost_mode 互斥/组合规则（N1）为前置 | 是：parent_self 行语义细化 | 结构闸门（语义）＋迁移闸门（数据） | 业务方 | 三种 cost_mode 算例准备 |
| B3 税链 | 待确认；单税为基线候选 | 快照逐层税留痕结构先行 | 否（结构） | 公式闸门 | 业务方 | 边界结构评审 |
| B4 双重加成 | 待确认 | 舍入路径与加成顺序联动（N4） | 否 | 公式闸门 | 业务方 | 算例已备（11 号） |
| B5 行膨胀 | 技术缺陷原则修复；历史差异单独核对 | 新查询从设计上按 (version,building/scope) 精确匹配，无复用旧视图 | 否 | 不阻塞（P3 实现时修复） | 开发＋业务签收差异清单 | 核对清单编制 |
| B6 取价/缺价 | 待确认 | 确认并发报价复核依赖取价口径定稿 | 否 | 公式闸门 | 业务方 | 有效性字段草案 |
| B7 汇率/本位币 | 待确认 | 汇率行不可变＋选定行冻结（§1.5）；未来日期策略与费率联动 | 否（结构） | 公式闸门 | 业务方 | 汇率表草案评审 |
| B8 孤儿代码 | 已判定排除 | 无 | 否 | 无 | — | — |
| B9 辅材系数语义 | 待确认 | 包装换算分层（N6）提供语义判别工具 | 否 | 迁移闸门 | 业务方 | 语义判别算例 |
| B10 精度规范 | P0；统一规范草案 | 币种最小单位分层（N4）；舍入路径版本号入快照 | 是：规范细化 | 公式闸门 | 开发评审＋业务确认展示精度 | 规范草案评审、锚点测试设计 |
| B11 死自动化 | 不迁移；意图转需求 | 无 | 否 | 无（库存阶段另议） | 业务方 | 需求清单维护 |
| B12 汇总权威 | 单一权威＋流水 | 快照即权威留档；汇总只读投影 | 否 | P5 | 开发 | 投影草案 |
| B13 唯一性/覆盖 | 待确认 | 费率版本链＋生成列唯一键已给结构答案；重复边规则 N7 | 是：结构方案落地 | 结构闸门（规则确认） | 业务方 | 唯一键草案评审 |
| B14 损耗 | 待确认 | 损耗率入快照输入槽位（line:{id}:loss） | 否（结构预留） | 公式闸门 | 业务方 | 算例已备 |
| B15 任务映射 | 待确认；与 BOM 解耦 | 无 | 否 | 无 | 业务方 | — |
| B16 目录形态 | 待确认；三层所有权 | 资源层外键目标依赖目录实体；**组件层不受阻塞可先行** | 是：依赖关系细化 | 结构闸门（资源层） | 业务方 | 组件层先行评审 |
| B17 费率作用域 | 升级 P0 结构阻塞 | NULL 唯一键方案（生成列）＋同日多版本＋整行覆盖草案（§1.4） | 是：结构方案具体化 | 结构闸门（快照费率输入） | 业务方（作用域/继承/区间模型） | DDL 草案评审、继承算例 |
| B18 草稿/快照边界 | 边界定稿 | 解耦后细化：版本确认独立；快照出生即 confirmed；多快照并存/取代待确认 | 是：状态机拆分 | 结构闸门 | 业务方（作废/取代/权限） | 状态机草案评审 |
| B19 调差/折扣 | 待确认；首期不自动调差 | 招标引用版本/快照的衔接方式待此结论 | 否 | P5 衔接 | 业务方 | 衔接草案 |
| B20 权限映射 | 技术评审＋受众待确认 | 新增版本确认/快照确认/作废权限分设 | 是：权限清单扩展 | 结构闸门（权限定义） | 业务方（受众）＋开发 | 权限草案评审 |

### 3.3 N1–N8（本轮新增，详见 §2）

| # | 结论 | 阻塞 | 确认人 | 确认前允许 |
|---|---|---|---|---|
| N1 互斥/组合 | cost_mode 三态草案，默认 components_only | 结构闸门 | 业务方 | 算例准备 |
| N2 DB 不可变 | 权限＋守卫触发器＋追加标记 | 结构闸门（技术，无需业务决策） | 开发评审 | 机制验证设计 |
| N3 确认并发 | §1.5 全套 | 结构闸门（技术） | 开发评审＋业务（取代规则） | 双连接测试设计 |
| N4 货币精度 | 币种最小单位＋定点＋版本化舍入 | 公式闸门 | 开发评审＋业务（展示精度） | 规范草案 |
| N5 分层 | 方案 B | 结构闸门（技术） | 开发评审 | DDL 草案 |
| N6 换算分层 | 量纲/包装两层 | 结构闸门 | 业务方（维护责任） | 草案评审 |
| N7 重复规则 | 默认严格唯一，例外待确认 | 结构闸门 | 业务方 | 唯一键草案 |
| N8 输入结果关联 | slot＋role 关联表 | 结构闸门（技术） | 开发评审 | DDL 草案 |

### 3.4 B17/B18/B10/B16 依赖关系复查（重点）

```
B16 目录形态/所有权 ──→ 资源层表外键目标 ──→ 版本资源层 ──┐
                                                        ├─→ 快照结构（输入槽位含目录价）
B17 作用域/继承/版本链 ──→ cost_settings 结构 ──→ 快照费率输入 ─┤
                                                        │
B10 精度规范 ──→ 结果列精度/舍入版本 ──→ 快照结果族 ────────┤
                                                        ▼
B18 边界细则（作废/取代/权限）──→ 快照状态机制 ──→ 快照确认功能
公式组 B3/B4/B6/B7/B14 ──→ 计算服务 ──→ 快照"实际确认"（结构可先建，确认需公式）
```
- **组件层（技术 BOM 版本）不依赖 B16/B17/B10/公式组**，可最先进入结构评审与实现（只依赖已确认组成契约）。
- B17 与 B16 互不依赖，可并行确认；B10 独立；B18 的状态机细节依赖解耦后的双状态机（本轮已定框架）。
- 公式未定**不阻止**：版本/组件层、单位体系、报价有效性字段、cost_settings 结构、快照族 DDL 评审、并发测试设计。
- 公式未定**阻止**：任何成本数值计算、快照实际确认功能上线。

## 4. 更新后的数据关系图（提案）

```
┌────────────────────────── 共享参考层（跨项目，无项目价格/状态）─────────────────────────┐
│ Suppliers ─ Supplier_Materials ─ Brands [已有]                                        │
│ Processing_Catalog / Mechanical_Catalog / Labor_Task_Catalog [新增·B16 待确认所有权]   │
│ Units（量纲目录）/ Unit_Conversions（量纲换算）[新增·N6]                                │
└──────────────────────────────────┬────────────────────────────────────────────────────┘
                                   │ 引用（受保护删除）
┌──────────────────────────────────▼───────────────────────── 项目数据层 ─────────────────────────────────────┐
│ Materials [已有·契约不动] ─── Material_Compositions [已有·契约不动＝当前技术 BOM]                              │
│   │  unit/送样要求…            （当前组成变化不影响任何历史版本）                                              │
│   ├─ Material_Quotations [已有＋A4 扩展:有效期/状态/含税/计价单位]                                            │
│   ├─ Auxiliary_Materials [已有·项目内] ＋ Item_Pack_Specs [新增·N6 包装规格]                                  │
│   ├─ Project_Cost_Prices [新增·A6 项目成本价]                                                               │
│   │                                                                                                        │
│   └─ Bom_Versions [新增·A1]  status: draft→confirmed；superseded 派生                                       │
│        │   确认记录表 Bom_Version_Confirmations [追加不可变]                                                 │
│        ├── Bom_Version_Components [新增·方案B]  冻结技术 BOM 副本                                            │
│        │     UNIQUE(version,child_material)；复合 FK→Materials；RESTRICT                                    │
│        └── Bom_Version_Resources [新增·方案B]  冻结成本资源                                                  │
│              resource_type＋类型化可空 FK 列(aux/proc/mech/labor/material)                                  │
│              CHECK 恰一；生成列 resource_key；UNIQUE(version,type,resource_key)                             │
│              cost_mode: components_only|self_only|combined [N1 待确认]                                      │
│        │                                                                                                   │
│        │ 1:N（快照必须引用已确认版本）                                                                       │
│        └── Cost_Snapshots [新增·A2]  出生即 confirmed，永不 UPDATE/DELETE                                    │
│              ├── Snapshot_Inputs [input_slot 唯一键；source_id＋冻结值＋币种/单位/税口径]                     │
│              ├── Snapshot_Line_Results [分项结果]                                                           │
│              ├── Snapshot_Result_Inputs [result×input×role 关联；双复合 FK]                                  │
│              ├── Snapshot_Totals [汇总]                                                                    │
│              └── Snapshot_Voids [追加作废标记；voided 派生]                                                  │
│                                                                                                            │
│ Cost_Settings [新增·B17]  building_key 生成列；UNIQUE(project,building_key,setting_version)                  │
│   scope: project|building（整行覆盖草案）；effective_date＋version 决胜；行不可变                             │
│ Exchange_Rates [新增·B7]  不可变历史行；UNIQUE(from,to,effective_date)                                       │
│ Cost_Change_Impacts [新增·A8]  结构变更/价格变更两类源 → 草稿提示/快照偏差记录                                │
│                                                                                                            │
│ Initial_Cost_Estimate_Detail [已有] ──→ 引用 Bom_Versions（数量工作可先于价格）                               │
│ Tender_List [已有] ──可选引用──→ Bom_Versions / Cost_Snapshots（B19 确认后衔接）                             │
└────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
  冻结引用（存 id＋值双份）：Snapshot_Inputs ⇢ Quotations / Exchange_Rates / Cost_Settings / Project_Cost_Prices
  完整性：成本域全 FK RESTRICT（无 CASCADE）；冻结表权限＋守卫触发器；项目内引用一律复合 FK
```

## 5. 修订后的数据库表设计草案（提案，不生成迁移 SQL）

> 通用：项目表含 project_id NOT NULL＋复合 FK；UTC 时间戳；成本域全 FK RESTRICT；冻结表不可变（N2 机制）。精度按 N4/B10 草案，最终待评审。

### 5.1 版本层
- **bom_versions**：version_id PK；project_id；parent_material_id（复合 FK）；version_no（UNIQUE(project,parent,version_no)）；status enum('draft','confirmed')；cost_mode enum('components_only','self_only','combined') 默认 components_only【N1 待确认】；derived_from_version_id NULL；created_by/at、updated_at。superseded 派生不存列。
- **bom_version_confirmations**（追加不可变）：version_id PK/UNIQUE；confirmed_by/at；structure_digest（冻结内容 SHA-256）。存在此记录＝已确认（守卫触发器据此拦截对版本及子表的后续写入）。
- **bom_version_components**：line_id PK；version_id（FK RESTRICT）；project_id；child_material_id（复合 FK (project_id,child_material_id)→Materials RESTRICT）；quantity decimal(18,6)（契约系数 10,2 的冻结镜像，精度余量容纳换算）；unit_snapshot/name_snapshot；UNIQUE(version_id,child_material_id)；CHECK quantity>0。
- **bom_version_resources**：line_id PK；version_id（FK RESTRICT）；project_id；resource_type enum('auxiliary','processing','mechanical','labor','parent_self')；aux_id/proc_id/mech_id/labor_task_id/material_id 均可空，各带真实 FK（辅材复合项目 FK；共享目录单列 FK；parent_self 复合 FK 且＝版本父项，守卫校验）；CHECK 恰一非空且匹配类型；resource_key 生成列 COALESCE(五列)；UNIQUE(version_id,resource_type,resource_key)【N7 例外待确认】；quantity decimal(18,6)；loss_rate decimal(6,4) NULL【B14 确认后启用】；unit_snapshot/name_snapshot。

### 5.2 快照层
- **cost_snapshots**：snapshot_id PK；project_id；version_id（FK RESTRICT＋守卫：必须存在确认记录）；idempotency_key char(36) UNIQUE(project_id,idempotency_key)；formula_version/rounding_spec_version；base_currency char(3)；expected_input_digest char(64)；confirmed_by/at。**无状态列**；出生即不可变。
- **snapshot_inputs**：input_id PK；snapshot_id（复合 FK 携带 project）；input_slot varchar(120)（`line:{id}:price`/`fx:{from}:{to}`/`setting:{id}`/`line:{id}:loss`/`tax:{source}`）；UNIQUE(snapshot_id,input_slot)；source_entity_id；frozen_value decimal(18,8)；frozen_currency/frozen_unit；tax_inclusive/frozen_tax_rate NULL；effective_date NULL。
- **snapshot_line_results**：result_id PK；snapshot_id；version_line_id（指向组件或资源行，多态仅在此处允许——因两层同属一个已冻结版本且由守卫保证存在；或拆两张结果表，评审定）；subtotal/mgmt/profit/tax/comprehensive decimal(18,4)；currency。
- **snapshot_result_inputs**：result_id＋input_id＋role enum('price','fx','loss','mgmt_rate','profit_rate','tax_rate','trans_rate','ins_rate')；UNIQUE(result_id,role)；双复合 FK（同 snapshot_id）。
- **snapshot_totals**：snapshot_id PK；comprehensive_total/final_quote decimal(18,4)；currency；completeness_digest。
- **snapshot_voids**（追加不可变）：snapshot_id PK；reason；voided_by/at。voided 派生。

### 5.3 设置与汇率
- **cost_settings**：setting_id PK；project_id；scope_type enum('project','building')；building_id NULL（复合 FK，scope=building 必填，CHECK）；**building_key 生成列 IFNULL(building_id,0) STORED**；setting_version；UNIQUE(project_id,building_key,setting_version)；effective_date NOT NULL；management_fee_rate/profit_rate/tax_rate/transportation_rate/insurance_rate decimal(6,4)【数值语义待 B3/B4/B7】；created_by/at。行不可变（更正＝新版本）。
- **exchange_rates**：rate_id PK；from_currency/to_currency char(3)；rate decimal(18,8)；effective_date；UNIQUE(from,to,effective_date)；行不可变。

### 5.4 单位与包装（N6）
- **units**：unit_code PK；unit_name；dimension enum（长度/面积/体积/质量/计数/时间）。
- **unit_conversions**：from_unit/to_unit；factor decimal(18,8)；UNIQUE(from,to)；全局量纲级。
- **item_pack_specs**：spec_id PK；project_id（项目内条目）或 catalog 归属（共享条目，随 B16）；item_type＋item_id（类型化 FK，同资源层模式）；pack_unit；pack_size decimal(18,6)（每包装含基本单位量）；base_unit；effective_version；UNIQUE(item,pack_unit,version)。

### 5.5 报价扩展与项目成本价（沿用 V1.1，增加并发语义）
- Material_Quotations 扩展：valid_from/valid_to、status、tax_inclusive、pricing_unit；**version 列既用于编辑并发也用于确认复核（§1.5）**。
- project_cost_prices：项目对目录项定价（币种/计价单位/有效期/版本）；UNIQUE(project,catalog_type,catalog_item,price_version)。

### 5.6 影响与审计
- cost_change_impacts：change_source_type enum('structure','price_input')＋source_id＋affected_kind('draft','snapshot')＋affected_id＋deviation NULL＋status('open','reviewed','dismissed')。
- 审计：版本确认/快照确认/作废/费率新版本/默认报价变更 details 含项目 ID＋前后值。

## 6. 独立流程图（提案）

### 6.1 BOM 版本生命周期
```
创建 draft 版本（从当前组成＋资源清单派生，或从旧版本派生）
   │  draft：可编辑子行、可删除（事务内先删子行，RESTRICT 强制）
   ▼
结构确认（独立动作，不查价格/汇率/费率）
   │  校验：组件/资源行合法（DB 约束）＋cost_mode 语义完整
   │  事务：冻结子行副本 → 追加 confirmations 记录 → status=confirmed
   ▼
confirmed（不可修改/不可删除；DB 守卫＋权限）
   │  当前组成此后如何变化都不影响本版本
   ├─ 同一父材料出现更新 confirmed 版本 → 本版本派生为 superseded（只读保留）
   └─ 可被任意多个成本快照引用（1:N）
```

### 6.2 成本快照确认流程（独立于版本确认）
```
选择已确认版本（draft 版本 → 409 拒绝）
   ▼
草稿实时计算（不落库；B18 边界）
   │  取价（报价有效性 A4）＋单位/包装换算（N6）＋完整性检查（A7）
   │  缺汇率/单位不匹配 → 阻断；缺价/缺费率 → 可试算禁确认
   ▼
发起确认（携带 idempotency_key＋expected_input_digest）
   │  事务开始
   ├─ 幂等键命中 → 直接返回已有快照（重放）
   ├─ 锁版本锚点行 FOR UPDATE → 校验确认记录存在
   ├─ 锁涉及报价行（升序）→ 复核 version/单价 vs 草稿期望
   ├─ 汇率/费率：选定不可变行 id（新增行不影响）
   ├─ 重算输入摘要 vs expected_input_digest → 不一致 409＋差异清单
   ├─ 按公式版本计算（公式闸门通过后才可用）
   ├─ 插入快照族：头→输入(slot)→分项结果→结果输入关联(role)→汇总
   ├─ 完整性校验：必要 role 关联齐全，否则回滚
   └─ 审计 → 提交（任一步失败整体回滚，无半个快照）
   ▼
confirmed 快照（出生即不可变；上游变更只生成影响记录，不改快照）
```

### 6.3 快照留档后流程
```
上游变更（报价编辑/新汇率行/新费率行/当前组成变更）
   ├─ 当前组成变更 → 只影响"新建版本"，历史版本与快照无感
   ├─ 价格输入变更 → 事件＋引用索引
   │     ├─ 草稿：标记输入过期，提示重算
   │     └─ 快照：不改快照；写 cost_change_impacts＋偏差试算
   ▼
复核/争议 → 读 snapshot_inputs 冻结值重现当时输入（不依赖上游现存值）
重算审计 → 快照输入＋指定 formula_version 重算 vs 留档比对
需要更正 → 显式新快照（同版本 1:N）或追加 snapshot_voids 作废标记
          禁止 UPDATE/DELETE 任何快照行（DB 强制）
```

## 7. P0–P7 实施与验收计划（三闸门）

> 闸门定义：**G1 结构闸门**（数据模型/约束/状态机评审通过）；**G2 公式闸门**（B3/B4/B6/B7/B14＋B10 舍入路径确认）；**G3 迁移闸门**（B2/B1/B9/B13 清洗规则确认＋备份/回滚方案审批）。公式未定不阻止 G1 内不依赖公式的结构评审与实现；公式未确认禁止任何成本数值计算上线。

| 阶段 | 内容 | 闸门/前置 | 退出条件（验收） |
|---|---|---|---|
| P0 决策准备 | 决策包整理（本文 §8 清单）；算例/测试设计；无代码 | 无 | 每项决策有负责人与排期；结构/公式/迁移三组分工明确 |
| P1 版本与组件层 | bom_versions/confirmations/components＋守卫机制＋权限；**不依赖 B16/B17/公式** | G1（组件层部分，可先行） | 版本确认不查价格通过；confirmed 行 UPDATE/DELETE 被 DB 拒绝；当前组成变更不影响历史版本；重复/跨项目被约束拒绝；composer test 基线不回退 |
| P2 目录/设置/单位结构 | 资源层（B16 后）＋cost_settings（B17 后）＋exchange_rates 结构＋units/包装（N6）＋报价有效性字段（A4） | G1（B16/B17/A4 结构结论） | NULL 唯一键方案验证（项目级行不可重复）；同日多版本决胜确定；跨项目隔离测试；包装/量纲分层测试 |
| P3 公式与计算 | 计算服务＋精度规范（B10/N4）＋完整性状态机（A7） | **G2** | bom42 锚点对照；缺项分类处置；JPY/边界精度；MySQL 实测（FK/CHECK/生成列/行锁） |
| P4 快照与并发 | 快照族＋input_slot/role 关联＋不可变机制＋幂等/锁/摘要（§1.5） | G1＋G2 | T1–T8 双连接测试全过；确认后上游改价快照字节不变；重算＝留档；半个快照 0 残留 |
| P5 影响与衔接 | A8 事件/引用索引/影响记录；测算按版本取数；招标衔接（B19 结论） | P4＋B19 | 草稿过期提示；快照偏差记录；事件丢失对账；测算/招标权限与项目隔离 |
| P6 数据迁移 | 旧 BOM 映射（B2 逐条裁决/cost_mode）＋清洗（B1/B9/B13）＋历史金额差异核对（B5） | **G3**（备份/回滚审批） | 只进新空库；自引用边逐条有裁决记录；差异清单业务签收；可重复安全或明确拒绝重跑 |
| P7 可选扩展与试运行 | 集采协议价/审批工作流/区间费率模型/多级 BOM（如需）；并行试运行准备 | 逐项业务确认 | 各扩展独立验收；主干回归通过 |

## 8. 尚待业务方决策的清单（汇总，未确认不实现）

**结构组（G1）**
1. B17：费率作用域（项目/楼栋）、整行覆盖 vs 逐字段合并、是否区间模型、未来日期是否生效（与 B7 联动）。
2. B16/A6：加工/机械/人工目录所有权（共享 vs 项目）；单项 vs 多项（推荐统一多行）。
3. N1/B2：cost_mode 互斥/组合规则；旧 5 条自引用边语义。
4. N7：同版本同资源多行例外是否开启。
5. A1/B18：版本派生规则（自动 draft vs 手动）；快照作废权限/流程；多快照是否自动取代。
6. A4：报价状态机与审批；过期/停用降级策略。
7. N6：包装规格维护责任与历史清洗。
8. B20：新增权限（版本确认/快照确认/作废）受众。

**公式组（G2）**
9. B3/A5：税链与四层税基口径。
10. B4：双重加成保留与否；利润基数。
11. B6：取价口径（默认 vs 最新）与缺价策略。
12. B7：成本本位币（ETB vs CNY）；汇率选择/兜底/未来日期。
13. B14：损耗显式化与口径。
14. B10/N4：舍入路径与展示精度评审。

**迁移组（G3）**
15. B1：未来精度需求评估。
16. B9：辅材系数语义（数量 vs 百分比）。
17. B13：旧重复边/全 0 费率裁决。
18. B5：历史虚增金额核对清单签收。
19. B19：调差/折扣是否进新链（tier 语义）。

## 9. 边界声明

- 本轮只读：未修改任何源码/数据库/迁移，未创建迁移 SQL，未执行开发。
- 所有表/字段/触发器/权限方案/流程/阶段计划均为**提案**，标注【待确认】处未代为决策；税率/利润/管理费/损耗/汇率/公式数值一律未填。
- 已确认契约（Material_Compositions、样品、默认报价人工选择、招标首期）零改动；A1–A8/B1–B20 原始事项全部保留于 §3 矩阵。
- 证据基础：第一/二轮已核实的旧库 DDL/视图/触发器/样本与新系统迁移/源码（10/11/12 号文件）；本轮未新增数据库查询。MySQL 8.0.46 支持 CHECK/生成列为第一轮已核实事实。
- 未验证：提案的 MySQL 实测（P1–P4 退出条件）；守卫触发器与权限分离的部署细节（需 DBA 评审）；SQLite 等价测试无法覆盖行锁/触发器/生成列，双轨测试为硬性要求。
# 28_Project_O_工程消项_数据库设计

> 日期：2026-11-22。阶段：Phase1 前置方案终审定点修订。
> 性质：**数据库设计文档（仅设计，未实现）**。本轮不修改源码、不生成迁移脚本、不创建 ORM 模型、不执行数据库结构变更、不开发页面/接口；完成后等待人工终审放行。
> 效力标记：【已确认】＝用户指令或真实库/源码事实；【设计提案】＝方向明确、细节待终审/DDL 审批；【待确认】＝现有源码或库未查到，不得假设已存在。
> 核查基准：本地 dump `project_o (6).sql`（2026-11-21 13:54:48 导出，295657 字节）；现行权威口径见 `25_Project_O_BOM与成本_规则总纲.md`，冲突以其为准。
>
> **实施修订（2026-11-22）**：模块已落地。最终口径为实时联查 BOM，`project_closeout_items.snapshot` 已删除；`title` 仅作可选自定义标签，留空回退实时材料名称；进度表、验收表保留。以下涉及快照的章节保留为原设计过程记录，实际以代码、迁移和本修订为准。

---

## 一、业务定位与 V1.0 范围

### 1.1 业务定义【已确认】

- **消项**：以 BOM 材料为来源的**项目工作完成事项管理**，跟踪"某材料对应的工作是否已完成、谁在负责、何时计划/实际完成、是否通过验收、风险如何"。
- 消项**不是材料数量核销**：本域**不设**核销数量字段、**不做** BOM 累计核销数量计算、**不回写** BOM 材料数量或状态。
- 消项是项目管理侧的独立业务记录；BOM（`Materials` / `Material_Compositions`）是材料业务真实数据源，二者**关联但互不替代**。

### 1.2 V1.0 纳入范围【已确认】

- 消项计划（主表，管计划状态）＋消项事项（明细表，独立管事项状态）。
- 事项来源 V1.0 **仅 BOM 材料**一种；进度记录、验收记录、附件。
- 责任单位、责任人、计划/实际日期、风险等级、父子材料交付范围互斥。

### 1.3 V1.0 明确不做【已确认】

- **不创建**多态来源关联表；**不提前接入** WBS、Issue、RFI；不在事项上预留可跨表悬挂的来源类型字段。
- 不做复杂审批流；返工通过独立变更流程处理（见 §10.6）。
- 不做数量核销、累计核销、扣库存、超用统计。
- 不复制/冻结整套 BOM 成本快照（与成本域口径见 §11.6 说明差异）。

---

## 二、数据库环境与兼容性核查（基于真实 dump）

### 2.1 已核查的环境事实【已确认，来源 `project_o (6).sql` 头部】

| 项 | 实际值 |
|---|---|
| 数据库 | MySQL **8.0.46**-0ubuntu0.24.04.5（InnoDB） |
| 导出工具 | phpMyAdmin 5.2.1deb3；PHP 8.3.26 |
| 库名 | `project_o` |
| 字符集 | utf8mb4 / utf8mb4_unicode_ci |
| 会话时区 | `+00:00`（UTC）；日期/时间字段沿用现有 timestamp/datetime 惯例 |
| SQL_MODE | `NO_AUTO_VALUE_ON_ZERO` |
| 主键惯例 | 普遍 `int` + `AUTO_INCREMENT` |

> 兼容性结论：MySQL 8.0.46 **支持** STORED 生成列、JSON 列、复合唯一索引、CHECK 约束。本方案唯一索引采用双生成列 + 组合唯一索引（§7.2），不依赖"部分唯一索引"（MySQL 8.0 无部分索引语法，早期方案中"支持部分唯一索引"的说法已作废）。

### 2.2 现有库已核查的相关表【已确认】

| 现有表 | 作用 | 与消项域关系 |
|---|---|---|
| `Projects` | 项目主表（project_id, project_name, start_date, end_date, status） | 消项计划/事项归属项目 |
| `Materials` | 材料主数据（material_id, project_id, material_code, material_name, unit, specifications …） | 消项事项唯一 BOM 来源对象 |
| `Material_Compositions` | 唯一 BOM 父子边（composition_id, project_id, parent_material_id, child_material_id, quantity） | 父子归并与防重复统计依据；消项表不复制边 |
| `Users` | 用户（user_id, username, is_active …） | 责任人、操作人关联；**无 full_name/部门字段** |
| `Suppliers` | 供应商（supplier_id, supplier_name, contact_person …） | 责任单位候选载体；**无 project_id 归属列** |
| `audit_logs` | 通用审计（id, user_id, project_id, entity_type, entity_id, action, details, ip_address, user_agent, created_at） | 计划/事项操作历史**复用**，追加写入 |
| `document_number_sequences` | 编号序列（project_id, document_type, last_number） | 消项编号**复用**，新增 document_type |
| `document_issues` 及其 follow_ups / images | 同类事项范式（状态、严重度、附件、编号、位置） | 字段/索引/附件范式参照 |

### 2.3 未查到、标注待确认的项【待确认】

- **ORM 框架及其对生成列、JSON、复合唯一索引的支持**：dump 仅含表结构，不含应用层 ORM 代码；本目录未见源码，需在源码库核查后确认 DDL 落地方式。
- **组织/部门表**：现有表清单中**未发现** Departments/Organizations/Teams 等表；责任单位是否仅用 `Suppliers` 或允许自由文本，待终审。
- **BOM 停用/软删除字段**：`Materials` 现无 is_deleted / status / archived 类字段；后续是否增加停用机制待确认。
- **BOM 多级递归与多父共享组件**：现有 `Material_Compositions` 数据为单层边；多级深度、防环、多父共享业务规则待确认。

---

## 三、实体关系说明

### 3.1 关系总图【设计提案】

```
Projects (1) ──< project_closeout_plans (N) ──< project_closeout_items (N)
                                                        │
                                                        ├──< project_closeout_progress_logs (N)
                                                        ├──< project_closeout_acceptances (N)
                                                        └──< project_closeout_images (N)

Materials (1) ──< project_closeout_items (N)   [BOM 为独立数据源，非消项计划父级业务表]
Material_Compositions ── 实时读取父子归并关系（消项表不复制边）
Users ──< 计划/事项/进度/验收/附件 的操作人与责任人
Suppliers ──< 事项责任单位（可空）
audit_logs ── 追加记录计划关闭/重开/取消/恢复等操作历史
document_number_sequences ── 提供计划/事项编号流水
```

### 3.2 关系要点【已确认】

1. `Projects → Closeout Plans → Closeout Items` 为消项域主链路。
2. `Closeout Items` 通过 `material_id` 引用 `Materials`；**BOM 是独立数据源，不属于消项计划的父级业务表**。
3. 进度记录、验收记录、附件均从属于消项事项；不允许产生孤立记录（§6.3）。
4. 计划主表管理**计划状态**；明细表独立管理**事项状态**，两者状态机分离（§10）。

### 3.3 本次新建/复用/不建一览

| 类别 | 对象 |
|---|---|
| 新建表（5） | `project_closeout_plans`、`project_closeout_items`、`project_closeout_progress_logs`、`project_closeout_acceptances`、`project_closeout_images` |
| 复用现有表 | `Projects`、`Materials`、`Material_Compositions`、`Users`、`Suppliers`、`audit_logs`、`document_number_sequences` |
| 不建表 | 多态来源关联表、独立计划日志表、独立状态历史表、BOM 快照副本表、编号序列新表 |

---

## 四、完整字段定义

> 类型/可空/默认对齐现有 `document_issues` 惯例：主键 `int`；时间 `timestamp` 默认 `CURRENT_TIMESTAMP`，更新时间 `ON UPDATE CURRENT_TIMESTAMP`；枚举 `enum(...)`；金额/数量本域不涉及。
> 标注：【已确认】对齐现有范式；【设计提案】消项域新增字段，待 DDL 评审；【待确认】取值依赖未核查源码/业务决策。

### 4.1 `project_closeout_plans`（消项计划主表）

| 字段 | 类型 | 可空 | 默认 | 说明 |
|---|---|---|---|---|
| `plan_id` | int | 否 | AUTO_INCREMENT | 主键 |
| `project_id` | int | 否 | — | 归属项目，复合项目隔离 |
| `plan_no` | varchar(50) | 否 | — | 计划编号，**项目内唯一**（格式见 §9） |
| `plan_name` | varchar(255) | 否 | — | 计划名称 |
| `description` | text | 是 | NULL | 计划说明 |
| `status` | enum('draft','active','closed') | 否 | 'draft' | 计划状态：草稿 / 进行中 / 已关闭 |
| `planned_start_date` | date | 是 | NULL | 计划开始日期 |
| `planned_end_date` | date | 是 | NULL | 计划完成日期 |
| `closed_at` | datetime | 是 | NULL | 计划关闭时间 |
| `closed_by` | int | 是 | NULL | 关闭操作人（Users.user_id） |
| `reopened_at` | datetime | 是 | NULL | 重新开启时间 |
| `reopened_by` | int | 是 | NULL | 重新开启操作人（Users.user_id） |
| `created_by` | int | 否 | — | 创建人（Users.user_id） |
| `created_at` | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | timestamp | 否 | CURRENT_TIMESTAMP ON UPDATE | 最后更新时间 |

### 4.2 `project_closeout_items`（消项事项明细表）

| 字段 | 类型 | 可空 | 默认 | 说明 |
|---|---|---|---|---|
| `item_id` | int | 否 | AUTO_INCREMENT | 主键 |
| `project_id` | int | 否 | — | 归属项目，复合项目隔离；必须与所属计划一致 |
| `plan_id` | int | 否 | — | 所属消项计划 |
| `item_no` | varchar(50) | 否 | — | 事项编号，**项目内唯一**（格式见 §9） |
| `material_id` | int | 否 | — | 来源 BOM 材料（Materials.material_id）；**创建后禁止普通编辑修改**（§11.5） |
| `coverage_mode` | enum('whole','split') | 否 | 'whole' | 交付范围管控模式：整体管控 / 拆分管控（§12） |
| `coverage_root_id` | int | 是 | NULL | 归并根材料：whole 时为纳入统计的根材料；split 时为拆分归属父级；语义见 §12 |
| `title` | varchar(255) | 否 | — | 事项标题 |
| `description` | text | 是 | NULL | 事项说明 |
| `status` | enum('pending','ongoing','pending_accept','closed','cancelled') | 否 | 'pending' | 事项状态：待处理 / 进行中 / 待验收 / 已消项 / 已取消 |
| `responsible_supplier_id` | int | 是 | NULL | 责任单位（Suppliers.supplier_id）；无组织表，载体待终审【待确认】 |
| `responsible_user_id` | int | 是 | NULL | 责任人（Users.user_id） |
| `planned_start_date` | date | 是 | NULL | 计划开始日期 |
| `planned_end_date` | date | 是 | NULL | 计划完成日期 |
| `submitted_at` | datetime | 是 | NULL | 责任人申报完成时间（进入待验收时写入） |
| `actual_complete_date` | date | 是 | NULL | 实际完成日期；**仅验收通过后确认**，驳回/取消不得回填 |
| `risk_level` | enum('low','medium','high','critical') | 否 | 'medium' | 风险等级（对齐现有 severity 枚举） |
| `cancel_reason` | text | 是 | NULL | 取消原因（取消必填） |
| `cancelled_by` | int | 是 | NULL | 取消操作人（Users.user_id） |
| `cancelled_at` | datetime | 是 | NULL | 取消时间 |
| `snapshot` | json | 否 | — | BOM 来源快照，创建即固化、不可编辑（§11.2） |
| `created_by` | int | 否 | — | 创建人（Users.user_id） |
| `created_at` | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | timestamp | 否 | CURRENT_TIMESTAMP ON UPDATE | 最后更新时间 |
| `eff_project_id` | int | 否 | 生成列 | 有效归属项目：`status<>'cancelled'` 时取 `project_id`，否则 NULL（§7.2） |
| `eff_material_id` | int | 否 | 生成列 | 有效来源材料：`status<>'cancelled'` 时取 `material_id`，否则 NULL（§7.2） |

**快照 `snapshot` JSON 最小结构【设计提案】**（创建时从 `Materials` / `Material_Compositions` 固化，之后不可编辑）：

```json
{
  "material_id": 124,
  "material_code": "M-124",
  "material_name": "钢化玻璃",
  "specifications": "12mm 钢化",
  "unit": "m²",
  "parent_material_ids": [33, 17],
  "bom_version": null
}
```

- `bom_version`：现有 BOM **无版本标识字段**，V1.0 置 NULL；如后续引入 BOM 版本再补【待确认】。
- `parent_material_ids`：创建时点从 `Material_Compositions` 反查的父级集合；仅作追溯展示，不参与实时归并。

### 4.3 `project_closeout_progress_logs`（进度记录表）

| 字段 | 类型 | 可空 | 默认 | 说明 |
|---|---|---|---|---|
| `progress_id` | int | 否 | AUTO_INCREMENT | 主键 |
| `project_id` | int | 否 | — | 归属项目 |
| `plan_id` | int | 否 | — | 所属计划（冗余便于项目隔离与校验） |
| `item_id` | int | 否 | — | 所属消项事项 |
| `progress_type` | enum('note','submit') | 否 | 'note' | 记录类型：普通进展 / 申报完成；submit 时回填事项 `submitted_at` |
| `progress_note` | text | 否 | — | 进展说明 |
| `recorded_by` | int | 否 | — | 记录人（Users.user_id） |
| `recorded_at` | timestamp | 否 | CURRENT_TIMESTAMP | 记录时间 |

### 4.4 `project_closeout_acceptances`（验收记录表）

| 字段 | 类型 | 可空 | 默认 | 说明 |
|---|---|---|---|---|
| `acceptance_id` | int | 否 | AUTO_INCREMENT | 主键 |
| `project_id` | int | 否 | — | 归属项目 |
| `plan_id` | int | 否 | — | 所属计划 |
| `item_id` | int | 否 | — | 所属消项事项 |
| `result` | enum('passed','rejected') | 否 | — | 验收结论：通过 / 驳回 |
| `remark` | text | 是 | NULL | 验收意见 |
| `evidence_reference` | varchar(1000) | 是 | NULL | 验收佐证（对齐 issues.evidence_reference） |
| `accepted_by` | int | 否 | — | 验收人（Users.user_id） |
| `accepted_at` | datetime | 否 | CURRENT_TIMESTAMP | 验收时间 |

> 同一事项可有多条验收记录（驳回后再次验收）；当前有效结论以事项状态 + 最新一条 `accepted_at` 记录为准。

### 4.5 `project_closeout_images`（附件表）

| 字段 | 类型 | 可空 | 默认 | 说明 |
|---|---|---|---|---|
| `image_id` | int | 否 | AUTO_INCREMENT | 主键 |
| `project_id` | int | 否 | — | 归属项目 |
| `item_id` | int | 否 | — | 所属消项事项 |
| `object_key` | varchar(1024) | 否 | — | 存储对象键（对齐 document_issue_images.object_key） |
| `original_name` | varchar(255) | 否 | — | 原始文件名 |
| `content_type` | varchar(100) | 否 | — | MIME 类型 |
| `file_size` | int | 否 | — | 文件大小（字节） |
| `uploaded_by` | int | 否 | — | 上传人（Users.user_id） |
| `created_at` | timestamp | 否 | CURRENT_TIMESTAMP | 上传时间 |

---

## 五、主外键关系

### 5.1 现有库惯例【已确认】

- 现有 dump 全部表**仅含 PRIMARY KEY / UNIQUE KEY / 普通 KEY**，未发现 `FOREIGN KEY ... REFERENCES` 物理外键；项目隔离靠复合唯一键 `(project_id, xxx_id)` + 应用层校验。
- 本域 DDL 是否落地物理外键，需在 DDL 评审时与现有惯例对齐确认【待确认：ORM / DBA 评审】。

### 5.2 建议的引用关系（逻辑外键 + 建议约束级别）

| 子表 | 父表 | 关联列 | 建议约束 | 说明 |
|---|---|---|---|---|
| plans | Projects | project_id | 真实 FK，**RESTRICT** | 项目不可被事项引用后删除；禁止级联删除 |
| items | plans | plan_id, project_id | 复合真实 FK，**RESTRICT** | 计划被事项引用不可删除；禁止级联删除 |
| items | Projects | project_id | 应用层一致性校验 | 事项 project_id 必须等于所属计划 project_id |
| items | Materials | material_id | **不建级联物理 FK**；应用层校验归属与有效性 | BOM 为独立数据源；引用保护见 §11.3 |
| progress_logs | items | item_id, project_id | 复合真实 FK，**RESTRICT** | 禁止孤立进度记录；事项删除受限 |
| acceptances | items | item_id, project_id | 复合真实 FK，**RESTRICT** | 禁止孤立验收记录 |
| images | items | item_id, project_id | 复合真实 FK，**RESTRICT** | 附件随事项归属校验 |
| plans/items/… | Users | created_by / 操作人 | 普通索引 + 应用层校验 | 现有 issues 对 reported_by 亦未建物理 FK |
| items | Suppliers | responsible_supplier_id | 普通索引 + 应用层校验 | 供应商为全局目录，无项目归属列 |

### 5.3 删除规则【已确认】

- **禁止任何 CASCADE 级联删除**。
- 计划、事项、进度、验收、附件之间：父记录被引用时 **RESTRICT**；不允许删除已产生进度/验收的事项作为常规路径。
- 来源追溯字段（`material_id`、`snapshot`）**禁止置空**；BOM 删除/停用不影响历史消项记录（§11）。

---

## 六、唯一性约束

### 6.1 编号唯一性【已确认】

| 约束 | 列 | 规则 |
|---|---|---|
| 计划编号项目内唯一 | `(project_id, plan_no)` | 同一项目内计划编号不重复；跨项目可复用编号段 |
| 事项编号项目内唯一 | `(project_id, item_no)` | 同一项目内事项编号不重复 |

### 6.2 同一项目同一 BOM 仅一条有效事项【设计提案】

- 业务规则：同一项目、同一 BOM 材料，默认只能存在**一条有效**（非 cancelled）消项事项。
- 取消事项保留历史、不释放为"可被新事项直接顶替"的状态；恢复时再做唯一性校验（§10.5）。
- 数据库层通过**双生成列 + 组合唯一索引**强制，不允许仅依赖前端校验（§7.2）。

### 6.3 项目归属一致性【已确认】

- 所有子表 `project_id` NOT NULL；事项/进度/验收/附件的 `project_id` 必须与其所属计划、事项一致，事务内校验。
- 禁止跨项目悬挂记录；禁止产生孤立的进度、验收、附件记录。

---

## 七、索引设计

### 7.1 普通/复合索引（对齐现有 `idx_` / `uq_` 命名惯例）

**`project_closeout_plans`**

| 索引名 | 列 | 类型 | 用途 |
|---|---|---|---|
| `PRIMARY` | plan_id | 主键 | — |
| `uq_closeout_plan_project_no` | (project_id, plan_no) | 唯一 | 计划编号项目内唯一 |
| `idx_closeout_plan_project_status` | (project_id, status) | 普通 | 项目内按计划状态筛选 |

**`project_closeout_items`**

| 索引名 | 列 | 类型 | 用途 |
|---|---|---|---|
| `PRIMARY` | item_id | 主键 | — |
| `uq_closeout_item_project_no` | (project_id, item_no) | 唯一 | 事项编号项目内唯一 |
| `idx_closeout_item_plan` | (plan_id, project_id) | 普通 | 计划下事项列表 |
| `idx_closeout_item_project_status` | (project_id, status) | 普通 | 项目内按事项状态筛选/完成率统计 |
| `idx_closeout_item_material` | (project_id, material_id) | 普通 | 按 BOM 材料反查事项 |
| `uq_closeout_item_active` | (eff_project_id, eff_material_id) | 唯一 | 同项目同 BOM 仅一条有效事项（§7.2） |

**`project_closeout_progress_logs`**

| 索引名 | 列 | 类型 | 用途 |
|---|---|---|---|
| `PRIMARY` | progress_id | 主键 | — |
| `idx_closeout_progress_item` | (item_id, project_id) | 普通 | 事项进度时间线 |

**`project_closeout_acceptances`**

| 索引名 | 列 | 类型 | 用途 |
|---|---|---|---|
| `PRIMARY` | acceptance_id | 主键 | — |
| `idx_closeout_acceptance_item` | (item_id, project_id) | 普通 | 事项验收记录时间线 |

**`project_closeout_images`**

| 索引名 | 列 | 类型 | 用途 |
|---|---|---|---|
| `PRIMARY` | image_id | 主键 | — |
| `idx_closeout_image_item` | (item_id, project_id) | 普通 | 事项附件列表（对齐 document_issue_images） |

### 7.2 生成列 + 组合唯一索引方案【设计提案，MySQL 8.0.46 已支持】

**方案要点（双生成列，替代早期 CONCAT 单列方案）：**

```sql
-- 示例 DDL 片段，仅供评审；本轮不生成迁移、不落库
eff_project_id  INT GENERATED ALWAYS AS
  (CASE WHEN `status` <> 'cancelled' THEN `project_id` END) STORED,
eff_material_id INT GENERATED ALWAYS AS
  (CASE WHEN `status` <> 'cancelled' THEN `material_id` END) STORED,
UNIQUE KEY `uq_closeout_item_active` (`eff_project_id`, `eff_material_id`)
```

- 取消（cancelled）时两列均为 **NULL**；MySQL 唯一索引对 NULL 不去重，因此多条已取消历史事项可共存。
- 非取消状态两列分别取原始 `project_id`、`material_id`，组合唯一即"同项目同材料仅一条有效事项"。
- 两生成列均为 **NOT NULL 语义由表达式保证**（CASE 未命中返回 NULL；仅取消态为 NULL，见上）；组合索引覆盖项目归属与材料来源。
- **并发创建**：同一项目同一材料并发插入两条有效事项时，由唯一索引兜底拒绝后提交，应用层捕获冲突并提示；不在应用层先查后插绕过约束。
- **批量导入**：单事务内执行；编号序列、唯一性校验、快照固化同事务提交；任一行冲突整体回滚（§13）。
- **取消与恢复**：取消 → 生成列置 NULL，历史保留；恢复 → 重新计算生成列，事务内先做来源归属、唯一性、计划状态校验，冲突则拒绝恢复。

> 兼容性备注：现有库 ORM 对 STORED 生成列、JSON、复合唯一索引的映射支持**待确认**【待确认】；若 ORM 不支持生成列，DDL 层仍可由数据库强制，应用层仅读写原始列。

---

## 八、编号规则

### 8.1 编号格式【对齐现有 issues 范式】

- 现有实例：`P1-ISS-001`、`P2-ISS-001`；序列表 `document_number_sequences(project_id, document_type, last_number)`。
- 消项复用该序列表，新增 document_type：
  - 事项：`CLO`，格式 `P{project_id}-CLO-{seq:3位}`，例 `P1-CLO-001`。
  - 计划：`CLO_PLAN`，格式 `P{project_id}-CLO-PLAN-{seq:3位}`，例 `P1-CLO-PLAN-001`。
- 编号在**项目内**唯一；序列递增在同一事务内占用，批量导入不得重号。
- 编号前缀与 document_type 取值最终以 DDL 评审为准【待确认】。

### 8.2 编号与事务保障【已确认】

- 编号流水与事项/计划插入在同一事务；序列行 `(project_id, document_type)` 行内锁占用，避免并发/批量导入重号。
- 事项编号不可为空（与 issues 允许 NULL 的早期数据不同，消项 V1.0 要求编号必填）。

---

## 九、状态流转规则

### 9.1 事项状态定义【已确认】

| 状态 | 含义 | 是否计入完成率分母 | 是否计入分子 |
|---|---|---|---|
| pending | 待处理 | 是 | 否 |
| ongoing | 进行中 | 是 | 否 |
| pending_accept | 待验收 | 是 | 否 |
| closed | 已消项 | 是 | 是 |
| cancelled | 已取消 | 否 | 否 |

### 9.2 事项状态转换矩阵【设计提案】

| 当前状态 | 目标状态 | 触发动作 | 必要条件 / 附带写入 |
|---|---|---|---|
| pending | ongoing | 开始执行 | 计划处于 active；记录操作人 |
| ongoing | pending_accept | 申报完成 | 写 `submitted_at`；生成进度记录 `progress_type='submit'` |
| pending_accept | closed | 验收通过 | 写验收记录 result=passed；回填 `actual_complete_date`；`actual_complete_date` 不得早于 `submitted_at` |
| pending_accept | ongoing | 验收驳回 | 写验收记录 result=rejected；事项回到 ongoing；**不回填** actual_complete_date |
| pending / ongoing / pending_accept | cancelled | 取消事项 | 必填 `cancel_reason`、写 `cancelled_by`、`cancelled_at`；生成列置 NULL |
| cancelled | pending | 恢复事项 | 见 §9.5；恢复后重新进入待处理 |
| closed | — | — | **不可直接取消、不可重复验收、不可回退** |

### 9.3 验收限制【已确认】

- 只有 `pending_accept` 状态可以执行验收。
- 验收通过 → `closed`；验收驳回 → 回到 `ongoing`。
- `closed` 不允许直接取消或重复验收。
- 取消必须记录操作人和原因（`cancelled_by`、`cancelled_at`、`cancel_reason`）。
- 返工：V1.0 不增加复杂审批流；已 closed 事项如需返工，走**独立变更流程**——原事项保留 closed 历史，另建正确事项关联说明（§10.6）。

### 9.4 计划状态转换矩阵【设计提案】

| 当前状态 | 目标状态 | 触发动作 | 规则 |
|---|---|---|---|
| draft | active | 发布计划 | 草稿计划不进入正式项目完成率（§14） |
| active | closed | 关闭计划 | **不自动完成**未完成事项；已关闭计划下未完成事项仍可继续执行和验收；已关闭计划**不能新增**事项 |
| closed | active | 重新开启 | 必须有明确操作记录（audit_logs）；重开后可继续新增/执行事项 |
| draft | closed | 废弃草稿 | 草稿不进完成率；是否允许直接关闭草稿待终审【待确认】 |

### 9.5 取消事项恢复校验【设计提案】

恢复前在同一事务内校验：
1. **来源有效**：`material_id` 对应材料仍存在且归属本项目；
2. **唯一性**：同项目同材料当前不存在另一条有效事项（生成列唯一索引兜底）；
3. **计划状态**：所属计划未被永久废弃；closed 计划下恢复事项需先重开计划，或按终审规则允许在 closed 计划续办既有事项【待确认】；
4. 恢复动作写 audit_logs，记录原取消原因、恢复人、恢复时间。

### 9.6 日期约束【设计提案】

- `planned_start_date` ≤ `planned_end_date`；事项日期不得与所属计划日期范围明显冲突时给提示（不强制阻断，待终审）。
- `submitted_at` 早于/等于验收时间；`actual_complete_date` 仅在验收通过时确认。
- 已关闭计划不自动改写任何事项日期或状态。

---

## 十、BOM 历史数据保护规则

### 10.1 隔离原则【已确认】

- 消项表只读引用 BOM；**不允许修改、回写或同步更新** BOM 原始材料信息（名称、规格、单位、父子关系、数量等）。
- BOM 后续修改不得改变历史消项记录；历史展示以快照为准，实时信息以 BOM 当前值为准，两者分开展示（§10.5）。

### 10.2 来源快照【设计提案】

- 事项创建时从 `Materials` 固化快照：原始 BOM 明细 ID（material_id）、材料编码、名称、规格、单位、父级 ID 集合、BOM 版本标识（现无版本字段，置 NULL）。
- 快照**不可编辑**；不提供快照修改入口。
- BOM 被修改后，历史事项页面同时呈现"创建时点快照"与"当前 BOM 实时值"，差异不自动回写、不覆盖快照。

### 10.3 BOM 删除与停用保护【已确认 + 待确认】

- **已被消项引用的 BOM 材料禁止物理删除**；删除材料前由应用层校验是否存在有效或历史消项事项（对齐现有 `delete_materials` 权限语义：仅可删除未被业务记录引用的材料）。
- 现有 `Materials` **无停用/软删除字段**【待确认】；一旦后续增加停用或软删除：
  - 停用/软删除**不影响**历史消项；事项仍凭 `material_id` + 快照追溯；
  - `material_id` 与快照**禁止置空**；
  - 创建新事项时校验材料项目归属与当前有效性，已停用材料不可被新事项引用。
- 现有 BOM 生命周期实现以源码核查为准；本方案不臆造新的删除/停用机制【待确认】。

### 10.4 外键与历史保留【设计提案】

- 消项事项对 `Materials` 不设级联删除外键；BOM 侧任何生命周期操作不得级联删除消项事项、进度、验收、附件。
- 历史消项记录（含 cancelled）必须永久保留，禁止因 BOM 清理而丢失。

### 10.5 实时信息与快照展示规则【设计提案】

| 展示场景 | 取值来源 |
|---|---|
| 历史事项详情、追溯归档 | `snapshot` 创建时点值 |
| 当前材料名称/规格/单位在 BOM 中的最新状态 | `Materials` 实时读取，仅作参考展示 |
| 父子归并、防重复统计 | 实时读取 `Material_Compositions` 当前边；快照父级集合仅用于历史对照 |

### 10.6 与成本域快照口径的差异说明【已确认】

- 成本域（24/25 号）已确认"资源行去快照、实时引用、不建冻结副本"；本域快照是**工程完成记录的来源留痕**，目的是事后追溯"当时依据哪条材料立项"，不参与成本计算、不复制价格/工程量。两者口径不同，不构成成本域快照体系复活。

---

## 十一、父子材料防重复规则

### 11.1 现有结构事实【已确认】

- 父子关系唯一来源：`Material_Compositions(project_id, parent_material_id, child_material_id, quantity)`；现有数据为单层边。
- 消项表**不修改** BOM 原始父子关系，不新增 `is_statistics` 单布尔字段解决互斥。

### 11.2 管控模式 `coverage_mode`【设计提案】

| 模式 | 含义 | 纳入统计规则 | 排除规则 |
|---|---|---|---|
| `whole` 整体管控 | 以一个父级（整合产品/组合材料）作为独立交付对象整体消项 | 纳入 `coverage_root_id` 指向的根材料；其下子级材料**不单独**作为统计对象 | 子级若另立事项，属重复纳入，唯一性/归并校验拦截 |
| `split` 拆分管控 | 父级拆到子级材料逐项消项 | 纳入被拆分的子材料；父级不重复作为独立统计对象 | 父级若另立 whole 事项，与子级 split 范围重叠时拦截 |

- **同一交付范围只能采用一种管控模式**；whole 与 split 不得在同一归并范围内混用。
- `coverage_root_id`：whole 模式下为纳入统计的根材料；split 模式下为拆分归属父级，用于归并判断与范围冲突检测。

### 11.3 多级父子统计规则【设计提案 + 待确认】

- 统计以**实际纳入的独立交付对象**为准：沿 `Material_Compositions` 边向上归并到根；父级、子级不得同时作为独立统计对象。
- 现有边为单层；多级递归深度、防环规则【待确认】。
- 计算完成率时，whole 根与其 split 子级不重复计数（§14）。

### 11.4 取消释放与恢复冲突【设计提案】

- 事项取消后，其占用的交付范围在恢复校验时重新参与冲突检测：
  - 若期间同范围已存在另一条有效事项，恢复被拒；
  - 取消是否即时释放范围供新事项占用：V1.0 按"取消即释放、新建走唯一性校验；恢复时再查重"处理【待终审确认】。

### 11.5 多父共享组件【待确认】

- 现有边允许同一 child 出现在多个 parent 下（如现状 child 124 同时挂 33、17）。共享组件跨交付范围的归属、是否允许在多个父级事项中分别消项、完成率如何归并，业务规则未确认；V1.0 先按"同一材料仅一条有效事项"的全局唯一性兜底，跨父级拆分规则待终审。

---

## 十二、进度记录、验收记录与附件规则

### 12.1 进度记录【设计提案】

- 进度记录从属于事项，可多次追加；记录类型 `note` / `submit`。
- `submit` 类型记录与事项进入 `pending_accept`、`submitted_at` 写入同事务。
- 进度记录不可删除作为常规路径；如需更正，追加新记录说明（对齐审计追加原则）。

### 12.2 验收记录【设计提案】

- 验收记录从属于事项；驳回可再次验收，历史验收记录全部保留。
- 验收通过时由事务联动事项 `status='closed'`、回填 `actual_complete_date`；驳回联动回 `ongoing`。
- 验收佐证 `evidence_reference` 与附件表配合使用；附件可在事项维度上传。

### 12.3 附件【设计提案】

- 附件范式对齐 `document_issue_images`：对象键 `object_key` 存储，元信息落库；不在事项表内嵌 JSON 附件清单。

---

## 十三、并发与事务保护【设计提案】

- **创建事项**：事务内校验项目归属、材料有效性、唯一性（生成列唯一索引兜底）、计划状态（active 方可新增）；快照同事务固化；编号同事务占用。
- **取消 / 恢复 / 状态流转**：事务内行锁事项记录；先校验当前状态与目标状态合法性，再更新状态与关联时间/人员；audit_logs 同事务追加。
- **批量导入**：单事务；任一行编号冲突、唯一性冲突、归属校验失败则整体回滚；不允许部分成功留下半批事项。
- **进度/验收写入**：先锁事项行校验状态，再写子记录；禁止向已 closed 事项追加验收以外的执行进度（细节待终审）。
- 乐观锁：事项可参考现有组价并发方向，在 DDL 评审时评估是否增加 `version` 列；V1.0 不强制【待确认】。

---

## 十四、项目完成率计算规则

### 14.1 事项口径【已确认】

- 分子：`closed` 事项数。
- 分母：`pending + ongoing + pending_accept + closed` 事项数；`cancelled` **不计入**分子和分母。

### 14.2 计划级完成率【设计提案】

- 仅统计该计划下有效事项（排除 cancelled）。
- 草稿（draft）计划**不进入**正式项目完成率；active / closed 计划纳入。
- 分母为 0（无有效统计事项）时，完成率显示 **`--`**，不显示 0%。
- 已关闭计划下未完成事项仍计入该计划分母；其能否在项目级口径中继续统计，待终审【待确认】。

### 14.3 项目级完成率【设计提案】

- 汇总本项目所有纳入范围计划的有效事项：项目完成率 = 项目 closed 事项数 / 项目有效事项数。
- 父子材料以实际纳入的独立交付对象为准，根与子级不重复计数（§11）。
- 无有效统计事项时显示 **`--`**。

### 14.4 统计口径示例（规则说明，非实现数值）

| 事项状态 | 计入分母 | 计入分子 |
|---|---|---|
| pending | 是 | 否 |
| ongoing | 是 | 否 |
| pending_accept | 是 | 否 |
| closed | 是 | 是 |
| cancelled | 否 | 否 |

---

## 十五、审计与操作日志规则

### 15.1 复用现有 audit_logs【已确认】

- 计划关闭、重新开启、事项取消、恢复、来源更正（取消 + 新建）等操作历史**复用** `audit_logs`：
  - `entity_type`：`closeout_plan` / `closeout_item`；
  - `entity_id`：plan_id / item_id；
  - `action`：如 `close_plan`、`reopen_plan`、`cancel_item`、`restore_item`；
  - `details`：JSON 文本记录前后状态、原因、操作人上下文；
  - `user_id` / `project_id` / `ip_address` / `user_agent` / `created_at` 沿用现有列。
- **不新建**独立计划日志表、状态历史表；审计记录**只追加**，不允许 JSON 覆盖式保存。

### 15.2 审计最小集【设计提案】

操作对象（计划/事项）、动作、前后状态、来源材料前后值、取消/驳回原因、验收结论、操作人、时间。

---

## 十六、未来扩展策略

| 方向 | V1.0 处理 | 后置扩展说明 |
|---|---|---|
| WBS / Issue / RFI 关联 | 不接入，不建多态来源表 | 后续需要时，按领域建类型化关联表（对齐成本域三类型化 FK 思路），不在事项表预留通用 source_type/source_id |
| 多来源消项 | 仅 BOM 材料 | 扩展时新增来源类型与对应校验，沿用快照与唯一性模式 |
| 审批流 | 不做 | 验收/返工审批后置；V1.0 验收由验收人直接结论 |
| 组织/部门 | 不假设存在 | 待组织表落地后，责任单位/责任归属可从自由文本/供应商关联迁移到组织关联 |
| BOM 版本 | 不建版本链 | 若后续引入 BOM 版本标识，快照 `bom_version` 回填；现有版本字段为 NULL |
| 复杂父子/共享组件规则 | 全局唯一兜底 | 多级递归、多父共享跨范围归并规则确认后补充到 §11 |

---

## 十七、与现有源码及数据库的差异分析

### 17.1 新增（消项域，5 张表）

| 新表 | 对应现有范式参照 |
|---|---|
| project_closeout_plans | document_issues 主表的状态/编号/人员/时间列范式 |
| project_closeout_items | document_issues 主表 + 双生成列唯一索引 + 快照 JSON |
| project_closeout_progress_logs | document_issue_follow_ups 跟进流水范式 |
| project_closeout_acceptances | document_issues 验收/关闭结论列范式（独立成表，支持多次验收） |
| project_closeout_images | document_issue_images 附件范式 |

### 17.2 复用现有（不新建）

- 项目隔离：`Projects.project_id`；用户：`Users.user_id`；责任单位候选：`Suppliers.supplier_id`。
- BOM 只读引用：`Materials.material_id`；父子归并实时读 `Material_Compositions`。
- 审计：`audit_logs`；编号序列：`document_number_sequences`（新增 type `CLO` / `CLO_PLAN`）。
- 风险等级枚举对齐 `document_issues.severity`；附件列对齐 `document_issue_images`。

### 17.3 与现有库的结构差异点

| 项 | 现有库现状 | 消项方案 |
|---|---|---|
| 物理外键 | 全部表无 `FOREIGN KEY` 约束，靠复合唯一键 + 应用层 | 建议 RESTRICT 真实 FK；是否落地待 DDL/ORM 评审，未落地前降级为普通 KEY + 应用层校验 |
| 生成列 | 现有表未使用 | 新增双 STORED 生成列支撑有效事项唯一索引（MySQL 8.0.46 支持） |
| JSON 列 | 现有 `details/transition_data` 多为 text 存 JSON 串 | 事项快照用原生 JSON 列；ORM 兼容性待确认 |
| 软删除/停用 | Materials 无对应字段 | 方案不依赖 BOM 软删；删除保护由应用层 + 快照追溯 |
| 组织归属 | 无部门/组织表 | 责任单位暂挂 Suppliers 或文本，待终审 |

### 17.4 明确不沿用的旧设计

- 数量核销字段（write_off_qty）、BOM 累计核销数量计算：**删除**，不复活。
- 旧 PHP 工程表结构（Internal_BOM / BOM_Components / Buildings 旧字段等）：**不作为依据**。
- 在 BOM 表回写已消项状态/数量：禁止。
- 多态来源表、`is_statistics` 单布尔父子互斥、CONCAT 单列生成列、JSON 覆盖式日志：不采用。

---

## 十八、待确认事项清单（未确认前不实现）

1. **ORM 及框架**：实际 ORM 对 STORED 生成列、JSON 列、复合唯一索引的支持与映射方式（源码未在本目录，待查）。
2. **责任单位载体**：关联 `Suppliers`、自由文本，还是后续新建组织/承包单位表；现有无组织部门表。
3. **BOM 停用/软删除机制**：Materials 是否计划增加状态/归档字段；删除保护校验接入点。
4. **父子结构深度**：BOM 是否存在多级递归、是否需要防环校验；多父共享组件跨范围消项与完成率归并规则。
5. **来源绑定对象**：消项来源绑定 `Materials.material_id`（推荐）还是 `Material_Compositions` 边 / 成本头表，终审确认。
6. **closed 计划续办口径**：已关闭计划下未完成事项是否继续计入项目完成率；closed 计划恢复事项是否必须先重开计划。
7. **编号前缀 / document_type**：`CLO`、`CLO_PLAN` 取值与编号位数。
8. **物理外键落地**：新表是否按建议建 RESTRICT 真实 FK，还是沿用现有纯普通 KEY 惯例。
9. **乐观锁 version 列**：事项并发编辑是否需要。
10. **草稿计划关闭**：draft 是否允许直接关闭、关闭后事项如何处置。

---

## 十九、修订记录（本轮定点修订）

### 19.1 修改章节与原因对照

| # | 修改章节 | 修改原因（终审任务书） | 修改内容 | 对现有数据库兼容性影响 |
|---|---|---|---|---|
| 1 | §4.2、§7.2 唯一索引 | 早期"MySQL 部分唯一索引"说法错误；须按真实库能力设计可靠约束 | 核实 MySQL 8.0.46；改双生成列 `eff_project_id`/`eff_material_id` + 组合唯一索引；取消态置 NULL；覆盖并发创建、批量导入、取消恢复 | 依赖 STORED 生成列（8.0 支持）；ORM 映射待确认；不兼容 5.7 生成列限制场景 |
| 2 | §10 BOM 快照 | 须固化来源快照、区分实时与历史 | 新增 `snapshot` JSON（material_id/code/name/spec/unit/父级集合/bom_version）；不可编辑；BOM 修改不影响快照；停用/软删靠快照追溯 | 新增 JSON 列；与成本域去快照口径显式区分 |
| 3 | §11 父子互斥 | 不得用 is_statistics 单布尔；须明确整体/拆分管控 | 新增 `coverage_mode`（whole/split）、`coverage_root_id`；定义纳入/排除规则、多级归并、取消释放与恢复冲突、多父共享待确认 | 不改动 Material_Compositions 原始边；新增两列 |
| 4 | §5 外键与删除 | 区分物理 FK 与业务关联；禁级联；禁孤立记录、禁破坏历史 | 建议 RESTRICT 复合 FK；BOM 关联不级联；进度/验收/附件从属于事项；材料引用禁物理删除；快照与 material_id 不置空 | 现有库无物理 FK，落地方式待评审；降级方案为普通 KEY + 应用层校验 |
| 5 | §8 编号唯一性 | 计划/事项编号项目内唯一；批量导入事务保障 | `uq(project_id, plan_no)`、`uq(project_id, item_no)`；复用 number_sequences；编号同事务占用 | 对齐现有 issues 编号范式；新增两个 document_type |
| 6 | §9.6、§12.2 日期与关闭 | actual_complete_date 仅验收通过确认；新增 submitted_at；关闭计划不自动完成、不可新增 | 事项增加 `submitted_at`；`actual_complete_date` 仅 passed 回填；计划关闭规则、重开规则与操作记录明确 | 新增两个日期/时间列；无破坏性变更 |
| 7 | §14 完成率 | 明确分子分母；草稿排除；无有效事项显示 `--` | 状态口径表；计划级/项目级规则；cancelled 不计；分母 0 显示 `--`；父子不重复计数 | 纯查询口径，不涉及表结构变更 |
| 8 | §10.5 来源不可改 | bom_item_id（material_id）创建后禁普通编辑；来源错误走取消留史 + 新建 | 明确 `material_id` 创建后不可普通编辑；更正路径为取消原事项 + 新建正确事项 | 应用层编辑约束；数据库层不另加只读列限制 |
| 9 | §3 实体关系图 | 修正 Project → Plans → Items；BOM 独立数据源 | ER 图重绘；BOM 不作为计划父级业务表 | 无 |
| 10 | §15 计划操作日志 | 评估可复用审计；追加记录不覆盖 | 复用 `audit_logs`，不建 plan_logs；details 追加 JSON；记录关闭/重开/取消/恢复 | 不新增日志表；沿用现有 audit_logs 列 |
| 11 | §13 并发保护 | 创建/删除需事务与并发保护 | 事项创建、取消、恢复、批量导入事务与行锁/唯一索引兜底规则 | 应用层事务要求；数据库唯一索引强制 |
| 12 | §2、§17 兼容核查 | 须核查真实 MySQL 版本、BOM 结构、删除机制、组织表、ORM 支持 | 逐条标注【已确认】/【待确认】；未查到的不假设 | 见 §17.3 差异表 |

### 19.2 本轮未决与边界

- 全部表结构、约束、索引均为**设计提案**，未建表、未生成迁移、未创建 ORM 模型、未改源码、未执行数据库操作。
- 待终审放行后，方可进入 DDL 单独审批 → 专用测试库实测 → 开发的后续闸门（对齐 25 号 §十二流程）。

---

> 边界声明：本文档只做数据库设计与规则梳理；【待确认】项未确认前不实现；不代为决定业务规则；未实现的机制不描述为已实现。完成后停止，等待项目负责人终审。

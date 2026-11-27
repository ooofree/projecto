# 28\_Project\_O\_工程消项\_数据库设计

> 日期：2026-11-22。阶段：Phase1 前置方案终审定点修订。
> 性质：
>
> **数据库设计文档（仅设计，未实现）**
>
> 。本轮不修改源码、不生成迁移脚本、不创建 ORM 模型、不执行数据库结构变更、不开发页面 / 接口；完成后等待人工终审放行。
> 效力标记：【已确认】＝用户指令或真实库 / 源码事实；【设计提案】＝方向明确、细节待终审 / DDL 审批；【待确认】＝现有源码或库未查到，不得假设已存在。
> 核查基准：本地 dump 
>
> `project_o (6).sql`
>
> （2026-11-21 13:54:48 导出，295657 字节）；现行权威口径见 
>
> `25_Project_O_BOM与成本_规则总纲.md`
>
> ，冲突以其为准。
> **实施修订（2026-11-22）**
>
> ：模块已落地。最终口径为实时联查 BOM，
>
> `project_closeout_items.snapshot`
>
>  已删除；
>
> `title`
>
>  仅作可选自定义标签，留空回退实时材料名称；进度表、验收表保留。以下涉及快照的章节保留为原设计过程记录，实际以代码、迁移和本修订为准。
> **V2.0 修订生效（2026-11-27）**
>
> ：消项主体由 "BOM 材料 + 单条事项" 升级为 "
>
> **父材料事项 + 空间子项**
>
> "。一条父材料仍只对应一条有效事项（
>
> `(project_id, material_id)`
>
>  唯一约束不变）；完成状态、进度百分比、验收动作全部下沉到空间子项，子项空间与基线量直接取自材料测算明细 
>
> `Initial_Cost_Estimate_Detail`
>
> 。
>
> **冲突时以 § 二十 为准**
>
> ；§1.3 数量口径、§9 状态机、§12.2 验收、§14 完成率按 §20.2 对照表取代，§4.2/§6/§7 的既有字段与索引继续有效，仅作增量。
> **V2.1 来源口径纠正（2026-11-27）**
>
> ：**父材料是消项唯一来源**；BOM 不是来源、消项不存 BOM 快照，BOM 仅在预测页按现值实时读取。全文一切"BOM 来源 / BOM 快照为准"的表述作废，以 §10 与 §20.16 为准。



***

## 一、业务定位与 V1.0 范围

### 1.1 业务定义【已确认】



* **消项**：以**父材料**为唯一来源的**项目工作完成事项管理**，跟踪 "某父材料对应的工作在哪些空间、做到什么程度、谁在负责、何时计划 / 实际完成、是否通过验收、风险如何"。

* 消项**不是材料数量核销**：本域**不设**核销数量字段、**不做** BOM 累计核销数量计算、**不回写** BOM 材料数量或状态。

* 消项是项目管理侧的独立业务记录；父材料（`Materials` 中被 `Material_Compositions` 指向为父级的那个 `material_id`）是消项锚点，材料测算明细（`Initial_Cost_Estimate_Detail`）提供空间与量，BOM（成本侧）仅提供钱与人。四者**关联但互不替代**。

* **BOM 不是消项来源，消项不存 BOM 快照**：BOM 现值只在预测时被实时读取；BOM 变更不触发消项同步，也不改变消项历史。

### 1.2 V1.0 纳入范围【已确认，V2.1 修订来源口径】



* 消项计划（主表，管计划状态）＋消项事项（明细表，独立管事项状态）。

* 事项来源 **仅父材料**一种（V2.1 纠正：原稿"仅 BOM 材料"作废）；位置与数量来自测算明细；进度记录、验收记录、附件。

* 责任单位、责任人、计划 / 实际日期、风险等级、父子材料交付范围互斥。

### 1.3 V1.0 明确不做【已确认】



* **不创建**多态来源关联表；**不提前接入** WBS、Issue、RFI；不在事项上预留可跨表悬挂的来源类型字段。

* 不做复杂审批流；返工通过独立变更流程处理（见 §10.6）。

* 不做数量核销、累计核销、扣库存、超用统计。

* 不复制 / 冻结整套 BOM 成本数据（消项侧无任何 BOM 副本；预测读最新值，见 §二十）。



***

## 二、数据库环境与兼容性核查（基于真实 dump）

### 2.1 已核查的环境事实【已确认，来源 `project_o (6).sql` 头部】



| 项         | 实际值                                               |
| --------- | ------------------------------------------------- |
| 数据库       | MySQL **8.0.46**-0ubuntu0.24.04.5（InnoDB）         |
| 导出工具      | phpMyAdmin 5.2.1deb3；PHP 8.3.26                   |
| 库名        | `project_o`                                       |
| 字符集       | utf8mb4 / utf8mb4\_unicode\_ci                    |
| 会话时区      | `+00:00`（UTC）；日期 / 时间字段沿用现有 timestamp/datetime 惯例 |
| SQL\_MODE | `NO_AUTO_VALUE_ON_ZERO`                           |
| 主键惯例      | 普遍 `int` + `AUTO_INCREMENT`                       |

> 兼容性结论：MySQL 8.0.46 
>
> **支持**
>
>  STORED 生成列、JSON 列、复合唯一索引、CHECK 约束。本方案唯一索引采用双生成列 + 组合唯一索引（§7.2），不依赖 "部分唯一索引"（MySQL 8.0 无部分索引语法，早期方案中 "支持部分唯一索引" 的说法已作废）。

### 2.2 现有库已核查的相关表【已确认】



| 现有表                                      | 作用                                                                                                                | 与消项域关系                            |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| `Projects`                               | 项目主表（project\_id, project\_name, start\_date, end\_date, status）                                                  | 消项计划 / 事项归属项目                     |
| `Materials`                              | 材料主数据（material\_id, project\_id, material\_code, material\_name, unit, specifications …）                          | 父材料引用对象（**父材料是消项唯一来源**，BOM 不是来源） |
| `Material_Compositions`                  | 唯一 BOM 父子边（composition\_id, project\_id, parent\_material\_id, child\_material\_id, quantity）                     | 判定"谁是父材料"的依据；消项表不复制边 |
| `Initial_Cost_Estimate_Detail`           | 材料测算明细（estimate\_detail\_id, building\_id, floor\_id, room\_id, material\_id, estimated\_quantity …）              | **提供空间位置与工程量**，据此生成子项；无 project\_id，经 `Buildings.project_id` 隔离 |
| `Users`                                  | 用户（user\_id, username, is\_active …）                                                                              | 责任人、操作人关联；**无 full\_name / 部门字段** |
| `Suppliers`                              | 供应商（supplier\_id, supplier\_name, contact\_person …）                                                              | 责任单位候选载体；**无 project\_id 归属列**    |
| `audit_logs`                             | 通用审计（id, user\_id, project\_id, entity\_type, entity\_id, action, details, ip\_address, user\_agent, created\_at） | 计划 / 事项操作历史**复用**，追加写入            |
| `document_number_sequences`              | 编号序列（project\_id, document\_type, last\_number）                                                                   | 消项编号**复用**，新增 document\_type      |
| `document_issues` 及其 follow\_ups /images | 同类事项范式（状态、严重度、附件、编号、位置）                                                                                           | 字段 / 索引 / 附件范式参照                  |

### 2.3 未查到、标注待确认的项【待确认】



* **ORM 框架及其对生成列、JSON、复合唯一索引的支持**：dump 仅含表结构，不含应用层 ORM 代码；本目录未见源码，需在源码库核查后确认 DDL 落地方式。

* **组织 / 部门表**：现有表清单中**未发现** Departments/Organizations/Teams 等表；责任单位是否仅用 `Suppliers` 或允许自由文本，待终审。

* **BOM 停用 / 软删除字段**：`Materials` 现无 is\_deleted /status/archived 类字段；后续是否增加停用机制待确认。

* **BOM 多级递归与多父共享组件**：现有 `Material_Compositions` 数据为单层边；多级深度、防环、多父共享业务规则待确认。



***

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

### 3.3 本次新建 / 复用 / 不建一览



| 类别     | 对象                                                                                                                                          |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| 新建表（5） | `project_closeout_plans`、`project_closeout_items`、`project_closeout_progress_logs`、`project_closeout_acceptances`、`project_closeout_images` |
| 复用现有表  | `Projects`、`Materials`、`Material_Compositions`、`Users`、`Suppliers`、`audit_logs`、`document_number_sequences`                                 |
| 不建表    | 多态来源关联表、独立计划日志表、独立状态历史表、BOM 快照副本表、编号序列新表                                                                                                    |



***

## 四、完整字段定义

> 类型 / 可空 / 默认对齐现有 
>
> `document_issues`
>
>  惯例：主键 
>
> `int`
>
> ；时间 
>
> `timestamp`
>
>  默认 
>
> `CURRENT_TIMESTAMP`
>
> ，更新时间 
>
> `ON UPDATE CURRENT_TIMESTAMP`
>
> ；枚举 
>
> `enum(...)`
>
> ；金额 / 数量本域不涉及。
> 标注：【已确认】对齐现有范式；【设计提案】消项域新增字段，待 DDL 评审；【待确认】取值依赖未核查源码 / 业务决策。

### 4.1 `project_closeout_plans`（消项计划主表）



| 字段                   | 类型                              | 可空 | 默认                           | 说明                      |
| -------------------- | ------------------------------- | -- | ---------------------------- | ----------------------- |
| `plan_id`            | int                             | 否  | AUTO\_INCREMENT              | 主键                      |
| `project_id`         | int                             | 否  | —                            | 归属项目，复合项目隔离             |
| `plan_no`            | varchar(50)                     | 否  | —                            | 计划编号，**项目内唯一**（格式见 §9）  |
| `plan_name`          | varchar(255)                    | 否  | —                            | 计划名称                    |
| `description`        | text                            | 是  | NULL                         | 计划说明                    |
| `status`             | enum('draft','active','closed') | 否  | 'draft'                      | 计划状态：草稿 / 进行中 / 已关闭     |
| `planned_start_date` | date                            | 是  | NULL                         | 计划开始日期                  |
| `planned_end_date`   | date                            | 是  | NULL                         | 计划完成日期                  |
| `closed_at`          | datetime                        | 是  | NULL                         | 计划关闭时间                  |
| `closed_by`          | int                             | 是  | NULL                         | 关闭操作人（Users.user\_id）   |
| `reopened_at`        | datetime                        | 是  | NULL                         | 重新开启时间                  |
| `reopened_by`        | int                             | 是  | NULL                         | 重新开启操作人（Users.user\_id） |
| `created_by`         | int                             | 否  | —                            | 创建人（Users.user\_id）     |
| `created_at`         | timestamp                       | 否  | CURRENT\_TIMESTAMP           | 创建时间                    |
| `updated_at`         | timestamp                       | 否  | CURRENT\_TIMESTAMP ON UPDATE | 最后更新时间                  |

### 4.2 `project_closeout_items`（消项事项明细表）



| 字段                        | 类型                                                               | 可空 | 默认                           | 说明                                                          |
| ------------------------- | ---------------------------------------------------------------- | -- | ---------------------------- | ----------------------------------------------------------- |
| `item_id`                 | int                                                              | 否  | AUTO\_INCREMENT              | 主键                                                          |
| `project_id`              | int                                                              | 否  | —                            | 归属项目，复合项目隔离；必须与所属计划一致                                       |
| `plan_id`                 | int                                                              | 否  | —                            | 所属消项计划                                                      |
| `item_no`                 | varchar(50)                                                      | 否  | —                            | 事项编号，**项目内唯一**（格式见 §9）                                      |
| `material_id`             | int                                                              | 否  | —                            | 父材料引用（`Materials.material_id`，须为 `Material_Compositions` 中的父级）；**创建后禁止普通编辑修改**（§11.5）    |
| `coverage_mode`           | enum('whole','split')                                            | 否  | 'whole'                      | 交付范围管控模式：整体管控 / 拆分管控（§12）                                   |
| `coverage_root_id`        | int                                                              | 是  | NULL                         | 归并根材料：whole 时为纳入统计的根材料；split 时为拆分归属父级；语义见 §12               |
| `title`                   | varchar(255)                                                     | 否  | —                            | 事项标题                                                        |
| `description`             | text                                                             | 是  | NULL                         | 事项说明                                                        |
| `status`                  | enum('pending','ongoing','pending\_accept','closed','cancelled') | 否  | 'pending'                    | 事项状态：待处理 / 进行中 / 待验收 / 已消项 / 已取消                            |
| `responsible_supplier_id` | int                                                              | 是  | NULL                         | 责任单位（Suppliers.supplier\_id）；无组织表，载体待终审【待确认】                |
| `responsible_user_id`     | int                                                              | 是  | NULL                         | 责任人（Users.user\_id）                                         |
| `planned_start_date`      | date                                                             | 是  | NULL                         | 计划开始日期                                                      |
| `planned_end_date`        | date                                                             | 是  | NULL                         | 计划完成日期                                                      |
| `submitted_at`            | datetime                                                         | 是  | NULL                         | 责任人申报完成时间（进入待验收时写入）                                         |
| `actual_complete_date`    | date                                                             | 是  | NULL                         | 实际完成日期；**仅验收通过后确认**，驳回 / 取消不得回填                             |
| `risk_level`              | enum('low','medium','high','critical')                           | 否  | 'medium'                     | 风险等级（对齐现有 severity 枚举）                                      |
| `cancel_reason`           | text                                                             | 是  | NULL                         | 取消原因（取消必填）                                                  |
| `cancelled_by`            | int                                                              | 是  | NULL                         | 取消操作人（Users.user\_id）                                       |
| `cancelled_at`            | datetime                                                         | 是  | NULL                         | 取消时间                                                        |
| ~~`snapshot`~~             | ~~json~~                                                         | —   | ~~已删除~~                      | **V1.1 已删除，V2.1 明确不恢复**；来源追溯靠父材料 + 子项基线量（§10）              |
| `created_by`              | int                                                              | 否  | —                            | 创建人（Users.user\_id）                                         |
| `created_at`              | timestamp                                                        | 否  | CURRENT\_TIMESTAMP           | 创建时间                                                        |
| `updated_at`              | timestamp                                                        | 否  | CURRENT\_TIMESTAMP ON UPDATE | 最后更新时间                                                      |
| `eff_project_id`          | int                                                              | 否  | 生成列                          | 有效归属项目：`status<>'cancelled'` 时取 `project_id`，否则 NULL（§7.2）  |
| `eff_material_id`         | int                                                              | 否  | 生成列                          | 有效来源材料：`status<>'cancelled'` 时取 `material_id`，否则 NULL（§7.2） |

**快照&#x20;**`snapshot`**&#x20;JSON 最小结构【设计提案】**（创建时从 `Materials` / `Material_Compositions` 固化，之后不可编辑）：



```
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



* `bom_version`：现有 BOM **无版本标识字段**，V1.0 置 NULL；如后续引入 BOM 版本再补【待确认】。

* `parent_material_ids`：创建时点从 `Material_Compositions` 反查的父级集合；仅作追溯展示，不参与实时归并。

### 4.3 `project_closeout_progress_logs`（进度记录表）



| 字段              | 类型                    | 可空 | 默认                 | 说明                                           |
| --------------- | --------------------- | -- | ------------------ | -------------------------------------------- |
| `progress_id`   | int                   | 否  | AUTO\_INCREMENT    | 主键                                           |
| `project_id`    | int                   | 否  | —                  | 归属项目                                         |
| `plan_id`       | int                   | 否  | —                  | 所属计划（冗余便于项目隔离与校验）                            |
| `item_id`       | int                   | 否  | —                  | 所属消项事项                                       |
| `progress_type` | enum('note','submit') | 否  | 'note'             | 记录类型：普通进展 / 申报完成；submit 时回填事项 `submitted_at` |
| `progress_note` | text                  | 否  | —                  | 进展说明                                         |
| `recorded_by`   | int                   | 否  | —                  | 记录人（Users.user\_id）                          |
| `recorded_at`   | timestamp             | 否  | CURRENT\_TIMESTAMP | 记录时间                                         |

### 4.4 `project_closeout_acceptances`（验收记录表）



| 字段                   | 类型                        | 可空 | 默认                 | 说明                                  |
| -------------------- | ------------------------- | -- | ------------------ | ----------------------------------- |
| `acceptance_id`      | int                       | 否  | AUTO\_INCREMENT    | 主键                                  |
| `project_id`         | int                       | 否  | —                  | 归属项目                                |
| `plan_id`            | int                       | 否  | —                  | 所属计划                                |
| `item_id`            | int                       | 否  | —                  | 所属消项事项                              |
| `result`             | enum('passed','rejected') | 否  | —                  | 验收结论：通过 / 驳回                        |
| `remark`             | text                      | 是  | NULL               | 验收意见                                |
| `evidence_reference` | varchar(1000)             | 是  | NULL               | 验收佐证（对齐 issues.evidence\_reference） |
| `accepted_by`        | int                       | 否  | —                  | 验收人（Users.user\_id）                 |
| `accepted_at`        | datetime                  | 否  | CURRENT\_TIMESTAMP | 验收时间                                |

> 同一事项可有多条验收记录（驳回后再次验收）；当前有效结论以事项状态 + 最新一条 
>
> `accepted_at`
>
>  记录为准。

### 4.5 `project_closeout_images`（附件表）



| 字段              | 类型            | 可空 | 默认                 | 说明                                            |
| --------------- | ------------- | -- | ------------------ | --------------------------------------------- |
| `image_id`      | int           | 否  | AUTO\_INCREMENT    | 主键                                            |
| `project_id`    | int           | 否  | —                  | 归属项目                                          |
| `item_id`       | int           | 否  | —                  | 所属消项事项                                        |
| `object_key`    | varchar(1024) | 否  | —                  | 存储对象键（对齐 document\_issue\_images.object\_key） |
| `original_name` | varchar(255)  | 否  | —                  | 原始文件名                                         |
| `content_type`  | varchar(100)  | 否  | —                  | MIME 类型                                       |
| `file_size`     | int           | 否  | —                  | 文件大小（字节）                                      |
| `uploaded_by`   | int           | 否  | —                  | 上传人（Users.user\_id）                           |
| `created_at`    | timestamp     | 否  | CURRENT\_TIMESTAMP | 上传时间                                          |



***

## 五、主外键关系

### 5.1 现有库惯例【已确认】



* 现有 dump 全部表**仅含 PRIMARY KEY / UNIQUE KEY / 普通 KEY**，未发现 `FOREIGN KEY ... REFERENCES` 物理外键；项目隔离靠复合唯一键 `(project_id, xxx_id)` + 应用层校验。

* 本域 DDL 是否落地物理外键，需在 DDL 评审时与现有惯例对齐确认【待确认：ORM / DBA 评审】。

### 5.2 建议的引用关系（逻辑外键 + 建议约束级别）



| 子表             | 父表        | 关联列                       | 建议约束                      | 说明                                  |
| -------------- | --------- | ------------------------- | ------------------------- | ----------------------------------- |
| plans          | Projects  | project\_id               | 真实 FK，**RESTRICT**        | 项目不可被事项引用后删除；禁止级联删除                 |
| items          | plans     | plan\_id, project\_id     | 复合真实 FK，**RESTRICT**      | 计划被事项引用不可删除；禁止级联删除                  |
| items          | Projects  | project\_id               | 应用层一致性校验                  | 事项 project\_id 必须等于所属计划 project\_id |
| items          | Materials | material\_id              | **不建级联物理 FK**；应用层校验归属与有效性 | BOM 为独立数据源；引用保护见 §11.3              |
| progress\_logs | items     | item\_id, project\_id     | 复合真实 FK，**RESTRICT**      | 禁止孤立进度记录；事项删除受限                     |
| acceptances    | items     | item\_id, project\_id     | 复合真实 FK，**RESTRICT**      | 禁止孤立验收记录                            |
| images         | items     | item\_id, project\_id     | 复合真实 FK，**RESTRICT**      | 附件随事项归属校验                           |
| plans/items/…  | Users     | created\_by / 操作人         | 普通索引 + 应用层校验              | 现有 issues 对 reported\_by 亦未建物理 FK   |
| items          | Suppliers | responsible\_supplier\_id | 普通索引 + 应用层校验              | 供应商为全局目录，无项目归属列                     |

### 5.3 删除规则【已确认】



* **禁止任何 CASCADE 级联删除**。

* 计划、事项、进度、验收、附件之间：父记录被引用时 **RESTRICT**；不允许删除已产生进度 / 验收的事项作为常规路径。

* 来源字段 `material_id` **禁止置空**；父材料删除 / 停用不影响历史消项记录（§10.3）。



***

## 六、唯一性约束

### 6.1 编号唯一性【已确认】



| 约束        | 列                       | 规则                     |
| --------- | ----------------------- | ---------------------- |
| 计划编号项目内唯一 | `(project_id, plan_no)` | 同一项目内计划编号不重复；跨项目可复用编号段 |
| 事项编号项目内唯一 | `(project_id, item_no)` | 同一项目内事项编号不重复           |

### 6.2 同一项目同一父材料仅一条有效事项【设计提案】



* 业务规则：同一项目、同一父材料，默认只能存在**一条有效**（非 cancelled）消项事项。

* 取消事项保留历史、不释放为 "可被新事项直接顶替" 的状态；恢复时再做唯一性校验（§10.5）。

* 数据库层通过**双生成列 + 组合唯一索引**强制，不允许仅依赖前端校验（§7.2）。

### 6.3 项目归属一致性【已确认】



* 所有子表 `project_id` NOT NULL；事项 / 进度 / 验收 / 附件的 `project_id` 必须与其所属计划、事项一致，事务内校验。

* 禁止跨项目悬挂记录；禁止产生孤立的进度、验收、附件记录。



***

## 七、索引设计

### 7.1 普通 / 复合索引（对齐现有 `idx_` / `uq_` 命名惯例）

`project_closeout_plans`



| 索引名                                | 列                       | 类型 | 用途         |
| ---------------------------------- | ----------------------- | -- | ---------- |
| `PRIMARY`                          | plan\_id                | 主键 | —          |
| `uq_closeout_plan_project_no`      | (project\_id, plan\_no) | 唯一 | 计划编号项目内唯一  |
| `idx_closeout_plan_project_status` | (project\_id, status)   | 普通 | 项目内按计划状态筛选 |

`project_closeout_items`



| 索引名                                | 列                                     | 类型 | 用途                     |
| ---------------------------------- | ------------------------------------- | -- | ---------------------- |
| `PRIMARY`                          | item\_id                              | 主键 | —                      |
| `uq_closeout_item_project_no`      | (project\_id, item\_no)               | 唯一 | 事项编号项目内唯一              |
| `idx_closeout_item_plan`           | (plan\_id, project\_id)               | 普通 | 计划下事项列表                |
| `idx_closeout_item_project_status` | (project\_id, status)                 | 普通 | 项目内按事项状态筛选 / 完成率统计     |
| `idx_closeout_item_material`       | (project\_id, material\_id)           | 普通 | 按父材料反查事项           |
| `uq_closeout_item_active`          | (eff\_project\_id, eff\_material\_id) | 唯一 | 同项目同父材料仅一条有效事项（§7.2） |

`project_closeout_progress_logs`



| 索引名                          | 列                       | 类型 | 用途      |
| ---------------------------- | ----------------------- | -- | ------- |
| `PRIMARY`                    | progress\_id            | 主键 | —       |
| `idx_closeout_progress_item` | (item\_id, project\_id) | 普通 | 事项进度时间线 |

`project_closeout_acceptances`



| 索引名                            | 列                       | 类型 | 用途        |
| ------------------------------ | ----------------------- | -- | --------- |
| `PRIMARY`                      | acceptance\_id          | 主键 | —         |
| `idx_closeout_acceptance_item` | (item\_id, project\_id) | 普通 | 事项验收记录时间线 |

`project_closeout_images`



| 索引名                       | 列                       | 类型 | 用途                                 |
| ------------------------- | ----------------------- | -- | ---------------------------------- |
| `PRIMARY`                 | image\_id               | 主键 | —                                  |
| `idx_closeout_image_item` | (item\_id, project\_id) | 普通 | 事项附件列表（对齐 document\_issue\_images） |

### 7.2 生成列 + 组合唯一索引方案【设计提案，MySQL 8.0.46 已支持】

**方案要点（双生成列，替代早期 CONCAT 单列方案）：**



```
-- 示例 DDL 片段，仅供评审；本轮不生成迁移、不落库
eff_project_id  INT GENERATED ALWAYS AS
  (CASE WHEN `status` <> 'cancelled' THEN `project_id` END) STORED,
eff_material_id INT GENERATED ALWAYS AS
  (CASE WHEN `status` <> 'cancelled' THEN `material_id` END) STORED,
UNIQUE KEY `uq_closeout_item_active` (`eff_project_id`, `eff_material_id`)
```



* 取消（cancelled）时两列均为 **NULL**；MySQL 唯一索引对 NULL 不去重，因此多条已取消历史事项可共存。

* 非取消状态两列分别取原始 `project_id`、`material_id`，组合唯一即 "同项目同材料仅一条有效事项"。

* 两生成列均为 **NOT NULL 语义由表达式保证**（CASE 未命中返回 NULL；仅取消态为 NULL，见上）；组合索引覆盖项目归属与材料来源。

* **并发创建**：同一项目同一材料并发插入两条有效事项时，由唯一索引兜底拒绝后提交，应用层捕获冲突并提示；不在应用层先查后插绕过约束。

* **批量导入**：单事务内执行；编号序列、唯一性校验、快照固化同事务提交；任一行冲突整体回滚（§13）。

* **取消与恢复**：取消 → 生成列置 NULL，历史保留；恢复 → 重新计算生成列，事务内先做来源归属、唯一性、计划状态校验，冲突则拒绝恢复。

> 兼容性备注：现有库 ORM 对 STORED 生成列、JSON、复合唯一索引的映射支持
>
> **待确认**
>
> 【待确认】；若 ORM 不支持生成列，DDL 层仍可由数据库强制，应用层仅读写原始列。



***

## 八、编号规则

### 8.1 编号格式【对齐现有 issues 范式】



* 现有实例：`P1-ISS-001`、`P2-ISS-001`；序列表 `document_number_sequences(project_id, document_type, last_number)`。

* 消项复用该序列表，新增 document\_type：


  * 事项：`CLO`，格式 `P{project_id}-CLO-{seq:3位}`，例 `P1-CLO-001`。

  * 计划：`CLO_PLAN`，格式 `P{project_id}-CLO-PLAN-{seq:3位}`，例 `P1-CLO-PLAN-001`。

* 编号在**项目内**唯一；序列递增在同一事务内占用，批量导入不得重号。

* 编号前缀与 document\_type 取值最终以 DDL 评审为准【待确认】。

### 8.2 编号与事务保障【已确认】



* 编号流水与事项 / 计划插入在同一事务；序列行 `(project_id, document_type)` 行内锁占用，避免并发 / 批量导入重号。

* 事项编号不可为空（与 issues 允许 NULL 的早期数据不同，消项 V1.0 要求编号必填）。



***

## 九、状态流转规则

### 9.1 事项状态定义【已确认】



| 状态              | 含义  | 是否计入完成率分母 | 是否计入分子 |
| --------------- | --- | --------- | ------ |
| pending         | 待处理 | 是         | 否      |
| ongoing         | 进行中 | 是         | 否      |
| pending\_accept | 待验收 | 是         | 否      |
| closed          | 已消项 | 是         | 是      |
| cancelled       | 已取消 | 否         | 否      |

### 9.2 事项状态转换矩阵【设计提案】



| 当前状态                                | 目标状态            | 触发动作 | 必要条件 / 附带写入                                                                              |
| ----------------------------------- | --------------- | ---- | ---------------------------------------------------------------------------------------- |
| pending                             | ongoing         | 开始执行 | 计划处于 active；记录操作人                                                                        |
| ongoing                             | pending\_accept | 申报完成 | 写 `submitted_at`；生成进度记录 `progress_type='submit'`                                         |
| pending\_accept                     | closed          | 验收通过 | 写验收记录 result=passed；回填 `actual_complete_date`；`actual_complete_date` 不得早于 `submitted_at` |
| pending\_accept                     | ongoing         | 验收驳回 | 写验收记录 result=rejected；事项回到 ongoing；**不回填** actual\_complete\_date                        |
| pending / ongoing / pending\_accept | cancelled       | 取消事项 | 必填 `cancel_reason`、写 `cancelled_by`、`cancelled_at`；生成列置 NULL                             |
| cancelled                           | pending         | 恢复事项 | 见 §9.5；恢复后重新进入待处理                                                                        |
| closed                              | —               | —    | **不可直接取消、不可重复验收、不可回退**                                                                   |

### 9.3 验收限制【已确认】



* 只有 `pending_accept` 状态可以执行验收。

* 验收通过 → `closed`；验收驳回 → 回到 `ongoing`。

* `closed` 不允许直接取消或重复验收。

* 取消必须记录操作人和原因（`cancelled_by`、`cancelled_at`、`cancel_reason`）。

* 返工：V1.0 不增加复杂审批流；已 closed 事项如需返工，走**独立变更流程**—— 原事项保留 closed 历史，另建正确事项关联说明（§10.6）。

### 9.4 计划状态转换矩阵【设计提案】



| 当前状态   | 目标状态   | 触发动作 | 规则                                                  |
| ------ | ------ | ---- | --------------------------------------------------- |
| draft  | active | 发布计划 | 草稿计划不进入正式项目完成率（§14）                                 |
| active | closed | 关闭计划 | **不自动完成**未完成事项；已关闭计划下未完成事项仍可继续执行和验收；已关闭计划**不能新增**事项 |
| closed | active | 重新开启 | 必须有明确操作记录（audit\_logs）；重开后可继续新增 / 执行事项              |
| draft  | closed | 废弃草稿 | 草稿不进完成率；是否允许直接关闭草稿待终审【待确认】                          |

### 9.5 取消事项恢复校验【设计提案】

恢复前在同一事务内校验：



1. **来源有效**：`material_id` 对应材料仍存在且归属本项目；

2. **唯一性**：同项目同材料当前不存在另一条有效事项（生成列唯一索引兜底）；

3. **计划状态**：所属计划未被永久废弃；closed 计划下恢复事项需先重开计划，或按终审规则允许在 closed 计划续办既有事项【待确认】；

4. 恢复动作写 audit\_logs，记录原取消原因、恢复人、恢复时间。

### 9.6 日期约束【设计提案】



* `planned_start_date` ≤ `planned_end_date`；事项日期不得与所属计划日期范围明显冲突时给提示（不强制阻断，待终审）。

* `submitted_at` 早于 / 等于验收时间；`actual_complete_date` 仅在验收通过时确认。

* 已关闭计划不自动改写任何事项日期或状态。



***

## 十、来源与关联规则（V2.1 纠正）

> 本章原为「BOM 历史数据保护规则」，以 BOM 为来源、以快照为准。该口径**作废**：父材料是消项唯一来源，BOM 只作实时联查，消项不存任何 BOM 副本。

### 10.1 原则【V2.1 确认】



* **父材料是唯一来源对象**：`Material_Compositions.parent_material_id` 指向的 `material_id`；事项表 `material_id` 即该父材料引用，不做类型扩展。
* 位置与量来自材料测算明细（`Initial_Cost_Estimate_Detail`），落为子项 `baseline_qty`，**不来自 BOM**。
* BOM 现值只在预测时读取（成本行 / 资源行 / 单价），只读、不回写、不同步、不建冻结副本。
* 消项侧**不存在** `snapshot` 列、`bom_version` 列、任何 BOM 冻结 JSON。原 §4.2 的 `snapshot` 已在 V1.1 删除，V2.1 明确不恢复。

### 10.2 历史追溯靠什么【V2.1 确认】



| 要回答的问题              | 取数来源                                    |
| ------------------- | --------------------------------------- |
| 这条消项当初依据什么          | 事项 `material_id`（父材料）+ 子项 `baseline_qty` / `baseline_at` |
| 当初在哪些空间、各多少量        | 子项 `location` 行 + 对应测算行                 |
| 现在这活儿值多少钱、要几个工      | 预测页实时读 BOM 现值                          |
| BOM 改了，历史会不会变        | 不会：消项表不含任何 BOM 派生字段                    |

### 10.3 父材料删除与停用保护【已确认 + 待确认】



* **已被有效或历史消项引用的父材料禁止物理删除**；删除前由应用层校验（对齐现有 `delete_materials` 权限语义：仅可删未被业务记录引用的材料）。
* 现有 `Materials` 无停用 / 软删除字段【待确认】；一旦引入：
  * 停用**不影响**历史消项，事项仍凭 `material_id` 追溯；
  * `material_id` 禁止置空；
  * 已停用父材料不可被新事项引用。
* 不设级联删除外键；BOM 侧任何生命周期操作不得级联删除消项事项、进度、验收、附件。

### 10.4 BOM 变更的影响范围【V2.1 确认】



* **影响**：预测页的剩余材料量、人工、工日、金额。
* **不影响**：事项状态、子项申报与验收、基线量、完成率、编号、计划与实际日期。
* 因此不存在"BOM 版本升级需迁移消项"的问题，也不需要任何同步任务；BOM 每次改动只是让下一次预测结果变化。

### 10.5 与成本域口径的关系【V2.1 确认】



* 成本域已定"资源行去快照、实时引用、不建冻结副本"；本域现与之**一致**。原 §10.6"本域快照与成本域口径不同"的说法作废。



***

## 十一、父子材料防重复规则

### 11.1 现有结构事实【已确认】



* 父子关系唯一来源：`Material_Compositions(project_id, parent_material_id, child_material_id, quantity)`；现有数据为单层边。

* 消项表**不修改** BOM 原始父子关系，不新增 `is_statistics` 单布尔字段解决互斥。

### 11.2 管控模式 `coverage_mode`【设计提案】



| 模式           | 含义                             | 纳入统计规则                                           | 排除规则                             |
| ------------ | ------------------------------ | ------------------------------------------------ | -------------------------------- |
| `whole` 整体管控 | 以一个父级（整合产品 / 组合材料）作为独立交付对象整体消项 | 纳入 `coverage_root_id` 指向的根材料；其下子级材料**不单独**作为统计对象 | 子级若另立事项，属重复纳入，唯一性 / 归并校验拦截       |
| `split` 拆分管控 | 父级拆到子级材料逐项消项                   | 纳入被拆分的子材料；父级不重复作为独立统计对象                          | 父级若另立 whole 事项，与子级 split 范围重叠时拦截 |



* **同一交付范围只能采用一种管控模式**；whole 与 split 不得在同一归并范围内混用。

* `coverage_root_id`：whole 模式下为纳入统计的根材料；split 模式下为拆分归属父级，用于归并判断与范围冲突检测。

### 11.3 多级父子统计规则【设计提案 + 待确认】



* 统计以**实际纳入的独立交付对象**为准：沿 `Material_Compositions` 边向上归并到根；父级、子级不得同时作为独立统计对象。

* 现有边为单层；多级递归深度、防环规则【待确认】。

* 计算完成率时，whole 根与其 split 子级不重复计数（§14）。

### 11.4 取消释放与恢复冲突【设计提案】



* 事项取消后，其占用的交付范围在恢复校验时重新参与冲突检测：


  * 若期间同范围已存在另一条有效事项，恢复被拒；

  * 取消是否即时释放范围供新事项占用：V1.0 按 "取消即释放、新建走唯一性校验；恢复时再查重" 处理【待终审确认】。

### 11.5 多父共享组件【待确认】



* 现有边允许同一 child 出现在多个 parent 下（如现状 child 124 同时挂 33、17）。共享组件跨交付范围的归属、是否允许在多个父级事项中分别消项、完成率如何归并，业务规则未确认；V1.0 先按 "同一材料仅一条有效事项" 的全局唯一性兜底，跨父级拆分规则待终审。



***

## 十二、进度记录、验收记录与附件规则

### 12.1 进度记录【设计提案】



* 进度记录从属于事项，可多次追加；记录类型 `note` / `submit`。

* `submit` 类型记录与事项进入 `pending_accept`、`submitted_at` 写入同事务。

* 进度记录不可删除作为常规路径；如需更正，追加新记录说明（对齐审计追加原则）。

### 12.2 验收记录【设计提案】



* 验收记录从属于事项；驳回可再次验收，历史验收记录全部保留。

* 验收通过时由事务联动事项 `status='closed'`、回填 `actual_complete_date`；驳回联动回 `ongoing`。

* 验收佐证 `evidence_reference` 与附件表配合使用；附件可在事项维度上传。

### 12.3 附件【设计提案】



* 附件范式对齐 `document_issue_images`：对象键 `object_key` 存储，元信息落库；不在事项表内嵌 JSON 附件清单。



***

## 十三、并发与事务保护【设计提案】



* **创建事项**：事务内校验项目归属、材料有效性、唯一性（生成列唯一索引兜底）、计划状态（active 方可新增）；快照同事务固化；编号同事务占用。

* **取消 / 恢复 / 状态流转**：事务内行锁事项记录；先校验当前状态与目标状态合法性，再更新状态与关联时间 / 人员；audit\_logs 同事务追加。

* **批量导入**：单事务；任一行编号冲突、唯一性冲突、归属校验失败则整体回滚；不允许部分成功留下半批事项。

* **进度 / 验收写入**：先锁事项行校验状态，再写子记录；禁止向已 closed 事项追加验收以外的执行进度（细节待终审）。

* 乐观锁：事项可参考现有组价并发方向，在 DDL 评审时评估是否增加 `version` 列；V1.0 不强制【待确认】。



***

## 十四、项目完成率计算规则

### 14.1 事项口径【已确认】



* 分子：`closed` 事项数。

* 分母：`pending + ongoing + pending_accept + closed` 事项数；`cancelled` **不计入**分子和分母。

### 14.2 计划级完成率【设计提案】



* 仅统计该计划下有效事项（排除 cancelled）。

* 草稿（draft）计划**不进入**正式项目完成率；active /closed 计划纳入。

* 分母为 0（无有效统计事项）时，完成率显示 `--`，不显示 0%。

* 已关闭计划下未完成事项仍计入该计划分母；其能否在项目级口径中继续统计，待终审【待确认】。

### 14.3 项目级完成率【设计提案】



* 汇总本项目所有纳入范围计划的有效事项：项目完成率 = 项目 closed 事项数 / 项目有效事项数。

* 父子材料以实际纳入的独立交付对象为准，根与子级不重复计数（§11）。

* 无有效统计事项时显示 `--`。

### 14.4 统计口径示例（规则说明，非实现数值）



| 事项状态            | 计入分母 | 计入分子 |
| --------------- | ---- | ---- |
| pending         | 是    | 否    |
| ongoing         | 是    | 否    |
| pending\_accept | 是    | 否    |
| closed          | 是    | 是    |
| cancelled       | 否    | 否    |



***

## 十五、审计与操作日志规则

### 15.1 复用现有 audit\_logs【已确认】



* 计划关闭、重新开启、事项取消、恢复、来源更正（取消 + 新建）等操作历史**复用** `audit_logs`：


  * `entity_type`：`closeout_plan` / `closeout_item`；

  * `entity_id`：plan\_id / item\_id；

  * `action`：如 `close_plan`、`reopen_plan`、`cancel_item`、`restore_item`；

  * `details`：JSON 文本记录前后状态、原因、操作人上下文；

  * `user_id` / `project_id` / `ip_address` / `user_agent` / `created_at` 沿用现有列。

* **不新建**独立计划日志表、状态历史表；审计记录**只追加**，不允许 JSON 覆盖式保存。

### 15.2 审计最小集【设计提案】

操作对象（计划 / 事项）、动作、前后状态、来源材料前后值、取消 / 驳回原因、验收结论、操作人、时间。



***

## 十六、未来扩展策略



| 方向                   | V1.0 处理     | 后置扩展说明                                                              |
| -------------------- | ----------- | ------------------------------------------------------------------- |
| WBS / Issue / RFI 关联 | 不接入，不建多态来源表 | 后续需要时，按领域建类型化关联表（对齐成本域三类型化 FK 思路），不在事项表预留通用 source\_type/source\_id |
| 多来源消项                | 仅父材料    | 扩展时新增类型化来源（WBS/Issue/RFI），不沿用快照（V2.1 取消快照）      |
| 审批流                  | 不做          | 验收 / 返工审批后置；V1.0 验收由验收人直接结论                                         |
| 组织 / 部门              | 不假设存在       | 待组织表落地后，责任单位 / 责任归属可从自由文本 / 供应商关联迁移到组织关联                            |
| BOM 版本               | 不建版本链、不记录    | BOM 只按现值实时联查，消项侧不保存版本标识；版本变化只影响预测结果                  |
| 复杂父子 / 共享组件规则        | 全局唯一兜底      | 多级递归、多父共享跨范围归并规则确认后补充到 §11                                          |



***

## 十七、与现有源码及数据库的差异分析

### 17.1 新增（消项域，5 张表）



| 新表                                | 对应现有范式参照                                   |
| --------------------------------- | ------------------------------------------ |
| project\_closeout\_plans          | document\_issues 主表的状态 / 编号 / 人员 / 时间列范式   |
| project\_closeout\_items          | document\_issues 主表 + 双生成列唯一索引 + 快照 JSON   |
| project\_closeout\_progress\_logs | document\_issue\_follow\_ups 跟进流水范式        |
| project\_closeout\_acceptances    | document\_issues 验收 / 关闭结论列范式（独立成表，支持多次验收） |
| project\_closeout\_images         | document\_issue\_images 附件范式               |

### 17.2 复用现有（不新建）



* 项目隔离：`Projects.project_id`；用户：`Users.user_id`；责任单位候选：`Suppliers.supplier_id`。

* BOM 只读引用：`Materials.material_id`；父子归并实时读 `Material_Compositions`。

* 审计：`audit_logs`；编号序列：`document_number_sequences`（新增 type `CLO` / `CLO_PLAN`）。

* 风险等级枚举对齐 `document_issues.severity`；附件列对齐 `document_issue_images`。

### 17.3 与现有库的结构差异点



| 项        | 现有库现状                                         | 消项方案                                                     |
| -------- | --------------------------------------------- | -------------------------------------------------------- |
| 物理外键     | 全部表无 `FOREIGN KEY` 约束，靠复合唯一键 + 应用层            | 建议 RESTRICT 真实 FK；是否落地待 DDL/ORM 评审，未落地前降级为普通 KEY + 应用层校验 |
| 生成列      | 现有表未使用                                        | 新增双 STORED 生成列支撑有效事项唯一索引（MySQL 8.0.46 支持）                |
| JSON 列   | 现有 `details/transition_data` 多为 text 存 JSON 串 | 事项快照用原生 JSON 列；ORM 兼容性待确认                                |
| 软删除 / 停用 | Materials 无对应字段                               | 方案不依赖 BOM 软删；删除保护由应用层校验 + 子项基线量追溯          |
| 组织归属     | 无部门 / 组织表                                     | 责任单位暂挂 Suppliers 或文本，待终审                                 |

### 17.4 明确不沿用的旧设计



* 数量核销字段（write\_off\_qty）、BOM 累计核销数量计算：**删除**，不复活。

* 旧 PHP 工程表结构（Internal\_BOM / BOM\_Components / Buildings 旧字段等）：**不作为依据**。

* 在 BOM 表回写已消项状态 / 数量：禁止。

* 多态来源表、`is_statistics` 单布尔父子互斥、CONCAT 单列生成列、JSON 覆盖式日志：不采用。



***

## 十八、待确认事项清单（未确认前不实现）



1. **ORM 及框架**：实际 ORM 对 STORED 生成列、JSON 列、复合唯一索引的支持与映射方式（源码未在本目录，待查）。

2. **责任单位载体**：关联 `Suppliers`、自由文本，还是后续新建组织 / 承包单位表；现有无组织部门表。

3. **BOM 停用 / 软删除机制**：Materials 是否计划增加状态 / 归档字段；删除保护校验接入点。

4. **父子结构深度**：BOM 是否存在多级递归、是否需要防环校验；多父共享组件跨范围消项与完成率归并规则。

5. **来源绑定对象**：消项来源绑定 `Materials.material_id`（推荐）还是 `Material_Compositions` 边 / 成本头表，终审确认。

6. **closed 计划续办口径**：已关闭计划下未完成事项是否继续计入项目完成率；closed 计划恢复事项是否必须先重开计划。

7. **编号前缀 /document\_type**：`CLO`、`CLO_PLAN` 取值与编号位数。

8. **物理外键落地**：新表是否按建议建 RESTRICT 真实 FK，还是沿用现有纯普通 KEY 惯例。

9. **乐观锁 version 列**：事项并发编辑是否需要。

10. **草稿计划关闭**：draft 是否允许直接关闭、关闭后事项如何处置。



***

## 十九、修订记录（本轮定点修订）

### 19.1 修改章节与原因对照



| #  | 修改章节             | 修改原因（终审任务书）                                                    | 修改内容                                                                                                   | 对现有数据库兼容性影响                                     |
| -- | ---------------- | -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------- |
| 1  | §4.2、§7.2 唯一索引   | 早期 "MySQL 部分唯一索引" 说法错误；须按真实库能力设计可靠约束                           | 核实 MySQL 8.0.46；改双生成列 `eff_project_id`/`eff_material_id` + 组合唯一索引；取消态置 NULL；覆盖并发创建、批量导入、取消恢复           | 依赖 STORED 生成列（8.0 支持）；ORM 映射待确认；不兼容 5.7 生成列限制场景 |
| 2  | §10 BOM 快照       | 须固化来源快照、区分实时与历史                                                | 新增 `snapshot` JSON（material\_id/code/name/spec/unit/ 父级集合 /bom\_version）；不可编辑；BOM 修改不影响快照；停用 / 软删靠快照追溯 | 新增 JSON 列；与成本域去快照口径显式区分                         |
| 3  | §11 父子互斥         | 不得用 is\_statistics 单布尔；须明确整体 / 拆分管控                            | 新增 `coverage_mode`（whole/split）、`coverage_root_id`；定义纳入 / 排除规则、多级归并、取消释放与恢复冲突、多父共享待确认                  | 不改动 Material\_Compositions 原始边；新增两列             |
| 4  | §5 外键与删除         | 区分物理 FK 与业务关联；禁级联；禁孤立记录、禁破坏历史                                  | 建议 RESTRICT 复合 FK；BOM 关联不级联；进度 / 验收 / 附件从属于事项；材料引用禁物理删除；快照与 material\_id 不置空                           | 现有库无物理 FK，落地方式待评审；降级方案为普通 KEY + 应用层校验           |
| 5  | §8 编号唯一性         | 计划 / 事项编号项目内唯一；批量导入事务保障                                        | `uq(project_id, plan_no)`、`uq(project_id, item_no)`；复用 number\_sequences；编号同事务占用                       | 对齐现有 issues 编号范式；新增两个 document\_type            |
| 6  | §9.6、§12.2 日期与关闭 | actual\_complete\_date 仅验收通过确认；新增 submitted\_at；关闭计划不自动完成、不可新增 | 事项增加 `submitted_at`；`actual_complete_date` 仅 passed 回填；计划关闭规则、重开规则与操作记录明确                              | 新增两个日期 / 时间列；无破坏性变更                             |
| 7  | §14 完成率          | 明确分子分母；草稿排除；无有效事项显示 `--`                                       | 状态口径表；计划级 / 项目级规则；cancelled 不计；分母 0 显示 `--`；父子不重复计数                                                    | 纯查询口径，不涉及表结构变更                                  |
| 8  | §10.5 来源不可改      | bom\_item\_id（material\_id）创建后禁普通编辑；来源错误走取消留史 + 新建             | 明确 `material_id` 创建后不可普通编辑；更正路径为取消原事项 + 新建正确事项                                                         | 应用层编辑约束；数据库层不另加只读列限制                            |
| 9  | §3 实体关系图         | 修正 Project → Plans → Items；BOM 独立数据源                           | ER 图重绘；BOM 不作为计划父级业务表                                                                                  | 无                                               |
| 10 | §15 计划操作日志       | 评估可复用审计；追加记录不覆盖                                                | 复用 `audit_logs`，不建 plan\_logs；details 追加 JSON；记录关闭 / 重开 / 取消 / 恢复                                      | 不新增日志表；沿用现有 audit\_logs 列                       |
| 11 | §13 并发保护         | 创建 / 删除需事务与并发保护                                                | 事项创建、取消、恢复、批量导入事务与行锁 / 唯一索引兜底规则                                                                        | 应用层事务要求；数据库唯一索引强制                               |
| 12 | §2、§17 兼容核查      | 须核查真实 MySQL 版本、BOM 结构、删除机制、组织表、ORM 支持                          | 逐条标注【已确认】/【待确认】；未查到的不假设                                                                                | 见 §17.3 差异表                                     |

### 19.2 本轮未决与边界



* 全部表结构、约束、索引均为**设计提案**，未建表、未生成迁移、未创建 ORM 模型、未改源码、未执行数据库操作。

* 待终审放行后，方可进入 DDL 单独审批 → 专用测试库实测 → 开发的后续闸门（对齐 25 号 § 十二流程）。



***

## 二十、V2.0 修订：父材料 — 空间子项模型【已确认方向，细节待 DDL 终审】

### 20.1 变更动因



| # | 事实                                                                                                     | 结论                          |
| - | ------------------------------------------------------------------------------------------------------ | --------------------------- |
| 1 | 消项要回答的是 "工程做完没有"，交付对象是**父材料在某空间**                                                                      | 事项单位取父材料，位置单位取空间，不能反过来      |
| 2 | 材料测算明细 `Initial_Cost_Estimate_Detail` 已是 `父材料 + building_id + floor_id + room_id + estimated_quantity` | 与消项**同维**，直接同维关联，不需要桥表与单位换算 |
| 3 | BOM 的锚点也是父材料（父材料→子材料 × 系数→人工资源行）                                                                       | 三处同维，剩余工程量与人工才能一次 join 贯通   |
| 4 | 按 "父材料 × 空间" 各建一条事项，60 父材料 ×N 房间会膨胀到上千条，列表不可用                                                          | 一条父材料一条事项，空间做子项，勾选完成        |

> 本模型
>
> **不推翻**
>
>  V1 的表结构：
>
> `(project_id, material_id)`
>
>  唯一约束、双生成列、计划状态机、编号规则、审计口径全部沿用，只新增子项层并将状态与验收下沉。

### 20.2 被取代的条款对照



| 原条款             | 原内容                                                    | V2.0 处理                                            |
| --------------- | ------------------------------------------------------ | -------------------------------------------------- |
| §1.3            | "不做数量核销、不设数量字段"                                        | **有限放开**：只记录子项基线量与完成判定，仍不回写 BOM、不做领料核销、不做累计核销、不扣库存 |
| §4.2            | 事项 `status`/`submitted_at`/`actual_complete_date` 人工填写 | 改为**由子项派生写回**（§20.6），不再提供手改入口                      |
| §9.1/9.2        | 事项级状态机与人工流转                                            | 人工流转**下沉到子项**；事项状态为派生结果，不可直接改                      |
| §12.2           | 验收记录挂事项                                                | 验收对象改为**子项**，事项级验收 = 全部子项验收的汇总                     |
| §14.1           | 完成率 = closed 事项数 / 有效事项数                               | 保留（事项完成率），**另加工程量完成率**（§20.7），两者分开展示、不得混算          |
| §4.2 `snapshot` | BOM 来源快照                                               | 已于 V1.1 删除，本版本不恢复；基线量快照落在子项 `baseline_qty`         |
| §17.4           | "数量核销字段不复活"                                            | 仍不复活 `write_off_qty`；`baseline_qty` 是完成判定分母，不是核销量  |

### 20.3 新表 `project_closeout_locations`（空间子项）

> 命名说明：避免使用 
>
> `project_closeout_spaces`
>
> ，以免与项目空间模块（
>
> `Buildings`
>
> /
>
> `Rooms`
>
> ）混淆；本表是 "消项位置行"，一条 = 一个父材料在一个空间的交付与完成记录。



| 字段                        | 类型                                                               | 可空 | 默认                           | 说明                                    |
| ------------------------- | ---------------------------------------------------------------- | -- | ---------------------------- | ------------------------------------- |
| `location_id`             | int                                                              | 否  | AUTO\_INCREMENT              | 主键                                    |
| `project_id`              | int                                                              | 否  | —                            | 归属项目，必须与事项一致                          |
| `plan_id`                 | int                                                              | 否  | —                            | 所属计划（冗余便于隔离校验）                        |
| `item_id`                 | int                                                              | 否  | —                            | 所属消项事项（一条父材料一条事项）                     |
| `estimate_detail_id`      | int                                                              | 是  | NULL                         | 来源测算行 ID，**仅溯源**；多行合并时置 NULL（§20.9）   |
| `building_id`             | int                                                              | 否  | —                            | 楼栋（`Buildings.building_id`）           |
| `floor_id`                | int                                                              | 是  | NULL                         | 楼层（`Building_Floors.floor_id`）；测算允许为空 |
| `room_id`                 | int                                                              | 是  | NULL                         | 房间（`Rooms.room_id`）；测算允许为空            |
| `baseline_qty`            | decimal(12,4)                                                    | 否  | 0.0000                       | **基线量**：纳入时点该空间的测算数量合计，分母来源           |
| `baseline_at`             | datetime                                                         | 否  | —                            | 基线固化时间                                |
| `status`                  | enum('pending','ongoing','pending\_accept','closed','cancelled') | 否  | 'pending'                    | 子项状态，枚举与事项一致                          |
| `submitted_at`            | datetime                                                         | 是  | NULL                         | 该空间申报完成时间                             |
| `accepted_at`             | datetime                                                         | 是  | NULL                         | 该空间验收通过时间（`accepted` 时写）              |
| `accepted_by`             | int                                                              | 是  | NULL                         | 验收人（`Users.user_id`）                  |
| `planned_start_date`      | date                                                             | 是  | NULL                         | 该空间计划开始                               |
| `planned_end_date`        | date                                                             | 是  | NULL                         | 该空间计划完成（逾期判定以此为准）                     |
| `responsible_supplier_id` | int                                                              | 是  | NULL                         | 该空间责任单位（`Suppliers`），可空               |
| `responsible_user_id`     | int                                                              | 是  | NULL                         | 该空间责任人（`Users`），可空                    |
| `cancel_reason`           | text                                                             | 是  | NULL                         | 子项取消原因（取消必填）                          |
| `cancelled_by`            | int                                                              | 是  | NULL                         | 取消人                                   |
| `cancelled_at`            | datetime                                                         | 是  | NULL                         | 取消时间                                  |
| `created_by`              | int                                                              | 否  | —                            | 创建人                                   |
| `created_at`              | timestamp                                                        | 否  | CURRENT\_TIMESTAMP           | —                                     |
| `updated_at`              | timestamp                                                        | 否  | CURRENT\_TIMESTAMP ON UPDATE | —                                     |
| `eff_space_key`           | varchar(80)                                                      | 否  | 生成列                          | 有效空间键（§20.4）                          |

### 20.4 生成列与唯一性【设计提案，MySQL 8.0.46 已验证支持】



```
-- 仅评审用片段，本轮不生成迁移
eff_space_key VARCHAR(80) GENERATED ALWAYS AS (
  CASE WHEN `status` <> 'cancelled'
  THEN CONCAT(CAST(`project_id` AS CHAR), '-', CAST(`item_id` AS CHAR), '-',
              CAST(`building_id` AS CHAR), '-',
              COALESCE(CAST(`floor_id` AS CHAR), '0'), '-',
              COALESCE(CAST(`room_id` AS CHAR), '0'))
  END) STORED,
UNIQUE KEY `uq_closeout_location_active` (`eff_space_key`)
```



* **为什么用单列字符串键**：`floor_id`/`room_id` 可空，若直接建 `(item_id, building_id, floor_id, room_id)` 组合唯一索引，MySQL 对 NULL 不去重，同一空间可被重复插入；`COALESCE(...,'0')` 先归一，再靠单列唯一兜底。

* 取消（`cancelled`）时整列为 NULL，唯一索引放行多条历史取消记录，与事项唯一键同一模式。

**索引清单**



| 索引名                                    | 列                                         | 类型 | 用途                    |
| -------------------------------------- | ----------------------------------------- | -- | --------------------- |
| `PRIMARY`                              | location\_id                              | 主键 | —                     |
| `uq_closeout_location_active`          | (eff\_space\_key)                         | 唯一 | 同一事项内同一空间仅一条有效子项      |
| `uq_closeout_location_scope`           | (location\_id, project\_id)               | 唯一 | 供验收 / 进度 / 附件复合 FK 引用 |
| `idx_closeout_location_item`           | (item\_id, project\_id)                   | 普通 | 事项下子项列表               |
| `idx_closeout_location_project_status` | (project\_id, status)                     | 普通 | 工程量完成率聚合              |
| `idx_closeout_location_building`       | (building\_id, floor\_id, room\_id)       | 普通 | 按空间反查消项               |
| `idx_closeout_location_estimate`       | (estimate\_detail\_id)                    | 普通 | 测算行溯源（无 FK）           |
| `idx_closeout_location_plan_date`      | (project\_id, status, planned\_end\_date) | 普通 | 逾期统计                  |

### 20.5 引用与删除规则【设计提案】



| 子表                                    | 父表                             | 关联列                                 | 约束                                                                 |
| ------------------------------------- | ------------------------------ | ----------------------------------- | ------------------------------------------------------------------ |
| locations                             | items                          | (item\_id, project\_id)             | 复合真实 FK，RESTRICT                                                   |
| locations                             | plans                          | (plan\_id, project\_id)             | 复合真实 FK，RESTRICT                                                   |
| locations                             | Projects                       | project\_id                         | 真实 FK，RESTRICT                                                     |
| locations                             | Buildings                      | building\_id                        | 真实 FK，RESTRICT                                                     |
| locations                             | Building\_Floors               | (floor\_id, building\_id)           | 复合 FK，RESTRICT                                                     |
| locations                             | Rooms                          | (room\_id, building\_id, floor\_id) | 复合 FK，RESTRICT                                                     |
| locations                             | `Initial_Cost_Estimate_Detail` | estimate\_detail\_id                | **不建物理 FK**：测算行可编辑、可删除，建 RESTRICT 会反向锁死材料测算模块的删除路径；溯源由应用层校验 + 展示降级 |
| acceptances / progress\_logs / images | locations                      | (location\_id, project\_id)         | 新增列，可空；新记录必填 location\_id，复合 FK RESTRICT                           |



* 禁止任何 CASCADE；取消子项不删除记录。

* **事项取消 = 其有效子项整批置 cancelled**（同一事务、同一操作人、同一原因），单条 SQL 不可绕过；恢复事项时自动恢复其 cancelled 子项，并重算状态。

### 20.6 状态派生规则【设计提案】

**有效子项**：`status <> 'cancelled'` 的子项。



| 事项状态             | 判定条件（全部有效子项）                                          |
| ---------------- | ----------------------------------------------------- |
| `pending`        | 存在 `pending` 子项                                       |
| `ongoing`        | 无 `pending`，且存在 `ongoing`                             |
| `pending_accept` | 全部 ∈ {`pending_accept`,`closed`}，且存在 `pending_accept` |
| `closed`         | 全部 = `closed`                                         |
| `cancelled`      | 事项已取消                                                 |



* 采用**木桶规则**：事项整体完成以最慢空间为准，"全部空间装完" 才允许 `closed`；局部进度由进度条（§20.7）表达，不用状态表达。

* 派生写回：任何子项状态变化后，在同一事务内 `UPDATE project_closeout_items SET status=?, submitted_at=MAX(子项.submitted_at), actual_complete_date=(全 closed 时 MAX(DATE(accepted_at)) 否则 NULL)`。事项状态**不再允许直接 UPDATE**，服务层拒绝外部传入事项状态。

* 无有效子项（legacy 事项，§20.14）：保持 V1 手填状态与日期，标记 `legacy`（页面提示 "未启用空间跟踪"），不参与派生重算。

* 事项级计划日期：保留可编辑，写入时**批量下发**到全部有效子项并记审计；展示值取子项 `MIN(planned_start_date)` / `MAX(planned_end_date)`。

### 20.7 进度与完成率口径【设计提案】



| 指标        | 公式                                                | 说明                     |
| --------- | ------------------------------------------------- | ---------------------- |
| 子项进度      | `closed`=100%，`pending_accept`= 已申报未验收，其余 0       | 用于颜色与排序                |
| 事项已验收进度   | Σ`baseline_qty`(`closed`) ÷ Σ`baseline_qty`(有效子项) | 量加权，4 位小数；分母 0 显示 `--` |
| 事项已申报进度   | Σ`baseline_qty`(`pending_accept`+`closed`) ÷ 同上分母 | 进度条半段                  |
| 事项完成率（条数） | `closed` 事项数 ÷ 有效事项数                              | 沿用 §14，管理视角            |
| 工程量完成率    | Σ`baseline_qty`(`closed`) ÷ Σ`baseline_qty`(有效)   | 施工视角，独立展示              |



* **禁止按子项条数算百分比**：20 m² 房间与 200 m² 大堂同权会直接骗人。

* 进度百分比**只读派生**，不提供手填或拖动入口；手填百分比一律视为违规数据，报表不采信。

* 两个完成率必须分列不同表头 / 图例，不得在同一数字里混算。

### 20.8 基线量、测算变更与重定基线【设计提案】



* `baseline_qty` 在纳入时点固化，**不随测算行变化而自动变化**（测算行可编辑，同 V1 BOM "编辑即重建" 的坑）。

* 页面同时显示 "基线量（纳入时）" 与 " 当前测算量（实时读 `Initial_Cost_Estimate_Detail`）"，差异超过容差（建议 0.5%）时提示。

* 调整基线必须走**显式动作**"重定基线"：校验差异原因 → 覆盖 `baseline_qty`、刷新 `baseline_at` → 写 `audit_logs`（前后值、原因、操作人）。无此动作，分母不动。

* 已 `closed` 的子项不得重定基线；需撤销完成（有权限的 "退回" 动作）后再调整。

### 20.9 纳入口径：自动全量纳入（V2.1 定稿，人工勾选取消）



1. **取消"选择父材料 → 纳入"的人工步骤**。计划处于 `active` 时，系统直接把当前项目的**所有已测算父材料一次性全部纳入**，每条父材料一条事项，并同步挂出空间子项。
2. 触发点三处，全部幂等：新建计划即 `active`、草稿计划被启动、计划被重新打开；另有"同步新增父材料"按钮，供后续补录测算后补齐。
3. 候选集 = `Initial_Cost_Estimate_Detail` 中出现过的父材料（经 `Buildings.project_id` 隔离）；**BOM 明细行不参与候选**。
4. 父材料身份仍由 `Material_Compositions` 判定（有下级即父级）。若某材料有测算行但不是父材料，**跳过并提示**——测算必须按父材料录入，这是录入方的数据问题，不能靠系统兜。
5. 已存在有效事项的父材料直接计入 `existing`，不重复建（`(project_id, material_id)` 唯一约束兜底）。
6. 一条父材料的测算行按 `(building_id, floor_id, room_id)` 聚合为子项；同一空间多行合并，`baseline_qty` = 各行 `estimated_quantity` 之和，`estimate_detail_id` 置 NULL（多来源无法单指）。
7. 单行来源保留 `estimate_detail_id`，仅作溯源展示，不参与计算。
8. 后续要给某条事项追加空间：走"追加子项"入口，不新建事项；要求计划 `active`，同一空间不重复（唯一索引兜底）。
9. 测算行 `building_id` 必填、`floor_id`/`room_id` 可空；空楼层 / 空房间按"楼层级 / 楼栋级"展示，不与具体房间合并。

> 口径提醒：
>
> `Initial_Cost_Estimate_Detail`
>
>  
>
> **没有&#x20;**
>
> `project_id`
>
> **&#x20;列**
>
> ，项目归属只能经 
>
> `Buildings.project_id`
>
>  判定。服务层必须校验 
>
> `Buildings.project_id = 事项 project_id`
>
> ，不得跨项目取测算行。
>
> **过渡事实**：真实库现有数据尚未迁移到"按父材料录入测算"的新口径（历史遗留），因此当前真实项目上自动纳入会得到 0 条事项，属预期；迁移由业务方手工完成，系统不做数据搬迁。

### 20.10 验收、进度、附件下沉【设计提案】



| 表                                | 变更                                    | 规则                                                                                                                        |
| -------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `project_closeout_acceptances`   | 加 `location_id` int NULL + 索引 + 复合 FK | 新验收必须带 `location_id`；通过 → 子项 `closed`、写 `accepted_at`/`accepted_by`；驳回 → 子项回 `ongoing`。历史记录 `location_id` 保持 NULL（无法反推空间） |
| `project_closeout_progress_logs` | 加 `location_id` int NULL              | `note` 可挂事项或空间；`submit` 必须挂空间，与子项 `pending_accept`、`submitted_at` 同事务                                                     |
| `project_closeout_images`        | 加 `location_id` int NULL              | 事项级或空间级附件；空间级验收照片建议强制至少 1 张（待终审）                                                                                          |



* 验收人权限沿用现有验收权限键（键名待核，§20.15）。

* 同一子项可多次验收（驳回后再报），历史全留；当前结论以子项状态 + 最新 `accepted_at` 为准。

### 20.11 界面结构（沿用单页工作台）【设计提案】



* 列表行 = 一条父材料：编号、父材料名 / 规格 / 单位、状态 chip、**双段进度条**（已验收实色 + 已申报半透明 + 剩余灰）、"空间 6/10"、逾期空间数、责任人、计划完成日。

* 行内展开 = 子项表：楼栋 / 楼层 / 房间、基线量、状态、计划完成、责任人、验收人 / 日期、操作（开始 / 申报 / 验收 / 取消）。

* 复选框语义：勾选 = **申报完成**（进入 `pending_accept`，需二次确认）；验收通过 = 独立按钮 + 权限校验。不得把勾选直接当验收通过。

* 批量：全选空间申报 / 全选空间验收 / 批量下发计划日期。

* 顶部只有"同步新增父材料"（幂等补齐）与"新建消项计划"，**没有逐项勾选纳入的入口**；旧弹层降级为历史补录，不再从主流程进入。

* 排序见 §20.17：逾期未完成 → 未完成（按计划完成日升序）→ 已消项 → 已取消。

### 20.17 列表排序规则【V2.1 确认，代码已实现】



| 优先级 | 分组 | 组内排序 |
| --- | --- | --- |
| 1 | 逾期未完成（`pending` / `ongoing` / `pending_accept` 且 `planned_end_date < 今天`） | 计划完成日升序，最晚也得最前 |
| 2 | 未完成未逾期 | 计划完成日升序（无日期排最后） |
| 3 | 已消项 `closed` | 计划完成日升序，整体沉到最后 |
| 4 | 已取消 `cancelled` | 排最末 |

要点：

- **完成即下沉**：一旦验收通过转为 `closed`，无论日期多早都排到未完成之后，列表头部只留待办。
- **紧迫即置顶**：逾期项永远在最前，其次按计划完成日升序，等于自动生成"今天该先干哪条"。
- 该排序在 `CloseoutService::listItems()` 的 `ORDER BY` 内实现，前端不再二次排序，避免两套口径。
- 子项内部（展开后的空间清单）沿用状态 + 计划完成日排序，与事项级规则一致。

* 顶部右侧增加次要按钮 "剩余工程量与人工预测 →"（§20.12）。

### 20.12 预测页边界与导航【已确认】



* 预测内容（剩余工程量、剩余材料、剩余人工、剩余工期）**不在消项页面内**，单独成页。

* 路由建议 `?page=closeout-forecast&project_id={id}`，侧栏入口置于 06 施工管理。

* 消项页只放导航按钮，不内嵌图表与预测表。

* 本轮（V2.0a）只做按钮与目标页占位；目标页未上线时按钮置灰并提示 "预测页开发中"，**不得跳 404**。

* 预测页只读：不回写 BOM、不回写采购 / 库存 / 成本，不做领料核销。

* **BOM 取最新现值，不取快照**：每次打开预测页都重新读当前 BOM / 单价 / 资源行；BOM 改了预测结果就变，消项记录不变（§10.4）。

* 口径提醒两条：

1. **损耗只乘一次**。BOM 成本明细的 `quantity` 已含损耗（`calculated_quantity`），剩余材料量 = 剩余工程量 × BOM 系数，不可再乘损耗率。

2. **人工系数语义待核**。若人工资源行 `quantity` 记的是工日，剩余工日 = 剩余工程量 × 系数，可再折算 "人 × 天"；若只是 "元 / 单位" 包干，则只能得人工费，落不到班组排班。未核实前，预测页只显示 "人工费" 字段，不显示工日。

### 20.13 收口：本版本明确不做【已确认边界】



* V2.0 的 `closed` 语义 = **该父材料在全部纳入空间安装完成**，**不含收口**。

* 收口是父材料之间的界面关系（A 收口至 B），单条父材料进度条无法表达；强行塞进子项字段会变成不可统计的自由文本。

* 因此：事项关闭弹窗必须显示 "收口未纳入本版本" 提示；报表标题为 "安装完成率"，不写 "交付完成率"。

* V2.1 建议模型（草案，不在本版本实施）：`project_closeout_joints(joint_id, project_id, item_id_a, location_id_a, item_id_b, location_id_b, joint_type, status, accepted_at)`，以子项为端点建边。

### 20.14 迁移与兼容【设计提案】



| 项         | 处理                                                                                                                            |
| --------- | ----------------------------------------------------------------------------------------------------------------------------- |
| 迁移文件      | 新增 `202611270001_closeout_locations.sql`（建表 + 索引 + FK）；`202611270002_closeout_location_refs.sql`（三表加 `location_id` + 索引 + FK） |
| 历史事项      | 现有事项（真实库仅 3 条）无子项，保留为 legacy：状态与日期继续手填，进度按状态口径，不计入工程量完成率                                                                      |
| 历史验收      | `location_id` = NULL，仅事项级追溯；不反推空间                                                                                             |
| 历史进度 / 附件 | 同上，`location_id` 可空                                                                                                           |
| 新事项       | 必须至少 1 条子项，否则拒绝创建（避免新旧混用）                                                                                                     |
| 口径切换      | 项目 / 计划报表同时给出 "事项完成率（含 legacy）" 与 "工程量完成率（仅空间跟踪事项）"，并在表头注明口径，禁止合并成单一数字                                                        |
| 回滚        | 建表可 DROP；三表新增列可 DROP COLUMN；不改既有列，无破坏性变更                                                                                      |

### 20.15 V2.0 待确认清单（未确认不实施）



1. **班组载体**：是否存在施工班组表（06 施工管理下）；本轮先用 `responsible_user_id` / `responsible_supplier_id`，班组字段留待确认，不臆造表名。

2. **验收权限键**：现有权限体系中验收 / 管理键的实际命名（`manage_closeout` / `accept_closeout` 待核对源码）。

3. **人工资源行语义**：`resource_catalog` 人工行的 `quantity` 是工日还是 "元 / 单位" 包干，直接决定预测页能否出工日。

4. **legacy 3 条事项**：是否在本轮补做测算并转空间跟踪，还是保留 legacy 只读。

5. **验收照片强制**：空间级验收是否强制至少 1 张附件。

6. **测算行单位与父材料单位一致性**：测算按父材料单位录入，若存在单位不一致（如 m² 与 张），需先统一，否则基线量不可加总。

### 20.16 来源口径纠正：父材料唯一，BOM 仅实时联查（V2.1 定稿）

> 本节纠正 V1 遗留的两处错误表述：**"BOM 为消项来源"** 与 **"历史以 BOM 快照为准"**。两者均作废。

1. **唯一来源是父材料**，不是 BOM 明细行，也不是 BOM 版本。`project_closeout_items.material_id` 指向 `Material_Compositions.parent_material_id` 对应的父级材料。
2. **位置与量只来自测算**（`Initial_Cost_Estimate_Detail`），落子项 `baseline_qty`；BOM 不提供位置、不提供消项数量。
3. **BOM 只被预测页按现值读取**（系数、材料量、人工、单价）；不进入消项表，不参与状态、完成率、验收。
4. **消项侧无任何 BOM 副本**：无 `snapshot`、无 `bom_version`、无冻结 JSON。追溯依据 = 父材料 + 子项基线量 / `baseline_at` + 测算行。
5. **BOM 变更只影响预测结果**，既不需要同步，也不需要版本迁移；不存在"BOM 升级导致消项失效"的路径。
6. **清单（bill）同样不是来源**：仅用于事后对比测算与消项范围、查漏项，属后续功能。

这条纠正在代码层的落点：`CloseoutService::createLocationsForItem()` 只读测算行、不读 BOM；事项唯一约束仍为 `(project_id, material_id)`，无 BOM 版本维度。



### 20.18 存量事项空间补齐（V2.1 增补，已实现）

背景：`project_closeout_locations` 是 V2 新增表，V1 时期建的事项从未生成空间行，因此界面显示 `空间 0/0`、无进度条。表结构不需要改动，缺的是数据。

1. **数据来源同一条口径**：按 `items.material_id` 取 `Initial_Cost_Estimate_Detail` 中该项目（经 `Buildings.project_id` 隔离）的行，一个 `building_id + floor_id + room_id` 一条空间，`baseline_qty` 为该空间 `estimated_quantity` 之和，`estimate_detail_id` 仅在单行空间可回指、多行合计置 NULL。
2. **继承而非新造**：`plan_id`、`planned_start_date` / `planned_end_date`、`responsible_supplier_id` / `responsible_user_id` 全部从所属事项继承；`baseline_at` 取执行时刻。
3. **幂等**：已有非 `cancelled` 空间的事项整条跳过；`uq_closeout_location_active`（`(item_id, eff_space_key)`）兜底防重。取消过的空间视为缺失，允许重新生成。
4. **五类不可补的情况分别回报**，不静默：`missing_estimates`（该材料在项目内 0 行测算，需先补测算）、`not_parent`（`Material_Compositions` 中该材料存在上级，旧数据可能按子材料建了事项，需按父材料重录）、`plan_inactive`（所属计划非进行中，与 `addLocations` 口径一致）、`closed_skipped`（已消项事项不补，否则会被木桶回算重新打开或凭空造验收证据）。
5. **不重算事项状态，空间初态镜像事项状态**：`ongoing` / `pending_accept` 的旧事项补出的空间为 `ongoing`（表示已开始，但不写 `submitted_at`，因为"申报"必须有真实提交时间）；`pending` 的补为 `pending`。进度仍只按已验收量算，历史进度不追认。
6. **可追溯**：一次补齐写一条汇总审计（`closeout.locations.backfill`，含条数与空间数）。
7. **入口**：工作台顶部「补齐存量空间」，仅在存在"无空间且未取消"的事项时出现；页面加载不写库。

### 20.19 责任班组（V2.1 增补，2026-11-27 业务确认，已实现）

背景：原设计把责任人指向系统账号（`responsible_user_id` → `Users`）。但 `Users` 里只有管理者账号（当前项目仅 1 个），一线执行主体是**施工班组**（`Labor_Crews`，由 labor-crew 模块维护，按项目隔离），因此"责任人"实际无法选择。

口径：

1. **责任主体 = 班组**：新增 `responsible_crew_id`（`project_closeout_items` 与 `project_closeout_locations` 各一列），外键指向 `Labor_Crews.crew_id`，只允许当前项目的**在用**班组（服务层按 `project_id + is_active = 1` 校验，跨项目/已归档直接 422）。
2. **旧字段保留不删**：`responsible_user_id` 留在表里承接 V1 数据，新写入只写 `responsible_crew_id`；展示时班组优先，班组为空才回落旧人名列，保证历史事项仍可读。
3. **空间继承**：新增/补建空间时 `responsible_crew_id` 从所属事项继承，与 `planned_*`、`responsible_supplier_id` 同一继承口径。
4. **展示统一为「班组（负责人）」**：服务层输出 `responsible_crew_name`、`responsible_crew_leader` 与合成字段 `responsible_label`，列表、详情、筛选下拉共用，避免各页各拼。
5. **筛选与批量**：工作台筛选键改为 `responsible_crew_id`（"全部班组"），批量条改为"批量责任班组"；`unassigned` 统计口径同步改为 `responsible_crew_id IS NULL`。
6. **迁移**：`202611270003_closeout_responsible_crew.sql` 只增列 + 索引 + 外键，不改 `Labor_Crews` 结构；已应用于测试库与真实库。

***

> 边界声明：§ 二十 为 V2.0 设计定稿（含 §20.16 来源口径纠正、§20.19 责任班组）。P1（两个迁移 + 服务层 + 测试）与 P2（工作台/详情界面 + 三个空间动作路由）已完成，迁移已应用于测试库 `project_o_test` 与真实库 `project_o`（迁移前已备份，迁移后另有完整快照 `project_o_after_closeout_v2_20261127.sql`，班组迁移前快照 `project_o_before_crew_20261127.sql`）。落地记录见 
>
> `34_Project_O_工程消项_V2.0_实施任务书.md`
>
>  分阶段落地。
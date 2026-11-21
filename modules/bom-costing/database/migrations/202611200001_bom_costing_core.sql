-- ============================================================================
-- Project O · BOM 与成本 V3.0 精简设计（24 号定稿，DR-1 / DR-2）
-- 8 张新建表：统一资源目录 / 项目成本价 / 组价头 / 资源行 / 当前态明细 /
--             费率配置 / 实际费用 / 分摊。
-- 通用约定：project_id NOT NULL ＋复合 FK；RESTRICT；UTC；定点计算禁 float。
--   金额 decimal(18,4)、费率 decimal(6,4)、数量 decimal(18,6)、系数 decimal(10,2)。
-- 闸门：本迁移只落结构与完整性约束；具体税率/抵扣/舍入路径未定案前，服务层不写数值公式。
-- 防重跑依赖 ProjectO_Migrations 台账（bin/install.php）。
-- ============================================================================

-- 0) Material_Compositions 复合唯一索引（DR-1）：供 cost_line_results 复合 FK 引用。
--    仅加索引，字段与业务语义零改动；可重复安全（存在性判断后再加）。
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Material_Compositions'
    AND INDEX_NAME = 'uq_composition_project_id'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `Material_Compositions`
     ADD UNIQUE KEY `uq_composition_project_id` (`project_id`, `composition_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ⑦ resource_catalog：统一资源目录（DR-2）。processing/mechanical/labor 合一，单表类型字段，
-- 非跨表多态；共享归属（B16）；不带价格（价格在 project_cost_prices）。
CREATE TABLE IF NOT EXISTS `resource_catalog` (
  `catalog_id` int NOT NULL AUTO_INCREMENT,
  `catalog_type` enum('processing','mechanical','labor') NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`catalog_id`),
  UNIQUE KEY `uq_catalog_type_name` (`catalog_type`, `name`),
  CONSTRAINT `fk_catalog_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑥ project_cost_prices：项目成本价（DR-2 后 aux_id / catalog_id 两类型化 FK，恰一）。
-- 目录项（加工/机械/人工）价格在项目内隔离；共享目录本身不带价格。
CREATE TABLE IF NOT EXISTS `project_cost_prices` (
  `price_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `unit_price` decimal(18,4) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'CNY',
  `pricing_unit` varchar(50) NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` enum('active','paused','expired') NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`price_id`),
  UNIQUE KEY `uq_project_cost_price_project` (`project_id`, `price_id`),
  -- 类型化唯一索引：NULL 不去重，避免跨类型误判。
  KEY `idx_cost_price_project_aux` (`project_id`, `aux_id`),
  KEY `idx_cost_price_project_catalog` (`project_id`, `catalog_id`),
  CONSTRAINT `chk_cost_price_target_one` CHECK (
    (`aux_id` IS NOT NULL AND `catalog_id` IS NULL)
    OR (`aux_id` IS NULL AND `catalog_id` IS NOT NULL)
  ),
  CONSTRAINT `chk_cost_price_unit_positive` CHECK (`unit_price` > 0),
  CONSTRAINT `fk_cost_price_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_aux` FOREIGN KEY (`project_id`, `aux_id`)
    REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_catalog` FOREIGN KEY (`catalog_id`)
    REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ① bom_costings：组价头。一父一份（F1）；status 业务确认 × calc_status 计算完整性双态分离；
-- version 为唯一并发判据（人工结构修改 +1 兼乐观锁；重算不改 version）。
CREATE TABLE IF NOT EXISTS `bom_costings` (
  `costing_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `parent_material_id` int NOT NULL,
  `status` enum('draft','confirmed') NOT NULL DEFAULT 'draft',
  `calc_status` enum('incomplete','calculable','complete') NOT NULL DEFAULT 'incomplete',
  `version` bigint NOT NULL DEFAULT 0,
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`costing_id`),
  UNIQUE KEY `uq_costing_project_parent` (`project_id`, `parent_material_id`),
  UNIQUE KEY `uq_costing_project_id` (`project_id`, `costing_id`),
  CONSTRAINT `fk_costing_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_parent_material` FOREIGN KEY (`project_id`, `parent_material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ③ bom_costing_resources：资源行（DR-2 后）。三类型化可空 FK ＋ CHECK 恰一＋类型匹配；
-- 无 unit/name 快照（实时读目录/材料表）；resource_key 生成列去重。
CREATE TABLE IF NOT EXISTS `bom_costing_resources` (
  `resource_line_id` int NOT NULL AUTO_INCREMENT,
  `costing_id` int NOT NULL,
  `project_id` int NOT NULL,
  `resource_type` enum('auxiliary','processing','mechanical','labor','parent_self') NOT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `material_id` int DEFAULT NULL,
  `resource_key` varchar(60) GENERATED ALWAYS AS (
    COALESCE(CAST(`aux_id` AS CHAR), CONCAT('c', CAST(`catalog_id` AS CHAR)), CONCAT('m', CAST(`material_id` AS CHAR)))
  ) STORED,
  `quantity` decimal(18,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resource_line_id`),
  UNIQUE KEY `uq_resource_line_project` (`project_id`, `resource_line_id`),
  UNIQUE KEY `uq_resource_line_unique` (`costing_id`, `resource_type`, `resource_key`),
  CONSTRAINT `chk_resource_target_one` CHECK (
    (`aux_id` IS NOT NULL AND `catalog_id` IS NULL AND `material_id` IS NULL)
    OR (`aux_id` IS NULL AND `catalog_id` IS NOT NULL AND `material_id` IS NULL)
    OR (`aux_id` IS NULL AND `catalog_id` IS NULL AND `material_id` IS NOT NULL)
  ),
  -- 类型与目标匹配：auxiliary→aux_id；processing/mechanical/labor→catalog_id 且类型一致；parent_self→material_id。
  CONSTRAINT `chk_resource_type_match` CHECK (
    (`resource_type` = 'auxiliary' AND `aux_id` IS NOT NULL)
    OR (`resource_type` IN ('processing','mechanical','labor') AND `catalog_id` IS NOT NULL)
    OR (`resource_type` = 'parent_self' AND `material_id` IS NOT NULL)
  ),
  CONSTRAINT `chk_resource_quantity_positive` CHECK (`quantity` > 0),
  CONSTRAINT `fk_resource_costing` FOREIGN KEY (`project_id`, `costing_id`)
    REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_aux` FOREIGN KEY (`project_id`, `aux_id`)
    REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_catalog` FOREIGN KEY (`catalog_id`)
    REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_material` FOREIGN KEY (`project_id`, `material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ④ cost_line_results：当前态明细。composition_id / resource_line_id 恰一（DR-1：组件结果直接引用
-- Material_Compositions.composition_id）；来源（报价/成本价/人工价）恰一；unit_price>0 禁零价。
CREATE TABLE IF NOT EXISTS `cost_line_results` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `costing_id` int NOT NULL,
  `project_id` int NOT NULL,
  `composition_id` int DEFAULT NULL,
  `resource_line_id` int DEFAULT NULL,
  `quantity` decimal(18,6) NOT NULL,
  `unit_price` decimal(18,4) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'CNY',
  `pricing_unit` varchar(50) NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `project_cost_price_id` int DEFAULT NULL,
  `manual_price` decimal(18,4) DEFAULT NULL,
  `manual_confirmed_by` int DEFAULT NULL,
  `source_tax_status` enum('taxable','exempt','unknown') NOT NULL DEFAULT 'unknown',
  `mgmt_rate` decimal(6,4) DEFAULT NULL,
  `profit_rate` decimal(6,4) DEFAULT NULL,
  `tax_rate` decimal(6,4) DEFAULT NULL,
  `trans_rate` decimal(6,4) DEFAULT NULL,
  `ins_rate` decimal(6,4) DEFAULT NULL,
  `subtotal` decimal(18,4) DEFAULT NULL,
  `mgmt_amount` decimal(18,4) DEFAULT NULL,
  `profit_amount` decimal(18,4) DEFAULT NULL,
  `tax_amount` decimal(18,4) DEFAULT NULL,
  `comprehensive` decimal(18,4) DEFAULT NULL,
  `formula_version` varchar(20) NOT NULL DEFAULT 'v3.0-structure',
  `rounding_spec_version` varchar(20) NOT NULL DEFAULT 'pending',
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calculated_by` int DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_result_project_id` (`project_id`, `result_id`),
  UNIQUE KEY `uq_result_composition` (`costing_id`, `composition_id`),
  UNIQUE KEY `uq_result_resource` (`costing_id`, `resource_line_id`),
  CONSTRAINT `chk_result_target_one` CHECK (
    (`composition_id` IS NOT NULL AND `resource_line_id` IS NULL)
    OR (`composition_id` IS NULL AND `resource_line_id` IS NOT NULL)
  ),
  -- 来源恰一：报价 / 项目成本价 / 人工确认价。
  CONSTRAINT `chk_result_source_one` CHECK (
    (`quotation_id` IS NOT NULL AND `project_cost_price_id` IS NULL AND `manual_price` IS NULL)
    OR (`quotation_id` IS NULL AND `project_cost_price_id` IS NOT NULL AND `manual_price` IS NULL)
    OR (`quotation_id` IS NULL AND `project_cost_price_id` IS NULL AND `manual_price` IS NOT NULL)
  ),
  CONSTRAINT `chk_result_unit_price_positive` CHECK (`unit_price` > 0),
  CONSTRAINT `fk_result_costing` FOREIGN KEY (`project_id`, `costing_id`)
    REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_composition` FOREIGN KEY (`project_id`, `composition_id`)
    REFERENCES `Material_Compositions` (`project_id`, `composition_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_resource` FOREIGN KEY (`project_id`, `resource_line_id`)
    REFERENCES `bom_costing_resources` (`project_id`, `resource_line_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_quotation` FOREIGN KEY (`project_id`, `quotation_id`)
    REFERENCES `Material_Quotations` (`project_id`, `quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_cost_price` FOREIGN KEY (`project_id`, `project_cost_price_id`)
    REFERENCES `project_cost_prices` (`project_id`, `price_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_confirmed_by` FOREIGN KEY (`manual_confirmed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_calculated_by` FOREIGN KEY (`calculated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑤ cost_settings：当前费率配置。每作用域一条（无版本链）；修改 UPDATE＋同事务审计。
-- building_key 生成列解决 NULL 楼栋的唯一约束（项目级 building_id 为 NULL）。
CREATE TABLE IF NOT EXISTS `cost_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `scope_type` enum('project','building') NOT NULL,
  `building_id` int DEFAULT NULL,
  `building_key` varchar(60) GENERATED ALWAYS AS (
    CASE WHEN `building_id` IS NULL THEN 'project' ELSE CONCAT('b', CAST(`building_id` AS CHAR)) END
  ) STORED,
  `mgmt_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `profit_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `trans_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `ins_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uq_settings_project_scope` (`project_id`, `building_key`),
  CONSTRAINT `chk_settings_scope_building` CHECK (
    (`scope_type` = 'project' AND `building_id` IS NULL)
    OR (`scope_type` = 'building' AND `building_id` IS NOT NULL)
  ),
  CONSTRAINT `fk_settings_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_building` FOREIGN KEY (`building_id`)
    REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑧ actual_cost_records：实际费用（DR-2 后）。三对象类型化 FK ＋严格绑定 CHECK；
-- normal/reversal/adjustment 自引用恰一；amount <> 0。组价重算链路永不读写本表。
CREATE TABLE IF NOT EXISTS `actual_cost_records` (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `building_id` int DEFAULT NULL,
  `cost_category` enum('material','auxiliary','processing','mechanical','labor','other') NOT NULL,
  `material_id` int DEFAULT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `cost_object_key` varchar(60) GENERATED ALWAYS AS (
    COALESCE(CONCAT('m', CAST(`material_id` AS CHAR)), CONCAT('a', CAST(`aux_id` AS CHAR)), CONCAT('c', CAST(`catalog_id` AS CHAR)))
  ) STORED,
  `description` text,
  `quantity` decimal(18,6) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(18,4) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL,
  `record_type` enum('normal','reversal','adjustment') NOT NULL DEFAULT 'normal',
  `reversal_of_record_id` int DEFAULT NULL,
  `adjustment_of_record_id` int DEFAULT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'CNY',
  `tax_status` enum('taxable','exempt','unknown') NOT NULL DEFAULT 'unknown',
  `occurred_at` date NOT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_ref` varchar(255) DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_actual_record_project` (`project_id`, `record_id`),
  KEY `idx_actual_record_project_category` (`project_id`, `cost_category`),
  CONSTRAINT `chk_actual_object_bind` CHECK (
    (`cost_category` = 'material' AND `material_id` IS NOT NULL AND `aux_id` IS NULL AND `catalog_id` IS NULL)
    OR (`cost_category` = 'auxiliary' AND `aux_id` IS NOT NULL AND `material_id` IS NULL AND `catalog_id` IS NULL)
    OR (`cost_category` IN ('processing','mechanical','labor') AND `catalog_id` IS NOT NULL AND `material_id` IS NULL AND `aux_id` IS NULL)
    OR (`cost_category` = 'other' AND `material_id` IS NULL AND `aux_id` IS NULL AND `catalog_id` IS NULL)
  ),
  CONSTRAINT `chk_actual_adjustment_one` CHECK (
    (`reversal_of_record_id` IS NULL AND `adjustment_of_record_id` IS NULL)
    OR (`reversal_of_record_id` IS NOT NULL AND `adjustment_of_record_id` IS NULL)
    OR (`reversal_of_record_id` IS NULL AND `adjustment_of_record_id` IS NOT NULL)
  ),
  CONSTRAINT `chk_actual_amount_nonzero` CHECK (`amount` <> 0),
  CONSTRAINT `fk_actual_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_building` FOREIGN KEY (`building_id`)
    REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_material` FOREIGN KEY (`project_id`, `material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_aux` FOREIGN KEY (`project_id`, `aux_id`)
    REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_catalog` FOREIGN KEY (`catalog_id`)
    REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_reversal_of` FOREIGN KEY (`project_id`, `reversal_of_record_id`)
    REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_adjustment_of` FOREIGN KEY (`project_id`, `adjustment_of_record_id`)
    REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⑨ actual_cost_allocations：分摊。一费可摊多组价；allocated_amount>0；Σ分摊≤可分摊净额（服务端锁内校验）。
CREATE TABLE IF NOT EXISTS `actual_cost_allocations` (
  `allocation_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `record_id` int NOT NULL,
  `costing_id` int NOT NULL,
  `allocated_amount` decimal(18,4) NOT NULL,
  `allocated_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  UNIQUE KEY `uq_allocation_record_costing` (`record_id`, `costing_id`),
  CONSTRAINT `chk_allocation_amount_positive` CHECK (`allocated_amount` > 0),
  CONSTRAINT `fk_allocation_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocation_record` FOREIGN KEY (`project_id`, `record_id`)
    REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocation_costing` FOREIGN KEY (`project_id`, `costing_id`)
    REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocated_by` FOREIGN KEY (`allocated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

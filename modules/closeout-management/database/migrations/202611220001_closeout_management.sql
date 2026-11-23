-- 工程消项管理 V1.0（依据 28 号数据库设计）。
-- 只新增消项域 5 张表，不改动 BOM、项目、用户、供应商等现有结构。
-- 本域不核销 BOM 数量，不回写 BOM 状态；Materials 为只读来源对象。
-- 同一项目同一 BOM 材料仅允许一条有效事项，由双 STORED 生成列与组合唯一索引强制。
-- 取消事项两生成列为 NULL，唯一索引因此允许多条已取消历史记录并存。
-- 防重复执行依赖 ProjectO_Migrations 台账（bin/install.php）。

CREATE TABLE IF NOT EXISTS `project_closeout_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_no` varchar(50) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','closed') NOT NULL DEFAULT 'draft',
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uq_closeout_plan_project_no` (`project_id`, `plan_no`),
  UNIQUE KEY `uq_closeout_plan_project_scope` (`plan_id`, `project_id`),
  KEY `idx_closeout_plan_project_status` (`project_id`, `status`),
  CONSTRAINT `fk_closeout_plan_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_reopener` FOREIGN KEY (`reopened_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_plan_dates` CHECK (`planned_start_date` IS NULL OR `planned_end_date` IS NULL OR `planned_start_date` <= `planned_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_closeout_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_no` varchar(50) NOT NULL,
  `material_id` int NOT NULL,
  `coverage_mode` enum('whole','split') NOT NULL DEFAULT 'whole',
  `coverage_root_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','ongoing','pending_accept','closed','cancelled') NOT NULL DEFAULT 'pending',
  `responsible_supplier_id` int DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `actual_complete_date` date DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `cancel_reason` text DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `snapshot` json NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eff_project_id` int GENERATED ALWAYS AS (CASE WHEN `status` <> 'cancelled' THEN `project_id` END) STORED,
  `eff_material_id` int GENERATED ALWAYS AS (CASE WHEN `status` <> 'cancelled' THEN `material_id` END) STORED,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_closeout_item_project_no` (`project_id`, `item_no`),
  UNIQUE KEY `uq_closeout_item_project_scope` (`item_id`, `project_id`),
  UNIQUE KEY `uq_closeout_item_active` (`eff_project_id`, `eff_material_id`),
  KEY `idx_closeout_item_plan` (`plan_id`, `project_id`),
  KEY `idx_closeout_item_project_status` (`project_id`, `status`),
  KEY `idx_closeout_item_material` (`project_id`, `material_id`),
  KEY `idx_closeout_item_responsible` (`project_id`, `responsible_user_id`),
  CONSTRAINT `fk_closeout_item_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_material` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_supplier` FOREIGN KEY (`responsible_supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_item_dates` CHECK (`planned_start_date` IS NULL OR `planned_end_date` IS NULL OR `planned_start_date` <= `planned_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_closeout_progress_logs` (
  `progress_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `progress_type` enum('note','submit') NOT NULL DEFAULT 'note',
  `progress_note` text NOT NULL,
  `recorded_by` int NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`progress_id`),
  KEY `idx_closeout_progress_item` (`item_id`, `project_id`),
  CONSTRAINT `fk_closeout_progress_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_user` FOREIGN KEY (`recorded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_closeout_acceptances` (
  `acceptance_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `result` enum('passed','rejected') NOT NULL,
  `remark` text DEFAULT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `accepted_by` int NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`acceptance_id`),
  KEY `idx_closeout_acceptance_item` (`item_id`, `project_id`),
  CONSTRAINT `fk_closeout_acceptance_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_user` FOREIGN KEY (`accepted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_closeout_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `item_id` int NOT NULL,
  `object_key` varchar(1024) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `content_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_closeout_image_item` (`item_id`, `project_id`),
  CONSTRAINT `fk_closeout_image_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_image_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

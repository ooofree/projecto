-- 工程消项 V2.0：空间子项表（父材料 × 楼栋/楼层/房间 × 基线量）。
-- 依据 28 号数据库设计 §二十。只新增消项域表，不改 BOM、材料测算、项目空间结构。
-- 子项来源为 Initial_Cost_Estimate_Detail，但只存 estimate_detail_id 作溯源，
-- 不建物理外键：测算行可编辑、可删除，建 RESTRICT 会反向锁死材料测算模块。
-- 有效子项唯一由单列 STORED 生成列 eff_space_key 强制；取消态置 NULL，
-- 使得同一空间的历史取消记录可多条并存。

CREATE TABLE IF NOT EXISTS `project_closeout_locations` (
  `location_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `estimate_detail_id` int DEFAULT NULL,
  `building_id` int NOT NULL,
  `floor_id` int DEFAULT NULL,
  `room_id` int DEFAULT NULL,
  `baseline_qty` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `baseline_at` datetime NOT NULL,
  `status` enum('pending','ongoing','pending_accept','closed','cancelled') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `responsible_supplier_id` int DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eff_space_key` varchar(80) GENERATED ALWAYS AS (
    CASE WHEN `status` <> 'cancelled'
    THEN CONCAT(
      CAST(`project_id` AS CHAR), '-',
      CAST(`item_id` AS CHAR), '-',
      CAST(`building_id` AS CHAR), '-',
      COALESCE(CAST(`floor_id` AS CHAR), '0'), '-',
      COALESCE(CAST(`room_id` AS CHAR), '0')
    )
    END) STORED,
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `uq_closeout_location_active` (`eff_space_key`),
  UNIQUE KEY `uq_closeout_location_scope` (`location_id`, `project_id`),
  KEY `idx_closeout_location_item` (`item_id`, `project_id`),
  KEY `idx_closeout_location_project_status` (`project_id`, `status`),
  KEY `idx_closeout_location_building` (`building_id`, `floor_id`, `room_id`),
  KEY `idx_closeout_location_estimate` (`estimate_detail_id`),
  KEY `idx_closeout_location_plan_date` (`project_id`, `status`, `planned_end_date`),
  KEY `idx_closeout_location_responsible` (`project_id`, `responsible_user_id`),
  CONSTRAINT `fk_closeout_location_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_building` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_floor` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_room` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_supplier` FOREIGN KEY (`responsible_supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_accepter` FOREIGN KEY (`accepted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_location_dates` CHECK (`planned_start_date` IS NULL OR `planned_end_date` IS NULL OR `planned_start_date` <= `planned_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

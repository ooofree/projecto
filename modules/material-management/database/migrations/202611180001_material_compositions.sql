-- 材料组成关系（BOM 式父—子单层）与送样要求。
-- 业务确认（2026-11-18）：定制材料为父项，默认无需送样；组成子材料各自独立送样。
-- 数量系数对齐旧 BOM_Components.quantity decimal(10,2)（每 1 父项单位所需子材料数量，
-- 单位取 Materials.unit）；人工/加工/费率不在本期，留待 BOM 与成本阶段映射
-- Internal_BOM + BOM_Components。防重跑依赖 ProjectO_Migrations 台账（bin/install.php）。

ALTER TABLE `Materials`
  ADD COLUMN `sample_required` enum('required','exempt') NOT NULL DEFAULT 'required' AFTER `default_quotation_id`;

CREATE TABLE IF NOT EXISTS `Material_Compositions` (
  `composition_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `parent_material_id` int NOT NULL,
  `child_material_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`composition_id`),
  UNIQUE KEY `uq_composition_project_parent_child` (`project_id`, `parent_material_id`, `child_material_id`),
  KEY `idx_composition_project_child` (`project_id`, `child_material_id`),
  CONSTRAINT `fk_composition_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_composition_parent_o` FOREIGN KEY (`project_id`, `parent_material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_composition_child_o` FOREIGN KEY (`project_id`, `child_material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_composition_not_self` CHECK (`parent_material_id` <> `child_material_id`),
  CONSTRAINT `chk_composition_quantity_positive` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
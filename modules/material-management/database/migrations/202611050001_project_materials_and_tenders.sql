-- Existing global catalog rows remain unassigned (project_id NULL), and ownership is not guessed.
ALTER TABLE `Material_Categories`
  ADD COLUMN `project_id` int DEFAULT NULL AFTER `category_id`,
  ADD UNIQUE KEY `uq_material_category_project_id` (`project_id`, `category_id`),
  ADD KEY `idx_material_category_project_parent` (`project_id`, `parent_category_id`),
  ADD CONSTRAINT `fk_material_category_project_o`
    FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT;

ALTER TABLE `Material_Categories`
  ADD CONSTRAINT `fk_material_category_parent_project_o`
    FOREIGN KEY (`project_id`, `parent_category_id`)
    REFERENCES `Material_Categories` (`project_id`, `category_id`) ON DELETE RESTRICT;

ALTER TABLE `Materials`
  ADD COLUMN `project_id` int DEFAULT NULL AFTER `material_id`,
  DROP INDEX `uq_material_code`,
  ADD UNIQUE KEY `uq_material_project_code` (`project_id`, `material_code`),
  ADD UNIQUE KEY `uq_material_project_id` (`project_id`, `material_id`),
  ADD KEY `idx_material_project_category` (`project_id`, `category_id`),
  ADD KEY `idx_material_project_default_quotation` (`project_id`, `material_id`, `default_quotation_id`),
  ADD CONSTRAINT `fk_material_project_o`
    FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_material_project_category_o`
    FOREIGN KEY (`project_id`, `category_id`)
    REFERENCES `Material_Categories` (`project_id`, `category_id`) ON DELETE RESTRICT;

ALTER TABLE `Material_Quotations`
  ADD COLUMN `project_id` int DEFAULT NULL AFTER `quotation_id`,
  ADD UNIQUE KEY `uq_quotation_project_id` (`project_id`, `quotation_id`),
  ADD KEY `idx_quotation_project_material_date` (`project_id`, `material_id`, `quotation_date`),
  ADD UNIQUE KEY `uq_quotation_project_material_id` (`project_id`, `material_id`, `quotation_id`),
  ADD CONSTRAINT `fk_quotation_project_material_o`
    FOREIGN KEY (`project_id`, `material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_quotation_project_o`
    FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT;

ALTER TABLE `Materials`
  ADD CONSTRAINT `fk_material_project_default_quotation_o`
    FOREIGN KEY (`project_id`, `material_id`, `default_quotation_id`)
    REFERENCES `Material_Quotations` (`project_id`, `material_id`, `quotation_id`) ON DELETE RESTRICT;

ALTER TABLE `Material_Mappings`
  ADD COLUMN `project_id` int DEFAULT NULL AFTER `mapping_id`,
  ADD KEY `idx_material_mapping_project_material` (`project_id`, `material_id`),
  ADD CONSTRAINT `fk_material_mapping_project_material_o`
    FOREIGN KEY (`project_id`, `material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_material_mapping_project_quotation_o`
    FOREIGN KEY (`project_id`, `quotation_id`)
    REFERENCES `Material_Quotations` (`project_id`, `quotation_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_material_mapping_project_o`
    FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT;

ALTER TABLE `Auxiliary_Materials`
  ADD COLUMN `project_id` int DEFAULT NULL AFTER `aux_id`,
  ADD UNIQUE KEY `uq_auxiliary_project_id` (`project_id`, `aux_id`),
  ADD KEY `idx_auxiliary_project_name` (`project_id`, `aux_name`),
  ADD CONSTRAINT `fk_auxiliary_project_o`
    FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS `Tender_List` (
  `tender_item_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `material_id` int NOT NULL,
  `tender_quantity` decimal(12,2) NOT NULL,
  `tender_unit_price` decimal(16,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'CNY',
  `status` enum('pending','approved','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tender_item_id`),
  KEY `idx_tender_project_material` (`project_id`, `material_id`),
  CONSTRAINT `fk_tender_project_material_o`
    FOREIGN KEY (`project_id`, `material_id`)
    REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

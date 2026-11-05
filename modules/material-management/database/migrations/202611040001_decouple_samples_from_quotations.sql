ALTER TABLE `Material_Samples`
  ADD COLUMN `material_id` int DEFAULT NULL AFTER `project_id`,
  ADD COLUMN `supplier_id` int DEFAULT NULL AFTER `material_id`;

UPDATE `Material_Samples` s
JOIN `Material_Quotations` q ON q.`quotation_id` = s.`quotation_id`
JOIN `Supplier_Materials` sm ON sm.`supplier_material_id` = q.`supplier_material_id`
SET s.`material_id` = q.`material_id`,
    s.`supplier_id` = sm.`supplier_id`;

ALTER TABLE `Material_Samples`
  MODIFY COLUMN `quotation_id` int DEFAULT NULL,
  MODIFY COLUMN `material_id` int NOT NULL,
  MODIFY COLUMN `supplier_id` int NOT NULL,
  ADD KEY `idx_sample_material` (`material_id`),
  ADD KEY `idx_sample_supplier` (`supplier_id`),
  ADD CONSTRAINT `fk_sample_material_o`
    FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_sample_supplier_o`
    FOREIGN KEY (`supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT;

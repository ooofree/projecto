-- ============================================================================
-- 3 张既有表扩展（DR-1 / V3.0 §2 扩展项）。契约字段与业务语义零改动，仅补列/索引。
-- 1) Material_Quotations：valid_from/to、status、tax_status 三态、pricing_unit。
-- 2) audit_logs：project_id / entity_type / entity_id（JSON details 复用现有 details 文本列）。
-- 3) Material_Compositions：UNIQUE(project_id, composition_id)，供 cost_line_results 复合 FK。
-- 可重复安全：列/索引用 information_schema 存在性判断后再加（MySQL 无 ADD COLUMN IF NOT EXISTS）。
-- ============================================================================

-- 1) 报价有效性与税口径扩展 ----------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Material_Quotations' AND COLUMN_NAME = 'valid_from'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `Material_Quotations`
     ADD COLUMN `valid_from` date DEFAULT NULL AFTER `quotation_date`,
     ADD COLUMN `valid_to` date DEFAULT NULL AFTER `valid_from`,
     ADD COLUMN `status` enum(''active'',''paused'',''expired'') NOT NULL DEFAULT ''active'' AFTER `valid_to`,
     ADD COLUMN `tax_status` enum(''taxable'',''exempt'',''unknown'') NOT NULL DEFAULT ''unknown'' AFTER `status`,
     ADD COLUMN `pricing_unit` varchar(50) DEFAULT NULL AFTER `tax_status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) 审计项目归属扩展 ---------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'project_id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `audit_logs`
     ADD COLUMN `project_id` int DEFAULT NULL AFTER `user_id`,
     ADD COLUMN `entity_type` varchar(100) DEFAULT NULL AFTER `project_id`,
     ADD COLUMN `entity_id` int DEFAULT NULL AFTER `entity_type`,
     ADD KEY `idx_audit_logs_project_entity` (`project_id`, `entity_type`, `entity_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) 组成复合唯一索引已在核心迁移 202611200001 开头创建（供结果表复合 FK）；
--    本文件保留报价有效性与审计归属两项扩展。

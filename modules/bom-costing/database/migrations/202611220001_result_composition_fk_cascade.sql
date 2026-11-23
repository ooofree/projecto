-- ============================================================================
-- 修复：删组成边被 cost_line_results 外键反锁（MySQL 1451）。
--
-- cost_line_results 是“当前态”派生数据（DR-1：Material_Compositions 的边是唯一真身）。
-- 按设计“边删→重算清理对应结果行”，但 RESTRICT 让“先删边”这一步直接失败：
--   Cannot delete or update a parent row: a foreign key constraint fails
--   (`project_o`.`cost_line_results`, CONSTRAINT `fk_result_composition` ... ON DELETE RESTRICT)
-- 现象即：编辑组价 / 材料管理里删除子材料，请求 500“系统发生错误”。
--
-- 改为 ON DELETE CASCADE：边被删时派生结果行随边清理，随后 recalc 按剩余结构重建。
-- 结果行只承载单价/来源/数量，审计信息在 audit_logs，删边本身有 bom.composition.delete 记录。
--
-- 可重复安全：先按 information_schema 判断约束是否存在，存在则 DROP，缺失则 ADD。
-- ============================================================================

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_line_results'
    AND CONSTRAINT_NAME = 'fk_result_composition'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists > 0,
  'ALTER TABLE `cost_line_results` DROP FOREIGN KEY `fk_result_composition`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cost_line_results'
    AND CONSTRAINT_NAME = 'fk_result_composition'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `cost_line_results` ADD CONSTRAINT `fk_result_composition` FOREIGN KEY (`project_id`, `composition_id`) REFERENCES `Material_Compositions` (`project_id`, `composition_id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

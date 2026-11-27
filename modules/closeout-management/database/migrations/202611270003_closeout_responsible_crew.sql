-- 消项责任人改为按「施工班组」指派（2026-11-27 业务确认）。
-- 口径：现场责任落在班组（Labor_Crews，按项目隔离），不再指向单个系统用户——
-- 系统用户表里只有管理者账号，一线执行主体是班组。
-- 旧字段 responsible_user_id 保留（历史数据与兼容），新写入以 responsible_crew_id 为准；
-- 空间默认继承所属事项的班组。
-- 说明：Labor_Crews 由 labor-crew 模块创建，本迁移只新增列与索引，不改该表结构。

ALTER TABLE `project_closeout_items`
  ADD COLUMN `responsible_crew_id` int DEFAULT NULL COMMENT '责任班组，取自 Labor_Crews，按项目隔离' AFTER `responsible_user_id`,
  ADD KEY `idx_closeout_item_crew` (`project_id`, `responsible_crew_id`),
  ADD CONSTRAINT `fk_closeout_item_crew` FOREIGN KEY (`responsible_crew_id`) REFERENCES `Labor_Crews` (`crew_id`) ON DELETE RESTRICT;

ALTER TABLE `project_closeout_locations`
  ADD COLUMN `responsible_crew_id` int DEFAULT NULL COMMENT '责任班组，创建空间时继承事项' AFTER `responsible_user_id`,
  ADD KEY `idx_closeout_location_crew` (`project_id`, `responsible_crew_id`),
  ADD CONSTRAINT `fk_closeout_location_crew` FOREIGN KEY (`responsible_crew_id`) REFERENCES `Labor_Crews` (`crew_id`) ON DELETE RESTRICT;

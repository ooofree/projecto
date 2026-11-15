-- 问题登记的「位置」改为从项目空间选择：新增结构化空间引用列。
-- 旧 location 文本列保留不动，历史手工填写的位置继续可读、可导出；
-- 新记录写入空间类型与各层级外键，展示名称由空间目录实时解析。
-- 四个外键均为可空单列引用（楼层/区域/房间表无 project_id，无法建复合外键），
-- 项目归属由服务端按当前项目校验；外键同时让空间删除时自动识别问题引用，
-- 被引用的空间转为归档而非硬删除。
-- 本迁移只新增可空列与索引，不修改或删除任何已有数据；
-- 由 ProjectO_Migrations 记录执行状态，重复运行会被安装器跳过；
-- 手工重复执行会因列已存在而明确报错。

ALTER TABLE `document_issues`
  ADD COLUMN `location_type` enum('building','floor','area','room') DEFAULT NULL COMMENT '所选空间类型；NULL 表示未选择或历史文本位置' AFTER `location`,
  ADD COLUMN `location_building_id` int DEFAULT NULL COMMENT '所属楼栋，用于项目归属与删除引用检查' AFTER `location_type`,
  ADD COLUMN `location_floor_id` int DEFAULT NULL COMMENT '所选楼层或其父楼层' AFTER `location_building_id`,
  ADD COLUMN `location_area_id` int DEFAULT NULL COMMENT '所选施工区域' AFTER `location_floor_id`,
  ADD COLUMN `location_room_id` int DEFAULT NULL COMMENT '所选房间' AFTER `location_area_id`,
  ADD KEY `idx_doc_issue_location_building` (`location_building_id`),
  ADD KEY `idx_doc_issue_location_floor` (`location_floor_id`),
  ADD KEY `idx_doc_issue_location_area` (`location_area_id`),
  ADD KEY `idx_doc_issue_location_room` (`location_room_id`),
  ADD CONSTRAINT `fk_doc_issue_location_building` FOREIGN KEY (`location_building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_doc_issue_location_floor` FOREIGN KEY (`location_floor_id`) REFERENCES `Building_Floors` (`floor_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_doc_issue_location_area` FOREIGN KEY (`location_area_id`) REFERENCES `Construction_Areas` (`area_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_doc_issue_location_room` FOREIGN KEY (`location_room_id`) REFERENCES `Rooms` (`room_id`) ON DELETE RESTRICT;

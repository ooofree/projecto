-- 施工班组联系人公开分享令牌。
-- 分享是显式 opt-in 的对外只读视图：令牌为 32 位十六进制随机串（128 位熵），
-- 知道链接即可免登录查看，可随时停用。每次开启分享新增一行；停用只置 revoked_at，
-- 历史行全部保留（不删除、不改写旧令牌），重新分享生成新令牌，旧链接保持失效。
-- 「每个项目至多一枚有效令牌」由服务层在事务内保证（先查有效行再插入）。
-- 公开页只展示与导出同口径的列（工种、班组、负责人姓名、电话、备注），
-- 身份证号、微信号、紧急联系人不进入公开视图；本表不保存任何联系人内容。
-- 本迁移只新建表，不改动旧系统任何表；
-- 由 ProjectO_Migrations 记录执行状态，CREATE TABLE IF NOT EXISTS 手工重复执行安全。

CREATE TABLE IF NOT EXISTS `Labor_Crew_Shares` (
  `share_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL COMMENT '分享归属项目；公开页只能读取该项目的在用班组',
  `token` char(32) NOT NULL COMMENT '公开链接令牌，32 位十六进制随机串',
  `created_by` int NOT NULL COMMENT '开启分享的用户',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` timestamp NULL DEFAULT NULL COMMENT '停用时间；非空即失效，保留记录不删除',
  `revoked_by` int DEFAULT NULL COMMENT '停用分享的用户',
  PRIMARY KEY (`share_id`),
  UNIQUE KEY `uq_crew_share_token` (`token`),
  KEY `idx_crew_share_project_revoked` (`project_id`, `revoked_at`),
  CONSTRAINT `fk_crew_shares_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crew_shares_created_by_o` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crew_shares_revoked_by_o` FOREIGN KEY (`revoked_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

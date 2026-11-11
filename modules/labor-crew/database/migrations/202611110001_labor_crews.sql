-- 施工班组联系人与工种目录。
-- 归属口径（已与业务确认）：班组只对它服务的项目负责，因此 Labor_Crews.project_id 非空，
-- 每个项目的班组互相隔离；工种目录 Labor_Crew_Types 是跨项目共享的轻量主数据（同 Brands/Suppliers）。
-- 旧系统没有班组实体（Labor_Types 是工种单价目录，Labor_Tasks 是劳务定额），
-- 本迁移只新建表，不复制旧表数据，也不改动旧系统任何表。
-- 身份证号属个人敏感信息：库内只保存 AES-256-GCM 密文与 SHA-256 加盐指纹，不保存明文；
-- 密文与指纹不得进入日志、导出文件或审计明细。页面默认只显示脱敏号码，
-- 完整号码需要额外的 view_labor_crew_id_numbers 权限。

CREATE TABLE IF NOT EXISTS `Labor_Crew_Types` (
  `crew_type_id` int NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL COMMENT '工种名称，如钢筋、木工、水电安装',
  `sort_order` int NOT NULL DEFAULT 0 COMMENT '列表排序，值小者在前',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`crew_type_id`),
  UNIQUE KEY `uq_crew_type_name` (`type_name`),
  KEY `idx_crew_type_sort` (`sort_order`, `crew_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Labor_Crews` (
  `crew_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL COMMENT '归属项目；班组只对该项目负责',
  `crew_type_id` int NOT NULL COMMENT '所属工种',
  `crew_name` varchar(255) NOT NULL COMMENT '班组名称',
  `leader_name` varchar(100) NOT NULL COMMENT '负责人姓名',
  `leader_phone` varchar(30) NOT NULL COMMENT '负责人手机号，可含 +86 前缀',
  `wechat_id` varchar(100) DEFAULT NULL COMMENT '微信号',
  `id_card_number_encrypted` text COMMENT '身份证号 AES-256-GCM 密文，禁止存明文',
  `id_card_fingerprint` char(64) DEFAULT NULL COMMENT '身份证号 SHA-256 加盐指纹，用于同项目查重',
  `emergency_contact_name` varchar(100) DEFAULT NULL COMMENT '紧急联系人姓名',
  `emergency_contact_phone` varchar(30) DEFAULT NULL COMMENT '紧急联系人电话',
  `notes` varchar(2000) DEFAULT NULL COMMENT '备注：常驻区域、擅长工艺等',
  `is_active` tinyint NOT NULL DEFAULT 1 COMMENT '1 在用，0 已归档；归档保留数据',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`crew_id`),
  UNIQUE KEY `uq_crew_id_project` (`crew_id`, `project_id`),
  UNIQUE KEY `uq_crew_project_fingerprint` (`project_id`, `id_card_fingerprint`),
  KEY `idx_crew_project_active_type` (`project_id`, `is_active`, `crew_type_id`),
  KEY `idx_crew_project_name` (`project_id`, `crew_name`),
  CONSTRAINT `fk_crews_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crews_type_o` FOREIGN KEY (`crew_type_id`) REFERENCES `Labor_Crew_Types` (`crew_type_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 常用工种种子数据。INSERT IGNORE 依赖 uq_crew_type_name，重复执行安全。
INSERT IGNORE INTO `Labor_Crew_Types` (`type_name`, `sort_order`) VALUES
  ('土建', 10),
  ('钢筋', 20),
  ('木工', 30),
  ('泥瓦', 40),
  ('混凝土', 50),
  ('脚手架', 60),
  ('防水', 70),
  ('水电安装', 80),
  ('暖通', 90),
  ('消防', 100),
  ('装饰装修', 110),
  ('幕墙', 120),
  ('门窗', 130),
  ('油漆涂料', 140),
  ('拆除', 150);

CREATE TABLE IF NOT EXISTS `project_foundation_archive_records` (
  `archive_id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(20) NOT NULL,
  `entity_id` int NOT NULL,
  `project_id` int NOT NULL,
  `archived_by` int NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archive_id`),
  UNIQUE KEY `uq_project_foundation_archive_entity` (`entity_type`, `entity_id`),
  KEY `idx_project_foundation_archive_project` (`project_id`, `entity_type`),
  CONSTRAINT `fk_project_foundation_archive_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_project_foundation_archive_user` FOREIGN KEY (`archived_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

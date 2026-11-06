CREATE TABLE IF NOT EXISTS `document_issue_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `issue_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `content_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_document_issue_image_project` (`project_id`,`issue_id`),
  CONSTRAINT `fk_document_issue_image_issue` FOREIGN KEY (`issue_id`,`project_id`) REFERENCES `document_issues` (`issue_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_document_issue_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `rfi_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `content_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_document_rfi_image_project` (`project_id`,`rfi_id`),
  CONSTRAINT `fk_document_rfi_image_rfi` FOREIGN KEY (`rfi_id`,`project_id`) REFERENCES `document_rfis` (`rfi_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_document_rfi_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

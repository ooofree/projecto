CREATE TABLE IF NOT EXISTS `document_issues` (
  `issue_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `description` text NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','in_review','resolved','closed') NOT NULL DEFAULT 'open',
  `location` varchar(255) DEFAULT NULL,
  `reported_by` int NOT NULL,
  `reported_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolution_result` text DEFAULT NULL,
  `verification_status` enum('pending','passed','not_required') NOT NULL DEFAULT 'pending',
  `verification_note` text DEFAULT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`issue_id`),
  UNIQUE KEY `uq_doc_issue_project` (`issue_id`,`project_id`),
  KEY `idx_doc_issue_project_status` (`project_id`,`status`,`updated_at`),
  CONSTRAINT `fk_doc_issue_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_reporter` FOREIGN KEY (`reported_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_issue_status_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `transition_data` text DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_issue_history` (`project_id`,`issue_id`,`changed_at`),
  CONSTRAINT `fk_doc_issue_history_issue` FOREIGN KEY (`issue_id`,`project_id`) REFERENCES `document_issues` (`issue_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_issue_follow_ups` (
  `follow_up_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int NOT NULL,
  `completed_by` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follow_up_id`),
  UNIQUE KEY `uq_doc_issue_follow_up_project` (`follow_up_id`,`project_id`),
  KEY `idx_doc_issue_follow_ups` (`project_id`,`issue_id`,`is_completed`),
  CONSTRAINT `fk_doc_issue_follow_up_issue` FOREIGN KEY (`issue_id`,`project_id`) REFERENCES `document_issues` (`issue_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_completer` FOREIGN KEY (`completed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_issue_follow_up_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `follow_up_id` int NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_issue_follow_up_history` (`project_id`,`follow_up_id`,`changed_at`),
  CONSTRAINT `fk_doc_issue_follow_up_history_item` FOREIGN KEY (`follow_up_id`,`project_id`) REFERENCES `document_issue_follow_ups` (`follow_up_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_number_sequences` (
  `calendar_year` smallint NOT NULL,
  `last_number` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`calendar_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `document_rfis` (
  `rfi_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `primary_issue_id` int NOT NULL,
  `rfi_number` varchar(50) NOT NULL,
  `assigned_to` int NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `due_date` datetime NOT NULL,
  `description` text NOT NULL,
  `status` enum('draft','submitted','under_review','responded','closed') NOT NULL DEFAULT 'draft',
  `submitted_by` int NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_text` text DEFAULT NULL,
  `response_complete` tinyint(1) NOT NULL DEFAULT 0,
  `evidence_required` tinyint(1) NOT NULL DEFAULT 0,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rfi_id`),
  UNIQUE KEY `uq_doc_rfi_number` (`rfi_number`),
  UNIQUE KEY `uq_doc_rfi_project` (`rfi_id`,`project_id`),
  KEY `idx_doc_rfi_project_status` (`project_id`,`status`,`updated_at`),
  KEY `idx_doc_rfi_primary_issue` (`project_id`,`primary_issue_id`),
  CONSTRAINT `fk_doc_rfi_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_primary_issue` FOREIGN KEY (`primary_issue_id`,`project_id`) REFERENCES `document_issues` (`issue_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_related_issues` (
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rfi_id`,`issue_id`),
  KEY `idx_doc_rfi_related_issue` (`project_id`,`issue_id`),
  CONSTRAINT `fk_doc_rfi_related_rfi` FOREIGN KEY (`rfi_id`,`project_id`) REFERENCES `document_rfis` (`rfi_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_related_issue` FOREIGN KEY (`issue_id`,`project_id`) REFERENCES `document_issues` (`issue_id`,`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_status_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `transition_data` text DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_rfi_history` (`project_id`,`rfi_id`,`changed_at`),
  CONSTRAINT `fk_doc_rfi_history_rfi` FOREIGN KEY (`rfi_id`,`project_id`) REFERENCES `document_rfis` (`rfi_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_follow_ups` (
  `follow_up_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int NOT NULL,
  `completed_by` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follow_up_id`),
  UNIQUE KEY `uq_doc_rfi_follow_up_project` (`follow_up_id`,`project_id`),
  KEY `idx_doc_rfi_follow_ups` (`project_id`,`rfi_id`,`is_completed`),
  CONSTRAINT `fk_doc_rfi_follow_up_rfi` FOREIGN KEY (`rfi_id`,`project_id`) REFERENCES `document_rfis` (`rfi_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_completer` FOREIGN KEY (`completed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_rfi_follow_up_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `follow_up_id` int NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `evidence_reference` varchar(1000) DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_rfi_follow_up_history` (`project_id`,`follow_up_id`,`changed_at`),
  CONSTRAINT `fk_doc_rfi_follow_up_history_item` FOREIGN KEY (`follow_up_id`,`project_id`) REFERENCES `document_rfi_follow_ups` (`follow_up_id`,`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

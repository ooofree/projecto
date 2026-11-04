ALTER TABLE `document_issues`
  ADD COLUMN `issue_number` varchar(50) DEFAULT NULL AFTER `project_id`,
  ADD UNIQUE KEY `uq_doc_issue_number` (`project_id`,`issue_number`);

CREATE TABLE IF NOT EXISTS `document_number_sequences` (
  `project_id` int NOT NULL,
  `document_type` varchar(10) NOT NULL,
  `last_number` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`project_id`,`document_type`),
  CONSTRAINT `fk_doc_number_sequence_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

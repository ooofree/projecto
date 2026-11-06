UPDATE `document_issues` SET `reported_at` = DATE_SUB(`reported_at`, INTERVAL 8 HOUR) WHERE `reported_at` IS NOT NULL;
UPDATE `document_issues` SET `closed_at` = DATE_SUB(`closed_at`, INTERVAL 8 HOUR) WHERE `closed_at` IS NOT NULL;
UPDATE `document_rfis` SET `due_date` = DATE_SUB(`due_date`, INTERVAL 8 HOUR) WHERE `due_date` IS NOT NULL;
UPDATE `document_rfis` SET `submitted_at` = DATE_SUB(`submitted_at`, INTERVAL 8 HOUR) WHERE `submitted_at` IS NOT NULL;
UPDATE `document_rfis` SET `responded_at` = DATE_SUB(`responded_at`, INTERVAL 8 HOUR) WHERE `responded_at` IS NOT NULL;
UPDATE `document_rfis` SET `closed_at` = DATE_SUB(`closed_at`, INTERVAL 8 HOUR) WHERE `closed_at` IS NOT NULL;
UPDATE `document_issue_follow_ups` SET `completed_at` = DATE_SUB(`completed_at`, INTERVAL 8 HOUR) WHERE `completed_at` IS NOT NULL;
UPDATE `document_rfi_follow_ups` SET `completed_at` = DATE_SUB(`completed_at`, INTERVAL 8 HOUR) WHERE `completed_at` IS NOT NULL;

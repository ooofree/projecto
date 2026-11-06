ALTER TABLE `Users` ADD COLUMN `timezone` varchar(64) DEFAULT NULL AFTER `country_code`;
INSERT IGNORE INTO `System_Settings` (`setting_key`, `setting_value`) VALUES ('timezone', 'Asia/Shanghai');
UPDATE `Roles` SET `created_at` = DATE_SUB(`created_at`, INTERVAL 8 HOUR) WHERE `created_at` IS NOT NULL;

-- 姓名/昵称与登录账号分离：username 继续承担英文登录名，display_name 记录中文姓名。
-- 允许为空；为空时界面回退显示 username。
ALTER TABLE `Users` ADD COLUMN `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `username`;

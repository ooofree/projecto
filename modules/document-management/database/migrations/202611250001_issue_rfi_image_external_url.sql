-- 问题与 RFI 图片支持 https 外链：只保存地址，不下载、不占用对象存储。
-- object_key 改为可空：外链记录留空，读取时直接使用 external_url。
-- content_type 写 image/external、file_size 写 0，便于区分两种来源。
-- 本迁移只新增可空列并放宽 object_key 约束，不修改或删除任何已有数据；
-- 由 ProjectO_Migrations 记录执行状态，重复运行会被安装器跳过。

ALTER TABLE `document_issue_images`
  MODIFY COLUMN `object_key` varchar(1024) NULL COMMENT '私有对象存储键；外链图片为 NULL',
  ADD COLUMN `external_url` varchar(1024) DEFAULT NULL COMMENT 'https 外链图片地址；有值时不再读取对象存储' AFTER `object_key`;

ALTER TABLE `document_rfi_images`
  MODIFY COLUMN `object_key` varchar(1024) NULL COMMENT '私有对象存储键；外链图片为 NULL',
  ADD COLUMN `external_url` varchar(1024) DEFAULT NULL COMMENT 'https 外链图片地址；有值时不再读取对象存储' AFTER `object_key`;

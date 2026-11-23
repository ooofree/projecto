-- 工程消项 V1.1：只去掉创建时 BOM 快照，材料与组成改为实时联查。
-- 进度日志与验收记录继续保留，作为业务历史，不并入事项行。
-- 执行本迁移前已在 /tmp 生成只读 JSON 备份。

ALTER TABLE `project_closeout_items`
  DROP COLUMN `snapshot`;

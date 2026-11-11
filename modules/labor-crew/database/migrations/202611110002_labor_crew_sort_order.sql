-- 施工班组列表支持手动排序（列表与导出的先后顺序）。
-- 班组按 sort_order 升序排列，值小者在前；默认 0，已有记录保持原有
-- 「工种排序 → 工种名称 → 班组名称」的展示顺序，不会因升级改变。
-- 本迁移只新增列，不修改或删除任何已有数据；由 ProjectO_Migrations 记录执行状态，
-- 重复运行会被安装器跳过；手工重复执行会因列已存在而明确报错。

ALTER TABLE `Labor_Crews`
  ADD COLUMN `sort_order` int NOT NULL DEFAULT 0 COMMENT '列表排序，值小者在前；0 为默认' AFTER `is_active`;

-- 位置选择改为「搜索 + 树浏览 + 最近使用」后，空间只能定位到楼栋/楼层/区域/房间，
-- 而现场问题常发生在轴线、前室、管井这类没有独立空间记录的部位。
-- 新增 location_note「位置补充说明」，承接这部分精度：
--   位置 = 结构化空间引用（或历史文本） + 可选补充说明。
-- 历史记录的 location 文本保持不动；新记录的补充说明为空，不猜测填充。
-- 本迁移只新增一个可空列，不修改或删除任何已有数据；
-- 由 ProjectO_Migrations 记录执行状态，重复运行会被安装器跳过；
-- 手工重复执行会因列已存在而明确报错。

ALTER TABLE `document_issues`
  ADD COLUMN `location_note` varchar(255) DEFAULT NULL COMMENT '位置补充说明，如轴线、部位；与所选空间配合使用' AFTER `location_room_id`;

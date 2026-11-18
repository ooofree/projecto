-- 送样要求人工设置持久化标记（2026-11-18 验收修复）。
-- 首次组成自动翻转为无需送样后，人工改回需送样会置 explicit=1；
-- 此后即使删除全部组成再重新添加，也不再覆盖人工设置。
-- 存量数据 backfill：已有组成子项且当前为需送样的材料，说明自动翻转后
-- 被人工改回（或人工先行设置），一律视为显式决定，保护既有选择。
-- 不改动 Material_Compositions 表结构；防重跑依赖 ProjectO_Migrations 台账。

ALTER TABLE `Materials`
  ADD COLUMN `sample_requirement_explicit` tinyint(1) NOT NULL DEFAULT 0 AFTER `sample_required`;

UPDATE `Materials` m
SET m.`sample_requirement_explicit` = 1
WHERE m.`sample_required` = 'required'
  AND EXISTS (
    SELECT 1 FROM `Material_Compositions` cc
    WHERE cc.`project_id` = m.`project_id` AND cc.`parent_material_id` = m.`material_id`
  );
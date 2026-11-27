-- 工程消项 V2.0：验收、进度、附件下沉到空间子项。
-- location_id 允许为 NULL：V1 历史记录无法反推空间，保持只读可追溯；
-- 新记录由应用层强制填写 location_id。
-- 复合外键 (location_id, project_id) -> project_closeout_locations(location_id, project_id)，
-- 保证不产生跨项目悬挂的子记录。

ALTER TABLE `project_closeout_acceptances`
  ADD COLUMN `location_id` int DEFAULT NULL AFTER `item_id`,
  ADD KEY `idx_closeout_acceptance_location` (`location_id`, `project_id`),
  ADD CONSTRAINT `fk_closeout_acceptance_location`
    FOREIGN KEY (`location_id`, `project_id`)
    REFERENCES `project_closeout_locations` (`location_id`, `project_id`)
    ON DELETE RESTRICT;

ALTER TABLE `project_closeout_progress_logs`
  ADD COLUMN `location_id` int DEFAULT NULL AFTER `item_id`,
  ADD KEY `idx_closeout_progress_location` (`location_id`, `project_id`),
  ADD CONSTRAINT `fk_closeout_progress_location`
    FOREIGN KEY (`location_id`, `project_id`)
    REFERENCES `project_closeout_locations` (`location_id`, `project_id`)
    ON DELETE RESTRICT;

ALTER TABLE `project_closeout_images`
  ADD COLUMN `location_id` int DEFAULT NULL AFTER `item_id`,
  ADD KEY `idx_closeout_image_location` (`location_id`, `project_id`),
  ADD CONSTRAINT `fk_closeout_image_location`
    FOREIGN KEY (`location_id`, `project_id`)
    REFERENCES `project_closeout_locations` (`location_id`, `project_id`)
    ON DELETE RESTRICT;

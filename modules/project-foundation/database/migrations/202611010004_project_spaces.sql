CREATE TABLE IF NOT EXISTS `Buildings` (
  `building_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `building_number` varchar(50) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`building_id`),
  UNIQUE KEY `uq_building_project_number` (`project_id`, `building_number`),
  UNIQUE KEY `uq_building_id_project` (`building_id`, `project_id`),
  CONSTRAINT `fk_buildings_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Building_Floors` (
  `floor_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_number` varchar(50) NOT NULL,
  `floor_name` varchar(50) DEFAULT NULL,
  `usage_type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`floor_id`),
  UNIQUE KEY `uq_floor_building_number` (`building_id`, `floor_number`),
  UNIQUE KEY `uq_floor_id_building` (`floor_id`, `building_id`),
  CONSTRAINT `fk_floors_building_project_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Construction_Areas` (
  `area_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_area_id_building` (`area_id`, `building_id`),
  KEY `idx_area_building` (`building_id`),
  CONSTRAINT `fk_areas_building_project_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Rooms` (
  `room_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_id` int NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `room_area` decimal(10,2) DEFAULT NULL,
  `room_height` decimal(5,2) DEFAULT NULL,
  `room_status` varchar(20) NOT NULL DEFAULT '空闲',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `uq_room_id_building_floor` (`room_id`, `building_id`, `floor_id`),
  UNIQUE KEY `uq_room_floor_number` (`floor_id`, `room_number`),
  CONSTRAINT `fk_rooms_floor_building_project_o` FOREIGN KEY (`floor_id`, `building_id`)
    REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Construction_Area_Rooms` (
  `area_room_id` int NOT NULL AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `area_id` int NOT NULL,
  `floor_id` int NOT NULL,
  `building_id` int NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`area_room_id`),
  UNIQUE KEY `uq_area_room` (`area_id`, `room_id`),
  CONSTRAINT `fk_area_room_area_project_o` FOREIGN KEY (`area_id`, `building_id`) REFERENCES `Construction_Areas` (`area_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_area_room_floor_project_o` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_area_room_room_project_o` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

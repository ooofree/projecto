CREATE TABLE IF NOT EXISTS `Material_Categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `parent_category_id` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  KEY `idx_material_category_parent` (`parent_category_id`),
  CONSTRAINT `fk_material_category_parent_o` FOREIGN KEY (`parent_category_id`) REFERENCES `Material_Categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Materials` (
  `material_id` int NOT NULL AUTO_INCREMENT,
  `material_code` varchar(50) DEFAULT NULL,
  `material_name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `specifications` text DEFAULT NULL,
  `material_description_detailed` text DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `default_quotation_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `uq_material_code` (`material_code`),
  KEY `idx_material_category` (`category_id`),
  CONSTRAINT `fk_material_category_o` FOREIGN KEY (`category_id`) REFERENCES `Material_Categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Brands` (
  `brand_id` int NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(255) NOT NULL,
  `external_link` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`),
  UNIQUE KEY `uq_brand_name` (`brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Suppliers` (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone_number` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country_code` char(2) DEFAULT NULL,
  `website_url` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  KEY `idx_supplier_name` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Supplier_Materials` (
  `supplier_material_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `brand_id` int DEFAULT NULL,
  `supplier_material_code` varchar(100) NOT NULL,
  `supplier_material_name` varchar(255) NOT NULL,
  `lead_time_days` int DEFAULT NULL,
  `material_details` text DEFAULT NULL,
  `specification` text DEFAULT NULL,
  `coverage_quantity` decimal(18,4) DEFAULT NULL,
  `coverage_unit` varchar(50) DEFAULT NULL,
  `price_total` decimal(18,4) DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_material_id`),
  UNIQUE KEY `uq_supplier_material_code` (`supplier_id`, `supplier_material_code`),
  KEY `idx_supplier_material_brand` (`brand_id`),
  CONSTRAINT `fk_supplier_material_supplier_o` FOREIGN KEY (`supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_material_brand_o` FOREIGN KEY (`brand_id`) REFERENCES `Brands` (`brand_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Material_Quotations` (
  `quotation_id` int NOT NULL AUTO_INCREMENT,
  `supplier_material_id` int NOT NULL,
  `material_id` int NOT NULL,
  `purchase_unit_price` decimal(18,4) NOT NULL,
  `quotation_date` date NOT NULL,
  `currency_code` char(3) NOT NULL,
  `min_order_quantity` decimal(12,4) DEFAULT NULL,
  `confirmed_tax_rate` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `version` int NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`quotation_id`),
  KEY `idx_quotation_material_date` (`material_id`, `quotation_date`),
  KEY `idx_quotation_supplier_material` (`supplier_material_id`),
  CONSTRAINT `fk_quotation_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_quotation_supplier_material_o` FOREIGN KEY (`supplier_material_id`) REFERENCES `Supplier_Materials` (`supplier_material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Material_Mappings` (
  `mapping_id` int NOT NULL AUTO_INCREMENT,
  `material_id` int NOT NULL,
  `quotation_id` int NOT NULL,
  `supplier_material_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uq_material_mapping_quotation` (`quotation_id`),
  KEY `idx_material_mapping_material` (`material_id`),
  CONSTRAINT `fk_material_mapping_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_quotation_o` FOREIGN KEY (`quotation_id`) REFERENCES `Material_Quotations` (`quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_supplier_material_o` FOREIGN KEY (`supplier_material_id`) REFERENCES `Supplier_Materials` (`supplier_material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Auxiliary_Materials` (
  `aux_id` int NOT NULL AUTO_INCREMENT,
  `aux_name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aux_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Initial_Cost_Estimate_Detail` (
  `estimate_detail_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_id` int DEFAULT NULL,
  `room_id` int DEFAULT NULL,
  `material_id` int NOT NULL,
  `estimated_quantity` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`estimate_detail_id`),
  KEY `idx_estimate_building` (`building_id`),
  KEY `idx_estimate_floor` (`floor_id`),
  KEY `idx_estimate_room` (`room_id`),
  KEY `idx_estimate_material` (`material_id`),
  CONSTRAINT `fk_estimate_building_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_floor_o` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_room_o` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Material_Samples` (
  `sample_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `quotation_id` int NOT NULL,
  `sample_quantity` int NOT NULL DEFAULT 0,
  `color` varchar(50) DEFAULT NULL,
  `document_status` varchar(30) NOT NULL DEFAULT 'pending_submission',
  `sent_status` varchar(30) NOT NULL DEFAULT 'not_sent',
  `sent_date` date DEFAULT NULL,
  `owner_approval_status` varchar(30) NOT NULL DEFAULT 'pending',
  `owner_approval_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` int DEFAULT NULL,
  PRIMARY KEY (`sample_id`),
  UNIQUE KEY `uq_sample_id_project` (`sample_id`, `project_id`),
  KEY `idx_sample_project_archive` (`project_id`, `archived_at`),
  KEY `idx_sample_quotation` (`quotation_id`),
  CONSTRAINT `fk_sample_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_quotation_o` FOREIGN KEY (`quotation_id`) REFERENCES `Material_Quotations` (`quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_created_by_o` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_archived_by_o` FOREIGN KEY (`archived_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Sample_Location_Map` (
  `sample_id` int NOT NULL,
  `project_id` int NOT NULL,
  `location_type` varchar(20) NOT NULL,
  `location_id` int NOT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`sample_id`),
  KEY `idx_sample_location_project` (`project_id`, `location_type`, `location_id`),
  CONSTRAINT `fk_sample_location_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_location_sample_o` FOREIGN KEY (`sample_id`, `project_id`) REFERENCES `Material_Samples` (`sample_id`, `project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Material_Sample_Images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `sample_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `content_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_sample_image_project` (`project_id`, `sample_id`),
  CONSTRAINT `fk_sample_image_sample_o` FOREIGN KEY (`sample_id`, `project_id`) REFERENCES `Material_Samples` (`sample_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_image_user_o` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: project_o
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.5

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Auxiliary_Materials`
--

DROP TABLE IF EXISTS `Auxiliary_Materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Auxiliary_Materials` (
  `aux_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `aux_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`aux_id`),
  UNIQUE KEY `uq_auxiliary_project_id` (`project_id`,`aux_id`),
  KEY `idx_auxiliary_project_name` (`project_id`,`aux_name`),
  CONSTRAINT `fk_auxiliary_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Auxiliary_Materials`
--

LOCK TABLES `Auxiliary_Materials` WRITE;
/*!40000 ALTER TABLE `Auxiliary_Materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `Auxiliary_Materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Brands`
--

DROP TABLE IF EXISTS `Brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Brands` (
  `brand_id` int NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_link` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`),
  UNIQUE KEY `uq_brand_name` (`brand_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Brands`
--

LOCK TABLES `Brands` WRITE;
/*!40000 ALTER TABLE `Brands` DISABLE KEYS */;
INSERT INTO `Brands` VALUES (1,'大华',NULL,'2026-11-01 14:05:22'),(2,'杨翔木饰面',NULL,'2026-11-01 14:08:13'),(3,'立邦',NULL,'2026-11-07 01:50:44'),(4,'杭波',NULL,'2026-11-18 05:32:08');
/*!40000 ALTER TABLE `Brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Building_Floors`
--

DROP TABLE IF EXISTS `Building_Floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Building_Floors` (
  `floor_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usage_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`floor_id`),
  UNIQUE KEY `uq_floor_building_number` (`building_id`,`floor_number`),
  UNIQUE KEY `uq_floor_id_building` (`floor_id`,`building_id`),
  CONSTRAINT `fk_floors_building_project_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Building_Floors`
--

LOCK TABLES `Building_Floors` WRITE;
/*!40000 ALTER TABLE `Building_Floors` DISABLE KEYS */;
INSERT INTO `Building_Floors` VALUES (1,1,'2F','二层','展厅'),(2,4,'2F','二层','办公室'),(3,4,'3F','三层','PI空间');
/*!40000 ALTER TABLE `Building_Floors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Buildings`
--

DROP TABLE IF EXISTS `Buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Buildings` (
  `building_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `building_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `building_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`building_id`),
  UNIQUE KEY `uq_building_project_number` (`project_id`,`building_number`),
  UNIQUE KEY `uq_building_id_project` (`building_id`,`project_id`),
  CONSTRAINT `fk_buildings_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Buildings`
--

LOCK TABLES `Buildings` WRITE;
/*!40000 ALTER TABLE `Buildings` DISABLE KEYS */;
INSERT INTO `Buildings` VALUES (1,1,'03','杭海金座3栋','2026-11-01 13:56:40','2026-11-01 13:56:40'),(4,2,'3D','3栋','2026-11-04 03:44:28','2026-11-04 03:45:55');
/*!40000 ALTER TABLE `Buildings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Construction_Area_Rooms`
--

DROP TABLE IF EXISTS `Construction_Area_Rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Construction_Area_Rooms` (
  `area_room_id` int NOT NULL AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `area_id` int NOT NULL,
  `floor_id` int NOT NULL,
  `building_id` int NOT NULL,
  `room_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`area_room_id`),
  UNIQUE KEY `uq_area_room` (`area_id`,`room_id`),
  KEY `fk_area_room_area_project_o` (`area_id`,`building_id`),
  KEY `fk_area_room_floor_project_o` (`floor_id`,`building_id`),
  KEY `fk_area_room_room_project_o` (`room_id`,`building_id`,`floor_id`),
  CONSTRAINT `fk_area_room_area_project_o` FOREIGN KEY (`area_id`, `building_id`) REFERENCES `Construction_Areas` (`area_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_area_room_floor_project_o` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_area_room_room_project_o` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Construction_Area_Rooms`
--

LOCK TABLES `Construction_Area_Rooms` WRITE;
/*!40000 ALTER TABLE `Construction_Area_Rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `Construction_Area_Rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Construction_Areas`
--

DROP TABLE IF EXISTS `Construction_Areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Construction_Areas` (
  `area_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `area_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_area_id_building` (`area_id`,`building_id`),
  KEY `idx_area_building` (`building_id`),
  CONSTRAINT `fk_areas_building_project_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Construction_Areas`
--

LOCK TABLES `Construction_Areas` WRITE;
/*!40000 ALTER TABLE `Construction_Areas` DISABLE KEYS */;
INSERT INTO `Construction_Areas` VALUES (1,1,'整个展厅','只有一个大空间','2026-11-01 14:04:22','2026-11-01 14:04:22');
/*!40000 ALTER TABLE `Construction_Areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Initial_Cost_Estimate_Detail`
--

DROP TABLE IF EXISTS `Initial_Cost_Estimate_Detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Initial_Cost_Estimate_Detail` (
  `estimate_detail_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_id` int DEFAULT NULL,
  `room_id` int DEFAULT NULL,
  `material_id` int NOT NULL,
  `estimated_quantity` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`estimate_detail_id`),
  KEY `idx_estimate_building` (`building_id`),
  KEY `idx_estimate_floor` (`floor_id`),
  KEY `idx_estimate_room` (`room_id`),
  KEY `idx_estimate_material` (`material_id`),
  KEY `fk_estimate_floor_o` (`floor_id`,`building_id`),
  KEY `fk_estimate_room_o` (`room_id`,`building_id`,`floor_id`),
  CONSTRAINT `fk_estimate_building_o` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_floor_o` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_estimate_room_o` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Initial_Cost_Estimate_Detail`
--

LOCK TABLES `Initial_Cost_Estimate_Detail` WRITE;
/*!40000 ALTER TABLE `Initial_Cost_Estimate_Detail` DISABLE KEYS */;
INSERT INTO `Initial_Cost_Estimate_Detail` VALUES (1,4,3,2,17,1.0000,NULL,'2026-11-13 04:15:50','2026-11-13 04:15:50'),(2,4,3,1,17,1.0000,NULL,'2026-11-13 04:34:04','2026-11-13 04:34:04'),(3,4,3,4,17,1.0000,NULL,'2026-11-13 04:34:27','2026-11-13 04:34:27'),(4,4,3,7,17,1.0000,NULL,'2026-11-13 04:34:40','2026-11-13 04:34:40'),(5,4,3,5,17,1.0000,NULL,'2026-11-13 04:34:58','2026-11-13 04:34:58'),(6,4,3,6,17,1.0000,NULL,'2026-11-13 04:35:37','2026-11-13 04:35:37'),(7,4,3,10,16,1.0000,NULL,'2026-11-13 04:36:58','2026-11-13 04:36:58'),(8,4,3,11,32,1.0000,'走道','2026-11-13 04:38:50','2026-11-13 04:39:39'),(9,4,3,11,18,4.0000,NULL,'2026-11-13 04:40:17','2026-11-13 04:40:17'),(10,4,3,12,33,2.0000,NULL,'2026-11-13 04:41:27','2026-11-13 04:41:27'),(11,4,3,13,16,1.0000,NULL,'2026-11-13 04:41:42','2026-11-13 04:41:42'),(12,4,2,15,17,1.0000,NULL,'2026-11-13 04:43:10','2026-11-13 04:43:10'),(13,4,2,14,34,1.0000,NULL,'2026-11-13 04:45:48','2026-11-13 04:45:48'),(14,4,2,17,17,2.0000,NULL,'2026-11-13 04:50:57','2026-11-13 04:50:57'),(15,4,2,18,17,1.0000,NULL,'2026-11-13 04:51:55','2026-11-13 04:51:55'),(16,4,2,19,17,1.0000,NULL,'2026-11-13 04:52:03','2026-11-13 04:52:03'),(17,4,2,20,17,1.0000,NULL,'2026-11-13 04:52:15','2026-11-13 04:52:15'),(18,4,2,16,32,1.0000,NULL,'2026-11-14 05:06:31','2026-11-14 05:06:31'),(19,4,2,21,57,29.0000,'二层办公区B2立面图','2026-11-17 04:10:21','2026-11-17 04:10:21'),(20,4,3,9,57,16.0000,'三层办公区A3立面图','2026-11-17 04:11:47','2026-11-17 04:11:47'),(21,4,3,9,57,22.0000,'三层办公区C1立面图','2026-11-17 04:12:36','2026-11-17 04:12:36'),(22,4,3,9,57,21.4000,'三层办公区C1立面图','2026-11-17 04:13:20','2026-11-17 04:13:20'),(23,4,2,14,46,9.8500,'天花','2026-11-17 04:15:06','2026-11-17 04:15:06'),(24,4,2,15,46,9.1500,'天花','2026-11-17 04:15:32','2026-11-17 04:15:32'),(25,4,2,17,46,20.4000,'天花','2026-11-17 04:16:12','2026-11-17 04:16:12'),(26,4,2,17,46,9.4000,'办公室两旁边静音仓顶面','2026-11-17 04:17:44','2026-11-17 04:17:44'),(27,4,3,NULL,46,5.0000,'静音仓顶面','2026-11-17 04:19:09','2026-11-17 04:19:09'),(28,4,3,8,46,28.0000,'天花','2026-11-17 04:20:19','2026-11-17 04:20:19'),(29,4,2,22,25,25.0000,NULL,'2026-11-17 04:58:09','2026-11-17 04:58:09'),(30,4,3,23,25,10.0000,'靠中庭开花','2026-11-17 05:01:59','2026-11-17 05:01:59'),(31,4,3,23,58,59.0000,NULL,'2026-11-18 04:05:10','2026-11-18 04:05:10'),(32,4,2,22,58,94.0000,'投影区顶面','2026-11-18 04:06:53','2026-11-18 04:06:53'),(33,4,2,21,142,12.8000,'二层办公区B2立面图','2026-11-18 13:01:19','2026-11-25 06:54:12'),(34,4,3,10,142,6.7000,'三层办公区C1立面图','2026-11-18 13:04:00','2026-11-25 06:54:45'),(35,4,3,24,142,5.3000,NULL,'2026-11-18 13:05:37','2026-11-25 06:54:35'),(36,4,3,10,142,7.4000,'排烟机房外','2026-11-18 13:06:33','2026-11-25 06:54:25'),(37,4,2,17,121,34.4800,'外侧墙面','2026-11-18 13:31:56','2026-11-18 13:31:56'),(38,4,3,9,121,19.2000,'南侧立柱','2026-11-18 13:37:23','2026-11-18 13:37:23'),(39,4,3,9,121,39.4100,'办公室外墙面','2026-11-18 13:54:01','2026-11-18 13:54:01'),(40,4,3,8,121,46.6200,'核芯筒墙面','2026-11-18 13:57:03','2026-11-18 13:57:03'),(41,4,3,9,121,74.2800,'北侧办公区立柱','2026-11-18 14:00:14','2026-11-18 14:00:14'),(42,4,3,25,122,166.0000,'外侧深色面积应该大一点。现在是按表面积除以2','2026-11-18 15:15:00','2026-11-18 15:15:00'),(43,4,3,25,123,166.0000,'面积应该比这个小一点','2026-11-18 15:15:25','2026-11-18 15:15:25'),(44,4,2,NULL,3,224.0000,'白色5.6米长共40根','2026-11-20 03:17:13','2026-11-20 03:19:29'),(45,4,2,NULL,3,106.4000,'蓝色共5.6米共19根','2026-11-20 03:18:29','2026-11-20 03:18:59'),(46,4,2,22,135,9.9000,'前厅顶上','2026-11-21 08:46:53','2026-11-23 13:14:22'),(47,4,2,18,135,10.8200,NULL,'2026-11-21 08:48:59','2026-11-23 13:13:57'),(48,4,2,19,135,10.4400,NULL,'2026-11-21 08:49:21','2026-11-23 13:13:47'),(49,4,2,20,135,11.6400,NULL,'2026-11-21 08:49:38','2026-11-23 13:13:33'),(50,4,3,2,135,14.2000,NULL,'2026-11-21 08:56:07','2026-11-23 13:17:29'),(51,4,3,1,135,13.6000,NULL,'2026-11-21 08:59:10','2026-11-23 13:17:52'),(52,4,3,4,135,13.8700,NULL,'2026-11-21 08:59:39','2026-11-23 13:17:41'),(53,4,3,5,135,13.1000,NULL,'2026-11-21 09:00:21','2026-11-23 13:17:13'),(54,4,3,6,135,12.1300,NULL,'2026-11-21 09:00:42','2026-11-23 13:16:48'),(55,4,3,7,135,11.4000,NULL,'2026-11-21 09:01:12','2026-11-23 13:16:26'),(56,4,2,21,136,1.0000,NULL,'2026-11-25 05:40:58','2026-11-25 05:40:58'),(57,4,2,26,137,1.0000,NULL,'2026-11-25 05:42:05','2026-11-25 05:42:05'),(58,4,3,27,138,1.0000,NULL,'2026-11-25 05:43:39','2026-11-25 05:43:39'),(59,4,3,24,139,1.0000,NULL,'2026-11-25 05:43:58','2026-11-25 05:43:58'),(60,4,3,12,140,1.0000,'中会议室对面','2026-11-25 05:44:35','2026-11-25 05:44:35'),(61,4,2,22,132,1.0000,NULL,'2026-11-25 05:47:20','2026-11-25 05:47:20'),(62,4,3,8,132,1.0000,NULL,'2026-11-25 05:47:34','2026-11-25 05:47:34'),(63,4,2,22,145,25.0000,NULL,'2026-11-27 02:18:02','2026-11-27 02:18:02'),(64,4,3,23,145,10.0000,NULL,'2026-11-27 02:18:37','2026-11-27 02:18:37'),(65,4,3,12,147,12.8000,NULL,'2026-11-27 02:32:43','2026-11-27 02:32:43'),(66,4,2,17,147,11.1900,'3.73W*3H高','2026-11-27 03:05:36','2026-11-27 03:05:36'),(67,4,2,17,147,8.4900,'2.83W*3H高','2026-11-27 03:06:14','2026-11-27 03:06:14'),(68,4,2,15,147,14.7300,'4.91W*3H','2026-11-27 03:07:43','2026-11-27 03:07:43'),(69,4,2,14,147,11.5400,'4.1W*2.35H','2026-11-27 03:08:35','2026-11-27 03:08:35');
/*!40000 ALTER TABLE `Initial_Cost_Estimate_Detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Labor_Crew_Shares`
--

DROP TABLE IF EXISTS `Labor_Crew_Shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Labor_Crew_Shares` (
  `share_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL COMMENT '分享归属项目；公开页只能读取该项目的在用班组',
  `token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公开链接令牌，32 位十六进制随机串',
  `created_by` int NOT NULL COMMENT '开启分享的用户',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` timestamp NULL DEFAULT NULL COMMENT '停用时间；非空即失效，保留记录不删除',
  `revoked_by` int DEFAULT NULL COMMENT '停用分享的用户',
  PRIMARY KEY (`share_id`),
  UNIQUE KEY `uq_crew_share_token` (`token`),
  KEY `idx_crew_share_project_revoked` (`project_id`,`revoked_at`),
  KEY `fk_crew_shares_created_by_o` (`created_by`),
  KEY `fk_crew_shares_revoked_by_o` (`revoked_by`),
  CONSTRAINT `fk_crew_shares_created_by_o` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crew_shares_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crew_shares_revoked_by_o` FOREIGN KEY (`revoked_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Labor_Crew_Shares`
--

LOCK TABLES `Labor_Crew_Shares` WRITE;
/*!40000 ALTER TABLE `Labor_Crew_Shares` DISABLE KEYS */;
INSERT INTO `Labor_Crew_Shares` VALUES (1,2,'58186912cac2eb5c32d51dfc68f2f1db',1,'2026-11-13 02:06:39',NULL,NULL),(2,1,'fdb8cfff84ed44fe07d30ccc9db5dfab',1,'2026-11-13 11:50:46',NULL,NULL);
/*!40000 ALTER TABLE `Labor_Crew_Shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Labor_Crew_Types`
--

DROP TABLE IF EXISTS `Labor_Crew_Types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Labor_Crew_Types` (
  `crew_type_id` int NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工种名称，如钢筋、木工、水电安装',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '列表排序，值小者在前',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`crew_type_id`),
  UNIQUE KEY `uq_crew_type_name` (`type_name`),
  KEY `idx_crew_type_sort` (`sort_order`,`crew_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Labor_Crew_Types`
--

LOCK TABLES `Labor_Crew_Types` WRITE;
/*!40000 ALTER TABLE `Labor_Crew_Types` DISABLE KEYS */;
INSERT INTO `Labor_Crew_Types` VALUES (3,'木工',30,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(4,'泥瓦',40,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(7,'防水',70,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(8,'水电安装',80,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(9,'暖通',90,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(10,'消防',100,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(11,'装饰装修',110,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(13,'门窗',130,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(14,'油漆涂料',140,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(15,'拆除',150,'2026-11-11 06:00:13','2026-11-11 06:00:13'),(20,'定制产品',20,'2026-11-11 06:30:49','2026-11-11 06:30:49');
/*!40000 ALTER TABLE `Labor_Crew_Types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Labor_Crews`
--

DROP TABLE IF EXISTS `Labor_Crews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Labor_Crews` (
  `crew_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL COMMENT '归属项目；班组只对该项目负责',
  `crew_type_id` int NOT NULL COMMENT '所属工种',
  `crew_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '班组名称',
  `leader_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '负责人姓名',
  `leader_phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '负责人手机号，可含 +86 前缀',
  `wechat_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '微信号',
  `id_card_number_encrypted` text COLLATE utf8mb4_unicode_ci COMMENT '身份证号 AES-256-GCM 密文，禁止存明文',
  `id_card_fingerprint` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '身份证号 SHA-256 加盐指纹，用于同项目查重',
  `emergency_contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '紧急联系人姓名',
  `emergency_contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '紧急联系人电话',
  `notes` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注：常驻区域、擅长工艺等',
  `is_active` tinyint NOT NULL DEFAULT '1' COMMENT '1 在用，0 已归档；归档保留数据',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '列表排序，值小者在前；0 为默认',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`crew_id`),
  UNIQUE KEY `uq_crew_id_project` (`crew_id`,`project_id`),
  UNIQUE KEY `uq_crew_project_fingerprint` (`project_id`,`id_card_fingerprint`),
  KEY `idx_crew_project_active_type` (`project_id`,`is_active`,`crew_type_id`),
  KEY `idx_crew_project_name` (`project_id`,`crew_name`),
  KEY `fk_crews_type_o` (`crew_type_id`),
  CONSTRAINT `fk_crews_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_crews_type_o` FOREIGN KEY (`crew_type_id`) REFERENCES `Labor_Crew_Types` (`crew_type_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Labor_Crews`
--

LOCK TABLES `Labor_Crews` WRITE;
/*!40000 ALTER TABLE `Labor_Crews` DISABLE KEYS */;
INSERT INTO `Labor_Crews` VALUES (3,2,20,'GRG制作','霍庆前','18726271011',NULL,NULL,NULL,NULL,NULL,'固定圆顶休息舱',1,7,'2026-11-11 06:31:50','2026-11-11 09:24:02'),(4,2,20,'不锈钢玻璃铝板','邵建军','13655812860',NULL,NULL,NULL,NULL,NULL,'不锈钢、玻璃、冲孔铝板',1,8,'2026-11-11 06:33:33','2026-11-11 09:20:58'),(5,2,20,'感应电动玻璃门','熊军','13567126962',NULL,NULL,NULL,NULL,NULL,NULL,1,9,'2026-11-11 06:34:54','2026-11-11 09:21:06'),(6,2,20,'木饰面定制柜','杨翔','15372989995',NULL,NULL,NULL,NULL,NULL,NULL,1,10,'2026-11-11 06:36:16','2026-11-11 09:21:17'),(7,2,8,'水电班组','卢国平','13989446050',NULL,NULL,NULL,NULL,NULL,'水电安装，布线；消防水电改造；灯具安装。',1,3,'2026-11-11 06:37:57','2026-11-11 09:10:58'),(8,2,9,'排烟安装班组','蒋排烟','13167664386',NULL,NULL,NULL,NULL,NULL,NULL,1,4,'2026-11-11 06:39:09','2026-11-11 09:13:44'),(9,2,4,'地面找平','吴 硕','18757627553',NULL,NULL,NULL,NULL,NULL,NULL,1,6,'2026-11-11 06:43:16','2026-11-11 09:13:30'),(10,2,9,'空调新风','田','13732221782',NULL,NULL,NULL,NULL,NULL,'现场负责',1,11,'2026-11-11 06:44:28','2026-11-11 09:21:27'),(11,2,11,'施工管理员','徐宁','18258846005',NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-11-11 06:53:35','2026-11-11 09:08:18'),(12,2,14,'油漆班组','骆晓军','18606712091',NULL,NULL,NULL,NULL,NULL,'涂料、油漆、艺术涂料',1,4,'2026-11-11 07:02:22','2026-11-11 09:11:11'),(13,1,11,'施工管理员','徐群云','15067050738',NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-11-11 08:24:16','2026-11-11 09:05:06'),(14,1,3,'木工班组','熊诗军','13970340165',NULL,NULL,NULL,NULL,NULL,'吊顶、隔墙、固定造型基层',1,2,'2026-11-11 08:44:22','2026-11-11 09:05:32'),(15,1,8,'水电班组','王小宝','13968160803',NULL,NULL,NULL,NULL,NULL,'消防水，水电。排烟。布线。灯具安装。',1,3,'2026-11-11 08:48:39','2026-11-12 16:08:04'),(16,1,9,'空调新风','程春晖','13456768527',NULL,NULL,NULL,NULL,NULL,'空调安装，新风安装',1,4,'2026-11-11 08:51:04','2026-11-12 16:03:41'),(17,1,4,'地面找平','吴 硕','18757627553',NULL,NULL,NULL,NULL,NULL,'地面陶粒混凝土找平、自流平',1,5,'2026-11-11 08:54:41','2026-11-11 08:54:48'),(18,1,14,'油漆班组','吴  成','15807080797',NULL,NULL,NULL,NULL,NULL,NULL,1,6,'2026-11-11 08:56:28','2026-11-11 08:56:28'),(19,1,20,'玻璃铝板','邵建军','13655812860',NULL,NULL,NULL,NULL,NULL,'玻璃隔断，弧形玻璃，铝板，冲孔铝板。',1,7,'2026-11-11 08:57:42','2026-11-11 08:57:42'),(20,1,20,'电动感应玻璃门','熊军','13567126962',NULL,NULL,NULL,NULL,NULL,NULL,1,8,'2026-11-11 08:59:04','2026-11-11 09:04:20'),(21,1,20,'广告制作','吴成蔚','15967166610',NULL,NULL,NULL,NULL,NULL,'动感灯箱、深渊镜、磁贴广告牌、树脂吧台、地贴',1,9,'2026-11-11 09:01:26','2026-11-12 16:07:29'),(22,1,20,'LED显示屏设备','杨城','15990011971',NULL,NULL,NULL,NULL,NULL,'LED屏，会议室旋转门轨道',1,10,'2026-11-11 09:04:06','2026-11-11 09:04:06'),(23,2,3,'木工班组','陈诚','13685758696',NULL,NULL,NULL,NULL,NULL,'吊顶、隔墙',1,2,'2026-11-11 09:09:57','2026-11-11 09:09:57'),(24,2,13,'倪伟飞','防火门','13588372195',NULL,NULL,NULL,NULL,NULL,NULL,1,12,'2026-11-13 05:00:12','2026-11-13 05:04:47'),(25,1,9,'防火卷帘','杨玉军','13666650119',NULL,NULL,NULL,NULL,NULL,'电动防火卷帘',1,12,'2026-11-13 11:48:26','2026-11-13 11:50:33'),(26,2,9,'防火卷帘','杨玉军','13666650119',NULL,NULL,NULL,NULL,NULL,'电动防火卷帘',1,13,'2026-11-13 11:49:07','2026-11-14 10:53:47'),(27,1,20,'木饰面定制柜','杨翔','15372989995',NULL,NULL,NULL,NULL,NULL,'木饰面、定制柜',1,11,'2026-11-13 11:50:24','2026-11-13 11:50:24'),(28,2,11,'静电地板','星耀','13957149106',NULL,NULL,NULL,NULL,NULL,'静电地板',1,14,'2026-11-14 10:53:27','2026-11-14 10:54:06'),(29,1,11,'静电地板','星耀','13957149106',NULL,NULL,NULL,NULL,NULL,'地胶地板，木地板',1,14,'2026-11-14 10:55:59','2026-11-14 10:55:59'),(30,2,20,'石材','万','15159854891',NULL,NULL,NULL,NULL,NULL,NULL,1,15,'2026-11-20 01:16:15','2026-11-20 01:16:30'),(31,2,4,'自流平','自','19032433528',NULL,NULL,NULL,NULL,NULL,NULL,1,16,'2026-11-23 14:37:08','2026-11-23 14:37:17');
/*!40000 ALTER TABLE `Labor_Crews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Categories`
--

DROP TABLE IF EXISTS `Material_Categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_category_id` int DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_material_category_project_id` (`project_id`,`category_id`),
  KEY `idx_material_category_parent` (`parent_category_id`),
  KEY `idx_material_category_project_parent` (`project_id`,`parent_category_id`),
  CONSTRAINT `fk_material_category_parent_o` FOREIGN KEY (`parent_category_id`) REFERENCES `Material_Categories` (`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_category_parent_project_o` FOREIGN KEY (`project_id`, `parent_category_id`) REFERENCES `Material_Categories` (`project_id`, `category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_category_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Categories`
--

LOCK TABLES `Material_Categories` WRITE;
/*!40000 ALTER TABLE `Material_Categories` DISABLE KEYS */;
INSERT INTO `Material_Categories` VALUES (1,2,'装修材料',NULL,3,'2026-11-01 13:57:29','2026-11-22 09:59:32'),(2,2,'设备',NULL,6,'2026-11-01 13:57:53','2026-11-22 10:00:08'),(3,2,'强电材料',NULL,5,'2026-11-01 13:58:05','2026-11-22 10:00:01'),(4,2,'成品定制',NULL,4,'2026-11-01 13:58:19','2026-11-22 09:59:51'),(5,2,'灯具',NULL,7,'2026-11-11 10:23:36','2026-11-22 10:00:36'),(6,1,'装修材料',NULL,0,'2026-11-18 05:00:40','2026-11-18 05:00:40'),(7,2,'整体产品',NULL,2,'2026-11-22 09:50:58','2026-11-22 09:50:58');
/*!40000 ALTER TABLE `Material_Categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Compositions`
--

DROP TABLE IF EXISTS `Material_Compositions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Compositions` (
  `composition_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `parent_material_id` int NOT NULL,
  `child_material_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`composition_id`),
  UNIQUE KEY `uq_composition_project_parent_child` (`project_id`,`parent_material_id`,`child_material_id`),
  UNIQUE KEY `uq_composition_project_id` (`project_id`,`composition_id`),
  KEY `idx_composition_project_child` (`project_id`,`child_material_id`),
  CONSTRAINT `fk_composition_child_o` FOREIGN KEY (`project_id`, `child_material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_composition_parent_o` FOREIGN KEY (`project_id`, `parent_material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_composition_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_composition_not_self` CHECK ((`parent_material_id` <> `child_material_id`)),
  CONSTRAINT `chk_composition_quantity_positive` CHECK ((`quantity` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Compositions`
--

LOCK TABLES `Material_Compositions` WRITE;
/*!40000 ALTER TABLE `Material_Compositions` DISABLE KEYS */;
INSERT INTO `Material_Compositions` VALUES (25,2,33,124,3.90,'2026-11-20 16:22:28','2026-11-20 16:22:28'),(26,2,33,125,1.00,'2026-11-20 16:22:57','2026-11-20 16:22:57'),(27,2,17,124,2.86,'2026-11-20 16:23:52','2026-11-20 16:23:52'),(28,2,34,124,2.42,'2026-11-20 16:24:18','2026-11-20 16:24:18'),(29,2,32,125,1.00,'2026-11-20 16:24:36','2026-11-20 16:24:36'),(30,2,126,128,1.00,'2026-11-22 04:47:19','2026-11-22 04:47:19'),(31,2,46,129,1.00,'2026-11-22 05:48:20','2026-11-22 05:48:20'),(32,2,46,130,1.00,'2026-11-22 05:48:29','2026-11-22 05:48:29'),(33,2,131,125,0.06,'2026-11-22 07:39:07','2026-11-22 07:39:07'),(34,2,58,4,0.23,'2026-11-22 09:04:57','2026-11-22 09:04:57'),(35,2,132,133,5.30,'2026-11-22 10:02:51','2026-11-22 10:02:51'),(36,2,132,14,4.30,'2026-11-22 10:03:43','2026-11-22 10:03:43'),(37,2,132,15,4.40,'2026-11-22 10:05:32','2026-11-22 10:05:32'),(39,2,132,13,1.13,'2026-11-22 10:08:57','2026-11-22 10:08:57'),(40,2,17,125,3.38,'2026-11-22 10:18:12','2026-11-22 10:18:12'),(41,2,132,130,4.52,'2026-11-22 10:20:52','2026-11-22 10:21:04'),(42,2,132,134,2.84,'2026-11-22 10:29:06','2026-11-22 10:29:06'),(43,2,135,51,1.00,'2026-11-23 13:12:22','2026-11-23 13:12:22'),(44,2,136,13,4.19,'2026-11-25 05:15:06','2026-11-25 05:15:06'),(45,2,137,13,3.63,'2026-11-25 05:21:04','2026-11-25 05:21:04'),(46,2,138,13,2.42,'2026-11-25 05:29:23','2026-11-25 05:29:23'),(47,2,139,13,1.10,'2026-11-25 05:31:39','2026-11-25 05:31:39'),(48,2,140,13,2.70,'2026-11-25 05:33:02','2026-11-25 05:33:02'),(49,2,140,141,1.00,'2026-11-25 05:36:45','2026-11-25 05:36:45'),(50,2,136,141,1.00,'2026-11-25 05:37:01','2026-11-25 05:37:01'),(51,2,137,141,1.00,'2026-11-25 05:37:17','2026-11-25 05:37:17'),(52,2,139,141,1.00,'2026-11-25 05:37:26','2026-11-25 05:37:26'),(53,2,142,120,1.00,'2026-11-25 06:52:57','2026-11-25 06:52:57'),(54,2,145,25,1.00,'2026-11-27 02:17:09','2026-11-27 02:17:09'),(55,2,147,146,1.00,'2026-11-27 02:26:41','2026-11-27 02:26:41');
/*!40000 ALTER TABLE `Material_Compositions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Mappings`
--

DROP TABLE IF EXISTS `Material_Mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Mappings` (
  `mapping_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `material_id` int NOT NULL,
  `quotation_id` int NOT NULL,
  `supplier_material_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uq_material_mapping_quotation` (`quotation_id`),
  KEY `idx_material_mapping_material` (`material_id`),
  KEY `fk_material_mapping_supplier_material_o` (`supplier_material_id`),
  KEY `idx_material_mapping_project_material` (`project_id`,`material_id`),
  KEY `fk_material_mapping_project_quotation_o` (`project_id`,`quotation_id`),
  CONSTRAINT `fk_material_mapping_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_project_material_o` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_project_quotation_o` FOREIGN KEY (`project_id`, `quotation_id`) REFERENCES `Material_Quotations` (`project_id`, `quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_quotation_o` FOREIGN KEY (`quotation_id`) REFERENCES `Material_Quotations` (`quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_mapping_supplier_material_o` FOREIGN KEY (`supplier_material_id`) REFERENCES `Supplier_Materials` (`supplier_material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Mappings`
--

LOCK TABLES `Material_Mappings` WRITE;
/*!40000 ALTER TABLE `Material_Mappings` DISABLE KEYS */;
INSERT INTO `Material_Mappings` VALUES (2,2,2,2,2,'2026-11-01 14:11:09','2026-11-05 13:48:35'),(3,2,124,8,3,'2026-11-21 13:49:19','2026-11-21 13:49:19'),(4,2,125,9,4,'2026-11-22 10:26:48','2026-11-22 10:26:48'),(5,2,134,10,4,'2026-11-23 07:52:49','2026-11-23 07:52:49');
/*!40000 ALTER TABLE `Material_Mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Quotations`
--

DROP TABLE IF EXISTS `Material_Quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Quotations` (
  `quotation_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `supplier_material_id` int NOT NULL,
  `material_id` int NOT NULL,
  `purchase_unit_price` decimal(18,4) NOT NULL,
  `quotation_date` date NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` enum('active','paused','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `tax_status` enum('taxable','exempt','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `pricing_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_order_quantity` decimal(12,4) DEFAULT NULL,
  `confirmed_tax_rate` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `version` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`quotation_id`),
  UNIQUE KEY `uq_quotation_project_id` (`project_id`,`quotation_id`),
  UNIQUE KEY `uq_quotation_project_material_id` (`project_id`,`material_id`,`quotation_id`),
  KEY `idx_quotation_material_date` (`material_id`,`quotation_date`),
  KEY `idx_quotation_supplier_material` (`supplier_material_id`),
  KEY `idx_quotation_project_material_date` (`project_id`,`material_id`,`quotation_date`),
  CONSTRAINT `fk_quotation_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_quotation_project_material_o` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_quotation_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_quotation_supplier_material_o` FOREIGN KEY (`supplier_material_id`) REFERENCES `Supplier_Materials` (`supplier_material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Quotations`
--

LOCK TABLES `Material_Quotations` WRITE;
/*!40000 ALTER TABLE `Material_Quotations` DISABLE KEYS */;
INSERT INTO `Material_Quotations` VALUES (2,2,2,2,350.0000,'2026-11-01',NULL,NULL,'active','unknown',NULL,'CNY',1.0000,0.0000,1,'2026-11-01 14:11:09','2026-11-05 13:48:35'),(8,2,3,124,120.0000,'2026-11-21',NULL,NULL,'active','unknown',NULL,'CNY',NULL,0.1300,1,'2026-11-21 13:49:19','2026-11-21 13:49:19'),(9,2,4,125,115.0000,'2026-11-22',NULL,NULL,'active','unknown',NULL,'CNY',NULL,0.1300,1,'2026-11-22 10:26:48','2026-11-22 10:26:48'),(10,2,4,134,320.0000,'2026-11-23',NULL,NULL,'active','unknown',NULL,'CNY',NULL,0.1300,1,'2026-11-23 07:52:49','2026-11-23 07:52:49');
/*!40000 ALTER TABLE `Material_Quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Sample_Images`
--

DROP TABLE IF EXISTS `Material_Sample_Images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Sample_Images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `sample_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_sample_image_project` (`project_id`,`sample_id`),
  KEY `fk_sample_image_sample_o` (`sample_id`,`project_id`),
  KEY `fk_sample_image_user_o` (`uploaded_by`),
  CONSTRAINT `fk_sample_image_sample_o` FOREIGN KEY (`sample_id`, `project_id`) REFERENCES `Material_Samples` (`sample_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_image_user_o` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Sample_Images`
--

LOCK TABLES `Material_Sample_Images` WRITE;
/*!40000 ALTER TABLE `Material_Sample_Images` DISABLE KEYS */;
INSERT INTO `Material_Sample_Images` VALUES (1,3,2,'samples/2/3/782395fbdb8f7b095757ce1b2e3b7a26.jpg','7292c952c71f95d1da2a5042a401e3af.jpg','image/jpeg',187573,1,'2026-11-06 15:33:13'),(2,4,2,'samples/2/4/3d141809c617e4e53025b0f45ef937ef.png','ScreenShot_2026-11-07_144136_072.png','image/png',114397,1,'2026-11-07 07:15:06'),(3,7,2,'samples/2/7/7cf2403ac00c06e7f4aadbaacdbafba2.png','ScreenShot_2026-11-07_152129_432.png','image/png',102504,1,'2026-11-07 07:21:57');
/*!40000 ALTER TABLE `Material_Sample_Images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Material_Samples`
--

DROP TABLE IF EXISTS `Material_Samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Material_Samples` (
  `sample_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `material_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `sample_quantity` int NOT NULL DEFAULT '0',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_submission',
  `sent_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_sent',
  `sent_date` date DEFAULT NULL,
  `owner_approval_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `owner_approval_date` date DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` int DEFAULT NULL,
  PRIMARY KEY (`sample_id`),
  UNIQUE KEY `uq_sample_id_project` (`sample_id`,`project_id`),
  KEY `idx_sample_project_archive` (`project_id`,`archived_at`),
  KEY `idx_sample_quotation` (`quotation_id`),
  KEY `fk_sample_created_by_o` (`created_by`),
  KEY `fk_sample_archived_by_o` (`archived_by`),
  KEY `idx_sample_material` (`material_id`),
  KEY `idx_sample_supplier` (`supplier_id`),
  CONSTRAINT `fk_sample_archived_by_o` FOREIGN KEY (`archived_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_created_by_o` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_material_o` FOREIGN KEY (`material_id`) REFERENCES `Materials` (`material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_quotation_o` FOREIGN KEY (`quotation_id`) REFERENCES `Material_Quotations` (`quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_supplier_o` FOREIGN KEY (`supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Material_Samples`
--

LOCK TABLES `Material_Samples` WRITE;
/*!40000 ALTER TABLE `Material_Samples` DISABLE KEYS */;
INSERT INTO `Material_Samples` VALUES (1,2,2,2,2,3,'木纹色','pending_submission','not_sent','2026-11-01','pending',NULL,NULL,1,'2026-11-01 14:12:57','2026-11-05 13:48:35','2026-11-02 10:00:46',1),(2,2,2,2,NULL,0,NULL,'pending_submission','not_sent',NULL,'pending',NULL,NULL,1,'2026-11-05 17:26:45','2026-11-05 17:26:51','2026-11-05 17:26:51',1),(3,2,2,2,NULL,1,'木纹色','submitted','sent','2026-11-01','approved','2026-11-06','所有木饰面柜体及木饰面全部使用这个颜色',1,'2026-11-05 17:38:08','2026-11-06 17:19:38',NULL,NULL),(4,2,8,3,NULL,1,'橙色','reviewed','sent','2026-11-06','approved','2026-11-06',NULL,1,'2026-11-07 06:41:08','2026-11-07 06:41:08',NULL,NULL),(5,2,6,3,NULL,1,'深色','pending_submission','sent','2026-11-07','pending',NULL,NULL,1,'2026-11-07 07:15:45','2026-11-18 04:13:24',NULL,NULL),(6,2,5,3,NULL,1,NULL,'submitted','sent','2026-11-09','pending',NULL,NULL,1,'2026-11-07 07:16:19','2026-11-07 07:16:19',NULL,NULL),(7,2,7,3,NULL,1,'蓝色','submitted','sent','2026-11-06','approved','2026-11-18','有修改更深一个颜色',1,'2026-11-07 07:18:39','2026-11-18 13:22:17',NULL,NULL),(8,2,57,1,NULL,1,'白色','submitted','sent','2026-11-18','pending',NULL,NULL,1,'2026-11-18 04:12:14','2026-11-18 04:13:17',NULL,NULL),(9,2,55,1,NULL,1,NULL,'submitted','sent','2026-11-11','approved','2026-11-13',NULL,1,'2026-11-18 04:13:08','2026-11-18 04:13:08',NULL,NULL),(10,2,46,1,NULL,1,'白色','submitted','sent','2026-11-18','pending',NULL,NULL,1,'2026-11-18 04:32:20','2026-11-18 04:32:20',NULL,NULL),(11,2,19,4,NULL,2,'黑色','submitted','sent','2026-11-05','approved','2026-11-07',NULL,1,'2026-11-18 04:38:47','2026-11-18 04:38:47',NULL,NULL),(20,2,120,6,NULL,2,'蓝色','submitted','sent','2026-11-15','approved','2026-11-15',NULL,1,'2026-11-18 13:00:03','2026-11-18 13:00:03',NULL,NULL),(21,2,122,3,NULL,1,NULL,'submitted','sent','2026-11-18','approved','2026-11-18',NULL,1,'2026-11-18 13:20:55','2026-11-18 13:20:55',NULL,NULL),(22,2,123,3,NULL,1,NULL,'submitted','sent','2026-11-18','approved','2026-11-18',NULL,1,'2026-11-18 13:21:25','2026-11-18 13:21:25',NULL,NULL),(23,2,121,3,NULL,0,NULL,'submitted','sent','2026-11-18','approved','2026-11-18',NULL,1,'2026-11-18 13:21:49','2026-11-18 13:21:49',NULL,NULL),(24,2,125,4,NULL,2,NULL,'submitted','sent','2026-11-19','approved','2026-11-19',NULL,1,'2026-11-21 12:41:55','2026-11-21 12:41:55',NULL,NULL),(25,2,124,4,NULL,3,NULL,'submitted','sent','2026-11-19','approved','2026-11-19',NULL,1,'2026-11-21 12:42:24','2026-11-21 12:42:24',NULL,NULL),(26,1,65,2,NULL,1,'木纹色','pending_submission','sent','2026-11-25','pending',NULL,NULL,1,'2026-11-25 14:13:22','2026-11-25 14:13:22',NULL,NULL),(27,1,63,2,NULL,1,'白色烤漆','pending_submission','not_sent','2026-11-25','pending',NULL,NULL,1,'2026-11-25 14:14:02','2026-11-25 14:14:02',NULL,NULL),(28,1,66,2,NULL,1,'白色烤漆','submitted','sent','2026-11-25','approved','2026-11-20',NULL,1,'2026-11-25 14:14:41','2026-11-25 14:14:41',NULL,NULL),(29,1,64,2,NULL,1,'渐变色','pending_submission','not_sent','2026-11-25','pending',NULL,'可以贴膜',1,'2026-11-25 14:15:15','2026-11-25 14:15:15',NULL,NULL),(30,1,71,8,NULL,1,'木纹色','reviewed','sent','2026-11-25','approved','2026-11-20',NULL,1,'2026-11-25 14:25:16','2026-11-25 14:25:16',NULL,NULL),(31,1,70,9,NULL,1,NULL,'pending_submission','not_sent','2026-11-25','pending',NULL,NULL,1,'2026-11-25 14:27:51','2026-11-25 14:27:51',NULL,NULL),(32,1,60,4,NULL,1,NULL,'submitted','sent','2026-11-20','pending',NULL,NULL,1,'2026-11-25 14:28:25','2026-11-25 14:28:25',NULL,NULL),(33,1,74,4,NULL,1,'银色','submitted','sent','2026-11-20','rejected','2026-11-20',NULL,1,'2026-11-25 14:29:12','2026-11-25 14:29:12',NULL,NULL),(34,1,67,8,NULL,2,'拼色','submitted','not_sent','2026-11-20','pending','2026-11-25',NULL,1,'2026-11-25 14:30:39','2026-11-25 14:30:39',NULL,NULL),(35,1,76,4,NULL,1,'黑色','pending_submission','not_sent','2026-11-25','pending',NULL,NULL,1,'2026-11-25 14:31:09','2026-11-25 14:31:09',NULL,NULL),(36,1,68,7,NULL,2,NULL,'pending_submission','not_sent','2026-11-25','pending',NULL,NULL,1,'2026-11-25 14:31:43','2026-11-25 14:31:43',NULL,NULL),(37,1,75,7,NULL,1,NULL,'submitted','not_sent','2026-11-20','pending',NULL,NULL,1,'2026-11-25 14:33:15','2026-11-25 14:33:15',NULL,NULL),(38,1,72,10,NULL,1,NULL,'submitted','sent','2026-11-20','approved','2026-11-20',NULL,1,'2026-11-25 14:36:31','2026-11-25 14:36:31',NULL,NULL),(39,1,73,10,NULL,1,'黑色','submitted','sent','2026-11-20','approved','2026-11-20',NULL,1,'2026-11-25 14:37:03','2026-11-25 14:37:03',NULL,NULL),(40,1,69,10,NULL,1,'黑色','submitted','sent','2026-11-20','approved','2026-11-20',NULL,1,'2026-11-25 14:37:35','2026-11-25 14:37:35',NULL,NULL);
/*!40000 ALTER TABLE `Material_Samples` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Materials`
--

DROP TABLE IF EXISTS `Materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Materials` (
  `material_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `material_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specifications` text COLLATE utf8mb4_unicode_ci,
  `material_description_detailed` text COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `default_quotation_id` int DEFAULT NULL,
  `sample_required` enum('required','exempt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'required',
  `sample_requirement_explicit` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `uq_material_project_code` (`project_id`,`material_code`),
  UNIQUE KEY `uq_material_project_id` (`project_id`,`material_id`),
  KEY `idx_material_category` (`category_id`),
  KEY `idx_material_project_category` (`project_id`,`category_id`),
  KEY `idx_material_project_default_quotation` (`project_id`,`material_id`,`default_quotation_id`),
  CONSTRAINT `fk_material_category_o` FOREIGN KEY (`category_id`) REFERENCES `Material_Categories` (`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_project_category_o` FOREIGN KEY (`project_id`, `category_id`) REFERENCES `Material_Categories` (`project_id`, `category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_project_default_quotation_o` FOREIGN KEY (`project_id`, `material_id`, `default_quotation_id`) REFERENCES `Material_Quotations` (`project_id`, `material_id`, `quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_material_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Materials`
--

LOCK TABLES `Materials` WRITE;
/*!40000 ALTER TABLE `Materials` DISABLE KEYS */;
INSERT INTO `Materials` VALUES (2,2,'WD-01','木饰面','m^2','18厚','墙身，定制柜',1,NULL,'required',0,'2026-11-01 14:10:47','2026-11-08 02:56:36'),(3,2,'AL-01','白色300*40@100铝方通','m','300*40',NULL,1,NULL,'required',1,'2026-11-04 15:12:47','2026-11-25 12:38:06'),(4,2,'AL-02','2.0厚木纹铝板','m^2','2.0mm',NULL,1,NULL,'required',0,'2026-11-04 15:14:06','2026-11-05 13:48:35'),(5,2,'PT-01','白色无机涂料','m^2','白色墙漆','白色墙漆',1,NULL,'required',0,'2026-11-04 15:15:30','2026-11-05 13:48:35'),(6,2,'PT-02','深色无机涂料','m^2','深色',NULL,1,NULL,'required',0,'2026-11-04 15:16:05','2026-11-05 13:48:35'),(7,2,'PT-03','蓝色无机涂料','m^2','蓝色',NULL,1,NULL,'required',0,'2026-11-04 15:16:38','2026-11-05 13:48:35'),(8,2,'PT-04','橙色无机涂料','m^2','橙色',NULL,1,NULL,'required',0,'2026-11-04 15:17:12','2026-11-05 13:48:35'),(9,2,'PE-01','A级白色透光膜','m^2','白色软膜',NULL,1,NULL,'required',0,'2026-11-04 15:18:14','2026-11-05 13:48:35'),(10,2,'SPA-01','吸音板V缝抽槽 蓝色','m^2','抽v缝',NULL,1,NULL,'required',0,'2026-11-08 02:43:59','2026-11-08 02:43:59'),(12,2,'SPA-02','吸音板V缝抽槽 橙色','m^2',NULL,NULL,1,NULL,'required',0,'2026-11-08 02:45:51','2026-11-08 02:45:51'),(13,2,'ST-01','石英石台面','m^2',NULL,'未做注明，暂用1.5厚',1,NULL,'required',0,'2026-11-08 02:54:36','2026-11-08 02:54:36'),(14,2,'WD-02','蓝色烤漆板抽缝（吧台）','m^2','抽缝','图纸未标明抽缝大小',1,NULL,'required',0,'2026-11-08 02:57:39','2026-11-08 02:58:51'),(15,2,'WD-03','白色烤漆板(吧台)','m^2',NULL,NULL,1,NULL,'required',0,'2026-11-08 02:58:33','2026-11-08 02:58:33'),(16,2,'Door-01','防火门（FM甲1026A）','套','木饰面开门（多层板基层）定制\r\n2660*1020\r\n门扇900宽，带电子锁','门扇900宽，带电子锁',4,NULL,'required',0,'2026-11-08 03:01:23','2026-11-13 03:46:17'),(17,2,'Door-02','12厚超白钢化玻璃开门(M1126B)','套','3000*1020(带门头板)\r\n门扇高2600 \r\n(电子门锁或把手+门锁)','包银灰不锈钢门及门套',4,NULL,'exempt',0,'2026-11-08 03:03:13','2026-11-20 16:23:52'),(18,2,'Door-03','防火门（FM乙1124C）','套','2400*1100',NULL,4,NULL,'required',0,'2026-11-08 03:40:13','2026-11-13 03:41:42'),(19,2,'MT-01','40高1.2厚304不锈钢踢脚','m^2','40高1.2厚304不锈钢踢脚',NULL,1,NULL,'required',0,'2026-11-08 03:44:42','2026-11-08 03:44:42'),(20,2,'MT-02','2.5厚铝板背衬15厚铝蜂窝','m^2','2.5mm面+1.5mm蜂窝背板',NULL,1,NULL,'required',0,'2026-11-08 03:46:00','2026-11-08 03:46:00'),(21,2,'MT-03','白色铝板穿孔透光','m^2','白色铝板穿孔透光','原图纸未注明是包含亚克力',1,NULL,'required',0,'2026-11-08 03:46:50','2026-11-08 03:46:50'),(22,2,'MT-04','白色铝板','m^2',NULL,NULL,1,NULL,'required',0,'2026-11-08 03:47:36','2026-11-08 03:47:36'),(23,2,'MT-05','1.2mm白色锌纹金属板','m^2','1.2mm白色锌纹金属板+10mm蜂窝+0.6合金',NULL,1,NULL,'required',0,'2026-11-08 03:48:22','2026-11-08 03:48:31'),(24,2,'L-01','弧形灯具（900*7000）','个','900*7000弧形灯罩\r\n15宽LED铝合金线性灯带',NULL,5,NULL,'required',0,'2026-11-11 10:26:19','2026-11-15 02:21:35'),(25,2,'L-02','三角形灯具','个','边长800*800*800mm\r\n木纹铝方通定制',NULL,5,NULL,'required',0,'2026-11-11 10:31:44','2026-11-11 10:31:44'),(32,2,'Door-04','木门外包银灰不锈钢(M1024C)','套',NULL,NULL,4,NULL,'exempt',0,'2026-11-13 03:35:20','2026-11-20 16:24:36'),(33,2,'Door-05','12厚超白钢化玻璃双开门(M1526B)','套','1500*2600\r\n地弹簧玻璃门\r\n不锈钢门套线',NULL,4,NULL,'exempt',0,'2026-11-13 03:49:00','2026-11-20 16:22:28'),(34,2,'Door-06','12厚超白钢化玻璃门(M1122B)','套','1100*2200',NULL,4,NULL,'exempt',0,'2026-11-13 04:44:09','2026-11-20 16:24:18'),(35,2,'L-01B','弧形灯具（900*6000）','个','弧形灯具（900*6000）\r\n含灯具',NULL,5,NULL,'required',0,'2026-11-15 02:22:36','2026-11-15 03:04:16'),(36,2,'L-01C','弧形灯具（900*4800）','个','弧形灯具（900*4800）\r\n包含灯具',NULL,5,NULL,'required',0,'2026-11-15 02:23:47','2026-11-15 03:04:30'),(37,2,'L-01D','弧形灯具（900*4100）','个','弧形灯罩（900*4100）\r\n含灯具',NULL,5,NULL,'required',0,'2026-11-15 02:24:45','2026-11-15 03:04:41'),(38,2,'L-06','走道明装筒灯','个','走道明装筒灯',NULL,5,NULL,'required',0,'2026-11-15 02:31:52','2026-11-15 02:31:52'),(39,2,'L-07','嵌入式筒灯','个','12W 5000K',NULL,5,NULL,'required',0,'2026-11-15 02:34:44','2026-11-15 04:18:21'),(40,2,'L-03E','铝合金100宽黑色外壳吊灯(5600)','个','100*5600黑色',NULL,5,NULL,'required',0,'2026-11-15 02:48:55','2026-11-15 04:26:19'),(41,2,'L-03A','铝合金100宽蓝色外壳吊灯(10600)','个','100*10600',NULL,5,NULL,'required',0,'2026-11-15 03:10:46','2026-11-15 03:10:46'),(42,2,'L-03B','铝合金100宽蓝色外壳吊灯(23300)','个','100*(3800+15670+3800)',NULL,5,NULL,'required',0,'2026-11-15 03:13:21','2026-11-15 03:13:21'),(43,2,'L-03C','带半圆弧铝合金100宽蓝色外壳吊灯(10600)','个','100*10600\r\n外弧半径700',NULL,5,NULL,'required',0,'2026-11-15 04:10:33','2026-11-15 04:10:33'),(44,2,'L-04','办公室吊灯','个','1600长装饰吊灯选样',NULL,5,NULL,'required',0,'2026-11-15 04:11:40','2026-11-15 04:17:43'),(45,2,'L-03D','铝合金100宽白色外壳吊灯(2400)','个','100*2400\r\n白色外壳',NULL,5,NULL,'required',0,'2026-11-15 04:12:41','2026-11-15 04:12:41'),(46,2,'L-05','20宽铝合金线型灯','m','20*10',NULL,5,NULL,'exempt',0,'2026-11-15 04:21:17','2026-11-22 05:48:20'),(47,2,'L-08','装饰吊灯（台面）','个',NULL,'吧台',5,NULL,'required',0,'2026-11-15 04:26:37','2026-11-15 04:31:51'),(48,2,'L-08B','装饰吊灯','个','设计选型',NULL,5,NULL,'required',0,'2026-11-15 04:28:26','2026-11-15 04:28:26'),(49,2,'L-08C','直径500吊灯','个','直径500吊灯选样\r\n办公区',NULL,5,NULL,'required',0,'2026-11-15 04:29:39','2026-11-15 04:29:39'),(50,2,'L-08D','装饰吊灯','个','休息仓（三楼）\r\n图纸直径900',NULL,5,NULL,'required',0,'2026-11-15 04:30:22','2026-11-17 03:16:02'),(51,2,'L-09','50宽磁吸轨道	壁厚2.5 四线','m','壁厚2.5 四线 ra80',NULL,5,NULL,'required',0,'2026-11-15 04:32:38','2026-11-15 04:36:20'),(52,2,'L-09B','300宽磁吸面光灯','个','12W	5000K',NULL,5,NULL,'required',0,'2026-11-15 04:34:17','2026-11-15 04:34:17'),(53,2,'L-09C','300宽磁吸格栅射灯','个','18W	5000K',NULL,5,NULL,'required',0,'2026-11-15 04:35:44','2026-11-15 04:35:44'),(54,2,'L-10','600长、100宽铝合金外壳条形灯','个','600*100，12W/m',NULL,5,NULL,'required',0,'2026-11-15 04:38:05','2026-11-15 04:38:05'),(55,2,'L-10B','1200长、100宽铝合金外壳条形灯','个','1200*100 12W/m',NULL,5,NULL,'required',0,'2026-11-15 04:38:49','2026-11-15 04:38:49'),(56,2,'L-10C','1800长、100宽铝合金外壳条形灯','个','1800*100,12W/m',NULL,5,NULL,'required',0,'2026-11-15 04:39:15','2026-11-15 04:39:15'),(57,2,'L-05B','10宽铝合金线形灯','m','10*10','墙面及柜体内',5,NULL,'required',0,'2026-11-16 15:56:48','2026-11-16 15:56:48'),(58,2,'AL-03','800正三角形铝方通','个','800*100高','定制铝方通',1,NULL,'exempt',0,'2026-11-18 04:03:40','2026-11-22 09:04:57'),(59,1,'D01','2mm银色阳极氧化铝板','m^2','2mm厚','银色',6,NULL,'required',0,'2026-11-18 05:02:57','2026-11-18 05:02:57'),(60,1,'D02','2mm厚铝板粉末喷涂白色','m^2','2mm高光聚酯粉末喷涂/白色',NULL,6,NULL,'required',0,'2026-11-18 05:04:23','2026-11-18 05:04:23'),(61,1,'D03','12mm碳晶板（定制图案）','m^2','12mm','位于弧形屏两侧',6,NULL,'required',0,'2026-11-18 05:06:01','2026-11-18 05:44:43'),(62,1,'D04','U型磨砂玻璃','m^2','230mm*60mm','墙面造型',6,NULL,'required',0,'2026-11-18 05:06:51','2026-11-18 05:06:51'),(63,1,'D05','12mm烤漆板（亮光白）','m^2','12mm','展柜位置',6,NULL,'required',0,'2026-11-18 05:07:31','2026-11-18 05:44:18'),(64,1,'D06','12mm烤漆板（定制渐变）','m^2','2mm','走道墙面',6,NULL,'required',0,'2026-11-18 05:08:36','2026-11-18 05:16:33'),(65,1,'D07','12mm木纹木饰面','m^2','12mm','会议室内',6,NULL,'required',0,'2026-11-18 05:13:21','2026-11-18 05:44:07'),(66,1,'D08','12mm烤漆板（哑光白）','m^2','12mm','会议室',6,NULL,'required',0,'2026-11-18 05:14:27','2026-11-18 05:44:31'),(67,1,'D09','2mm地胶板深灰/浅灰','m^2','8mm','展厅地面',6,NULL,'required',0,'2026-11-18 05:17:48','2026-11-18 05:19:08'),(68,1,'D10','5mm地毯','m^2','5mm。灰色同心圆纹路',NULL,6,NULL,'required',0,'2026-11-18 05:19:59','2026-11-18 05:19:59'),(69,1,'D11','800*800玻化砖','m^2','黑色镜面800*800',NULL,6,NULL,'required',0,'2026-11-18 05:20:31','2026-11-18 05:20:31'),(70,1,'D13','20mm灰色门槛石','m^2','20mm',NULL,6,NULL,'required',0,'2026-11-18 05:20:58','2026-11-18 05:25:09'),(71,1,'D14','15mm木纹复合地板','m^2','15mm木纹','讲台',6,NULL,'required',0,'2026-11-18 05:21:30','2026-11-18 05:24:51'),(72,1,'D15','5mm银镜碳晶板','m^2','5mm银镜',NULL,6,NULL,'required',0,'2026-11-18 05:22:00','2026-11-18 05:24:44'),(73,1,'D16','5mm黑镜碳晶板','m^2','5mm黑镜',NULL,6,NULL,'required',0,'2026-11-18 05:22:28','2026-11-18 05:24:32'),(74,1,'D17','2mm银色波纹板','m^2','2mm银色波纹板',NULL,6,NULL,'required',0,'2026-11-18 05:22:58','2026-11-18 05:24:21'),(75,1,'D12','600*600灰色地毯','m^2','600*600灰色','多媒体室',6,NULL,'required',0,'2026-11-18 05:25:51','2026-11-18 05:25:51'),(76,1,'D18','40mm黑色不锈钢内凹踢脚线','m^2','40mm',NULL,6,NULL,'required',0,'2026-11-18 05:26:43','2026-11-18 05:26:43'),(77,1,'D19','窄边白色风口','m',NULL,NULL,6,NULL,'required',0,'2026-11-18 05:27:14','2026-11-18 05:27:14'),(120,2,'CT-01','75*300复古砖','m^2','75*300蓝色','水槽后',1,NULL,'required',0,'2026-11-18 12:58:16','2026-11-18 12:58:16'),(121,2,'PT-05','仿水泥艺术涂料','m^2','仿水泥',NULL,1,NULL,'required',0,'2026-11-18 13:17:31','2026-11-18 13:17:31'),(122,2,'PT-06','深色艺术涂料','m^2','圆弧造型休息仓外侧',NULL,1,NULL,'required',0,'2026-11-18 13:19:21','2026-11-18 13:19:21'),(123,2,'PT-07','浅色艺术涂料','m^2','圆弧造型休息仓内侧',NULL,1,NULL,'required',0,'2026-11-18 13:20:03','2026-11-18 13:20:03'),(124,2,'GL-01','12厚超白钢化玻璃','m^2',NULL,NULL,1,NULL,'required',1,'2026-11-20 16:20:15','2026-11-20 16:21:36'),(125,2,'MT-06','1.2厚304不锈钢','m^2',NULL,NULL,1,NULL,'required',0,'2026-11-20 16:21:22','2026-11-20 16:21:22'),(126,2,'RM-01','软膜天花','m^2','含灯',NULL,1,NULL,'exempt',0,'2026-11-22 04:46:17','2026-11-22 04:47:19'),(128,2,'RM-02','软膜','m^2',NULL,NULL,1,NULL,'required',0,'2026-11-22 04:47:06','2026-11-22 04:47:06'),(129,2,'L-AL-2.0','20*10线型灯线型材','m','20*10',NULL,1,NULL,'required',0,'2026-11-22 05:46:30','2026-11-22 05:46:30'),(130,2,'L-L-01','贴片灯10W/m','m','10W/m',NULL,1,NULL,'required',0,'2026-11-22 05:47:16','2026-11-22 05:47:16'),(131,2,'MT-TJIAO','不锈钢踢脚','m','40*10',NULL,1,NULL,'exempt',0,'2026-11-22 07:25:51','2026-11-22 07:39:07'),(132,2,'ZT-01','吧台','个','3400*760',NULL,7,NULL,'exempt',0,'2026-11-22 09:54:31','2026-11-22 10:02:51'),(133,2,'GL-02','8厚超白钢化玻璃贴渐变膜','m^2','8mm',NULL,1,NULL,'required',0,'2026-11-22 09:59:13','2026-11-22 09:59:13'),(134,2,'MT-08','1.5mm304黑色不锈钢','m^2','1.5mm厚',NULL,1,NULL,'required',1,'2026-11-22 10:22:36','2026-11-25 05:24:27'),(135,2,'L-09h','磁吸轨道','m','30宽磁吸轨道',NULL,5,NULL,'exempt',1,'2026-11-23 13:12:09','2026-11-23 13:18:30'),(136,2,'ZT-02','茶水台1（办公区B2）','组','3485*（850+800）\r\n2组',NULL,7,NULL,'exempt',1,'2026-11-25 05:07:57','2026-11-25 05:18:06'),(137,2,'ZT-03','茶水台2（办公区D）','组','L型地柜6.04m长',NULL,7,NULL,'exempt',1,'2026-11-25 05:20:35','2026-11-25 05:23:08'),(138,2,'ZT-04','文印台3（三层办公区C1）','组','（2368+1648）*（850+800）\r\n两组柜','柜体+石英石台面',7,NULL,'exempt',1,'2026-11-25 05:28:08','2026-11-25 05:36:33'),(139,2,'ZT-05','茶水台4（三层办公区A1）','组','1680*（800+850）\r\n地柜+吊柜','柜体+石英石台面',7,NULL,'exempt',0,'2026-11-25 05:31:16','2026-11-25 05:31:39'),(140,2,'ZT-06','茶水台5（办公区C2）','组','4500*（800*850）地柜+吊柜','柜体+石英石台面',7,NULL,'exempt',0,'2026-11-25 05:32:44','2026-11-25 05:33:02'),(141,2,'CP-01','不锈钢水槽（390*500）','个',NULL,NULL,2,NULL,'required',0,'2026-11-25 05:35:08','2026-11-25 05:35:08'),(142,2,'CT-01H','75*300复古砖','m^2','75*300\r\n结构胶粘贴',NULL,1,NULL,'exempt',0,'2026-11-25 06:52:47','2026-11-25 06:52:57'),(143,2,'RC-AL-01H','白色/蓝色300*40@100铝方通','项','300*40@100铝方通','安装及人工',1,NULL,'required',0,'2026-11-25 12:35:03','2026-11-25 12:35:03'),(144,2,'AL-01a','蓝色300*40@100铝方通 (m)','m','蓝色300*40@100铝方通',NULL,1,NULL,'required',0,'2026-11-25 12:37:47','2026-11-25 12:37:47'),(145,2,'L02H','三角形灯具','个','600*600*600正三角形灯','包含安装',5,NULL,'exempt',0,'2026-11-27 02:16:55','2026-11-27 02:17:09'),(146,2,'GL-03','8mm厚橙色烤漆玻璃','m^2','8mm厚',NULL,1,NULL,'required',0,'2026-11-27 02:25:55','2026-11-27 02:25:55'),(147,2,'GL-03H','8mm厚橙色烤漆玻璃安装','m^2','玻璃+安装',NULL,1,NULL,'exempt',0,'2026-11-27 02:26:32','2026-11-27 02:26:41');
/*!40000 ALTER TABLE `Materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Permissions`
--

DROP TABLE IF EXISTS `Permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `module` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permissions_name` (`permission_name`)
) ENGINE=InnoDB AUTO_INCREMENT=850 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Permissions`
--

LOCK TABLES `Permissions` WRITE;
/*!40000 ALTER TABLE `Permissions` DISABLE KEYS */;
INSERT INTO `Permissions` VALUES (1,'view_projects','查看项目','允许查看并选择项目','Project Foundation'),(2,'create_projects','创建项目','允许创建项目','Project Foundation'),(3,'view_issues','查看问题与 RFI','允许查看当前项目的问题和 RFI','文档管理'),(4,'create_issues','创建问题与 RFI','允许在当前项目登记问题并创建 RFI','文档管理'),(5,'edit_issues','处理问题与 RFI','允许编辑问题与 RFI 信息、状态、答复和后续事项','文档管理'),(6,'manage_issues','管理问题与 RFI','允许重新打开已关闭的问题或 RFI','文档管理'),(9,'manage_platform','管理平台','管理用户、角色权限、插件和系统设置','平台管理'),(10,'view_project_spaces','查看项目空间','允许查看当前项目楼栋、楼层、区域和房间','Project Foundation'),(11,'manage_project_spaces','管理项目空间','允许维护当前项目楼栋、楼层、区域和房间','Project Foundation'),(12,'view_materials','查看材料','查看当前项目的材料管理和材料测算所用材料','材料与样品跟踪'),(13,'edit_materials','编辑材料','维护当前项目的材料和分类','材料与样品跟踪'),(14,'delete_materials','删除材料','删除当前项目中未被业务记录引用的材料或分类','材料与样品跟踪'),(15,'view_brands','查看品牌','查看全局品牌目录','材料与样品跟踪'),(16,'edit_brands','编辑品牌','维护品牌','材料与样品跟踪'),(17,'delete_brands','删除品牌','删除未被供应商材料引用的品牌','材料与样品跟踪'),(18,'edit_auxiliary_materials','编辑辅材','维护当前项目辅材目录','材料与样品跟踪'),(19,'view_suppliers','查看供应商','查看全局供应商目录','材料与样品跟踪'),(20,'edit_suppliers','编辑供应商','维护供应商信息','材料与样品跟踪'),(21,'view_supplier_materials','查看供应商材料','查看供应商产品及其品牌/规格','材料与样品跟踪'),(22,'edit_supplier_materials','编辑供应商材料','维护供应商材料','材料与样品跟踪'),(23,'view_material_quotations','查看材料报价','查看当前项目的报价记录与默认报价','材料与样品跟踪'),(24,'edit_material_quotations','编辑材料报价','维护当前项目的供应商报价并人工选择默认报价','材料与样品跟踪'),(25,'view_material_samples','查看材料样品','查看当前项目的样品跟踪记录和受控图片','材料与样品跟踪'),(26,'add_sample','登记材料样品','在当前项目登记材料、供样供应商和位置；报价为可选信息','材料与样品跟踪'),(27,'edit_sample','编辑材料样品','维护当前项目样品状态并上传图片','材料与样品跟踪'),(28,'delete_sample','归档材料样品','归档当前项目样品，不硬删除记录或图片','材料与样品跟踪'),(29,'view_material_estimates','查看材料测算明细','查看当前项目按空间归属的材料数量和备注','材料与样品跟踪'),(30,'edit_material_estimates','编辑材料测算明细','维护当前项目材料测算数量和备注','材料与样品跟踪'),(61,'delete_projects','删除与归档项目','允许删除空项目、归档含有关联记录的项目并恢复已归档项目','Project Foundation'),(87,'view_tenders','查看招标清单','查看当前项目按材料记录的招标数量和单价','材料与样品跟踪'),(88,'edit_tender_list','编辑招标清单','维护当前项目招标清单的材料、数量、单价和币种','材料与样品跟踪'),(121,'manage_document_settings','配置文件模块系统设置','维护图片对象存储等文件模块系统设置','文档管理'),(132,'view_labor_crews','查看施工班组','查看当前项目施工班组的负责人姓名、电话等联系方式','施工班组'),(133,'view_labor_crew_id_numbers','查看负责人身份证号','在当前项目班组页面完整显示已加密保存的负责人身份证号；未授予时只显示脱敏号码','施工班组'),(134,'edit_labor_crews','编辑施工班组','在当前项目登记和维护班组、工种、负责人联系方式与备注','施工班组'),(135,'archive_labor_crews','归档施工班组','归档或恢复当前项目的班组；归档保留记录与加密信息，不硬删除','施工班组'),(136,'view_labor_crew_types','查看工种目录','查看跨项目共享的工种目录','施工班组'),(137,'edit_labor_crew_types','编辑工种目录','维护跨项目共享的工种名称与排序','施工班组'),(212,'share_labor_crews','分享施工班组联系人','为当前项目开启或停用班组联系人公开分享链接；持有链接者无需登录即可查看在用班组的工种、班组、负责人姓名、电话与备注，并可直接拨号','施工班组'),(549,'view_costings','查看组价','查看当前项目的 BOM 组价、当前态明细与缺项','BOM 组价与成本'),(550,'edit_costings','编辑组价','维护组价资源行、数量与来源，确认组价结构','BOM 组价与成本'),(551,'view_cost_catalog','查看资源目录','查看共享的加工/机械/人工资源目录','BOM 组价与成本'),(552,'edit_cost_catalog','编辑资源目录','维护共享加工/机械/人工目录项','BOM 组价与成本'),(553,'view_actual_costs','查看实际费用','查看当前项目实际费用、冲销调整与分摊统计','BOM 组价与成本'),(554,'edit_actual_costs','登记实际费用','录入实际费用、冲销、调整与分摊','BOM 组价与成本'),(632,'view_closeouts','查看工程消项','查看消项计划、事项、进度与验收记录','工程消项'),(633,'create_closeouts','创建消项计划与纳入事项','新建消项计划并自动纳入已测算的父材料','工程消项'),(634,'edit_closeouts','维护消项执行信息','编辑事项管理信息、记录进度、开始执行、提交验收、上传附件','工程消项'),(635,'accept_closeouts','验收消项','对提交验收的事项给出通过或不通过结论','工程消项'),(636,'manage_closeouts','管理消项计划与事项','启动/关闭/重开计划，取消/恢复/重新打开事项','工程消项');
/*!40000 ALTER TABLE `Permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Plugin_States`
--

DROP TABLE IF EXISTS `Plugin_States`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Plugin_States` (
  `plugin_id` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Plugin_States`
--

LOCK TABLES `Plugin_States` WRITE;
/*!40000 ALTER TABLE `Plugin_States` DISABLE KEYS */;
INSERT INTO `Plugin_States` VALUES ('material.management',1,1,'2026-11-01 13:48:46');
/*!40000 ALTER TABLE `Plugin_States` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ProjectO_Migrations`
--

DROP TABLE IF EXISTS `ProjectO_Migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ProjectO_Migrations` (
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ProjectO_Migrations`
--

LOCK TABLES `ProjectO_Migrations` WRITE;
/*!40000 ALTER TABLE `ProjectO_Migrations` DISABLE KEYS */;
INSERT INTO `ProjectO_Migrations` VALUES ('202611010001_core.sql','2026-11-01 10:37:24'),('202611010003_platform_administration.sql','2026-11-01 12:28:20'),('202611070001_utc_timezone.sql','2026-11-06 16:21:54'),('202611070002_platform_name.sql','2026-11-11 06:00:12'),('202611220001_result_composition_fk_cascade.sql','2026-11-22 10:35:26'),('202611250002_user_display_name.sql','2026-11-25 12:22:27'),('bom.costing/202611200001_bom_costing_core.sql','2026-11-20 16:36:13'),('bom.costing/202611200002_extend_existing_tables.sql','2026-11-20 16:36:13'),('bom.costing/202611220001_result_composition_fk_cascade.sql','2026-11-25 07:37:40'),('closeout.management/202611220001_closeout_management.sql','2026-11-22 05:23:33'),('closeout.management/202611220002_closeout_live_bom.sql','2026-11-22 06:51:24'),('closeout.management/202611220003_closeout_title_as_label.sql','2026-11-22 06:57:09'),('closeout.management/202611270001_closeout_locations.sql','2026-11-27 13:44:55'),('closeout.management/202611270002_closeout_location_refs.sql','2026-11-27 13:44:56'),('document.management/202611010002_document_management.sql','2026-11-01 12:28:20'),('document.management/202611040001_project_scoped_document_numbers.sql','2026-11-04 04:48:43'),('document.management/202611060001_issue_rfi_images.sql','2026-11-06 15:18:52'),('document.management/202611070001_utc_datetime_normalization.sql','2026-11-06 16:21:54'),('document.management/202611150001_issue_space_location.sql','2026-11-15 08:51:27'),('document.management/202611150002_issue_location_note.sql','2026-11-15 09:41:12'),('document.management/202611250001_issue_rfi_image_external_url.sql','2026-11-25 07:37:41'),('labor.crew/202611110001_labor_crews.sql','2026-11-11 06:00:13'),('labor.crew/202611110002_labor_crew_sort_order.sql','2026-11-11 08:39:30'),('labor.crew/202611130001_labor_crew_shares.sql','2026-11-13 02:06:04'),('material.management/202611010005_material_management.sql','2026-11-01 13:53:39'),('material.management/202611040001_decouple_samples_from_quotations.sql','2026-11-04 15:58:42'),('material.management/202611050001_project_materials_and_tenders.sql','2026-11-05 13:38:22'),('material.management/202611050002_tender_currency_default_cny.sql','2026-11-05 14:50:12'),('material.management/202611180001_material_compositions.sql','2026-11-18 11:35:08'),('material.management/202611180002_material_sample_requirement_explicit.sql','2026-11-18 12:27:42'),('project.foundation/202611010004_project_spaces.sql','2026-11-01 13:53:38'),('project.foundation/202611010006_project_archiving.sql','2026-11-04 04:40:39');
/*!40000 ALTER TABLE `ProjectO_Migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Projects`
--

DROP TABLE IF EXISTS `Projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Projects` (
  `project_id` int NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','ongoing','completed','on-hold') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planning',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`),
  KEY `idx_projects_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Projects`
--

LOCK TABLES `Projects` WRITE;
/*!40000 ALTER TABLE `Projects` DISABLE KEYS */;
INSERT INTO `Projects` VALUES (1,'火山引擎展厅','2026-10-21','2026-12-31','ongoing','2026-11-01 11:00:25','2026-11-01 11:00:25'),(2,'Coding办公室装修','2026-10-26','2026-11-30','ongoing','2026-11-04 03:12:38','2026-11-04 03:12:38');
/*!40000 ALTER TABLE `Projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Role_Permissions`
--

DROP TABLE IF EXISTS `Role_Permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Role_Permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `Permissions` (`permission_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `Roles` (`role_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Role_Permissions`
--

LOCK TABLES `Role_Permissions` WRITE;
/*!40000 ALTER TABLE `Role_Permissions` DISABLE KEYS */;
INSERT INTO `Role_Permissions` VALUES (1,1),(3,1),(1,2),(3,2),(1,3),(3,3),(1,4),(3,4),(1,5),(3,5),(1,6),(3,6),(1,9),(1,10),(3,10),(1,11),(3,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(3,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,61),(1,87),(1,88),(1,121),(1,132),(1,133),(1,134),(1,135),(1,136),(1,137),(1,212),(1,549),(1,550),(1,551),(1,552),(1,553),(1,554),(1,632),(1,633),(1,634),(1,635),(1,636);
/*!40000 ALTER TABLE `Role_Permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Roles`
--

DROP TABLE IF EXISTS `Roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Roles` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Roles`
--

LOCK TABLES `Roles` WRITE;
/*!40000 ALTER TABLE `Roles` DISABLE KEYS */;
INSERT INTO `Roles` VALUES (1,'administrator','Platform administrator','2026-11-01 10:37:24'),(3,'manager','看查项目，管理项目','2026-11-01 12:32:22');
/*!40000 ALTER TABLE `Roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Rooms`
--

DROP TABLE IF EXISTS `Rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Rooms` (
  `room_id` int NOT NULL AUTO_INCREMENT,
  `building_id` int NOT NULL,
  `floor_id` int NOT NULL,
  `room_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_area` decimal(10,2) DEFAULT NULL,
  `room_height` decimal(5,2) DEFAULT NULL,
  `room_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '空闲',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `uq_room_id_building_floor` (`room_id`,`building_id`,`floor_id`),
  UNIQUE KEY `uq_room_floor_number` (`floor_id`,`room_number`),
  KEY `fk_rooms_floor_building_project_o` (`floor_id`,`building_id`),
  CONSTRAINT `fk_rooms_floor_building_project_o` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Rooms`
--

LOCK TABLES `Rooms` WRITE;
/*!40000 ALTER TABLE `Rooms` DISABLE KEYS */;
INSERT INTO `Rooms` VALUES (1,4,3,'单间2','办公室',NULL,NULL,'空闲','2026-11-13 04:04:42','2026-11-13 04:17:03'),(2,4,3,'单间1','办公室',NULL,NULL,'空闲','2026-11-13 04:11:25','2026-11-13 04:11:25'),(4,4,3,'单间3','办公室',NULL,NULL,'空闲','2026-11-13 04:25:22','2026-11-13 04:25:22'),(5,4,3,'单间4','办公室',NULL,NULL,'空闲','2026-11-13 04:25:29','2026-11-13 04:25:29'),(6,4,3,'单间5','办公室',NULL,NULL,'空闲','2026-11-13 04:25:35','2026-11-13 04:25:35'),(7,4,3,'单间6','办公室',NULL,NULL,'空闲','2026-11-13 04:25:43','2026-11-13 04:25:43'),(8,4,3,'前厅','接待区',NULL,NULL,'空闲','2026-11-13 04:27:07','2026-11-13 04:27:07'),(9,4,3,'公共公办区','公共办公区',NULL,NULL,'空闲','2026-11-13 04:27:28','2026-11-13 04:27:28'),(10,4,3,'排烟机房','功能房',NULL,NULL,'空闲','2026-11-13 04:36:29','2026-11-13 04:36:29'),(11,4,3,'走道','核心筒中心走道',NULL,NULL,'空闲','2026-11-13 04:39:24','2026-11-13 04:39:24'),(12,4,3,'中会议室',NULL,NULL,NULL,'空闲','2026-11-13 04:40:42','2026-11-13 04:40:42'),(13,4,3,'弱电机房',NULL,NULL,NULL,'空闲','2026-11-13 04:40:52','2026-11-13 04:40:52'),(14,4,2,'小会议室','会议室',NULL,NULL,'空闲','2026-11-13 04:42:24','2026-11-13 04:47:47'),(15,4,2,'小会议室1','会议室',NULL,NULL,'空闲','2026-11-13 04:42:32','2026-11-13 04:47:51'),(16,4,2,'洗漱','卫生间',NULL,NULL,'空闲','2026-11-13 04:46:48','2026-11-13 04:47:59'),(17,4,2,'产业基金','办公室',NULL,NULL,'空闲','2026-11-13 04:47:26','2026-11-13 04:47:37'),(18,4,2,'单间1','办公室',NULL,NULL,'空闲','2026-11-13 04:48:34','2026-11-13 04:48:34'),(19,4,2,'单间2','办公室',NULL,NULL,'空闲','2026-11-13 04:48:40','2026-11-13 04:48:40'),(20,4,2,'单间3','办公室',NULL,NULL,'空闲','2026-11-13 04:48:47','2026-11-13 04:48:47'),(21,4,2,'办公区北 74人',NULL,NULL,NULL,'空闲','2026-11-17 04:09:33','2026-11-17 04:09:33'),(22,4,2,'前厅',NULL,NULL,NULL,'空闲','2026-11-17 04:57:34','2026-11-17 04:57:34'),(23,4,3,'沙龙客厅',NULL,NULL,NULL,'空闲','2026-11-17 05:00:28','2026-11-17 05:00:28'),(24,4,3,'头脑风暴',NULL,NULL,NULL,'空闲','2026-11-18 13:05:12','2026-11-18 13:05:12'),(25,4,3,'半开放洽谈',NULL,NULL,NULL,'空闲','2026-11-18 15:12:42','2026-11-18 15:12:42'),(26,4,2,'休闲工位','开放区',NULL,NULL,'空闲','2026-11-25 05:41:45','2026-11-25 05:41:45'),(27,4,3,'文印区','开放区',NULL,NULL,'空闲','2026-11-25 05:43:13','2026-11-25 05:43:13'),(28,1,1,'序厅',NULL,NULL,NULL,'空闲','2026-11-25 14:03:15','2026-11-25 14:03:15'),(29,1,1,'总述-豆包大模型家族','滑轨屏位',NULL,NULL,'空闲','2026-11-25 14:03:46','2026-11-25 14:03:46'),(30,1,1,'豆包大模型2.1 Pro',NULL,NULL,NULL,'空闲','2026-11-25 14:04:15','2026-11-25 14:04:15'),(31,1,1,'视频生成模型',NULL,NULL,NULL,'空闲','2026-11-25 14:04:29','2026-11-25 14:04:29'),(32,1,1,'语音模型',NULL,NULL,NULL,'空闲','2026-11-25 14:04:48','2026-11-25 14:04:48'),(33,1,1,'图像创作模型',NULL,NULL,NULL,'空闲','2026-11-25 14:05:03','2026-11-25 14:05:03'),(34,1,1,'工作、影视、教育',NULL,NULL,NULL,'空闲','2026-11-25 14:05:18','2026-11-25 14:05:18'),(35,1,1,'MaaS',NULL,NULL,NULL,'空闲','2026-11-25 14:05:34','2026-11-25 14:05:34'),(36,1,1,'Agent',NULL,NULL,NULL,'空闲','2026-11-25 14:05:46','2026-11-25 14:05:46'),(37,1,1,'杭州区域案例及火山行业案例',NULL,NULL,NULL,'空闲','2026-11-25 14:05:57','2026-11-25 14:05:57'),(38,1,1,'客户LOGO墙',NULL,NULL,NULL,'空闲','2026-11-25 14:06:06','2026-11-25 14:06:06'),(39,1,1,'生态与展望',NULL,NULL,NULL,'空闲','2026-11-25 14:06:18','2026-11-25 14:06:18'),(40,1,1,'结语',NULL,NULL,NULL,'空闲','2026-11-25 14:06:30','2026-11-25 14:06:30'),(41,1,1,'演播厅','会议室',NULL,NULL,'空闲','2026-11-25 14:06:52','2026-11-25 14:06:52');
/*!40000 ALTER TABLE `Rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Sample_Location_Map`
--

DROP TABLE IF EXISTS `Sample_Location_Map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Sample_Location_Map` (
  `sample_id` int NOT NULL,
  `project_id` int NOT NULL,
  `location_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_id` int NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`sample_id`),
  KEY `idx_sample_location_project` (`project_id`,`location_type`,`location_id`),
  KEY `fk_sample_location_sample_o` (`sample_id`,`project_id`),
  CONSTRAINT `fk_sample_location_project_o` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sample_location_sample_o` FOREIGN KEY (`sample_id`, `project_id`) REFERENCES `Material_Samples` (`sample_id`, `project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Sample_Location_Map`
--

LOCK TABLES `Sample_Location_Map` WRITE;
/*!40000 ALTER TABLE `Sample_Location_Map` DISABLE KEYS */;
INSERT INTO `Sample_Location_Map` VALUES (1,2,'floor',2,NULL),(2,2,'building',4,NULL),(3,2,'floor',2,'所有木饰面柜体及木饰面全部使用这个颜色'),(4,2,'floor',3,NULL),(5,2,'building',4,NULL),(6,2,'building',4,NULL),(7,2,'floor',3,'有修改更深一个颜色'),(8,2,'building',4,NULL),(9,2,'building',4,NULL),(10,2,'building',4,NULL),(11,2,'building',4,NULL),(20,2,'building',4,NULL),(21,2,'floor',3,NULL),(22,2,'floor',3,NULL),(23,2,'building',4,NULL),(24,2,'building',4,NULL),(25,2,'building',4,NULL),(26,1,'room',41,NULL),(27,1,'area',1,NULL),(28,1,'floor',1,NULL),(29,1,'floor',1,'可以贴膜'),(30,1,'room',41,NULL),(31,1,'floor',1,NULL),(32,1,'floor',1,NULL),(33,1,'room',29,NULL),(34,1,'floor',1,NULL),(35,1,'floor',1,NULL),(36,1,'room',41,NULL),(37,1,'room',41,NULL),(38,1,'area',1,NULL),(39,1,'area',1,NULL),(40,1,'area',1,NULL);
/*!40000 ALTER TABLE `Sample_Location_Map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Supplier_Materials`
--

DROP TABLE IF EXISTS `Supplier_Materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Supplier_Materials` (
  `supplier_material_id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `brand_id` int DEFAULT NULL,
  `supplier_material_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_material_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_time_days` int DEFAULT NULL,
  `material_details` text COLLATE utf8mb4_unicode_ci,
  `specification` text COLLATE utf8mb4_unicode_ci,
  `coverage_quantity` decimal(18,4) DEFAULT NULL,
  `coverage_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_total` decimal(18,4) DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_material_id`),
  UNIQUE KEY `uq_supplier_material_code` (`supplier_id`,`supplier_material_code`),
  KEY `idx_supplier_material_brand` (`brand_id`),
  CONSTRAINT `fk_supplier_material_brand_o` FOREIGN KEY (`brand_id`) REFERENCES `Brands` (`brand_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_material_supplier_o` FOREIGN KEY (`supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Supplier_Materials`
--

LOCK TABLES `Supplier_Materials` WRITE;
/*!40000 ALTER TABLE `Supplier_Materials` DISABLE KEYS */;
INSERT INTO `Supplier_Materials` VALUES (1,3,3,'LED-01','123',30,NULL,NULL,1.0000,'m^2',NULL,NULL,'2026-11-01 14:06:04','2026-11-17 15:25:55'),(2,2,2,'WD-01','木饰面',20,NULL,NULL,1.0000,'m^2',350.0000,'CNY','2026-11-01 14:09:15','2026-11-01 14:09:15'),(3,4,4,'GL-10S','10mm超白钢化玻璃',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-18 05:32:59','2026-11-18 05:32:59'),(4,4,NULL,'MT','不锈钢',NULL,NULL,NULL,1.0000,'m^2',115.0000,NULL,'2026-11-22 10:26:24','2026-11-22 10:26:24');
/*!40000 ALTER TABLE `Supplier_Materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Suppliers`
--

DROP TABLE IF EXISTS `Suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Suppliers` (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `country_code` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  KEY `idx_supplier_name` (`supplier_name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Suppliers`
--

LOCK TABLES `Suppliers` WRITE;
/*!40000 ALTER TABLE `Suppliers` DISABLE KEYS */;
INSERT INTO `Suppliers` VALUES (1,'灯具浙江铭燕居','邵燕','13735499399',NULL,NULL,NULL,'2026-11-01 14:05:06','2026-11-23 14:58:36'),(2,'杨翔木饰面','杨翔',NULL,NULL,NULL,NULL,'2026-11-01 14:08:26','2026-11-23 14:57:51'),(3,'凤西油漆','骆晓军',NULL,NULL,NULL,NULL,'2026-11-07 01:59:25','2026-11-23 14:57:37'),(4,'邵建军','邵建军','13655812860',NULL,NULL,NULL,'2026-11-07 07:51:36','2026-11-07 07:51:36'),(5,'防火门','防火门','13588372195',NULL,NULL,NULL,'2026-11-13 05:01:01','2026-11-13 05:03:33'),(6,'石材供应商',NULL,NULL,NULL,NULL,NULL,'2026-11-18 04:30:53','2026-11-18 04:30:53'),(7,'维美地毯','郑','13656819900',NULL,NULL,NULL,'2026-11-25 14:20:49','2026-11-25 14:20:49'),(8,'火山-星耀仓库实木.三层.强化.木塑.静电','地板','13957149106',NULL,NULL,NULL,'2026-11-25 14:24:23','2026-11-25 14:24:23'),(9,'凤栖大理石石材','张','13634138850',NULL,NULL,NULL,'2026-11-25 14:26:42','2026-11-25 14:27:09'),(10,'杭港建材（板材）','周','180 6976 6957',NULL,NULL,NULL,'2026-11-25 14:35:29','2026-11-25 14:35:43'),(11,'铝方通','刘好平','18857879678',NULL,NULL,NULL,'2026-11-27 12:31:22','2026-11-27 12:31:22');
/*!40000 ALTER TABLE `Suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `System_Settings`
--

DROP TABLE IF EXISTS `System_Settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `System_Settings` (
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `System_Settings`
--

LOCK TABLES `System_Settings` WRITE;
/*!40000 ALTER TABLE `System_Settings` DISABLE KEYS */;
INSERT INTO `System_Settings` VALUES ('admin_guard','1',NULL,'2026-11-01 12:28:20'),('cloud_storage.access_key_id','ebaf64f37810cd807eeddd87b278831f',1,'2026-11-06 15:16:36'),('cloud_storage.bucket_name','eeu',1,'2026-11-06 15:16:36'),('cloud_storage.enabled','1',1,'2026-11-06 15:16:36'),('cloud_storage.r2_account_id','9d812a7b482860f6f7c60ae6ec0ac47f',1,'2026-11-06 15:16:36'),('cloud_storage.secret_access_key','d71e820476edc88ef208fe46c73f10f27b3f0cfaade895251cd88e0cc17b2f4d',1,'2026-11-06 15:16:36'),('default_country_code','CN',1,'2026-11-01 12:30:26'),('default_currency_code','CNY',1,'2026-11-01 12:30:26'),('document.issue.export_sequence.20261107','1',1,'2026-11-06 16:29:00'),('document.issue.export_sequence.20261115','3',1,'2026-11-15 10:00:13'),('document.issue.export_sequence.20261125','1',1,'2026-11-25 12:08:24'),('document.issue.export_sequence.20261127','2',1,'2026-11-27 06:20:30'),('labor.crew.export_sequence.20261111','12',1,'2026-11-11 09:36:38'),('labor.crew.export_sequence.20261113','2',1,'2026-11-12 16:08:08'),('language','zh-CN',NULL,'2026-11-01 12:28:20'),('material.demand.export_sequence.20261123','3',1,'2026-11-23 13:30:32'),('material.demand.export_sequence.20261127','1',1,'2026-11-27 03:13:35'),('material.estimate.export_sequence.20261113','5',1,'2026-11-13 05:56:58'),('material.estimate.export_sequence.20261117','1',1,'2026-11-17 04:20:47'),('material.estimate.export_sequence.20261118','9',1,'2026-11-18 15:40:01'),('material.estimate.export_sequence.20261120','2',1,'2026-11-20 03:19:47'),('material.estimate.export_sequence.20261121','2',1,'2026-11-21 09:06:14'),('material.estimate.export_sequence.20261123','1',1,'2026-11-23 05:27:50'),('material.sample.export_sequence.20261106','3',1,'2026-11-06 16:13:08'),('material.sample.export_sequence.20261107','1',1,'2026-11-07 14:20:19'),('material.sample.export_sequence.20261117','3',1,'2026-11-17 15:23:17'),('platform_name','Project 工程管理系统',1,'2026-11-06 16:58:07'),('theme','dark',1,'2026-11-25 13:23:38'),('timezone','Asia/Shanghai',1,'2026-11-06 16:32:20');
/*!40000 ALTER TABLE `System_Settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Tender_List`
--

DROP TABLE IF EXISTS `Tender_List`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Tender_List` (
  `tender_item_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `material_id` int NOT NULL,
  `tender_quantity` decimal(12,2) NOT NULL,
  `tender_unit_price` decimal(16,2) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `status` enum('pending','approved','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tender_item_id`),
  KEY `idx_tender_project_material` (`project_id`,`material_id`),
  CONSTRAINT `fk_tender_project_material_o` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Tender_List`
--

LOCK TABLES `Tender_List` WRITE;
/*!40000 ALTER TABLE `Tender_List` DISABLE KEYS */;
/*!40000 ALTER TABLE `Tender_List` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `User_Roles`
--

DROP TABLE IF EXISTS `User_Roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `User_Roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `Roles` (`role_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `User_Roles`
--

LOCK TABLES `User_Roles` WRITE;
/*!40000 ALTER TABLE `User_Roles` DISABLE KEYS */;
INSERT INTO `User_Roles` VALUES (1,1);
/*!40000 ALTER TABLE `User_Roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Users`
--

DROP TABLE IF EXISTS `Users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Users`
--

LOCK TABLES `Users` WRITE;
/*!40000 ALTER TABLE `Users` DISABLE KEYS */;
INSERT INTO `Users` VALUES (1,'ooofree','李泽沁','$2y$10$zauLDUxu8m5YqJ9VD3JcUeDkDzrfosfCRDOqkQ.R7tbcR3ijlCqgm','CN',NULL,1,'2026-11-01 10:37:24','2026-11-25 12:42:07');
/*!40000 ALTER TABLE `Users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `actual_cost_allocations`
--

DROP TABLE IF EXISTS `actual_cost_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actual_cost_allocations` (
  `allocation_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `record_id` int NOT NULL,
  `costing_id` int NOT NULL,
  `allocated_amount` decimal(18,4) NOT NULL,
  `allocated_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  UNIQUE KEY `uq_allocation_record_costing` (`record_id`,`costing_id`),
  KEY `fk_allocation_record` (`project_id`,`record_id`),
  KEY `fk_allocation_costing` (`project_id`,`costing_id`),
  KEY `fk_allocated_by` (`allocated_by`),
  CONSTRAINT `fk_allocated_by` FOREIGN KEY (`allocated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocation_costing` FOREIGN KEY (`project_id`, `costing_id`) REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocation_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_allocation_record` FOREIGN KEY (`project_id`, `record_id`) REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_allocation_amount_positive` CHECK ((`allocated_amount` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actual_cost_allocations`
--

LOCK TABLES `actual_cost_allocations` WRITE;
/*!40000 ALTER TABLE `actual_cost_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `actual_cost_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `actual_cost_records`
--

DROP TABLE IF EXISTS `actual_cost_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actual_cost_records` (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `building_id` int DEFAULT NULL,
  `cost_category` enum('material','auxiliary','processing','mechanical','labor','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_id` int DEFAULT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `cost_object_key` varchar(60) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(concat(_utf8mb4'm',cast(`material_id` as char charset utf8mb4)),concat(_utf8mb4'a',cast(`aux_id` as char charset utf8mb4)),concat(_utf8mb4'c',cast(`catalog_id` as char charset utf8mb4)))) STORED,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(18,6) DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(18,4) DEFAULT NULL,
  `amount` decimal(18,4) NOT NULL,
  `record_type` enum('normal','reversal','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `reversal_of_record_id` int DEFAULT NULL,
  `adjustment_of_record_id` int DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `tax_status` enum('taxable','exempt','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `occurred_at` date NOT NULL,
  `source_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_actual_record_project` (`project_id`,`record_id`),
  KEY `idx_actual_record_project_category` (`project_id`,`cost_category`),
  KEY `fk_actual_building` (`building_id`),
  KEY `fk_actual_material` (`project_id`,`material_id`),
  KEY `fk_actual_aux` (`project_id`,`aux_id`),
  KEY `fk_actual_catalog` (`catalog_id`),
  KEY `fk_actual_reversal_of` (`project_id`,`reversal_of_record_id`),
  KEY `fk_actual_adjustment_of` (`project_id`,`adjustment_of_record_id`),
  KEY `fk_actual_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_actual_adjustment_of` FOREIGN KEY (`project_id`, `adjustment_of_record_id`) REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_aux` FOREIGN KEY (`project_id`, `aux_id`) REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_building` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_material` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_actual_reversal_of` FOREIGN KEY (`project_id`, `reversal_of_record_id`) REFERENCES `actual_cost_records` (`project_id`, `record_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_actual_adjustment_one` CHECK ((((`reversal_of_record_id` is null) and (`adjustment_of_record_id` is null)) or ((`reversal_of_record_id` is not null) and (`adjustment_of_record_id` is null)) or ((`reversal_of_record_id` is null) and (`adjustment_of_record_id` is not null)))),
  CONSTRAINT `chk_actual_amount_nonzero` CHECK ((`amount` <> 0)),
  CONSTRAINT `chk_actual_object_bind` CHECK ((((`cost_category` = _utf8mb4'material') and (`material_id` is not null) and (`aux_id` is null) and (`catalog_id` is null)) or ((`cost_category` = _utf8mb4'auxiliary') and (`aux_id` is not null) and (`material_id` is null) and (`catalog_id` is null)) or ((`cost_category` in (_utf8mb4'processing',_utf8mb4'mechanical',_utf8mb4'labor')) and (`catalog_id` is not null) and (`material_id` is null) and (`aux_id` is null)) or ((`cost_category` = _utf8mb4'other') and (`material_id` is null) and (`aux_id` is null) and (`catalog_id` is null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actual_cost_records`
--

LOCK TABLES `actual_cost_records` WRITE;
/*!40000 ALTER TABLE `actual_cost_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `actual_cost_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_created` (`user_id`,`created_at`),
  KEY `idx_audit_logs_project_entity` (`project_id`,`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1033 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,NULL,NULL,NULL,'login','User signed in.',NULL,NULL,'2026-11-01 10:57:53'),(2,1,NULL,NULL,NULL,'login','User signed in.',NULL,NULL,'2026-11-01 10:57:53'),(3,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 10:59:04'),(4,1,NULL,NULL,NULL,'project.create','Created project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 11:00:25'),(5,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 11:00:25'),(6,1,NULL,NULL,NULL,'logout','User signed out.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 11:39:03'),(7,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 11:42:28'),(8,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 11:42:31'),(9,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 12:30:18'),(10,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 12:30:26'),(11,1,NULL,NULL,NULL,'platform.role.create','Created role 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 12:32:22'),(12,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 12:33:29'),(13,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:23:32'),(14,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:23:35'),(15,1,NULL,NULL,NULL,'platform.plugin.state','Changed plugin material.management to disabled.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:48:43'),(16,1,NULL,NULL,NULL,'platform.plugin.state','Changed plugin material.management to enabled.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:48:46'),(17,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:50:23'),(18,1,NULL,NULL,NULL,'platform.role.permissions','Updated permissions for role 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:51:05'),(19,1,NULL,NULL,NULL,'platform.role.permissions','Updated permissions for role 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:53:25'),(20,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:56:40'),(21,1,NULL,NULL,NULL,'material.category.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:57:29'),(23,1,NULL,NULL,NULL,'material.category.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:57:53'),(24,1,NULL,NULL,NULL,'material.category.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:58:05'),(25,1,NULL,NULL,NULL,'material.category.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 13:58:19'),(27,1,NULL,NULL,NULL,'material.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:02:36'),(28,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:03:43'),(29,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:04:22'),(30,1,NULL,NULL,NULL,'material.supplier.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:05:06'),(31,1,NULL,NULL,NULL,'material.brand.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:05:22'),(32,1,NULL,NULL,NULL,'material.supplier_material.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:06:04'),(33,1,NULL,NULL,NULL,'material.quotation.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:06:30'),(34,1,NULL,NULL,NULL,'material.brand.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:08:13'),(35,1,NULL,NULL,NULL,'material.supplier.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:08:26'),(36,1,NULL,NULL,NULL,'material.supplier_material.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:09:15'),(37,1,NULL,NULL,NULL,'material.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:10:47'),(38,1,NULL,NULL,NULL,'material.quotation.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:11:09'),(39,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:11:21'),(40,1,NULL,NULL,NULL,'material.sample.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:12:57'),(41,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.144.0 Chrome/150.0.7871.250 Electron/43.8.3 Safari/537.36','2026-11-01 14:33:06'),(42,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:38:09'),(43,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.144.0 Chrome/150.0.7871.250 Electron/43.8.3 Safari/537.36','2026-11-01 14:40:54'),(44,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 14:53:27'),(45,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 16:17:43'),(46,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 16:18:45'),(47,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-01 16:22:22'),(48,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.144.0 Chrome/150.0.7871.250 Electron/43.8.3 Safari/537.36','2026-11-01 16:33:05'),(49,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.144.0 Chrome/150.0.7871.250 Electron/43.8.3 Safari/537.36','2026-11-01 16:33:13'),(50,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.144.0 Chrome/150.0.7871.250 Electron/43.8.3 Safari/537.36','2026-11-01 16:33:49'),(51,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-02 09:42:53'),(52,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-02 09:42:55'),(53,1,NULL,NULL,NULL,'material.sample.archive','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-02 10:00:46'),(54,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-02 21:58:50'),(55,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-02 21:58:58'),(56,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-03 00:25:58'),(57,1,NULL,NULL,NULL,'platform.user.update','Updated user 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-03 00:26:50'),(58,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-03 00:27:00'),(59,1,NULL,NULL,NULL,'document.issue.create','Created issue 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-03 00:31:57'),(60,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:11:49'),(61,1,NULL,NULL,NULL,'project.create','Created project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:12:38'),(62,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:12:38'),(63,1,NULL,NULL,NULL,'document.issue.create','Created issue 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:15:05'),(64,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:43:50'),(65,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:44:06'),(66,1,NULL,NULL,NULL,'project_space.save','Saved project space record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:44:28'),(67,1,NULL,NULL,NULL,'project_space.save','Saved project space record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:44:38'),(68,1,NULL,NULL,NULL,'project_space.save','Saved project space record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:45:28'),(69,1,NULL,NULL,NULL,'project_space.save','Saved project space record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:45:55'),(70,1,NULL,NULL,NULL,'project_space.save','Saved project space record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:46:15'),(71,1,NULL,NULL,NULL,'project_space.save','Saved project space record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:46:17'),(72,1,NULL,NULL,NULL,'project_space.save','Saved project space record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:46:35'),(73,1,NULL,NULL,NULL,'project_space.save','Saved project space record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 03:46:37'),(74,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 04:04:57'),(75,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 04:04:59'),(76,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 04:55:14'),(77,1,NULL,NULL,NULL,'document.issue.create','Created issue 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 05:00:58'),(78,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 05:05:05'),(79,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 14:24:03'),(80,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 14:24:10'),(81,1,NULL,NULL,NULL,'project_space.delete','Deleted building 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 14:31:11'),(82,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 14:33:10'),(83,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 14:33:26'),(84,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:11:55'),(85,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:11:57'),(86,1,NULL,NULL,NULL,'material.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:12:47'),(87,1,NULL,NULL,NULL,'material.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:14:06'),(88,1,NULL,NULL,NULL,'material.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:14:26'),(89,1,NULL,NULL,NULL,'material.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:15:30'),(90,1,NULL,NULL,NULL,'material.save','Record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:16:05'),(91,1,NULL,NULL,NULL,'material.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:16:38'),(92,1,NULL,NULL,NULL,'material.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:17:12'),(93,1,NULL,NULL,NULL,'material.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-04 15:18:14'),(94,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 07:27:03'),(95,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 07:27:08'),(96,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 12:43:23'),(97,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 12:43:26'),(98,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:29:59'),(99,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:30:01'),(100,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:31:42'),(101,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:31:46'),(102,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:40:26'),(103,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:41:02'),(104,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:47:03'),(105,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 13:49:11'),(106,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 14:29:52'),(107,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 14:30:06'),(108,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:26:15'),(109,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:26:20'),(110,1,NULL,NULL,NULL,'material.sample.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:26:45'),(111,1,NULL,NULL,NULL,'material.sample.archive','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:26:51'),(112,1,NULL,NULL,NULL,'material.sample.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:38:08'),(113,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:53:30'),(114,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-05 17:58:20'),(115,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 00:03:52'),(116,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 00:03:56'),(117,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 00:08:59'),(118,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 00:17:50'),(119,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 00:19:38'),(120,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 14:35:46'),(121,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 14:35:48'),(122,1,NULL,NULL,NULL,'document.settings.storage.save','Updated image object storage configuration (enabled=1).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:16:36'),(123,1,NULL,NULL,NULL,'document.settings.storage.save','Updated image object storage configuration (enabled=1).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:16:39'),(124,1,NULL,NULL,NULL,'document.settings.storage.save','Updated image object storage configuration (enabled=1).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:16:59'),(125,1,NULL,NULL,NULL,'platform.role.permissions','Updated permissions for role 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:20:10'),(126,1,NULL,NULL,NULL,'material.sample.image.upload','Added image 1 to sample 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:33:13'),(127,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:33:34'),(128,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:53:10'),(129,1,NULL,NULL,NULL,'material.sample.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:55:47'),(130,1,NULL,NULL,NULL,'material.sample.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 15:57:18'),(131,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:07:00'),(132,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:09:11'),(133,1,NULL,NULL,NULL,'material.sample.export','Exported 1 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:13:08'),(134,1,NULL,NULL,NULL,'document.issue.export','Exported 1 issue rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:29:00'),(135,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:30:24'),(136,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:30:58'),(137,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:32:20'),(138,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:47:38'),(139,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:53:23'),(140,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 16:58:07'),(141,1,NULL,NULL,NULL,'material.sample.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-11-06 17:19:38'),(142,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 01:50:03'),(143,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 01:50:09'),(144,1,NULL,NULL,NULL,'material.brand.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 01:50:44'),(145,1,NULL,NULL,NULL,'material.supplier.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 01:59:25'),(146,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 06:40:18'),(147,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 06:40:20'),(148,1,NULL,NULL,NULL,'material.sample.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 06:41:08'),(149,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:14:53'),(150,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:14:54'),(151,1,NULL,NULL,NULL,'material.sample.image.upload','Added image 2 to sample 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:15:06'),(152,1,NULL,NULL,NULL,'material.sample.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:15:45'),(153,1,NULL,NULL,NULL,'material.sample.save','Record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:16:19'),(154,1,NULL,NULL,NULL,'material.sample.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:17:29'),(155,1,NULL,NULL,NULL,'material.sample.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:18:39'),(156,1,NULL,NULL,NULL,'material.sample.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:21:44'),(157,1,NULL,NULL,NULL,'material.sample.image.upload','Added image 3 to sample 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:21:57'),(158,1,NULL,NULL,NULL,'material.sample.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:46:40'),(159,1,NULL,NULL,NULL,'material.supplier.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 07:51:36'),(160,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 09:09:07'),(161,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 09:09:09'),(162,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 09:31:18'),(163,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 09:31:41'),(164,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 13:49:51'),(165,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 13:49:55'),(166,1,NULL,NULL,NULL,'material.sample.export','Exported 5 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-07 14:20:19'),(167,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:17:44'),(168,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:17:46'),(169,1,NULL,NULL,NULL,'material.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:43:59'),(170,1,NULL,NULL,NULL,'material.save','Record 12.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:45:51'),(171,1,NULL,NULL,NULL,'material.save','Record 13.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:54:36'),(172,1,NULL,NULL,NULL,'material.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:55:19'),(173,1,NULL,NULL,NULL,'material.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:56:36'),(174,1,NULL,NULL,NULL,'material.save','Record 14.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:57:39'),(175,1,NULL,NULL,NULL,'material.save','Record 15.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:58:33'),(176,1,NULL,NULL,NULL,'material.save','Record 14.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 02:58:51'),(177,1,NULL,NULL,NULL,'material.save','Record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:01:23'),(178,1,NULL,NULL,NULL,'material.save','Record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:03:13'),(179,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:39:41'),(180,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:39:43'),(181,1,NULL,NULL,NULL,'material.save','Record 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:40:13'),(182,1,NULL,NULL,NULL,'material.save','Record 19.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:44:42'),(183,1,NULL,NULL,NULL,'material.save','Record 20.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:46:00'),(184,1,NULL,NULL,NULL,'material.save','Record 21.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:46:50'),(185,1,NULL,NULL,NULL,'material.save','Record 22.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:47:36'),(186,1,NULL,NULL,NULL,'material.save','Record 23.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:48:22'),(187,1,NULL,NULL,NULL,'material.save','Record 23.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 03:48:31'),(188,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 11:26:47'),(189,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 13:30:54'),(190,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 13:33:03'),(191,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 15:14:37'),(192,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 15:14:39'),(193,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 16:15:58'),(194,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 16:15:59'),(195,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 16:18:00'),(196,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-08 16:18:15'),(197,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 00:47:34'),(198,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 00:47:36'),(199,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 14:09:00'),(200,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 14:09:02'),(201,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 15:45:38'),(202,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-09 15:45:45'),(203,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 05:55:22'),(204,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 05:55:24'),(205,1,NULL,NULL,NULL,'labor.crew.save','Crew 1 (E2E 测试班组) in project 1.',NULL,NULL,'2026-11-11 06:02:35'),(206,1,NULL,NULL,NULL,'labor.crew.save','Crew 1 (E2E 测试班组) in project 1.',NULL,NULL,'2026-11-11 06:02:35'),(207,1,NULL,NULL,NULL,'labor.crew.archive','Crew 1 archived in project 1.',NULL,NULL,'2026-11-11 06:02:35'),(208,1,NULL,NULL,NULL,'labor.crew.restore','Crew 1 restored in project 1.',NULL,NULL,'2026-11-11 06:02:35'),(209,1,NULL,NULL,NULL,'labor.crew_type.save','Type 16.',NULL,NULL,'2026-11-11 06:02:35'),(210,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 16.',NULL,NULL,'2026-11-11 06:02:35'),(211,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:11:10'),(212,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:11:19'),(213,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:11:21'),(214,1,NULL,NULL,NULL,'labor.crew.save','Crew 2 (FINAL 归档链路班组) in project 1.',NULL,NULL,'2026-11-11 06:11:26'),(215,1,NULL,NULL,NULL,'labor.crew.archive','Crew 2 archived in project 1.',NULL,NULL,'2026-11-11 06:11:26'),(216,1,NULL,NULL,NULL,'labor.crew.save','Crew 2 (FINAL 归档链路班组) in project 1.',NULL,NULL,'2026-11-11 06:11:26'),(217,1,NULL,NULL,NULL,'labor.crew.restore','Crew 2 restored in project 1.',NULL,NULL,'2026-11-11 06:11:27'),(218,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:11:27'),(219,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 12.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:11:35'),(220,1,NULL,NULL,NULL,'labor.crew_type.save','Type 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:25:41'),(221,1,NULL,NULL,NULL,'labor.crew_type.save','Type 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:25:49'),(222,1,NULL,NULL,NULL,'labor.crew_type.save','Type 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:26:06'),(223,1,NULL,NULL,NULL,'labor.crew_type.save','Type 19.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:26:31'),(224,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:28:11'),(225,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:29:05'),(226,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:29:21'),(227,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:29:56'),(228,1,NULL,NULL,NULL,'labor.crew_type.delete','Type 19.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:30:03'),(229,1,NULL,NULL,NULL,'labor.crew_type.save','Type 20.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:30:49'),(230,1,NULL,NULL,NULL,'labor.crew.save','Crew 3 (GRG) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:31:50'),(231,1,NULL,NULL,NULL,'labor.crew.save','Crew 4 (不锈钢玻璃铝板) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:33:33'),(232,1,NULL,NULL,NULL,'labor.crew.save','Crew 5 (感应电动玻璃门) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:34:54'),(233,1,NULL,NULL,NULL,'labor.crew.save','Crew 6 (木饰面定制柜) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:36:16'),(234,1,NULL,NULL,NULL,'labor.crew.save','Crew 7 (水电、消防水) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:37:57'),(235,1,NULL,NULL,NULL,'labor.crew.save','Crew 8 (排烟) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:39:09'),(236,1,NULL,NULL,NULL,'labor.crew.save','Crew 9 (地面找平) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:43:16'),(237,1,NULL,NULL,NULL,'labor.crew.save','Crew 10 (空调新风) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:44:28'),(238,1,NULL,NULL,NULL,'labor.crew.save','Crew 10 (空调新风) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:48:36'),(239,1,NULL,NULL,NULL,'labor.crew.save','Crew 11 (施工员) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:53:35'),(240,1,NULL,NULL,NULL,'labor.crew.export','Exported 9 crew rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 06:55:47'),(241,1,NULL,NULL,NULL,'labor.crew.export','Exported 9 crew rows for project 2.',NULL,NULL,'2026-11-11 07:00:20'),(242,1,NULL,NULL,NULL,'labor.crew.export','Exported 9 crew rows for project 2.',NULL,NULL,'2026-11-11 07:00:38'),(243,1,NULL,NULL,NULL,'labor.crew.save','Crew 12 (油漆班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 07:02:22'),(244,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:12:35'),(245,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:12:36'),(246,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:12:47'),(247,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:22:50'),(248,1,NULL,NULL,NULL,'labor.crew.save','Crew 13 (施工管里员) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:24:16'),(249,1,NULL,NULL,NULL,'labor.crew.save','Crew 14 (木工班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:44:22'),(250,1,NULL,NULL,NULL,'labor.crew.save','Crew 13 (施工管里员) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:44:35'),(251,1,NULL,NULL,NULL,'labor.crew.save','Crew 15 (水电班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:48:39'),(252,1,NULL,NULL,NULL,'labor.crew.export','Exported 3 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:49:03'),(253,1,NULL,NULL,NULL,'labor.crew.export','Exported 3 crew rows for project 1.',NULL,NULL,'2026-11-11 08:50:48'),(254,1,NULL,NULL,NULL,'labor.crew.save','Crew 16 (空调新风) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:51:04'),(255,1,NULL,NULL,NULL,'labor.crew.save','Crew 16 (空调新风) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:51:14'),(256,1,NULL,NULL,NULL,'labor.crew.save','Crew 16 (空调新风) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:51:36'),(257,1,NULL,NULL,NULL,'labor.crew.save','Crew 17 (地面找平) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:54:41'),(258,1,NULL,NULL,NULL,'labor.crew.save','Crew 17 (地面找平) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:54:48'),(259,1,NULL,NULL,NULL,'labor.crew.save','Crew 18 (油漆班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:56:28'),(260,1,NULL,NULL,NULL,'labor.crew.save','Crew 19 (玻璃铝板) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:57:42'),(261,1,NULL,NULL,NULL,'labor.crew.save','Crew 20 (电动感应玻璃门) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 08:59:04'),(262,1,NULL,NULL,NULL,'labor.crew.save','Crew 21 (广告制作) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:01:26'),(263,1,NULL,NULL,NULL,'labor.crew.save','Crew 21 (广告制作) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:01:35'),(264,1,NULL,NULL,NULL,'labor.crew.save','Crew 22 (LED显示屏设备) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:04:06'),(265,1,NULL,NULL,NULL,'labor.crew.save','Crew 20 (电动感应玻璃门) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:04:20'),(266,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:04:25'),(267,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:04:34'),(268,1,NULL,NULL,NULL,'labor.crew.save','Crew 13 (施工管理员) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:05:07'),(269,1,NULL,NULL,NULL,'labor.crew.save','Crew 14 (木工班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:05:32'),(270,1,NULL,NULL,NULL,'labor.crew.save','Crew 15 (水电班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:05:40'),(271,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:05:46'),(272,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:07:58'),(273,1,NULL,NULL,NULL,'labor.crew.save','Crew 11 (施工管理员) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:08:18'),(274,1,NULL,NULL,NULL,'labor.crew.save','Crew 23 (木工班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:09:57'),(275,1,NULL,NULL,NULL,'labor.crew.save','Crew 7 (水电班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:10:58'),(276,1,NULL,NULL,NULL,'labor.crew.save','Crew 12 (油漆班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:11:11'),(277,1,NULL,NULL,NULL,'labor.crew.save','Crew 9 (地面找平) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:12:47'),(278,1,NULL,NULL,NULL,'labor.crew.save','Crew 8 (排烟安装班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:13:14'),(279,1,NULL,NULL,NULL,'labor.crew.save','Crew 9 (地面找平) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:13:31'),(280,1,NULL,NULL,NULL,'labor.crew.save','Crew 8 (排烟安装班组) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:13:44'),(281,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:17:41'),(282,1,NULL,NULL,NULL,'labor.crew.save','Crew 15 (水电班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:17:51'),(283,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:19:14'),(284,1,NULL,NULL,NULL,'labor.crew.save','Crew 3 (GRG) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:20:48'),(285,1,NULL,NULL,NULL,'labor.crew.save','Crew 4 (不锈钢玻璃铝板) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:20:58'),(286,1,NULL,NULL,NULL,'labor.crew.save','Crew 5 (感应电动玻璃门) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:21:06'),(287,1,NULL,NULL,NULL,'labor.crew.save','Crew 6 (木饰面定制柜) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:21:17'),(288,1,NULL,NULL,NULL,'labor.crew.save','Crew 10 (空调新风) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:21:27'),(289,1,NULL,NULL,NULL,'labor.crew.export','Exported 11 crew rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:22:48'),(290,1,NULL,NULL,NULL,'labor.crew.save','Crew 3 (GRG制作) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:24:02'),(291,1,NULL,NULL,NULL,'labor.crew.export','Exported 11 crew rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:35:29'),(292,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:36:32'),(293,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 09:36:38'),(294,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 10:21:36'),(295,1,NULL,NULL,NULL,'material.category.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 10:23:36'),(296,1,NULL,NULL,NULL,'material.save','Record 24.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 10:26:19'),(297,1,NULL,NULL,NULL,'material.save','Record 25.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 10:31:44'),(298,1,NULL,NULL,NULL,'material.export','Exported 0 material rows for project 1 (all categories).',NULL,NULL,'2026-11-11 12:27:07'),(299,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 12:33:30'),(300,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 12:33:32'),(301,1,NULL,NULL,NULL,'material.export','Exported 2 material rows for project 2 (category 5).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 12:33:43'),(302,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-11 15:34:10'),(303,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 14:42:50'),(304,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 14:42:51'),(305,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:01:38'),(306,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:01:40'),(307,1,NULL,NULL,NULL,'labor.crew.save','Crew 16 (空调新风) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:03:41'),(308,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:03:54'),(309,1,NULL,NULL,NULL,'labor.crew.save','Crew 21 (广告制作) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:07:29'),(310,1,NULL,NULL,NULL,'labor.crew.save','Crew 15 (水电班组) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:08:04'),(311,1,NULL,NULL,NULL,'labor.crew.export','Exported 10 crew rows for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:08:08'),(312,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-12 16:09:16'),(313,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 00:30:57'),(314,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 00:30:59'),(315,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 02:01:23'),(316,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 02:01:25'),(317,1,NULL,NULL,NULL,'labor.crew.share','Public crew contact sharing enabled for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 02:06:39'),(318,1,NULL,NULL,NULL,'material.save','Record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 02:59:41'),(319,1,NULL,NULL,NULL,'material.save','Record 32.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:35:20'),(320,1,NULL,NULL,NULL,'material.save','Record 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:35:32'),(321,1,NULL,NULL,NULL,'material.save','Record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:37:52'),(322,1,NULL,NULL,NULL,'material.save','Record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:39:15'),(323,1,NULL,NULL,NULL,'material.save','Record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:40:28'),(324,1,NULL,NULL,NULL,'material.save','Record 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:41:42'),(325,1,NULL,NULL,NULL,'material.save','Record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:46:17'),(326,1,NULL,NULL,NULL,'material.save','Record 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 03:49:00'),(327,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:04:42'),(328,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:04:54'),(329,1,NULL,NULL,NULL,'project_space.save','Saved project space record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:11:25'),(330,1,NULL,NULL,NULL,'material.estimate.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:15:50'),(331,1,NULL,NULL,NULL,'project_space.save','Saved project space record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:17:03'),(332,1,NULL,NULL,NULL,'project_space.save','Saved project space record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:25:22'),(333,1,NULL,NULL,NULL,'project_space.save','Saved project space record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:25:29'),(334,1,NULL,NULL,NULL,'project_space.save','Saved project space record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:25:35'),(335,1,NULL,NULL,NULL,'project_space.save','Saved project space record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:25:43'),(336,1,NULL,NULL,NULL,'project_space.save','Saved project space record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:27:07'),(337,1,NULL,NULL,NULL,'project_space.save','Saved project space record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:27:28'),(338,1,NULL,NULL,NULL,'material.estimate.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:34:04'),(339,1,NULL,NULL,NULL,'material.estimate.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:34:27'),(340,1,NULL,NULL,NULL,'material.estimate.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:34:40'),(341,1,NULL,NULL,NULL,'material.estimate.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:34:58'),(342,1,NULL,NULL,NULL,'material.estimate.save','Record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:35:37'),(343,1,NULL,NULL,NULL,'project_space.save','Saved project space record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:36:29'),(344,1,NULL,NULL,NULL,'material.estimate.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:36:58'),(345,1,NULL,NULL,NULL,'material.estimate.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:38:50'),(346,1,NULL,NULL,NULL,'project_space.save','Saved project space record 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:39:24'),(347,1,NULL,NULL,NULL,'material.estimate.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:39:39'),(348,1,NULL,NULL,NULL,'material.estimate.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:40:17'),(349,1,NULL,NULL,NULL,'project_space.save','Saved project space record 12.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:40:42'),(350,1,NULL,NULL,NULL,'project_space.save','Saved project space record 13.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:40:52'),(351,1,NULL,NULL,NULL,'material.estimate.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:41:27'),(352,1,NULL,NULL,NULL,'material.estimate.save','Record 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:41:42'),(353,1,NULL,NULL,NULL,'project_space.save','Saved project space record 14.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:42:24'),(354,1,NULL,NULL,NULL,'project_space.save','Saved project space record 15.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:42:32'),(355,1,NULL,NULL,NULL,'project_space.save','Saved project space record 15.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:42:39'),(356,1,NULL,NULL,NULL,'material.estimate.save','Record 12.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:43:10'),(357,1,NULL,NULL,NULL,'material.save','Record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:44:09'),(358,1,NULL,NULL,NULL,'material.estimate.save','Record 13.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:45:48'),(359,1,NULL,NULL,NULL,'project_space.save','Saved project space record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:46:48'),(360,1,NULL,NULL,NULL,'project_space.save','Saved project space record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:47:26'),(361,1,NULL,NULL,NULL,'project_space.save','Saved project space record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:47:37'),(362,1,NULL,NULL,NULL,'project_space.save','Saved project space record 14.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:47:47'),(363,1,NULL,NULL,NULL,'project_space.save','Saved project space record 15.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:47:51'),(364,1,NULL,NULL,NULL,'project_space.save','Saved project space record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:48:00'),(365,1,NULL,NULL,NULL,'project_space.save','Saved project space record 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:48:34'),(366,1,NULL,NULL,NULL,'project_space.save','Saved project space record 19.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:48:40'),(367,1,NULL,NULL,NULL,'project_space.save','Saved project space record 20.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:48:47'),(368,1,NULL,NULL,NULL,'material.estimate.save','Record 14.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:50:57'),(369,1,NULL,NULL,NULL,'material.estimate.save','Record 15.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:51:55'),(370,1,NULL,NULL,NULL,'material.estimate.save','Record 16.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:52:03'),(371,1,NULL,NULL,NULL,'material.estimate.save','Record 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 04:52:15'),(372,1,NULL,NULL,NULL,'labor.crew.save','Crew 24 (防盗门) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:00:12'),(373,1,NULL,NULL,NULL,'labor.crew.save','Crew 24 (防盗门) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:00:20'),(374,1,NULL,NULL,NULL,'material.supplier.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:01:01'),(375,1,NULL,NULL,NULL,'material.estimate.export','Exported 3 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:02:03'),(376,1,NULL,NULL,NULL,'material.supplier.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:03:33'),(377,1,NULL,NULL,NULL,'labor.crew.save','Crew 24 (倪伟飞) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:04:47'),(378,1,NULL,NULL,NULL,'material.estimate.export','Exported 1 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:08:10'),(379,1,NULL,NULL,NULL,'material.estimate.export','Exported 13 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:43:49'),(380,1,NULL,NULL,NULL,'material.estimate.export','Exported 17 estimate rows for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:52:59'),(381,1,NULL,NULL,NULL,'material.estimate.export','Exported 17 estimate rows for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 05:56:58'),(382,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:46:21'),(383,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:46:50'),(384,1,NULL,NULL,NULL,'labor.crew.save','Crew 25 (防火卷帘) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:48:26'),(385,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:48:43'),(386,1,NULL,NULL,NULL,'labor.crew.save','Crew 26 (防火卷帘) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:49:07'),(387,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:49:40'),(388,1,NULL,NULL,NULL,'labor.crew.save','Crew 27 (木饰面定制柜) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:50:24'),(389,1,NULL,NULL,NULL,'labor.crew.save','Crew 25 (防火卷帘) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:50:33'),(390,1,NULL,NULL,NULL,'labor.crew.share','Public crew contact sharing enabled for project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:50:46'),(391,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 11:57:21'),(392,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 13:53:47'),(393,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 13:53:50'),(394,1,NULL,NULL,NULL,'document.issue.create','Created issue 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 13:54:10'),(395,1,NULL,NULL,NULL,'document.issue.create','Created issue 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 13:55:27'),(396,1,NULL,NULL,NULL,'document.issue.status_change','Issue 5: open -> in_review. Reason: 图审修改','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 13:59:54'),(397,1,NULL,NULL,NULL,'document.rfi.create','Created RFI P1-RFI-001.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-13 14:00:49'),(398,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 04:45:59'),(399,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 04:46:01'),(400,1,NULL,NULL,NULL,'material.estimate.save','Record 18.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 05:06:31'),(401,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 06:25:05'),(402,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 06:25:07'),(403,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:44:43'),(404,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:44:45'),(405,1,NULL,NULL,NULL,'labor.crew.save','Crew 28 (静电地板) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:53:27'),(406,1,NULL,NULL,NULL,'labor.crew.save','Crew 28 (静电地板) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:53:38'),(407,1,NULL,NULL,NULL,'labor.crew.save','Crew 26 (防火卷帘) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:53:47'),(408,1,NULL,NULL,NULL,'labor.crew.save','Crew 28 (静电地板) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:54:06'),(409,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:54:20'),(410,1,NULL,NULL,NULL,'labor.crew.save','Crew 29 (静电地板) in project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 10:55:59'),(411,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 14:28:27'),(412,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-14 14:28:28'),(413,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 01:52:10'),(414,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 01:52:12'),(415,1,NULL,NULL,NULL,'material.save','Record 24.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:21:35'),(416,1,NULL,NULL,NULL,'material.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:22:36'),(417,1,NULL,NULL,NULL,'material.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:22:47'),(418,1,NULL,NULL,NULL,'material.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:23:47'),(419,1,NULL,NULL,NULL,'material.save','Record 37.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:24:45'),(420,1,NULL,NULL,NULL,'material.save','Record 38.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:31:52'),(421,1,NULL,NULL,NULL,'material.save','Record 39.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:34:44'),(422,1,NULL,NULL,NULL,'material.save','Record 40.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 02:48:56'),(423,1,NULL,NULL,NULL,'material.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 03:04:16'),(424,1,NULL,NULL,NULL,'material.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 03:04:30'),(425,1,NULL,NULL,NULL,'material.save','Record 37.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 03:04:41'),(426,1,NULL,NULL,NULL,'material.save','Record 41.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 03:10:46'),(427,1,NULL,NULL,NULL,'material.save','Record 42.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 03:13:21'),(428,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:03:43'),(429,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:03:44'),(430,1,NULL,NULL,NULL,'material.save','Record 43.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:10:33'),(431,1,NULL,NULL,NULL,'material.save','Record 44.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:11:40'),(432,1,NULL,NULL,NULL,'material.save','Record 45.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:12:41'),(433,1,NULL,NULL,NULL,'material.save','Record 44.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:17:43'),(434,1,NULL,NULL,NULL,'material.save','Record 39.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:18:21'),(435,1,NULL,NULL,NULL,'material.save','Record 46.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:21:17'),(436,1,NULL,NULL,NULL,'material.save','Record 40.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:26:19'),(437,1,NULL,NULL,NULL,'material.save','Record 47.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:26:37'),(438,1,NULL,NULL,NULL,'material.save','Record 48.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:28:26'),(439,1,NULL,NULL,NULL,'material.save','Record 49.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:29:39'),(440,1,NULL,NULL,NULL,'material.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:30:22'),(441,1,NULL,NULL,NULL,'material.save','Record 47.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:31:51'),(442,1,NULL,NULL,NULL,'material.save','Record 51.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:32:38'),(443,1,NULL,NULL,NULL,'material.save','Record 52.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:34:17'),(444,1,NULL,NULL,NULL,'material.save','Record 53.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:35:44'),(445,1,NULL,NULL,NULL,'material.save','Record 51.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:36:20'),(446,1,NULL,NULL,NULL,'material.save','Record 54.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:38:05'),(447,1,NULL,NULL,NULL,'material.save','Record 55.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:38:49'),(448,1,NULL,NULL,NULL,'material.save','Record 56.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 04:39:15'),(449,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 05:18:23'),(450,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 05:18:24'),(451,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 07:39:10'),(452,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 07:39:11'),(453,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 08:46:10'),(454,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 08:46:12'),(455,1,NULL,NULL,NULL,'document.issue.export','Exported 4 issue rows for project 1.',NULL,NULL,'2026-11-15 08:51:48'),(456,1,NULL,NULL,NULL,'document.issue.export','Exported 1 issue rows for project 2.',NULL,NULL,'2026-11-15 09:41:39'),(457,1,NULL,NULL,NULL,'document.issue.create','Created issue 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 09:44:40'),(458,1,NULL,NULL,NULL,'document.issue.export','Exported 2 issue rows for project 2.',NULL,NULL,'2026-11-15 10:00:13'),(459,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 10:23:26'),(460,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 10:23:29'),(461,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 10:30:40'),(462,1,NULL,NULL,NULL,'document.issue.update','Updated ordinary fields for issue 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 10:31:33'),(463,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 11:48:57'),(464,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 11:49:03'),(465,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 11:49:06'),(466,1,NULL,NULL,NULL,'document.issue.create','Created issue 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-15 11:53:03'),(467,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-16 15:55:10'),(468,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-16 15:55:12'),(469,1,NULL,NULL,NULL,'material.save','Record 57.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-16 15:56:48'),(470,1,NULL,NULL,NULL,'material.export','Exported 25 material rows for project 2 (category 5).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-16 15:56:54'),(471,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 01:41:45'),(472,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 01:41:47'),(473,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 02:25:27'),(474,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 03:13:32'),(475,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 03:13:34'),(476,1,NULL,NULL,NULL,'material.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 03:16:02'),(477,1,NULL,NULL,NULL,'project_space.save','Saved project space record 21.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:09:33'),(478,1,NULL,NULL,NULL,'material.estimate.save','Record 19.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:10:21'),(479,1,NULL,NULL,NULL,'material.estimate.save','Record 20.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:11:47'),(480,1,NULL,NULL,NULL,'material.estimate.save','Record 21.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:12:36'),(481,1,NULL,NULL,NULL,'material.estimate.save','Record 22.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:13:20'),(482,1,NULL,NULL,NULL,'material.estimate.save','Record 23.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:15:06'),(483,1,NULL,NULL,NULL,'material.estimate.save','Record 24.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:15:32'),(484,1,NULL,NULL,NULL,'material.estimate.save','Record 25.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:16:12'),(485,1,NULL,NULL,NULL,'material.estimate.save','Record 26.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:17:44'),(486,1,NULL,NULL,NULL,'material.estimate.save','Record 27.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:19:09'),(487,1,NULL,NULL,NULL,'material.estimate.save','Record 28.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:20:19'),(488,1,NULL,NULL,NULL,'material.estimate.export','Exported 10 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:20:47'),(489,1,NULL,NULL,NULL,'project_space.save','Saved project space record 22.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:57:34'),(490,1,NULL,NULL,NULL,'material.estimate.save','Record 29.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 04:58:09'),(491,1,NULL,NULL,NULL,'project_space.save','Saved project space record 23.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 05:00:28'),(492,1,NULL,NULL,NULL,'material.estimate.save','Record 30.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 05:01:59'),(493,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 12:46:26'),(494,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 12:46:46'),(495,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 12:54:22'),(496,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 12:57:59'),(497,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:03:58'),(498,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-11-17 13:19:05'),(499,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:21:21'),(500,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:21:24'),(501,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:21:38'),(502,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:35:32'),(503,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 13:36:07'),(504,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 14:17:55'),(505,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 14:17:57'),(506,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:02:44'),(507,1,NULL,NULL,NULL,'document.issue.status_change','Issue 7: open -> in_review. Reason: 设计为手动','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:07:34'),(508,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:08:07'),(509,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:18:57'),(510,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:19:25'),(511,1,NULL,NULL,NULL,'material.sample.export','Exported 5 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:22:06'),(512,1,NULL,NULL,NULL,'material.sample.export','Exported 5 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:22:15'),(513,1,NULL,NULL,NULL,'material.sample.export','Exported 5 sample rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:23:17'),(514,1,NULL,NULL,NULL,'material.supplier_material.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:25:01'),(515,1,NULL,NULL,NULL,'material.supplier_material.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:25:55'),(516,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:45:43'),(517,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 15:45:45'),(518,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:05:20'),(519,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:05:26'),(520,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:10:31'),(521,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:10:38'),(522,1,NULL,NULL,NULL,'material.supplier.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:18:04'),(523,1,NULL,NULL,NULL,'material.quotation.delete','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:18:22'),(524,1,NULL,NULL,NULL,'material.delete','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:18:36'),(525,1,NULL,NULL,NULL,'material.category.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:18:57'),(526,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:33:19'),(527,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:33:22'),(528,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:33:31'),(529,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:34:26'),(530,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:34:33'),(531,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:34:36'),(532,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:38:34'),(533,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:39:01'),(534,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-17 17:46:50'),(535,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 01:49:52'),(536,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 01:49:52'),(537,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 01:49:55'),(538,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 02:00:01'),(539,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 03:58:07'),(540,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 03:58:10'),(541,1,NULL,NULL,NULL,'material.save','Record 58.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:03:40'),(542,1,NULL,NULL,NULL,'material.estimate.save','Record 31.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:05:10'),(543,1,NULL,NULL,NULL,'material.estimate.save','Record 32.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:06:53'),(544,1,NULL,NULL,NULL,'material.sample.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:12:14'),(545,1,NULL,NULL,NULL,'material.sample.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:13:08'),(546,1,NULL,NULL,NULL,'material.sample.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:13:17'),(547,1,NULL,NULL,NULL,'material.sample.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:13:24'),(548,1,NULL,NULL,NULL,'material.sample.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:13:44'),(549,1,NULL,NULL,NULL,'material.supplier.save','Record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:30:53'),(550,1,NULL,NULL,NULL,'material.sample.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:32:20'),(551,1,NULL,NULL,NULL,'material.sample.save','Record 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 04:38:47'),(552,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:00:27'),(553,1,NULL,NULL,NULL,'material.category.save','Record 6.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:00:40'),(554,1,NULL,NULL,NULL,'material.save','Record 59.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:02:57'),(555,1,NULL,NULL,NULL,'material.save','Record 60.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:04:23'),(556,1,NULL,NULL,NULL,'material.save','Record 61.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:06:01'),(557,1,NULL,NULL,NULL,'material.save','Record 62.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:06:51'),(558,1,NULL,NULL,NULL,'material.save','Record 63.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:07:31'),(559,1,NULL,NULL,NULL,'material.save','Record 64.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:08:36'),(560,1,NULL,NULL,NULL,'material.save','Record 65.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:13:21'),(561,1,NULL,NULL,NULL,'material.save','Record 66.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:14:27'),(562,1,NULL,NULL,NULL,'material.save','Record 63.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:15:47'),(563,1,NULL,NULL,NULL,'material.save','Record 64.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:16:33'),(564,1,NULL,NULL,NULL,'material.save','Record 65.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:17:10'),(565,1,NULL,NULL,NULL,'material.save','Record 67.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:17:48'),(566,1,NULL,NULL,NULL,'material.save','Record 66.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:18:45'),(567,1,NULL,NULL,NULL,'material.save','Record 67.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:19:08'),(568,1,NULL,NULL,NULL,'material.save','Record 68.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:19:59'),(569,1,NULL,NULL,NULL,'material.save','Record 69.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:20:31'),(570,1,NULL,NULL,NULL,'material.save','Record 70.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:20:58'),(571,1,NULL,NULL,NULL,'material.save','Record 71.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:21:30'),(572,1,NULL,NULL,NULL,'material.save','Record 72.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:22:00'),(573,1,NULL,NULL,NULL,'material.save','Record 73.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:22:28'),(574,1,NULL,NULL,NULL,'material.save','Record 74.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:22:58'),(575,1,NULL,NULL,NULL,'material.save','Record 74.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:24:21'),(576,1,NULL,NULL,NULL,'material.save','Record 73.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:24:32'),(577,1,NULL,NULL,NULL,'material.save','Record 72.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:24:44'),(578,1,NULL,NULL,NULL,'material.save','Record 71.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:24:51'),(579,1,NULL,NULL,NULL,'material.save','Record 70.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:25:09'),(580,1,NULL,NULL,NULL,'material.save','Record 75.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:25:51'),(581,1,NULL,NULL,NULL,'material.save','Record 76.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:26:43'),(582,1,NULL,NULL,NULL,'material.save','Record 77.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:27:14'),(583,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:31:21'),(584,1,NULL,NULL,NULL,'material.brand.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:32:08'),(585,1,NULL,NULL,NULL,'material.supplier_material.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:32:59'),(586,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:43:38'),(587,1,NULL,NULL,NULL,'material.save','Record 65.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:44:07'),(588,1,NULL,NULL,NULL,'material.save','Record 63.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:44:18'),(589,1,NULL,NULL,NULL,'material.save','Record 66.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:44:31'),(590,1,NULL,NULL,NULL,'material.save','Record 61.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:44:43'),(591,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 05:45:38'),(592,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 11:27:00'),(593,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 11:27:02'),(594,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 12:24:11'),(595,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 12:24:13'),(596,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 12:47:38'),(597,1,NULL,NULL,NULL,'material.save','Record 120.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 12:58:16'),(598,1,NULL,NULL,NULL,'material.sample.save','Record 20.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:00:03'),(599,1,NULL,NULL,NULL,'material.estimate.save','Record 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:01:19'),(600,1,NULL,NULL,NULL,'material.estimate.save','Record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:04:00'),(601,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:04:56'),(602,1,NULL,NULL,NULL,'project_space.save','Saved project space record 24.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:05:12'),(603,1,NULL,NULL,NULL,'material.estimate.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:05:37'),(604,1,NULL,NULL,NULL,'material.estimate.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:06:33'),(605,1,NULL,NULL,NULL,'material.estimate.export','Exported 4 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:07:11'),(606,1,NULL,NULL,NULL,'material.save','Record 121.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:17:31'),(607,1,NULL,NULL,NULL,'material.save','Record 122.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:19:21'),(608,1,NULL,NULL,NULL,'material.save','Record 123.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:20:03'),(609,1,NULL,NULL,NULL,'material.sample.save','Record 21.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:20:55'),(610,1,NULL,NULL,NULL,'material.sample.save','Record 22.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:21:25'),(611,1,NULL,NULL,NULL,'material.sample.save','Record 23.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:21:49'),(612,1,NULL,NULL,NULL,'material.sample.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:22:17'),(613,1,NULL,NULL,NULL,'material.estimate.save','Record 37.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:31:56'),(614,1,NULL,NULL,NULL,'material.estimate.save','Record 38.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:37:23'),(615,1,NULL,NULL,NULL,'material.estimate.save','Record 39.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:54:01'),(616,1,NULL,NULL,NULL,'material.estimate.save','Record 40.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 13:57:03'),(617,1,NULL,NULL,NULL,'material.estimate.save','Record 41.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 14:00:14'),(618,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 14:44:22'),(619,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 14:44:23'),(620,1,NULL,NULL,NULL,'material.estimate.export','Exported 8 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 14:44:56'),(621,1,NULL,NULL,NULL,'material.estimate.export','Exported 8 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 14:45:20'),(622,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:12:22'),(623,1,NULL,NULL,NULL,'project_space.save','Saved project space record 25.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:12:42'),(624,1,NULL,NULL,NULL,'material.estimate.save','Record 42.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:15:00'),(625,1,NULL,NULL,NULL,'material.estimate.save','Record 43.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:15:25'),(626,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:27:41'),(627,1,NULL,NULL,NULL,'material.estimate.export','Exported 43 estimate rows for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:27:45'),(628,1,NULL,NULL,NULL,'material.estimate.export','Exported 7 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:29:30'),(629,1,NULL,NULL,NULL,'material.estimate.export','Exported 7 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:32:49'),(630,1,NULL,NULL,NULL,'material.estimate.export','Exported 7 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:36:30'),(631,1,NULL,NULL,NULL,'material.estimate.export','Exported 7 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:36:52'),(632,1,NULL,NULL,NULL,'material.estimate.export','Exported 4 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:40:01'),(633,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-18 15:53:20'),(634,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 01:27:55'),(635,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 01:27:58'),(636,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 01:28:02'),(637,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 02:10:05'),(638,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 02:10:07'),(639,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 05:36:09'),(640,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 05:36:31'),(641,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 13:18:13'),(642,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 14:15:16'),(643,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 14:15:18'),(644,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-19 14:15:23'),(645,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 01:09:34'),(646,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 01:09:45'),(647,1,NULL,NULL,NULL,'labor.crew.save','Crew 30 (石材) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 01:16:15'),(648,1,NULL,NULL,NULL,'labor.crew.save','Crew 30 (石材) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 01:16:30'),(649,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:14:55'),(650,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:14:57'),(651,1,NULL,NULL,NULL,'material.estimate.save','Record 44.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:17:13'),(652,1,NULL,NULL,NULL,'material.estimate.save','Record 45.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:18:29'),(653,1,NULL,NULL,NULL,'material.estimate.save','Record 45.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:18:59'),(654,1,NULL,NULL,NULL,'material.estimate.save','Record 44.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:19:29'),(655,1,NULL,NULL,NULL,'material.estimate.export','Exported 45 estimate rows for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:19:31'),(656,1,NULL,NULL,NULL,'material.estimate.export','Exported 2 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 03:19:47'),(657,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 09:53:47'),(658,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 09:53:50'),(659,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:01:26'),(660,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:01:28'),(661,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:11:24'),(662,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:13:39'),(663,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:13:42'),(664,1,NULL,NULL,NULL,'platform.role.permissions','Updated permissions for role 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:16:38'),(665,1,NULL,NULL,NULL,'material.save','Record 124.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:20:15'),(666,1,NULL,NULL,NULL,'material.save','Record 125.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:21:22'),(667,1,NULL,NULL,NULL,'material.save','Record 124.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:21:36'),(668,1,NULL,NULL,NULL,'material.composition.save','Record 25.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:22:28'),(669,1,NULL,NULL,NULL,'material.composition.save','Record 26.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:22:57'),(670,1,NULL,NULL,NULL,'material.composition.save','Record 27.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:23:52'),(671,1,NULL,NULL,NULL,'material.composition.save','Record 28.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:24:18'),(672,1,NULL,NULL,NULL,'material.composition.save','Record 29.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:24:36'),(673,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:30:08'),(674,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-20 16:38:16'),(675,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-21 05:52:34'),(676,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-21 05:52:36'),(677,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0','2026-11-21 06:20:26'),(678,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 07:57:03'),(679,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 07:57:05'),(680,1,NULL,NULL,NULL,'material.estimate.export','Exported 10 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 07:57:47'),(681,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:45:33'),(682,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:45:34'),(683,1,NULL,NULL,NULL,'material.estimate.save','Record 46.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:46:53'),(684,1,NULL,NULL,NULL,'material.estimate.save','Record 47.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:48:59'),(685,1,NULL,NULL,NULL,'material.estimate.save','Record 48.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:49:21'),(686,1,NULL,NULL,NULL,'material.estimate.save','Record 49.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:49:38'),(687,1,NULL,NULL,NULL,'material.estimate.save','Record 49.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:49:57'),(688,1,NULL,NULL,NULL,'material.estimate.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:56:07'),(689,1,NULL,NULL,NULL,'material.estimate.save','Record 51.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:59:10'),(690,1,NULL,NULL,NULL,'material.estimate.save','Record 52.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:59:39'),(691,1,NULL,NULL,NULL,'material.estimate.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 08:59:53'),(692,1,NULL,NULL,NULL,'material.estimate.save','Record 53.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 09:00:21'),(693,1,NULL,NULL,NULL,'material.estimate.save','Record 54.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 09:00:42'),(694,1,NULL,NULL,NULL,'material.estimate.save','Record 55.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 09:01:12'),(695,1,NULL,NULL,NULL,'material.estimate.export','Exported 10 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 09:06:14'),(696,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 12:40:55'),(697,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 12:41:13'),(698,1,NULL,NULL,NULL,'material.sample.save','Record 24.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 12:41:55'),(699,1,NULL,NULL,NULL,'material.sample.save','Record 25.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 12:42:24'),(700,1,NULL,NULL,NULL,'bom.costing.create','Costing 1 created for material 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 12:43:31'),(701,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:10:02'),(702,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:10:04'),(703,1,NULL,NULL,NULL,'document.issue.create','Created issue 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:45:53'),(704,1,NULL,NULL,NULL,'bom.catalog.save','Resource catalog 1 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:47:43'),(705,1,NULL,NULL,NULL,'material.quotation.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:49:19'),(706,1,NULL,NULL,NULL,'bom.costing.recalc','Costing 1 recalculated (committed=1).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 13:53:01'),(707,1,NULL,NULL,NULL,'bom.resource.save','Costing 1 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-21 14:04:21'),(708,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 01:06:33'),(709,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 01:06:35'),(710,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 01:30:39'),(711,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 04:44:47'),(712,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 04:44:49'),(713,1,NULL,NULL,NULL,'material.save','Record 126.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 04:46:17'),(714,1,NULL,NULL,NULL,'material.save','Record 128.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 04:47:06'),(715,1,NULL,NULL,NULL,'material.composition.save','Record 30.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 04:47:19'),(716,1,2,'closeout_plan',1,'closeout.plan.create','{\"plan_no\":\"P2-CLO-PLAN-001\",\"plan_name\":\"Coding办公室赶工消项计划\",\"status\":\"active\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:34:38'),(717,1,2,'closeout_item',1,'closeout.item.create','{\"item_no\":\"P2-CLO-001\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":33,\"material_name\":\"12厚超白钢化玻璃双开门(M1526B)\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:42:34'),(718,1,2,'closeout_item',2,'closeout.item.create','{\"item_no\":\"P2-CLO-002\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":126,\"material_name\":\"软膜天花\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:42:34'),(719,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:42:52'),(720,1,2,'closeout_item',2,'closeout.item.start','{\"item_no\":\"P2-CLO-002\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:44:06'),(721,1,2,'closeout_item',1,'closeout.item.start','{\"item_no\":\"P2-CLO-001\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:44:22'),(722,1,NULL,NULL,NULL,'material.save','Record 129.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:46:30'),(723,1,NULL,NULL,NULL,'material.save','Record 130.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:47:16'),(724,1,NULL,NULL,NULL,'bom.costing.create','Costing 2 created for material 46.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:48:06'),(725,1,NULL,NULL,NULL,'bom.composition.save','Costing 2 composition 31 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:48:20'),(726,1,NULL,NULL,NULL,'bom.composition.save','Costing 2 composition 32 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:48:29'),(727,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 2 confirmed.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:48:45'),(728,1,NULL,NULL,NULL,'bom.catalog.save','Resource catalog 2 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:49:33'),(729,1,NULL,NULL,NULL,'bom.resource.save','Costing 2 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:50:00'),(730,1,NULL,NULL,NULL,'bom.costing.quantities.save','Costing 2 quantities saved: compositions=0 resources=1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:50:17'),(731,1,2,'closeout_item',3,'closeout.item.create','{\"item_no\":\"P2-CLO-003\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":46,\"material_name\":\"20宽铝合金线型灯\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:51:03'),(732,1,2,'closeout_item',3,'closeout.item.start','{\"item_no\":\"P2-CLO-003\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 05:51:24'),(733,1,2,'closeout_item',4,'closeout.item.create','{\"item_no\":\"P2-CLO-004\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":17,\"material_name\":\"12厚超白钢化玻璃开门(M1126B)\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:23:23'),(734,1,2,'closeout_item',5,'closeout.item.create','{\"item_no\":\"P2-CLO-005\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":34,\"material_name\":\"12厚超白钢化玻璃门(M1122B)\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:23:23'),(735,1,2,'closeout_item',6,'closeout.item.create','{\"item_no\":\"P2-CLO-006\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":32,\"material_name\":\"木门外包银灰不锈钢(M1024C)\",\"coverage_mode\":\"whole\",\"planned_end_date\":null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:23:23'),(736,1,2,'closeout_item',0,'closeout.batch.update','{\"requested\":3,\"updated\":3,\"skipped\":0,\"planned_start_date\":\"2026-11-22\",\"planned_end_date\":\"2026-11-26\",\"responsible_user_id\":\"\",\"responsible_supplier_id\":\"5\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:24:00'),(737,1,2,'closeout_item',0,'closeout.batch.update','{\"requested\":3,\"updated\":3,\"skipped\":0,\"planned_start_date\":\"2026-11-22\",\"planned_end_date\":\"2026-11-26\",\"responsible_user_id\":\"\",\"responsible_supplier_id\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:24:24'),(738,1,2,'closeout_item',6,'closeout.item.start','{\"item_no\":\"P2-CLO-006\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:24:28'),(739,1,2,'closeout_item',5,'closeout.item.start','{\"item_no\":\"P2-CLO-005\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:24:40'),(740,1,2,'closeout_item',4,'closeout.item.start','{\"item_no\":\"P2-CLO-004\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 06:24:54'),(741,1,NULL,NULL,NULL,'material.save','Record 131.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:25:51'),(742,1,NULL,NULL,NULL,'bom.costing.create','Costing 3 created for material 131.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:38:30'),(743,1,NULL,NULL,NULL,'bom.composition.save','Costing 3 composition 33 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:39:07'),(744,1,NULL,NULL,NULL,'bom.resource.save','Costing 3 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:39:25'),(745,1,NULL,NULL,NULL,'bom.cost_price.save','Cost price 1 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:40:27'),(746,1,NULL,NULL,NULL,'bom.cost_price.save','Cost price 2 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:40:41'),(747,1,NULL,NULL,NULL,'bom.costing.quantities.save','Costing 3 quantities saved: compositions=0 resources=1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:41:03'),(748,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 3 confirmed (save+confirm).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:41:03'),(749,1,NULL,NULL,NULL,'bom.costing.create','Costing 4 created for material 56.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 07:43:45'),(750,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 08:42:47'),(751,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 08:42:49'),(752,1,NULL,NULL,NULL,'material.composition.save','Record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:04:57'),(753,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:50:36'),(754,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:50:37'),(755,1,NULL,NULL,NULL,'material.category.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:50:58'),(756,1,NULL,NULL,NULL,'material.save','Record 132.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:54:31'),(757,1,NULL,NULL,NULL,'material.save','Record 133.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:59:13'),(758,1,NULL,NULL,NULL,'material.category.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:59:32'),(759,1,NULL,NULL,NULL,'material.category.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 09:59:51'),(760,1,NULL,NULL,NULL,'material.category.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:00:01'),(761,1,NULL,NULL,NULL,'material.category.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:00:08'),(762,1,NULL,NULL,NULL,'material.category.save','Record 5.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:00:36'),(763,1,NULL,NULL,NULL,'material.composition.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:02:51'),(764,1,NULL,NULL,NULL,'material.composition.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:03:43'),(765,1,NULL,NULL,NULL,'bom.costing.create','Costing 5 created for material 132.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:03:59'),(766,1,NULL,NULL,NULL,'bom.composition.save','Costing 5 composition 37 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:05:32'),(767,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:06:49'),(768,1,NULL,NULL,NULL,'bom.composition.save','Costing 5 composition 38 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:08:13'),(769,1,NULL,NULL,NULL,'bom.composition.save','Costing 5 composition 39 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:08:57'),(770,1,NULL,NULL,NULL,'bom.costing.create','Costing 6 created for material 17.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:14:36'),(771,1,NULL,NULL,NULL,'bom.composition.save','Costing 6 composition 40 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:18:12'),(772,1,NULL,NULL,NULL,'bom.composition.save','Costing 5 composition 41 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:20:52'),(773,1,NULL,NULL,NULL,'bom.costing.quantities.save','Costing 5 quantities saved: compositions=1 resources=0.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:21:04'),(774,1,NULL,NULL,NULL,'material.save','Record 134.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:22:36'),(775,1,NULL,NULL,NULL,'material.supplier_material.save','Record 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:26:24'),(776,1,NULL,NULL,NULL,'material.quotation.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:26:48'),(777,1,NULL,NULL,NULL,'bom.composition.save','Costing 5 composition 42 saved on parent material.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:29:06'),(778,1,NULL,NULL,NULL,'bom.costing.create','Costing 7 created for material 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:30:35'),(779,1,NULL,NULL,NULL,'bom.costing.create','Costing 8 created for material 58.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:30:57'),(780,1,NULL,NULL,NULL,'bom.costing.create','Costing 9 created for material 32.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:31:07'),(781,1,NULL,NULL,NULL,'bom.costing.create','Costing 10 created for material 126.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:31:20'),(782,1,NULL,NULL,NULL,'material.composition.delete','Record 38.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-22 10:51:49'),(783,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 05:22:08'),(784,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 05:22:09'),(785,1,NULL,NULL,NULL,'material.estimate.export','Exported 10 estimate rows for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 05:27:50'),(786,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 07:51:48'),(787,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 07:51:50'),(788,1,NULL,NULL,NULL,'material.quotation.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 07:52:49'),(789,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:04:56'),(790,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:04:57'),(791,1,NULL,NULL,NULL,'material.demand.export','Exported 41 demand rows (5 material codes) for project 2 (all records).',NULL,NULL,'2026-11-23 13:10:17'),(792,1,NULL,NULL,NULL,'material.save','Record 135.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:12:09'),(793,1,NULL,NULL,NULL,'material.composition.save','Record 43.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:12:22'),(794,1,NULL,NULL,NULL,'bom.costing.create','Costing 11 created for material 135.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:12:41'),(795,1,NULL,NULL,NULL,'bom.resource.save','Costing 11 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:12:55'),(796,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 11 confirmed.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:13:08'),(797,1,NULL,NULL,NULL,'material.estimate.save','Record 49.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:13:33'),(798,1,NULL,NULL,NULL,'material.estimate.save','Record 48.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:13:47'),(799,1,NULL,NULL,NULL,'material.estimate.save','Record 47.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:13:57'),(800,1,NULL,NULL,NULL,'material.estimate.save','Record 46.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:14:22'),(801,1,NULL,NULL,NULL,'material.estimate.save','Record 55.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:16:26'),(802,1,NULL,NULL,NULL,'material.estimate.save','Record 54.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:16:48'),(803,1,NULL,NULL,NULL,'material.estimate.save','Record 53.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:17:13'),(804,1,NULL,NULL,NULL,'material.estimate.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:17:29'),(805,1,NULL,NULL,NULL,'material.estimate.save','Record 52.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:17:41'),(806,1,NULL,NULL,NULL,'material.estimate.save','Record 51.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:17:52'),(807,1,NULL,NULL,NULL,'material.save','Record 135.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:18:30'),(808,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:21:23'),(809,1,NULL,NULL,NULL,'bom.resource.save','Costing 5 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:22:37'),(810,1,NULL,NULL,NULL,'bom.resource.save','Costing 5 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:22:54'),(811,1,NULL,NULL,NULL,'bom.costing.quantities.save','Costing 5 quantities saved: compositions=0 resources=1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:23:36'),(812,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 5 confirmed.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:24:10'),(813,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:25:37'),(814,1,NULL,NULL,NULL,'material.demand.export','Exported 51 demand rows (6 material codes) for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:25:57'),(815,1,2,'closeout_item',7,'closeout.item.create','{\"item_no\":\"P2-CLO-007\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":132}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:29:55'),(816,1,2,'closeout_item',8,'closeout.item.create','{\"item_no\":\"P2-CLO-008\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":135}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:29:55'),(817,1,2,'closeout_item',9,'closeout.item.create','{\"item_no\":\"P2-CLO-009\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":58}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:29:55'),(818,1,2,'closeout_item',10,'closeout.item.create','{\"item_no\":\"P2-CLO-010\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":131}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:29:55'),(819,1,NULL,NULL,NULL,'material.demand.export','Exported 51 demand rows (6 material codes) for project 2 (all records).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:30:32'),(820,1,2,'closeout_item',6,'closeout.progress.add','{\"item_no\":\"P2-CLO-006\",\"note\":\"在定制中\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:38:27'),(821,1,2,'closeout_item',8,'closeout.item.update','{\"item_no\":\"P2-CLO-008\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:51:29'),(822,1,2,'closeout_item',8,'closeout.item.start','{\"item_no\":\"P2-CLO-008\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:51:31'),(823,1,2,'closeout_item',8,'closeout.progress.add','{\"item_no\":\"P2-CLO-008\",\"note\":\"安装还差3根轨道，需要补发\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:52:01'),(824,1,2,'closeout_item',10,'closeout.item.update','{\"item_no\":\"P2-CLO-010\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:53:48'),(825,1,2,'closeout_item',10,'closeout.item.start','{\"item_no\":\"P2-CLO-010\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:53:50'),(826,1,2,'closeout_item',0,'closeout.batch.update','{\"updated\":2,\"skipped\":0}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:54:13'),(827,1,2,'closeout_item',7,'closeout.item.start','{\"item_no\":\"P2-CLO-007\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:54:29'),(828,1,2,'closeout_item',9,'closeout.item.start','{\"item_no\":\"P2-CLO-009\",\"from\":\"pending\",\"to\":\"ongoing\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 13:54:37'),(829,1,2,'closeout_item',6,'closeout.item.submit','{\"item_no\":\"P2-CLO-006\",\"note\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:24:01'),(830,1,2,'closeout_item',0,'closeout.batch.update','{\"updated\":10,\"skipped\":0}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:25:14'),(831,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:26:57'),(832,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:27:10'),(833,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:31:45'),(834,1,2,'closeout_item',10,'closeout.item.update','{\"item_no\":\"P2-CLO-010\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:33:17'),(835,1,NULL,NULL,NULL,'labor.crew.save','Crew 31 (自流平) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:37:08'),(836,1,NULL,NULL,NULL,'labor.crew.save','Crew 31 (自流平) in project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:37:17'),(837,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:37:57'),(838,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:38:01'),(839,1,NULL,NULL,NULL,'material.supplier.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:57:37'),(840,1,NULL,NULL,NULL,'material.supplier.save','Record 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:57:51'),(841,1,NULL,NULL,NULL,'material.supplier.save','Record 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 14:58:36'),(842,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:02:23'),(843,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:02:27'),(844,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:02:39'),(845,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:21:28'),(846,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:22:41'),(847,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:27:19'),(848,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 15:53:54'),(849,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 16:55:19'),(850,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 16:55:20'),(851,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 18:29:51'),(852,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-23 18:29:56'),(853,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 12:44:56'),(854,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 12:44:57'),(855,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 12:49:48'),(856,1,NULL,NULL,NULL,'document.issue.create','Created issue 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 12:52:38'),(857,1,NULL,NULL,NULL,'document.issue.follow_up.create','Created issue follow-up 1 for issue 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:01:09'),(858,1,NULL,NULL,NULL,'document.issue.update','Updated ordinary fields for issue 4.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:01:25'),(859,1,NULL,NULL,NULL,'document.issue.follow_up.complete','Completed issue follow-up 1. Reason: 改用本地代理货源','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:02:16'),(860,1,NULL,NULL,NULL,'document.issue.status_change','Issue 4: open -> in_review. Reason: 改用本地代理商货源','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:03:20'),(861,1,NULL,NULL,NULL,'document.rfi.status_change','RFI P1-RFI-001: draft -> submitted. Reason: 报审未通过','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:05:56'),(862,1,NULL,NULL,NULL,'document.rfi.update','Updated RFI P1-RFI-001.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:06:05'),(863,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:45:40'),(864,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:45:45'),(865,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:46:02'),(866,1,NULL,NULL,NULL,'document.rfi.status_change','RFI P1-RFI-001: submitted -> under_review. Reason: 报审','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:47:22'),(867,1,NULL,NULL,NULL,'document.rfi.respond','RFI P1-RFI-001: under_review -> responded. Reason: 报审','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:47:56'),(868,1,NULL,NULL,NULL,'document.rfi.status_change','RFI P1-RFI-001: responded -> closed. Reason: 报审','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:48:13'),(869,1,NULL,NULL,NULL,'document.issue.status_change','Issue 5: in_review -> resolved. Reason: 关联 RFI P1-RFI-001 已关闭，该问题名下 RFI 已全部收口，系统自动转为待关闭，等待复核。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 13:48:13'),(870,1,NULL,NULL,NULL,'document.issue.status_change','Issue 5: resolved -> closed. Reason: 快捷操作：关闭问题。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:09:08'),(871,1,NULL,NULL,NULL,'document.issue.status_change','Issue 4: in_review -> resolved. Reason: 快捷操作：转待关闭。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:17:27'),(872,1,NULL,NULL,NULL,'document.issue.status_change','Issue 4: resolved -> closed. Reason: 快捷操作：关闭问题。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:17:53'),(873,1,NULL,NULL,NULL,'document.issue.status_change','Issue 7: in_review -> resolved. Reason: 快捷操作：转待关闭。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:18:34'),(874,1,NULL,NULL,NULL,'document.issue.status_change','Issue 3: open -> in_review. Reason: 快捷操作：开始处理。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:18:45'),(875,1,NULL,NULL,NULL,'document.issue.status_change','Issue 9: open -> in_review. Reason: 快捷操作：开始处理。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:18:47'),(876,1,NULL,NULL,NULL,'document.issue.status_change','Issue 3: in_review -> resolved. Reason: 快捷操作：转待关闭。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:19:10'),(877,1,NULL,NULL,NULL,'document.issue.status_change','Issue 1: open -> in_review. Reason: 快捷操作：开始处理。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:20:01'),(878,1,NULL,NULL,NULL,'document.issue.status_change','Issue 1: in_review -> resolved. Reason: 快捷操作：转待关闭。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:21:27'),(879,1,NULL,NULL,NULL,'document.issue.status_change','Issue 1: resolved -> closed. Reason: 快捷操作：关闭问题。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:22:08'),(880,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 14:58:23'),(881,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 15:11:53'),(882,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 15:12:58'),(883,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-24 15:56:21'),(884,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 04:50:24'),(885,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 04:50:26'),(886,1,NULL,NULL,NULL,'material.save','Record 136.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:07:57'),(887,1,NULL,NULL,NULL,'material.composition.save','Record 44.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:15:06'),(888,1,NULL,NULL,NULL,'material.save','Record 136.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:17:04'),(889,1,NULL,NULL,NULL,'material.save','Record 136.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:17:18'),(890,1,NULL,NULL,NULL,'material.save','Record 136.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:17:40'),(891,1,NULL,NULL,NULL,'material.save','Record 136.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:18:06'),(892,1,NULL,NULL,NULL,'material.save','Record 137.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:20:35'),(893,1,NULL,NULL,NULL,'material.composition.save','Record 45.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:21:04'),(894,1,NULL,NULL,NULL,'material.save','Record 137.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:23:08'),(895,1,NULL,NULL,NULL,'material.save','Record 134.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:24:27'),(896,1,NULL,NULL,NULL,'material.save','Record 138.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:28:08'),(897,1,NULL,NULL,NULL,'material.composition.save','Record 46.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:29:23'),(898,1,NULL,NULL,NULL,'material.save','Record 138.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:29:47'),(899,1,NULL,NULL,NULL,'material.save','Record 139.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:31:16'),(900,1,NULL,NULL,NULL,'material.composition.save','Record 47.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:31:39'),(901,1,NULL,NULL,NULL,'material.save','Record 140.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:32:44'),(902,1,NULL,NULL,NULL,'material.composition.save','Record 48.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:33:02'),(903,1,NULL,NULL,NULL,'material.save','Record 141.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:35:08'),(904,1,NULL,NULL,NULL,'material.save','Record 138.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:36:33'),(905,1,NULL,NULL,NULL,'material.composition.save','Record 49.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:36:45'),(906,1,NULL,NULL,NULL,'material.composition.save','Record 50.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:37:01'),(907,1,NULL,NULL,NULL,'material.composition.save','Record 51.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:37:17'),(908,1,NULL,NULL,NULL,'material.composition.save','Record 52.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:37:26'),(909,1,NULL,NULL,NULL,'material.estimate.save','Record 56.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:40:58'),(910,1,NULL,NULL,NULL,'project_space.save','Saved project space record 26.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:41:45'),(911,1,NULL,NULL,NULL,'material.estimate.save','Record 57.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:42:05'),(912,1,NULL,NULL,NULL,'project_space.save','Saved project space record 27.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:43:13'),(913,1,NULL,NULL,NULL,'material.estimate.save','Record 58.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:43:39'),(914,1,NULL,NULL,NULL,'material.estimate.save','Record 59.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:43:58'),(915,1,NULL,NULL,NULL,'material.estimate.save','Record 60.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:44:35'),(916,1,NULL,NULL,NULL,'material.estimate.save','Record 61.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:47:20'),(917,1,NULL,NULL,NULL,'material.estimate.save','Record 62.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 05:47:34'),(918,1,NULL,NULL,NULL,'document.issue.create','Created issue 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:25:46'),(919,1,NULL,NULL,NULL,'document.issue.status_change','Issue 10: open -> in_review. Reason: 快捷操作：开始处理。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:27:08'),(920,1,NULL,NULL,NULL,'document.issue.status_change','Issue 10: in_review -> resolved. Reason: 快捷操作：转待关闭。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:27:51'),(921,1,NULL,NULL,NULL,'document.issue.image.upload','Added an image to issue 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:34:17'),(922,1,NULL,NULL,NULL,'material.save','Record 142.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:52:47'),(923,1,NULL,NULL,NULL,'material.composition.save','Record 53.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:52:57'),(924,1,NULL,NULL,NULL,'material.estimate.save','Record 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:54:12'),(925,1,NULL,NULL,NULL,'material.estimate.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:54:25'),(926,1,NULL,NULL,NULL,'material.estimate.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:54:35'),(927,1,NULL,NULL,NULL,'material.estimate.save','Record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:54:45'),(928,1,NULL,NULL,NULL,'bom.costing.create','Costing 12 created for material 142.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:54:55'),(929,1,NULL,NULL,NULL,'bom.catalog.save','Resource catalog 3 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:56:10'),(930,1,NULL,NULL,NULL,'bom.cost_price.save','Cost price 3 saved.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 06:56:39'),(931,1,NULL,NULL,NULL,'bom.resource.save','Costing 12 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 07:18:23'),(932,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 12 confirmed.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 07:18:31'),(933,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 08:08:27'),(934,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 11:54:33'),(935,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 11:54:35'),(936,1,NULL,NULL,NULL,'document.issue.export','Exported 4 issue rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:08:24'),(937,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:09:42'),(938,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:10:09'),(939,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:12:17'),(940,1,NULL,NULL,NULL,'material.save','Record 143.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:35:03'),(941,1,NULL,NULL,NULL,'material.save','Record 144.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:37:47'),(942,1,NULL,NULL,NULL,'material.save','Record 3.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:38:06'),(943,1,NULL,NULL,NULL,'profile.display_name.update','Changed own display name.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:42:07'),(944,1,NULL,NULL,NULL,'document.issue.image.link','Added an external image link to issue 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 12:44:37'),(945,1,NULL,NULL,NULL,'document.issue.status_change','Issue 10: resolved -> in_review. Reason: 木饰面也随着变更','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 13:10:17'),(946,1,NULL,NULL,NULL,'platform.settings.update','Updated platform settings.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 13:23:38'),(947,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:00:27'),(948,1,NULL,NULL,NULL,'project_space.save','Saved project space record 28.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:03:15'),(949,1,NULL,NULL,NULL,'project_space.save','Saved project space record 29.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:03:46'),(950,1,NULL,NULL,NULL,'project_space.save','Saved project space record 30.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:04:15'),(951,1,NULL,NULL,NULL,'project_space.save','Saved project space record 31.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:04:29'),(952,1,NULL,NULL,NULL,'project_space.save','Saved project space record 32.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:04:48'),(953,1,NULL,NULL,NULL,'project_space.save','Saved project space record 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:05:03'),(954,1,NULL,NULL,NULL,'project_space.save','Saved project space record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:05:18'),(955,1,NULL,NULL,NULL,'project_space.save','Saved project space record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:05:34'),(956,1,NULL,NULL,NULL,'project_space.save','Saved project space record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:05:46'),(957,1,NULL,NULL,NULL,'project_space.save','Saved project space record 37.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:05:57'),(958,1,NULL,NULL,NULL,'project_space.save','Saved project space record 38.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:06:06'),(959,1,NULL,NULL,NULL,'project_space.save','Saved project space record 39.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:06:18'),(960,1,NULL,NULL,NULL,'project_space.save','Saved project space record 40.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:06:30'),(961,1,NULL,NULL,NULL,'project_space.save','Saved project space record 41.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:06:52'),(962,1,NULL,NULL,NULL,'material.sample.save','Record 26.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:13:22'),(963,1,NULL,NULL,NULL,'material.sample.save','Record 27.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:14:02'),(964,1,NULL,NULL,NULL,'material.sample.save','Record 28.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:14:41'),(965,1,NULL,NULL,NULL,'material.sample.save','Record 29.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:15:15'),(966,1,NULL,NULL,NULL,'material.supplier.save','Record 7.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:20:49'),(967,1,NULL,NULL,NULL,'material.supplier.save','Record 8.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:24:23'),(968,1,NULL,NULL,NULL,'material.sample.save','Record 30.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:25:16'),(969,1,NULL,NULL,NULL,'material.supplier.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:26:42'),(970,1,NULL,NULL,NULL,'material.supplier.save','Record 9.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:27:09'),(971,1,NULL,NULL,NULL,'material.sample.save','Record 31.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:27:51'),(972,1,NULL,NULL,NULL,'material.sample.save','Record 32.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:28:25'),(973,1,NULL,NULL,NULL,'material.sample.save','Record 33.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:29:12'),(974,1,NULL,NULL,NULL,'material.sample.save','Record 34.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:30:39'),(975,1,NULL,NULL,NULL,'material.sample.save','Record 35.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:31:09'),(976,1,NULL,NULL,NULL,'material.sample.save','Record 36.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:31:43'),(977,1,NULL,NULL,NULL,'material.sample.save','Record 37.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:33:15'),(978,1,NULL,NULL,NULL,'material.supplier.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:35:29'),(979,1,NULL,NULL,NULL,'material.supplier.save','Record 10.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:35:43'),(980,1,NULL,NULL,NULL,'material.sample.save','Record 38.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:36:31'),(981,1,NULL,NULL,NULL,'material.sample.save','Record 39.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:37:03'),(982,1,NULL,NULL,NULL,'material.sample.save','Record 40.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-25 14:37:35'),(983,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 08:39:24'),(984,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 08:39:25'),(985,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:45:32'),(986,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:45:33'),(987,1,NULL,NULL,NULL,'project.select','Selected project 1.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:46:01'),(988,1,NULL,NULL,NULL,'document.issue.status_change','Issue 3: resolved -> closed. Reason: 快捷操作：关闭问题。','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:46:55'),(989,1,NULL,NULL,NULL,'document.issue.create','Created issue 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:50:03'),(990,1,NULL,NULL,NULL,'document.issue.update','Updated ordinary fields for issue 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:51:34'),(991,1,NULL,NULL,NULL,'document.issue.image.upload','Added an image to issue 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-26 13:55:08'),(992,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:14:17'),(993,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:14:19'),(994,1,NULL,NULL,NULL,'material.save','Record 145.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:16:55'),(995,1,NULL,NULL,NULL,'material.composition.save','Record 54.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:17:09'),(996,1,NULL,NULL,NULL,'material.estimate.save','Record 63.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:18:02'),(997,1,NULL,NULL,NULL,'material.estimate.save','Record 64.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:18:37'),(998,1,NULL,NULL,NULL,'material.save','Record 146.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:25:55'),(999,1,NULL,NULL,NULL,'material.save','Record 147.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:26:32'),(1000,1,NULL,NULL,NULL,'material.composition.save','Record 55.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:26:41'),(1001,1,NULL,NULL,NULL,'material.estimate.save','Record 65.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 02:32:43'),(1002,1,NULL,NULL,NULL,'material.estimate.save','Record 66.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 03:05:36'),(1003,1,NULL,NULL,NULL,'material.estimate.save','Record 67.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 03:06:14'),(1004,1,NULL,NULL,NULL,'material.estimate.save','Record 68.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 03:07:43'),(1005,1,NULL,NULL,NULL,'material.estimate.save','Record 69.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 03:08:35'),(1006,1,NULL,NULL,NULL,'material.demand.export','Exported 5 demand rows (1 material codes) for project 2 (search filter applied).','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 03:13:35'),(1007,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 06:20:18'),(1008,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 06:20:20'),(1009,1,NULL,NULL,NULL,'document.issue.export','Exported 4 issue rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 06:20:28'),(1010,1,NULL,NULL,NULL,'document.issue.export','Exported 4 issue rows for project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 06:20:30'),(1011,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:16:48'),(1012,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:16:49'),(1013,1,NULL,NULL,NULL,'material.supplier.save','Record 11.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:31:22'),(1014,1,2,'closeout_item',9,'closeout.item.update','{\"item_no\":\"P2-CLO-009\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:31:57'),(1015,1,2,'closeout_item',8,'closeout.item.submit','{\"item_no\":\"P2-CLO-008\",\"note\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:32:03'),(1016,1,NULL,NULL,NULL,'bom.costing.create','Costing 13 created for material 147.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:35:35'),(1017,1,NULL,NULL,NULL,'bom.resource.save','Costing 13 resource updated.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:36:05'),(1018,1,NULL,NULL,NULL,'bom.costing.confirm','Costing 13 confirmed.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 12:36:21'),(1019,1,NULL,NULL,NULL,'login','User signed in.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:31:08'),(1020,1,NULL,NULL,NULL,'project.select','Selected project 2.','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:31:09'),(1021,1,2,'closeout_item',11,'closeout.item.create','{\"item_no\":\"P2-CLO-011\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1022,1,2,'closeout_item',12,'closeout.item.create','{\"item_no\":\"P2-CLO-012\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1023,1,2,'closeout_item',13,'closeout.item.create','{\"item_no\":\"P2-CLO-013\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1024,1,2,'closeout_item',14,'closeout.item.create','{\"item_no\":\"P2-CLO-014\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1025,1,2,'closeout_item',15,'closeout.item.create','{\"item_no\":\"P2-CLO-015\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1026,1,2,'closeout_item',16,'closeout.item.create','{\"item_no\":\"P2-CLO-016\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1027,1,2,'closeout_item',17,'closeout.item.create','{\"item_no\":\"P2-CLO-017\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1028,1,2,'closeout_item',18,'closeout.item.create','{\"item_no\":\"P2-CLO-018\",\"plan_no\":\"P2-CLO-PLAN-001\",\"material_id\":null,\"source\":\"auto-provision-sync\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:49:30'),(1029,1,2,'closeout_location',7,'closeout.location.submit','{\"item_id\":16,\"space\":\"3栋 / 三层 / 排烟机房\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:56:26'),(1030,1,2,'closeout_location',8,'closeout.location.submit','{\"item_id\":16,\"space\":\"3栋 / 三层 / 头脑风暴\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:56:39'),(1031,1,2,'closeout_item',15,'closeout.item.update','{\"item_no\":\"P2-CLO-015\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:57:13'),(1032,1,2,'closeout_item',18,'closeout.item.update','{\"item_no\":\"P2-CLO-018\",\"before_title\":\"\",\"after_title\":\"\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36 Edg/158.0.0.0','2026-11-27 13:57:29');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bom_costing_resources`
--

DROP TABLE IF EXISTS `bom_costing_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bom_costing_resources` (
  `resource_line_id` int NOT NULL AUTO_INCREMENT,
  `costing_id` int NOT NULL,
  `project_id` int NOT NULL,
  `resource_type` enum('auxiliary','processing','mechanical','labor','parent_self') COLLATE utf8mb4_unicode_ci NOT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `material_id` int DEFAULT NULL,
  `resource_key` varchar(60) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(cast(`aux_id` as char charset utf8mb4),concat(_utf8mb4'c',cast(`catalog_id` as char charset utf8mb4)),concat(_utf8mb4'm',cast(`material_id` as char charset utf8mb4)))) STORED,
  `quantity` decimal(18,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resource_line_id`),
  UNIQUE KEY `uq_resource_line_project` (`project_id`,`resource_line_id`),
  UNIQUE KEY `uq_resource_line_unique` (`costing_id`,`resource_type`,`resource_key`),
  KEY `fk_resource_costing` (`project_id`,`costing_id`),
  KEY `fk_resource_aux` (`project_id`,`aux_id`),
  KEY `fk_resource_catalog` (`catalog_id`),
  KEY `fk_resource_material` (`project_id`,`material_id`),
  CONSTRAINT `fk_resource_aux` FOREIGN KEY (`project_id`, `aux_id`) REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_costing` FOREIGN KEY (`project_id`, `costing_id`) REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_material` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_resource_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_resource_quantity_positive` CHECK ((`quantity` > 0)),
  CONSTRAINT `chk_resource_target_one` CHECK ((((`aux_id` is not null) and (`catalog_id` is null) and (`material_id` is null)) or ((`aux_id` is null) and (`catalog_id` is not null) and (`material_id` is null)) or ((`aux_id` is null) and (`catalog_id` is null) and (`material_id` is not null)))),
  CONSTRAINT `chk_resource_type_match` CHECK ((((`resource_type` = _utf8mb4'auxiliary') and (`aux_id` is not null)) or ((`resource_type` in (_utf8mb4'processing',_utf8mb4'mechanical',_utf8mb4'labor')) and (`catalog_id` is not null)) or ((`resource_type` = _utf8mb4'parent_self') and (`material_id` is not null))))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bom_costing_resources`
--

LOCK TABLES `bom_costing_resources` WRITE;
/*!40000 ALTER TABLE `bom_costing_resources` DISABLE KEYS */;
INSERT INTO `bom_costing_resources` (`resource_line_id`, `costing_id`, `project_id`, `resource_type`, `aux_id`, `catalog_id`, `material_id`, `quantity`, `created_at`, `updated_at`) VALUES (1,1,2,'labor',NULL,1,NULL,1.000000,'2026-11-21 14:04:21','2026-11-21 14:04:21'),(2,2,2,'labor',NULL,2,NULL,0.010000,'2026-11-22 05:49:59','2026-11-22 05:50:17'),(3,3,2,'labor',NULL,1,NULL,0.050000,'2026-11-22 07:39:25','2026-11-22 07:41:03'),(4,11,2,'labor',NULL,1,NULL,0.100000,'2026-11-23 13:12:55','2026-11-23 13:12:55'),(5,5,2,'labor',NULL,1,NULL,1.000000,'2026-11-23 13:22:37','2026-11-23 13:22:37'),(6,5,2,'labor',NULL,2,NULL,0.050000,'2026-11-23 13:22:54','2026-11-23 13:23:36'),(7,12,2,'labor',NULL,3,NULL,0.125000,'2026-11-25 07:18:23','2026-11-25 07:18:23'),(8,13,2,'labor',NULL,1,NULL,0.200000,'2026-11-27 12:36:05','2026-11-27 12:36:05');
/*!40000 ALTER TABLE `bom_costing_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bom_costings`
--

DROP TABLE IF EXISTS `bom_costings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bom_costings` (
  `costing_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `parent_material_id` int NOT NULL,
  `status` enum('draft','confirmed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `calc_status` enum('incomplete','calculable','complete') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incomplete',
  `version` bigint NOT NULL DEFAULT '0',
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`costing_id`),
  UNIQUE KEY `uq_costing_project_parent` (`project_id`,`parent_material_id`),
  UNIQUE KEY `uq_costing_project_id` (`project_id`,`costing_id`),
  KEY `fk_costing_confirmed_by` (`confirmed_by`),
  KEY `fk_costing_created_by` (`created_by`),
  CONSTRAINT `fk_costing_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_parent_material` FOREIGN KEY (`project_id`, `parent_material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_costing_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bom_costings`
--

LOCK TABLES `bom_costings` WRITE;
/*!40000 ALTER TABLE `bom_costings` DISABLE KEYS */;
INSERT INTO `bom_costings` VALUES (1,2,33,'draft','calculable',1,NULL,NULL,1,'2026-11-21 12:43:31','2026-11-22 01:29:29'),(2,2,46,'confirmed','calculable',2,1,'2026-11-22 05:48:45',1,'2026-11-22 05:48:06','2026-11-22 10:06:41'),(3,2,131,'confirmed','complete',2,1,'2026-11-22 07:41:03',1,'2026-11-22 07:38:30','2026-11-22 10:27:16'),(4,2,56,'draft','incomplete',0,NULL,NULL,1,'2026-11-22 07:43:45','2026-11-22 07:43:46'),(5,2,132,'confirmed','calculable',4,1,'2026-11-23 13:24:10',1,'2026-11-22 10:03:59','2026-11-25 05:46:47'),(6,2,17,'draft','calculable',0,NULL,NULL,1,'2026-11-22 10:14:36','2026-11-22 10:18:12'),(7,2,34,'draft','complete',0,NULL,NULL,1,'2026-11-22 10:30:35','2026-11-22 10:30:35'),(8,2,58,'draft','incomplete',0,NULL,NULL,1,'2026-11-22 10:30:57','2026-11-22 10:30:57'),(9,2,32,'draft','complete',0,NULL,NULL,1,'2026-11-22 10:31:07','2026-11-22 10:31:07'),(10,2,126,'draft','incomplete',0,NULL,NULL,1,'2026-11-22 10:31:20','2026-11-22 10:31:20'),(11,2,135,'confirmed','calculable',1,1,'2026-11-23 13:13:08',1,'2026-11-23 13:12:41','2026-11-23 13:13:09'),(12,2,142,'confirmed','calculable',1,1,'2026-11-25 07:18:31',1,'2026-11-25 06:54:55','2026-11-25 07:18:31'),(13,2,147,'confirmed','calculable',1,1,'2026-11-27 12:36:21',1,'2026-11-27 12:35:35','2026-11-27 12:36:21');
/*!40000 ALTER TABLE `bom_costings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cost_line_results`
--

DROP TABLE IF EXISTS `cost_line_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_line_results` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `costing_id` int NOT NULL,
  `project_id` int NOT NULL,
  `composition_id` int DEFAULT NULL,
  `resource_line_id` int DEFAULT NULL,
  `quantity` decimal(18,6) NOT NULL,
  `unit_price` decimal(18,4) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `pricing_unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `project_cost_price_id` int DEFAULT NULL,
  `manual_price` decimal(18,4) DEFAULT NULL,
  `manual_confirmed_by` int DEFAULT NULL,
  `source_tax_status` enum('taxable','exempt','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `mgmt_rate` decimal(6,4) DEFAULT NULL,
  `profit_rate` decimal(6,4) DEFAULT NULL,
  `tax_rate` decimal(6,4) DEFAULT NULL,
  `trans_rate` decimal(6,4) DEFAULT NULL,
  `ins_rate` decimal(6,4) DEFAULT NULL,
  `subtotal` decimal(18,4) DEFAULT NULL,
  `mgmt_amount` decimal(18,4) DEFAULT NULL,
  `profit_amount` decimal(18,4) DEFAULT NULL,
  `tax_amount` decimal(18,4) DEFAULT NULL,
  `comprehensive` decimal(18,4) DEFAULT NULL,
  `formula_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'v3.0-structure',
  `rounding_spec_version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calculated_by` int DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uq_result_project_id` (`project_id`,`result_id`),
  UNIQUE KEY `uq_result_composition` (`costing_id`,`composition_id`),
  UNIQUE KEY `uq_result_resource` (`costing_id`,`resource_line_id`),
  KEY `fk_result_costing` (`project_id`,`costing_id`),
  KEY `fk_result_resource` (`project_id`,`resource_line_id`),
  KEY `fk_result_quotation` (`project_id`,`quotation_id`),
  KEY `fk_result_cost_price` (`project_id`,`project_cost_price_id`),
  KEY `fk_result_confirmed_by` (`manual_confirmed_by`),
  KEY `fk_result_calculated_by` (`calculated_by`),
  KEY `fk_result_composition` (`project_id`,`composition_id`),
  CONSTRAINT `fk_result_calculated_by` FOREIGN KEY (`calculated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_composition` FOREIGN KEY (`project_id`, `composition_id`) REFERENCES `Material_Compositions` (`project_id`, `composition_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_confirmed_by` FOREIGN KEY (`manual_confirmed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_cost_price` FOREIGN KEY (`project_id`, `project_cost_price_id`) REFERENCES `project_cost_prices` (`project_id`, `price_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_costing` FOREIGN KEY (`project_id`, `costing_id`) REFERENCES `bom_costings` (`project_id`, `costing_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_quotation` FOREIGN KEY (`project_id`, `quotation_id`) REFERENCES `Material_Quotations` (`project_id`, `quotation_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_resource` FOREIGN KEY (`project_id`, `resource_line_id`) REFERENCES `bom_costing_resources` (`project_id`, `resource_line_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_result_source_one` CHECK ((((`quotation_id` is not null) and (`project_cost_price_id` is null) and (`manual_price` is null)) or ((`quotation_id` is null) and (`project_cost_price_id` is not null) and (`manual_price` is null)) or ((`quotation_id` is null) and (`project_cost_price_id` is null) and (`manual_price` is not null)))),
  CONSTRAINT `chk_result_target_one` CHECK ((((`composition_id` is not null) and (`resource_line_id` is null)) or ((`composition_id` is null) and (`resource_line_id` is not null)))),
  CONSTRAINT `chk_result_unit_price_positive` CHECK ((`unit_price` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cost_line_results`
--

LOCK TABLES `cost_line_results` WRITE;
/*!40000 ALTER TABLE `cost_line_results` DISABLE KEYS */;
INSERT INTO `cost_line_results` VALUES (39,1,2,25,NULL,3.900000,120.0000,'CNY','',8,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 01:29:29',1),(44,2,2,NULL,2,0.010000,300.0000,'CNY','工日',NULL,2,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:06:41',1),(47,6,2,27,NULL,2.860000,120.0000,'CNY','',8,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:18:12',1),(49,3,2,33,NULL,0.060000,115.0000,'CNY','',9,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:27:16',1),(50,3,2,NULL,3,0.050000,300.0000,'CNY','工日',NULL,1,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:27:16',1),(56,7,2,28,NULL,2.420000,120.0000,'CNY','',8,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:30:35',1),(58,9,2,29,NULL,1.000000,115.0000,'CNY','',9,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-22 10:31:07',1),(62,11,2,NULL,4,0.100000,300.0000,'CNY','工日',NULL,1,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-23 13:13:09',1),(84,5,2,42,NULL,2.840000,320.0000,'CNY','',10,NULL,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-25 05:46:47',1),(85,5,2,NULL,5,1.000000,300.0000,'CNY','工日',NULL,1,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-25 05:46:47',1),(86,5,2,NULL,6,0.050000,300.0000,'CNY','工日',NULL,2,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-25 05:46:47',1),(87,12,2,NULL,7,0.125000,350.0000,'CNY','工日',NULL,3,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-25 07:18:23',1),(89,13,2,NULL,8,0.200000,300.0000,'CNY','工日',NULL,1,NULL,NULL,'unknown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'v3.0-structure','pending','2026-11-27 12:36:05',1);
/*!40000 ALTER TABLE `cost_line_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cost_settings`
--

DROP TABLE IF EXISTS `cost_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `scope_type` enum('project','building') COLLATE utf8mb4_unicode_ci NOT NULL,
  `building_id` int DEFAULT NULL,
  `building_key` varchar(60) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`building_id` is null) then _utf8mb4'project' else concat(_utf8mb4'b',cast(`building_id` as char charset utf8mb4)) end)) STORED,
  `mgmt_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `profit_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `tax_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `trans_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `ins_rate` decimal(6,4) NOT NULL DEFAULT '0.0000',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uq_settings_project_scope` (`project_id`,`building_key`),
  KEY `fk_settings_building` (`building_id`),
  KEY `fk_settings_created_by` (`created_by`),
  KEY `fk_settings_updated_by` (`updated_by`),
  CONSTRAINT `fk_settings_building` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_settings_scope_building` CHECK ((((`scope_type` = _utf8mb4'project') and (`building_id` is null)) or ((`scope_type` = _utf8mb4'building') and (`building_id` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cost_settings`
--

LOCK TABLES `cost_settings` WRITE;
/*!40000 ALTER TABLE `cost_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `cost_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_issue_follow_up_history`
--

DROP TABLE IF EXISTS `document_issue_follow_up_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_issue_follow_up_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `follow_up_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_issue_follow_up_history` (`project_id`,`follow_up_id`,`changed_at`),
  KEY `fk_doc_issue_follow_up_history_item` (`follow_up_id`,`project_id`),
  KEY `fk_doc_issue_follow_up_history_user` (`changed_by`),
  CONSTRAINT `fk_doc_issue_follow_up_history_item` FOREIGN KEY (`follow_up_id`, `project_id`) REFERENCES `document_issue_follow_ups` (`follow_up_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_issue_follow_up_history`
--

LOCK TABLES `document_issue_follow_up_history` WRITE;
/*!40000 ALTER TABLE `document_issue_follow_up_history` DISABLE KEYS */;
INSERT INTO `document_issue_follow_up_history` VALUES (1,1,1,'open','completed','改用本地代理货源',NULL,1,'2026-11-24 13:02:16');
/*!40000 ALTER TABLE `document_issue_follow_up_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_issue_follow_ups`
--

DROP TABLE IF EXISTS `document_issue_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_issue_follow_ups` (
  `follow_up_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `completed_by` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follow_up_id`),
  UNIQUE KEY `uq_doc_issue_follow_up_project` (`follow_up_id`,`project_id`),
  KEY `idx_doc_issue_follow_ups` (`project_id`,`issue_id`,`is_completed`),
  KEY `fk_doc_issue_follow_up_issue` (`issue_id`,`project_id`),
  KEY `fk_doc_issue_follow_up_creator` (`created_by`),
  KEY `fk_doc_issue_follow_up_completer` (`completed_by`),
  CONSTRAINT `fk_doc_issue_follow_up_completer` FOREIGN KEY (`completed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_follow_up_issue` FOREIGN KEY (`issue_id`, `project_id`) REFERENCES `document_issues` (`issue_id`, `project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_issue_follow_ups`
--

LOCK TABLES `document_issue_follow_ups` WRITE;
/*!40000 ALTER TABLE `document_issue_follow_ups` DISABLE KEYS */;
INSERT INTO `document_issue_follow_ups` VALUES (1,1,4,'改用本地代理机器',1,1,1,'2026-11-24 13:02:16','2026-11-24 13:01:09');
/*!40000 ALTER TABLE `document_issue_follow_ups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_issue_images`
--

DROP TABLE IF EXISTS `document_issue_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_issue_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `issue_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '私有对象存储键；外链图片为 NULL',
  `external_url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'https 外链图片地址；有值时不再读取对象存储',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_document_issue_image_project` (`project_id`,`issue_id`),
  KEY `fk_document_issue_image_issue` (`issue_id`,`project_id`),
  KEY `fk_document_issue_image_user` (`uploaded_by`),
  CONSTRAINT `fk_document_issue_image_issue` FOREIGN KEY (`issue_id`, `project_id`) REFERENCES `document_issues` (`issue_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_document_issue_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_issue_images`
--

LOCK TABLES `document_issue_images` WRITE;
/*!40000 ALTER TABLE `document_issue_images` DISABLE KEYS */;
INSERT INTO `document_issue_images` VALUES (3,10,2,'issues/2/10/a12c7f5f3e7e03e59b39b005180156eb.png',NULL,'ScreenShot_2026-11-25_143352_176.png','image/png',68123,1,'2026-11-25 06:34:17'),(5,10,2,NULL,'https://net-cloud-tx.xhey.top/group/photo/tencent-7566116792dab9253b7e8105e4dc9e4b.jpg','tencent-7566116792dab9253b7e8105e4dc9e4b.jpg','image/external',0,1,'2026-11-25 12:44:37'),(6,11,1,'issues/1/11/d4ead2c069e099f221d27997614472b3.png',NULL,'ScreenShot_2026-11-26_215422_715.png','image/png',52858,1,'2026-11-26 13:55:08');
/*!40000 ALTER TABLE `document_issue_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_issue_status_history`
--

DROP TABLE IF EXISTS `document_issue_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_issue_status_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transition_data` text COLLATE utf8mb4_unicode_ci,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_issue_history` (`project_id`,`issue_id`,`changed_at`),
  KEY `fk_doc_issue_history_issue` (`issue_id`,`project_id`),
  KEY `fk_doc_issue_history_user` (`changed_by`),
  CONSTRAINT `fk_doc_issue_history_issue` FOREIGN KEY (`issue_id`, `project_id`) REFERENCES `document_issues` (`issue_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_issue_status_history`
--

LOCK TABLES `document_issue_status_history` WRITE;
/*!40000 ALTER TABLE `document_issue_status_history` DISABLE KEYS */;
INSERT INTO `document_issue_status_history` VALUES (1,1,1,NULL,'open','创建问题',NULL,'{\"description\":\"1. 电梯前室电动玻璃门单扇宽度(1.6m)超过电梯及楼梯间的运输限制。需要调整玻璃门宽度。\\r\\n2. 展示厅的电动玻璃门单扇宽度（1.8m）同样问题。\\r\\n\\r\\n这个问题会阻止基础施工。需要立即解决。\"}',1,'2026-11-03 00:31:57'),(2,2,2,NULL,'open','创建问题',NULL,'{\"description\":\"楼下空调主机线移位，原空调线位于未拆除墙内，拆除后需要把主电源线移位。需要增加电缆长度40m。\"}',1,'2026-11-04 03:15:05'),(3,1,3,NULL,'open','创建问题',NULL,'{\"description\":\"弱电间的位置。\\r\\n弱电间需要明确位置，涉及新风、空调、电缆桥架的安装及后续工程。\"}',1,'2026-11-04 05:00:58'),(4,1,4,NULL,'open','创建问题',NULL,'{\"description\":\"主要内容总结\\r\\n一、核心矛盾\\r\\n项目空调已向厂家报备（区域保护），指定由当地经销商供货；我方为赶工期从上海调货，被监理\\/甲方卡住，无法安装。\\r\\n二、风险点\\r\\n空调有唯一编码，销售与安装去向厂家可查。一旦被举报，我方经销商资格可能不保，因此不敢改码或造假蒙混。\\r\\n三、各方立场\\r\\n•甲方：口头称制冷量达标即可，实际仍按报备规则执行。\\r\\n•监理：不认可上海货源，要求走当地报备经销商。\\r\\n•我方：工期被甲方催，又因货源问题受阻，两头为难。\\r\\n四、处理结果\\r\\n1.上海调来的这批货明天退回。\\r\\n2.改从当地报备经销商采购，谈判安排在周一。\\r\\n3.工期将受影响，需向甲方说明。\"}',1,'2026-11-13 13:54:10'),(5,1,5,NULL,'open','创建问题',NULL,'{\"description\":\"防火门因为报审问题，拆除原来已施工的，需要重新扩大门洞。\"}',1,'2026-11-13 13:55:27'),(6,1,5,'open','in_review','图审修改',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-13 13:59:54'),(7,2,6,NULL,'open','创建问题',NULL,'{\"description\":\"平面图上没有靠幕墙的封堵，剖面图含有。需要在平面图上添加。\"}',1,'2026-11-15 09:44:40'),(8,1,7,NULL,'open','创建问题',NULL,'{\"description\":\"问题澄清：\\r\\n旋转门设计为手动旋转，清单中含有电机。\"}',1,'2026-11-15 11:53:03'),(9,1,7,'open','in_review','设计为手动',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-17 15:07:34'),(10,2,8,NULL,'open','创建问题',NULL,'{\"description\":\"三层弧形顶面艺术灯照明没有回路。\"}',1,'2026-11-21 13:45:53'),(11,1,9,NULL,'open','创建问题',NULL,'{\"description\":\"150平方电缆，总共150m。难以实施，建议使用原电井中母线槽。希望业主能够与物业协调。\"}',1,'2026-11-24 12:52:38'),(12,1,4,'open','in_review','改用本地代理商货源',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 13:03:20'),(13,1,5,'in_review','resolved','关联 RFI P1-RFI-001 已关闭，该问题名下 RFI 已全部收口，系统自动转为待关闭，等待复核。',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 13:48:13'),(14,1,5,'resolved','closed','快捷操作：关闭问题。','同意','{\"resolution_result\":\"已经处理\",\"verification_status\":\"passed\",\"verification_note\":null,\"evidence_reference\":\"同意\"}',1,'2026-11-24 14:09:08'),(15,1,4,'in_review','resolved','快捷操作：转待关闭。',NULL,'{\"resolution_result\":\"空调已经到工地，准备安装\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:17:27'),(16,1,4,'resolved','closed','快捷操作：关闭问题。','同意','{\"resolution_result\":\"空调已经到工地，准备安装\",\"verification_status\":\"passed\",\"verification_note\":\"完全符合\",\"evidence_reference\":\"同意\"}',1,'2026-11-24 14:17:53'),(17,1,7,'in_review','resolved','快捷操作：转待关闭。',NULL,'{\"resolution_result\":\"设计同意，因为原设计不是电动旋转门，所以按原设计。\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:18:34'),(18,1,3,'open','in_review','快捷操作：开始处理。',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:18:45'),(19,1,9,'open','in_review','快捷操作：开始处理。',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:18:47'),(20,1,3,'in_review','resolved','快捷操作：转待关闭。',NULL,'{\"resolution_result\":\"弱电间位置按原位置不变，增加空调。\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:19:10'),(21,1,1,'open','in_review','快捷操作：开始处理。',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:20:01'),(22,1,1,'in_review','resolved','快捷操作：转待关闭。',NULL,'{\"resolution_result\":\"电梯厅的门大小不变，结尾的玻璃门改小到1300。增加两块小的固定玻璃\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-24 14:21:27'),(23,1,1,'resolved','closed','快捷操作：关闭问题。','已经下发设计变更联系单','{\"resolution_result\":\"电梯厅的门大小不变，结尾的玻璃门改小到1300。增加两块小的固定玻璃\",\"verification_status\":\"passed\",\"verification_note\":null,\"evidence_reference\":\"已经下发设计变更联系单\"}',1,'2026-11-24 14:22:08'),(24,2,10,NULL,'open','创建问题',NULL,'{\"description\":\"二层C-B与C-1轴，包柱子上面因为非常多管道，又重新包管道。\"}',1,'2026-11-25 06:25:46'),(25,2,10,'open','in_review','快捷操作：开始处理。',NULL,'{\"resolution_result\":null,\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-25 06:27:08'),(26,2,10,'in_review','resolved','快捷操作：转待关闭。',NULL,'{\"resolution_result\":\"设计现场查看后，要求增加包管道 。\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-25 06:27:51'),(27,2,10,'resolved','in_review','木饰面也随着变更',NULL,'{\"resolution_result\":\"设计现场查看后，要求增加包管道 。\",\"verification_status\":\"pending\",\"verification_note\":null,\"evidence_reference\":null}',1,'2026-11-25 13:10:17'),(28,1,3,'resolved','closed','快捷操作：关闭问题。','同意','{\"resolution_result\":\"弱电间位置按原位置不变，增加空调。\",\"verification_status\":\"passed\",\"verification_note\":\"按原图纸施工\",\"evidence_reference\":\"同意\"}',1,'2026-11-26 13:46:55'),(29,1,11,NULL,'open','创建问题',NULL,'{\"description\":\"弱电间，朝西的幕墙边，建议做窗帘盒，并增加窗帘。\"}',1,'2026-11-26 13:50:03');
/*!40000 ALTER TABLE `document_issue_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_issues`
--

DROP TABLE IF EXISTS `document_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_issues` (
  `issue_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `issue_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('open','in_review','resolved','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_type` enum('building','floor','area','room') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '所选空间类型；NULL 表示未选择或历史文本位置',
  `location_building_id` int DEFAULT NULL COMMENT '所属楼栋，用于项目归属与删除引用检查',
  `location_floor_id` int DEFAULT NULL COMMENT '所选楼层或其父楼层',
  `location_area_id` int DEFAULT NULL COMMENT '所选施工区域',
  `location_room_id` int DEFAULT NULL COMMENT '所选房间',
  `location_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '位置补充说明，如轴线、部位；与所选空间配合使用',
  `reported_by` int NOT NULL,
  `reported_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolution_result` text COLLATE utf8mb4_unicode_ci,
  `verification_status` enum('pending','passed','not_required') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verification_note` text COLLATE utf8mb4_unicode_ci,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`issue_id`),
  UNIQUE KEY `uq_doc_issue_project` (`issue_id`,`project_id`),
  UNIQUE KEY `uq_doc_issue_number` (`project_id`,`issue_number`),
  KEY `idx_doc_issue_project_status` (`project_id`,`status`,`updated_at`),
  KEY `fk_doc_issue_reporter` (`reported_by`),
  KEY `fk_doc_issue_closer` (`closed_by`),
  KEY `idx_doc_issue_location_building` (`location_building_id`),
  KEY `idx_doc_issue_location_floor` (`location_floor_id`),
  KEY `idx_doc_issue_location_area` (`location_area_id`),
  KEY `idx_doc_issue_location_room` (`location_room_id`),
  CONSTRAINT `fk_doc_issue_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_location_area` FOREIGN KEY (`location_area_id`) REFERENCES `Construction_Areas` (`area_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_location_building` FOREIGN KEY (`location_building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_location_floor` FOREIGN KEY (`location_floor_id`) REFERENCES `Building_Floors` (`floor_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_location_room` FOREIGN KEY (`location_room_id`) REFERENCES `Rooms` (`room_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_issue_reporter` FOREIGN KEY (`reported_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_issues`
--

LOCK TABLES `document_issues` WRITE;
/*!40000 ALTER TABLE `document_issues` DISABLE KEYS */;
INSERT INTO `document_issues` VALUES (1,1,NULL,'1. 电梯前室电动玻璃门单扇宽度(1.6m)超过电梯及楼梯间的运输限制。需要调整玻璃门宽度。\r\n2. 展示厅的电动玻璃门单扇宽度（1.8m）同样问题。\r\n\r\n这个问题会阻止基础施工。需要立即解决。','critical','closed','客梯前室与内部展厅',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-03 00:31:57','电梯厅的门大小不变，结尾的玻璃门改小到1300。增加两块小的固定玻璃','passed',NULL,'已经下发设计变更联系单','2026-11-24 14:22:08',1,'2026-11-03 00:31:57','2026-11-24 14:22:08'),(2,2,NULL,'楼下空调主机线移位，原空调线位于未拆除墙内，拆除后需要把主电源线移位。需要增加电缆长度40m。','critical','open','二层C-E轴',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-04 03:15:05',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-04 03:15:05','2026-11-06 16:21:54'),(3,1,'P1-ISS-001','弱电间的位置。\r\n弱电间需要明确位置，涉及新风、空调、电缆桥架的安装及后续工程。','critical','closed','弱电间',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-04 05:00:58','弱电间位置按原位置不变，增加空调。','passed','按原图纸施工','同意','2026-11-26 13:46:55',1,'2026-11-04 05:00:58','2026-11-26 13:46:55'),(4,1,'P1-ISS-002','主要内容总结\r\n一、核心矛盾\r\n项目空调已向厂家报备（区域保护），指定由当地经销商供货；我方为赶工期从上海调货，被监理/甲方卡住，无法安装。\r\n二、风险点\r\n空调有唯一编码，销售与安装去向厂家可查。一旦被举报，我方经销商资格可能不保，因此不敢改码或造假蒙混。\r\n三、各方立场\r\n•甲方：口头称制冷量达标即可，实际仍按报备规则执行。\r\n•监理：不认可上海货源，要求走当地报备经销商。\r\n•我方：工期被甲方催，又因货源问题受阻，两头为难。\r\n四、处理结果\r\n1.上海调来的这批货明天退回。\r\n2.改从当地报备经销商采购，谈判安排在周一。\r\n3.工期将受影响，需向甲方说明。','high','closed',NULL,'area',1,NULL,1,NULL,NULL,1,'2026-11-13 13:54:10','空调已经到工地，准备安装','passed','完全符合','同意','2026-11-24 14:17:53',1,'2026-11-13 13:54:10','2026-11-24 14:17:53'),(5,1,'P1-ISS-003','防火门因为报审问题，拆除原来已施工的，需要重新扩大门洞。','medium','closed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-13 13:55:27','已经处理','passed',NULL,'同意','2026-11-24 14:09:08',1,'2026-11-13 13:55:27','2026-11-24 14:09:08'),(6,2,'P2-ISS-001','平面图上没有靠幕墙的封堵，剖面图含有。需要在平面图上添加。','medium','open',NULL,'building',4,NULL,NULL,NULL,NULL,1,'2026-11-15 09:44:40',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-15 09:44:40','2026-11-15 10:31:33'),(7,1,'P1-ISS-004','问题澄清：\r\n旋转门设计为手动旋转，清单中含有电机。','medium','resolved',NULL,'floor',1,1,NULL,NULL,'会议室与多媒体室中间',1,'2026-11-15 11:53:03','设计同意，因为原设计不是电动旋转门，所以按原设计。','pending',NULL,NULL,NULL,NULL,'2026-11-15 11:53:03','2026-11-24 14:18:34'),(8,2,'P2-ISS-002','三层弧形顶面艺术灯照明没有回路。','high','open',NULL,'room',4,3,NULL,25,NULL,1,'2026-11-21 13:45:53',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-21 13:45:53','2026-11-21 13:45:53'),(9,1,'P1-ISS-005','150平方电缆，总共150m。难以实施，建议使用原电井中母线槽。希望业主能够与物业协调。','high','in_review',NULL,'area',1,NULL,1,NULL,'强电间',1,'2026-11-24 12:52:38',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-24 12:52:38','2026-11-24 14:18:47'),(10,2,'P2-ISS-003','二层C-B与C-1轴，包柱子上面因为非常多管道，又重新包管道。','medium','in_review',NULL,'room',4,2,NULL,26,'C-B与C-1轴',1,'2026-11-25 06:25:46','设计现场查看后，要求增加包管道 。','pending',NULL,NULL,NULL,NULL,'2026-11-25 06:25:46','2026-11-25 13:10:17'),(11,1,'P1-ISS-006','弱电间，朝西的幕墙边，夏天温度会非常高。建议做窗帘盒，并增加窗帘。同时用于阻挡太阳直晒','high','open',NULL,'floor',1,1,NULL,NULL,'轴1-1与轴1-D/轴1-C',1,'2026-11-26 13:50:03',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-26 13:50:03','2026-11-26 13:51:34');
/*!40000 ALTER TABLE `document_issues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_number_sequences`
--

DROP TABLE IF EXISTS `document_number_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_number_sequences` (
  `project_id` int NOT NULL,
  `document_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_number` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`project_id`,`document_type`),
  CONSTRAINT `fk_doc_number_sequence_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_number_sequences`
--

LOCK TABLES `document_number_sequences` WRITE;
/*!40000 ALTER TABLE `document_number_sequences` DISABLE KEYS */;
INSERT INTO `document_number_sequences` VALUES (1,'ISS',6),(1,'RFI',1),(2,'CLO',18),(2,'CLO_PLAN',1),(2,'ISS',3);
/*!40000 ALTER TABLE `document_number_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_follow_up_history`
--

DROP TABLE IF EXISTS `document_rfi_follow_up_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_follow_up_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `follow_up_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_rfi_follow_up_history` (`project_id`,`follow_up_id`,`changed_at`),
  KEY `fk_doc_rfi_follow_up_history_item` (`follow_up_id`,`project_id`),
  KEY `fk_doc_rfi_follow_up_history_user` (`changed_by`),
  CONSTRAINT `fk_doc_rfi_follow_up_history_item` FOREIGN KEY (`follow_up_id`, `project_id`) REFERENCES `document_rfi_follow_ups` (`follow_up_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_follow_up_history`
--

LOCK TABLES `document_rfi_follow_up_history` WRITE;
/*!40000 ALTER TABLE `document_rfi_follow_up_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_rfi_follow_up_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_follow_ups`
--

DROP TABLE IF EXISTS `document_rfi_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_follow_ups` (
  `follow_up_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `completed_by` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follow_up_id`),
  UNIQUE KEY `uq_doc_rfi_follow_up_project` (`follow_up_id`,`project_id`),
  KEY `idx_doc_rfi_follow_ups` (`project_id`,`rfi_id`,`is_completed`),
  KEY `fk_doc_rfi_follow_up_rfi` (`rfi_id`,`project_id`),
  KEY `fk_doc_rfi_follow_up_creator` (`created_by`),
  KEY `fk_doc_rfi_follow_up_completer` (`completed_by`),
  CONSTRAINT `fk_doc_rfi_follow_up_completer` FOREIGN KEY (`completed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_follow_up_rfi` FOREIGN KEY (`rfi_id`, `project_id`) REFERENCES `document_rfis` (`rfi_id`, `project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_follow_ups`
--

LOCK TABLES `document_rfi_follow_ups` WRITE;
/*!40000 ALTER TABLE `document_rfi_follow_ups` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_rfi_follow_ups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_images`
--

DROP TABLE IF EXISTS `document_rfi_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `rfi_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '私有对象存储键；外链图片为 NULL',
  `external_url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'https 外链图片地址；有值时不再读取对象存储',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_document_rfi_image_project` (`project_id`,`rfi_id`),
  KEY `fk_document_rfi_image_rfi` (`rfi_id`,`project_id`),
  KEY `fk_document_rfi_image_user` (`uploaded_by`),
  CONSTRAINT `fk_document_rfi_image_rfi` FOREIGN KEY (`rfi_id`, `project_id`) REFERENCES `document_rfis` (`rfi_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_document_rfi_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_images`
--

LOCK TABLES `document_rfi_images` WRITE;
/*!40000 ALTER TABLE `document_rfi_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_rfi_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_number_sequences`
--

DROP TABLE IF EXISTS `document_rfi_number_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_number_sequences` (
  `calendar_year` smallint NOT NULL,
  `last_number` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`calendar_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_number_sequences`
--

LOCK TABLES `document_rfi_number_sequences` WRITE;
/*!40000 ALTER TABLE `document_rfi_number_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_rfi_number_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_related_issues`
--

DROP TABLE IF EXISTS `document_rfi_related_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_related_issues` (
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `issue_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rfi_id`,`issue_id`),
  KEY `idx_doc_rfi_related_issue` (`project_id`,`issue_id`),
  KEY `fk_doc_rfi_related_rfi` (`rfi_id`,`project_id`),
  KEY `fk_doc_rfi_related_issue` (`issue_id`,`project_id`),
  CONSTRAINT `fk_doc_rfi_related_issue` FOREIGN KEY (`issue_id`, `project_id`) REFERENCES `document_issues` (`issue_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_related_rfi` FOREIGN KEY (`rfi_id`, `project_id`) REFERENCES `document_rfis` (`rfi_id`, `project_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_related_issues`
--

LOCK TABLES `document_rfi_related_issues` WRITE;
/*!40000 ALTER TABLE `document_rfi_related_issues` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_rfi_related_issues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfi_status_history`
--

DROP TABLE IF EXISTS `document_rfi_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfi_status_history` (
  `history_id` bigint NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `rfi_id` int NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transition_data` text COLLATE utf8mb4_unicode_ci,
  `changed_by` int NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_doc_rfi_history` (`project_id`,`rfi_id`,`changed_at`),
  KEY `fk_doc_rfi_history_rfi` (`rfi_id`,`project_id`),
  KEY `fk_doc_rfi_history_user` (`changed_by`),
  CONSTRAINT `fk_doc_rfi_history_rfi` FOREIGN KEY (`rfi_id`, `project_id`) REFERENCES `document_rfis` (`rfi_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_history_user` FOREIGN KEY (`changed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfi_status_history`
--

LOCK TABLES `document_rfi_status_history` WRITE;
/*!40000 ALTER TABLE `document_rfi_status_history` DISABLE KEYS */;
INSERT INTO `document_rfi_status_history` VALUES (1,1,1,NULL,'draft','创建 RFI 草稿',NULL,'{\"primary_issue_id\":5,\"rfi_number\":\"P1-RFI-001\"}',1,'2026-11-13 14:00:49'),(2,1,1,'draft','submitted','报审未通过',NULL,'{\"response_text\":null,\"response_complete\":false,\"evidence_required\":false,\"evidence_reference\":null}',1,'2026-11-24 13:05:56'),(3,1,1,'submitted','under_review','报审','同意','{\"response_text\":null,\"response_complete\":false,\"evidence_required\":true,\"evidence_reference\":null}',1,'2026-11-24 13:47:22'),(4,1,1,'under_review','responded','报审',NULL,'{\"response_text\":\"同意更改\",\"response_complete\":true,\"evidence_required\":true,\"evidence_reference\":null}',1,'2026-11-24 13:47:56'),(5,1,1,'responded','closed','报审','同意','{\"response_text\":\"同意更改\",\"response_complete\":true,\"evidence_required\":true,\"evidence_reference\":\"同意\"}',1,'2026-11-24 13:48:13');
/*!40000 ALTER TABLE `document_rfi_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_rfis`
--

DROP TABLE IF EXISTS `document_rfis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_rfis` (
  `rfi_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `primary_issue_id` int NOT NULL,
  `rfi_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_to` int NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `due_date` datetime NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','submitted','under_review','responded','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submitted_by` int NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_text` text COLLATE utf8mb4_unicode_ci,
  `response_complete` tinyint(1) NOT NULL DEFAULT '0',
  `evidence_required` tinyint(1) NOT NULL DEFAULT '0',
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rfi_id`),
  UNIQUE KEY `uq_doc_rfi_number` (`rfi_number`),
  UNIQUE KEY `uq_doc_rfi_project` (`rfi_id`,`project_id`),
  KEY `idx_doc_rfi_project_status` (`project_id`,`status`,`updated_at`),
  KEY `idx_doc_rfi_primary_issue` (`project_id`,`primary_issue_id`),
  KEY `fk_doc_rfi_primary_issue` (`primary_issue_id`,`project_id`),
  KEY `fk_doc_rfi_assignee` (`assigned_to`),
  KEY `fk_doc_rfi_submitter` (`submitted_by`),
  KEY `fk_doc_rfi_closer` (`closed_by`),
  CONSTRAINT `fk_doc_rfi_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_primary_issue` FOREIGN KEY (`primary_issue_id`, `project_id`) REFERENCES `document_issues` (`issue_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_rfi_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_rfis`
--

LOCK TABLES `document_rfis` WRITE;
/*!40000 ALTER TABLE `document_rfis` DISABLE KEYS */;
INSERT INTO `document_rfis` VALUES (1,1,5,'P1-RFI-001',1,'medium','2026-11-13 14:00:00','修改大小。','closed',1,'2026-11-13 14:00:49','同意更改',1,1,'同意','2026-11-24 13:47:56','2026-11-24 13:48:13',1,'2026-11-13 14:00:49','2026-11-24 13:48:13');
/*!40000 ALTER TABLE `document_rfis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_acceptances`
--

DROP TABLE IF EXISTS `project_closeout_acceptances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_acceptances` (
  `acceptance_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `result` enum('passed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci,
  `evidence_reference` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_by` int NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`acceptance_id`),
  KEY `idx_closeout_acceptance_item` (`item_id`,`project_id`),
  KEY `fk_closeout_acceptance_project` (`project_id`),
  KEY `fk_closeout_acceptance_plan` (`plan_id`,`project_id`),
  KEY `fk_closeout_acceptance_user` (`accepted_by`),
  KEY `idx_closeout_acceptance_location` (`location_id`,`project_id`),
  CONSTRAINT `fk_closeout_acceptance_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_location` FOREIGN KEY (`location_id`, `project_id`) REFERENCES `project_closeout_locations` (`location_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_acceptance_user` FOREIGN KEY (`accepted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_acceptances`
--

LOCK TABLES `project_closeout_acceptances` WRITE;
/*!40000 ALTER TABLE `project_closeout_acceptances` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_closeout_acceptances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_images`
--

DROP TABLE IF EXISTS `project_closeout_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `item_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `object_key` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_closeout_image_item` (`item_id`,`project_id`),
  KEY `fk_closeout_image_project` (`project_id`),
  KEY `fk_closeout_image_user` (`uploaded_by`),
  KEY `idx_closeout_image_location` (`location_id`,`project_id`),
  CONSTRAINT `fk_closeout_image_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_image_location` FOREIGN KEY (`location_id`, `project_id`) REFERENCES `project_closeout_locations` (`location_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_image_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_images`
--

LOCK TABLES `project_closeout_images` WRITE;
/*!40000 ALTER TABLE `project_closeout_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_closeout_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_items`
--

DROP TABLE IF EXISTS `project_closeout_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_id` int NOT NULL,
  `coverage_mode` enum('whole','split') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whole',
  `coverage_root_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','ongoing','pending_accept','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responsible_supplier_id` int DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `actual_complete_date` date DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eff_project_id` int GENERATED ALWAYS AS ((case when (`status` <> _utf8mb4'cancelled') then `project_id` end)) STORED,
  `eff_material_id` int GENERATED ALWAYS AS ((case when (`status` <> _utf8mb4'cancelled') then `material_id` end)) STORED,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_closeout_item_project_no` (`project_id`,`item_no`),
  UNIQUE KEY `uq_closeout_item_project_scope` (`item_id`,`project_id`),
  UNIQUE KEY `uq_closeout_item_active` (`eff_project_id`,`eff_material_id`),
  KEY `idx_closeout_item_plan` (`plan_id`,`project_id`),
  KEY `idx_closeout_item_project_status` (`project_id`,`status`),
  KEY `idx_closeout_item_material` (`project_id`,`material_id`),
  KEY `idx_closeout_item_responsible` (`project_id`,`responsible_user_id`),
  KEY `fk_closeout_item_supplier` (`responsible_supplier_id`),
  KEY `fk_closeout_item_responsible` (`responsible_user_id`),
  KEY `fk_closeout_item_creator` (`created_by`),
  KEY `fk_closeout_item_canceller` (`cancelled_by`),
  CONSTRAINT `fk_closeout_item_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_material` FOREIGN KEY (`project_id`, `material_id`) REFERENCES `Materials` (`project_id`, `material_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_item_supplier` FOREIGN KEY (`responsible_supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_item_dates` CHECK (((`planned_start_date` is null) or (`planned_end_date` is null) or (`planned_start_date` <= `planned_end_date`)))
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_items`
--

LOCK TABLES `project_closeout_items` WRITE;
/*!40000 ALTER TABLE `project_closeout_items` DISABLE KEYS */;
INSERT INTO `project_closeout_items` (`item_id`, `project_id`, `plan_id`, `item_no`, `material_id`, `coverage_mode`, `coverage_root_id`, `title`, `description`, `status`, `responsible_supplier_id`, `responsible_user_id`, `planned_start_date`, `planned_end_date`, `submitted_at`, `actual_complete_date`, `risk_level`, `cancel_reason`, `cancelled_by`, `cancelled_at`, `created_by`, `created_at`, `updated_at`) VALUES (1,2,1,'P2-CLO-001',33,'whole',33,'',NULL,'ongoing',NULL,NULL,NULL,'2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 05:42:34','2026-11-23 14:25:14'),(2,2,1,'P2-CLO-002',126,'whole',126,'',NULL,'ongoing',NULL,NULL,NULL,'2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 05:42:34','2026-11-23 14:25:14'),(3,2,1,'P2-CLO-003',46,'whole',46,'',NULL,'ongoing',NULL,NULL,NULL,'2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 05:51:03','2026-11-23 14:25:14'),(4,2,1,'P2-CLO-004',17,'whole',17,'',NULL,'ongoing',5,NULL,'2026-11-22','2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 06:23:23','2026-11-23 14:25:14'),(5,2,1,'P2-CLO-005',34,'whole',34,'',NULL,'ongoing',5,NULL,'2026-11-22','2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 06:23:23','2026-11-23 14:25:14'),(6,2,1,'P2-CLO-006',32,'whole',32,'',NULL,'pending_accept',5,NULL,'2026-11-22','2026-11-28','2026-11-23 14:24:01',NULL,'medium',NULL,NULL,NULL,1,'2026-11-22 06:23:23','2026-11-23 14:25:14'),(7,2,1,'P2-CLO-007',132,'whole',132,'',NULL,'ongoing',NULL,NULL,'2026-11-23','2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-23 13:29:55','2026-11-23 14:25:14'),(8,2,1,'P2-CLO-008',135,'whole',135,'',NULL,'pending_accept',NULL,NULL,'2026-11-23','2026-11-28','2026-11-27 12:32:03',NULL,'medium',NULL,NULL,NULL,1,'2026-11-23 13:29:55','2026-11-27 12:32:03'),(9,2,1,'P2-CLO-009',58,'whole',58,'',NULL,'ongoing',11,NULL,'2026-11-23','2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-23 13:29:55','2026-11-27 12:31:57'),(10,2,1,'P2-CLO-010',131,'whole',131,'',NULL,'ongoing',4,1,'2026-11-23','2026-11-28',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-23 13:29:55','2026-11-23 14:33:17'),(11,2,1,'P2-CLO-011',136,'whole',136,'',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(12,2,1,'P2-CLO-012',137,'whole',137,'',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(13,2,1,'P2-CLO-013',138,'whole',138,'',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(14,2,1,'P2-CLO-014',139,'whole',139,'',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(15,2,1,'P2-CLO-015',140,'whole',140,'',NULL,'pending',NULL,NULL,'2026-11-26','2026-11-29',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:57:13'),(16,2,1,'P2-CLO-016',142,'whole',142,'',NULL,'pending',NULL,NULL,NULL,NULL,'2026-11-27 13:56:39',NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:56:39'),(17,2,1,'P2-CLO-017',145,'whole',145,'',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(18,2,1,'P2-CLO-018',147,'whole',147,'',NULL,'pending',NULL,NULL,'2026-11-27','2026-11-30',NULL,NULL,'medium',NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:57:29');
/*!40000 ALTER TABLE `project_closeout_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_locations`
--

DROP TABLE IF EXISTS `project_closeout_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_locations` (
  `location_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `estimate_detail_id` int DEFAULT NULL,
  `building_id` int NOT NULL,
  `floor_id` int DEFAULT NULL,
  `room_id` int DEFAULT NULL,
  `baseline_qty` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `baseline_at` datetime NOT NULL,
  `status` enum('pending','ongoing','pending_accept','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `submitted_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `responsible_supplier_id` int DEFAULT NULL,
  `responsible_user_id` int DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eff_space_key` varchar(80) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`status` <> _utf8mb4'cancelled') then concat(cast(`project_id` as char charset utf8mb4),_utf8mb4'-',cast(`item_id` as char charset utf8mb4),_utf8mb4'-',cast(`building_id` as char charset utf8mb4),_utf8mb4'-',coalesce(cast(`floor_id` as char charset utf8mb4),_utf8mb4'0'),_utf8mb4'-',coalesce(cast(`room_id` as char charset utf8mb4),_utf8mb4'0')) end)) STORED,
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `uq_closeout_location_scope` (`location_id`,`project_id`),
  UNIQUE KEY `uq_closeout_location_active` (`eff_space_key`),
  KEY `idx_closeout_location_item` (`item_id`,`project_id`),
  KEY `idx_closeout_location_project_status` (`project_id`,`status`),
  KEY `idx_closeout_location_building` (`building_id`,`floor_id`,`room_id`),
  KEY `idx_closeout_location_estimate` (`estimate_detail_id`),
  KEY `idx_closeout_location_plan_date` (`project_id`,`status`,`planned_end_date`),
  KEY `idx_closeout_location_responsible` (`project_id`,`responsible_user_id`),
  KEY `fk_closeout_location_plan` (`plan_id`,`project_id`),
  KEY `fk_closeout_location_floor` (`floor_id`,`building_id`),
  KEY `fk_closeout_location_room` (`room_id`,`building_id`,`floor_id`),
  KEY `fk_closeout_location_supplier` (`responsible_supplier_id`),
  KEY `fk_closeout_location_responsible` (`responsible_user_id`),
  KEY `fk_closeout_location_accepter` (`accepted_by`),
  KEY `fk_closeout_location_creator` (`created_by`),
  KEY `fk_closeout_location_canceller` (`cancelled_by`),
  CONSTRAINT `fk_closeout_location_accepter` FOREIGN KEY (`accepted_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_building` FOREIGN KEY (`building_id`) REFERENCES `Buildings` (`building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_floor` FOREIGN KEY (`floor_id`, `building_id`) REFERENCES `Building_Floors` (`floor_id`, `building_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_room` FOREIGN KEY (`room_id`, `building_id`, `floor_id`) REFERENCES `Rooms` (`room_id`, `building_id`, `floor_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_location_supplier` FOREIGN KEY (`responsible_supplier_id`) REFERENCES `Suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_location_dates` CHECK (((`planned_start_date` is null) or (`planned_end_date` is null) or (`planned_start_date` <= `planned_end_date`)))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_locations`
--

LOCK TABLES `project_closeout_locations` WRITE;
/*!40000 ALTER TABLE `project_closeout_locations` DISABLE KEYS */;
INSERT INTO `project_closeout_locations` (`location_id`, `project_id`, `plan_id`, `item_id`, `estimate_detail_id`, `building_id`, `floor_id`, `room_id`, `baseline_qty`, `baseline_at`, `status`, `submitted_at`, `accepted_at`, `accepted_by`, `planned_start_date`, `planned_end_date`, `responsible_supplier_id`, `responsible_user_id`, `cancel_reason`, `cancelled_by`, `cancelled_at`, `created_by`, `created_at`, `updated_at`) VALUES (1,2,1,11,56,4,2,21,1.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(2,2,1,12,57,4,2,26,1.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(3,2,1,13,58,4,3,27,1.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(4,2,1,14,59,4,3,24,1.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(5,2,1,15,60,4,3,12,1.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(6,2,1,16,33,4,2,21,12.8000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(7,2,1,16,NULL,4,3,10,14.1000,'2026-11-27 13:49:30','pending_accept','2026-11-27 13:56:26',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:56:26'),(8,2,1,16,35,4,3,24,5.3000,'2026-11-27 13:49:30','pending_accept','2026-11-27 13:56:39',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:56:39'),(9,2,1,17,63,4,2,22,25.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(10,2,1,17,64,4,3,23,10.0000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(11,2,1,18,65,4,3,12,12.8000,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(12,2,1,18,NULL,4,2,17,19.6800,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(13,2,1,18,68,4,2,15,14.7300,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30'),(14,2,1,18,69,4,2,14,11.5400,'2026-11-27 13:49:30','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-11-27 13:49:30','2026-11-27 13:49:30');
/*!40000 ALTER TABLE `project_closeout_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_plans`
--

DROP TABLE IF EXISTS `project_closeout_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','active','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uq_closeout_plan_project_no` (`project_id`,`plan_no`),
  UNIQUE KEY `uq_closeout_plan_project_scope` (`plan_id`,`project_id`),
  KEY `idx_closeout_plan_project_status` (`project_id`,`status`),
  KEY `fk_closeout_plan_creator` (`created_by`),
  KEY `fk_closeout_plan_closer` (`closed_by`),
  KEY `fk_closeout_plan_reopener` (`reopened_by`),
  CONSTRAINT `fk_closeout_plan_closer` FOREIGN KEY (`closed_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_creator` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_plan_reopener` FOREIGN KEY (`reopened_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_closeout_plan_dates` CHECK (((`planned_start_date` is null) or (`planned_end_date` is null) or (`planned_start_date` <= `planned_end_date`)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_plans`
--

LOCK TABLES `project_closeout_plans` WRITE;
/*!40000 ALTER TABLE `project_closeout_plans` DISABLE KEYS */;
INSERT INTO `project_closeout_plans` VALUES (1,2,'P2-CLO-PLAN-001','Coding办公室赶工消项计划','所有装饰饰装修工程','active','2026-11-22','2026-11-30',NULL,NULL,NULL,NULL,1,'2026-11-22 05:34:37','2026-11-22 05:34:37');
/*!40000 ALTER TABLE `project_closeout_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_closeout_progress_logs`
--

DROP TABLE IF EXISTS `project_closeout_progress_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_closeout_progress_logs` (
  `progress_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `item_id` int NOT NULL,
  `location_id` int DEFAULT NULL,
  `progress_type` enum('note','submit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'note',
  `progress_note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` int NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`progress_id`),
  KEY `idx_closeout_progress_item` (`item_id`,`project_id`),
  KEY `fk_closeout_progress_project` (`project_id`),
  KEY `fk_closeout_progress_plan` (`plan_id`,`project_id`),
  KEY `fk_closeout_progress_user` (`recorded_by`),
  KEY `idx_closeout_progress_location` (`location_id`,`project_id`),
  CONSTRAINT `fk_closeout_progress_item` FOREIGN KEY (`item_id`, `project_id`) REFERENCES `project_closeout_items` (`item_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_location` FOREIGN KEY (`location_id`, `project_id`) REFERENCES `project_closeout_locations` (`location_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_plan` FOREIGN KEY (`plan_id`, `project_id`) REFERENCES `project_closeout_plans` (`plan_id`, `project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_closeout_progress_user` FOREIGN KEY (`recorded_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_closeout_progress_logs`
--

LOCK TABLES `project_closeout_progress_logs` WRITE;
/*!40000 ALTER TABLE `project_closeout_progress_logs` DISABLE KEYS */;
INSERT INTO `project_closeout_progress_logs` VALUES (1,2,1,6,NULL,'note','在定制中',1,'2026-11-23 13:38:27'),(2,2,1,8,NULL,'note','安装还差3根轨道，需要补发',1,'2026-11-23 13:52:01'),(3,2,1,6,NULL,'submit','提交验收',1,'2026-11-23 14:24:01'),(4,2,1,8,NULL,'submit','提交验收',1,'2026-11-27 12:32:03'),(5,2,1,16,7,'submit','空间完成，提交验收',1,'2026-11-27 13:56:26'),(6,2,1,16,8,'submit','空间完成，提交验收',1,'2026-11-27 13:56:39');
/*!40000 ALTER TABLE `project_closeout_progress_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_cost_prices`
--

DROP TABLE IF EXISTS `project_cost_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_cost_prices` (
  `price_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `aux_id` int DEFAULT NULL,
  `catalog_id` int DEFAULT NULL,
  `unit_price` decimal(18,4) NOT NULL,
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `pricing_unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `status` enum('active','paused','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`price_id`),
  UNIQUE KEY `uq_project_cost_price_project` (`project_id`,`price_id`),
  KEY `idx_cost_price_project_aux` (`project_id`,`aux_id`),
  KEY `idx_cost_price_project_catalog` (`project_id`,`catalog_id`),
  KEY `fk_cost_price_catalog` (`catalog_id`),
  KEY `fk_cost_price_created_by` (`created_by`),
  CONSTRAINT `fk_cost_price_aux` FOREIGN KEY (`project_id`, `aux_id`) REFERENCES `Auxiliary_Materials` (`project_id`, `aux_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `resource_catalog` (`catalog_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cost_price_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_cost_price_target_one` CHECK ((((`aux_id` is not null) and (`catalog_id` is null)) or ((`aux_id` is null) and (`catalog_id` is not null)))),
  CONSTRAINT `chk_cost_price_unit_positive` CHECK ((`unit_price` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_cost_prices`
--

LOCK TABLES `project_cost_prices` WRITE;
/*!40000 ALTER TABLE `project_cost_prices` DISABLE KEYS */;
INSERT INTO `project_cost_prices` VALUES (1,2,NULL,1,300.0000,'CNY','工日','2026-11-22','2026-12-31','active',1,'2026-11-22 07:40:27','2026-11-22 07:40:27'),(2,2,NULL,2,300.0000,'CNY','工日','2026-11-22','2026-12-31','active',1,'2026-11-22 07:40:41','2026-11-22 07:40:41'),(3,2,NULL,3,350.0000,'CNY','工日','2026-11-25','2026-12-06','active',1,'2026-11-25 06:56:39','2026-11-25 06:56:39');
/*!40000 ALTER TABLE `project_cost_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_foundation_archive_records`
--

DROP TABLE IF EXISTS `project_foundation_archive_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_foundation_archive_records` (
  `archive_id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `project_id` int NOT NULL,
  `archived_by` int NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archive_id`),
  UNIQUE KEY `uq_project_foundation_archive_entity` (`entity_type`,`entity_id`),
  KEY `idx_project_foundation_archive_project` (`project_id`,`entity_type`),
  KEY `fk_project_foundation_archive_user` (`archived_by`),
  CONSTRAINT `fk_project_foundation_archive_project` FOREIGN KEY (`project_id`) REFERENCES `Projects` (`project_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_project_foundation_archive_user` FOREIGN KEY (`archived_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_foundation_archive_records`
--

LOCK TABLES `project_foundation_archive_records` WRITE;
/*!40000 ALTER TABLE `project_foundation_archive_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_foundation_archive_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_catalog`
--

DROP TABLE IF EXISTS `resource_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_catalog` (
  `catalog_id` int NOT NULL AUTO_INCREMENT,
  `catalog_type` enum('processing','mechanical','labor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`catalog_id`),
  UNIQUE KEY `uq_catalog_type_name` (`catalog_type`,`name`),
  KEY `fk_catalog_created_by` (`created_by`),
  CONSTRAINT `fk_catalog_created_by` FOREIGN KEY (`created_by`) REFERENCES `Users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_catalog`
--

LOCK TABLES `resource_catalog` WRITE;
/*!40000 ALTER TABLE `resource_catalog` DISABLE KEYS */;
INSERT INTO `resource_catalog` VALUES (1,'labor','安装人工','元','',NULL,'2026-11-21 13:47:43','2026-11-21 13:47:43'),(2,'labor','线灯安装人工','天','',NULL,'2026-11-22 05:49:33','2026-11-22 05:49:33'),(3,'labor','瓷砖贴砖','天','',NULL,'2026-11-25 06:56:10','2026-11-25 06:56:10');
/*!40000 ALTER TABLE `resource_catalog` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-27 22:00:14

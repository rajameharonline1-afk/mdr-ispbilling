-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: isp_billing
-- ------------------------------------------------------
-- Server version	8.0.44-0ubuntu0.24.04.2

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
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('cash','bank','mfs','other','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT '0.00',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `type` (`type`),
  KEY `idx_accounts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned DEFAULT NULL,
  `olt_id` int unsigned DEFAULT NULL,
  `type` varchar(64) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `ack` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_type_time` (`type`,`created_at`),
  KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alerts`
--

LOCK TABLES `alerts` WRITE;
/*!40000 ALTER TABLE `alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entity` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint DEFAULT NULL,
  `old_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity`),
  KEY `idx_audit_entity_id` (`entity_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_logins`
--

DROP TABLE IF EXISTS `auth_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_logins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `event` enum('login','logout','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'login',
  `success` tinyint(1) NOT NULL DEFAULT '1',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `session_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`created_at`),
  KEY `idx_event_time` (`event`,`created_at`),
  KEY `idx_ip_time` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_logins`
--

LOCK TABLES `auth_logins` WRITE;
/*!40000 ALTER TABLE `auth_logins` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_logins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bill_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('paid','due') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'due',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills`
--

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bkash_inbox`
--

DROP TABLE IF EXISTS `bkash_inbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bkash_inbox` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `raw_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sender_msisdn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `trxid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sms_time` datetime DEFAULT NULL,
  `direction` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'in',
  `status` enum('new','parsed','matched','applied','ignored','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'new',
  `error_msg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inbox_trxid` (`trxid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bkash_inbox`
--

LOCK TABLES `bkash_inbox` WRITE;
/*!40000 ALTER TABLE `bkash_inbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `bkash_inbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bkash_rtn_events`
--

DROP TABLE IF EXISTS `bkash_rtn_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bkash_rtn_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_hash` char(64) NOT NULL,
  `event_type` varchar(64) DEFAULT NULL,
  `event_id` varchar(128) DEFAULT NULL,
  `payment_id` varchar(64) DEFAULT NULL,
  `trx_id` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `payer_msisdn` varchar(32) DEFAULT NULL,
  `merchant_invoice_number` varchar(64) DEFAULT NULL,
  `raw_body` mediumtext,
  `headers_json` mediumtext,
  `received_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remote_ip` varchar(64) DEFAULT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `process_attempts` int NOT NULL DEFAULT '0',
  `processed_at` datetime DEFAULT NULL,
  `applied_client_id` int DEFAULT NULL,
  `applied_amount` decimal(12,2) DEFAULT NULL,
  `last_error` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_hash` (`event_hash`),
  KEY `idx_processed_received` (`processed`,`received_at`),
  KEY `idx_trx` (`trx_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_ref` (`merchant_invoice_number`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bkash_rtn_events`
--

LOCK TABLES `bkash_rtn_events` WRITE;
/*!40000 ALTER TABLE `bkash_rtn_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `bkash_rtn_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bkash_webhook_pending`
--

DROP TABLE IF EXISTS `bkash_webhook_pending`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bkash_webhook_pending` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trx_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `msisdn` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `raw_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','matched','applied','ignored') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bkash_webhook_pending`
--

LOCK TABLES `bkash_webhook_pending` WRITE;
/*!40000 ALTER TABLE `bkash_webhook_pending` DISABLE KEYS */;
/*!40000 ALTER TABLE `bkash_webhook_pending` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_ledger`
--

DROP TABLE IF EXISTS `client_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_ledger` (
  `client_id` int NOT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`client_id`),
  CONSTRAINT `fk_ledger_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_ledger`
--

LOCK TABLES `client_ledger` WRITE;
/*!40000 ALTER TABLE `client_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_location_options`
--

DROP TABLE IF EXISTS `client_location_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_location_options` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('area','sub_zone','box') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_area` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_sub_zone` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_location_options`
--

LOCK TABLES `client_location_options` WRITE;
/*!40000 ALTER TABLE `client_location_options` DISABLE KEYS */;
INSERT INTO `client_location_options` VALUES (135,'area','Rajamehar',NULL,NULL,NULL,'2026-01-02 19:05:40'),(136,'sub_zone','Uttor',NULL,'Rajamehar',NULL,'2026-01-02 19:05:45'),(137,'box','Boktar Bari',NULL,'Rajamehar','Uttor','2026-01-02 19:06:06'),(153,'area','Govindupor',NULL,NULL,NULL,'2026-01-03 04:51:53'),(154,'sub_zone','Middle',NULL,'Govindupor',NULL,'2026-01-03 04:52:46'),(155,'box','Mollha Bari',NULL,'Govindupor','Middle','2026-01-03 04:53:02');
/*!40000 ALTER TABLE `client_location_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_traffic_log`
--

DROP TABLE IF EXISTS `client_traffic_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_traffic_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `log_time` datetime NOT NULL,
  `rx_speed` int NOT NULL,
  `tx_speed` int NOT NULL,
  `total_download_gb` decimal(10,2) NOT NULL,
  `total_upload_gb` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_traffic_log`
--

LOCK TABLES `client_traffic_log` WRITE;
/*!40000 ALTER TABLE `client_traffic_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_traffic_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_id` int DEFAULT NULL,
  `olt_id` int unsigned DEFAULT NULL,
  `olt_vendor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `olt_port` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `olt_onu` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_linked_at` datetime DEFAULT NULL,
  `client_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pppoe_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ap_mac` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pppoe_pass` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_payment_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_bill` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ledger_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `expiry_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `router_mac` varchar(17) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caller_mac` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_mac` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rx_power` decimal(6,2) DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  `last_sync_time` datetime DEFAULT NULL,
  `connection_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_zone` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_id` int NOT NULL,
  `join_date` date NOT NULL,
  `expire_date` date DEFAULT NULL,
  `status` enum('active','inactive','expired','pending','left') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `is_whitelist` tinyint(1) NOT NULL DEFAULT '0',
  `auto_control_optout` tinyint(1) NOT NULL DEFAULT '0',
  `is_vip` tinyint(1) NOT NULL DEFAULT '0',
  `is_left` tinyint(1) NOT NULL DEFAULT '0',
  `left_at` datetime DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_online` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `next_due_date` date DEFAULT NULL,
  `photo_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_logout_at` datetime DEFAULT NULL,
  `suspend_by_billing` tinyint(1) NOT NULL DEFAULT '0',
  `suspended_at` datetime DEFAULT NULL,
  `bkash_msisdn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `reseller_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pppoe_id` (`pppoe_id`),
  UNIQUE KEY `uq_clients_pppoe_id` (`pppoe_id`),
  UNIQUE KEY `uq_pppoe_id` (`pppoe_id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `uq_clients_client_code` (`client_code`),
  UNIQUE KEY `uq_router_mac` (`router_mac`),
  KEY `client_code` (`client_code`) USING BTREE,
  KEY `idx_clients_olt_id` (`olt_id`),
  KEY `idx_clients_is_left` (`is_left`),
  KEY `idx_clients_left_at` (`left_at`),
  KEY `idx_clients_router_mac` (`router_mac`),
  KEY `idx_status` (`status`),
  KEY `idx_is_left` (`is_left`),
  KEY `idx_router` (`router_id`),
  KEY `idx_package` (`package_id`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_area` (`area`),
  KEY `idx_clients_ap_mac` (`ap_mac`),
  KEY `idx_clients_reseller` (`reseller_id`),
  CONSTRAINT `fk_clients_olts_id` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2632 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (2631,NULL,11,12,'vsol','EPON 0/04','13','2026-01-14 15:46:02','5555','R3545555','R3545555',NULL,'ISP6676',NULL,NULL,NULL,'unpaid',NULL,'500',0.00,NULL,NULL,'B4:64:15:EE:9B:73','b4:64:15:ee:9b:73',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,43,'2026-01-14',NULL,'active',0,0,0,0,NULL,NULL,'2026-01-14 09:23:31','2026-01-14 09:46:02',0,0,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cron_runs`
--

DROP TABLE IF EXISTS `cron_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_runs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `output` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `error` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `triggered_by` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_key` (`job_key`),
  KEY `status` (`status`),
  KEY `started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_runs`
--

LOCK TABLES `cron_runs` WRITE;
/*!40000 ALTER TABLE `cron_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `cron_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_payments`
--

DROP TABLE IF EXISTS `employee_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `employee_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `wallet_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_payments`
--

LOCK TABLES `employee_payments` WRITE;
/*!40000 ALTER TABLE `employee_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `emp_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `designation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `join_date` date NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`emp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=202519 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees_roles`
--

DROP TABLE IF EXISTS `employees_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees_roles` (
  `emp_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role_id` int NOT NULL,
  PRIMARY KEY (`emp_id`,`role_id`),
  KEY `idx_emprole_role` (`role_id`),
  CONSTRAINT `fk_er_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees_roles`
--

LOCK TABLES `employees_roles` WRITE;
/*!40000 ALTER TABLE `employees_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_accounts`
--

DROP TABLE IF EXISTS `expense_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_accounts`
--

LOCK TABLES `expense_accounts` WRITE;
/*!40000 ALTER TABLE `expense_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `parent_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (7,'Upstream Bill',NULL,1,'2025-12-24 23:58:52'),(8,'Product Purchase',NULL,1,'2025-12-24 23:58:52'),(9,'Employee Salary',NULL,1,'2025-12-24 23:58:52'),(10,'Office Rent',NULL,1,'2025-12-24 23:58:52'),(11,'Utility',NULL,1,'2025-12-24 23:58:52'),(12,'Other',NULL,1,'2025-12-24 23:58:52');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `paid_at` datetime NOT NULL,
  `account_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_deleted` (`is_deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income`
--

DROP TABLE IF EXISTS `income`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income`
--

LOCK TABLES `income` WRITE;
/*!40000 ALTER TABLE `income` DISABLE KEYS */;
/*!40000 ALTER TABLE `income` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `months` tinyint unsigned NOT NULL DEFAULT '1',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_month` date NOT NULL,
  `is_auto_generated` tinyint(1) DEFAULT '0',
  `package_id` int unsigned DEFAULT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) DEFAULT '0.00',
  `vat_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payable` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('unpaid','paid','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `is_void` tinyint(1) NOT NULL DEFAULT '0',
  `paid_at` datetime DEFAULT NULL,
  `method` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reseller_id` int DEFAULT NULL,
  `reseller_price` decimal(10,2) DEFAULT NULL,
  `reseller_commission` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  UNIQUE KEY `uniq_client_period` (`client_id`,`period_start`,`period_end`),
  UNIQUE KEY `invoice_unique_period` (`client_id`,`period_start`,`period_end`),
  KEY `created_by` (`created_by`),
  KEY `package_id` (`package_id`),
  KEY `idx_invoices_client` (`client_id`),
  KEY `idx_invoices_billing_month` (`billing_month`),
  KEY `idx_inv_client` (`client_id`),
  KEY `idx_inv_month` (`billing_month`),
  KEY `idx_inv_status` (`status`),
  KEY `idx_inv_date` (`invoice_date`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mac_logs`
--

DROP TABLE IF EXISTS `mac_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mac_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `interface_name` varchar(64) NOT NULL,
  `mac_old` varchar(32) DEFAULT NULL,
  `mac_new` varchar(32) NOT NULL,
  `vlan` int unsigned DEFAULT NULL,
  `target_table` enum('clients','onu_inventory') NOT NULL,
  `target_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_iface_time` (`interface_name`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mac_logs`
--

LOCK TABLES `mac_logs` WRITE;
/*!40000 ALTER TABLE `mac_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `mac_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mac_vendors`
--

DROP TABLE IF EXISTS `mac_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mac_vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mac_prefix` char(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac_prefix` (`mac_prefix`),
  KEY `idx_mac_vendors_prefix` (`mac_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mac_vendors`
--

LOCK TABLES `mac_vendors` WRITE;
/*!40000 ALTER TABLE `mac_vendors` DISABLE KEYS */;
/*!40000 ALTER TABLE `mac_vendors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_overrides`
--

DROP TABLE IF EXISTS `menu_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `menu_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_menu` (`user_id`,`menu_key`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_overrides`
--

LOCK TABLES `menu_overrides` WRITE;
/*!40000 ALTER TABLE `menu_overrides` DISABLE KEYS */;
/*!40000 ALTER TABLE `menu_overrides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `network_areas`
--

DROP TABLE IF EXISTS `network_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `network_areas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#0d6efd',
  `geojson` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `network_areas`
--

LOCK TABLES `network_areas` WRITE;
/*!40000 ALTER TABLE `network_areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `network_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_key` varchar(80) NOT NULL,
  `channel` enum('sms','email') NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `body` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl` (`template_key`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_templates`
--

LOCK TABLES `notification_templates` WRITE;
/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olt_logs`
--

DROP TABLE IF EXISTS `olt_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `action` varchar(64) NOT NULL,
  `olt_id` bigint DEFAULT NULL,
  `user_id` bigint DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olt_logs`
--

LOCK TABLES `olt_logs` WRITE;
/*!40000 ALTER TABLE `olt_logs` DISABLE KEYS */;
INSERT INTO `olt_logs` VALUES (1,'update',11,1,'{\"ts\": \"2026-01-10T15:28:20+06:00\", \"meta\": {\"id\": 11, \"host\": \"192.168.120.2\", \"name\": \"OLT-Cholash1\", \"active\": 1, \"vendor\": \"vsol\", \"prev_host\": \"192.168.120.2\"}, \"action\": \"update\", \"user_id\": 1}','2026-01-10 15:28:20'),(2,'update',11,1,'{\"ts\": \"2026-01-10T15:45:29+06:00\", \"meta\": {\"id\": 11, \"host\": \"192.168.120.2\", \"name\": \"OLT-Cholash\", \"active\": 1, \"vendor\": \"vsol\", \"prev_host\": \"192.168.120.2\"}, \"action\": \"update\", \"user_id\": 1}','2026-01-10 15:45:29');
/*!40000 ALTER TABLE `olt_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olt_mac_cache`
--

DROP TABLE IF EXISTS `olt_mac_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olt_mac_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` int unsigned NOT NULL,
  `vendor` varchar(64) DEFAULT NULL,
  `client_id` int unsigned DEFAULT NULL,
  `mac` varchar(32) NOT NULL,
  `clients` varchar(255) DEFAULT NULL,
  `client_mac` varchar(32) DEFAULT NULL,
  `client_mac_vlan` int unsigned DEFAULT NULL,
  `client_mac_learned_at` datetime DEFAULT NULL,
  `vlan` smallint unsigned DEFAULT NULL,
  `port` varchar(64) DEFAULT NULL,
  `onu` varchar(32) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `distance_m` decimal(10,2) DEFAULT NULL,
  `rx_power_dbm` decimal(10,2) DEFAULT NULL,
  `last_dereg_reason` varchar(255) DEFAULT NULL,
  `last_dereg_time` datetime DEFAULT NULL,
  `status` enum('online','offline','unknown') DEFAULT NULL,
  `learned_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_olt_mac` (`olt_id`,`mac`),
  KEY `idx_mac` (`mac`),
  KEY `idx_port` (`port`),
  KEY `idx_client_mac` (`client_mac`),
  KEY `idx_client_mac_vlan` (`client_mac`,`client_mac_vlan`)
) ENGINE=InnoDB AUTO_INCREMENT=172638 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olt_mac_cache`
--

LOCK TABLES `olt_mac_cache` WRITE;
/*!40000 ALTER TABLE `olt_mac_cache` DISABLE KEYS */;
INSERT INTO `olt_mac_cache` VALUES (18760,10,'vsol',NULL,'c4:70:0b:a0:7f:24',NULL,NULL,NULL,NULL,0,'PON 0/00','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:17'),(18761,10,'vsol',2386,'30:16:9d:5f:85:0e','2386','30:16:9d:5f:85:0e',NULL,NULL,0,'EPON 0/01','ONU 30',':',2910.00,-22.92,'Wire Down','2026-01-04 15:37:25','online','2026-01-14 15:40:16'),(18762,10,'vsol',2383,'b0:a7:b9:e4:d3:4b','2383','b0:a7:b9:e4:d3:4b',NULL,NULL,0,'EPON 0/01','ONU 3',':',556.00,-12.94,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:16'),(18763,10,'vsol',2429,'dc:8e:8d:5d:1f:77','2429','dc:8e:8d:5d:1f:77',NULL,NULL,0,'EPON 0/01','ONU 24',':',1998.00,-15.20,'Power Off','2026-01-14 13:33:07','online','2026-01-14 15:40:16'),(18764,10,'vsol',1911,'d8:32:14:e6:47:f8','1911','d8:32:14:e6:47:f8',NULL,NULL,0,'EPON 0/01','ONU 15',':',2608.00,-11.06,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:16'),(18765,10,'vsol',2528,'d8:32:14:9e:93:e8','2528','d8:32:14:9e:93:e8',NULL,NULL,0,'EPON 0/01','ONU 5',':',856.00,-15.14,'Power Off','2026-01-14 13:33:10','online','2026-01-14 15:40:16'),(18766,10,'vsol',2385,'bc:22:28:c2:71:95','2385','bc:22:28:c2:71:95',NULL,NULL,0,'EPON 0/01','ONU 2',':',946.00,-12.10,'Power Off','2026-01-14 13:33:13','online','2026-01-14 15:40:16'),(18767,10,'vsol',2326,'b0:19:21:49:63:7f','2326','b0:19:21:49:63:7f',NULL,NULL,0,'EPON 0/01','ONU 6',':',861.00,-8.66,'Power Off','2026-01-14 13:33:08','online','2026-01-14 15:40:16'),(18768,10,'vsol',2210,'40:ae:30:5d:b9:f1','2210','40:ae:30:5d:b9:f1',NULL,NULL,0,'EPON 0/01','ONU 17',':',542.00,-12.94,'Wire Down','2026-01-14 13:33:08','online','2026-01-14 15:40:16'),(18769,10,'vsol',2431,'50:0f:f5:1b:a2:50','2431','50:0f:f5:1b:a2:50',NULL,NULL,0,'EPON 0/01','ONU 20',':',1611.00,-16.68,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:16'),(18770,10,'vsol',2468,'cc:2d:21:3a:a1:70','2468','cc:2d:21:3a:a1:70',NULL,NULL,0,'EPON 0/01','ONU 28',':',154.00,-18.89,'Power Off','2026-01-14 13:33:12','online','2026-01-14 15:40:16'),(18771,10,'vsol',2095,'78:8c:b5:01:39:5b','2095','78:8c:b5:01:39:5b',NULL,NULL,0,'EPON 0/01','ONU 13',':',546.00,-12.56,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:16'),(18772,10,'vsol',2001,'cc:2d:21:e9:b9:10','2001','cc:2d:21:e9:b9:10',NULL,NULL,0,'EPON 0/01','ONU 1','5060-Sohag',461.00,-17.03,'Wire Down','2026-01-01 10:17:36','online','2026-01-14 15:40:16'),(18773,10,'vsol',2424,'50:0f:f5:b6:da:a8','2424','50:0f:f5:b6:da:a8',NULL,NULL,0,'EPON 0/01','ONU 23',':',2080.00,-19.96,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:16'),(18774,10,'vsol',2523,'d8:32:14:a3:88:bf','2523','d8:32:14:a3:88:bf',NULL,NULL,0,'EPON 0/01','ONU 12',':',665.00,-20.41,'Power Off','2026-01-14 13:33:13','online','2026-01-14 15:40:16'),(18775,10,'vsol',2464,'8c:90:2d:ad:4b:23','2464','8c:90:2d:ad:4b:23',NULL,NULL,0,'EPON 0/01','ONU 18',':',847.00,-15.48,'Power Off','2026-01-14 15:19:59','online','2026-01-14 15:40:16'),(18776,10,'vsol',2423,'50:0f:f5:bc:69:c8','2423','50:0f:f5:bc:69:c8',101,'2026-01-14 08:11:41',0,'EPON 0/01','ONU 22',':',2041.00,-22.52,'Power Off','2026-01-13 16:46:03','online','2026-01-14 08:11:42'),(18777,10,'vsol',2464,'4c:d7:c8:df:4b:ff','2464','4c:d7:c8:df:4b:ff',NULL,NULL,0,'EPON 0/01','ONU 18',':',847.00,-15.48,'Power Off','2026-01-14 15:19:59','online','2026-01-14 15:40:16'),(18778,10,'vsol',2288,'cc:2d:21:6b:80:58','2288','cc:2d:21:6b:80:58',NULL,NULL,0,'EPON 0/01','ONU 19',':',597.00,-20.41,'Wire Down','2026-01-14 13:33:09','online','2026-01-14 15:40:16'),(18779,10,'vsol',2469,'cc:2d:21:3a:a1:e8','2469','cc:2d:21:3a:a1:e8',NULL,NULL,0,'EPON 0/01','ONU 27',':',177.00,-19.71,'Power Off','2026-01-14 13:33:06','online','2026-01-14 15:40:16'),(18780,10,'vsol',1874,'58:d9:d5:01:10:b0','1874','58:d9:d5:01:10:b0',NULL,NULL,0,'EPON 0/01','ONU 8',':',483.00,-9.67,'Power Off','2026-01-14 13:33:12','online','2026-01-14 15:40:16'),(18781,10,'vsol',2518,'50:0f:f5:36:18:98','2518','50:0f:f5:36:18:98',NULL,NULL,0,'EPON 0/01','ONU 9',':',949.00,-16.09,'Power Off','2026-01-14 13:33:08','online','2026-01-14 15:40:16'),(18782,10,'vsol',2420,'cc:2d:21:18:d9:38','2420','cc:2d:21:18:d9:38',NULL,NULL,0,'EPON 0/01','ONU 25',':',1608.00,-10.56,'Wire Down','2026-01-01 10:17:56','online','2026-01-14 15:40:16'),(18783,10,'vsol',2418,'5c:62:8b:e1:88:09','2418','5c:62:8b:e1:88:09',NULL,NULL,0,'EPON 0/01','ONU 7',':',911.00,-12.56,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:16'),(18784,10,'vsol',2325,'b0:19:21:11:bd:2d','2325','b0:19:21:11:bd:2d',NULL,NULL,0,'EPON 0/01','ONU 4',':',888.00,-17.90,'Power Off','2026-01-14 13:33:06','online','2026-01-14 15:40:16'),(18785,10,'vsol',2452,'9c:a2:f4:90:0f:29','2452','9c:a2:f4:90:0f:29',NULL,NULL,0,'EPON 0/01','ONU 16',':',636.00,-20.92,'Wire Down','2026-01-14 13:33:08','online','2026-01-14 15:40:16'),(18786,10,'vsol',2060,'58:d9:d5:77:a5:98','2060','58:d9:d5:77:a5:98',NULL,NULL,0,'EPON 0/01','ONU 14',':',2613.00,-11.10,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:40:16'),(18787,10,'vsol',2428,'50:0f:f5:b6:db:08','2428','50:0f:f5:b6:db:08',NULL,NULL,0,'EPON 0/01','ONU 21',':',2049.00,-14.44,'Power Off','2026-01-14 13:33:06','online','2026-01-14 15:40:16'),(18788,10,'vsol',2456,'4c:d7:c8:bd:3d:55','2456','4c:d7:c8:bd:3d:55',101,'2026-01-14 15:21:41',0,'EPON 0/01','ONU 26',':',102.00,-18.21,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:21:41'),(18789,10,'vsol',2543,'d8:32:14:9e:93:e0','2543','d8:32:14:9e:93:e0',NULL,NULL,0,'EPON 0/01','ONU 10',':',667.00,-21.80,'Power Off','2026-01-14 13:33:07','online','2026-01-14 15:40:16'),(18790,10,'vsol',2418,'a0:7e:11:12:a3:27','2418','a0:7e:11:12:a3:27',NULL,NULL,0,'EPON 0/01','ONU 7',':',911.00,-12.56,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:16'),(18791,10,'vsol',2456,'98:ba:5f:42:1d:2d','2456','98:ba:5f:42:1d:2d',NULL,NULL,0,'EPON 0/01','ONU 26',':',102.00,-18.15,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:40:16'),(18792,10,'vsol',NULL,'a2:4e:08:02:4b:90',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 11','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:16'),(18793,10,'vsol',NULL,'a0:7f:04:16:76:ae',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 29','',757.00,-17.08,'Power Off','2026-01-14 12:29:23','offline','2026-01-14 13:00:16'),(18794,10,'vsol',2623,'10:af:78:fe:7b:b9','2623','10:af:78:fe:7b:b9',NULL,NULL,0,'EPON 0/01','ONU 31','',875.00,NULL,'Power Off','2026-01-14 12:29:33','offline','2026-01-14 13:00:16'),(18795,10,'vsol',NULL,'a2:4f:08:26:90:50',NULL,NULL,NULL,NULL,0,'EPON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 12:11:41'),(18796,10,'vsol',1759,'60:a4:b7:3d:d4:a9','1759','60:a4:b7:3d:d4:a9',NULL,NULL,0,'EPON 0/02','ONU 10',':',885.00,-4.65,'Power Off','2026-01-01 11:18:02','online','2026-01-14 15:40:16'),(18797,10,'vsol',1902,'58:d9:d5:29:15:28','1902','58:d9:d5:29:15:28',NULL,NULL,0,'EPON 0/02','ONU 4',':',2678.00,-5.79,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:16'),(18798,10,'vsol',2118,'28:3b:82:4d:be:a9','2118','28:3b:82:4d:be:a9',NULL,NULL,0,'EPON 0/02','ONU 24',':',1508.00,-33.01,'Power Off','2026-01-14 13:33:11','online','2026-01-14 15:40:17'),(18799,10,'vsol',2069,'58:d9:d5:23:ed:58','2069','58:d9:d5:23:ed:58',NULL,NULL,0,'EPON 0/02','ONU 13',':',1590.00,-19.71,'Power Off','2026-01-14 13:33:09','online','2026-01-14 15:40:17'),(18800,10,'vsol',1757,'08:40:f3:8f:70:30','1757','08:40:f3:8f:70:30',NULL,NULL,0,'EPON 0/02','ONU 11',':',552.00,-6.35,'N/A',NULL,'online','2026-01-14 15:40:17'),(18801,10,'vsol',2097,'a8:42:a1:3f:45:43','2097','a8:42:a1:3f:45:43',NULL,NULL,0,'EPON 0/02','ONU 2',':',3106.00,-19.24,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:17'),(18802,10,'vsol',2045,'a0:7d:09:09:51:f6','2045','a0:7d:09:09:51:f6',NULL,NULL,0,'EPON 0/02','ONU 3',':',2728.00,-11.19,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:40:17'),(18803,10,'vsol',2381,'3c:52:a1:ae:88:2f','2381','3c:52:a1:ae:88:2f',NULL,NULL,0,'EPON 0/02','ONU 18',':',1036.00,-17.12,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:17'),(18804,10,'vsol',2454,'d8:32:14:8b:fe:b9','2454','d8:32:14:8b:fe:b9',NULL,NULL,0,'EPON 0/02','ONU 5',':',598.00,-6.47,'Power Off','2026-01-14 13:33:06','online','2026-01-14 15:40:17'),(18805,10,'vsol',2096,'5c:e9:31:dd:a4:4d','2096','5c:e9:31:dd:a4:4d',NULL,NULL,0,'EPON 0/02','ONU 15',':',898.00,-10.03,'Power Off','2026-01-14 13:33:10','online','2026-01-14 15:40:17'),(18806,10,'vsol',2152,'a8:42:a1:c4:a9:70','2152','a8:42:a1:c4:a9:70',NULL,NULL,0,'EPON 0/02','ONU 23',':',769.00,-17.10,'Power Off','2026-01-14 13:33:12','online','2026-01-14 15:40:17'),(18807,10,'vsol',2504,'dc:8e:8d:79:5a:19','2504','dc:8e:8d:79:5a:19',NULL,NULL,0,'EPON 0/02','ONU 22',':',1733.00,-23.10,'Power Off','2026-01-14 13:33:11','online','2026-01-14 15:40:17'),(18808,10,'vsol',1850,'b4:0f:3b:c1:5d:70','1850','b4:0f:3b:c1:5d:70',NULL,NULL,0,'EPON 0/02','ONU 11',':',552.00,-6.35,'N/A',NULL,'online','2026-01-14 15:40:17'),(18809,12,'vsol',1785,'80:af:ca:2f:9d:b1','1785','80:af:ca:2f:9d:b1',NULL,NULL,0,'EPON 0/01','ONU 31',':',1854.00,-24.69,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:40:54'),(18810,10,'vsol',2161,'98:25:4a:a1:05:61','2161','98:25:4a:a1:05:61',NULL,NULL,0,'EPON 0/02','ONU 9',':',738.00,-10.61,'Power Off','2026-01-07 17:20:19','online','2026-01-14 15:40:17'),(18811,12,'vsol',2544,'a0:7f:04:17:5b:9f','2544','a0:7f:04:17:5b:9f',301,'2026-01-14 14:59:16',0,'EPON 0/01','ONU 6',':',1218.00,-15.93,'Power Off','2024-11-21 20:32:17','online','2026-01-14 14:59:18'),(18812,10,'vsol',2171,'14:eb:b6:ab:f1:e7','2171','14:eb:b6:ab:f1:e7',NULL,NULL,0,'EPON 0/02','ONU 20',':',752.00,-17.14,'Power Off','2026-01-14 13:33:09','online','2026-01-14 15:40:17'),(18813,12,'vsol',2124,'cc:2d:21:78:90:20','2124','cc:2d:21:78:90:20',NULL,NULL,0,'EPON 0/01','ONU 43',':',3477.00,-16.70,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:40:55'),(18814,10,'vsol',2045,'58:d9:d5:69:07:08','2045','58:d9:d5:69:07:08',NULL,NULL,0,'EPON 0/02','ONU 3',':',2728.00,-11.19,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:40:17'),(18815,12,'vsol',1864,'d8:32:14:1e:97:60','1864','d8:32:14:1e:97:60',NULL,NULL,0,'EPON 0/01','ONU 32',':',1136.00,-11.99,'Power Off','2024-11-21 20:32:40','online','2026-01-14 15:40:54'),(18816,10,'vsol',2549,'10:be:f5:f9:a5:1b','2549','10:be:f5:f9:a5:1b',NULL,NULL,0,'EPON 0/02','ONU 14',':',1195.00,-20.27,'Power Off','2026-01-14 13:33:05','online','2026-01-14 15:40:17'),(18817,12,'vsol',1784,'08:40:f3:98:40:30','1784','08:40:f3:98:40:30',NULL,NULL,0,'EPON 0/01','ONU 19',':',1934.00,-25.53,'Power Off','2024-11-05 21:57:12','online','2026-01-14 15:40:54'),(18818,10,'vsol',NULL,'6c:68:a4:e8:aa:08',NULL,'58:d9:d5:eb:19:e0',102,'2026-01-14 15:21:41',0,'EPON 0/02','ONU 1',':',747.00,-18.12,'Power Off','2026-01-14 13:33:10','online','2026-01-14 15:21:42'),(18819,12,'vsol',2345,'58:d9:d5:a4:6e:17','2345','58:d9:d5:a4:6e:17',NULL,NULL,0,'EPON 0/01','ONU 28',':',3610.00,-19.59,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:54'),(18820,10,'vsol',2067,'5c:e9:31:59:d3:f3','2067','5c:e9:31:59:d3:f3',NULL,NULL,0,'EPON 0/02','ONU 19',':',1665.00,-23.67,'Power Off','2026-01-14 13:33:11','online','2026-01-14 15:40:17'),(18821,12,'vsol',1852,'30:16:9d:3e:b7:0f','1852','30:16:9d:3e:b7:0f',NULL,NULL,0,'EPON 0/01','ONU 46',':',1374.00,-19.63,'Power Off','2024-11-21 20:32:35','online','2026-01-14 15:40:54'),(18822,10,'vsol',2199,'98:25:4a:f1:a3:b6','2199','98:25:4a:f1:a3:b6',NULL,NULL,0,'EPON 0/02','ONU 8',':',931.00,-11.23,'Power Off','2026-01-14 13:33:04','online','2026-01-14 15:40:17'),(18823,12,'vsol',1905,'50:0f:f5:dc:ce:f0','1905','50:0f:f5:dc:ce:f0',NULL,NULL,0,'EPON 0/01','ONU 52',':',1252.00,-24.20,'Power Off','2024-11-21 20:32:59','online','2026-01-14 15:40:55'),(18824,10,'vsol',1758,'04:95:e6:6f:a2:c0','1758','04:95:e6:6f:a2:c0',NULL,NULL,0,'EPON 0/02','ONU 16',':',746.00,-18.12,'Wire Down','2026-01-14 13:33:11','online','2026-01-14 15:40:17'),(18825,10,'vsol',2045,'a0:7d:09:09:51:f5','2045','a0:7d:09:09:51:f5',NULL,NULL,0,'EPON 0/02','ONU 3',':',2728.00,-11.19,'Power Off','2026-01-14 13:33:03','online','2026-01-14 15:40:17'),(18826,12,'vsol',2298,'84:d8:1b:07:1f:c7','2298','84:d8:1b:07:1f:c7',NULL,NULL,0,'EPON 0/01','ONU 30',':',1897.00,-19.83,'Power Off','2024-11-21 20:33:02','online','2026-01-14 15:40:54'),(18827,10,'vsol',2119,'50:0f:f5:74:9d:e8','2119','50:0f:f5:74:9d:e8',NULL,NULL,0,'EPON 0/02','ONU 12',':',1082.00,-22.15,'Power Off','2026-01-14 13:33:09','online','2026-01-14 15:40:17'),(18828,12,'vsol',2509,'50:0f:f5:bc:29:08','2509','50:0f:f5:bc:29:08',NULL,NULL,0,'EPON 0/01','ONU 18',':',1939.00,-15.78,'Power Off','2024-11-17 20:18:46','online','2026-01-14 15:40:54'),(18829,10,'vsol',2575,'80:af:ca:ba:8f:a7','2575','80:af:ca:ba:8f:a7',NULL,NULL,0,'EPON 0/02','ONU 25',':',1542.00,-17.03,'Power Off','2026-01-14 10:32:39','online','2026-01-14 12:20:17'),(18830,12,'vsol',2397,'cc:2d:21:16:3a:47','2397','cc:2d:21:16:3a:47',NULL,NULL,0,'EPON 0/01','ONU 23',':',3431.00,-22.01,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:55'),(18831,10,'vsol',NULL,'00:d3:9e:b7:56:7c',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 6','',1029.00,NULL,'Wire Down','2026-01-05 13:08:17','offline','2026-01-14 15:40:17'),(18832,10,'vsol',NULL,'a2:3e:08:24:f0:c0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 7','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:17'),(18833,12,'vsol',2167,'e8:65:d4:fc:a7:c8','2167','e8:65:d4:fc:a7:c8',NULL,NULL,0,'EPON 0/01','ONU 34',':',3418.00,-14.01,'Power Off','2024-11-16 17:18:36','online','2026-01-14 15:40:54'),(18834,10,'vsol',NULL,'c0:70:09:b0:61:df',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 17','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:17'),(18835,10,'vsol',NULL,'54:93:59:31:70:6e',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 21','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:17'),(18836,12,'vsol',2091,'04:95:e6:94:be:38','2091','04:95:e6:94:be:38',NULL,NULL,0,'EPON 0/01','ONU 11',':',3480.00,-24.56,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:40:54'),(18837,10,'vsol',NULL,'a2:3e:07:18:ba:60',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 26','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:17'),(18839,12,'vsol',2252,'b8:3a:08:6b:33:90','2252','b8:3a:08:6b:33:90',NULL,NULL,0,'EPON 0/01','ONU 26',':',3293.00,-22.29,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:54'),(18840,10,'vsol',NULL,'dc:2c:6e:6f:e3:f5',NULL,NULL,NULL,NULL,0,'PON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:17'),(18841,12,'vsol',2009,'50:0f:f5:68:e8:00','2009','50:0f:f5:68:e8:00',NULL,NULL,0,'EPON 0/01','ONU 12',':',1003.00,-14.10,'Power Off','2024-11-21 20:32:42','online','2026-01-14 15:40:54'),(18842,10,'vsol',1760,'98:25:4a:f1:1c:9b','1760','98:25:4a:f1:1c:9b',NULL,NULL,0,'PON 0/03','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:17'),(18843,12,'vsol',2524,'d8:32:14:9e:b1:20','2524','d8:32:14:9e:b1:20',NULL,NULL,0,'EPON 0/01','ONU 7',':',2077.00,-27.45,'Power Off','2024-11-21 20:39:11','online','2026-01-14 15:40:54'),(18844,12,'vsol',2510,'88:bd:09:37:ff:b3','2510','88:bd:09:37:ff:b3',NULL,NULL,0,'EPON 0/01','ONU 53',':',1211.00,-27.21,'Power Off','2024-11-21 20:32:42','online','2026-01-14 15:40:54'),(18845,12,'vsol',2362,'b8:3a:08:6c:84:f0','2362','b8:3a:08:6c:84:f0',NULL,NULL,0,'EPON 0/01','ONU 57',':',1234.00,-18.12,'Power Off','2024-11-21 20:32:39','online','2026-01-14 15:40:54'),(18846,12,'vsol',2371,'b4:64:15:ed:ba:f3','2371','b4:64:15:ed:ba:f3',NULL,NULL,0,'EPON 0/01','ONU 58',':',1757.00,-18.15,'Power Off','2024-11-21 20:32:51','online','2026-01-14 15:40:54'),(18847,12,'vsol',1897,'58:d9:d5:29:fa:c8','1897','58:d9:d5:29:fa:c8',NULL,NULL,0,'EPON 0/01','ONU 44',':',1083.00,-10.62,'Power Off','2024-11-21 20:32:55','online','2026-01-14 15:40:54'),(18848,12,'vsol',1859,'04:5e:a4:d5:89:55','1859','04:5e:a4:d5:89:55',NULL,NULL,0,'EPON 0/01','ONU 40',':',1200.00,-19.79,'Power Off','2024-11-21 20:32:58','online','2026-01-14 15:40:55'),(18849,12,'vsol',2125,'cc:2d:21:78:90:28','2125','cc:2d:21:78:90:28',NULL,NULL,0,'EPON 0/01','ONU 42',':',3505.00,-23.98,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:54'),(18850,12,'vsol',2265,'e8:65:d4:fc:a7:90','2265','e8:65:d4:fc:a7:90',NULL,NULL,0,'EPON 0/01','ONU 56',':',1318.00,-20.32,'Power Off','2024-11-21 20:32:54','online','2026-01-14 15:40:55'),(18851,12,'vsol',1762,'d8:32:14:87:4b:88','1762','d8:32:14:87:4b:88',NULL,NULL,0,'EPON 0/01','ONU 55',':',1357.00,-21.61,'Power Off','2024-11-21 20:33:07','online','2026-01-14 15:40:54'),(18852,12,'vsol',2474,'b8:3a:08:8c:bc:6f','2474','b8:3a:08:8c:bc:6f',NULL,NULL,0,'EPON 0/01','ONU 33',':',1664.00,-21.49,'Power Off','2024-11-21 02:05:54','online','2026-01-14 15:40:54'),(18853,12,'vsol',2351,'cc:2d:21:17:4e:30','2351','cc:2d:21:17:4e:30',NULL,NULL,0,'EPON 0/01','ONU 2','default',1182.00,-27.96,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:54'),(18854,12,'vsol',2093,'50:0f:f5:bc:69:90','2093','50:0f:f5:bc:69:90',NULL,NULL,0,'EPON 0/01','ONU 27',':',1679.00,-22.01,'Power Off','2024-11-21 20:32:31','online','2026-01-14 15:40:54'),(18855,12,'vsol',2544,'cc:2d:21:58:2e:c8','2544','cc:2d:21:58:2e:c8',NULL,NULL,0,'EPON 0/01','ONU 6',':',1218.00,-16.07,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:54'),(18856,12,'vsol',2570,'b4:64:15:ee:af:23','2570','b4:64:15:ee:af:23',NULL,NULL,0,'EPON 0/01','ONU 14',':',2016.00,-23.10,'Power Off','2024-11-21 22:22:02','online','2026-01-14 15:40:54'),(18857,12,'vsol',1854,'cc:2d:21:dc:c6:98','1854','cc:2d:21:dc:c6:98',NULL,NULL,0,'EPON 0/01','ONU 15',':',1141.00,-16.70,'Power Off','2024-11-21 20:32:47','online','2026-01-14 15:40:54'),(18858,12,'vsol',1755,'b4:0f:3b:c3:ea:c8','1755','b4:0f:3b:c3:ea:c8',NULL,NULL,0,'EPON 0/01','ONU 12',':',1003.00,-14.10,'Power Off','2024-11-21 20:32:42','online','2026-01-14 15:40:54'),(18859,12,'vsol',2109,'04:95:e6:94:be:30','2109','04:95:e6:94:be:30',NULL,NULL,0,'EPON 0/01','ONU 21',':',3324.00,-27.45,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:55'),(18860,12,'vsol',1839,'54:af:97:95:df:39','1839','54:af:97:95:df:39',NULL,NULL,0,'EPON 0/01','',':',0.00,-17.50,'Power Off','2024-11-21 17:31:44','unknown','2026-01-14 12:37:48'),(18861,12,'vsol',1866,'e8:65:d4:fc:a7:c0','1866','e8:65:d4:fc:a7:c0',NULL,NULL,0,'EPON 0/01','ONU 39',':',1288.00,-17.17,'Power Off','2024-11-21 20:32:35','online','2026-01-14 15:40:54'),(18862,12,'vsol',2253,'b8:3a:08:6b:33:98','2253','b8:3a:08:6b:33:98',NULL,NULL,0,'EPON 0/01','ONU 24',':',3352.00,-23.77,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:40:54'),(18863,12,'vsol',1754,'08:40:f3:98:40:68','1754','08:40:f3:98:40:68',NULL,NULL,0,'EPON 0/01','ONU 38',':',1524.00,-28.86,'Power Off','2024-11-21 20:32:34','online','2026-01-14 15:40:54'),(18864,12,'vsol',2400,'b8:3a:08:dd:6f:58','2400','b8:3a:08:dd:6f:58',NULL,NULL,0,'EPON 0/01','ONU 8',':',1256.00,-16.42,'Power Off','2024-11-21 20:32:44','online','2026-01-14 15:40:54'),(18865,12,'vsol',2212,'d8:32:14:09:a6:c8','2212','d8:32:14:09:a6:c8',NULL,NULL,0,'EPON 0/01','ONU 29',':',1152.00,-40.00,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:40:54'),(18866,12,'vsol',2021,'50:0f:f5:aa:de:f0','2021','50:0f:f5:aa:de:f0',NULL,NULL,0,'EPON 0/01','ONU 47',':',1179.00,-33.01,'Power Off','2024-11-21 20:32:36','online','2026-01-14 15:40:54'),(18867,12,'vsol',2573,'e8:65:d4:fc:b8:68','2573','e8:65:d4:fc:b8:68',NULL,NULL,0,'EPON 0/01','ONU 37',':',995.00,-17.54,'Power Off','2024-11-21 20:32:47','online','2026-01-14 15:40:54'),(18868,12,'vsol',2011,'e8:65:d4:fc:ac:f8','2011','e8:65:d4:fc:ac:f8',NULL,NULL,0,'EPON 0/01','ONU 48',':',1288.00,-25.38,'Power Off','2024-11-21 20:32:48','online','2026-01-14 15:40:54'),(18869,12,'vsol',1810,'ac:15:a2:36:7d:e0','1810','ac:15:a2:36:7d:e0',NULL,NULL,0,'EPON 0/01','ONU 1','default',1185.00,-22.76,'Wire Down','2024-11-07 09:56:40','online','2026-01-14 15:40:55'),(18870,12,'vsol',1812,'58:d9:d5:eb:19:c0','1812','58:d9:d5:eb:19:c0',NULL,NULL,0,'EPON 0/01','ONU 22',':',3193.00,-14.12,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:55'),(18871,12,'vsol',2474,'4c:d7:c8:ce:6e:ba','2474','4c:d7:c8:ce:6e:ba',NULL,NULL,0,'EPON 0/01','ONU 33',':',1664.00,-21.31,'Power Off','2024-11-21 02:05:54','online','2026-01-14 15:40:54'),(18872,12,'vsol',1814,'d8:32:14:1e:91:18','1814','d8:32:14:1e:91:18',NULL,NULL,0,'EPON 0/01','ONU 51',':',1193.00,-23.98,'N/A',NULL,'online','2026-01-14 15:40:54'),(18873,12,'vsol',1803,'d8:32:14:9a:b8:90','1803','d8:32:14:9a:b8:90',NULL,NULL,0,'EPON 0/01','ONU 3',':',1783.00,-14.76,'Wire Down','2024-11-21 20:32:25','online','2026-01-14 15:40:54'),(18874,12,'vsol',1773,'c0:06:c3:19:7d:6d','1773','c0:06:c3:19:7d:6d',NULL,NULL,0,'EPON 0/01','ONU 35',':',3513.00,-20.22,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:40:54'),(18875,12,'vsol',2555,'b4:64:15:ee:7e:43','2555','b4:64:15:ee:7e:43',NULL,NULL,0,'EPON 0/01','ONU 13',':',1098.00,-13.91,'Power Off','2024-11-21 20:32:55','online','2026-01-14 15:40:55'),(18876,12,'vsol',1901,'e8:65:d4:fc:63:00','1901','e8:65:d4:fc:63:00',NULL,NULL,0,'EPON 0/01','ONU 49',':',1338.00,-17.54,'Power Off','2024-11-21 20:32:57','online','2026-01-14 15:40:54'),(18877,12,'vsol',2025,'3c:52:a1:ae:4d:9e','2025','3c:52:a1:ae:4d:9e',NULL,NULL,0,'EPON 0/01','ONU 10',':',1118.00,-11.97,'Power Off','2024-11-21 20:32:53','online','2026-01-14 15:40:54'),(18878,12,'vsol',1808,'e8:65:d4:27:5e:28','1808','e8:65:d4:27:5e:28',NULL,NULL,0,'EPON 0/01','ONU 25',':',985.00,-22.15,'Power Off','2024-11-21 20:32:27','online','2026-01-14 15:40:55'),(18879,12,'vsol',2212,'3c:fa:d3:c0:f9:2c','2212','3c:fa:d3:c0:f9:2c',NULL,NULL,0,'EPON 0/01','ONU 29',':',1152.00,-40.00,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:40:54'),(18880,12,'vsol',2038,'58:d9:d5:9e:79:80','2038','58:d9:d5:9e:79:80',NULL,NULL,0,'EPON 0/01','ONU 21',':',3324.00,-27.45,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:54'),(18881,12,'vsol',1761,'d8:07:b6:c3:cd:b9','1761','d8:07:b6:c3:cd:b9',NULL,NULL,0,'EPON 0/01','ONU 20',':',1124.00,-22.15,'Power Off','2024-11-21 20:33:07','online','2026-01-14 15:40:54'),(18882,12,'vsol',1807,'e8:65:d4:fc:a4:c0','1807','e8:65:d4:fc:a4:c0',NULL,NULL,0,'EPON 0/01','ONU 50',':',1677.00,-18.57,'Power Off','2024-11-21 20:32:49','online','2026-01-14 15:40:54'),(18883,12,'vsol',1861,'d8:32:14:c0:5b:28','1861','d8:32:14:c0:5b:28',NULL,NULL,0,'EPON 0/01','ONU 9',':',1179.00,-18.57,'Power Off','2024-11-21 20:32:43','online','2026-01-14 15:40:54'),(18884,12,'vsol',2158,'04:95:e6:9d:08:a8','2158','04:95:e6:9d:08:a8',NULL,NULL,0,'EPON 0/01','ONU 41',':',1585.00,-18.10,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:54'),(18885,12,'vsol',2109,'a0:7d:09:09:52:6d','2109','a0:7d:09:09:52:6d',NULL,NULL,0,'EPON 0/01','ONU 21',':',3324.00,-27.45,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:54'),(18886,12,'vsol',1885,'c4:70:0b:5d:40:b9','1885','60:32:b1:88:32:81',301,'2026-01-14 15:29:11',0,'EPON 0/01','ONU 36',':',1490.00,-17.40,'Power Off','2024-11-02 07:16:26','online','2026-01-14 15:29:13'),(18887,12,'vsol',2109,'a0:7d:09:09:52:6e','2109','a0:7d:09:09:52:6e',NULL,NULL,0,'EPON 0/01','ONU 21',':',3324.00,-27.45,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:54'),(18888,12,'vsol',1885,'60:32:b1:88:32:81','1885','60:32:b1:88:32:81',NULL,NULL,0,'EPON 0/01','ONU 36',':',1490.00,-17.40,'Power Off','2024-11-02 07:16:26','online','2026-01-14 15:40:54'),(18889,12,'vsol',2606,'cc:2d:21:05:56:08','2606','cc:2d:21:05:56:08',NULL,NULL,0,'EPON 0/01','ONU 5',':',1170.00,-19.07,'Power Off','2024-11-21 20:32:54','online','2026-01-14 15:40:54'),(18890,12,'vsol',2285,'b4:0f:3b:37:03:47','2285','b4:0f:3b:37:03:47',NULL,NULL,0,'EPON 0/01','ONU 16',':',3385.00,-29.59,'Wire Down','2024-11-20 22:10:44','online','2026-01-14 15:40:54'),(18891,12,'vsol',2115,'cc:2d:21:4c:f3:50','2115','cc:2d:21:4c:f3:50',NULL,NULL,0,'EPON 0/01','ONU 59',':',1364.00,-12.71,'Power Off','2024-11-21 20:32:30','online','2026-01-14 15:40:54'),(18892,12,'vsol',2012,'08:40:f3:c2:1a:b8','2012','08:40:f3:c2:1a:b8',NULL,NULL,0,'EPON 0/01','ONU 45',':',1252.00,-23.01,'Power Off','2024-11-21 20:32:46','online','2026-01-14 15:40:55'),(18902,12,'vsol',2158,'6c:68:a4:47:4e:75','2158','6c:68:a4:47:4e:75',NULL,NULL,0,'EPON 0/01','ONU 41',':',1585.00,-18.10,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:54'),(18920,12,'vsol',1785,'c4:70:0b:5c:fe:a1','1785','c4:70:0b:5c:fe:a1',NULL,NULL,0,'EPON 0/01','ONU 31',':',1854.00,-24.20,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:40:54'),(18959,12,'vsol',2022,'5c:a6:e6:f1:90:87','2022','5c:a6:e6:f1:90:87',NULL,NULL,0,'EPON 0/02','ONU 36',':',865.00,-24.32,'Power Off','2024-11-21 20:32:38','online','2026-01-14 15:40:56'),(18960,12,'vsol',2217,'d8:32:14:72:9e:ff','2217','d8:32:14:72:9e:ff',NULL,NULL,0,'EPON 0/02','ONU 31',':',777.00,-20.86,'Power Off','2024-11-21 20:33:06','online','2026-01-14 15:40:56'),(18961,12,'vsol',2530,'bc:e0:01:a9:de:e0','2530','bc:e0:01:a9:de:e0',NULL,NULL,0,'EPON 0/02','ONU 27',':',3582.00,-18.10,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:40:56'),(18962,12,'vsol',2552,'d8:32:14:3a:e8:28','2552','d8:32:14:3a:e8:28',NULL,NULL,0,'EPON 0/02','ONU 11',':',757.00,-14.31,'Power Off','2024-11-21 20:33:10','online','2026-01-14 15:40:56'),(18963,12,'vsol',2017,'3c:fa:d3:c0:48:9c','2017','3c:fa:d3:c0:48:9c',NULL,NULL,0,'EPON 0/02','ONU 34',':',715.00,-23.10,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:56'),(18964,12,'vsol',2330,'b8:3a:08:dc:d3:08','2330','b8:3a:08:dc:d3:08',NULL,NULL,0,'EPON 0/02','ONU 2',':',1285.00,-26.38,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:55'),(18965,12,'vsol',1886,'d8:32:14:be:f7:90','1886','d8:32:14:be:f7:90',NULL,NULL,0,'EPON 0/02','ONU 26',':',1316.00,-18.39,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:40:55'),(18966,12,'vsol',1767,'b4:0f:3b:48:f9:70','1767','b4:0f:3b:48:f9:70',NULL,NULL,0,'EPON 0/02','ONU 5',':',729.00,-19.83,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:40:56'),(18967,12,'vsol',2577,'cc:2d:21:dc:9b:38','2577','cc:2d:21:dc:9b:38',NULL,NULL,0,'EPON 0/02','ONU 17',':',1303.00,-17.57,'N/A',NULL,'online','2026-01-14 15:40:55'),(18968,12,'vsol',1766,'90:9a:4a:06:a5:fd','1766','90:9a:4a:06:a5:fd',NULL,NULL,0,'EPON 0/02','ONU 23',':',775.00,-17.72,'Power Off','2024-11-21 22:29:18','online','2026-01-14 15:40:56'),(18969,12,'vsol',1883,'bc:e0:01:89:e6:3b','1883','bc:e0:01:89:e6:3b',NULL,NULL,0,'EPON 0/02','ONU 16',':',672.00,-29.59,'Power Off','2024-11-21 20:33:09','online','2026-01-14 15:40:56'),(18970,12,'vsol',2502,'cc:2d:21:26:6b:ff','2502','cc:2d:21:26:6b:ff',NULL,NULL,0,'EPON 0/02','ONU 19',':',3500.00,-14.01,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:40:56'),(18971,12,'vsol',2358,'b8:3a:08:a7:4a:d7','2358','b8:3a:08:a7:4a:d7',NULL,NULL,0,'EPON 0/02','ONU 1',':',836.00,-22.37,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:40:56'),(18972,12,'vsol',2320,'60:83:e7:92:53:42','2320','60:83:e7:92:53:42',NULL,NULL,0,'EPON 0/02','ONU 33',':',1877.00,-10.67,'Power Off','2024-11-21 20:32:37','online','2026-01-14 15:40:55'),(18973,12,'vsol',1842,'cc:2d:21:31:6e:d0','1842','cc:2d:21:31:6e:d0',NULL,NULL,0,'EPON 0/02','ONU 21',':',3910.00,-19.55,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:56'),(18974,12,'vsol',2462,'88:bd:09:47:d6:d6','2462','88:bd:09:47:d6:d6',NULL,NULL,0,'EPON 0/02','ONU 32',':',1377.00,-11.42,'Power Off','2024-11-21 20:32:46','online','2026-01-14 15:40:56'),(18975,12,'vsol',1756,'d8:32:14:86:d5:80','1756','d8:32:14:86:d5:80',NULL,NULL,0,'EPON 0/02','ONU 12',':',3870.00,-9.53,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:40:56'),(18976,12,'vsol',1892,'50:0f:f5:66:c3:10','1892','50:0f:f5:66:c3:10',NULL,NULL,0,'EPON 0/02','ONU 22',':',831.00,-21.19,'Power Off','2024-11-21 19:35:12','online','2026-01-14 15:40:56'),(18977,12,'vsol',1847,'d8:32:14:a0:69:08','1847','d8:32:14:a0:69:08',NULL,NULL,0,'EPON 0/02','ONU 9',':',747.00,-23.37,'Power Off','2024-11-21 20:32:53','online','2026-01-14 15:40:56'),(18978,12,'vsol',1765,'58:d9:d5:29:12:e0','1765','58:d9:d5:29:12:e0',NULL,NULL,0,'EPON 0/02','ONU 34',':',715.00,-22.22,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:56'),(18979,12,'vsol',2150,'cc:2d:21:58:c4:07','2150','cc:2d:21:58:c4:07',NULL,NULL,0,'EPON 0/02','ONU 14',':',1815.00,-26.38,'Power Off','2024-11-21 20:32:47','online','2026-01-14 15:40:56'),(18980,12,'vsol',2240,'3c:fa:d3:c2:55:34','2240','3c:fa:d3:c2:55:34',NULL,NULL,0,'EPON 0/02','ONU 30',':',783.00,-17.83,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:55'),(18981,12,'vsol',2017,'10:27:f5:8a:ae:31','2017','10:27:f5:8a:ae:31',NULL,NULL,0,'EPON 0/02','ONU 34',':',715.00,-22.22,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:56'),(18982,12,'vsol',1764,'58:d9:d5:29:fa:a0','1764','58:d9:d5:29:fa:a0',NULL,NULL,0,'EPON 0/02','ONU 16',':',672.00,-29.59,'Power Off','2024-11-21 20:33:09','online','2026-01-14 15:40:56'),(18983,12,'vsol',2085,'58:d9:d5:74:ea:48','2085','58:d9:d5:74:ea:48',NULL,NULL,0,'EPON 0/02','ONU 20',':',3990.00,-20.22,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:56'),(18984,12,'vsol',1768,'50:0f:f5:eb:57:b8','1768','50:0f:f5:eb:57:b8',NULL,NULL,0,'EPON 0/02','ONU 35',':',780.00,-19.47,'Wire Down','2024-11-21 20:32:59','online','2026-01-14 15:40:56'),(18985,12,'vsol',1881,'7c:11:cb:7f:61:66','1881','7c:11:cb:7f:61:66',NULL,NULL,0,'EPON 0/02','ONU 29',':',1821.00,-17.80,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:56'),(18986,12,'vsol',1867,'bc:22:28:b1:5a:8b','1867','bc:22:28:b1:5a:8b',NULL,NULL,0,'EPON 0/02','ONU 37',':',1854.00,-13.72,'Power Off','2024-11-21 20:32:49','online','2026-01-14 15:40:55'),(18987,12,'vsol',2024,'78:8c:b5:e9:cc:a5','2024','78:8c:b5:e9:cc:a5',NULL,NULL,0,'EPON 0/02','ONU 7',':',733.00,-15.69,'Power Off','2024-11-21 20:33:05','online','2026-01-14 15:40:56'),(18988,12,'vsol',1886,'c4:70:0b:33:90:e1','1886','d8:32:14:be:f7:90',302,'2026-01-14 15:09:31',0,'EPON 0/02','ONU 26',':',1316.00,-19.24,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:09:36'),(18989,12,'vsol',2317,'b8:3a:08:80:69:b8','2317','b8:3a:08:80:69:b8',NULL,NULL,0,'EPON 0/02','ONU 6',':',913.00,-25.38,'Power Off','2024-11-21 20:32:28','online','2026-01-14 15:40:56'),(18990,12,'vsol',2505,'3c:6a:d2:0f:8f:1d','2505','3c:6a:d2:0f:8f:1d',NULL,NULL,0,'EPON 0/02','ONU 15',':',1944.00,-23.98,'Power Off','2024-11-21 20:32:27','online','2026-01-14 15:40:56'),(18991,12,'vsol',1763,'58:d9:d5:29:fa:40','1763','58:d9:d5:29:fa:40',NULL,NULL,0,'EPON 0/02','ONU 8',':',682.00,-16.80,'Power Off','2024-11-21 20:32:59','online','2026-01-14 15:40:56'),(18992,12,'vsol',2368,'3c:fa:d3:c3:53:5c','2368','3c:fa:d3:c3:53:5c',NULL,NULL,0,'EPON 0/02','ONU 25',':',1701.00,0.98,'Power Off','2024-11-20 00:08:34','online','2026-01-14 15:40:56'),(18993,12,'vsol',1841,'d8:32:14:1e:72:90','1841','d8:32:14:1e:72:90',NULL,NULL,0,'EPON 0/02','ONU 10',':',1901.00,-19.55,'Wire Down','2024-11-21 20:32:39','online','2026-01-14 15:40:56'),(18994,12,'vsol',2368,'dc:62:79:d7:ba:e5','2368','dc:62:79:d7:ba:e5',NULL,NULL,0,'EPON 0/02','ONU 25',':',1701.00,0.98,'Power Off','2024-11-20 00:08:34','online','2026-01-14 15:40:56'),(18995,12,'vsol',1867,'bc:22:28:b1:5a:8a','1867','bc:22:28:b1:5a:8a',NULL,NULL,0,'EPON 0/02','ONU 37',':',1854.00,-13.72,'Power Off','2024-11-21 20:32:49','online','2026-01-14 15:40:56'),(18996,12,'vsol',2075,'58:d9:d5:74:ea:40','2075','58:d9:d5:74:ea:40',NULL,NULL,0,'EPON 0/02','ONU 18',':',2747.00,-24.56,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:56'),(18997,12,'vsol',2020,'d8:32:14:a0:26:10','2020','d8:32:14:a0:26:10',NULL,NULL,0,'EPON 0/02','ONU 28',':',3965.00,-21.80,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:55'),(18998,12,'vsol',2335,'d8:32:14:65:25:28','2335','d8:32:14:65:25:28',NULL,NULL,0,'EPON 0/02','ONU 13',':',805.00,-20.46,'Power Off','2024-11-21 20:32:23','online','2026-01-14 15:40:56'),(18999,12,'vsol',2240,'50:0f:f5:be:91:e0','2240','50:0f:f5:be:91:e0',NULL,NULL,0,'EPON 0/02','ONU 30',':',783.00,-16.93,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:55'),(19000,12,'vsol',1844,'34:60:f9:6c:d7:5d','1844','34:60:f9:6c:d7:5d',NULL,NULL,0,'EPON 0/02','ONU 24',':',1865.00,-23.01,'Power Off','2024-11-21 20:32:50','online','2026-01-14 15:40:55'),(19001,12,'vsol',1821,'cc:2d:21:89:a1:e8','1821','cc:2d:21:89:a1:e8',NULL,NULL,0,'EPON 0/02','ONU 3',':',1801.00,-9.60,'Wire Down','2024-11-21 20:32:37','online','2026-01-14 15:40:56'),(19042,12,'vsol',NULL,'4c:ae:1c:27:c5:30',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 62','',1320.00,NULL,'Wire Down','2024-11-21 21:25:41','offline','2026-01-14 15:41:00'),(19043,12,'vsol',1816,'68:ff:7b:4b:bc:2f','1816','68:ff:7b:4b:bc:2f',NULL,NULL,0,'EPON 0/03','ONU 3',':',1275.00,-24.95,'Wire Down','2024-11-21 20:32:39','online','2026-01-14 15:40:57'),(19044,12,'vsol',2611,'40:ae:30:f4:0e:31','2611','40:ae:30:f4:0e:31',NULL,NULL,0,'EPON 0/03','ONU 51',':',1901.00,-19.17,'Wire Down','2024-11-21 20:40:17','online','2026-01-14 15:40:57'),(19045,12,'vsol',2329,'d8:32:14:a4:95:b8','2329','d8:32:14:a4:95:b8',NULL,NULL,0,'EPON 0/03','ONU 43',':',3198.00,-24.09,'Wire Down','2024-11-21 20:41:51','online','2026-01-14 15:40:57'),(19046,12,'vsol',2033,'6c:68:a4:27:80:7f','2033','6c:68:a4:27:80:7f',NULL,NULL,0,'EPON 0/03','ONU 8',':',1349.00,-28.54,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:58'),(19047,12,'vsol',1835,'d8:32:14:61:0a:e0','1835','d8:32:14:61:0a:e0',NULL,NULL,0,'EPON 0/03','ONU 34',':',2636.00,-18.76,'Wire Down','2024-11-21 17:07:45','online','2026-01-14 15:40:57'),(19048,12,'vsol',2321,'60:83:e7:92:6d:cd','2321','60:83:e7:92:6d:cd',NULL,NULL,0,'EPON 0/03','ONU 44',':',2952.00,-23.67,'Power Off','2024-11-21 20:32:48','online','2026-01-14 15:40:58'),(19049,12,'vsol',2624,'08:40:f3:94:b8:bf','2624','08:40:f3:94:b8:bf',NULL,NULL,0,'EPON 0/03','ONU 46',':',742.00,-24.44,'Power Off','2024-11-21 20:32:33','online','2026-01-14 15:40:58'),(19050,12,'vsol',2603,'a0:7f:08:05:99:41','2603','a0:7f:08:05:99:41',NULL,NULL,0,'EPON 0/03','ONU 13',':',2901.00,-22.84,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:40:58'),(19051,12,'vsol',2438,'d8:32:14:55:8f:b8','2438','d8:32:14:55:8f:b8',NULL,NULL,0,'EPON 0/03','ONU 52',':',2285.00,-20.13,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:57'),(19052,12,'vsol',2218,'d8:32:14:a0:2b:50','2218','d8:32:14:a0:2b:50',NULL,NULL,0,'EPON 0/03','ONU 42',':',1974.00,-23.47,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:40:57'),(19053,12,'vsol',1857,'e8:65:d4:52:86:b0','1857','e8:65:d4:52:86:b0',NULL,NULL,0,'EPON 0/03','ONU 22',':',2723.00,-14.49,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:57'),(19054,12,'vsol',2378,'cc:2d:21:17:4e:58','2378','cc:2d:21:17:4e:58',NULL,NULL,0,'EPON 0/03','ONU 16',':',821.00,-25.69,'Power Off','2024-11-21 20:33:08','online','2026-01-14 15:40:57'),(19055,12,'vsol',2562,'d8:32:14:c9:de:39','2562','d8:32:14:c9:de:39',NULL,NULL,0,'EPON 0/03','ONU 39',':',2808.00,-22.84,'Power Off','2024-11-17 21:17:27','online','2026-01-14 15:40:57'),(19056,12,'vsol',2534,'d8:32:14:30:f3:b8','2534','d8:32:14:30:f3:b8',NULL,NULL,0,'EPON 0/03','ONU 24',':',849.00,-17.50,'Power Off','2024-11-21 20:32:40','online','2026-01-14 15:40:57'),(19057,12,'vsol',2111,'b4:64:15:ed:c9:53','2111','b4:64:15:ed:c9:53',NULL,NULL,0,'EPON 0/03','ONU 47',':',2047.00,-22.76,'Power Off','2024-11-21 20:33:03','online','2026-01-14 15:40:57'),(19058,12,'vsol',2463,'3c:64:cf:ba:7f:3f','2463','3c:64:cf:ba:7f:3f',NULL,NULL,0,'EPON 0/03','ONU 7',':',1610.00,-30.97,'Power Off','2024-11-21 20:33:04','online','2026-01-14 15:40:58'),(19059,12,'vsol',2534,'a0:7f:06:13:02:90','2534','a0:7f:06:13:02:90',NULL,NULL,0,'EPON 0/03','',':',0.00,-17.50,'Power Off','2024-11-21 20:32:40','unknown','2026-01-14 15:40:56'),(19060,12,'vsol',2377,'50:0f:f5:82:bb:af','2377','50:0f:f5:82:bb:af',NULL,NULL,0,'EPON 0/03','ONU 38',':',923.00,-25.38,'Power Off','2024-11-21 20:32:53','online','2026-01-14 15:40:58'),(19061,12,'vsol',2033,'54:af:97:54:1f:21','2033','54:af:97:54:1f:21',NULL,NULL,0,'EPON 0/03','ONU 8',':',1349.00,-28.54,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:58'),(19062,12,'vsol',2184,'dc:8e:8d:01:c5:8a','2184','dc:8e:8d:01:c5:8a',NULL,NULL,0,'EPON 0/03','ONU 40',':',2560.00,-23.67,'Power Off','2024-11-21 20:33:10','online','2026-01-14 15:40:58'),(19063,12,'vsol',2277,'b8:3a:08:6b:33:a8','2277','b8:3a:08:6b:33:a8',NULL,NULL,0,'EPON 0/03','ONU 14',':',2769.00,-17.70,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:57'),(19064,12,'vsol',2113,'cc:2d:21:74:80:9f','2113','cc:2d:21:74:80:9f',NULL,NULL,0,'EPON 0/03','ONU 18',':',2544.00,-19.55,'Power Off','2024-11-21 21:10:04','online','2026-01-14 15:40:58'),(19065,12,'vsol',1871,'c0:25:2f:72:cd:ff','1871','c0:25:2f:72:cd:ff',NULL,NULL,0,'EPON 0/03','ONU 29',':',1877.00,-23.28,'Power Off','2024-11-21 20:32:52','online','2026-01-14 15:40:57'),(19066,12,'vsol',2092,'04:95:e6:94:be:48','2092','04:95:e6:94:be:48',NULL,NULL,0,'EPON 0/03','ONU 32',':',4088.00,-23.28,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:57'),(19067,12,'vsol',1916,'50:0f:f5:dc:ce:e0','1916','50:0f:f5:dc:ce:e0',NULL,NULL,0,'EPON 0/03','ONU 5',':',2718.00,-21.43,'Power Off','2024-11-21 20:32:48','online','2026-01-14 15:40:57'),(19068,12,'vsol',2603,'08:40:f3:52:17:69','2603','08:40:f3:52:17:69',NULL,NULL,0,'EPON 0/03','ONU 13',':',2901.00,-22.84,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:40:57'),(19069,12,'vsol',2592,'8c:86:dd:ad:df:c5','2592','8c:86:dd:ad:df:c5',NULL,NULL,0,'EPON 0/03','ONU 9',':',1990.00,-23.28,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:40:57'),(19070,12,'vsol',2376,'dc:62:79:93:a3:26','2376','dc:62:79:93:a3:26',NULL,NULL,0,'EPON 0/03','ONU 41',':',2828.00,-15.06,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:57'),(19071,12,'vsol',2198,'d8:32:14:71:c0:17','2198','d8:32:14:71:c0:17',NULL,NULL,0,'EPON 0/03','ONU 36',':',738.00,-20.22,'Power Off','2024-11-21 20:32:28','online','2026-01-14 15:40:57'),(19072,12,'vsol',1925,'9c:a2:f4:bc:37:9d','1925','9c:a2:f4:bc:37:9d',NULL,NULL,0,'EPON 0/03','ONU 48',':',1806.00,-18.10,'Power Off','2024-11-21 20:32:44','online','2026-01-14 15:40:57'),(19073,12,'vsol',2186,'dc:8e:8d:28:79:17','2186','dc:8e:8d:28:79:17',NULL,NULL,0,'EPON 0/03','ONU 23',':',2741.00,-23.47,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:58'),(19074,12,'vsol',1887,'d8:32:14:1e:ba:60','1887','d8:32:14:1e:ba:60',NULL,NULL,0,'EPON 0/03','ONU 2',':',800.00,-22.84,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:40:57'),(19075,12,'vsol',1823,'50:0f:f5:b4:0a:40','1823','50:0f:f5:b4:0a:40',NULL,NULL,0,'EPON 0/03','ONU 28',':',2288.00,-18.57,'Power Off','2024-11-21 12:35:06','online','2026-01-14 15:40:58'),(19076,12,'vsol',2106,'04:95:e6:94:be:28','2106','04:95:e6:94:be:28',NULL,NULL,0,'EPON 0/03','ONU 37',':',2895.00,-23.10,'Power Off','2024-11-21 20:32:29','online','2026-01-14 15:40:57'),(19077,12,'vsol',2112,'cc:2d:21:4e:39:48','2112','cc:2d:21:4e:39:48',NULL,NULL,0,'EPON 0/03','ONU 26',':',4208.00,-25.53,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:40:57'),(19078,12,'vsol',2039,'58:d9:d5:9e:79:58','2039','58:d9:d5:9e:79:58',NULL,NULL,0,'EPON 0/03','ONU 5',':',2718.00,-21.43,'Power Off','2024-11-21 20:32:48','online','2026-01-14 15:40:57'),(19079,12,'vsol',1858,'84:16:f9:de:c6:fd','1858','84:16:f9:de:c6:fd',NULL,NULL,0,'EPON 0/03','ONU 11',':',2457.00,-18.73,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:40:57'),(19080,12,'vsol',2610,'b4:64:15:ee:e3:83','2610','b4:64:15:ee:e3:83',NULL,NULL,0,'EPON 0/03','ONU 49',':',1979.00,-22.92,'Power Off','2024-11-21 20:32:58','online','2026-01-14 15:40:57'),(19081,12,'vsol',2019,'50:0f:f5:aa:df:00','2019','50:0f:f5:aa:df:00',NULL,NULL,0,'EPON 0/03','ONU 17',':',1546.00,-22.08,'Power Off','2024-11-21 20:32:50','online','2026-01-14 15:40:58'),(19082,12,'vsol',2391,'50:0f:f5:b6:56:38','2391','50:0f:f5:b6:56:38',NULL,NULL,0,'EPON 0/03','ONU 31',':',2898.00,-22.01,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:40:57'),(19083,12,'vsol',2564,'b4:64:15:ee:de:b3','2564','b4:64:15:ee:de:b3',NULL,NULL,0,'EPON 0/03','ONU 27',':',2290.00,-18.10,'Power Off','2024-11-21 20:32:51','online','2026-01-14 15:40:57'),(19084,12,'vsol',2628,'88:bd:09:4f:bb:88','2628','88:bd:09:4f:bb:88',NULL,NULL,0,'EPON 0/03','ONU 53',':',2190.00,-23.37,'Power Off','2024-11-21 20:32:58','online','2026-01-14 15:40:57'),(19085,12,'vsol',2324,'b8:3a:08:d5:ef:08','2324','b8:3a:08:d5:ef:08',NULL,NULL,0,'EPON 0/03','ONU 25',':',3428.00,-25.53,'Power Off','2024-11-21 20:32:23','online','2026-01-14 15:40:58'),(19086,12,'vsol',2179,'d8:32:14:09:a7:00','2179','d8:32:14:09:a7:00',NULL,NULL,0,'EPON 0/03','ONU 21',':',3033.00,-30.46,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:57'),(19087,12,'vsol',2110,'cc:2d:21:4c:f3:30','2110','cc:2d:21:4c:f3:30',NULL,NULL,0,'EPON 0/03','ONU 35',':',2013.00,-22.22,'Power Off','2024-11-21 20:32:57','online','2026-01-14 15:40:57'),(19088,12,'vsol',1949,'50:eb:f6:62:d4:68','1949','50:eb:f6:62:d4:68',NULL,NULL,0,'EPON 0/03','ONU 33',':',2290.00,-24.95,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:58'),(19089,12,'vsol',2609,'cc:ba:bd:67:60:3d','2609','cc:ba:bd:67:60:3d',NULL,NULL,0,'EPON 0/03','ONU 50',':',1660.00,-23.37,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:40:57'),(19090,12,'vsol',2567,'d8:32:14:df:8b:00','2567','d8:32:14:df:8b:00',NULL,NULL,0,'EPON 0/03','ONU 4',':',1060.00,-30.46,'Power Off','2024-11-21 20:33:03','online','2026-01-14 15:40:57'),(19091,12,'vsol',2363,'4c:d7:c8:80:15:73','2363','4c:d7:c8:80:15:73',303,'2026-01-14 15:29:11',0,'EPON 0/03','ONU 30',':',2757.00,-18.79,'Power Off','2024-11-17 03:32:43','online','2026-01-14 15:29:16'),(19092,12,'vsol',2599,'08:40:f3:89:3d:ef','2599','08:40:f3:89:3d:ef',NULL,NULL,0,'EPON 0/03','ONU 15',':',2790.00,-21.43,'Power Off','2024-11-21 20:32:45','online','2026-01-14 15:40:57'),(19093,12,'vsol',2262,'b8:3a:08:00:47:48','2262','b8:3a:08:00:47:48',NULL,NULL,0,'EPON 0/03','ONU 19',':',803.00,-16.62,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:40:57'),(19094,12,'vsol',2451,'dc:8e:8d:ba:39:7a','2451','dc:8e:8d:ba:39:7a',NULL,NULL,0,'EPON 0/03','ONU 1',':',2470.00,-22.08,'Power Off','2024-11-21 20:32:23','online','2026-01-14 15:40:57'),(19095,12,'vsol',2363,'cc:2d:21:af:4e:7f','2363','cc:2d:21:af:4e:7f',NULL,NULL,0,'EPON 0/03','ONU 30',':',2757.00,-18.79,'Power Off','2024-11-17 03:32:43','online','2026-01-14 15:40:57'),(19096,12,'vsol',2276,'d8:32:14:42:6d:d7','2276','d8:32:14:42:6d:d7',NULL,NULL,0,'EPON 0/03','ONU 20',':',2911.00,-25.85,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:40:57'),(19097,12,'vsol',2501,'98:da:c4:97:81:39','2501','98:da:c4:97:81:39',NULL,NULL,0,'EPON 0/03','ONU 10',':',2201.00,-24.44,'Wire Down','2024-11-21 20:32:37','online','2026-01-14 15:40:57'),(19098,12,'vsol',2609,'4c:46:d1:86:ac:24','2609','cc:ba:bd:67:60:3d',303,'2026-01-14 15:09:31',0,'EPON 0/03','ONU 50',':',1660.00,-23.47,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:09:38'),(19099,12,'vsol',1948,'04:5e:a4:ea:bb:96','1948','04:5e:a4:ea:bb:96',NULL,NULL,0,'EPON 0/03','ONU 12',':',721.00,-16.86,'Power Off','2024-11-21 20:32:46','online','2026-01-14 15:40:57'),(19126,12,'vsol',1857,'4c:d7:c8:83:3f:fc','1857','4c:d7:c8:83:3f:fc',303,'2026-01-14 15:29:11',0,'EPON 0/03','ONU 22',':',2723.00,-14.49,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:29:16'),(19154,12,'vsol',2376,'4c:d7:c8:7f:cf:ef','2376','4c:d7:c8:7f:cf:ef',NULL,NULL,0,'EPON 0/03','',':',0.00,-15.17,'Power Off','2024-11-21 20:32:16','unknown','2026-01-14 15:29:15'),(19159,12,'vsol',1771,'b4:b0:24:06:b3:9b','1771','b4:b0:24:06:b3:9b',NULL,NULL,0,'EPON 0/04','ONU 6',':',431.00,-16.72,'Power Off','2024-11-21 20:32:35','online','2026-01-14 15:40:58'),(19160,12,'vsol',1862,'68:ff:7b:e7:35:27','1862','68:ff:7b:e7:35:27',NULL,NULL,0,'EPON 0/04','ONU 3',':',70.00,-2.25,'N/A',NULL,'online','2026-01-14 15:40:59'),(19161,12,'vsol',2556,'78:20:51:4a:e9:6d','2556','78:20:51:4a:e9:6d',NULL,NULL,0,'EPON 0/04','ONU 18',':',608.00,-23.77,'N/A',NULL,'online','2026-01-14 15:40:58'),(19162,12,'vsol',1991,'b4:0f:3b:eb:f0:10','1991','b4:0f:3b:eb:f0:10',NULL,NULL,0,'EPON 0/04','ONU 22',':',162.00,-11.40,'Power Off','2024-11-21 20:32:47','online','2026-01-14 15:40:59'),(19163,12,'vsol',1781,'c0:c9:e3:b5:df:0f','1781','c0:c9:e3:b5:df:0f',NULL,NULL,0,'EPON 0/04','ONU 26',':',854.00,-21.80,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:40:58'),(19164,12,'vsol',2516,'cc:2d:21:b9:29:90','2516','cc:2d:21:b9:29:90',NULL,NULL,0,'EPON 0/04','ONU 30',':',485.00,-20.76,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:40:58'),(19165,12,'vsol',2631,'b4:64:15:ee:9b:73','2631','b4:64:15:ee:9b:73',NULL,NULL,0,'EPON 0/04','ONU 13',':',220.00,-12.49,'Power Off','2024-11-03 21:50:01','online','2026-01-14 15:40:59'),(19166,12,'vsol',2128,'cc:2d:21:78:90:40','2128','cc:2d:21:78:90:40',NULL,NULL,0,'EPON 0/04','ONU 16',':',188.00,-11.44,'Power Off','2024-11-21 20:33:08','online','2026-01-14 15:40:59'),(19167,12,'vsol',1780,'c0:c9:e3:1c:f0:99','1780','c0:c9:e3:1c:f0:99',NULL,NULL,0,'EPON 0/04','ONU 5',':',890.00,-15.07,'Power Off','2024-11-21 20:32:32','online','2026-01-14 15:40:59'),(19168,12,'vsol',1800,'cc:2d:21:a8:0f:b8','1800','cc:2d:21:a8:0f:b8',NULL,NULL,0,'EPON 0/04','ONU 9',':',242.00,-9.15,'Power Off','2024-11-21 20:32:33','online','2026-01-14 15:40:59'),(19169,12,'vsol',2615,'5c:a6:e6:8b:0d:43','2615','5c:a6:e6:8b:0d:43',NULL,NULL,0,'EPON 0/04','ONU 35',':',577.00,-15.00,'Power Off','2024-11-21 20:33:10','online','2026-01-14 15:40:59'),(19170,12,'vsol',2494,'d8:32:14:06:10:70','2494','d8:32:14:06:10:70',NULL,NULL,0,'EPON 0/04','ONU 2',':',546.00,-11.62,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:59'),(19171,12,'vsol',2228,'d8:32:14:e5:aa:f0','2228','d8:32:14:e5:aa:f0',NULL,NULL,0,'EPON 0/04','ONU 19',':',400.00,-16.84,'Power Off','2024-11-21 20:33:06','online','2026-01-14 15:40:58'),(19172,12,'vsol',1772,'98:ba:5f:2d:d5:d3','1772','98:ba:5f:2d:d5:d3',NULL,NULL,0,'EPON 0/04','ONU 8',':',342.00,-12.54,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:40:59'),(19173,12,'vsol',2425,'60:d2:dd:01:05:14','2425','60:d2:dd:01:05:14',NULL,NULL,0,'EPON 0/04','ONU 7',':',261.00,-14.41,'Power Off','2024-11-21 20:33:06','online','2026-01-14 15:40:59'),(19174,12,'vsol',2192,'0c:0e:76:a0:1a:bf','2192','0c:0e:76:a0:1a:bf',NULL,NULL,0,'EPON 0/04','ONU 20',':',272.00,-8.72,'Power Off','2024-11-21 20:32:27','online','2026-01-14 15:40:58'),(19175,12,'vsol',2228,'a0:7e:04:15:19:55','2228','d8:32:14:e5:aa:f0',304,'2026-01-14 15:29:11',0,'EPON 0/04','ONU 19',':',400.00,-16.84,'Power Off','2024-11-21 20:33:06','online','2026-01-14 15:29:17'),(19176,12,'vsol',2496,'1c:ef:03:e4:ff:f1','2496','1c:ef:03:e4:ff:f1',NULL,NULL,0,'EPON 0/04','ONU 14',':',377.00,-16.80,'Power Off','2024-11-21 20:33:01','online','2026-01-14 15:40:59'),(19177,12,'vsol',1906,'bc:0f:9a:22:e8:e7','1906','bc:0f:9a:22:e8:e7',NULL,NULL,0,'EPON 0/04','ONU 34',':',128.00,-8.86,'Power Off','2024-11-10 15:27:50','online','2026-01-14 15:40:59'),(19178,12,'vsol',2630,'30:07:5c:21:b6:2a','2630','30:07:5c:21:b6:2a',NULL,NULL,0,'EPON 0/04','ONU 36','',457.00,0.00,'Power Off','2024-11-21 02:14:41','online','2026-01-13 19:25:52'),(19179,12,'vsol',2300,'b8:3a:08:c8:b8:08','2300','b8:3a:08:c8:b8:08',NULL,NULL,0,'EPON 0/04','ONU 33',':',547.00,-26.99,'Power Off','2024-11-21 20:32:35','online','2026-01-14 15:40:59'),(19180,12,'vsol',1782,'dc:8e:8d:28:f4:62','1782','dc:8e:8d:28:f4:62',NULL,NULL,0,'EPON 0/04','ONU 27',':',246.00,-17.96,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:40:58'),(19181,12,'vsol',2355,'f0:09:0d:a6:4f:f7','2355','f0:09:0d:a6:4f:f7',NULL,NULL,0,'EPON 0/04','ONU 17',':',202.00,-12.77,'Power Off','2024-11-21 20:32:28','online','2026-01-14 15:40:59'),(19182,12,'vsol',2332,'b0:19:21:15:2d:c9','2332','b0:19:21:15:2d:c9',NULL,NULL,0,'EPON 0/04','ONU 28',':',139.00,-13.44,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:58'),(19183,12,'vsol',1899,'08:40:f3:8f:6d:28','1899','08:40:f3:8f:6d:28',NULL,NULL,0,'EPON 0/04','ONU 15',':',51.00,-14.24,'Power Off','2024-11-21 21:48:58','online','2026-01-14 15:40:58'),(19184,12,'vsol',2380,'d8:32:14:1d:a9:40','2380','d8:32:14:1d:a9:40',NULL,NULL,0,'EPON 0/04','ONU 25',':',523.00,-19.28,'Wire Down','2024-11-21 20:32:51','online','2026-01-14 15:40:58'),(19185,12,'vsol',1769,'60:a4:b7:b2:0d:39','1769','60:a4:b7:b2:0d:39',NULL,NULL,0,'EPON 0/04','ONU 12',':',126.00,-8.50,'N/A',NULL,'online','2026-01-14 15:40:58'),(19186,12,'vsol',1846,'54:af:97:e1:ee:6f','1846','54:af:97:e1:ee:6f',NULL,NULL,0,'EPON 0/04','ONU 23',':',454.00,-12.40,'Power Off','2024-11-21 20:32:46','online','2026-01-14 15:40:59'),(19187,12,'vsol',2372,'cc:2d:21:18:47:a8','2372','cc:2d:21:18:47:a8',NULL,NULL,0,'EPON 0/04','ONU 4',':',588.00,-14.60,'Power Off','2024-11-21 20:33:03','online','2026-01-14 15:40:59'),(19188,12,'vsol',2007,'50:0f:f5:33:d9:58','2007','50:0f:f5:33:d9:58',NULL,NULL,0,'EPON 0/04','ONU 15',':',51.00,-14.24,'Power Off','2024-11-21 21:48:58','online','2026-01-14 15:40:59'),(19189,12,'vsol',1787,'e8:65:d4:27:5e:40','1787','e8:65:d4:27:5e:40',NULL,NULL,0,'EPON 0/04','ONU 1',':',418.00,-13.15,'N/A',NULL,'online','2026-01-14 15:40:59'),(19190,12,'vsol',1786,'b8:3a:08:de:9e:30','1786','b8:3a:08:de:9e:30',NULL,NULL,0,'EPON 0/04','ONU 24',':',374.00,-11.75,'Wire Down','2024-11-21 19:29:43','online','2026-01-14 15:40:59'),(19191,12,'vsol',2154,'cc:2d:21:e9:b9:a0','2154','cc:2d:21:e9:b9:a0',NULL,NULL,0,'EPON 0/04','ONU 11',':',2392.00,-19.36,'N/A',NULL,'online','2026-01-14 15:40:58'),(19192,12,'vsol',1782,'c4:70:0b:5d:37:b9','1782','c4:70:0b:5d:37:b9',NULL,NULL,0,'EPON 0/04','ONU 27',':',246.00,-17.96,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:40:59'),(19193,12,'vsol',2339,'50:0f:f5:db:69:a8','2339','50:0f:f5:db:69:a8',NULL,NULL,0,'EPON 0/04','ONU 29',':',429.00,-16.13,'Power Off','2024-11-21 20:32:30','online','2026-01-14 15:40:58'),(19194,12,'vsol',2349,'d8:32:14:55:d2:d8','2349','d8:32:14:55:d2:d8',NULL,NULL,0,'EPON 0/04','ONU 21',':',601.00,-28.24,'Power Off','2024-11-21 20:32:57','online','2026-01-14 15:40:59'),(19195,12,'vsol',1770,'cc:2d:21:24:0d:37','1770','cc:2d:21:24:0d:37',NULL,NULL,0,'EPON 0/04','ONU 34',':',128.00,-8.86,'Power Off','2024-11-10 15:27:50','online','2026-01-14 15:40:58'),(19221,12,'vsol',1781,'80:07:1b:ce:f9:d1','1781','80:07:1b:ce:f9:d1',NULL,NULL,0,'EPON 0/04','ONU 26',':',854.00,-21.80,'Power Off','2024-11-21 20:32:25','online','2026-01-14 15:40:59'),(19235,12,'vsol',2318,'3c:64:cf:7a:64:c1','2318','3c:64:cf:7a:64:c1',NULL,NULL,0,'EPON 0/05','ONU 18',':',1905.00,-16.78,'Power Off','2024-11-21 17:18:06','online','2026-01-14 15:41:00'),(19236,12,'vsol',2389,'cc:2d:21:d7:a2:f9','2389','cc:2d:21:d7:a2:f9',NULL,NULL,0,'EPON 0/05','',':',0.00,-8.74,'Power Off','2024-11-21 20:32:24','unknown','2026-01-14 14:31:06'),(19237,12,'vsol',1843,'28:87:ba:7c:a5:bb','1843','28:87:ba:7c:a5:bb',NULL,NULL,0,'EPON 0/05','ONU 24',':',1085.00,-20.27,'Power Off','2024-11-21 04:09:38','online','2026-01-14 15:41:00'),(19238,12,'vsol',2130,'04:95:e6:8a:9c:cf','2130','04:95:e6:8a:9c:cf',NULL,NULL,0,'EPON 0/05','',':',0.00,-25.85,'Power Off','2024-11-21 20:32:40','unknown','2026-01-14 14:31:06'),(19239,12,'vsol',2601,'08:40:f3:54:ed:1f','2601','08:40:f3:54:ed:1f',NULL,NULL,0,'EPON 0/05','ONU 59',':',562.00,-23.47,'Power Off','2024-11-18 17:42:45','online','2026-01-14 15:41:00'),(19240,12,'vsol',2322,'b8:3a:08:a7:4a:df','2322','b8:3a:08:a7:4a:df',NULL,NULL,0,'EPON 0/05','ONU 49',':',1819.00,-24.20,'Power Off','2024-11-21 17:17:47','online','2026-01-14 15:41:00'),(19241,12,'vsol',2215,'e4:fa:c4:7e:e2:8f','2215','e4:fa:c4:7e:e2:8f',NULL,NULL,0,'EPON 0/05','ONU 10',':',1221.00,-24.81,'Power Off','2024-11-21 20:32:41','online','2026-01-14 15:41:00'),(19242,12,'vsol',2054,'58:d9:d5:77:a5:c8','2054','58:d9:d5:77:a5:c8',NULL,NULL,0,'EPON 0/05','ONU 26',':',3190.00,-11.71,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:41:00'),(19243,12,'vsol',2514,'d8:32:14:3e:02:c0','2514','d8:32:14:3e:02:c0',NULL,NULL,0,'EPON 0/05','ONU 55',':',1864.00,-23.77,'Power Off','2024-11-21 20:33:03','online','2026-01-14 15:41:00'),(19244,12,'vsol',2059,'d8:32:14:15:1d:28','2059','d8:32:14:15:1d:28',NULL,NULL,0,'EPON 0/05','ONU 60',':',1842.00,-18.66,'Power Off','2024-11-21 20:32:34','online','2026-01-14 15:41:00'),(19245,12,'vsol',2574,'b8:3a:08:de:9e:68','2574','b8:3a:08:de:9e:68',NULL,NULL,0,'EPON 0/05','',':',0.00,-30.46,'Power Off','2024-11-21 20:32:29','unknown','2026-01-14 14:31:06'),(19246,12,'vsol',2103,'40:ed:00:50:9d:a3','2103','40:ed:00:50:9d:a3',NULL,NULL,0,'EPON 0/05','ONU 45',':',879.00,-21.74,'Power Off','2024-11-21 17:17:41','online','2026-01-14 15:41:00'),(19247,12,'vsol',2374,'cc:2d:21:5c:24:c0','2374','cc:2d:21:5c:24:c0',NULL,NULL,0,'EPON 0/05','ONU 44',':',1805.00,-25.69,'Power Off','2024-11-21 20:32:40','online','2026-01-14 15:41:00'),(19248,12,'vsol',2352,'80:af:ca:2e:ee:53','2352','80:af:ca:2e:ee:53',NULL,NULL,0,'EPON 0/05','ONU 22',':',1316.00,-26.02,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:41:00'),(19249,12,'vsol',2057,'5c:e9:31:59:df:87','2057','5c:e9:31:59:df:87',NULL,NULL,0,'EPON 0/05','ONU 41',':',1864.00,-17.38,'Power Off','2024-11-21 17:17:46','online','2026-01-14 15:41:00'),(19250,12,'vsol',2327,'40:ae:30:52:c7:be','2327','40:ae:30:52:c7:be',NULL,NULL,0,'EPON 0/05','ONU 7',':',1041.00,-15.99,'N/A',NULL,'online','2026-01-14 15:41:00'),(19251,12,'vsol',2055,'5c:a6:e6:a1:7d:87','2055','5c:a6:e6:a1:7d:87',NULL,NULL,0,'EPON 0/05','ONU 40',':',1751.00,-21.94,'Power Off','2024-11-21 17:17:57','online','2026-01-14 15:41:00'),(19252,12,'vsol',1882,'bc:e0:01:27:4c:fc','1882','bc:e0:01:27:4c:fc',NULL,NULL,0,'EPON 0/05','ONU 39',':',1034.00,-15.65,'Power Off','2024-11-21 20:32:29','online','2026-01-14 15:41:00'),(19253,12,'vsol',2344,'b0:19:21:12:35:75','2344','b0:19:21:12:35:75',NULL,NULL,0,'EPON 0/05','ONU 33',':',583.00,-24.81,'N/A',NULL,'online','2026-01-14 15:41:00'),(19254,12,'vsol',2627,'e8:65:d4:27:5e:0c','2627','e8:65:d4:27:5e:0c',NULL,NULL,0,'EPON 0/05','',':',0.00,-24.32,'Power Off','2024-11-21 20:32:39','unknown','2026-01-14 14:31:07'),(19255,12,'vsol',2587,'cc:2d:21:05:5f:70','2587','cc:2d:21:05:5f:70',NULL,NULL,0,'EPON 0/05','ONU 27',':',1788.00,-22.68,'Power Off','2024-11-21 17:17:49','online','2026-01-14 15:41:00'),(19256,12,'vsol',2545,'d8:32:14:36:91:27','2545','d8:32:14:36:91:27',NULL,NULL,0,'EPON 0/05','ONU 3',':',1841.00,-15.93,'Power Off','2024-11-21 18:22:02','online','2026-01-14 15:41:00'),(19257,12,'vsol',1778,'08:40:f3:c2:1b:00','1778','08:40:f3:c2:1b:00',NULL,NULL,0,'EPON 0/05','',':',0.00,-25.69,'Wire Down','2024-11-18 23:25:33','unknown','2026-01-14 14:31:07'),(19258,12,'vsol',2023,'20:23:51:74:16:e1','2023','20:23:51:74:16:e1',NULL,NULL,0,'EPON 0/05','',':',0.00,-24.95,'Power Off','2024-11-21 19:29:08','unknown','2026-01-14 14:31:07'),(19259,12,'vsol',2352,'a0:7e:09:17:78:11','2352','a0:7e:09:17:78:11',NULL,NULL,0,'EPON 0/05','',':',0.00,-26.02,'Power Off','2024-11-21 20:32:15','unknown','2026-01-14 15:40:59'),(19260,12,'vsol',2286,'98:da:c4:cf:28:c3','2286','98:da:c4:cf:28:c3',NULL,NULL,0,'EPON 0/05','ONU 56',':',975.00,-27.96,'Power Off','2024-11-21 17:18:18','online','2026-01-14 15:41:00'),(19261,12,'vsol',2455,'ec:75:0c:14:fa:14','2455','ec:75:0c:14:fa:14',NULL,NULL,0,'EPON 0/05','',':',0.00,-27.21,'Power Off','2024-11-21 20:32:21','unknown','2026-01-14 14:31:07'),(19262,12,'vsol',2409,'54:48:e6:9f:23:ea','2409','54:48:e6:9f:23:ea',NULL,NULL,0,'EPON 0/05','ONU 9',':',274.00,-23.98,'Power Off','2024-11-21 20:32:51','online','2026-01-14 15:41:00'),(19263,12,'vsol',2036,'c0:06:c3:11:0b:ef','2036','c0:06:c3:11:0b:ef',NULL,NULL,0,'EPON 0/05','ONU 5',':',1052.00,-11.76,'N/A',NULL,'online','2026-01-14 15:41:00'),(19264,12,'vsol',2155,'b8:3a:08:3c:f6:3f','2155','b8:3a:08:3c:f6:3f',NULL,NULL,0,'EPON 0/05','ONU 32',':',1772.00,-21.02,'Power Off','2024-11-21 17:17:42','online','2026-01-14 15:41:00'),(19265,12,'vsol',1775,'80:3f:5d:63:50:38','1775','80:3f:5d:63:50:38',NULL,NULL,0,'EPON 0/05','ONU 29',':',515.00,-28.86,'Power Off','2024-11-21 20:32:36','online','2026-01-14 15:41:00'),(19266,12,'vsol',2273,'d8:32:14:dd:55:d0','2273','d8:32:14:dd:55:d0',NULL,NULL,0,'EPON 0/05','ONU 34',':',3172.00,-26.20,'Power Off','2024-11-21 20:32:17','online','2026-01-14 15:41:00'),(19267,12,'vsol',1777,'84:d8:1b:07:1e:7b','1777','84:d8:1b:07:1e:7b',NULL,NULL,0,'EPON 0/05','ONU 17',':',1015.00,-19.96,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:41:00'),(19268,12,'vsol',2208,'d8:32:14:a0:28:d0','2208','d8:32:14:a0:28:d0',NULL,NULL,0,'EPON 0/05','',':',0.00,-27.45,'Power Off','2024-11-21 20:32:18','unknown','2026-01-14 14:31:07'),(19269,12,'vsol',2492,'b8:3a:08:1c:38:98','2492','b8:3a:08:1c:38:98',NULL,NULL,0,'EPON 0/05','ONU 19',':',1349.00,-19.32,'Power Off','2024-11-21 20:33:05','online','2026-01-14 14:09:33'),(19270,12,'vsol',2580,'88:bd:09:4f:bc:38','2580','88:bd:09:4f:bc:38',NULL,NULL,0,'EPON 0/05','',':',0.00,-28.24,'Power Off','2024-11-21 20:33:09','unknown','2026-01-14 14:31:07'),(19271,12,'vsol',1776,'98:03:8e:e9:02:af','1776','98:03:8e:e9:02:af',NULL,NULL,0,'EPON 0/05','ONU 43',':',1005.00,-24.09,'Power Off','2024-11-21 20:33:07','online','2026-01-14 15:41:00'),(19272,12,'vsol',2263,'d8:32:14:09:a6:f0','2263','d8:32:14:09:a6:f0',NULL,NULL,0,'EPON 0/05','ONU 23',':',1560.00,-23.47,'Power Off','2024-11-21 20:32:38','online','2026-01-14 15:41:00'),(19273,12,'vsol',1779,'d8:32:14:87:51:e8','1779','d8:32:14:87:51:e8',NULL,NULL,0,'EPON 0/05','',':',0.00,-29.59,'Power Off','2024-11-21 20:33:00','unknown','2026-01-14 14:31:07'),(19274,12,'vsol',1890,'50:0f:f5:79:a2:b8','1890','50:0f:f5:79:a2:b8',NULL,NULL,0,'EPON 0/05','',':',0.00,-16.25,'Power Off','2024-11-21 20:32:19','unknown','2026-01-14 14:31:07'),(19275,12,'vsol',2142,'04:95:e6:93:ed:20','2142','04:95:e6:93:ed:20',NULL,NULL,0,'EPON 0/05','ONU 31',':',639.00,-22.92,'Power Off','2024-11-21 20:32:58','online','2026-01-14 15:41:00'),(19276,12,'vsol',2028,'58:d9:d5:25:6f:80','2028','58:d9:d5:25:6f:80',NULL,NULL,0,'EPON 0/05','',':',0.00,-22.68,'Power Off','2024-11-21 20:32:17','unknown','2026-01-14 14:31:07'),(19277,12,'vsol',1774,'d8:07:b6:51:48:eb','1774','d8:07:b6:51:48:eb',NULL,NULL,0,'EPON 0/05','ONU 38',':',359.00,-23.28,'Power Off','2024-11-21 20:32:40','online','2026-01-14 15:41:00'),(19278,12,'vsol',2149,'cc:2d:21:78:24:50','2149','cc:2d:21:78:24:50',NULL,NULL,0,'EPON 0/05','',':',0.00,-30.46,'Power Off','2024-11-21 20:32:17','unknown','2026-01-14 14:31:07'),(19279,12,'vsol',1910,'54:af:97:e1:bd:85','1910','54:af:97:e1:bd:85',NULL,NULL,0,'EPON 0/05','ONU 48',':',1046.00,-23.10,'Power Off','2024-11-21 20:32:32','online','2026-01-14 15:41:00'),(19280,12,'vsol',1880,'b4:0f:3b:eb:f0:00','1880','b4:0f:3b:eb:f0:00',NULL,NULL,0,'EPON 0/05','',':',0.00,-15.95,'Power Off','2024-11-21 20:32:56','unknown','2026-01-14 14:31:07'),(19281,12,'vsol',2182,'58:d9:d5:35:ff:90','2182','58:d9:d5:35:ff:90',NULL,NULL,0,'EPON 0/05','ONU 53',':',1254.00,-28.54,'Power Off','2024-11-21 20:32:38','online','2026-01-14 15:41:00'),(19282,12,'vsol',2037,'54:af:97:56:8c:e9','2037','54:af:97:56:8c:e9',NULL,NULL,0,'EPON 0/05','ONU 8',':',223.00,-17.59,'N/A',NULL,'online','2026-01-14 15:41:00'),(19283,12,'vsol',1893,'b4:0f:3b:c1:74:c0','1893','b4:0f:3b:c1:74:c0',NULL,NULL,0,'EPON 0/05','ONU 42',':',1024.00,-22.60,'Wire Down','2024-11-21 20:32:20','online','2026-01-14 15:41:00'),(19284,12,'vsol',2129,'cc:2d:21:4d:f5:d0','2129','cc:2d:21:4d:f5:d0',NULL,NULL,0,'EPON 0/05','ONU 37',':',1160.00,-21.19,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:41:00'),(19285,12,'vsol',1889,'04:5e:a4:df:ec:43','1889','04:5e:a4:df:ec:43',NULL,NULL,0,'EPON 0/05','ONU 6',':',692.00,-10.00,'Wire Down','2024-11-21 20:34:50','online','2026-01-14 15:41:00'),(19286,12,'vsol',1802,'60:a4:b7:95:7d:dc','1802','60:a4:b7:95:7d:dc',NULL,NULL,0,'EPON 0/05','ONU 12',':',657.00,-10.92,'Power Off','2024-11-20 17:15:43','online','2026-01-14 15:41:00'),(19287,12,'vsol',2101,'5c:62:8b:cf:75:a0','2101','5c:62:8b:cf:75:a0',NULL,NULL,0,'EPON 0/05','ONU 25',':',952.00,-14.03,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:41:00'),(19288,12,'vsol',2272,'a8:42:a1:c4:8e:df','2272','a8:42:a1:c4:8e:df',NULL,NULL,0,'EPON 0/05','ONU 13',':',424.00,-22.76,'N/A',NULL,'online','2026-01-14 15:41:00'),(19289,12,'vsol',2489,'b0:19:21:11:af:5c','2489','b0:19:21:11:af:5c',NULL,NULL,0,'EPON 0/05','ONU 30',':',533.00,-23.28,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:41:00'),(19290,12,'vsol',2121,'cc:2d:21:78:90:50','2121','cc:2d:21:78:90:50',NULL,NULL,0,'EPON 0/05','',':',0.00,-20.04,'Power Off','2024-11-21 20:32:21','unknown','2026-01-14 14:31:07'),(19291,12,'vsol',2207,'60:a4:b7:96:f4:8d','2207','60:a4:b7:96:f4:8d',NULL,NULL,0,'EPON 0/05','',':',0.00,-23.47,'Wire Down','2024-11-18 22:57:28','unknown','2026-01-14 14:31:07'),(19292,12,'vsol',2584,'cc:2d:21:30:81:20','2584','cc:2d:21:30:81:20',NULL,NULL,0,'EPON 0/05','',':',0.00,-21.67,'Power Off','2024-11-21 20:32:22','unknown','2026-01-14 14:31:08'),(19293,12,'vsol',2379,'5c:62:8b:e1:8b:93','2379','5c:62:8b:e1:8b:93',NULL,NULL,0,'EPON 0/05','ONU 21',':',1144.00,-22.52,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:41:00'),(19294,12,'vsol',2597,'b4:64:15:ed:d7:43','2597','b4:64:15:ed:d7:43',NULL,NULL,0,'EPON 0/05','ONU 57',':',295.00,-12.93,'Power Off','2024-11-21 20:32:48','online','2026-01-14 15:41:00'),(19295,12,'vsol',2347,'a8:6e:84:02:9d:61','2347','a8:6e:84:02:9d:61',NULL,NULL,0,'EPON 0/05','ONU 14',':',988.00,-17.19,'N/A',NULL,'online','2026-01-14 15:41:00'),(19303,12,'vsol',2129,'6c:68:a4:63:0d:b5','2129','6c:68:a4:63:0d:b5',NULL,NULL,0,'EPON 0/05','ONU 37',':',1160.00,-21.19,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:41:00'),(19358,12,'vsol',2467,'cc:2d:21:77:05:d0','2467','cc:2d:21:77:05:d0',NULL,NULL,0,'EPON 0/06','ONU 36',':',4269.00,-27.45,'Power Off','2024-11-20 19:44:02','online','2026-01-14 15:41:02'),(19359,12,'vsol',1898,'b4:0f:3b:eb:fe:50','1898','b4:0f:3b:eb:fe:50',NULL,NULL,0,'EPON 0/06','ONU 31',':',4834.00,-20.09,'Power Off','2024-11-20 19:44:01','online','2026-01-14 15:41:01'),(19360,12,'vsol',1894,'50:0f:f5:66:87:d0','1894','50:0f:f5:66:87:d0',NULL,NULL,0,'EPON 0/06','ONU 26',':',5008.00,-23.19,'Power Off','2024-11-20 19:44:03','online','2026-01-14 15:41:01'),(19361,12,'vsol',1824,'e8:65:d4:fc:be:a8','1824','e8:65:d4:fc:be:a8',NULL,NULL,0,'EPON 0/06','ONU 12',':',3993.00,-22.01,'Power Off','2024-11-20 19:44:02','online','2026-01-14 15:41:02'),(19362,12,'vsol',2520,'b8:3a:08:80:69:c8','2520','b8:3a:08:80:69:c8',NULL,NULL,0,'EPON 0/06','ONU 21',':',4729.00,-19.83,'Power Off','2024-11-20 19:46:42','online','2026-01-14 15:41:02'),(19363,12,'vsol',2080,'c0:25:2f:a1:97:d3','2080','c0:25:2f:a1:97:d3',NULL,NULL,0,'EPON 0/06','ONU 39',':',4565.00,-19.91,'Power Off','2024-11-20 19:44:03','online','2026-01-14 15:41:02'),(19364,12,'vsol',2216,'b8:3a:08:01:f6:48','2216','b8:3a:08:01:f6:48',NULL,NULL,0,'EPON 0/06','ONU 14',':',3572.00,-7.95,'Power Off','2024-11-21 17:17:30','online','2026-01-14 15:41:01'),(19365,12,'vsol',2557,'e8:65:d4:27:8e:70','2557','e8:65:d4:27:8e:70',NULL,NULL,0,'EPON 0/06','ONU 18',':',3928.00,-24.32,'Power Off','2024-11-21 20:37:10','online','2026-01-14 15:41:01'),(19366,12,'vsol',2430,'dc:8e:8d:58:e3:5c','2430','dc:8e:8d:58:e3:5c',NULL,NULL,0,'EPON 0/06','ONU 7',':',1375.00,-9.36,'Power Off','2024-11-18 04:18:32','online','2026-01-14 15:41:01'),(19367,12,'vsol',1915,'58:d9:d5:27:fd:47','1915','58:d9:d5:27:fd:47',NULL,NULL,0,'EPON 0/06','ONU 11',':',4142.00,-25.53,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:02'),(19368,12,'vsol',1984,'04:5e:a4:28:df:bc','1984','04:5e:a4:28:df:bc',NULL,NULL,0,'EPON 0/06','ONU 30',':',4746.00,-22.44,'Power Off','2024-11-20 19:44:02','online','2026-01-14 15:41:02'),(19369,12,'vsol',2165,'bc:e0:01:79:5a:34','2165','bc:e0:01:79:5a:34',NULL,NULL,0,'EPON 0/06','ONU 19',':',1147.00,-20.41,'Power Off','2024-11-21 17:18:31','online','2026-01-14 15:41:01'),(19370,12,'vsol',2301,'88:bd:09:16:94:2a','2301','88:bd:09:16:94:2a',NULL,NULL,0,'EPON 0/06','ONU 3',':',1290.00,-9.53,'Wire Down','2024-11-21 17:18:00','online','2026-01-14 15:41:02'),(19371,12,'vsol',2593,'b8:3a:08:3c:f6:57','2593','b8:3a:08:3c:f6:57',NULL,NULL,0,'EPON 0/06','ONU 17',':',4316.00,-23.98,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:01'),(19372,12,'vsol',2122,'d4:6e:0e:75:05:5b','2122','d4:6e:0e:75:05:5b',NULL,NULL,0,'EPON 0/06','ONU 16',':',1372.00,-26.99,'Wire Down','2024-11-20 14:55:33','online','2026-01-13 21:31:12'),(19373,12,'vsol',2284,'40:ae:30:b0:db:2b','2284','40:ae:30:b0:db:2b',NULL,NULL,0,'EPON 0/06','ONU 6',':',1224.00,-10.58,'Power Off','2024-11-21 17:18:06','online','2026-01-14 15:41:01'),(19374,12,'vsol',2366,'58:d9:d5:aa:e0:00','2366','58:d9:d5:aa:e0:00',NULL,NULL,0,'EPON 0/06','ONU 20',':',6857.00,-22.84,'Power Off','2024-11-20 19:43:59','online','2026-01-14 15:41:01'),(19375,12,'vsol',2108,'40:ed:00:8b:57:c7','2108','40:ed:00:8b:57:c7',NULL,NULL,0,'EPON 0/06','ONU 8',':',1206.00,-7.90,'Power Off','2024-11-21 17:17:53','online','2026-01-14 15:41:02'),(19376,12,'vsol',2520,'6c:68:a4:c6:82:f1','2520','6c:68:a4:c6:82:f1',306,'2026-01-14 15:29:11',0,'EPON 0/06','ONU 21',':',4729.00,-19.83,'Power Off','2024-11-20 19:46:42','online','2026-01-14 15:29:20'),(19377,12,'vsol',1872,'d8:32:14:42:c8:20','1872','d8:32:14:42:c8:20',NULL,NULL,0,'EPON 0/06','ONU 34',':',4783.00,-21.74,'Power Off','2024-11-20 19:44:03','online','2026-01-14 15:41:01'),(19378,12,'vsol',2084,'c0:25:2f:a1:98:27','2084','c0:25:2f:a1:98:27',NULL,NULL,0,'EPON 0/06','ONU 4',':',4013.00,-22.01,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:01'),(19379,12,'vsol',1825,'cc:2d:21:76:fe:68','1825','cc:2d:21:76:fe:68',NULL,NULL,0,'EPON 0/06','ONU 30',':',4746.00,-22.44,'Power Off','2024-11-20 19:44:02','online','2026-01-14 15:41:02'),(19380,12,'vsol',2134,'dc:8e:8d:68:fc:49','2134','dc:8e:8d:68:fc:49',NULL,NULL,0,'EPON 0/06','ONU 22',':',6255.00,-22.76,'Power Off','2024-11-21 20:37:08','online','2026-01-14 15:41:01'),(19381,12,'vsol',2220,'04:5e:a4:c5:27:9f','2220','04:5e:a4:c5:27:9f',NULL,NULL,0,'EPON 0/06','ONU 25',':',4842.00,-18.66,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:01'),(19382,12,'vsol',2087,'40:ed:00:f0:95:69','2087','40:ed:00:f0:95:69',NULL,NULL,0,'EPON 0/06','ONU 29',':',1218.00,-10.00,'Wire Down','2024-11-20 16:34:14','online','2026-01-14 15:41:01'),(19383,12,'vsol',2230,'b8:3a:08:01:f8:18','2230','b8:3a:08:01:f8:18',NULL,NULL,0,'EPON 0/06','ONU 13',':',1311.00,-21.74,'Power Off','2024-11-21 17:17:36','online','2026-01-14 15:41:01'),(19384,12,'vsol',1828,'e8:65:d4:fc:e2:48','1828','e8:65:d4:fc:e2:48',NULL,NULL,0,'EPON 0/06','ONU 24',':',4228.00,-18.60,'Wire Down','2024-11-21 20:37:11','online','2026-01-14 15:41:02'),(19385,12,'vsol',2353,'74:fe:ce:04:dc:39','2353','74:fe:ce:04:dc:39',NULL,NULL,0,'EPON 0/06','ONU 2',':',1239.00,-4.20,'Wire Down','2024-11-14 20:28:25','online','2026-01-14 15:41:01'),(19386,12,'vsol',1900,'50:0f:f5:dd:4d:a8','1900','50:0f:f5:dd:4d:a8',NULL,NULL,0,'EPON 0/06','ONU 33',':',3629.00,-24.20,'Power Off','2024-11-21 20:37:11','online','2026-01-14 15:41:01'),(19387,12,'vsol',2554,'dc:8e:8d:4b:ce:40','2554','dc:8e:8d:4b:ce:40',NULL,NULL,0,'EPON 0/06','ONU 28',':',3670.00,-20.46,'Power Off','2024-11-21 20:37:08','online','2026-01-14 15:41:02'),(19388,12,'vsol',2177,'dc:8e:8d:06:67:ef','2177','dc:8e:8d:06:67:ef',NULL,NULL,0,'EPON 0/06','ONU 15',':',4795.00,-20.36,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:02'),(19389,12,'vsol',2338,'60:83:e7:2d:0c:9a','2338','60:83:e7:2d:0c:9a',NULL,NULL,0,'EPON 0/06','ONU 1',':',1247.00,-13.70,'Power Off','2024-11-21 17:17:54','online','2026-01-14 15:41:01'),(19390,12,'vsol',2087,'6c:68:a4:44:f4:85','2087','6c:68:a4:44:f4:85',NULL,NULL,0,'EPON 0/06','ONU 29',':',1218.00,-10.00,'Wire Down','2024-11-20 16:34:14','online','2026-01-14 15:41:01'),(19391,12,'vsol',2013,'50:d4:f7:bd:fa:bb','2013','50:d4:f7:bd:fa:bb',NULL,NULL,0,'EPON 0/06','ONU 35',':',4147.00,-23.47,'Power Off','2024-11-20 19:44:02','online','2026-01-14 15:41:01'),(19392,12,'vsol',1827,'c0:25:2f:a1:97:f5','1827','c0:25:2f:a1:97:f5',NULL,NULL,0,'EPON 0/06','ONU 27',':',5708.00,-16.48,'Power Off','2024-11-21 20:37:08','online','2026-01-14 15:41:02'),(19393,12,'vsol',2014,'c0:25:2f:a1:98:1b','2014','c0:25:2f:a1:98:1b',NULL,NULL,0,'EPON 0/06','ONU 11',':',4142.00,-25.53,'Power Off','2024-11-20 19:44:04','online','2026-01-14 15:41:01'),(19394,12,'vsol',2267,'cc:2d:21:62:64:10','2267','cc:2d:21:62:64:10',NULL,NULL,0,'EPON 0/06','ONU 23',':',6641.00,-20.76,'Power Off','2024-11-20 19:43:59','online','2026-01-14 15:41:02'),(19395,12,'vsol',2302,'88:bd:09:16:93:d7','2302','88:bd:09:16:93:d7',NULL,NULL,0,'EPON 0/06','ONU 10',':',1367.00,-4.82,'Wire Down','2024-11-21 17:23:46','online','2026-01-14 15:41:02'),(19396,12,'vsol',2364,'60:83:e7:61:19:9d','2364','60:83:e7:61:19:9d',NULL,NULL,0,'EPON 0/06','ONU 42',':',1744.00,-20.66,'Power Off','2024-11-21 17:18:22','online','2026-01-14 15:41:01'),(19397,12,'vsol',2614,'c0:25:2f:a1:97:a1','2614','c0:25:2f:a1:97:a1',NULL,NULL,0,'EPON 0/06','ONU 41',':',3783.00,-22.84,'Power Off','2024-11-21 20:37:11','online','2026-01-14 15:41:02'),(19398,12,'vsol',2472,'ec:75:0c:c1:63:31','2472','ec:75:0c:c1:63:31',NULL,NULL,0,'EPON 0/06','ONU 37',':',4213.00,-21.19,'Power Off','2024-11-21 20:37:10','online','2026-01-14 15:41:01'),(19399,12,'vsol',2365,'50:0f:f5:da:2a:58','2365','50:0f:f5:da:2a:58',NULL,NULL,0,'EPON 0/06','ONU 38',':',3872.00,-24.81,'Power Off','2024-11-21 20:37:11','online','2026-01-14 15:41:01'),(19400,12,'vsol',2336,'64:64:4a:27:05:26','2336','64:64:4a:27:05:26',NULL,NULL,0,'EPON 0/06','ONU 32',':',3898.00,-18.07,'Power Off','2024-11-21 20:37:12','online','2026-01-14 15:41:02'),(19443,12,'vsol',2338,'a0:7e:08:06:63:e0','2338','a0:7e:08:06:63:e0',NULL,NULL,0,'EPON 0/06','ONU 1','',1247.00,NULL,'Wire Down','2024-11-21 17:17:54','offline','2026-01-14 13:38:15'),(19444,12,'vsol',2450,'50:0f:f5:08:23:5f','2450','50:0f:f5:08:23:5f',NULL,NULL,0,'EPON 0/07','ONU 17',':',2200.00,-27.45,'Power Off','2024-11-21 17:22:44','online','2026-01-14 15:41:03'),(19445,12,'vsol',2359,'b8:3a:08:f8:a3:20','2359','b8:3a:08:f8:a3:20',NULL,NULL,0,'EPON 0/07','ONU 9',':',1983.00,-23.57,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:03'),(19446,12,'vsol',1876,'18:fd:74:dd:1a:fd','1876','18:fd:74:dd:1a:fd',NULL,NULL,0,'EPON 0/07','ONU 32',':',2674.00,-20.09,'Power Off','2024-11-21 02:05:59','online','2026-01-14 15:41:03'),(19447,12,'vsol',2491,'7c:f1:7e:b2:59:7c','2491','7c:f1:7e:b2:59:7c',NULL,NULL,0,'EPON 0/07','ONU 6',':',641.00,-22.37,'Wire Down','2024-11-13 22:02:32','online','2026-01-14 15:41:02'),(19448,12,'vsol',2444,'4c:d7:c8:a1:79:05','2444','4c:d7:c8:a1:79:05',NULL,NULL,0,'EPON 0/07','ONU 22',':',1057.00,-23.67,'Wire Down','2024-11-13 22:54:04','online','2026-01-14 15:41:02'),(19449,12,'vsol',1809,'04:5e:a4:ea:92:47','1809','04:5e:a4:ea:92:47',NULL,NULL,0,'EPON 0/07','ONU 3',':',2044.00,-20.13,'Power Off','2024-11-21 17:22:46','online','2026-01-14 15:41:02'),(19450,12,'vsol',2005,'dc:8e:8d:05:c8:e7','2005','dc:8e:8d:05:c8:e7',NULL,NULL,0,'EPON 0/07','ONU 29',':',3974.00,-21.02,'Power Off','2024-11-21 17:22:41','online','2026-01-14 15:41:02'),(19451,12,'vsol',2269,'e4:fa:c4:f1:87:c7','2269','e4:fa:c4:f1:87:c7',NULL,NULL,0,'EPON 0/07','ONU 25',':',2010.00,-19.43,'Power Off','2024-11-21 17:22:47','online','2026-01-14 15:41:03'),(19452,12,'vsol',2314,'d8:32:14:a0:14:d0','2314','d8:32:14:a0:14:d0',NULL,NULL,0,'EPON 0/07','ONU 28',':',1865.00,-9.85,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:03'),(19453,12,'vsol',2625,'b4:64:15:ed:c7:93','2625','b4:64:15:ed:c7:93',NULL,NULL,0,'EPON 0/07','ONU 34',':',693.00,-21.43,'Power Off','2024-11-21 17:17:52','online','2026-01-14 15:41:02'),(19454,12,'vsol',2178,'b4:0f:3b:e9:0e:49','2178','b4:0f:3b:e9:0e:49',NULL,NULL,0,'EPON 0/07','ONU 10',':',2275.00,-15.45,'Power Off','2024-11-21 17:22:47','online','2026-01-14 15:41:02'),(19455,12,'vsol',2049,'58:d9:d5:77:a5:b8','2049','58:d9:d5:77:a5:b8',NULL,NULL,0,'EPON 0/07','ONU 23',':',3270.00,-21.02,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:41:03'),(19456,12,'vsol',1869,'50:0f:f5:be:97:e0','1869','50:0f:f5:be:97:e0',NULL,NULL,0,'EPON 0/07','ONU 16',':',2203.00,-17.45,'Power Off','2024-11-21 17:22:50','online','2026-01-14 15:41:03'),(19457,12,'vsol',1845,'1c:61:b4:63:37:09','1845','1c:61:b4:63:37:09',NULL,NULL,0,'EPON 0/07','ONU 4',':',1790.00,-10.79,'Power Off','2024-11-21 17:22:50','online','2026-01-14 15:41:02'),(19458,12,'vsol',2157,'28:ee:52:fd:a9:8b','2157','28:ee:52:fd:a9:8b',NULL,NULL,0,'EPON 0/07','ONU 24',':',4339.00,-29.59,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:02'),(19459,12,'vsol',1895,'1c:61:b4:3a:ce:c2','1895','1c:61:b4:3a:ce:c2',NULL,NULL,0,'EPON 0/07','ONU 18',':',2510.00,-20.27,'Power Off','2024-11-21 17:22:49','online','2026-01-14 15:41:03'),(19460,12,'vsol',2132,'3c:52:a1:9b:72:cb','2132','3c:52:a1:9b:72:cb',NULL,NULL,0,'EPON 0/07','ONU 30',':',2280.00,-27.70,'Wire Down','2024-11-15 19:17:06','online','2026-01-14 15:41:02'),(19461,12,'vsol',2314,'4c:d7:c8:e7:e9:fd','2314','4c:d7:c8:e7:e9:fd',NULL,NULL,0,'EPON 0/07','ONU 28',':',1865.00,-9.85,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:02'),(19462,12,'vsol',2359,'a0:7e:09:17:18:31','2359','a0:7e:09:17:18:31',NULL,NULL,0,'EPON 0/07','ONU 9',':',1983.00,-23.57,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:03'),(19463,12,'vsol',2532,'b8:3a:08:91:85:77','2532','b8:3a:08:91:85:77',NULL,NULL,0,'EPON 0/07','ONU 1',':',2239.00,-23.57,'Power Off','2024-11-21 20:43:17','online','2026-01-14 15:41:03'),(19464,12,'vsol',2367,'b8:3a:08:8e:5e:97','2367','b8:3a:08:8e:5e:97',NULL,NULL,0,'EPON 0/07','ONU 8',':',2147.00,-25.23,'Power Off','2024-11-21 17:22:45','online','2026-01-14 15:41:03'),(19465,12,'vsol',2578,'d8:32:14:67:4b:19','2578','d8:32:14:67:4b:19',NULL,NULL,0,'EPON 0/07','ONU 14',':',1985.00,-19.17,'Power Off','2024-11-21 17:22:48','online','2026-01-14 15:41:03'),(19466,12,'vsol',2547,'d8:32:14:3e:fa:df','2547','d8:32:14:3e:fa:df',NULL,NULL,0,'EPON 0/07','ONU 21',':',2203.00,-22.15,'Power Off','2024-11-21 17:22:45','online','2026-01-14 15:41:02'),(19467,12,'vsol',2503,'cc:2d:21:04:7e:20','2503','cc:2d:21:04:7e:20',NULL,NULL,0,'EPON 0/07','ONU 31',':',2026.00,-21.80,'Power Off','2024-11-21 17:22:51','online','2026-01-14 15:41:03'),(19468,12,'vsol',2343,'e8:65:d4:fc:40:b0','2343','e8:65:d4:fc:40:b0',NULL,NULL,0,'EPON 0/07','ONU 2',':',913.00,-10.77,'Power Off','2024-11-21 20:32:42','online','2026-01-14 15:41:02'),(19469,12,'vsol',2004,'9c:53:22:43:ce:65','2004','9c:53:22:43:ce:65',NULL,NULL,0,'EPON 0/07','ONU 27',':',2200.00,-23.01,'Power Off','2024-11-02 07:45:17','online','2026-01-14 15:41:03'),(19470,12,'vsol',2559,'08:40:f3:a3:1e:90','2559','08:40:f3:a3:1e:90',NULL,NULL,0,'EPON 0/07','ONU 15',':',2223.00,-19.32,'Power Off','2024-11-21 17:22:46','online','2026-01-14 15:41:03'),(19471,12,'vsol',1818,'e8:65:d4:fc:93:a8','1818','e8:65:d4:fc:93:a8',NULL,NULL,0,'EPON 0/07','ONU 13',':',2119.00,-23.77,'Power Off','2024-11-21 17:22:47','online','2026-01-14 15:41:03'),(19472,12,'vsol',2600,'c0:25:2f:5c:a6:dd','2600','c0:25:2f:5c:a6:dd',NULL,NULL,0,'EPON 0/07','ONU 33',':',2126.00,-20.60,'Power Off','2024-11-21 17:22:45','online','2026-01-14 15:41:03'),(19473,12,'vsol',2506,'88:bd:09:50:cb:15','2506','88:bd:09:50:cb:15',NULL,NULL,0,'EPON 0/07','ONU 20',':',2241.00,-27.70,'Power Off','2024-11-21 17:22:44','online','2026-01-14 15:41:03'),(19474,12,'vsol',2226,'50:0f:f5:a7:be:c8','2226','50:0f:f5:a7:be:c8',NULL,NULL,0,'EPON 0/07','ONU 5',':',567.00,-17.42,'Power Off','2024-11-21 20:32:56','online','2026-01-14 15:41:02'),(19475,12,'vsol',1801,'60:83:e7:60:d1:48','1801','60:83:e7:60:d1:48',NULL,NULL,0,'EPON 0/07','ONU 12',':',1828.00,-11.82,'Power Off','2024-11-20 00:13:10','online','2026-01-14 15:41:02'),(19476,12,'vsol',2444,'70:4f:57:a2:61:d7','2444','70:4f:57:a2:61:d7',NULL,NULL,0,'EPON 0/07','ONU 22',':',1057.00,-23.67,'Wire Down','2024-11-13 22:54:04','online','2026-01-14 15:41:02'),(19477,12,'vsol',2018,'8c:de:f9:dc:5c:ac','2018','8c:de:f9:dc:5c:ac',NULL,NULL,0,'EPON 0/07','ONU 26',':',720.00,-23.77,'Power Off','2024-11-21 20:32:36','online','2026-01-14 15:41:02'),(19512,12,'vsol',2309,'04:95:e6:ef:69:87','2309','04:95:e6:ef:69:87',NULL,NULL,0,'EPON 0/08','ONU 21',':',769.00,-16.22,'Power Off','2024-11-21 20:32:26','online','2026-01-14 15:41:04'),(19513,12,'vsol',2585,'d8:32:14:36:91:17','2585','d8:32:14:36:91:17',NULL,NULL,0,'EPON 0/08','',':',0.00,-23.28,'Power Off','2024-11-21 00:23:29','unknown','2026-01-13 20:38:09'),(19514,12,'vsol',2310,'b0:19:21:11:ef:37','2310','b0:19:21:11:ef:37',NULL,NULL,0,'EPON 0/08','ONU 26',':',3039.00,-15.77,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(19515,12,'vsol',1863,'c4:70:0b:5d:3d:e1','1863','c4:70:0b:5d:3d:e1',NULL,NULL,0,'EPON 0/08','ONU 23',':',933.00,-21.94,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:41:04'),(19516,12,'vsol',2256,'6c:68:a4:9f:c1:c5','2256','6c:68:a4:9f:c1:c5',NULL,NULL,0,'EPON 0/08','',':',0.00,-13.52,'Power Off','2024-11-21 17:22:42','unknown','2026-01-14 15:41:03'),(19517,12,'vsol',1840,'34:60:f9:52:ed:9f','1840','34:60:f9:52:ed:9f',NULL,NULL,0,'EPON 0/08','ONU 6','5180-Sorif',1593.00,-17.72,'Wire Down','2024-11-21 20:32:29','online','2026-01-14 15:41:04'),(19518,12,'vsol',2175,'64:64:4a:e1:a3:36','2175','64:64:4a:e1:a3:36',NULL,NULL,0,'EPON 0/08','ONU 27',':',1710.00,-22.08,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(19519,12,'vsol',2245,'dc:8e:8d:3b:06:f1','2245','dc:8e:8d:3b:06:f1',NULL,NULL,0,'EPON 0/08','ONU 10',':',2944.00,-2.58,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:41:04'),(19520,12,'vsol',2256,'28:87:ba:f4:d6:6c','2256','28:87:ba:f4:d6:6c',NULL,NULL,0,'EPON 0/08','ONU 16',':',2862.00,-13.52,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(19521,12,'vsol',2241,'dc:8e:8d:2f:42:e3','2241','dc:8e:8d:2f:42:e3',NULL,NULL,0,'EPON 0/08','ONU 8',':',1726.00,-16.31,'Power Off','2024-11-21 17:22:44','online','2026-01-14 15:41:04'),(19522,12,'vsol',2225,'d8:32:14:42:f3:d7','2225','d8:32:14:42:f3:d7',NULL,NULL,0,'EPON 0/08','ONU 29',':',920.00,-19.03,'Power Off','2024-11-21 17:17:44','online','2026-01-14 15:41:04'),(19523,12,'vsol',2626,'78:20:51:e3:56:b3','2626','78:20:51:e3:56:b3',NULL,NULL,0,'EPON 0/08','ONU 41',':',849.00,-21.31,'Power Off','2024-11-21 20:32:34','online','2026-01-14 15:41:04'),(19524,12,'vsol',2089,'58:d5:6e:d1:6e:73','2089','58:d5:6e:d1:6e:73',NULL,NULL,0,'EPON 0/08','ONU 15',':',1023.00,-20.51,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:41:04'),(19525,12,'vsol',1820,'b0:19:21:03:49:49','1820','b0:19:21:03:49:49',NULL,NULL,0,'EPON 0/08','ONU 20',':',908.00,-26.99,'N/A',NULL,'online','2026-01-14 15:41:04'),(19526,12,'vsol',2539,'d8:32:14:3e:02:b8','2539','d8:32:14:3e:02:b8',NULL,NULL,0,'EPON 0/08','ONU 38',':',983.00,-19.28,'Power Off','2024-11-21 17:18:10','online','2026-01-14 15:41:04'),(19527,12,'vsol',2255,'20:23:51:67:a4:ef','2255','20:23:51:67:a4:ef',NULL,NULL,0,'EPON 0/08','ONU 34',':',2944.00,-13.62,'Power Off','2024-11-21 17:22:47','online','2026-01-14 15:41:04'),(19528,12,'vsol',2582,'8c:86:dd:41:f3:08','2582','8c:86:dd:41:f3:08',NULL,NULL,0,'EPON 0/08','ONU 42',':',806.00,-19.21,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:41:04'),(19529,12,'vsol',2316,'b0:19:21:12:32:c3','2316','b0:19:21:12:32:c3',NULL,NULL,0,'EPON 0/08','ONU 25',':',2472.00,-7.65,'Power Off','2024-11-08 14:49:36','online','2026-01-14 15:41:04'),(19530,12,'vsol',2526,'d8:32:14:df:7a:30','2526','d8:32:14:df:7a:30',NULL,NULL,0,'EPON 0/08','ONU 18','5634-Halim',2988.00,-12.86,'Power Off','2024-11-21 17:22:49','online','2026-01-14 15:41:04'),(19531,12,'vsol',2299,'b8:3a:08:a7:4a:bf','2299','b8:3a:08:a7:4a:bf',NULL,NULL,0,'EPON 0/08','ONU 37',':',1746.00,-17.70,'Power Off','2024-11-21 17:22:48','online','2026-01-14 15:41:04'),(19532,12,'vsol',2126,'e4:fa:c4:6b:e1:cd','2126','e4:fa:c4:6b:e1:cd',NULL,NULL,0,'EPON 0/08','ONU 4','5350-Rajamehar-ISS',1582.00,-15.70,'Power Off','2024-11-20 18:29:21','online','2026-01-14 15:41:04'),(19533,12,'vsol',2583,'cc:2d:21:04:b9:20','2583','cc:2d:21:04:b9:20',NULL,NULL,0,'EPON 0/08','ONU 2','5676-Fahem-Mer',1036.00,-22.01,'Power Off','2024-11-21 17:17:45','online','2026-01-14 15:41:04'),(19534,12,'vsol',1947,'40:ed:00:3e:46:24','1947','40:ed:00:3e:46:24',NULL,NULL,0,'EPON 0/08','ONU 11','5033-Rashid',2610.00,-3.03,'Power Off','2024-11-21 17:22:46','online','2026-01-14 15:41:04'),(19535,12,'vsol',1863,'d8:32:14:c0:5a:b0','1863','d8:32:14:c0:5a:b0',NULL,NULL,0,'EPON 0/08','ONU 23',':',933.00,-21.94,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:41:04'),(19536,12,'vsol',1853,'08:8a:f1:11:22:fe','1853','08:8a:f1:11:22:fe',NULL,NULL,0,'EPON 0/08','ONU 12',':',916.00,-22.68,'Power Off','2024-11-14 00:08:07','online','2026-01-14 15:41:04'),(19537,12,'vsol',2209,'98:25:4a:85:6d:ad','2209','98:25:4a:85:6d:ad',NULL,NULL,0,'EPON 0/08','ONU 32',':',1706.00,-15.36,'Power Off','2024-11-21 17:22:44','online','2026-01-14 15:41:04'),(19538,12,'vsol',1838,'64:64:4a:3e:e2:98','1838','64:64:4a:3e:e2:98',NULL,NULL,0,'EPON 0/08','',':',0.00,-17.06,'Power Off','2024-11-21 19:28:57','unknown','2026-01-14 13:38:18'),(19539,12,'vsol',2334,'d8:32:14:12:89:98','2334','d8:32:14:12:89:98',NULL,NULL,0,'EPON 0/08','ONU 13',':',954.00,-36.99,'Power Off','2024-11-21 20:32:31','online','2026-01-14 15:41:04'),(19540,12,'vsol',2394,'4c:d7:c8:be:42:29','2394','4c:d7:c8:be:42:29',NULL,NULL,0,'EPON 0/08','ONU 35',':',972.00,-23.57,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:41:04'),(19541,12,'vsol',1783,'60:32:b1:8a:65:67','1783','60:32:b1:8a:65:67',NULL,NULL,0,'EPON 0/08','ONU 19',':',979.00,-20.32,'Power Off','2024-11-21 20:33:00','online','2026-01-14 15:41:04'),(19542,12,'vsol',2194,'50:d4:f7:3c:43:59','2194','50:d4:f7:3c:43:59',NULL,NULL,0,'EPON 0/08','ONU 1','5401-Nazmul',1157.00,-10.64,'Wire Down','2024-11-21 20:40:16','online','2026-01-14 15:41:04'),(19543,12,'vsol',1813,'8c:de:f9:d3:9f:5e','1813','8c:de:f9:d3:9f:5e',NULL,NULL,0,'EPON 0/08','ONU 4','5350-Rajamehar-ISS',1582.00,-15.70,'Power Off','2024-11-20 18:29:21','online','2026-01-14 15:41:04'),(19544,12,'vsol',2071,'74:da:88:a6:ef:b5','2071','74:da:88:a6:ef:b5',NULL,NULL,0,'EPON 0/08','ONU 7','5304-Akash',2103.00,-16.02,'Power Off','2024-11-21 17:22:51','online','2026-01-14 15:41:04'),(19545,12,'vsol',2243,'b8:3a:08:01:f6:58','2243','b8:3a:08:01:f6:58',NULL,NULL,0,'EPON 0/08','ONU 40',':',862.00,-21.25,'Power Off','2024-11-21 20:32:57','online','2026-01-14 15:41:04'),(19546,12,'vsol',2621,'8c:86:dd:86:43:9d','2621','8c:86:dd:86:43:9d',NULL,NULL,0,'EPON 0/08','ONU 33',':',3078.00,-23.77,'Power Off','2024-11-21 20:20:45','online','2026-01-14 15:41:04'),(19547,12,'vsol',2622,'3c:6a:d2:1b:5f:d7','2622','3c:6a:d2:1b:5f:d7',NULL,NULL,0,'EPON 0/08','ONU 39',':',3123.00,-24.20,'Wire Down','2024-11-21 21:46:50','online','2026-01-14 15:41:04'),(19548,12,'vsol',2616,'3c:78:95:be:ff:ba','2616','3c:78:95:be:ff:ba',NULL,NULL,0,'EPON 0/08','ONU 3','5697-CDGS',1426.00,-14.13,'Power Off','2024-11-21 20:32:41','online','2026-01-14 15:41:04'),(19549,12,'vsol',2183,'5c:e9:31:dd:b4:c4','2183','5c:e9:31:dd:b4:c4',NULL,NULL,0,'EPON 0/08','ONU 14',':',1111.00,-23.01,'Power Off','2024-11-21 20:32:28','online','2026-01-14 15:41:04'),(19550,12,'vsol',2099,'04:95:e6:3a:4a:3f','2099','04:95:e6:3a:4a:3f',NULL,NULL,0,'EPON 0/08','ONU 36',':',3121.00,-13.70,'Power Off','2024-11-21 17:22:49','online','2026-01-14 15:41:04'),(19551,12,'vsol',2250,'98:25:4a:aa:49:0b','2250','98:25:4a:aa:49:0b',NULL,NULL,0,'EPON 0/08','ONU 31',':',3090.00,-15.53,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(19552,12,'vsol',2576,'88:bd:09:4f:bb:90','2576','88:bd:09:4f:bb:90',NULL,NULL,0,'EPON 0/08','ONU 5','5670-Asma',1062.00,-23.47,'Power Off','2024-11-21 17:18:25','online','2026-01-14 15:41:04'),(19553,12,'vsol',2394,'cc:2d:21:04:99:40','2394','cc:2d:21:04:99:40',NULL,NULL,0,'EPON 0/08','ONU 35',':',972.00,-23.57,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:41:04'),(19554,12,'vsol',1913,'48:22:54:89:eb:b5','1913','48:22:54:89:eb:b5',NULL,NULL,0,'EPON 0/08','ONU 28',':',759.00,-17.40,'Power Off','2024-11-21 20:32:55','online','2026-01-14 15:41:04'),(19555,12,'vsol',2388,'d8:32:14:1e:41:a8','2388','d8:32:14:1e:41:a8',NULL,NULL,0,'EPON 0/08','ONU 22',':',1713.00,-16.50,'Power Off','2024-11-21 17:22:46','online','2026-01-14 15:41:04'),(19556,12,'vsol',2062,'58:d9:d5:74:e9:f8','2062','58:d9:d5:74:e9:f8',NULL,NULL,0,'EPON 0/08','ONU 17',':',2946.00,-15.36,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:41:04'),(19557,12,'vsol',1853,'a0:94:6a:04:5d:ae','1853','a0:94:6a:04:5d:ae',NULL,NULL,0,'EPON 0/08','ONU 12',':',916.00,-22.68,'Power Off','2024-11-14 00:08:07','online','2026-01-14 15:41:04'),(19558,12,'vsol',2077,'b8:3a:08:6b:33:60','2077','b8:3a:08:6b:33:60',NULL,NULL,0,'EPON 0/08','ONU 30',':',824.00,-20.60,'Power Off','2024-11-21 20:32:31','online','2026-01-14 15:41:04'),(19602,12,'vsol',2250,'6c:68:a4:45:29:f1','2250','6c:68:a4:45:29:f1',NULL,NULL,0,'EPON 0/08','',':',0.00,-15.53,'Power Off','2024-11-21 17:22:42','unknown','2026-01-14 15:41:03'),(19607,12,'vsol',2089,'1c:ef:03:ae:5f:5e','2089','1c:ef:03:ae:5f:5e',NULL,NULL,0,'EPON 0/08','ONU 15',':',1023.00,-20.51,'Power Off','2024-11-21 20:32:21','online','2026-01-14 15:41:04'),(19608,11,'vsol',NULL,'1c:ef:03:b6:39:ec',NULL,NULL,NULL,NULL,0,'PON 0/00','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19609,11,'vsol',2401,'7c:f1:7e:88:30:84','2401','7c:f1:7e:88:30:84',NULL,NULL,0,'EPON 0/01','ONU 60','5571-Sajida',2028.00,-23.87,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:40:32'),(19610,11,'vsol',1797,'0c:80:63:0a:5f:9f','1797','0c:80:63:0a:5f:9f',NULL,NULL,0,'EPON 0/01','ONU 10','5082-Nur',2185.00,-15.26,'Power Off','2026-01-14 09:25:53','online','2026-01-14 15:40:32'),(19611,11,'vsol',1855,'8c:90:2d:52:ee:8c','1855','8c:90:2d:52:ee:8c',NULL,NULL,0,'EPON 0/01','ONU 14','5040-Ibrahim',2059.00,-19.71,'Power Off','2026-01-14 09:25:43','online','2026-01-14 15:40:32'),(19612,11,'vsol',2172,'58:d9:d5:eb:19:f0','2172','58:d9:d5:eb:19:f0',NULL,NULL,0,'EPON 0/01','ONU 26','5382-Sonya',2364.00,-17.06,'Wire Down','2026-01-14 09:38:06','online','2026-01-14 15:40:32'),(19613,11,'vsol',1907,'10:27:f5:04:98:0d','1907','10:27:f5:04:98:0d',NULL,NULL,0,'EPON 0/01','ONU 18','5251-Kaium',2411.00,-10.57,'Power Off','2026-01-14 09:25:43','online','2026-01-14 15:40:32'),(19614,11,'vsol',2180,'d8:32:14:4d:78:c9','2180','d8:32:14:4d:78:c9',NULL,NULL,0,'EPON 0/01','ONU 32','5392-Yousuf',2188.00,-9.88,'Power Off','2026-01-13 14:42:11','online','2026-01-14 15:40:32'),(19615,11,'vsol',1870,'54:af:97:e1:be:e3','1870','54:af:97:e1:be:e3',NULL,NULL,0,'EPON 0/01','ONU 70','5213-Riday',1998.00,-24.56,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:32'),(19616,11,'vsol',1836,'50:0f:f5:74:e7:c0','1836','50:0f:f5:74:e7:c0',NULL,NULL,0,'EPON 0/01','ONU 45',':',3656.00,-26.58,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:32'),(19617,11,'vsol',2473,'ec:75:0c:15:25:25','2473','ec:75:0c:15:25:25',NULL,NULL,0,'EPON 0/01','ONU 3','5607-mohasin',1938.00,-16.20,'Power Off','2026-01-14 09:25:44','online','2026-01-14 15:40:32'),(19618,11,'vsol',1856,'d8:32:14:1e:ba:28','1856','d8:32:14:1e:ba:28',NULL,NULL,0,'EPON 0/01','ONU 9','5018-Mohiuddin',2339.00,-14.72,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:32'),(19619,11,'vsol',2531,'d8:32:14:9e:b1:40','2531','d8:32:14:9e:b1:40',NULL,NULL,0,'EPON 0/01','ONU 59','5639-Mehedi',2419.00,-25.23,'Power Off','2026-01-14 09:25:45','online','2026-01-14 15:40:32'),(19620,11,'vsol',1914,'50:0f:f5:ef:a7:70','1914','50:0f:f5:ef:a7:70',NULL,NULL,0,'EPON 0/01','ONU 31','5248-Sawon',2436.00,-24.95,'Power Off','2026-01-14 09:25:56','online','2026-01-14 15:40:32'),(19621,11,'vsol',2493,'10:5f:02:8b:5c:7f','2493','10:5f:02:8b:5c:7f',NULL,NULL,0,'EPON 0/01','ONU 33',':',2575.00,-18.73,'Power Off','2026-01-14 09:25:49','online','2026-01-14 15:40:32'),(19622,11,'vsol',1986,'78:8c:b5:e9:df:67','1986','78:8c:b5:e9:df:67',NULL,NULL,0,'EPON 0/01','ONU 5','5110-Mosa',2459.00,-19.36,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:32'),(19623,11,'vsol',2283,'b8:3a:08:26:87:40','2283','b8:3a:08:26:87:40',NULL,NULL,0,'EPON 0/01','ONU 40','5458-Amina',1970.00,-18.42,'Power Off','2026-01-14 09:25:50','online','2026-01-14 15:40:32'),(19624,11,'vsol',2445,'3c:64:cf:7a:69:93','2445','3c:64:cf:7a:69:93',NULL,NULL,0,'EPON 0/01','ONU 30',':',2277.00,-21.49,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:32'),(19625,11,'vsol',2493,'10:5f:02:8b:5c:87','2493','10:5f:02:8b:5c:87',NULL,NULL,0,'EPON 0/01','ONU 33',':',2575.00,-18.73,'Power Off','2026-01-14 09:25:49','online','2026-01-14 15:40:32'),(19626,11,'vsol',2608,'cc:ba:bd:20:66:cd','2608','cc:ba:bd:20:66:cd',NULL,NULL,0,'EPON 0/01','ONU 11',':',1997.00,-24.81,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:32'),(19627,11,'vsol',2370,'8c:de:f9:75:63:b4','2370','8c:de:f9:75:63:b4',NULL,NULL,0,'EPON 0/01','ONU 42',':',2251.00,-25.69,'Power Off','2026-01-14 14:22:35','online','2026-01-14 15:40:33'),(19628,11,'vsol',1868,'d8:32:14:c0:fc:68','1868','d8:32:14:c0:fc:68',NULL,NULL,0,'EPON 0/01','ONU 55','5210-Nazrul',1923.00,-24.44,'Power Off','2026-01-14 09:26:01','online','2026-01-14 15:40:33'),(19629,11,'vsol',2304,'ac:15:a2:d1:b6:29','2304','ac:15:a2:d1:b6:29',NULL,NULL,0,'EPON 0/01','ONU 1','5479-Azizur-ST',2693.00,-19.63,'Power Off','2026-01-08 11:39:48','online','2026-01-14 15:40:33'),(19630,11,'vsol',2387,'50:0f:f5:2e:97:89','2387','50:0f:f5:2e:97:89',NULL,NULL,0,'EPON 0/01','ONU 22','5560-Sorifa',2411.00,-12.98,'Power Off','2026-01-14 09:25:35','online','2026-01-14 15:40:33'),(19631,11,'vsol',2162,'c0:25:2f:f8:4b:e7','2162','c0:25:2f:f8:4b:e7',NULL,NULL,0,'EPON 0/01','ONU 24','5375-Mohin',2700.00,-21.94,'Power Off','2026-01-14 12:40:04','online','2026-01-14 15:40:33'),(19632,11,'vsol',1804,'3c:fa:d3:c0:27:26','1804','3c:fa:d3:c0:27:26',NULL,NULL,0,'EPON 0/01','ONU 49','5080-Farden',1772.00,-10.41,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:33'),(19633,11,'vsol',1908,'48:22:54:6d:5e:c3','1908','48:22:54:6d:5e:c3',NULL,NULL,0,'EPON 0/01','ONU 20','5252-Mosharaf',2521.00,-10.85,'Power Off','2026-01-14 09:25:57','online','2026-01-14 15:40:33'),(19634,11,'vsol',1848,'d8:32:14:a4:89:00','1848','d8:32:14:a4:89:00',NULL,NULL,0,'EPON 0/01','ONU 63','5192-Imran',1908.00,-14.72,'Power Off','2026-01-14 09:25:45','online','2026-01-14 15:40:33'),(19635,11,'vsol',1907,'80:07:1b:e1:38:89','1907','80:07:1b:e1:38:89',NULL,NULL,0,'EPON 0/01','ONU 18','5251-Kaium',2411.00,-10.57,'Power Off','2026-01-14 09:25:43','online','2026-01-14 15:40:33'),(19636,11,'vsol',2224,'b8:3a:08:2a:ab:7f','2224','b8:3a:08:2a:ab:7f',NULL,NULL,0,'EPON 0/01','ONU 62',':',2316.00,-21.94,'Power Off','2026-01-14 09:25:51','online','2026-01-14 15:40:33'),(19637,11,'vsol',1917,'3c:fa:d3:c0:1a:60','1917','3c:fa:d3:c0:1a:60',NULL,NULL,0,'EPON 0/01','ONU 4','5115-Alamin',2326.00,-40.00,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:33'),(19638,11,'vsol',1796,'e8:65:d4:fc:2d:50','1796','e8:65:d4:fc:2d:50',NULL,NULL,0,'EPON 0/01','ONU 28','5081-Hasan',2177.00,-14.07,'Wire Down','2026-01-14 09:25:58','online','2026-01-14 15:40:33'),(19639,11,'vsol',2495,'b8:3a:08:7c:d5:87','2495','b8:3a:08:7c:d5:87',NULL,NULL,0,'EPON 0/01','ONU 38','5615-Sanaullah',2351.00,-21.80,'Wire Down','2026-01-14 09:37:38','online','2026-01-14 15:40:33'),(19640,11,'vsol',1798,'e8:65:d4:fc:2b:b8','1798','e8:65:d4:fc:2b:b8',NULL,NULL,0,'EPON 0/01','ONU 34','5083-Halim',2029.00,-14.52,'Power Off','2026-01-14 09:25:46','online','2026-01-14 15:40:33'),(19641,11,'vsol',2395,'60:83:e7:92:cd:a0','2395','60:83:e7:92:cd:a0',NULL,NULL,0,'EPON 0/01','ONU 61','5567-Nazrul',1923.00,-24.32,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:33'),(19642,11,'vsol',2303,'b8:3a:08:47:8d:d7','2303','b8:3a:08:47:8d:d7',NULL,NULL,0,'EPON 0/01','ONU 43','5478-Kalam',1498.00,-18.79,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:33'),(19643,11,'vsol',2174,'e0:1c:fc:3e:e3:43','2174','e0:1c:fc:3e:e3:43',NULL,NULL,0,'EPON 0/01','ONU 17','5385-Salma',1593.00,-23.77,'Power Off','2026-01-14 09:25:35','online','2026-01-14 15:40:33'),(19644,11,'vsol',2551,'20:23:51:66:eb:4b','2551','20:23:51:66:eb:4b',NULL,NULL,0,'EPON 0/01','ONU 58','5652-Nowshin',1969.00,-30.00,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:33'),(19645,11,'vsol',2607,'88:bd:09:4f:b9:b4','2607','88:bd:09:4f:b9:b4',NULL,NULL,0,'EPON 0/01','ONU 41',':',2147.00,-18.60,'Power Off','2026-01-14 09:26:01','online','2026-01-14 15:40:33'),(19646,11,'vsol',2065,'58:d9:d5:74:ea:30','2065','58:d9:d5:74:ea:30',NULL,NULL,0,'EPON 0/01','ONU 47','5300-Fardin',4649.00,-10.78,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:40:33'),(19647,11,'vsol',2476,'64:64:4a:36:0b:3e','2476','64:64:4a:36:0b:3e',NULL,NULL,0,'EPON 0/01','ONU 37',':',2210.00,-22.76,'Power Off','2026-01-14 09:25:47','online','2026-01-14 15:40:33'),(19648,11,'vsol',NULL,'30:07:5c:21:b5:e2',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 48',':',2939.00,-31.55,'Power Off','2026-01-14 12:40:05','online','2026-01-14 15:40:33'),(19649,11,'vsol',2340,'b0:19:21:ef:85:b7','2340','b0:19:21:ef:85:b7',NULL,NULL,0,'EPON 0/01','ONU 36',':',2854.00,-30.00,'Power Off','2026-01-14 12:40:04','online','2026-01-14 15:40:33'),(19650,11,'vsol',1879,'80:3f:5d:64:38:6e','1879','80:3f:5d:64:38:6e',NULL,NULL,0,'EPON 0/01','ONU 2','5002-Sharifa',1336.00,-18.30,'Power Off','2026-01-14 13:09:23','online','2026-01-14 15:40:33'),(19651,11,'vsol',2174,'a0:7e:01:12:0e:5d','2174','e0:1c:fc:3e:e3:43',201,'2026-01-14 15:34:01',0,'EPON 0/01','ONU 17','5385-Salma',1593.00,-23.77,'Power Off','2026-01-14 09:25:35','online','2026-01-14 15:34:01'),(19652,11,'vsol',2078,'bc:e0:01:8b:d3:dd','2078','bc:e0:01:8b:d3:dd',NULL,NULL,0,'EPON 0/01','ONU 54','5311-Kawsar',2180.00,-22.22,'Power Off','2026-01-14 09:25:53','online','2026-01-14 15:40:33'),(19653,11,'vsol',1875,'9c:a2:f4:98:f1:c3','1875','9c:a2:f4:98:f1:c3',NULL,NULL,0,'EPON 0/01','ONU 53','5064-Jowel',2900.00,-26.20,'Power Off','2026-01-14 12:40:04','online','2026-01-14 15:40:33'),(19654,11,'vsol',1870,'a0:7f:06:31:6b:b7','1870','a0:7f:06:31:6b:b7',NULL,NULL,0,'EPON 0/01','ONU 70','5213-Riday',1998.00,-24.56,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:33'),(19655,11,'vsol',1799,'d8:32:14:f3:77:68','1799','d8:32:14:f3:77:68',NULL,NULL,0,'EPON 0/01','ONU 46','5085-Sazad',3642.00,-23.47,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:33'),(19656,11,'vsol',2094,'60:32:b1:2d:d4:e6','2094','60:32:b1:2d:d4:e6',NULL,NULL,0,'EPON 0/01','ONU 13','5323-Shalom',2331.00,-17.35,'Power Off','2026-01-14 09:25:56','online','2026-01-14 15:40:33'),(19657,11,'vsol',1792,'68:9f:f0:0d:cd:09','1792','68:9f:f0:0d:cd:09',NULL,NULL,0,'EPON 0/01','ONU 11',':',1997.00,-24.81,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:33'),(19658,11,'vsol',2191,'3c:64:cf:b2:86:9a','2191','3c:64:cf:b2:86:9a',NULL,NULL,0,'EPON 0/01','ONU 16','5399-Emon',4000.00,-14.72,'Power Off','2026-01-14 09:25:41','online','2026-01-14 15:40:33'),(19659,11,'vsol',2181,'4c:d7:c8:bb:af:c5','2181','4c:d7:c8:bb:af:c5',NULL,NULL,0,'EPON 0/01','ONU 23',':',1972.00,-23.57,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:33'),(19660,11,'vsol',1896,'50:0f:f5:67:49:30','1896','50:0f:f5:67:49:30',NULL,NULL,0,'EPON 0/01','ONU 7','5235-Younos',2259.00,-17.72,'Power Off','2026-01-10 20:14:50','online','2026-01-14 15:40:33'),(19661,11,'vsol',1804,'58:d9:d5:9b:10:00','1804','58:d9:d5:9b:10:00',NULL,NULL,0,'EPON 0/01','ONU 49','5080-Farden',1772.00,-10.41,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:33'),(19662,11,'vsol',2151,'88:bd:09:1b:e2:97','2151','88:bd:09:1b:e2:97',NULL,NULL,0,'EPON 0/01','ONU 15','5365-Depak',2028.00,-21.87,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:33'),(19663,11,'vsol',2181,'88:bd:09:45:58:da','2181','88:bd:09:45:58:da',NULL,NULL,0,'EPON 0/01','ONU 23',':',1972.00,-23.57,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:33'),(19664,11,'vsol',1851,'d8:32:14:40:3f:f0','1851','d8:32:14:40:3f:f0',NULL,NULL,0,'EPON 0/01','ONU 27','5196-Aktaruzaman',2354.00,-14.81,'Power Off','2026-01-14 09:25:58','online','2026-01-14 15:40:33'),(19665,11,'vsol',1873,'00:eb:d8:36:10:c1','1873','00:eb:d8:36:10:c1',NULL,NULL,0,'EPON 0/01','ONU 39',':',2182.00,-7.68,'Power Off','2026-01-14 09:25:51','online','2026-01-14 15:40:33'),(19666,11,'vsol',2533,'0c:ef:15:35:5b:98','2533','0c:ef:15:35:5b:98',NULL,NULL,0,'EPON 0/01','ONU 44','5641-Sultan',1728.00,-23.47,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:33'),(19667,11,'vsol',2561,'50:0f:f5:7b:e0:e8','2561','50:0f:f5:7b:e0:e8',NULL,NULL,0,'EPON 0/01','ONU 6','5258-Alim',2305.00,-8.10,'Power Off','2026-01-14 09:25:44','online','2026-01-14 15:40:33'),(19668,11,'vsol',2533,'4c:d7:c8:e3:49:49','2533','0c:ef:15:35:5b:98',201,'2026-01-14 15:34:01',0,'EPON 0/01','ONU 44','5641-Sultan',1728.00,-23.47,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:34:02'),(19669,11,'vsol',1849,'d8:32:14:1e:ba:20','1849','d8:32:14:1e:ba:20',NULL,NULL,0,'EPON 0/01','ONU 19','5193-Tarek',2352.00,-16.48,'Power Off','2026-01-14 09:25:45','online','2026-01-14 15:40:33'),(19670,11,'vsol',2026,'50:0f:f5:da:98:48','2026','50:0f:f5:da:98:48',NULL,NULL,0,'EPON 0/01','ONU 51','5272-Jabed',4246.00,-26.78,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:33'),(19671,11,'vsol',1917,'60:a4:b7:20:51:65','1917','60:a4:b7:20:51:65',NULL,NULL,0,'EPON 0/01','ONU 4','5115-Alamin',2326.00,-40.00,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:33'),(19672,11,'vsol',2354,'cc:2d:21:17:4e:20','2354','cc:2d:21:17:4e:20',NULL,NULL,0,'EPON 0/01','ONU 21','5526-Rubil',1811.00,-20.13,'Power Off','2026-01-14 09:25:53','online','2026-01-14 15:40:33'),(19673,11,'vsol',1891,'48:22:54:2b:bb:cd','1891','48:22:54:2b:bb:cd',NULL,NULL,0,'EPON 0/01','ONU 12','5229-AliHaydar',4106.00,-18.18,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:33'),(19674,11,'vsol',1909,'9c:53:22:e8:be:ed','1909','9c:53:22:e8:be:ed',NULL,NULL,0,'EPON 0/01','ONU 35','5253-Salma',2367.00,-21.94,'Power Off','2026-01-14 09:25:51','online','2026-01-14 15:40:33'),(19675,11,'vsol',NULL,'d4:94:e8:c3:81:4f',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 8','',2185.00,NULL,'Wire Down','2026-01-01 22:14:29','offline','2026-01-14 15:40:33'),(19676,11,'vsol',NULL,'a2:3e:09:10:a0:d0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 25','',1941.00,NULL,'Wire Down','2025-12-11 21:08:50','offline','2026-01-14 15:40:33'),(19677,11,'vsol',1878,'4c:d7:c8:13:5b:c4','1878','4c:d7:c8:13:5b:c4',NULL,NULL,0,'EPON 0/01','ONU 29','',2219.00,NULL,'Power Off','2026-01-14 07:42:07','offline','2026-01-14 15:40:33'),(19678,11,'vsol',NULL,'cc:53:b5:ba:17:2b',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 64','',2029.00,NULL,'Power Off','2026-01-13 08:55:31','offline','2026-01-14 15:40:33'),(19679,11,'vsol',2058,'58:d9:d5:77:a5:90','2058','58:d9:d5:77:a5:90',NULL,NULL,0,'EPON 0/02','ONU 17','5293-Sofiq',3854.00,-19.71,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:33'),(19680,11,'vsol',1829,'38:6b:1c:b7:24:51','1829','38:6b:1c:b7:24:51',NULL,NULL,0,'EPON 0/02','ONU 6','5166-Samad',2431.00,-23.67,'Wire Down','2026-01-14 09:25:59','online','2026-01-14 15:40:33'),(19681,11,'vsol',2369,'dc:8e:8d:3c:5b:35','2369','dc:8e:8d:3c:5b:35',NULL,NULL,0,'EPON 0/02','ONU 36',':',3736.00,-16.23,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:33'),(19682,11,'vsol',2090,'5c:62:8b:78:48:41','2090','5c:62:8b:78:48:41',202,'2026-01-14 11:54:01',0,'EPON 0/02','ONU 10','5318-Bilal',980.00,-21.67,'Power Off','2026-01-14 09:25:47','online','2026-01-14 11:54:03'),(19683,11,'vsol',2144,'58:d9:d5:9b:10:08','2144','58:d9:d5:9b:10:08',NULL,NULL,0,'EPON 0/02','ONU 12','5362-Saiful-islam',1564.00,-12.37,'Power Off','2026-01-07 19:29:08','online','2026-01-14 15:40:33'),(19684,11,'vsol',2293,'04:95:e6:3a:88:2f','2293','04:95:e6:3a:88:2f',NULL,NULL,0,'EPON 0/02','ONU 9','5467-Arman',3442.00,-26.99,'Power Off','2026-01-13 15:30:44','online','2026-01-14 15:40:33'),(19685,11,'vsol',1830,'04:5e:a4:d8:73:fe','1830','04:5e:a4:d8:73:fe',NULL,NULL,0,'EPON 0/02','ONU 2','5163-Ripon',2329.00,-24.32,'Wire Down','2026-01-14 09:25:56','online','2026-01-14 15:40:33'),(19686,11,'vsol',2535,'4c:d7:c8:bd:af:49','2535','4c:d7:c8:bd:af:49',NULL,NULL,0,'EPON 0/02','ONU 30','5643-Hasan',2344.00,-24.81,'Power Off','2026-01-14 09:25:41','online','2026-01-14 15:40:33'),(19687,11,'vsol',2535,'a8:31:62:04:9c:bc','2535','a8:31:62:04:9c:bc',NULL,NULL,0,'EPON 0/02','ONU 30','5643-Hasan',2344.00,-24.81,'Power Off','2026-01-14 09:25:41','online','2026-01-14 15:40:33'),(19688,11,'vsol',1837,'b4:0f:3b:ce:8b:28','1837','b4:0f:3b:ce:8b:28',NULL,NULL,0,'EPON 0/02','ONU 19','5173-Masoma',2980.00,-23.01,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:33'),(19689,11,'vsol',2357,'74:da:88:71:67:b3','2357','74:da:88:71:67:b3',NULL,NULL,0,'EPON 0/02','ONU 39','5529-Jahed',1654.00,-10.55,'Power Off','2026-01-14 09:25:49','online','2026-01-14 15:40:33'),(19690,11,'vsol',2292,'b8:3a:08:dc:8d:88','2292','b8:3a:08:dc:8d:88',NULL,NULL,0,'EPON 0/02','ONU 25','5466-Kawser',2892.00,-23.19,'Power Off','2026-01-13 10:32:10','online','2026-01-14 15:40:33'),(19691,11,'vsol',2282,'d8:32:14:67:9b:28','2282','d8:32:14:67:9b:28',NULL,NULL,0,'EPON 0/02','ONU 28','5457-Kaiyum',1693.00,-7.55,'Power Off','2026-01-14 09:25:52','online','2026-01-14 15:40:33'),(19692,11,'vsol',1831,'c0:25:2f:a1:97:d9','1831','c0:25:2f:a1:97:d9',NULL,NULL,0,'EPON 0/02','ONU 29','5164-Kaiyom',2485.00,-25.53,'Power Off','2026-01-14 09:26:01','online','2026-01-14 15:40:33'),(19693,11,'vsol',2290,'94:0e:6b:eb:65:1b','2290','94:0e:6b:eb:65:1b',NULL,NULL,0,'EPON 0/02','ONU 24',':',3233.00,-23.98,'Power Off','2026-01-13 15:30:40','online','2026-01-14 15:40:33'),(19694,11,'vsol',2294,'60:83:e7:61:23:f7','2294','60:83:e7:61:23:f7',NULL,NULL,0,'EPON 0/02','ONU 21','5468-Afroja',3537.00,-21.55,'Power Off','2026-01-13 15:30:43','online','2026-01-14 15:40:33'),(19695,11,'vsol',2289,'0c:80:63:ea:39:3d','2289','0c:80:63:ea:39:3d',NULL,NULL,0,'EPON 0/02','ONU 13','5463-Kamrul',3216.00,-25.85,'Power Off','2026-01-13 15:30:43','online','2026-01-14 15:40:33'),(19696,11,'vsol',2296,'d8:32:14:d6:f0:a8','2296','d8:32:14:d6:f0:a8',NULL,NULL,0,'EPON 0/02','ONU 26','5470-Roma',3551.00,-20.46,'Power Off','2026-01-13 15:30:41','online','2026-01-14 15:40:33'),(19697,11,'vsol',2341,'98:25:4a:99:8b:47','2341','98:25:4a:99:8b:47',NULL,NULL,0,'EPON 0/02','ONU 38',':',2262.00,-26.38,'Power Off','2026-01-14 09:25:57','online','2026-01-14 15:40:33'),(19698,11,'vsol',2499,'88:bd:09:62:a2:fb','2499','88:bd:09:62:a2:fb',NULL,NULL,0,'EPON 0/02','ONU 14','5619-Sofiulha',5446.00,-27.21,'Power Off','2026-01-13 15:30:40','online','2026-01-14 15:40:34'),(19699,11,'vsol',2297,'d8:32:14:76:e0:08','2297','d8:32:14:76:e0:08',NULL,NULL,0,'EPON 0/02','ONU 5','5471-Mostufa',3619.00,-20.51,'Power Off','2026-01-13 15:30:43','online','2026-01-14 15:40:34'),(19700,11,'vsol',2565,'98:ba:5f:67:95:15','2565','98:ba:5f:67:95:15',NULL,NULL,0,'EPON 0/02','ONU 35',':',3721.00,-20.66,'Power Off','2026-01-14 10:39:00','online','2026-01-14 15:40:34'),(19701,11,'vsol',2384,'b4:0f:3b:78:3d:d8','2384','b4:0f:3b:78:3d:d8',202,'2026-01-14 10:43:47',0,'EPON 0/02','ONU 31','5557-Alamin',2505.00,-31.55,'Power Off','2026-01-14 09:25:40','online','2026-01-14 10:43:49'),(19702,11,'vsol',2235,'d8:32:14:a0:2b:08','2235','d8:32:14:a0:2b:08',NULL,NULL,0,'EPON 0/02','ONU 16','5431-Shoal',1838.00,-20.56,'Power Off','2026-01-14 09:25:44','online','2026-01-14 15:40:34'),(19703,11,'vsol',2404,'4c:d7:c8:a0:d7:2d','2404','4c:d7:c8:a0:d7:2d',NULL,NULL,0,'EPON 0/02','ONU 27','5573-Ayesha',2508.00,-27.70,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:34'),(19704,11,'vsol',1832,'e8:65:d4:fc:31:40','1832','e8:65:d4:fc:31:40',NULL,NULL,0,'EPON 0/02','ONU 40',':',2190.00,-27.96,'Power Off','2026-01-14 09:25:52','online','2026-01-14 15:40:34'),(19705,11,'vsol',2295,'d8:32:14:d6:af:90','2295','d8:32:14:d6:af:90',NULL,NULL,0,'EPON 0/02','ONU 15','5469-Munoara',3557.00,-21.43,'Power Off','2026-01-13 15:30:43','online','2026-01-14 15:40:34'),(19706,11,'vsol',2548,'c8:7f:54:b7:2f:50','2548','c8:7f:54:b7:2f:50',NULL,NULL,0,'EPON 0/02','ONU 32','5649-Rohoman',2564.00,-28.86,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:34'),(19707,11,'vsol',2176,'58:d9:d5:eb:19:70','2176','58:d9:d5:eb:19:70',NULL,NULL,0,'EPON 0/02','ONU 11','5387-Sabuj',2928.00,-21.02,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:34'),(19708,11,'vsol',2596,'dc:8e:8d:f4:7d:02','2596','dc:8e:8d:f4:7d:02',NULL,NULL,0,'EPON 0/02','ONU 37',':',2608.00,-10.44,'Power Off','2026-01-14 09:25:58','online','2026-01-14 15:40:34'),(19709,11,'vsol',2008,'d8:32:14:43:53:08','2008','d8:32:14:43:53:08',NULL,NULL,0,'EPON 0/02','ONU 18','5261-Subhan',2982.00,-16.66,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:40:34'),(19710,11,'vsol',2238,'b8:3a:08:6b:33:a0','2238','b8:3a:08:6b:33:a0',NULL,NULL,0,'EPON 0/02','ONU 22','5433-Enos',3941.00,-27.96,'Power Off','2026-01-14 08:10:40','online','2026-01-14 15:40:34'),(19711,11,'vsol',1815,'b4:b0:24:c7:37:dd','1815','b4:b0:24:c7:37:dd',NULL,NULL,0,'EPON 0/02','ONU 7','5114-KAMRUL',1703.00,-15.65,'Power Off','2026-01-13 21:02:52','online','2026-01-14 15:40:34'),(19712,11,'vsol',2205,'40:ed:00:5d:64:68','2205','40:ed:00:5d:64:68',NULL,NULL,0,'EPON 0/02','ONU 8','5407-Tanvir',1695.00,-15.56,'N/A',NULL,'online','2026-01-14 15:40:34'),(19713,11,'vsol',2553,'64:64:4a:30:41:64','2553','64:64:4a:30:41:64',NULL,NULL,0,'EPON 0/02','ONU 33','5654-Mirajul',3442.00,-18.10,'Power Off','2026-01-13 15:30:41','online','2026-01-14 15:40:34'),(19714,11,'vsol',2553,'4c:d7:c8:ce:d8:76','2553','4c:d7:c8:ce:d8:76',NULL,NULL,0,'EPON 0/02','ONU 33','5654-Mirajul',3442.00,-18.10,'Power Off','2026-01-13 15:30:41','online','2026-01-14 15:40:34'),(19715,11,'vsol',2331,'5c:a6:e6:f1:66:2b','2331','5c:a6:e6:f1:66:2b',NULL,NULL,0,'EPON 0/02','ONU 34','5242-Arman',831.00,-18.45,'Power Off','2026-01-14 09:26:00','online','2026-01-14 15:40:34'),(19716,11,'vsol',2061,'58:d9:d5:77:a5:a0','2061','58:d9:d5:77:a5:a0',NULL,NULL,0,'EPON 0/02','ONU 23','5296-Sakib',1819.00,-22.92,'Power Off','2026-01-14 09:25:50','online','2026-01-14 15:40:34'),(19717,11,'vsol',1833,'d8:0d:17:64:16:6f','1833','d8:0d:17:64:16:6f',NULL,NULL,0,'EPON 0/02','ONU 1','5167-Dalowar',806.00,-14.40,'Power Off','2026-01-05 12:42:26','online','2026-01-14 15:40:34'),(19718,11,'vsol',2404,'c0:25:2f:be:f8:d7','2404','c0:25:2f:be:f8:d7',NULL,NULL,0,'EPON 0/02','ONU 27','5573-Ayesha',2508.00,-27.70,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:34'),(19719,11,'vsol',NULL,'e8:4d:d0:0e:a6:7b',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 3','',2360.00,NULL,'Wire Down','2025-12-04 12:36:22','offline','2026-01-14 15:40:34'),(19720,11,'vsol',NULL,'70:a5:6a:2e:c2:d1',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 4','',2675.00,NULL,'Wire Down','2026-01-04 08:12:41','offline','2026-01-14 15:40:34'),(19721,11,'vsol',2141,'30:3d:51:e4:5f:5c','2141','30:3d:51:e4:5f:5c',NULL,NULL,0,'EPON 0/02','ONU 20','',3113.00,NULL,'Power Off','2026-01-14 14:15:29','offline','2026-01-14 15:34:03'),(19722,11,'vsol',NULL,'a2:3e:09:10:88:e1',NULL,NULL,NULL,NULL,0,'EPON 0/03','','',0.00,NULL,'',NULL,'unknown','2026-01-14 14:54:05'),(19723,11,'vsol',2342,'b8:3a:08:a7:4a:c7','2342','b8:3a:08:a7:4a:c7',NULL,NULL,0,'EPON 0/03','ONU 20','5514-Lija',764.00,-21.80,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:34'),(19724,11,'vsol',2360,'d8:07:b6:e7:bf:63','2360','d8:07:b6:e7:bf:63',NULL,NULL,0,'EPON 0/03','ONU 22',':',756.00,-19.71,'Power Off','2026-01-14 09:26:00','online','2026-01-14 15:40:34'),(19725,11,'vsol',1791,'e8:65:d4:27:8f:24','1791','e8:65:d4:27:8f:24',NULL,NULL,0,'EPON 0/03','ONU 24',':',1577.00,-13.42,'Power Off','2026-01-14 09:25:48','online','2026-01-14 15:40:34'),(19726,11,'vsol',2308,'a0:7f:04:17:61:cf','2308','a0:7f:04:17:61:cf',NULL,NULL,0,'EPON 0/03','ONU 10','5480-Seyam',746.00,-19.17,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:34'),(19727,11,'vsol',1819,'20:23:51:68:6b:94','1819','20:23:51:68:6b:94',NULL,NULL,0,'EPON 0/03','ONU 15','5119-Kamal',1387.00,-18.73,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:34'),(19728,11,'vsol',1795,'28:ee:52:f5:b4:3d','1795','28:ee:52:f5:b4:3d',NULL,NULL,0,'EPON 0/03','ONU 2','5077-Ojaher',1277.00,-23.67,'Power Off','2026-01-14 09:25:41','online','2026-01-14 15:40:34'),(19729,11,'vsol',1904,'b4:b0:24:44:3a:91','1904','b4:b0:24:44:3a:91',NULL,NULL,0,'EPON 0/03','ONU 18','5239-Rasel',3434.00,-9.72,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:34'),(19730,11,'vsol',2498,'0c:0e:76:a0:39:bb','2498','0c:0e:76:a0:39:bb',NULL,NULL,0,'EPON 0/03','ONU 4',':',1603.00,-14.66,'Power Off','2026-01-14 09:25:45','online','2026-01-14 15:40:34'),(19731,11,'vsol',2602,'b8:3a:08:f8:89:d0','2602','b8:3a:08:f8:89:d0',NULL,NULL,0,'EPON 0/03','ONU 21',':',92.00,-24.20,'Power Off','2026-01-14 09:25:50','online','2026-01-14 15:40:34'),(19732,11,'vsol',2403,'3c:64:cf:66:d9:ee','2403','3c:64:cf:66:d9:ee',NULL,NULL,0,'EPON 0/03','ONU 28','5572-CCDA',34.00,-20.76,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:34'),(19733,11,'vsol',1806,'e4:c3:2a:4d:10:05','1806','e4:c3:2a:4d:10:05',NULL,NULL,0,'EPON 0/03','ONU 35',':',1590.00,-18.57,'Power Off','2026-01-14 09:25:50','online','2026-01-14 15:40:34'),(19734,11,'vsol',1788,'04:5e:a4:30:4c:a2','1788','04:5e:a4:30:4c:a2',NULL,NULL,0,'EPON 0/03','ONU 30','5066-Sakib',759.00,-19.71,'Power Off','2026-01-14 09:25:54','online','2026-01-14 15:40:34'),(19735,11,'vsol',2072,'04:95:e6:94:75:10','2072','04:95:e6:94:75:10',NULL,NULL,0,'EPON 0/03','ONU 40',':',1588.00,-12.81,'Power Off','2026-01-14 09:25:54','online','2026-01-14 15:40:34'),(19736,11,'vsol',1865,'d8:32:14:c0:c1:98','1865','d8:32:14:c0:c1:98',NULL,NULL,0,'EPON 0/03','ONU 43',':',1626.00,-23.77,'Power Off','2026-01-14 09:25:53','online','2026-01-14 15:40:34'),(19737,11,'vsol',2398,'dc:8e:8d:b5:f8:77','2398','dc:8e:8d:b5:f8:77',NULL,NULL,0,'EPON 0/03','ONU 27','5569-Mizan',126.00,-24.81,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:34'),(19738,11,'vsol',1805,'04:5e:a4:c5:37:c1','1805','04:5e:a4:c5:37:c1',NULL,NULL,0,'EPON 0/03','ONU 23','5095-Hanif',1334.00,-12.99,'Power Off','2026-01-14 09:25:44','online','2026-01-14 15:40:34'),(19739,11,'vsol',2311,'a8:6e:84:02:78:70','2311','a8:6e:84:02:78:70',NULL,NULL,0,'EPON 0/03','ONU 1',':',1254.00,-13.46,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:34'),(19740,11,'vsol',2206,'00:31:92:7d:62:af','2206','00:31:92:7d:62:af',NULL,NULL,0,'EPON 0/03','ONU 6','5408-Moynal',1369.00,-13.79,'Power Off','2026-01-14 09:25:45','online','2026-01-14 15:40:34'),(19741,11,'vsol',2104,'5c:e9:31:e4:62:09','2104','5c:e9:31:e4:62:09',NULL,NULL,0,'EPON 0/03','ONU 3','5333-sohrif',774.00,-19.24,'Power Off','2026-01-14 09:25:54','online','2026-01-14 15:40:34'),(19742,11,'vsol',2291,'60:83:e7:11:bb:8b','2291','60:83:e7:11:bb:8b',NULL,NULL,0,'EPON 0/03','ONU 12','5465-Arif',738.00,-19.00,'Power Off','2026-01-14 09:25:42','online','2026-01-14 15:40:34'),(19743,11,'vsol',1790,'58:d9:d5:74:ea:08','1790','58:d9:d5:74:ea:08',NULL,NULL,0,'EPON 0/03','ONU 8',':',1641.00,-16.72,'Power Off','2026-01-14 09:26:02','online','2026-01-14 15:40:34'),(19744,11,'vsol',1794,'68:ff:7b:e2:de:92','1794','68:ff:7b:e2:de:92',NULL,NULL,0,'EPON 0/03','ONU 19','5076-Said',1141.00,-18.89,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:40:34'),(19745,11,'vsol',1794,'80:07:1b:e0:7f:89','1794','68:ff:7b:e2:de:92',203,'2026-01-14 15:24:01',0,'EPON 0/03','ONU 19','5076-Said',1141.00,-18.86,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:24:03'),(19746,11,'vsol',2323,'6c:68:a4:e8:d0:5c','2323','6c:68:a4:e8:d0:5c',NULL,NULL,0,'EPON 0/03','ONU 16','5496-Khokon',1416.00,-21.31,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:34'),(19747,11,'vsol',2319,'f0:09:0d:b1:ba:b2','2319','f0:09:0d:b1:ba:b2',NULL,NULL,0,'EPON 0/03','ONU 46',':',1613.00,-21.02,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:34'),(19748,11,'vsol',2333,'84:d8:1b:11:2c:0b','2333','84:d8:1b:11:2c:0b',NULL,NULL,0,'EPON 0/03','ONU 13','5505-Hafsa',49.00,-21.08,'Power Off','2026-01-14 09:25:48','online','2026-01-14 15:40:34'),(19749,11,'vsol',2169,'cc:2d:21:e9:b9:88','2169','cc:2d:21:e9:b9:88',NULL,NULL,0,'EPON 0/03','ONU 44',':',3657.00,-21.19,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:34'),(19750,11,'vsol',2629,'7c:f1:7e:7e:93:8f','2629','7c:f1:7e:7e:93:8f',NULL,NULL,0,'EPON 0/03','ONU 25',':',1233.00,-17.38,'Power Off','2026-01-14 09:26:00','online','2026-01-14 15:40:34'),(19751,11,'vsol',2323,'b0:19:21:12:6d:22','2323','b0:19:21:12:6d:22',NULL,NULL,0,'EPON 0/03','ONU 16','5496-Khokon',1416.00,-21.31,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:40:34'),(19752,11,'vsol',2591,'50:0f:f5:dc:3e:00','2591','50:0f:f5:dc:3e:00',NULL,NULL,0,'EPON 0/03','ONU 11',':',2880.00,-18.70,'Power Off','2026-01-14 09:25:43','online','2026-01-14 15:40:34'),(19753,11,'vsol',1789,'e4:c3:2a:4d:30:fd','1789','e4:c3:2a:4d:30:fd',NULL,NULL,0,'EPON 0/03','ONU 26',':',1562.00,-17.72,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:34'),(19754,11,'vsol',2308,'20:23:51:67:9e:ad','2308','20:23:51:67:9e:ad',NULL,NULL,0,'EPON 0/03','ONU 10','5480-Seyam',746.00,-19.07,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:40:34'),(19755,11,'vsol',2063,'58:d9:d5:a4:6e:27','2063','58:d9:d5:a4:6e:27',NULL,NULL,0,'EPON 0/03','ONU 7','5299-Morshed',1406.00,-17.03,'Power Off','2026-01-14 09:25:35','online','2026-01-14 15:40:34'),(19756,11,'vsol',2319,'6c:68:a4:e9:04:30','2319','6c:68:a4:e9:04:30',NULL,NULL,0,'EPON 0/03','ONU 46',':',1613.00,-21.02,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:40:34'),(19757,11,'vsol',2346,'58:d9:d5:aa:dd:f8','2346','58:d9:d5:aa:dd:f8',NULL,NULL,0,'EPON 0/03','ONU 45',':',1679.00,-21.55,'Power Off','2026-01-14 09:25:53','online','2026-01-14 15:40:34'),(19758,11,'vsol',NULL,'6c:68:a4:72:6e:9c',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 5','',1513.00,NULL,'Wire Down','2025-12-15 15:27:01','offline','2026-01-14 15:40:34'),(19759,11,'vsol',NULL,'a2:3e:07:18:78:a0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 9','',46.00,NULL,'Wire Down','2025-11-22 08:40:02','offline','2026-01-14 15:40:34'),(19760,11,'vsol',NULL,'00:d3:9e:6f:bd:14',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 14','',39.00,NULL,'Wire Down','2025-12-11 08:09:00','offline','2026-01-14 15:40:34'),(19761,11,'vsol',NULL,'18:09:d2:18:8f:c1',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 17','',2119.00,NULL,'Power Off','2025-12-30 09:09:20','offline','2026-01-14 15:40:35'),(19762,11,'vsol',NULL,'00:d3:9f:75:25:1a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 29','',1570.00,NULL,'Wire Down','2025-12-29 18:20:02','offline','2026-01-14 15:40:35'),(19764,11,'vsol',NULL,'dc:2c:6e:6f:e3:f6',NULL,NULL,NULL,NULL,0,'PON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19765,11,'vsol',1931,'cc:2d:21:68:21:70','1931','cc:2d:21:68:21:70',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19766,11,'vsol',1929,'7c:10:c9:2f:a2:50','1929','7c:10:c9:2f:a2:50',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19767,11,'vsol',1978,'98:03:8e:14:b0:59','1978','98:03:8e:14:b0:59',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19768,11,'vsol',2219,'40:ae:30:b0:bc:db','2219','40:ae:30:b0:bc:db',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19769,11,'vsol',2258,'d8:32:14:d7:c1:90','2258','d8:32:14:d7:c1:90',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19770,11,'vsol',2016,'58:d9:d5:19:a5:30','2016','58:d9:d5:19:a5:30',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19771,11,'vsol',2487,'cc:2d:21:b9:29:70','2487','cc:2d:21:b9:29:70',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19772,11,'vsol',1939,'d8:32:14:64:49:18','1939','d8:32:14:64:49:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19773,11,'vsol',1919,'cc:2d:21:4f:c4:cf','1919','cc:2d:21:4f:c4:cf',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19774,11,'vsol',1926,'50:0f:f5:ad:71:3f','1926','50:0f:f5:ad:71:3f',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19775,11,'vsol',1920,'cc:2d:21:5d:7c:78','1920','cc:2d:21:5d:7c:78',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19776,11,'vsol',1990,'80:3f:5d:7a:7e:72','1990','80:3f:5d:7a:7e:72',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19777,11,'vsol',2164,'54:af:97:b5:e7:93','2164','54:af:97:b5:e7:93',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19778,11,'vsol',2064,'58:d9:d5:26:76:88','2064','58:d9:d5:26:76:88',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:04:21'),(19779,11,'vsol',NULL,'a0:7e:08:33:00:6b',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 14:44:06'),(19780,11,'vsol',2137,'90:9a:4a:78:85:09','2137','90:9a:4a:78:85:09',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19781,11,'vsol',2266,'b8:3a:08:65:50:18','2266','b8:3a:08:65:50:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19782,11,'vsol',1918,'50:0f:f5:c1:8a:d8','1918','50:0f:f5:c1:8a:d8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19783,11,'vsol',2249,'b8:3a:08:65:50:38','2249','b8:3a:08:65:50:38',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19784,11,'vsol',2248,'b8:3a:08:65:50:30','2248','b8:3a:08:65:50:30',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19785,11,'vsol',1974,'cc:2d:21:3a:a1:60','1974','cc:2d:21:3a:a1:60',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19786,11,'vsol',NULL,'a0:7e:08:17:95:e7',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19787,11,'vsol',2223,'b8:3a:08:01:65:50','2223','b8:3a:08:01:65:50',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19788,11,'vsol',2274,'d8:32:14:c0:80:a8','2274','d8:32:14:c0:80:a8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19789,11,'vsol',2044,'d4:6e:0e:25:51:15','2044','d4:6e:0e:25:51:15',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19790,11,'vsol',1981,'20:23:51:54:21:7d','1981','20:23:51:54:21:7d',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19791,11,'vsol',2542,'60:83:e7:7a:f3:62','2542','60:83:e7:7a:f3:62',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19792,11,'vsol',2131,'ac:15:a2:ab:b9:c1','2131','ac:15:a2:ab:b9:c1',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19793,11,'vsol',1932,'50:0f:f5:f1:3e:80','1932','50:0f:f5:f1:3e:80',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19794,11,'vsol',2102,'cc:2d:21:27:5a:40','2102','cc:2d:21:27:5a:40',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19795,11,'vsol',2189,'dc:8e:8d:25:ea:b3','2189','dc:8e:8d:25:ea:b3',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19796,11,'vsol',NULL,'50:0b:91:ef:53:5b',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19797,11,'vsol',2105,'cc:2d:21:00:0a:77','2105','cc:2d:21:00:0a:77',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19798,11,'vsol',2010,'58:d9:d5:51:e2:00','2010','58:d9:d5:51:e2:00',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19799,11,'vsol',2558,'d8:32:14:31:49:10','2558','d8:32:14:31:49:10',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19800,11,'vsol',2200,'b8:3a:08:04:c5:f7','2200','b8:3a:08:04:c5:f7',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19801,11,'vsol',2185,'d8:32:14:39:cf:28','2185','d8:32:14:39:cf:28',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19802,11,'vsol',1959,'d8:32:14:09:2f:18','1959','d8:32:14:09:2f:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19803,11,'vsol',NULL,'a2:4f:09:08:5b:20',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:04:22'),(19804,11,'vsol',2187,'d8:32:14:39:cf:10','2187','d8:32:14:39:cf:10',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19805,11,'vsol',2511,'5c:e9:31:8b:2a:1f','2511','5c:e9:31:8b:2a:1f',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19806,11,'vsol',2047,'cc:2d:21:08:e0:78','2047','cc:2d:21:08:e0:78',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19807,11,'vsol',2478,'d8:32:14:38:53:18','2478','d8:32:14:38:53:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19808,11,'vsol',2232,'20:23:51:66:b8:47','2232','20:23:51:66:b8:47',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19809,11,'vsol',2413,'50:3d:d1:3c:9a:c1','2413','50:3d:d1:3c:9a:c1',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19810,11,'vsol',NULL,'3c:fa:d3:c0:f6:ac',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19811,11,'vsol',2251,'24:2f:d0:94:bc:c5','2251','24:2f:d0:94:bc:c5',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19812,11,'vsol',2515,'30:07:5c:1f:62:4a','2515','30:07:5c:1f:62:4a',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19813,11,'vsol',1972,'d8:32:14:df:16:e8','1972','d8:32:14:df:16:e8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19814,11,'vsol',2070,'58:d9:d5:9e:5d:90','2070','58:d9:d5:9e:5d:90',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19815,11,'vsol',1928,'b8:3a:08:26:21:c8','1928','b8:3a:08:26:21:c8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19816,11,'vsol',1965,'b8:3a:08:fe:5f:d8','1965','b8:3a:08:fe:5f:d8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19817,11,'vsol',2522,'50:0f:f5:7a:e5:10','2522','50:0f:f5:7a:e5:10',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19818,11,'vsol',1976,'b8:3a:08:6b:8e:b0','1976','b8:3a:08:6b:8e:b0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19819,11,'vsol',1930,'cc:2d:21:76:eb:68','1930','cc:2d:21:76:eb:68',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19820,11,'vsol',2043,'58:d9:d5:9e:d1:70','2043','58:d9:d5:9e:d1:70',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19821,11,'vsol',2081,'cc:2d:21:08:e0:98','2081','cc:2d:21:08:e0:98',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19822,11,'vsol',1940,'cc:2d:21:c0:0e:a8','1940','cc:2d:21:c0:0e:a8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19823,11,'vsol',2082,'cc:2d:21:08:e0:90','2082','cc:2d:21:08:e0:90',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19824,11,'vsol',2083,'cc:2d:21:08:e0:a0','2083','cc:2d:21:08:e0:a0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19825,11,'vsol',2426,'38:6b:1c:2a:82:07','2426','38:6b:1c:2a:82:07',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19826,11,'vsol',2260,'cc:2d:21:31:02:c8','2260','cc:2d:21:31:02:c8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19827,11,'vsol',2159,'dc:8e:8d:13:02:11','2159','dc:8e:8d:13:02:11',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19828,11,'vsol',2190,'dc:8e:8d:25:ec:57','2190','dc:8e:8d:25:ec:57',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19829,11,'vsol',2006,'58:d9:d5:25:5c:30','2006','58:d9:d5:25:5c:30',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19830,11,'vsol',2605,'08:40:f3:82:75:a0','2605','08:40:f3:82:75:a0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(19831,11,'vsol',2222,'74:fe:ce:26:d1:d7','2222','74:fe:ce:26:d1:d7',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19832,11,'vsol',2139,'d8:32:14:39:ac:38','2139','d8:32:14:39:ac:38',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19833,11,'vsol',1936,'0c:0e:76:a0:34:6f','1936','0c:0e:76:a0:34:6f',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19834,11,'vsol',2051,'58:d9:d5:69:07:18','2051','58:d9:d5:69:07:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19835,11,'vsol',2525,'50:0f:f5:d7:ad:e8','2525','50:0f:f5:d7:ad:e8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19836,11,'vsol',2483,'d8:32:14:df:0d:a8','2483','d8:32:14:df:0d:a8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19837,11,'vsol',NULL,'50:0b:91:ef:5d:99',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 14:54:08'),(19838,11,'vsol',2458,'d8:32:14:d7:bf:e8','2458','d8:32:14:d7:bf:e8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19839,11,'vsol',2589,'08:40:f3:22:81:d0','2589','08:40:f3:22:81:d0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19840,11,'vsol',2595,'c0:c9:e3:b5:cb:df','2595','c0:c9:e3:b5:cb:df',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19841,11,'vsol',2433,'cc:2d:21:ff:22:c7','2433','cc:2d:21:ff:22:c7',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19842,11,'vsol',1969,'d8:32:14:42:3a:b8','1969','d8:32:14:42:3a:b8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19843,11,'vsol',2393,'50:0f:f5:cd:3f:18','2393','50:0f:f5:cd:3f:18',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19844,11,'vsol',1985,'cc:2d:21:dc:c6:c8','1985','cc:2d:21:dc:c6:c8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19845,11,'vsol',1996,'e8:65:d4:6d:20:e0','1996','e8:65:d4:6d:20:e0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19846,11,'vsol',1975,'04:95:e6:70:db:80','1975','04:95:e6:70:db:80',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19847,11,'vsol',NULL,'00:9e:1e:12:40:b5',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19848,11,'vsol',2042,'30:de:4b:25:45:1f','2042','30:de:4b:25:45:1f',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19849,11,'vsol',2133,'cc:2d:21:18:47:d0','2133','cc:2d:21:18:47:d0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19850,11,'vsol',2116,'50:0f:f5:35:dd:e8','2116','50:0f:f5:35:dd:e8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19851,11,'vsol',2048,'cc:2d:21:6d:26:f8','2048','cc:2d:21:6d:26:f8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19852,11,'vsol',2002,'04:5e:a4:94:45:fb','2002','04:5e:a4:94:45:fb',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19853,11,'vsol',1933,'c0:a5:dd:1b:be:85','1933','c0:a5:dd:1b:be:85',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19854,11,'vsol',2196,'30:16:9d:f9:08:17','2196','30:16:9d:f9:08:17',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19855,11,'vsol',NULL,'38:3a:21:28:cb:16',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19856,11,'vsol',1935,'cc:2d:21:dc:c6:b0','1935','cc:2d:21:dc:c6:b0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19857,11,'vsol',2465,'58:d9:d5:ab:32:b0','2465','58:d9:d5:ab:32:b0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19858,11,'vsol',1993,'cc:2d:21:dc:c7:48','1993','cc:2d:21:dc:c7:48',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19859,11,'vsol',NULL,'4c:d7:c8:6c:52:95',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19860,11,'vsol',2136,'c8:3a:35:46:68:e0','2136','c8:3a:35:46:68:e0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19861,11,'vsol',2566,'08:40:f3:66:0d:b8','2566','08:40:f3:66:0d:b8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 11:54:07'),(19862,11,'vsol',2306,'08:40:f3:63:78:d8','2306','08:40:f3:63:78:d8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19863,11,'vsol',1923,'08:40:f3:66:0d:80','1923','08:40:f3:66:0d:80',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19864,11,'vsol',2588,'08:40:f3:22:80:a8','2588','08:40:f3:22:80:a8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19865,11,'vsol',2166,'60:83:e7:92:f6:f5','2166','60:83:e7:92:f6:f5',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19866,11,'vsol',2160,'b0:95:75:d7:16:2f','2160','b0:95:75:d7:16:2f',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19867,11,'vsol',2088,'cc:2d:21:4c:df:08','2088','cc:2d:21:4c:df:08',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19868,11,'vsol',2120,'cc:2d:21:62:64:a0','2120','cc:2d:21:62:64:a0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19869,11,'vsol',NULL,'a0:7f:06:26:2a:e1',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:34:05'),(19870,11,'vsol',2211,'b8:3a:08:fd:b5:f0','2211','b8:3a:08:fd:b5:f0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19871,11,'vsol',1970,'08:40:f3:22:04:f8','1970','08:40:f3:22:04:f8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19872,11,'vsol',NULL,'a0:7e:08:17:aa:75',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:24:04'),(19873,11,'vsol',1968,'50:0f:f5:bb:77:90','1968','50:0f:f5:bb:77:90',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19874,11,'vsol',2446,'50:0f:f5:e8:af:78','2446','50:0f:f5:e8:af:78',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19875,11,'vsol',1988,'d8:32:14:41:1f:70','1988','d8:32:14:41:1f:70',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19876,11,'vsol',2052,'58:d9:d5:ab:32:c8','2052','58:d9:d5:ab:32:c8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19877,11,'vsol',1962,'cc:2d:21:08:2e:07','1962','cc:2d:21:08:2e:07',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19878,11,'vsol',2434,'fc:34:97:56:9a:64','2434','fc:34:97:56:9a:64',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19879,11,'vsol',NULL,'a2:4f:09:08:39:90',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19880,11,'vsol',2234,'b4:b0:24:05:e3:d9','2234','b4:b0:24:05:e3:d9',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19881,11,'vsol',1967,'50:0f:f5:f1:4d:e0','1967','50:0f:f5:f1:4d:e0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19882,11,'vsol',2612,'08:40:f3:22:38:30','2612','08:40:f3:22:38:30',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19883,11,'vsol',2270,'b8:3a:08:6e:35:e0','2270','b8:3a:08:6e:35:e0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19884,11,'vsol',NULL,'50:0b:91:ef:50:0a',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19885,11,'vsol',1927,'d8:32:14:c0:d3:c0','1927','d8:32:14:c0:d3:c0',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19886,11,'vsol',2203,'d8:32:14:bf:40:50','2203','d8:32:14:bf:40:50',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19887,11,'vsol',NULL,'a0:7f:06:26:32:61',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19888,11,'vsol',1973,'00:72:63:dc:b2:11','1973','00:72:63:dc:b2:11',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19889,11,'vsol',2572,'d8:32:14:3b:25:58','2572','d8:32:14:3b:25:58',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19890,11,'vsol',2193,'cc:2d:21:62:04:28','2193','cc:2d:21:62:04:28',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19891,11,'vsol',2107,'cc:2d:21:28:52:80','2107','cc:2d:21:28:52:80',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19892,11,'vsol',1922,'b4:0f:3b:c2:31:48','1922','b4:0f:3b:c2:31:48',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19893,11,'vsol',NULL,'a0:7e:11:12:a6:09',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:34:05'),(19894,11,'vsol',1924,'08:40:f3:ec:8f:f8','1924','08:40:f3:ec:8f:f8',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19895,11,'vsol',2419,'b4:0f:3b:5a:83:78','2419','b4:0f:3b:5a:83:78',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19896,11,'vsol',1817,'cc:2d:21:dc:c6:38','1817','cc:2d:21:dc:c6:38',NULL,NULL,0,'PON 0/03','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(19897,12,'vsol',NULL,'4c:d7:c8:22:be:c2',NULL,NULL,NULL,NULL,0,'--','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:53'),(19922,12,'vsol',NULL,'a2:4f:06:17:40:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:53'),(19927,12,'vsol',NULL,'a2:8f:07:23:0b:e0',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:29:12'),(19935,12,'vsol',NULL,'a2:4f:08:26:d3:20',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:53'),(19949,12,'vsol',1808,'c4:70:0b:88:1b:49','1808','c4:70:0b:88:1b:49',NULL,NULL,0,'EPON 0/01','ONU 25',':',985.00,-22.15,'Power Off','2024-11-21 20:32:27','online','2026-01-14 15:40:54'),(19959,12,'vsol',NULL,'a2:8f:07:21:a5:c0',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:53'),(20034,12,'vsol',NULL,'c0:7e:40:b3:e7:40',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 15','',1141.00,NULL,'Power Off','2024-11-21 20:32:47','offline','2026-01-14 13:38:05'),(20035,12,'vsol',NULL,'a2:4f:05:06:17:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 17','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:55'),(20036,12,'vsol',NULL,'a2:8e:08:31:4d:d0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 54','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:55'),(20056,12,'vsol',1881,'3c:fa:d3:c2:35:c2','1881','3c:fa:d3:c2:35:c2',NULL,NULL,0,'EPON 0/02','ONU 29',':',1821.00,-17.80,'Power Off','2024-11-21 20:32:16','online','2026-01-14 15:40:55'),(20073,12,'vsol',NULL,'a2:3e:09:10:89:f1',NULL,NULL,NULL,NULL,0,'EPON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:29:14'),(20120,12,'vsol',NULL,'c0:7e:40:b2:c0:54',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 4','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:56'),(20123,12,'vsol',1858,'a0:7e:09:18:1b:e1','1858','a0:7e:09:18:1b:e1',NULL,NULL,0,'EPON 0/03','ONU 11',':',2457.00,-18.66,'Power Off','2024-11-21 20:32:20','online','2026-01-14 15:40:57'),(20126,12,'vsol',1949,'00:6d:61:ca:d2:29','1949','00:6d:61:ca:d2:29',NULL,NULL,0,'EPON 0/03','ONU 33',':',2290.00,-24.95,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:57'),(20127,12,'vsol',NULL,'a2:3e:07:31:96:61',NULL,NULL,NULL,NULL,0,'EPON 0/03','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:56'),(20168,12,'vsol',2438,'1c:ef:03:ae:6c:16','2438','1c:ef:03:ae:6c:16',NULL,NULL,0,'EPON 0/03','ONU 52',':',2285.00,-20.13,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:57'),(20173,12,'vsol',2592,'a0:7f:04:16:8b:8b','2592','a0:7f:04:16:8b:8b',NULL,NULL,0,'EPON 0/03','ONU 9',':',1990.00,-23.28,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:40:57'),(20245,12,'vsol',NULL,'a2:3e:04:09:83:a0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 6','',952.00,NULL,'Power Off','2024-11-19 05:48:05','offline','2026-01-14 15:40:58'),(20246,12,'vsol',2281,'20:0b:c7:ff:85:7c','2281','20:0b:c7:ff:85:7c',NULL,NULL,0,'EPON 0/03','ONU 45','',1028.00,NULL,'Power Off','2024-11-20 23:32:20','offline','2026-01-14 15:40:58'),(20249,12,'vsol',1787,'a0:7e:09:18:f3:c1','1787','a0:7e:09:18:f3:c1',NULL,NULL,0,'EPON 0/04','ONU 1',':',418.00,-13.15,'N/A',NULL,'online','2026-01-14 15:40:59'),(20264,12,'vsol',2332,'4c:d7:c8:13:b5:a5','2332','4c:d7:c8:13:b5:a5',NULL,NULL,0,'EPON 0/04','ONU 28',':',139.00,-13.44,'Power Off','2024-11-21 20:32:18','online','2026-01-14 15:40:59'),(20267,12,'vsol',NULL,'a2:3e:08:24:95:61',NULL,NULL,NULL,NULL,0,'EPON 0/04','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:58'),(20276,12,'vsol',NULL,'a2:4f:06:17:46:f0',NULL,NULL,NULL,NULL,0,'EPON 0/04','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:58'),(20288,12,'vsol',2494,'d4:9e:09:86:15:08','2494','d4:9e:09:86:15:08',NULL,NULL,0,'EPON 0/04','ONU 2',':',546.00,-11.62,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:58'),(20329,12,'vsol',NULL,'38:d4:a5:74:c2:3f',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 31','',259.00,NULL,'Power Off','2024-11-10 00:11:45','offline','2026-01-14 15:40:59'),(20330,12,'vsol',NULL,'a0:7d:01:04:38:68',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 32','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:40:59'),(20337,12,'vsol',NULL,'a2:8f:07:21:a5:e0',NULL,NULL,NULL,NULL,0,'EPON 0/05','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:59'),(20339,12,'vsol',2545,'a0:7f:04:17:5b:33','2545','d8:32:14:36:91:27',305,'2026-01-14 15:29:11',0,'EPON 0/05','ONU 3',':',1841.00,-15.93,'Power Off','2024-11-21 18:22:02','online','2026-01-14 15:29:18'),(20346,12,'vsol',2455,'d4:9e:09:56:51:c8','2455','d4:9e:09:56:51:c8',NULL,NULL,0,'EPON 0/05','ONU 15',':',1411.00,-27.21,'Wire Down','2024-11-21 21:25:41','offline','2026-01-14 15:41:00'),(20348,12,'vsol',2101,'4c:d7:c8:a1:71:fd','2101','4c:d7:c8:a1:71:fd',NULL,NULL,0,'EPON 0/05','ONU 25',':',952.00,-14.03,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:41:00'),(20362,12,'vsol',2379,'a0:7e:08:33:d6:b5','2379','a0:7e:08:33:d6:b5',NULL,NULL,0,'EPON 0/05','ONU 21',':',1144.00,-22.52,'Power Off','2024-11-21 20:32:15','online','2026-01-14 15:41:00'),(20365,12,'vsol',2389,'a0:7e:11:11:76:9d','2389','a0:7e:11:11:76:9d',NULL,NULL,0,'EPON 0/05','ONU 2',':',1162.00,-8.74,'Power Off','2024-11-21 20:32:24','online','2026-01-14 14:29:24'),(20366,12,'vsol',2272,'6c:68:a4:9f:5c:d9','2272','6c:68:a4:9f:5c:d9',NULL,NULL,0,'EPON 0/05','ONU 13',':',424.00,-22.68,'N/A',NULL,'online','2026-01-14 15:41:00'),(20373,12,'vsol',2347,'a0:7e:09:18:f4:41','2347','a0:7e:09:18:f4:41',NULL,NULL,0,'EPON 0/05','ONU 14',':',988.00,-17.19,'N/A',NULL,'online','2026-01-14 15:41:00'),(20469,12,'vsol',NULL,'00:d3:9e:66:d0:da',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 58','',0.00,NULL,'N/A',NULL,'offline','2026-01-14 15:41:00'),(20470,12,'vsol',NULL,'70:2e:22:0a:ac:36',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 61','',1185.00,NULL,'Power Off','2024-11-16 17:14:14','offline','2026-01-14 15:41:00'),(20488,12,'vsol',2338,'a0:7e:08:06:63:e1','2338','60:83:e7:2d:0c:9a',306,'2026-01-14 15:09:31',0,'EPON 0/06','ONU 1',':',1247.00,-11.64,'Power Off','2024-11-21 17:17:54','online','2026-01-14 15:09:46'),(20500,12,'vsol',2230,'6c:68:a4:c4:61:1e','2230','6c:68:a4:c4:61:1e',NULL,NULL,0,'EPON 0/06','',':',0.00,-21.74,'Power Off','2024-11-21 17:17:36','unknown','2026-01-14 15:41:01'),(20508,12,'vsol',2554,'6c:68:a4:d6:96:54','2554','6c:68:a4:d6:96:54',NULL,NULL,0,'EPON 0/06','ONU 28',':',3670.00,-20.46,'Power Off','2024-11-21 20:37:08','online','2026-01-14 15:41:01'),(20562,12,'vsol',NULL,'00:d3:9e:eb:6a:9a',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 5','',1388.00,NULL,'Wire Down','2024-11-10 17:03:16','offline','2026-01-14 15:41:02'),(20563,12,'vsol',NULL,'6c:68:a4:73:db:88',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 9','',1523.00,NULL,'Power Off','2024-11-20 07:31:53','offline','2026-01-14 15:41:02'),(20564,12,'vsol',NULL,'00:d5:9e:66:72:54',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 40','',3685.00,NULL,'Power Off','2024-11-05 16:27:04','offline','2026-01-14 15:41:02'),(20633,12,'vsol',NULL,'a2:4f:03:05:64:20',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 7','',1813.00,NULL,'Power Off','2024-11-20 22:38:39','offline','2026-01-14 15:41:03'),(20634,12,'vsol',NULL,'a0:7d:01:04:b5:a2',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 11','',4242.00,NULL,'Power Off','2024-11-18 21:09:32','offline','2026-01-14 15:41:03'),(20635,12,'vsol',NULL,'a2:7e:09:07:cf:b0',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 19','',1938.00,-19.83,'Power Off','2024-11-21 17:22:51','online','2026-01-14 15:41:03'),(20638,12,'vsol',2310,'4c:d7:c8:12:89:c9','2310','4c:d7:c8:12:89:c9',NULL,NULL,0,'EPON 0/08','ONU 26',':',3039.00,-15.77,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(20661,12,'vsol',2175,'80:07:1b:e1:54:a1','2175','80:07:1b:e1:54:a1',NULL,NULL,0,'EPON 0/08','ONU 27',':',1710.00,-22.08,'Power Off','2024-11-21 17:22:42','online','2026-01-14 15:41:04'),(20672,12,'vsol',2526,'a0:7f:06:13:02:50','2526','a0:7f:06:13:02:50',308,'2026-01-14 15:29:11',0,'EPON 0/08','ONU 18','5634-Halim',2988.00,-12.86,'Power Off','2024-11-21 17:22:49','online','2026-01-14 15:29:22'),(20679,12,'vsol',2316,'6c:68:a4:c4:66:a2','2316','6c:68:a4:c4:66:a2',NULL,NULL,0,'EPON 0/08','ONU 25',':',2472.00,-7.65,'Power Off','2024-11-08 14:49:36','online','2026-01-14 15:41:04'),(20738,12,'vsol',2213,'6c:68:a4:6f:88:4c','2213','6c:68:a4:6f:88:4c',NULL,NULL,0,'EPON 0/08','ONU 9','',951.00,NULL,'Power Off','2024-11-21 17:29:31','offline','2026-01-14 15:41:04'),(20739,12,'vsol',NULL,'dc:2c:6e:6f:e3:f7',NULL,NULL,NULL,NULL,0,'PON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:41:04'),(20797,11,'vsol',NULL,'a2:3e:08:25:97:b1',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 14:04:01'),(20804,11,'vsol',2304,'6c:68:a4:a6:17:88','2304','6c:68:a4:a6:17:88',NULL,NULL,0,'EPON 0/01','ONU 1','5479-Azizur-ST',2693.00,-19.63,'Power Off','2026-01-08 11:39:48','online','2026-01-14 15:40:32'),(20805,11,'vsol',2473,'4c:d7:c8:d1:ae:5a','2473','4c:d7:c8:d1:ae:5a',NULL,NULL,0,'EPON 0/01','ONU 3','5607-mohasin',1938.00,-16.20,'Power Off','2026-01-14 09:25:44','online','2026-01-14 15:40:32'),(20820,11,'vsol',2401,'4c:d7:c8:a1:f9:f1','2401','7c:f1:7e:88:30:84',201,'2026-01-14 15:34:01',0,'EPON 0/01','ONU 60','5571-Sajida',2028.00,-23.87,'Power Off','2026-01-14 09:25:38','online','2026-01-14 15:34:01'),(20868,11,'vsol',2290,'6c:68:a4:46:e4:25','2290','6c:68:a4:46:e4:25',NULL,NULL,0,'EPON 0/02','ONU 24',':',3233.00,-23.98,'Power Off','2026-01-13 15:30:40','online','2026-01-14 15:40:33'),(20888,11,'vsol',2292,'6c:68:a4:a0:2c:6d','2292','6c:68:a4:a0:2c:6d',NULL,NULL,0,'EPON 0/02','ONU 25','5466-Kawser',2892.00,-23.19,'Power Off','2026-01-13 10:32:10','online','2026-01-14 15:40:34'),(20920,11,'vsol',1819,'3c:fa:d3:c0:36:58','1819','3c:fa:d3:c0:36:58',NULL,NULL,0,'EPON 0/03','ONU 15','5119-Kamal',1387.00,-18.73,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:34'),(20922,11,'vsol',1789,'3c:fa:d3:c0:4e:06','1789','3c:fa:d3:c0:4e:06',NULL,NULL,0,'EPON 0/03','ONU 26',':',1562.00,-17.72,'Power Off','2026-01-14 09:25:36','online','2026-01-14 15:40:34'),(20930,11,'vsol',2403,'4c:d7:c8:a7:cb:f4','2403','3c:64:cf:66:d9:ee',203,'2026-01-14 15:34:01',0,'EPON 0/03','ONU 28','5572-CCDA',34.00,-20.76,'Power Off','2026-01-14 09:25:37','online','2026-01-14 15:34:03'),(20941,11,'vsol',2629,'10:af:78:fe:7d:21','2629','10:af:78:fe:7d:21',NULL,NULL,0,'EPON 0/03','ONU 25','',1233.00,NULL,'Power Off','2026-01-14 09:26:00','offline','2026-01-14 10:33:33'),(20943,11,'vsol',NULL,'4c:d7:c8:be:11:15',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(20944,11,'vsol',NULL,'50:0b:91:ef:4f:d7',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:35'),(20945,11,'vsol',NULL,'00:d3:9e:7a:10:a4',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 11:20:37'),(20946,11,'vsol',NULL,'50:0b:91:ef:56:31',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:24:04'),(20947,11,'vsol',NULL,'50:0b:91:ef:5d:12',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(20948,11,'vsol',NULL,'50:0b:91:ef:5d:48',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:04:23'),(20949,11,'vsol',NULL,'50:0b:91:ef:50:37',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(20950,11,'vsol',NULL,'a0:7f:06:25:ca:e1',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:34:05'),(20951,11,'vsol',NULL,'a0:7f:07:20:23:f1',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(21065,11,'vsol',2495,'d4:9e:08:93:10:a7','2495','b8:3a:08:7c:d5:87',201,'2026-01-14 14:54:02',0,'EPON 0/01','ONU 38','5615-Sanaullah',2351.00,-21.80,'Wire Down','2026-01-14 09:37:38','online','2026-01-14 14:54:04'),(21112,11,'vsol',2296,'6c:68:a4:e0:62:4d','2296','d8:32:14:d6:f0:a8',202,'2026-01-14 15:34:01',0,'EPON 0/02','ONU 26','5470-Roma',3551.00,-20.46,'Power Off','2026-01-13 15:30:41','online','2026-01-14 15:34:02'),(21154,11,'vsol',NULL,'a0:7e:11:11:a3:25',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(21306,12,'vsol',2358,'a0:7e:09:19:1b:51','2358','a0:7e:09:19:1b:51',NULL,NULL,0,'EPON 0/02','ONU 1',':',836.00,-22.37,'Power Off','2024-11-21 20:32:22','online','2026-01-14 15:40:55'),(21494,12,'vsol',2628,'10:af:8f:72:ee:cb','2628','10:af:8f:72:ee:cb',NULL,NULL,0,'EPON 0/03','ONU 53','',2190.00,NULL,'Power Off','2024-11-21 20:32:58','offline','2026-01-14 13:38:09'),(21609,12,'vsol',NULL,'a2:3e:09:10:84:41',NULL,NULL,NULL,NULL,0,'EPON 0/05','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:29:18'),(21900,12,'vsol',NULL,'a2:8f:07:23:0c:60',NULL,NULL,NULL,NULL,0,'EPON 0/08','','',0.00,NULL,'',NULL,'unknown','2026-01-13 20:30:05'),(22098,12,'vsol',1839,'60:d2:dd:19:5b:a2','1839','60:d2:dd:19:5b:a2',NULL,NULL,0,'EPON 0/01','ONU 4','',3226.00,NULL,'Power Off','2024-11-21 19:28:43','offline','2026-01-14 15:40:55'),(22801,10,'vsol',2579,'a0:7f:04:16:76:af','2579','50:0f:f5:67:d9:08',101,'2026-01-14 15:21:41',0,'EPON 0/01','ONU 29',':',757.00,-16.97,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:21:41'),(22975,11,'vsol',2063,'d4:9e:09:47:bc:f3','2063','d4:9e:09:47:bc:f3',203,'2026-01-14 13:43:58',0,'EPON 0/03','ONU 7','5299-Morshed',1406.00,-16.90,'Power Off','2026-01-14 09:25:35','online','2026-01-14 13:44:02'),(22987,10,'vsol',2623,'18:d6:c7:84:3b:ef','2623','18:d6:c7:84:3b:ef',NULL,NULL,0,'EPON 0/01','ONU 31',':',875.00,-17.42,'Power Off','2026-01-14 13:33:12','online','2026-01-14 15:40:16'),(23717,12,'vsol',2359,'a0:7e:09:17:18:30','2359','b8:3a:08:f8:a3:20',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 9','',1983.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:43'),(23818,11,'vsol',NULL,'a2:3e:09:10:01:f1',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:04:17'),(23965,11,'vsol',1805,'00:11:41:2a:71:98','1805','00:11:41:2a:71:98',NULL,NULL,0,'EPON 0/03','ONU 23','',1334.00,NULL,'Power Off','2026-01-14 09:25:44','offline','2026-01-14 10:33:32'),(23967,11,'vsol',NULL,'3c:fa:d3:c0:46:ee',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:34:04'),(25826,10,'vsol',NULL,'6c:68:a4:e8:aa:07',NULL,'58:d9:d5:eb:19:e0',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 1','',747.00,-18.27,'Power Off','2026-01-14 13:33:10','offline','2026-01-14 13:41:39'),(25894,11,'vsol',NULL,'a2:3e:09:26:95:21',NULL,NULL,NULL,NULL,0,'EPON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:33'),(26071,11,'vsol',2387,'a0:7e:11:11:7c:af','2387','50:0f:f5:2e:97:89',201,'2026-01-14 14:54:02',0,'EPON 0/01','ONU 22','5560-Sorifa',2411.00,-12.94,'Power Off','2026-01-14 09:25:35','online','2026-01-14 14:54:03'),(26177,12,'vsol',2351,'a0:7e:09:19:25:51','2351','a0:7e:09:19:25:51',NULL,NULL,0,'EPON 0/01','ONU 2','default',1182.00,-27.96,'Power Off','2024-11-21 20:32:24','online','2026-01-14 15:40:54'),(26179,12,'vsol',NULL,'6a:7e:4a:6e:46:05',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-13 18:35:41'),(26225,12,'vsol',NULL,'b2:1f:f5:bb:d4:51',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-13 18:35:42'),(26639,12,'vsol',NULL,'a2:3e:09:10:01:a1',NULL,NULL,NULL,NULL,0,'EPON 0/05','','',0.00,NULL,'',NULL,'unknown','2026-01-14 14:29:24'),(26822,12,'vsol',NULL,'a2:3e:09:24:37:61',NULL,NULL,NULL,NULL,0,'EPON 0/07','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:41:02'),(27034,11,'vsol',2151,'a0:7f:06:31:14:15','2151','a0:7f:06:31:14:15',201,'2026-01-14 15:04:17',0,'EPON 0/01','ONU 15','5365-Depak',2028.00,-21.87,'Power Off','2026-01-14 09:25:39','online','2026-01-14 15:04:17'),(27156,11,'vsol',2398,'a0:7e:11:12:ec:83','2398','dc:8e:8d:b5:f8:77',203,'2026-01-14 15:34:01',0,'EPON 0/03','ONU 27','5569-Mizan',126.00,-24.81,'Power Off','2026-01-14 09:25:40','online','2026-01-14 15:34:03'),(27180,11,'vsol',NULL,'00:d3:9e:7a:46:c2',NULL,NULL,NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:40:36'),(29038,12,'vsol',2388,'00:d3:9e:b8:0a:f4','2388','d8:32:14:1e:41:a8',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 22','',1711.00,NULL,'Power Off','2024-11-21 17:22:46','offline','2026-01-14 10:37:45'),(29722,11,'vsol',2162,'6c:68:a4:74:87:82','2162','6c:68:a4:74:87:82',NULL,NULL,0,'EPON 0/01','ONU 24','',2700.00,NULL,'Power Off','2026-01-14 12:40:04','offline','2026-01-14 13:44:00'),(29723,11,'vsol',2340,'a2:4e:07:19:84:00','2340','a2:4e:07:19:84:00',NULL,NULL,0,'EPON 0/01','ONU 36','',2854.00,NULL,'Power Off','2026-01-14 12:40:04','offline','2026-01-14 13:44:00'),(29724,11,'vsol',NULL,'10:af:af:81:df:45',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 48','',2939.00,NULL,'Power Off','2026-01-14 12:40:05','offline','2026-01-14 13:44:00'),(29725,11,'vsol',1875,'e4:2d:7b:5d:46:8f','1875','e4:2d:7b:5d:46:8f',NULL,NULL,0,'EPON 0/01','ONU 53','',2900.00,NULL,'Power Off','2026-01-14 12:40:04','offline','2026-01-14 13:44:00'),(30007,12,'vsol',NULL,'a2:3e:09:10:2d:b1',NULL,NULL,NULL,NULL,0,'EPON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:09:35'),(30326,12,'vsol',2545,'08:40:f3:e6:30:27',NULL,'a0:7f:04:17:5b:33',305,'2026-01-13 18:55:43',0,'EPON 0/05','ONU 3','',1839.00,NULL,'Power Off','2024-11-21 01:50:40','online','2026-01-13 18:56:11'),(30494,12,'vsol',2557,'a2:4f:07:18:14:e0','2557','e8:65:d4:27:8e:70',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 18','',3926.00,NULL,'Power Off','2024-11-21 20:37:10','offline','2026-01-14 13:49:26'),(30495,12,'vsol',2134,'30:3d:51:e4:2f:c8','2134','dc:8e:8d:68:fc:49',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 22','',6255.00,NULL,'Power Off','2024-11-21 20:37:08','offline','2026-01-14 13:49:26'),(30496,12,'vsol',1828,'a0:a3:3b:21:9d:9d','1828','e8:65:d4:fc:e2:48',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 24','',4224.00,NULL,'Wire Down','2024-11-21 20:37:11','offline','2026-01-14 13:49:26'),(30497,12,'vsol',1827,'54:93:59:31:82:bc','1827','c0:25:2f:a1:97:f5',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 27','',5708.00,NULL,'Power Off','2024-11-21 20:37:08','offline','2026-01-14 13:49:27'),(30498,12,'vsol',2554,'6c:68:a4:d6:96:53','2554','6c:68:a4:d6:96:54',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 28','',3670.00,NULL,'Power Off','2024-11-21 20:37:08','offline','2026-01-14 13:49:27'),(30499,12,'vsol',2336,'30:f3:35:e8:78:b4','2336','64:64:4a:27:05:26',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 32','',3900.00,NULL,'Power Off','2024-11-21 20:37:12','offline','2026-01-14 13:49:27'),(30500,12,'vsol',1900,'a2:8b:07:31:54:f0','1900','50:0f:f5:dd:4d:a8',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 33','',3631.00,NULL,'Power Off','2024-11-21 20:37:11','offline','2026-01-14 13:49:27'),(30501,12,'vsol',2472,'a2:4e:12:29:29:80','2472','ec:75:0c:c1:63:31',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 37','',4213.00,NULL,'Power Off','2024-11-21 20:37:10','offline','2026-01-14 13:49:27'),(30502,12,'vsol',2365,'00:d3:9e:6e:cd:14','2365','50:0f:f5:da:2a:58',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 38','',3874.00,NULL,'Power Off','2024-11-21 20:37:11','offline','2026-01-14 13:49:27'),(30503,12,'vsol',2614,'c0:7e:40:b3:e5:c2','2614','c0:25:2f:a1:97:a1',306,'2026-01-14 13:49:12',0,'EPON 0/06','ONU 41','',3785.00,NULL,'Power Off','2024-11-21 20:37:11','offline','2026-01-14 13:49:27'),(30880,11,'vsol',2342,'a2:3e:09:10:88:e0','2342','a2:3e:09:10:88:e0',NULL,NULL,0,'EPON 0/03','ONU 20','',764.00,NULL,'Power Off','2026-01-14 09:25:37','offline','2026-01-14 10:33:32'),(31201,11,'vsol',2551,'a2:4e:05:25:b5:b0','2551','a2:4e:05:25:b5:b0',NULL,NULL,0,'EPON 0/01','ONU 58','',1969.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:31'),(31692,12,'vsol',2630,'10:af:8f:72:fe:d9','2630','10:af:8f:72:fe:d9',NULL,NULL,0,'EPON 0/04','ONU 36','',457.00,NULL,'Power Off','2024-11-21 02:22:14','offline','2026-01-14 15:40:59'),(32750,12,'vsol',2186,'a0:7e:03:17:03:4d','2186','a0:7e:03:17:03:4d',303,'2026-01-14 15:29:11',0,'EPON 0/03','ONU 23',':',2741.00,-23.47,'Power Off','2024-11-21 20:32:19','online','2026-01-14 15:29:16'),(33185,12,'vsol',NULL,'a0:7d:01:04:64:7e',NULL,'18:fd:74:dd:1a:fd',307,'2026-01-14 08:39:22',0,'EPON 0/07','ONU 32','',2674.00,NULL,'Power Off','2024-11-21 02:05:59','offline','2026-01-14 08:39:39'),(33288,11,'vsol',NULL,'a2:3e:11:24:2b:e1',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 15:34:01'),(33390,11,'vsol',2141,'cc:2d:21:78:90:48','2141','cc:2d:21:78:90:48',NULL,NULL,0,'EPON 0/02','ONU 20','5358-Rasel',3111.00,-18.12,'Power Off','2026-01-14 14:15:29','online','2026-01-14 15:40:34'),(34421,12,'vsol',2213,'d8:32:14:f3:77:60','2213','d8:32:14:f3:77:60',NULL,NULL,0,'EPON 0/08','',':',0.00,-15.83,'Power Off','2024-11-20 17:50:51','unknown','2026-01-14 10:21:11'),(34675,11,'vsol',1878,'4c:d7:c8:13:5b:c5','1878','4c:d7:c8:13:5b:c5',201,'2026-01-14 08:34:03',0,'EPON 0/01','ONU 29',':',2219.00,-7.32,'Power Off','2026-01-13 16:53:34','online','2026-01-14 08:34:03'),(34726,11,'vsol',1878,'5c:62:8b:cf:19:d9','1878','4c:d7:c8:13:5b:c5',201,'2026-01-14 08:34:03',0,'EPON 0/01','ONU 29',':',2219.00,-7.32,'Power Off','2026-01-13 16:53:34','online','2026-01-14 08:34:04'),(35973,10,'vsol',2579,'50:0f:f5:67:d9:08','2579','50:0f:f5:67:d9:08',NULL,NULL,0,'EPON 0/01','ONU 29',':',757.00,-16.93,'Power Off','2026-01-14 13:33:02','online','2026-01-14 15:40:16'),(38762,12,'vsol',NULL,'c0:7e:40:b2:c0:78',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 1','',1185.00,NULL,'Wire Down','2024-11-07 09:56:40','online','2026-01-13 19:57:46'),(38763,12,'vsol',NULL,'a0:7e:09:19:25:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 2','',1182.00,NULL,'Power Off','2024-11-21 20:32:24','offline','2026-01-14 13:38:04'),(38764,12,'vsol',NULL,'c0:7e:40:b2:c3:b4',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 3','',1783.00,NULL,'Wire Down','2024-11-21 20:32:25','offline','2026-01-14 13:38:04'),(38766,12,'vsol',NULL,'a2:4f:08:26:d3:28',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 5','',1172.00,NULL,'Power Off','2024-11-21 20:32:54','offline','2026-01-14 13:38:04'),(38767,12,'vsol',NULL,'a0:7f:04:17:5b:9e',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 6','',1218.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:04'),(38768,12,'vsol',NULL,'4c:f9:a3:ed:26:88',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 7','',2077.00,NULL,'Wire Down','2024-11-21 19:57:19','offline','2026-01-14 13:00:53'),(38769,12,'vsol',NULL,'00:d3:9e:eb:c3:e0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 8','',1256.00,NULL,'Power Off','2024-11-21 20:32:44','offline','2026-01-14 13:38:04'),(38770,12,'vsol',NULL,'38:3a:21:27:d5:e3',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 9','',1179.00,NULL,'Power Off','2024-11-21 20:32:43','offline','2026-01-14 13:38:04'),(38771,12,'vsol',NULL,'1c:ef:03:e8:8b:ea',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 10','',1116.00,NULL,'Power Off','2024-11-21 20:32:53','offline','2026-01-14 13:38:05'),(38772,12,'vsol',NULL,'30:3d:51:e2:22:e4',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 11','',3482.00,NULL,'Power Off','2024-11-21 20:32:20','offline','2026-01-14 13:38:05'),(38773,12,'vsol',NULL,'c0:7e:40:a8:66:c8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 12','',1003.00,NULL,'Power Off','2024-11-21 20:32:42','offline','2026-01-14 13:38:05'),(38774,12,'vsol',NULL,'b4:64:15:ee:7e:3a',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 13','',1098.00,NULL,'Power Off','2024-11-21 20:32:55','offline','2026-01-14 13:38:05'),(38775,12,'vsol',NULL,'b4:64:15:ee:af:1a',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 14','',2015.00,NULL,'Power Off','2024-11-21 20:33:01','offline','2026-01-14 13:38:05'),(38776,12,'vsol',NULL,'a0:7d:04:23:23:00',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 16','',3385.00,NULL,'Wire Down','2024-11-20 22:10:44','online','2026-01-13 19:57:46'),(38777,12,'vsol',NULL,'4c:f9:a3:e8:3f:ce',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 18','',1939.00,NULL,'Power Off','2024-11-17 20:18:46','online','2026-01-13 19:57:46'),(38778,12,'vsol',NULL,'a2:7e:09:07:68:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 19','',1934.00,NULL,'Power Off','2024-11-05 21:57:12','online','2026-01-13 19:57:46'),(38779,12,'vsol',NULL,'a2:8f:07:21:a5:c8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 20','',1123.00,NULL,'Power Off','2024-11-21 20:33:07','offline','2026-01-14 13:38:05'),(38780,12,'vsol',NULL,'a0:7d:09:09:52:6c',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 21','',3324.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:05'),(38781,12,'vsol',NULL,'30:3d:51:e4:a2:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 22','',3193.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:05'),(38782,12,'vsol',NULL,'00:d3:9e:ed:4e:42',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 23','',3431.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:05'),(38783,12,'vsol',NULL,'30:3d:51:e5:97:44',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 24','',3354.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:05'),(38784,12,'vsol',NULL,'c4:70:0b:88:1b:48',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 25','',985.00,NULL,'Power Off','2024-11-21 20:32:27','offline','2026-01-14 13:38:05'),(38785,12,'vsol',NULL,'30:3d:51:e5:97:5c',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 26','',3293.00,NULL,'Power Off','2024-11-21 20:32:24','offline','2026-01-14 13:38:05'),(38786,12,'vsol',NULL,'a2:4f:06:17:40:f8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 27','',1679.00,NULL,'Power Off','2024-11-21 20:32:31','offline','2026-01-14 13:38:05'),(38787,12,'vsol',NULL,'a0:7d:09:15:28:66',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 28','',3610.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:05'),(38789,12,'vsol',NULL,'a2:4f:05:06:25:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 30','',1897.00,NULL,'Power Off','2024-11-21 20:33:02','offline','2026-01-14 13:38:05'),(38790,12,'vsol',NULL,'c4:70:0b:5c:fe:a0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 31','',1854.00,NULL,'Power Off','2024-11-21 20:32:26','offline','2026-01-14 13:38:05'),(38791,12,'vsol',NULL,'a2:4f:04:17:c6:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 32','',1136.00,NULL,'Power Off','2024-11-21 20:32:40','offline','2026-01-14 13:38:05'),(38792,12,'vsol',NULL,'4c:d7:c8:ce:6e:b9',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 33','',1664.00,NULL,'Power Off','2024-11-21 02:05:54','online','2026-01-13 19:57:47'),(38793,12,'vsol',NULL,'30:3d:51:e4:47:98',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 34','',3418.00,NULL,'Power Off','2024-11-16 17:18:36','online','2026-01-13 19:57:47'),(38794,12,'vsol',NULL,'a0:7b:10:18:04:ae',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 35','',3513.00,NULL,'Power Off','2024-11-21 20:32:20','offline','2026-01-14 13:38:05'),(38795,12,'vsol',NULL,'c4:70:0b:5d:40:b8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 36','',1490.00,NULL,'Power Off','2024-11-02 07:16:26','online','2026-01-13 19:57:47'),(38796,12,'vsol',NULL,'a2:8f:07:23:0b:e8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 37','',995.00,NULL,'Power Off','2024-11-21 20:32:47','offline','2026-01-14 13:38:05'),(38797,12,'vsol',NULL,'a2:3e:03:26:25:40',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 38','',1524.00,NULL,'Power Off','2024-11-21 20:32:34','offline','2026-01-14 13:38:05'),(38798,12,'vsol',NULL,'a2:5d:03:15:7e:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 39','',1287.00,NULL,'Power Off','2024-11-21 20:32:35','offline','2026-01-14 13:38:05'),(38799,12,'vsol',NULL,'a2:4e:12:2a:d6:00',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 40','',1198.00,NULL,'Power Off','2024-11-21 20:32:58','offline','2026-01-14 13:38:05'),(38800,12,'vsol',NULL,'6c:68:a4:47:4e:74',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 41','',1585.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:05'),(38801,12,'vsol',NULL,'30:3d:51:e4:4b:e8',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 42','',3505.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:05'),(38802,12,'vsol',NULL,'30:3d:51:e4:55:30',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 43','',3477.00,NULL,'Power Off','2024-11-21 20:32:26','offline','2026-01-14 13:38:05'),(38803,12,'vsol',NULL,'a0:a3:3b:20:bf:e3',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 44','',1083.00,NULL,'Power Off','2024-11-21 20:32:55','offline','2026-01-14 13:38:05'),(38804,12,'vsol',NULL,'a2:7e:09:11:aa:10',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 45','',1254.00,NULL,'Power Off','2024-11-21 20:32:46','offline','2026-01-14 13:38:05'),(38805,12,'vsol',NULL,'a2:4f:02:10:5d:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 46','',1375.00,NULL,'Power Off','2024-11-21 20:32:35','offline','2026-01-14 13:38:05'),(38806,12,'vsol',NULL,'a2:4f:02:10:3c:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 47','',1180.00,NULL,'Power Off','2024-11-21 20:32:36','offline','2026-01-14 13:38:05'),(38807,12,'vsol',NULL,'80:d4:a5:29:ab:cf',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 48','',1288.00,NULL,'Power Off','2024-11-21 20:32:48','offline','2026-01-14 13:38:05'),(38808,12,'vsol',NULL,'a2:3e:03:26:36:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 49','',1338.00,NULL,'Power Off','2024-11-21 20:32:57','offline','2026-01-14 13:38:05'),(38809,12,'vsol',NULL,'38:d4:a5:72:c2:4f',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 50','',1677.00,NULL,'Power Off','2024-11-21 20:32:49','offline','2026-01-14 13:38:05'),(38810,12,'vsol',NULL,'00:d5:9e:33:81:57',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 51','',1193.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:47'),(38811,12,'vsol',NULL,'38:d4:a5:72:e2:0f',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 52','',1251.00,NULL,'Power Off','2024-11-21 20:32:59','offline','2026-01-14 13:38:05'),(38812,12,'vsol',NULL,'a2:8e:07:03:03:d0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 53','',1210.00,NULL,'Power Off','2024-11-21 20:32:42','offline','2026-01-14 13:38:05'),(38813,12,'vsol',NULL,'4c:64:f5:aa:04:9a',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 55','',1357.00,NULL,'Power Off','2024-11-21 20:33:07','offline','2026-01-14 13:38:05'),(38814,12,'vsol',NULL,'a2:4e:03:22:09:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 56','',1320.00,NULL,'Power Off','2024-11-21 20:32:54','offline','2026-01-14 13:38:05'),(38815,12,'vsol',NULL,'74:88:2a:6e:29:bd',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 57','',1233.00,NULL,'Power Off','2024-11-21 20:32:39','offline','2026-01-14 13:38:05'),(38816,12,'vsol',NULL,'b4:64:15:ed:ba:ea',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 58','',1757.00,NULL,'Power Off','2024-11-21 20:32:51','offline','2026-01-14 13:38:05'),(38817,12,'vsol',NULL,'a2:3e:03:25:c5:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 59','',1365.00,NULL,'Power Off','2024-11-21 20:32:30','offline','2026-01-14 13:38:05'),(38818,12,'vsol',NULL,'a0:7e:09:19:1b:50',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 1','',836.00,NULL,'Power Off','2024-11-21 20:32:22','offline','2026-01-14 13:38:06'),(38819,12,'vsol',NULL,'a2:3e:09:10:2d:b0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 2','',1287.00,NULL,'Power Off','2024-11-21 20:32:24','offline','2026-01-14 13:38:06'),(38820,12,'vsol',NULL,'c0:7e:40:b0:6d:83',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 3','',1801.00,NULL,'Wire Down','2024-11-21 20:32:37','offline','2026-01-14 13:38:06'),(38821,12,'vsol',NULL,'c0:7e:40:b2:c2:d5',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 5','',729.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:06'),(38822,12,'vsol',NULL,'00:d3:9e:66:cd:86',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 6','',915.00,NULL,'Power Off','2024-11-21 20:32:28','offline','2026-01-14 13:38:06'),(38823,12,'vsol',NULL,'38:d4:a5:79:e6:0f',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 7','',733.00,NULL,'Power Off','2024-11-21 20:33:05','offline','2026-01-14 13:38:07'),(38824,12,'vsol',NULL,'a2:7e:09:07:83:60',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 8','',682.00,NULL,'Power Off','2024-11-21 20:32:59','offline','2026-01-14 13:38:07'),(38825,12,'vsol',NULL,'b0:7c:07:15:31:95',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 9','',746.00,NULL,'Power Off','2024-11-21 20:32:53','offline','2026-01-14 13:38:07'),(38826,12,'vsol',NULL,'98:c7:a4:01:c3:3a',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 10','',1901.00,NULL,'Power Off','2024-11-21 20:32:39','offline','2026-01-14 13:38:07'),(38827,12,'vsol',NULL,'b4:64:15:bb:89:93',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 11','',756.00,NULL,'Power Off','2024-11-21 20:33:10','offline','2026-01-14 13:38:07'),(38828,12,'vsol',NULL,'c0:7e:40:a7:24:7c',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 12','',3870.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:07'),(38829,12,'vsol',NULL,'a2:3e:09:10:89:f0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 13','',806.00,NULL,'Power Off','2024-11-21 20:32:23','offline','2026-01-14 13:38:07'),(38830,12,'vsol',NULL,'a2:4d:12:28:87:70',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 14','',1815.00,NULL,'Power Off','2024-11-21 20:32:47','offline','2026-01-14 13:38:07'),(38831,12,'vsol',NULL,'a2:3e:12:06:7f:b0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 15','',1942.00,NULL,'Power Off','2024-11-21 20:32:27','offline','2026-01-14 13:38:07'),(38832,12,'vsol',NULL,'a2:7e:09:11:7a:b0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 16','',674.00,NULL,'Power Off','2024-11-21 20:33:09','offline','2026-01-14 13:38:07'),(38833,12,'vsol',NULL,'00:d5:9e:03:4f:b8',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 17','',1303.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:47'),(38834,12,'vsol',NULL,'30:3d:51:e3:61:dc',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 18','',2747.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:07'),(38835,12,'vsol',NULL,'a0:7d:11:20:1b:94',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 19','',3501.00,NULL,'Power Off','2024-11-21 20:32:26','offline','2026-01-14 13:38:07'),(38836,12,'vsol',NULL,'30:3d:51:e3:60:98',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 20','',3990.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:07'),(38837,12,'vsol',NULL,'a0:7d:09:15:3f:b8',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 21','',3910.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:07'),(38838,12,'vsol',NULL,'a2:5d:02:10:79:c0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 22','',831.00,NULL,'Power Off','2024-11-21 19:35:12','offline','2026-01-14 13:00:54'),(38839,12,'vsol',NULL,'a2:7e:09:11:9a:00',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 23','',777.00,NULL,'Power Off','2024-11-21 20:32:54','offline','2026-01-14 13:38:07'),(38840,12,'vsol',NULL,'80:d4:a5:1b:32:9f',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 24','',1867.00,NULL,'Power Off','2024-11-21 20:32:50','offline','2026-01-14 13:38:07'),(38842,12,'vsol',NULL,'c4:70:0b:33:90:e0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 26','',1320.00,NULL,'Power Off','2024-11-21 20:32:25','offline','2026-01-14 13:38:07'),(38843,12,'vsol',NULL,'a0:7d:10:12:1e:52',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 27','',3580.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:07'),(38844,12,'vsol',NULL,'30:3d:51:e3:61:16',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 28','',3965.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:07'),(38847,12,'vsol',NULL,'a2:3d:11:12:2b:f0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 31','',777.00,NULL,'Power Off','2024-11-21 20:33:06','offline','2026-01-14 13:38:07'),(38848,12,'vsol',NULL,'00:d3:9e:b2:66:fc',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 32','',1379.00,NULL,'Power Off','2024-11-21 20:32:46','offline','2026-01-14 13:38:07'),(38849,12,'vsol',NULL,'00:d5:9e:78:b2:fc',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 33','',1877.00,NULL,'Power Off','2024-11-21 20:32:37','offline','2026-01-14 13:38:07'),(38851,12,'vsol',NULL,'38:d4:a5:72:e1:1f',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 35','',782.00,NULL,'Wire Down','2024-11-21 20:32:59','offline','2026-01-14 13:38:07'),(38852,12,'vsol',NULL,'a2:8b:07:31:72:60',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 36','',865.00,NULL,'Power Off','2024-11-21 20:32:38','offline','2026-01-14 13:38:07'),(38853,12,'vsol',NULL,'00:d3:9e:8a:0f:48',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 37','',1852.00,-13.80,'Power Off','2024-11-21 20:32:49','offline','2026-01-14 13:38:07'),(38855,12,'vsol',NULL,'a2:3e:07:31:96:60',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 1','',2470.00,NULL,'Power Off','2024-11-21 20:32:23','offline','2026-01-14 13:38:09'),(38856,12,'vsol',NULL,'b0:7c:07:15:52:6b',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 2','',800.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:09'),(38857,12,'vsol',NULL,'c0:7e:40:b2:c2:45',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 3','',1275.00,NULL,'Wire Down','2024-11-21 20:32:39','offline','2026-01-14 13:38:09'),(38858,12,'vsol',NULL,'a2:4f:06:1a:4b:d0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 4','',1060.00,NULL,'Power Off','2024-11-21 20:33:03','offline','2026-01-14 13:38:09'),(38859,12,'vsol',NULL,'a2:3d:08:07:4a:f0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 5','',2718.00,NULL,'Power Off','2024-11-21 20:32:48','offline','2026-01-14 13:38:09'),(38860,12,'vsol',NULL,'a2:4f:01:07:fc:f0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 7','',1608.00,NULL,'Power Off','2024-11-21 20:33:04','offline','2026-01-14 13:38:09'),(38861,12,'vsol',NULL,'6c:68:a4:27:80:7e',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 8','',1349.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:09'),(38862,12,'vsol',NULL,'a0:7f:04:16:8b:8a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 9','',1990.00,NULL,'Power Off','2024-11-21 20:32:15','offline','2026-01-14 13:38:09'),(38863,12,'vsol',NULL,'98:c7:a4:01:c3:78',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 10','',2201.00,NULL,'Power Off','2024-11-21 20:32:37','offline','2026-01-14 13:38:09'),(38864,12,'vsol',NULL,'a0:7e:09:18:1b:e0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 11','',2460.00,NULL,'Power Off','2024-11-21 20:32:20','offline','2026-01-14 13:38:09'),(38865,12,'vsol',NULL,'1c:ef:03:c1:4a:e4',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 12','',721.00,NULL,'Power Off','2024-11-21 20:32:46','offline','2026-01-14 13:38:09'),(38866,12,'vsol',NULL,'a0:7f:08:05:99:40',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 13','',2900.00,NULL,'Power Off','2024-11-21 20:32:22','offline','2026-01-14 13:38:09'),(38867,12,'vsol',NULL,'30:3d:51:e5:97:56',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 14','',2769.00,NULL,'Power Off','2024-11-21 20:32:24','offline','2026-01-14 13:38:09'),(38868,12,'vsol',NULL,'b4:64:15:ba:c1:47',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 15','',2790.00,NULL,'Power Off','2024-11-21 20:32:45','offline','2026-01-14 13:38:09'),(38869,12,'vsol',NULL,'00:d3:9e:b8:0a:b2',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 16','',821.00,NULL,'Power Off','2024-11-21 20:33:08','offline','2026-01-14 13:38:09'),(38870,12,'vsol',NULL,'a2:5d:03:17:5d:a0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 17','',1546.00,-21.87,'Power Off','2024-11-21 20:32:50','offline','2026-01-14 13:38:09'),(38871,12,'vsol',NULL,'30:3d:51:e7:be:da',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 18','',2544.00,-19.96,'Wire Down','2024-11-21 20:32:54','offline','2026-01-14 13:38:09'),(38872,12,'vsol',NULL,'00:d3:9e:8f:1d:d2',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 19','',803.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:09'),(38873,12,'vsol',NULL,'30:3d:51:e5:34:60',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 20','',2910.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:09'),(38874,12,'vsol',NULL,'30:3d:51:e4:a2:7a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 21','',3033.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:09'),(38875,12,'vsol',NULL,'4c:d7:c8:83:3f:fb',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 22','',2726.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:09'),(38876,12,'vsol',NULL,'a0:7e:03:17:03:4c',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 23','',2742.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:09'),(38877,12,'vsol',NULL,'a0:7f:06:13:02:98',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 24','',849.00,NULL,'Power Off','2024-11-21 20:32:40','offline','2026-01-14 13:38:09'),(38878,12,'vsol',NULL,'30:3d:51:e6:ec:06',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 25','',3429.00,NULL,'Power Off','2024-11-21 20:32:23','offline','2026-01-14 13:38:09'),(38879,12,'vsol',NULL,'30:3d:51:e4:54:f4',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 26','',4206.00,NULL,'Power Off','2024-11-21 20:32:25','offline','2026-01-14 13:38:09'),(38880,12,'vsol',NULL,'b4:64:15:ee:de:aa',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 27','',2288.00,NULL,'Power Off','2024-11-21 20:32:51','offline','2026-01-14 13:38:09'),(38881,12,'vsol',NULL,'80:14:a8:88:57:08',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 28','',2288.00,NULL,'Power Off','2024-11-21 12:35:06','offline','2026-01-14 05:51:04'),(38882,12,'vsol',NULL,'a2:8c:10:18:cb:80',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 29','',1875.00,NULL,'Power Off','2024-11-21 20:32:52','offline','2026-01-14 13:38:09'),(38883,12,'vsol',NULL,'4c:d7:c8:80:15:72',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 30','',2757.00,NULL,'Power Off','2024-11-17 03:32:43','online','2026-01-13 19:57:48'),(38884,12,'vsol',NULL,'30:3d:51:e7:6e:16',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 31','',2897.00,NULL,'Power Off','2024-11-21 20:32:26','offline','2026-01-14 13:38:09'),(38885,12,'vsol',NULL,'30:3d:51:e2:06:9a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 32','',4090.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:09'),(38886,12,'vsol',NULL,'00:6d:61:ca:d2:28',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 33','',2290.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:09'),(38887,12,'vsol',NULL,'00:d3:9e:20:5b:bc',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 34','',2636.00,NULL,'Wire Down','2024-11-21 17:07:45','offline','2026-01-14 10:21:00'),(38888,12,'vsol',NULL,'30:3d:51:e3:8f:48',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 35','',2013.00,NULL,'Power Off','2024-11-21 20:32:57','offline','2026-01-14 13:38:09'),(38889,12,'vsol',NULL,'9c:28:ef:7c:81:c1',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 36','',738.00,NULL,'Power Off','2024-11-21 20:32:28','offline','2026-01-14 13:38:09'),(38890,12,'vsol',NULL,'48:ad:08:51:e4:93',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 37','',2897.00,NULL,'Power Off','2024-11-21 20:32:29','offline','2026-01-14 13:38:09'),(38891,12,'vsol',NULL,'00:d3:9e:b8:0b:c0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 38','',923.00,NULL,'Power Off','2024-11-21 20:32:53','offline','2026-01-14 13:38:09'),(38892,12,'vsol',NULL,'a2:3e:12:09:02:a8',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 39','',2808.00,NULL,'Power Off','2024-11-17 21:17:27','online','2026-01-13 19:57:48'),(38893,12,'vsol',NULL,'00:d3:9e:b5:32:be',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 40','',2560.00,NULL,'Power Off','2024-11-21 20:33:10','offline','2026-01-14 13:38:09'),(38894,12,'vsol',NULL,'4c:d7:c8:7f:cf:ee',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 41','',2826.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:09'),(38895,12,'vsol',NULL,'a2:3e:04:09:83:80',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 42','',1974.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:09'),(38896,12,'vsol',NULL,'8c:90:2d:c7:05:65',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 43','',3197.00,NULL,'Power Off','2024-11-21 20:33:06','offline','2026-01-14 13:38:09'),(38897,12,'vsol',NULL,'a2:4e:06:15:84:30',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 44','',2952.00,NULL,'Power Off','2024-11-21 20:32:48','offline','2026-01-14 13:38:09'),(38898,12,'vsol',NULL,'a2:4f:09:29:b2:20',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 46','',742.00,NULL,'Power Off','2024-11-21 20:32:33','offline','2026-01-14 13:38:09'),(38899,12,'vsol',NULL,'b4:64:15:ed:c9:4a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 47','',2044.00,NULL,'Power Off','2024-11-21 20:33:03','offline','2026-01-14 13:38:09'),(38900,12,'vsol',NULL,'a2:4d:08:01:23:10',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 48','',1806.00,NULL,'Power Off','2024-11-21 20:32:44','offline','2026-01-14 13:38:09'),(38901,12,'vsol',NULL,'b4:64:15:ee:e3:7a',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 49','',1979.00,NULL,'Power Off','2024-11-21 20:32:58','offline','2026-01-14 13:38:09'),(38902,12,'vsol',NULL,'4c:46:d1:86:ac:23',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 50','',1660.00,NULL,'Power Off','2024-11-21 20:32:25','offline','2026-01-14 13:38:09'),(38903,12,'vsol',NULL,'78:20:51:5e:22:c1',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 51','',1901.00,NULL,'Power Off','2024-11-21 20:32:33','offline','2026-01-14 13:38:09'),(38904,12,'vsol',NULL,'1c:ef:03:ae:6c:15',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 52','',2283.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:09'),(38906,12,'vsol',NULL,'a0:7e:09:18:f3:c0',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 1','',418.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38908,12,'vsol',NULL,'98:c7:a4:04:6b:ca',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 3','',70.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38909,12,'vsol',NULL,'a2:3e:06:22:d7:50',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 4','',588.00,NULL,'Power Off','2024-11-21 20:33:03','offline','2026-01-14 13:38:10'),(38910,12,'vsol',NULL,'a2:7e:09:11:9a:70',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 5','',892.00,NULL,'Power Off','2024-11-21 20:32:32','offline','2026-01-14 13:38:10'),(38911,12,'vsol',NULL,'a2:4e:12:2a:b4:c0',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 6','',429.00,-17.62,'Power Off','2024-11-21 20:32:35','offline','2026-01-14 13:38:10'),(38912,12,'vsol',NULL,'60:d2:dd:01:05:10',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 7','',259.00,NULL,'Power Off','2024-11-21 20:33:06','offline','2026-01-14 13:38:10'),(38913,12,'vsol',NULL,'c0:7e:40:b2:c2:44',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 8','',344.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:10'),(38914,12,'vsol',NULL,'98:c7:a4:06:42:2f',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 9','',246.00,NULL,'Power Off','2024-11-21 20:32:33','offline','2026-01-14 13:38:10'),(38916,12,'vsol',NULL,'30:3d:51:e4:4d:50',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 11','',2392.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38917,12,'vsol',NULL,'c0:7e:40:a8:65:63',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 12','',126.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38918,12,'vsol',NULL,'b4:64:15:ee:9b:6a',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 13','',220.00,NULL,'Power Off','2024-11-03 21:50:01','online','2026-01-13 19:57:48'),(38919,12,'vsol',NULL,'1c:ef:03:e4:ff:e8',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 14','',375.00,NULL,'Power Off','2024-11-21 20:33:01','offline','2026-01-14 13:38:11'),(38920,12,'vsol',NULL,'e0:36:76:67:8f:24',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 15','',49.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38921,12,'vsol',NULL,'30:3d:51:e3:8f:ba',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 16','',190.00,NULL,'Power Off','2024-11-21 20:33:08','offline','2026-01-14 13:38:11'),(38922,12,'vsol',NULL,'a2:3e:06:22:cb:50',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 17','',202.00,NULL,'Power Off','2024-11-20 23:45:23','online','2026-01-13 19:57:48'),(38923,12,'vsol',NULL,'a2:4f:06:17:46:f8',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 18','',608.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:48'),(38924,12,'vsol',NULL,'a0:7e:04:15:19:54',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 19','',402.00,NULL,'Power Off','2024-11-21 20:33:06','offline','2026-01-14 13:38:11'),(38925,12,'vsol',NULL,'a2:3e:03:25:e7:30',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 20','',274.00,NULL,'Power Off','2024-11-21 20:32:27','offline','2026-01-14 13:38:11'),(38926,12,'vsol',NULL,'80:d4:a5:0e:c6:1f',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 21','',601.00,NULL,'Power Off','2024-11-21 20:32:57','offline','2026-01-14 13:38:11'),(38927,12,'vsol',NULL,'00:d3:9e:8a:0f:0c',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 22','',162.00,NULL,'Power Off','2024-11-21 20:32:47','offline','2026-01-14 13:38:11'),(38928,12,'vsol',NULL,'a2:7e:04:1b:5e:d0',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 23','',454.00,NULL,'Power Off','2024-11-21 20:32:46','offline','2026-01-14 13:38:11'),(38929,12,'vsol',NULL,'a2:3e:07:18:bc:20',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 24','',375.00,NULL,'Power Off','2024-11-20 00:03:20','online','2026-01-13 19:57:48'),(38930,12,'vsol',NULL,'80:d4:a5:10:94:5f',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 25','',521.00,NULL,'Wire Down','2024-11-21 20:32:51','offline','2026-01-14 13:38:11'),(38931,12,'vsol',NULL,'80:07:1b:ce:f9:d0',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 26','',856.00,NULL,'Power Off','2024-11-21 20:32:25','offline','2026-01-14 13:38:11'),(38932,12,'vsol',NULL,'c4:70:0b:5d:37:b8',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 27','',246.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:11'),(38933,12,'vsol',NULL,'4c:d7:c8:13:b5:a4',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 28','',138.00,NULL,'Power Off','2024-11-21 20:32:18','offline','2026-01-14 13:38:11'),(38934,12,'vsol',NULL,'00:d3:9e:66:d0:b0',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 29','',431.00,NULL,'Power Off','2024-11-21 20:32:30','offline','2026-01-14 13:38:11'),(38935,12,'vsol',NULL,'a2:3e:08:24:95:60',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 30','',483.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:11'),(38936,12,'vsol',2300,'a2:7e:04:1b:c8:30','2300','a2:7e:04:1b:c8:30',NULL,NULL,0,'EPON 0/04','ONU 33','',547.00,NULL,'Power Off','2024-11-21 20:32:35','offline','2026-01-14 13:38:11'),(38937,12,'vsol',1906,'a2:4f:09:19:01:40',NULL,NULL,NULL,NULL,0,'EPON 0/04','ONU 34','',128.00,NULL,'Power Off','2024-11-10 15:27:50','online','2026-01-13 19:57:49'),(38938,12,'vsol',2615,'1c:ef:03:1a:7f:40','2615','1c:ef:03:1a:7f:40',NULL,NULL,0,'EPON 0/04','ONU 35','',577.00,NULL,'Power Off','2024-11-21 20:33:10','offline','2026-01-14 13:38:11'),(38939,12,'vsol',2584,'a2:3e:09:10:01:a0','2584','a2:3e:09:10:01:a0',NULL,NULL,0,'EPON 0/05','ONU 1','',1470.00,NULL,'Wire Down','2024-11-21 21:25:40','offline','2026-01-14 15:41:00'),(38940,12,'vsol',2389,'a0:7e:11:11:76:9c','2389','a0:7e:11:11:76:9c',NULL,NULL,0,'EPON 0/05','ONU 2','',1162.00,NULL,'Wire Down','2024-11-21 21:25:41','offline','2026-01-14 15:41:00'),(38941,12,'vsol',2545,'a0:7f:04:17:5b:32',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 3','',1841.00,NULL,'Power Off','2024-11-21 02:00:11','online','2026-01-13 19:57:49'),(38942,12,'vsol',2028,'c0:7e:40:a0:89:f2','2028','c0:7e:40:a0:89:f2',NULL,NULL,0,'EPON 0/05','ONU 4','',3385.00,NULL,'Wire Down','2024-11-21 21:25:39','offline','2026-01-14 15:41:00'),(38943,12,'vsol',2036,'80:b5:75:21:9c:02',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 5','',1052.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38944,12,'vsol',1889,'38:d4:a5:72:e0:ff','1889','38:d4:a5:72:e0:ff',NULL,NULL,0,'EPON 0/05','ONU 6','',692.00,-9.92,'Wire Down','2024-11-21 19:30:39','offline','2026-01-14 13:00:57'),(38945,12,'vsol',2327,'00:d3:9e:66:cd:2c',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 7','',1041.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38946,12,'vsol',2037,'48:46:fb:fc:75:b5',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 8','',223.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38947,12,'vsol',2409,'6c:68:a4:6f:40:28','2409','6c:68:a4:6f:40:28',NULL,NULL,0,'EPON 0/05','ONU 9','',272.00,NULL,'Power Off','2024-11-21 20:32:51','offline','2026-01-14 13:38:13'),(38948,12,'vsol',2215,'6c:68:a4:8b:be:60','2215','6c:68:a4:8b:be:60',NULL,NULL,0,'EPON 0/05','ONU 10','',1221.00,NULL,'Power Off','2024-11-21 20:32:41','offline','2026-01-14 13:38:13'),(38949,12,'vsol',1880,'1c:ef:03:08:11:ec','1880','1c:ef:03:08:11:ec',NULL,NULL,0,'EPON 0/05','ONU 11','',1359.00,NULL,'Wire Down','2024-11-21 21:25:40','offline','2026-01-14 15:41:00'),(38950,12,'vsol',1802,'c0:7e:40:b2:c3:94',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 12','',657.00,NULL,'Power Off','2024-11-20 17:15:43','online','2026-01-13 19:57:49'),(38951,12,'vsol',2272,'6c:68:a4:9f:5c:d8',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 13','',424.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38952,12,'vsol',2347,'a0:7e:09:18:f4:40',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 14','',988.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38954,12,'vsol',2580,'10:af:78:fe:85:ff','2580','10:af:78:fe:85:ff',NULL,NULL,0,'EPON 0/05','ONU 16','',1462.00,NULL,'Wire Down','2024-11-21 21:25:39','offline','2026-01-14 15:41:00'),(38955,12,'vsol',1777,'a2:3e:09:10:84:40','1777','a2:3e:09:10:84:40',NULL,NULL,0,'EPON 0/05','ONU 17','',1015.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:13'),(38956,12,'vsol',2318,'a2:4e:09:24:69:80','2318','a2:4e:09:24:69:80',NULL,NULL,0,'EPON 0/05','ONU 18','',1905.00,NULL,'Power Off','2024-11-21 17:18:06','offline','2026-01-14 13:38:13'),(38957,12,'vsol',2492,'74:a0:63:df:2c:7a','2492','74:a0:63:df:2c:7a',NULL,NULL,0,'EPON 0/05','ONU 19','',1349.00,NULL,'Power Off','2024-11-21 21:05:24','offline','2026-01-14 15:41:00'),(38958,12,'vsol',2208,'c0:7e:40:a0:95:c2','2208','c0:7e:40:a0:95:c2',NULL,NULL,0,'EPON 0/05','ONU 20','',3454.00,NULL,'Wire Down','2024-11-21 21:25:42','offline','2026-01-14 15:41:00'),(38959,12,'vsol',2379,'a0:7e:08:33:d6:b4','2379','a0:7e:08:33:d6:b4',NULL,NULL,0,'EPON 0/05','ONU 21','',1144.00,NULL,'Power Off','2024-11-21 20:32:15','offline','2026-01-14 13:38:13'),(38960,12,'vsol',2352,'a0:7e:09:17:78:10','2352','a0:7e:09:17:78:10',NULL,NULL,0,'EPON 0/05','ONU 22','',1318.00,NULL,'Power Off','2024-11-21 20:32:15','offline','2026-01-14 13:38:13'),(38961,12,'vsol',2263,'80:f7:a6:c2:f4:b2','2263','80:f7:a6:c2:f4:b2',NULL,NULL,0,'EPON 0/05','ONU 23','',1560.00,NULL,'Power Off','2024-11-21 20:32:38','offline','2026-01-14 13:38:13'),(38962,12,'vsol',1843,'70:b6:4f:d3:d9:e8',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 24','',1083.00,NULL,'Power Off','2024-11-08 02:57:03','online','2026-01-13 19:57:49'),(38963,12,'vsol',2101,'4c:d7:c8:a1:71:fc','2101','4c:d7:c8:a1:71:fc',NULL,NULL,0,'EPON 0/05','ONU 25','',952.00,NULL,'Power Off','2024-11-21 20:32:22','offline','2026-01-14 13:38:13'),(38964,12,'vsol',2054,'30:3d:51:e3:72:50','2054','30:3d:51:e3:72:50',NULL,NULL,0,'EPON 0/05','ONU 26','',3190.00,NULL,'Power Off','2024-11-21 20:32:24','offline','2026-01-14 13:38:13'),(38965,12,'vsol',2587,'a2:8f:07:21:a5:e8','2587','a2:8f:07:21:a5:e8',NULL,NULL,0,'EPON 0/05','ONU 27','',1787.00,NULL,'Power Off','2024-11-21 17:17:49','offline','2026-01-14 13:38:13'),(38966,12,'vsol',2149,'30:3d:51:e4:51:2e','2149','30:3d:51:e4:51:2e',NULL,NULL,0,'EPON 0/05','ONU 28','',3516.00,NULL,'Wire Down','2024-11-21 21:25:38','offline','2026-01-14 15:41:00'),(38967,12,'vsol',1775,'a2:7e:09:11:9d:d0','1775','a2:7e:09:11:9d:d0',NULL,NULL,0,'EPON 0/05','ONU 29','',515.00,NULL,'Power Off','2024-11-21 20:32:36','offline','2026-01-14 13:38:13'),(38968,12,'vsol',2489,'a2:4e:12:2a:b2:10','2489','a2:4e:12:2a:b2:10',NULL,NULL,0,'EPON 0/05','ONU 30','',533.00,NULL,'Power Off','2024-11-21 20:32:26','offline','2026-01-14 13:38:13'),(38969,12,'vsol',2142,'a2:3d:12:08:51:b0','2142','a2:3d:12:08:51:b0',NULL,NULL,0,'EPON 0/05','ONU 31','',641.00,NULL,'Power Off','2024-11-21 20:32:58','offline','2026-01-14 13:38:13'),(38970,12,'vsol',2155,'a2:3e:04:0c:84:40','2155','a2:3e:04:0c:84:40',NULL,NULL,0,'EPON 0/05','ONU 32','',1772.00,NULL,'Power Off','2024-11-21 17:17:42','offline','2026-01-14 13:38:13'),(38971,12,'vsol',2344,'bc:60:6b:4c:7c:90',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 33','',583.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:49'),(38972,12,'vsol',2273,'30:3d:51:e5:33:5e','2273','30:3d:51:e5:33:5e',NULL,NULL,0,'EPON 0/05','ONU 34','',3174.00,NULL,'Power Off','2024-11-21 20:32:17','offline','2026-01-14 13:38:13'),(38973,12,'vsol',1890,'a0:7d:01:04:35:98','1890','a0:7d:01:04:35:98',NULL,NULL,0,'EPON 0/05','ONU 35','',3326.00,-14.35,'Wire Down','2024-11-21 21:25:40','offline','2026-01-14 15:41:00'),(38974,12,'vsol',2121,'30:3d:51:e4:44:98','2121','30:3d:51:e4:44:98',NULL,NULL,0,'EPON 0/05','ONU 36','',3398.00,NULL,'Wire Down','2024-11-21 21:25:40','offline','2026-01-14 15:41:00'),(38975,12,'vsol',2129,'6c:68:a4:63:0d:b4','2129','6c:68:a4:63:0d:b4',NULL,NULL,0,'EPON 0/05','ONU 37','',1159.00,NULL,'Power Off','2024-11-21 20:32:22','offline','2026-01-14 13:38:13'),(38976,12,'vsol',1774,'a2:7e:09:07:ab:40','1774','a2:7e:09:07:ab:40',NULL,NULL,0,'EPON 0/05','ONU 38','',359.00,NULL,'Power Off','2024-11-21 20:32:40','offline','2026-01-14 13:38:13'),(38977,12,'vsol',1882,'a2:5c:12:17:c8:90','1882','a2:5c:12:17:c8:90',NULL,NULL,0,'EPON 0/05','ONU 39','',1036.00,NULL,'Power Off','2024-11-21 20:32:29','offline','2026-01-14 13:38:13'),(38978,12,'vsol',2055,'a2:3d:09:30:0c:90','2055','a2:3d:09:30:0c:90',NULL,NULL,0,'EPON 0/05','ONU 40','',1749.00,NULL,'Power Off','2024-11-21 17:17:57','offline','2026-01-14 13:38:13'),(38979,12,'vsol',2057,'a2:3d:09:30:01:10','2057','a2:3d:09:30:01:10',NULL,NULL,0,'EPON 0/05','ONU 41','',1864.00,NULL,'Power Off','2024-11-21 17:17:46','offline','2026-01-14 13:38:13'),(38980,12,'vsol',1893,'80:d4:a5:1d:83:6f','1893','80:d4:a5:1d:83:6f',NULL,NULL,0,'EPON 0/05','ONU 42','',1024.00,NULL,'Wire Down','2024-11-21 20:32:20','offline','2026-01-14 13:38:13'),(38981,12,'vsol',1776,'c0:7e:40:a7:95:0c','1776','c0:7e:40:a7:95:0c',NULL,NULL,0,'EPON 0/05','ONU 43','',1003.00,NULL,'Power Off','2024-11-21 20:33:07','offline','2026-01-14 13:38:13'),(38982,12,'vsol',2374,'00:d3:9e:b8:09:4a','2374','00:d3:9e:b8:09:4a',NULL,NULL,0,'EPON 0/05','ONU 44','',1805.00,NULL,'Power Off','2024-11-21 20:32:40','offline','2026-01-14 13:38:13'),(38983,12,'vsol',2103,'00:d5:9f:75:b8:74','2103','00:d5:9f:75:b8:74',NULL,NULL,0,'EPON 0/05','ONU 45','',879.00,NULL,'Power Off','2024-11-21 17:17:41','offline','2026-01-14 13:38:13'),(38984,12,'vsol',2574,'a2:3e:06:22:d7:f0','2574','a2:3e:06:22:d7:f0',NULL,NULL,0,'EPON 0/05','ONU 46','',1488.00,NULL,'Wire Down','2024-11-21 21:25:41','offline','2026-01-14 15:41:00'),(38985,12,'vsol',2130,'a2:5c:11:04:e8:f0','2130','a2:5c:11:04:e8:f0',NULL,NULL,0,'EPON 0/05','ONU 47','',1413.00,NULL,'Wire Down','2024-11-21 21:25:39','offline','2026-01-14 15:41:00'),(38986,12,'vsol',1910,'a2:5c:12:1b:26:50','1910','a2:5c:12:1b:26:50',NULL,NULL,0,'EPON 0/05','ONU 48','',1044.00,NULL,'Power Off','2024-11-21 20:32:32','offline','2026-01-14 13:38:13'),(38987,12,'vsol',2322,'a2:3d:09:30:07:20','2322','a2:3d:09:30:07:20',NULL,NULL,0,'EPON 0/05','ONU 49','',1818.00,NULL,'Power Off','2024-11-21 17:17:47','offline','2026-01-14 13:38:13'),(38988,12,'vsol',2207,'a2:4d:07:07:00:20','2207','a2:4d:07:07:00:20',NULL,NULL,0,'EPON 0/05','ONU 50','',1365.00,NULL,'Wire Down','2024-11-21 21:25:42','offline','2026-01-14 15:41:00'),(38989,12,'vsol',2023,'a2:5d:03:17:1d:b0','2023','a2:5d:03:17:1d:b0',NULL,NULL,0,'EPON 0/05','ONU 51','',1354.00,NULL,'Wire Down','2024-11-21 21:25:41','offline','2026-01-14 15:41:00'),(38990,12,'vsol',1779,'a2:7e:09:07:de:40','1779','a2:7e:09:07:de:40',NULL,NULL,0,'EPON 0/05','ONU 52','',1218.00,NULL,'Wire Down','2024-11-21 21:25:39','offline','2026-01-14 15:41:00'),(38991,12,'vsol',2182,'48:62:76:c9:3d:8b','2182','48:62:76:c9:3d:8b',NULL,NULL,0,'EPON 0/05','ONU 53','',1254.00,NULL,'Power Off','2024-11-21 20:32:38','offline','2026-01-14 13:38:13'),(38992,12,'vsol',1778,'a2:7e:09:07:e4:60','1778','a2:7e:09:07:e4:60',NULL,NULL,0,'EPON 0/05','ONU 54','',1259.00,NULL,'Wire Down','2024-11-21 21:25:39','offline','2026-01-14 15:41:00'),(38993,12,'vsol',2514,'a2:4f:05:06:47:b0','2514','a2:4f:05:06:47:b0',NULL,NULL,0,'EPON 0/05','ONU 55','',1864.00,NULL,'Power Off','2024-11-21 20:33:03','offline','2026-01-14 13:38:13'),(38994,12,'vsol',2286,'00:d3:9e:c4:2e:f8','2286','00:d3:9e:c4:2e:f8',NULL,NULL,0,'EPON 0/05','ONU 56','',975.00,NULL,'Power Off','2024-11-21 17:18:18','offline','2026-01-14 13:38:14'),(38995,12,'vsol',2597,'b4:64:15:ed:d7:3a','2597','b4:64:15:ed:d7:3a',NULL,NULL,0,'EPON 0/05','ONU 57','',293.00,NULL,'Power Off','2024-11-21 20:32:48','offline','2026-01-14 13:38:14'),(38996,12,'vsol',2601,'a2:4f:08:10:97:70',NULL,NULL,NULL,NULL,0,'EPON 0/05','ONU 59','',562.00,NULL,'Power Off','2024-11-18 17:42:45','online','2026-01-13 19:57:49'),(38997,12,'vsol',2059,'28:31:52:cd:bc:e4','2059','28:31:52:cd:bc:e4',NULL,NULL,0,'EPON 0/05','ONU 60','',1842.00,NULL,'Power Off','2024-11-21 20:32:34','offline','2026-01-14 13:38:14'),(39000,12,'vsol',2353,'a0:7e:10:26:24:7b',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 2','',1239.00,NULL,'Wire Down','2024-11-14 20:28:25','online','2026-01-13 19:57:49'),(39001,12,'vsol',2301,'00:d3:9e:6a:00:18','2301','00:d3:9e:6a:00:18',NULL,NULL,0,'EPON 0/06','ONU 3','',1290.00,NULL,'Wire Down','2024-11-21 17:18:00','offline','2026-01-14 13:38:15'),(39002,12,'vsol',2084,'c0:7e:40:b3:e6:44',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 4','',4013.00,NULL,'Power Off','2024-11-20 19:44:04','online','2026-01-13 19:57:49'),(39003,12,'vsol',2284,'00:d5:9e:8a:3d:02','2284','00:d5:9e:8a:3d:02',NULL,NULL,0,'EPON 0/06','ONU 6','',1224.00,NULL,'Power Off','2024-11-21 17:18:06','offline','2026-01-14 13:38:15'),(39004,12,'vsol',2430,'a2:4e:12:23:dd:b0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 7','',1375.00,NULL,'Power Off','2024-11-18 04:18:32','online','2026-01-13 19:57:49'),(39005,12,'vsol',NULL,'30:3d:51:e3:ab:da',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 8','',1205.00,NULL,'Power Off','2024-11-21 17:17:53','offline','2026-01-14 13:38:15'),(39006,12,'vsol',NULL,'1c:ef:03:41:3e:80',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 10','',1367.00,NULL,'Power Off','2024-11-21 17:23:46','offline','2026-01-14 13:38:15'),(39007,12,'vsol',NULL,'c0:7e:40:b2:c2:f5',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 11','',4142.00,NULL,'Power Off','2024-11-20 19:44:04','online','2026-01-13 19:57:49'),(39008,12,'vsol',NULL,'c0:7e:40:b3:e5:b0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 12','',3993.00,NULL,'Power Off','2024-11-20 19:44:02','online','2026-01-13 19:57:49'),(39009,12,'vsol',NULL,'6c:68:a4:c4:61:1d',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 13','',1310.00,NULL,'Power Off','2024-11-21 17:17:36','offline','2026-01-14 13:38:15'),(39010,12,'vsol',NULL,'30:3d:51:e5:33:b2',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 14','',3572.00,NULL,'Power Off','2024-11-21 17:17:30','offline','2026-01-14 13:38:15'),(39011,12,'vsol',NULL,'6c:68:a4:73:e9:5c',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 15','',4795.00,NULL,'Power Off','2024-11-20 19:44:04','online','2026-01-13 19:57:49'),(39012,12,'vsol',2122,'a2:4f:06:14:22:a0','2122','a2:4f:06:14:22:a0',NULL,NULL,0,'EPON 0/06','ONU 16','',1372.00,-26.58,'Wire Down','2024-11-21 21:35:54','online','2026-01-14 15:41:02'),(39013,12,'vsol',NULL,'a2:3d:11:12:2c:e0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 17','',4316.00,NULL,'Power Off','2024-11-20 19:44:04','online','2026-01-13 19:57:49'),(39015,12,'vsol',2165,'a2:5d:07:20:0c:90','2165','a2:5d:07:20:0c:90',NULL,NULL,0,'EPON 0/06','ONU 19','',1147.00,NULL,'Power Off','2024-11-21 17:18:31','offline','2026-01-14 13:38:15'),(39016,12,'vsol',2366,'30:3d:51:e4:4c:7e',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 20','',6857.00,NULL,'Power Off','2024-11-20 19:43:59','online','2026-01-13 19:57:50'),(39017,12,'vsol',2520,'6c:68:a4:c6:82:f0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 21','',4729.00,NULL,'Power Off','2024-11-20 19:46:42','online','2026-01-13 19:57:50'),(39019,12,'vsol',2267,'a0:7d:06:29:48:1e',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 23','',6641.00,NULL,'Power Off','2024-11-20 19:43:59','online','2026-01-13 19:57:50'),(39021,12,'vsol',2220,'00:d3:9e:c4:4e:f6',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 25','',4842.00,NULL,'Power Off','2024-11-20 19:44:04','online','2026-01-13 19:57:50'),(39022,12,'vsol',1894,'e4:a8:b6:88:fe:b7',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 26','',5008.00,NULL,'Power Off','2024-11-20 19:44:03','online','2026-01-13 19:57:50'),(39025,12,'vsol',2087,'6c:68:a4:44:f4:84',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 29','',1218.00,NULL,'Wire Down','2024-11-20 16:34:14','online','2026-01-13 19:57:50'),(39026,12,'vsol',1984,'48:46:fb:78:87:5b',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 30','',4746.00,NULL,'Power Off','2024-11-20 19:44:02','online','2026-01-13 19:57:50'),(39027,12,'vsol',1898,'20:3d:b2:3e:57:d0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 31','',4834.00,NULL,'Power Off','2024-11-20 19:44:01','online','2026-01-13 19:57:50'),(39030,12,'vsol',1872,'1c:01:a7:4a:75:09',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 34','',4783.00,NULL,'Power Off','2024-11-20 19:44:03','online','2026-01-13 19:57:50'),(39031,12,'vsol',2013,'a0:7e:12:22:8a:f0',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 35','',4147.00,NULL,'Power Off','2024-11-20 19:44:02','online','2026-01-13 19:57:50'),(39032,12,'vsol',2467,'a2:6d:03:16:fe:50',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 36','',4269.00,NULL,'Power Off','2024-11-20 19:44:02','online','2026-01-13 19:57:50'),(39035,12,'vsol',2080,'c0:7e:40:b3:e7:4e',NULL,NULL,NULL,NULL,0,'EPON 0/06','ONU 39','',4565.00,NULL,'Power Off','2024-11-20 19:44:03','online','2026-01-13 19:57:50'),(39037,12,'vsol',2364,'80:f7:a6:9f:c0:22','2364','80:f7:a6:9f:c0:22',NULL,NULL,0,'EPON 0/06','ONU 42','',1744.00,NULL,'Power Off','2024-11-21 17:18:22','offline','2026-01-14 13:38:15'),(39038,12,'vsol',2532,'a2:3e:09:24:37:60','2532','b8:3a:08:91:85:77',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 1','',2241.00,NULL,'Power Off','2024-11-21 17:22:41','offline','2026-01-14 10:37:43'),(39039,12,'vsol',2343,'c0:7e:40:b1:be:93','2343','c0:7e:40:b1:be:93',NULL,NULL,0,'EPON 0/07','ONU 2','',913.00,NULL,'Power Off','2024-11-21 20:32:42','offline','2026-01-14 13:38:17'),(39040,12,'vsol',1809,'c0:7e:40:b2:c4:9e','1809','04:5e:a4:ea:92:47',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 3','',2044.00,NULL,'Power Off','2024-11-21 17:22:46','offline','2026-01-14 10:37:43'),(39041,12,'vsol',1845,'b0:7c:07:15:31:9e','1845','1c:61:b4:63:37:09',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 4','',1788.00,NULL,'Power Off','2024-11-21 17:22:50','offline','2026-01-14 10:37:43'),(39042,12,'vsol',2226,'a2:3e:04:0c:71:a0','2226','a2:3e:04:0c:71:a0',NULL,NULL,0,'EPON 0/07','ONU 5','',567.00,NULL,'Power Off','2024-11-21 20:32:56','offline','2026-01-14 13:38:17'),(39043,12,'vsol',2491,'a2:4e:12:2b:56:20','2491','a2:4e:12:2b:56:20',NULL,NULL,0,'EPON 0/07','ONU 6','',641.00,-22.29,'Wire Down','2024-11-13 22:02:32','online','2026-01-14 13:38:17'),(39044,12,'vsol',2367,'a2:3e:07:1a:fe:e0','2367','b8:3a:08:8e:5e:97',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 8','',2147.00,NULL,'Power Off','2024-11-21 17:22:45','offline','2026-01-14 10:37:43'),(39046,12,'vsol',2178,'70:a5:6a:e2:8e:de','2178','b4:0f:3b:e9:0e:49',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 10','',2274.00,NULL,'Power Off','2024-11-21 17:22:47','offline','2026-01-14 10:37:43'),(39047,12,'vsol',1801,'c0:7e:40:b3:e6:82',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 12','',1828.00,NULL,'Power Off','2024-11-20 00:13:10','online','2026-01-13 19:57:50'),(39048,12,'vsol',1818,'98:c7:a4:01:c4:1c','1818','e8:65:d4:fc:93:a8',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 13','',2121.00,NULL,'Power Off','2024-11-21 17:22:47','offline','2026-01-14 10:37:43'),(39049,12,'vsol',2578,'10:af:78:fe:9b:ad','2578','d8:32:14:67:4b:19',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 14','',1985.00,NULL,'Power Off','2024-11-21 17:22:48','offline','2026-01-14 10:37:43'),(39050,12,'vsol',2559,'b4:64:15:af:6d:a4','2559','08:40:f3:a3:1e:90',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 15','',2223.00,NULL,'Power Off','2024-11-21 17:22:46','offline','2026-01-14 10:37:43'),(39051,12,'vsol',1869,'70:b6:4f:d4:cf:18','1869','50:0f:f5:be:97:e0',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 16','',2203.00,NULL,'Power Off','2024-11-21 17:22:50','offline','2026-01-14 10:37:43'),(39052,12,'vsol',2450,'74:88:2a:76:dd:1e','2450','50:0f:f5:08:23:5f',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 17','',2200.00,NULL,'Power Off','2024-11-21 17:22:44','offline','2026-01-14 10:37:43'),(39053,12,'vsol',1895,'70:a5:6a:0c:34:20','1895','1c:61:b4:3a:ce:c2',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 18','',2510.00,NULL,'Power Off','2024-11-21 17:22:49','offline','2026-01-14 10:37:43'),(39054,12,'vsol',2506,'a2:4f:05:22:29:70','2506','88:bd:09:50:cb:15',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 20','',2241.00,NULL,'Power Off','2024-11-21 17:22:44','offline','2026-01-14 10:37:43'),(39055,12,'vsol',2547,'70:2e:22:01:75:a5','2547','d8:32:14:3e:fa:df',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 21','',2201.00,NULL,'Power Off','2024-11-21 17:22:45','offline','2026-01-14 10:37:43'),(39056,12,'vsol',2444,'4c:d7:c8:a1:79:04',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 22','',1057.00,NULL,'Wire Down','2024-11-13 22:54:04','online','2026-01-13 19:57:50'),(39057,12,'vsol',2049,'30:3d:51:e3:72:0e','2049','30:3d:51:e3:72:0e',NULL,NULL,0,'EPON 0/07','ONU 23','',3270.00,NULL,'Power Off','2024-11-21 20:32:20','offline','2026-01-14 13:38:17'),(39058,12,'vsol',2157,'30:3d:51:e4:7c:b8','2157','28:ee:52:fd:a9:8b',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 24','',4339.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:43'),(39059,12,'vsol',2269,'a2:3e:05:14:55:00','2269','e4:fa:c4:f1:87:c7',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 25','',2010.00,NULL,'Power Off','2024-11-21 17:22:47','offline','2026-01-14 10:37:44'),(39060,12,'vsol',2018,'80:d4:a5:4f:64:5f','2018','80:d4:a5:4f:64:5f',NULL,NULL,0,'EPON 0/07','ONU 26','',721.00,NULL,'Power Off','2024-11-21 20:32:36','offline','2026-01-14 13:38:17'),(39061,12,'vsol',2004,'a0:8c:a2:f2:ec:ad',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 27','',2200.00,NULL,'Power Off','2024-11-02 07:45:17','online','2026-01-13 19:57:50'),(39062,12,'vsol',2314,'4c:d7:c8:e7:e9:fc','2314','4c:d7:c8:e7:e9:fd',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 28','',1865.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:44'),(39063,12,'vsol',2005,'a0:7d:04:06:8c:e0','2005','dc:8e:8d:05:c8:e7',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 29','',3974.00,NULL,'Power Off','2024-11-21 17:22:41','offline','2026-01-14 10:37:44'),(39064,12,'vsol',2132,'a2:4f:06:08:99:80',NULL,NULL,NULL,NULL,0,'EPON 0/07','ONU 30','',2280.00,NULL,'Wire Down','2024-11-15 19:17:06','online','2026-01-13 19:57:50'),(39065,12,'vsol',2503,'a2:4f:04:17:c3:40','2503','cc:2d:21:04:7e:20',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 31','',2026.00,NULL,'Power Off','2024-11-21 17:22:51','offline','2026-01-14 10:37:44'),(39066,12,'vsol',2600,'a2:5d:03:17:5d:90','2600','c0:25:2f:5c:a6:dd',307,'2026-01-14 10:37:31',0,'EPON 0/07','ONU 33','',2126.00,NULL,'Power Off','2024-11-21 17:22:45','offline','2026-01-14 10:37:44'),(39067,12,'vsol',2625,'b4:64:15:ed:c7:8a','2625','b4:64:15:ed:c7:8a',NULL,NULL,0,'EPON 0/07','ONU 34','',692.00,NULL,'Power Off','2024-11-21 17:17:52','offline','2026-01-14 13:38:17'),(39068,12,'vsol',2194,'80:f7:a6:07:af:37','2194','80:f7:a6:07:af:37',NULL,NULL,0,'EPON 0/08','ONU 1','',1157.00,NULL,'Power Off','2024-11-21 20:32:41','offline','2026-01-14 13:38:19'),(39069,12,'vsol',2583,'a2:3f:07:23:41:b0','2583','a2:3f:07:23:41:b0',NULL,NULL,0,'EPON 0/08','ONU 2','',1038.00,NULL,'Power Off','2024-11-21 17:17:45','offline','2026-01-14 13:38:19'),(39070,12,'vsol',2616,'10:af:78:fe:7b:41','2616','10:af:78:fe:7b:41',NULL,NULL,0,'EPON 0/08','ONU 3','',1424.00,NULL,'Power Off','2024-11-21 20:32:41','offline','2026-01-14 13:38:19'),(39071,12,'vsol',2126,'a2:4d:07:07:08:90',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 4','',1582.00,NULL,'Power Off','2024-11-20 18:29:21','online','2026-01-13 19:57:50'),(39072,12,'vsol',2576,'10:af:78:fe:7d:0d','2576','10:af:78:fe:7d:0d',NULL,NULL,0,'EPON 0/08','ONU 5','',1060.00,NULL,'Power Off','2024-11-21 17:18:25','offline','2026-01-14 13:38:19'),(39073,12,'vsol',1840,'98:c7:a4:01:c3:3c','1840','98:c7:a4:01:c3:3c',NULL,NULL,0,'EPON 0/08','ONU 6','',1592.00,NULL,'Power Off','2024-11-21 20:32:29','offline','2026-01-14 13:38:19'),(39074,12,'vsol',2071,'70:a5:6a:cd:5f:ae','2071','74:da:88:a6:ef:b5',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 7','',2103.00,NULL,'Power Off','2024-11-21 17:22:51','offline','2026-01-14 10:37:45'),(39075,12,'vsol',2241,'c4:47:3f:9c:c4:6b','2241','dc:8e:8d:2f:42:e3',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 8','',1724.00,NULL,'Power Off','2024-11-21 17:22:44','offline','2026-01-14 10:37:45'),(39077,12,'vsol',2245,'18:09:d1:02:4b:31','2245','18:09:d1:02:4b:31',NULL,NULL,0,'EPON 0/08','ONU 10','',2944.00,NULL,'Power Off','2024-11-21 20:32:15','offline','2026-01-14 13:38:19'),(39078,12,'vsol',1947,'1c:ef:03:e9:02:62','1947','40:ed:00:3e:46:24',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 11','',2611.00,NULL,'Power Off','2024-11-21 17:22:46','offline','2026-01-14 10:37:45'),(39079,12,'vsol',1853,'a0:94:6a:04:5d:ad',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 12','',916.00,NULL,'Power Off','2024-11-14 00:08:07','online','2026-01-13 19:57:50'),(39080,12,'vsol',2334,'00:d3:9e:ed:10:38','2334','00:d3:9e:ed:10:38',NULL,NULL,0,'EPON 0/08','ONU 13','',952.00,NULL,'Power Off','2024-11-21 20:32:31','offline','2026-01-14 13:38:19'),(39081,12,'vsol',2183,'a2:3d:12:08:12:a0','2183','a2:3d:12:08:12:a0',NULL,NULL,0,'EPON 0/08','ONU 14','',1111.00,NULL,'Power Off','2024-11-21 20:32:28','offline','2026-01-14 13:38:19'),(39082,12,'vsol',2089,'1c:ef:03:ae:5f:5d','2089','1c:ef:03:ae:5f:5d',NULL,NULL,0,'EPON 0/08','ONU 15','',1023.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:19'),(39083,12,'vsol',2256,'6c:68:a4:9f:c1:c4','2256','6c:68:a4:9f:c1:c5',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 16','',2860.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:45'),(39084,12,'vsol',2062,'30:3d:51:e3:61:f4','2062','30:3d:51:e3:61:f4',NULL,NULL,0,'EPON 0/08','ONU 17','',2947.00,NULL,'Power Off','2024-11-21 20:32:16','offline','2026-01-14 13:38:19'),(39085,12,'vsol',2526,'a0:7f:06:13:02:58','2526','a0:7f:06:13:02:50',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 18','',2988.00,NULL,'Power Off','2024-11-21 17:22:49','offline','2026-01-14 10:37:45'),(39086,12,'vsol',1783,'00:d3:9e:b8:0b:b4','1783','00:d3:9e:b8:0b:b4',NULL,NULL,0,'EPON 0/08','ONU 19','',980.00,NULL,'Power Off','2024-11-21 20:33:00','offline','2026-01-14 13:38:19'),(39087,12,'vsol',1820,'74:88:2a:84:20:a8',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 20','',908.00,NULL,'N/A',NULL,'online','2026-01-13 19:57:50'),(39088,12,'vsol',2309,'a2:3d:12:16:3d:40',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 21','',769.00,NULL,'Power Off','2024-11-20 23:45:49','online','2026-01-13 19:57:51'),(39090,12,'vsol',1863,'c4:70:0b:5d:3d:e0','1863','c4:70:0b:5d:3d:e0',NULL,NULL,0,'EPON 0/08','ONU 23','',933.00,NULL,'Power Off','2024-11-21 20:32:19','offline','2026-01-14 13:38:19'),(39091,12,'vsol',NULL,'a2:8f:07:23:0c:68',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 24','',944.00,NULL,'Power Off','2024-11-21 03:31:23','offline','2026-01-14 15:41:04'),(39092,12,'vsol',2316,'6c:68:a4:c4:66:a1',NULL,NULL,NULL,NULL,0,'EPON 0/08','ONU 25','',2472.00,NULL,'Power Off','2024-11-08 14:49:36','online','2026-01-13 19:57:51'),(39093,12,'vsol',2310,'4c:d7:c8:12:89:c8','2310','4c:d7:c8:12:89:c9',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 26','',3039.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:45'),(39094,12,'vsol',2175,'80:07:1b:e1:54:a0','2175','64:64:4a:e1:a3:36',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 27','',1708.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:45'),(39095,12,'vsol',1913,'38:d4:a5:7b:77:ef','1913','38:d4:a5:7b:77:ef',NULL,NULL,0,'EPON 0/08','ONU 28','',759.00,NULL,'Power Off','2024-11-21 20:32:55','offline','2026-01-14 13:38:19'),(39096,12,'vsol',2225,'a2:3e:04:0a:80:00','2225','a2:3e:04:0a:80:00',NULL,NULL,0,'EPON 0/08','ONU 29','',918.00,NULL,'Power Off','2024-11-21 17:17:44','offline','2026-01-14 13:38:19'),(39097,12,'vsol',2077,'00:d5:9f:76:54:14','2077','00:d5:9f:76:54:14',NULL,NULL,0,'EPON 0/08','ONU 30','',824.00,NULL,'Power Off','2024-11-21 20:32:31','offline','2026-01-14 13:38:19'),(39098,12,'vsol',2250,'6c:68:a4:45:29:f0','2250','98:25:4a:aa:49:0b',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 31','',3088.00,NULL,'Power Off','2024-11-21 17:22:42','offline','2026-01-14 10:37:45'),(39099,12,'vsol',2209,'a2:3d:11:13:b1:50','2209','98:25:4a:85:6d:ad',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 32','',1706.00,NULL,'Power Off','2024-11-21 17:22:44','offline','2026-01-14 10:37:45'),(39100,12,'vsol',2621,'ca:11:b7:2e:fc:a2','2621','ca:11:b7:2e:fc:a2',NULL,NULL,0,'EPON 0/08','ONU 33','',3080.00,NULL,'Power Off','2024-11-21 20:20:45','offline','2026-01-14 14:19:35'),(39101,12,'vsol',2255,'6c:68:a4:85:ce:32','2255','20:23:51:67:a4:ef',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 34','',2942.00,NULL,'Power Off','2024-11-21 17:22:47','offline','2026-01-14 10:37:45'),(39102,12,'vsol',2394,'4c:d7:c8:be:42:28','2394','4c:d7:c8:be:42:28',NULL,NULL,0,'EPON 0/08','ONU 35','',974.00,NULL,'Power Off','2024-11-21 20:32:20','offline','2026-01-14 13:38:19'),(39103,12,'vsol',2099,'a2:3d:05:31:09:20','2099','04:95:e6:3a:4a:3f',308,'2026-01-14 11:59:12',0,'EPON 0/08','ONU 36','',3121.00,NULL,'Power Off','2024-11-21 17:22:49','offline','2026-01-14 11:59:30'),(39104,12,'vsol',2299,'a2:3e:07:18:01:60','2299','b8:3a:08:a7:4a:bf',308,'2026-01-14 10:37:31',0,'EPON 0/08','ONU 37','',1746.00,NULL,'Power Off','2024-11-21 17:22:48','offline','2026-01-14 10:37:45'),(39105,12,'vsol',2539,'4c:f9:a3:bc:62:8e','2539','4c:f9:a3:bc:62:8e',NULL,NULL,0,'EPON 0/08','ONU 38','',982.00,NULL,'Power Off','2024-11-21 17:18:10','offline','2026-01-14 13:38:19'),(39106,12,'vsol',2622,'00:d5:9e:8c:0c:7c','2622','3c:6a:d2:1b:5f:d7',308,'2026-01-14 14:49:15',0,'EPON 0/08','ONU 39','',3123.00,NULL,'Wire Down','2024-11-21 20:20:44','offline','2026-01-14 14:49:35'),(39107,12,'vsol',2243,'68:89:c1:09:45:ba','2243','68:89:c1:09:45:ba',NULL,NULL,0,'EPON 0/08','ONU 40','',864.00,NULL,'Power Off','2024-11-21 20:32:57','offline','2026-01-14 13:38:19'),(39108,12,'vsol',2626,'00:d5:9f:78:4f:e0','2626','00:d5:9f:78:4f:e0',NULL,NULL,0,'EPON 0/08','ONU 41','',847.00,NULL,'Power Off','2024-11-21 20:32:34','offline','2026-01-14 13:38:19'),(39109,12,'vsol',2582,'00:d5:9e:8a:cb:2e','2582','00:d5:9e:8a:cb:2e',NULL,NULL,0,'EPON 0/08','ONU 42','',806.00,NULL,'Power Off','2024-11-21 20:32:21','offline','2026-01-14 13:38:19'),(39110,12,'vsol',1838,'08:e8:4f:19:29:f7','1838','08:e8:4f:19:29:f7',NULL,NULL,0,'EPON 0/08','ONU 43','',1488.00,-17.30,'Power Off','2024-11-21 20:33:05','online','2026-01-14 15:41:04'),(54613,11,'vsol',1848,'a2:4e:12:29:69:70','1848','a2:4e:12:29:69:70',NULL,NULL,0,'EPON 0/01','ONU 63','',1908.00,NULL,'Power Off','2026-01-14 09:25:45','offline','2026-01-14 10:33:31'),(59718,11,'vsol',2443,'cc:2d:21:dc:c7:28','2443','cc:2d:21:dc:c7:28',NULL,NULL,0,'PON 0/02','','',0.00,NULL,'',NULL,'unknown','2026-01-14 11:00:39'),(66717,10,'vsol',2153,'58:d9:d5:eb:19:e0','2153','58:d9:d5:eb:19:e0',NULL,NULL,0,'EPON 0/02','ONU 1',':',747.00,-18.12,'Power Off','2026-01-14 13:33:10','online','2026-01-14 15:40:17'),(114435,11,'vsol',NULL,'a2:8c:10:19:79:30',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 31','',2505.00,NULL,'Wire Down','2026-01-14 09:50:43','offline','2026-01-14 15:40:34'),(115643,11,'vsol',NULL,'a2:3d:09:30:39:b0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 23','',1818.00,NULL,'Power Off','2026-01-14 09:25:50','offline','2026-01-14 10:33:32'),(120516,11,'vsol',NULL,'30:3d:51:e5:7c:86',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 22','',3941.00,NULL,'Power Off','2026-01-14 08:10:40','offline','2026-01-14 10:54:06'),(123998,10,'vsol',NULL,'80:d4:a5:1c:60:2f',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 22','',2041.00,NULL,'Power Off','2026-01-14 08:15:56','offline','2026-01-14 15:40:16'),(137426,10,'vsol',NULL,'70:a5:6a:cc:d4:4d',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 2','',946.00,NULL,'Power Off','2026-01-14 12:29:34','offline','2026-01-14 13:00:15'),(137427,10,'vsol',NULL,'00:d3:9e:66:d0:ce',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 4','',890.00,NULL,'Power Off','2026-01-14 12:29:27','offline','2026-01-14 13:00:15'),(137428,10,'vsol',NULL,'4c:f9:a3:b2:16:2d',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 5','',856.00,NULL,'Power Off','2026-01-14 12:29:32','offline','2026-01-14 13:00:15'),(137429,10,'vsol',NULL,'00:d3:9e:ee:a0:82',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 6','',861.00,NULL,'Power Off','2026-01-14 12:29:27','offline','2026-01-14 13:00:15'),(137430,10,'vsol',NULL,'a0:7e:11:12:a3:26',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 7','',913.00,NULL,'Power Off','2026-01-14 12:29:23','offline','2026-01-14 13:00:15'),(137431,10,'vsol',NULL,'a0:8c:f8:f4:4b:7e',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 8','',483.00,NULL,'Power Off','2026-01-14 12:29:34','offline','2026-01-14 13:00:15'),(137432,10,'vsol',NULL,'a2:3d:12:18:6f:30',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 9','',951.00,NULL,'Power Off','2026-01-14 12:29:29','offline','2026-01-14 13:00:15'),(137433,10,'vsol',NULL,'4c:f9:a3:3f:41:cf',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 10','',667.00,NULL,'Power Off','2026-01-14 12:29:29','offline','2026-01-14 13:00:15'),(137435,10,'vsol',NULL,'4c:f9:a3:6b:23:24',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 12','',665.00,NULL,'Power Off','2026-01-14 13:33:13','offline','2026-01-14 13:41:39'),(137436,10,'vsol',NULL,'30:f3:35:34:2d:e9',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 13','',546.00,NULL,'Power Off','2026-01-14 12:29:26','offline','2026-01-14 13:00:15'),(137437,10,'vsol',NULL,'30:3d:51:e3:71:7e',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 14','',2613.00,NULL,'Power Off','2026-01-14 12:29:24','offline','2026-01-14 13:00:15'),(137438,10,'vsol',NULL,'b0:7c:12:12:29:ec',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 15','',2608.00,NULL,'Power Off','2026-01-14 12:29:24','offline','2026-01-14 13:00:15'),(137439,10,'vsol',NULL,'38:d4:a5:93:92:1f',NULL,'9c:a2:f4:90:0f:29',101,'2026-01-14 13:41:38',0,'EPON 0/01','ONU 16','',638.00,NULL,'Wire Down','2026-01-14 13:33:08','offline','2026-01-14 13:41:39'),(137440,10,'vsol',NULL,'00:11:41:47:44:0d',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 17','',541.00,NULL,'Wire Down','2026-01-14 12:29:31','offline','2026-01-14 13:00:15'),(137441,10,'vsol',NULL,'4c:d7:c8:df:4b:fe',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 18','',846.00,-15.54,'Power Off','2026-01-14 15:19:59','offline','2026-01-14 15:21:41'),(137442,10,'vsol',NULL,'a2:3e:05:14:07:90',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 19','',598.00,NULL,'Wire Down','2026-01-14 12:29:29','offline','2026-01-14 13:00:15'),(137443,10,'vsol',NULL,'00:d5:9e:77:b2:be',NULL,'50:0f:f5:1b:a2:50',101,'2026-01-14 13:41:38',0,'EPON 0/01','ONU 20','',1611.00,NULL,'Power Off','2026-01-14 13:33:05','offline','2026-01-14 13:41:39'),(137444,10,'vsol',NULL,'00:d5:9f:76:54:0e',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 21','',2049.00,NULL,'Power Off','2026-01-14 12:29:28','offline','2026-01-14 13:00:15'),(137446,10,'vsol',NULL,'00:d3:9e:b8:0b:cc',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 23','',2079.00,NULL,'Power Off','2026-01-14 12:29:28','offline','2026-01-14 13:00:15'),(137447,10,'vsol',NULL,'00:d5:9f:75:db:de',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 24','',1998.00,NULL,'Power Off','2026-01-14 12:29:28','offline','2026-01-14 13:00:15'),(137448,10,'vsol',NULL,'4c:d7:c8:bd:3d:54',NULL,'4c:d7:c8:bd:3d:55',101,'2026-01-14 13:41:38',0,'EPON 0/01','ONU 26','',102.00,NULL,'Power Off','2026-01-14 13:33:03','offline','2026-01-14 13:41:39'),(137449,10,'vsol',NULL,'a2:4f:04:17:c3:90',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 27','',177.00,NULL,'Power Off','2026-01-14 12:29:27','offline','2026-01-14 13:00:16'),(137450,10,'vsol',NULL,'4c:f9:b1:80:f6:ee',NULL,'cc:2d:21:3a:a1:70',101,'2026-01-14 13:41:38',0,'EPON 0/01','ONU 28','',154.00,NULL,'Power Off','2026-01-14 13:33:12','offline','2026-01-14 13:41:39'),(137459,10,'vsol',NULL,'30:3d:51:e4:33:40',NULL,'a8:42:a1:3f:45:43',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 2','',3106.00,-19.10,'Power Off','2026-01-14 13:33:02','offline','2026-01-14 13:41:39'),(137460,10,'vsol',NULL,'a0:7d:09:09:51:f4',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 3','',2728.00,NULL,'Power Off','2026-01-14 12:29:24','offline','2026-01-14 13:00:16'),(137461,10,'vsol',NULL,'a0:7d:04:09:1f:ea',NULL,'58:d9:d5:29:15:28',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 4','',2678.00,NULL,'Power Off','2026-01-14 13:33:02','offline','2026-01-14 13:41:39'),(137462,10,'vsol',NULL,'a2:4f:02:10:5d:10',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 5','',597.00,NULL,'Power Off','2026-01-14 12:29:27','offline','2026-01-14 13:00:16'),(137465,10,'vsol',NULL,'a2:3e:03:07:12:90',NULL,'98:25:4a:f1:a3:b6',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 8','',931.00,NULL,'Power Off','2026-01-14 13:33:04','offline','2026-01-14 13:41:39'),(137466,10,'vsol',NULL,'00:d3:9e:b7:54:ba',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 12','',1082.00,NULL,'Power Off','2026-01-14 12:29:30','offline','2026-01-14 13:00:16'),(137467,10,'vsol',NULL,'88:cf:98:61:ea:2b',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 13','',1590.00,NULL,'Power Off','2026-01-14 12:29:30','offline','2026-01-14 13:00:16'),(137468,10,'vsol',NULL,'a2:4f:06:1a:4b:30',NULL,'10:be:f5:f9:a5:1b',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 14','',1193.00,-20.22,'Power Off','2026-01-14 13:33:05','offline','2026-01-14 13:41:39'),(137469,10,'vsol',NULL,'dc:c6:4b:34:64:a7',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 15','',898.00,NULL,'Power Off','2026-01-14 12:29:32','offline','2026-01-14 13:00:16'),(137470,10,'vsol',NULL,'a2:7e:09:07:67:d0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 16','',747.00,NULL,'Wire Down','2026-01-14 12:29:31','offline','2026-01-14 13:00:16'),(137472,10,'vsol',NULL,'a2:4e:11:12:8e:c0',NULL,'3c:52:a1:ae:88:2f',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 18','',1036.00,-17.17,'Power Off','2026-01-14 13:33:05','offline','2026-01-14 13:41:39'),(137473,10,'vsol',NULL,'a2:3d:09:1b:9a:10',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 19','',1665.00,NULL,'Power Off','2026-01-14 13:33:11','offline','2026-01-14 13:51:42'),(137474,10,'vsol',NULL,'00:11:41:46:a1:c4',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 20','',752.00,NULL,'Power Off','2026-01-14 12:29:30','offline','2026-01-14 13:00:16'),(137476,10,'vsol',NULL,'00:d5:9e:9f:37:de',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 22','',1734.00,NULL,'Power Off','2026-01-14 12:29:32','offline','2026-01-14 13:00:16'),(137477,10,'vsol',NULL,'a2:3e:03:25:c5:00',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 23','',769.00,NULL,'Power Off','2026-01-14 12:29:33','offline','2026-01-14 13:00:16'),(137478,10,'vsol',NULL,'00:d3:9e:b7:56:b2',NULL,'28:3b:82:4d:be:a9',102,'2026-01-14 13:41:38',0,'EPON 0/02','ONU 24','',1506.00,NULL,'Power Off','2026-01-14 13:33:11','offline','2026-01-14 13:41:39'),(137479,10,'vsol',NULL,'a2:4f:08:26:90:58',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 25','',1542.00,NULL,'Power Off','2026-01-14 12:29:35','offline','2026-01-14 15:40:17'),(138683,11,'vsol',NULL,'c0:7e:40:b3:e7:30',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 2','',1336.00,NULL,'Power Off','2026-01-14 09:26:02','offline','2026-01-14 10:33:31'),(138684,11,'vsol',NULL,'4c:d7:c8:d1:ae:59',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 3','',1936.00,NULL,'Power Off','2026-01-14 09:25:44','offline','2026-01-14 10:33:31'),(138686,11,'vsol',NULL,'a2:3e:09:10:01:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 5','',2460.00,NULL,'Power Off','2026-01-14 09:25:37','offline','2026-01-14 10:33:31'),(138687,11,'vsol',NULL,'a2:4f:08:12:36:60',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 6','',2305.00,NULL,'Power Off','2026-01-14 09:25:44','offline','2026-01-14 10:33:31'),(138690,11,'vsol',NULL,'1c:ef:03:df:7a:6c',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 13','',2331.00,NULL,'Power Off','2026-01-14 09:25:56','offline','2026-01-14 10:33:31'),(138691,11,'vsol',NULL,'98:c7:a4:04:9a:6a',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 14','',2057.00,NULL,'Power Off','2026-01-14 09:25:43','offline','2026-01-14 10:33:31'),(138692,11,'vsol',NULL,'a0:7f:06:31:14:14',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 15','',2026.00,NULL,'Power Off','2026-01-14 09:25:39','offline','2026-01-14 10:33:31'),(138693,11,'vsol',NULL,'a0:7d:05:17:84:90',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 16','',4000.00,NULL,'Power Off','2026-01-14 09:25:41','offline','2026-01-14 10:33:31'),(138694,11,'vsol',NULL,'a0:7e:01:12:0e:5c',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 17','',1593.00,NULL,'Power Off','2026-01-14 09:25:35','offline','2026-01-14 10:33:31'),(138695,11,'vsol',NULL,'80:07:1b:e1:38:88',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 18','',2413.00,NULL,'Power Off','2026-01-14 09:25:43','offline','2026-01-14 10:33:31'),(138696,11,'vsol',NULL,'1c:ef:03:c1:2d:68',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 20','',2519.00,NULL,'Power Off','2026-01-14 09:25:57','offline','2026-01-14 10:33:31'),(138697,11,'vsol',NULL,'c0:7e:40:b3:e6:99',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 21','',1810.00,NULL,'Power Off','2026-01-14 09:25:53','offline','2026-01-14 10:33:31'),(138698,11,'vsol',NULL,'a0:7e:11:11:7c:ae',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 22','',2411.00,NULL,'Power Off','2026-01-14 09:25:35','offline','2026-01-14 10:33:31'),(138699,11,'vsol',NULL,'4c:d7:c8:bb:af:c4',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 23','',1972.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:31'),(138701,11,'vsol',NULL,'30:3d:51:e7:c1:02',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 26','',2362.00,NULL,'Power Off','2026-01-14 09:25:55','offline','2026-01-14 10:33:31'),(138702,11,'vsol',NULL,'70:b6:4f:dd:08:88',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 27','',2354.00,NULL,'Power Off','2026-01-14 09:25:58','offline','2026-01-14 10:33:31'),(138703,11,'vsol',NULL,'c0:7e:40:b2:c3:ce',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 28','',2177.00,NULL,'Wire Down','2026-01-14 09:25:58','offline','2026-01-14 10:33:31'),(138705,11,'vsol',NULL,'a2:3e:11:24:2b:e0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 30','',2277.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:31'),(138706,11,'vsol',NULL,'18:c5:8a:c3:dd:ce',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 31','',2436.00,NULL,'Power Off','2026-01-14 09:25:56','offline','2026-01-14 10:33:31'),(138707,11,'vsol',NULL,'10:af:78:f6:46:c5',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 33','',2577.00,NULL,'Power Off','2026-01-14 09:25:49','offline','2026-01-14 10:33:31'),(138708,11,'vsol',NULL,'00:d3:9e:70:bc:82',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 34','',2029.00,NULL,'Power Off','2026-01-14 09:25:46','offline','2026-01-14 10:33:31'),(138709,11,'vsol',NULL,'a2:5c:12:16:a4:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 35','',2369.00,NULL,'Power Off','2026-01-14 09:25:51','offline','2026-01-14 10:33:31'),(138710,11,'vsol',NULL,'a2:4f:02:18:14:20',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 37','',2210.00,NULL,'Power Off','2026-01-14 09:25:47','offline','2026-01-14 10:33:31'),(138712,11,'vsol',NULL,'48:ad:08:5f:c9:1f',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 39','',2182.00,NULL,'Power Off','2026-01-14 09:25:51','offline','2026-01-14 10:33:31'),(138713,11,'vsol',NULL,'a2:3e:06:16:e8:70',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 40','',1972.00,NULL,'Power Off','2026-01-14 09:25:50','offline','2026-01-14 10:33:31'),(138714,11,'vsol',NULL,'10:af:78:fe:7b:37',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 41','',2146.00,NULL,'Power Off','2026-01-14 09:26:01','offline','2026-01-14 10:33:31'),(138715,11,'vsol',NULL,'10:af:78:fe:7a:15',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 42','',2251.00,NULL,'Power Off','2026-01-14 14:22:35','offline','2026-01-14 15:24:02'),(138716,11,'vsol',NULL,'a2:4e:04:09:00:40',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 43','',1498.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:31'),(138717,11,'vsol',NULL,'4c:d7:c8:e3:49:48',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 44','',1728.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:31'),(138718,11,'vsol',NULL,'b0:7c:07:18:19:66',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 45','',3656.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:31'),(138719,11,'vsol',NULL,'00:d3:9e:15:4c:c6',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 46','',3642.00,NULL,'Power Off','2026-01-14 09:25:39','offline','2026-01-14 10:33:31'),(138720,11,'vsol',NULL,'30:3d:51:e3:60:92',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 47','',4649.00,NULL,'Power Off','2026-01-14 09:25:38','offline','2026-01-14 10:33:31'),(138722,11,'vsol',NULL,'a0:7d:01:04:9a:3c',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 51','',4246.00,NULL,'Power Off','2026-01-14 09:25:37','offline','2026-01-14 10:33:31'),(138723,11,'vsol',NULL,'80:d4:a5:43:ba:bf',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 54','',2180.00,NULL,'Power Off','2026-01-14 09:25:53','offline','2026-01-14 10:33:31'),(138724,11,'vsol',NULL,'10:51:72:74:94:cf',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 55','',1924.00,NULL,'Power Off','2026-01-14 09:26:01','offline','2026-01-14 10:33:31'),(138726,11,'vsol',NULL,'a2:4f:05:06:47:90',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 59','',2418.00,NULL,'Power Off','2026-01-14 09:25:45','offline','2026-01-14 10:33:31'),(138727,11,'vsol',NULL,'4c:d7:c8:a1:f9:f0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 60','',2028.00,NULL,'Power Off','2026-01-14 09:25:38','offline','2026-01-14 10:33:31'),(138728,11,'vsol',NULL,'a2:3e:08:25:97:b0',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 61','',1924.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:31'),(138729,11,'vsol',NULL,'a2:4e:04:09:c4:50',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 62','',2315.00,NULL,'Power Off','2026-01-14 09:25:51','offline','2026-01-14 10:33:31'),(138732,11,'vsol',NULL,'a0:7f:06:31:6b:b6',NULL,NULL,NULL,NULL,0,'EPON 0/01','ONU 70','',2000.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:31'),(138751,11,'vsol',NULL,'a0:a3:3b:21:3d:6d',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 2','',2329.00,NULL,'Wire Down','2026-01-14 09:25:56','offline','2026-01-14 10:33:32'),(138754,11,'vsol',NULL,'c0:7e:40:b3:e7:62',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 6','',2431.00,NULL,'Wire Down','2026-01-14 09:25:59','offline','2026-01-14 10:33:32'),(138755,11,'vsol',NULL,'44:55:b1:1a:d7:a4',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 10','',980.00,NULL,'Power Off','2026-01-14 11:00:56','offline','2026-01-14 15:40:34'),(138756,11,'vsol',NULL,'30:3d:51:e4:a2:1a',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 11','',2928.00,NULL,'Power Off','2026-01-14 09:25:40','offline','2026-01-14 10:33:32'),(138757,11,'vsol',NULL,'4c:ae:1c:21:b6:40',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 16','',1839.00,NULL,'Power Off','2026-01-14 09:25:44','offline','2026-01-14 10:33:32'),(138758,11,'vsol',NULL,'30:3d:51:e3:71:ae',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 17','',3854.00,NULL,'Power Off','2026-01-14 09:25:39','offline','2026-01-14 10:33:32'),(138759,11,'vsol',NULL,'b0:7c:09:18:19:06',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 18','',2983.00,NULL,'Power Off','2026-01-14 09:25:38','offline','2026-01-14 10:33:32'),(138760,11,'vsol',NULL,'54:93:59:31:83:58',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 19','',2980.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:32'),(138764,11,'vsol',NULL,'4c:d7:c8:a0:d7:2c',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 27','',2510.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:32'),(138765,11,'vsol',NULL,'a2:3d:09:1a:43:c0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 28','',1693.00,NULL,'Power Off','2026-01-14 09:25:52','offline','2026-01-14 10:33:32'),(138766,11,'vsol',NULL,'80:b5:75:21:f0:0d',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 29','',2485.00,NULL,'Power Off','2026-01-14 09:26:01','offline','2026-01-14 10:33:32'),(138767,11,'vsol',NULL,'4c:d7:c8:bd:af:48',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 30','',2344.00,NULL,'Power Off','2026-01-14 09:25:41','offline','2026-01-14 10:33:32'),(138769,11,'vsol',NULL,'a2:3e:09:26:95:20',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 32','',2564.00,NULL,'Power Off','2026-01-14 09:25:36','offline','2026-01-14 10:33:32'),(138770,11,'vsol',NULL,'90:03:25:1f:ba:e4',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 34','',831.00,NULL,'Power Off','2026-01-14 09:26:00','offline','2026-01-14 10:33:32'),(138771,11,'vsol',NULL,'30:3d:51:e6:69:f8',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 36','',3736.00,NULL,'Power Off','2026-01-14 09:25:40','offline','2026-01-14 10:33:32'),(138772,11,'vsol',NULL,'4c:46:d1:1b:d4:98',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 37','',2610.00,NULL,'Power Off','2026-01-14 09:25:58','offline','2026-01-14 10:33:32'),(138773,11,'vsol',NULL,'f8:98:b9:60:7b:d9',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 38','',2264.00,NULL,'Power Off','2026-01-14 09:25:57','offline','2026-01-14 10:33:32'),(138774,11,'vsol',NULL,'a2:3d:09:19:1e:c0',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 39','',1656.00,NULL,'Power Off','2026-01-14 09:25:49','offline','2026-01-14 10:33:32'),(138775,11,'vsol',NULL,'c0:7e:40:b2:c3:c5',NULL,NULL,NULL,NULL,0,'EPON 0/02','ONU 40','',2192.00,NULL,'Power Off','2026-01-14 09:25:52','offline','2026-01-14 10:33:32'),(138776,11,'vsol',NULL,'a2:3e:06:06:87:00',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 1','',1252.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:32'),(138777,11,'vsol',NULL,'a2:7e:09:11:69:c0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 2','',1279.00,NULL,'Power Off','2026-01-14 09:25:41','offline','2026-01-14 10:33:32'),(138778,11,'vsol',NULL,'30:3d:51:e3:a3:28',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 3','',774.00,NULL,'Power Off','2026-01-14 09:25:54','offline','2026-01-14 10:33:32'),(138779,11,'vsol',NULL,'a2:4f:04:17:c2:c0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 4','',1605.00,NULL,'Power Off','2026-01-14 09:25:45','offline','2026-01-14 10:33:32'),(138781,11,'vsol',NULL,'20:3d:b2:37:40:81',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 6','',1369.00,NULL,'Power Off','2026-01-14 09:25:45','offline','2026-01-14 10:33:32'),(138783,11,'vsol',NULL,'c0:7e:40:a8:64:a3',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 8','',1644.00,NULL,'Power Off','2026-01-14 09:26:02','offline','2026-01-14 10:33:32'),(138785,11,'vsol',NULL,'a0:7f:04:17:61:ce',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 10','',749.00,NULL,'Power Off','2026-01-14 09:25:37','offline','2026-01-14 10:33:32'),(138786,11,'vsol',NULL,'00:d3:9e:b7:09:3c',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 11','',2880.00,NULL,'Power Off','2026-01-14 09:25:43','offline','2026-01-14 10:33:32'),(138787,11,'vsol',NULL,'a2:4e:05:24:82:60',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 12','',738.00,NULL,'Power Off','2026-01-14 09:25:42','offline','2026-01-14 10:33:32'),(138788,11,'vsol',NULL,'38:d4:a5:99:6c:3f',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 13','',49.00,NULL,'Power Off','2026-01-14 09:25:48','offline','2026-01-14 10:33:32'),(138791,11,'vsol',NULL,'6c:68:a4:e8:d0:5b',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 16','',1413.00,NULL,'Power Off','2026-01-14 09:25:39','offline','2026-01-14 10:33:32'),(138793,11,'vsol',NULL,'b0:7c:12:08:22:7e',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 18','',3434.00,NULL,'Power Off','2026-01-14 09:25:40','offline','2026-01-14 10:33:32'),(138794,11,'vsol',NULL,'80:07:1b:e0:7f:88',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 19','',1141.00,NULL,'Power Off','2026-01-14 09:25:38','offline','2026-01-14 10:33:32'),(138796,11,'vsol',NULL,'a2:4f:08:11:17:80',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 21','',92.00,NULL,'Power Off','2026-01-14 09:25:50','offline','2026-01-14 10:33:32'),(138797,11,'vsol',NULL,'4c:f9:b3:a8:85:ac',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 22','',757.00,NULL,'Power Off','2026-01-14 09:26:00','offline','2026-01-14 10:33:32'),(138799,11,'vsol',NULL,'a2:7e:09:07:b9:e0',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 24','',1577.00,NULL,'Power Off','2026-01-14 09:25:48','offline','2026-01-14 10:33:33'),(138802,11,'vsol',NULL,'a0:7e:11:12:ec:82',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 27','',126.00,NULL,'Power Off','2026-01-14 09:25:40','offline','2026-01-14 10:33:33'),(138803,11,'vsol',NULL,'4c:d7:c8:a7:cb:f3',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 28','',34.00,NULL,'Power Off','2026-01-14 09:25:37','offline','2026-01-14 10:33:33'),(138805,11,'vsol',NULL,'e8:bd:d1:19:1a:81',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 30','',759.00,NULL,'Power Off','2026-01-14 09:25:54','offline','2026-01-14 10:33:33'),(138806,11,'vsol',NULL,'a2:7e:09:07:6c:90',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 35','',1590.00,NULL,'Power Off','2026-01-14 09:25:50','offline','2026-01-14 10:33:33'),(138807,11,'vsol',NULL,'00:d3:9e:21:36:46',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 40','',1587.00,NULL,'Power Off','2026-01-14 09:25:54','offline','2026-01-14 10:33:33'),(138808,11,'vsol',NULL,'00:d3:9e:6e:00:18',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 43','',1626.00,NULL,'Power Off','2026-01-14 09:25:53','offline','2026-01-14 10:33:33'),(138809,11,'vsol',NULL,'30:3d:51:e4:4a:f8',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 44','',3657.00,NULL,'Power Off','2026-01-14 09:25:39','offline','2026-01-14 10:33:33'),(138810,11,'vsol',NULL,'68:89:c1:5d:ad:98',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 45','',1679.00,NULL,'Power Off','2026-01-14 09:25:53','offline','2026-01-14 10:33:33'),(138811,11,'vsol',NULL,'6c:68:a4:e9:04:2f',NULL,NULL,NULL,NULL,0,'EPON 0/03','ONU 46','',1615.00,NULL,'Power Off','2026-01-14 09:25:40','offline','2026-01-14 10:33:33'),(138996,12,'vsol',NULL,'00:00:00:01:00:02',NULL,NULL,NULL,NULL,0,'EPON 0/01','','',0.00,NULL,'',NULL,'unknown','2026-01-14 10:59:16'),(168781,11,'vsol',NULL,'8c:de:f9:75:63:b6',NULL,'8c:de:f9:75:63:b4',201,'2026-01-14 15:04:17',0,'EPON 0/01','ONU 42',':',2251.00,-25.69,'Power Off','2026-01-14 09:25:56','online','2026-01-14 15:04:18');
/*!40000 ALTER TABLE `olt_mac_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `olts`
--

DROP TABLE IF EXISTS `olts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `vendor` enum('huawei','zte','bdcom','vsol') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mgmt_proto` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'telnet',
  `host` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `snmp_community` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'public',
  `ssh_port` int NOT NULL DEFAULT '22',
  `telnet_port` int NOT NULL DEFAULT '23',
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enable_password` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prompt_regex` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u1` (`host`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `olts`
--

LOCK TABLES `olts` WRITE;
/*!40000 ALTER TABLE `olts` DISABLE KEYS */;
INSERT INTO `olts` VALUES (10,'OLT-Khuroil','vsol','telnet','192.168.110.2','public',23,23,'A','eVuUxxXWbXpe1rGgkIVH4r7JD72j6bTU24JvgrPkhnU=','w7nfB9JI1SckCROmhGPSpXywx2jHW3jq98H1AsoDNvM=','(>|#)\\s*$',1,'2025-12-13 07:22:20'),(11,'OLT-Cholash','vsol','telnet','192.168.120.2','public',23,23,'A','p4qc7arpFfDLFw/fPlgWbEjZll6Ip1iwMGbhqY8rA6g=','81GIRyFaDWSMb2umgUtROd5bxn34cF7AccnLiUIRww4=','(>|#)\\s*$',1,'2025-12-13 07:22:43'),(12,'OLT-Office','vsol','telnet','192.168.130.2','public',23,23,'A','u65Vgl/kW7Qq/2hTewP2IdmIWVTs1t4SsIDYtTOqQRg=','XBRCn7I01Fi7zbdiWqDvyJIaQaR2ked7wt8MPGMqPrQ=','(>|#)\\s*$',1,'2025-12-13 07:23:06');
/*!40000 ALTER TABLE `olts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `router_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `speed` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL,
  `validity` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duration_days` int NOT NULL DEFAULT '30',
  `profile_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_packages_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (43,NULL,'5Mbps',NULL,'N/A',1,500.00,30,NULL,30,NULL,0);
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `otp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `client_id` int NOT NULL,
  `bill_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_date` datetime NOT NULL,
  `method` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `txn_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_by` int DEFAULT NULL,
  `transaction_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `wallet_id` int DEFAULT NULL,
  `received_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reseller_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_method_txn` (`method`,`txn_id`),
  KEY `fk_payments_invoice` (`bill_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_pay_client` (`client_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_pay_invoice` (`invoice_id`),
  KEY `idx_pay_txn` (`txn_id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_client` (`client_id`),
  KEY `idx_payments_account` (`account_id`),
  KEY `idx_payments_received_by` (`received_by`),
  KEY `fk_payments_wallet` (`wallet_id`),
  KEY `idx_payments_account_id` (`account_id`),
  KEY `idx_pay_date` (`payment_date`),
  KEY `idx_pay_method` (`method`),
  KEY `idx_pay_acc` (`account_id`),
  KEY `idx_pay_recv` (`received_by`),
  KEY `idx_payments_txn` (`txn_id`),
  KEY `idx_payments_method` (`method`),
  KEY `idx_payments_method_txn` (`method`,`txn_id`),
  KEY `idx_payments_paid_at` (`paid_at`),
  CONSTRAINT `fk_pay_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_pay_ins` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
  CALL sp_sync_invoice_totals(NEW.bill_id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_pay_upd` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
  CALL sp_sync_invoice_totals(NEW.bill_id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_pay_del` AFTER DELETE ON `payments` FOR EACH ROW BEGIN
  CALL sp_sync_invoice_totals(OLD.bill_id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_view` tinyint(1) DEFAULT '0',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  `perm_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_perm_key` (`perm_key`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Add Client','',0,0,0,'add.client',NULL),(2,'Edit Client','',0,0,0,'edit.client',NULL),(3,'Delete Client','',0,0,0,'delete.client',NULL),(4,'View Billing','',0,0,0,'view.billing',NULL),(5,'Generate Invoice','',0,0,0,'generate.invoice',NULL),(21,'View All','',0,0,0,'view.all',NULL),(24,'client.view','',1,0,0,'client.view',NULL),(25,'Create client','',1,1,0,'crate.client',NULL),(27,'Mark/Undo Left Client','',1,1,0,'mark.left',NULL),(28,'View routers','',1,0,0,'view.routers',NULL),(29,'Edit routers','',1,1,0,'edit.routers',NULL),(30,'Enable/Disable PPP user','',1,1,0,'enable.disable.ppp',NULL),(31,'Bulk profile change','',1,1,0,'profile.change',NULL),(32,'View billing dashboard','',1,0,0,'view.billing.dhashboard',NULL),(33,'Generate monthly invoices','',1,1,0,'generate.monthly.invoice',NULL),(34,'Add payment','',1,1,0,'add.payments',NULL),(35,'View reports','',1,0,0,'view.reports',NULL),(36,'Export CSV/Excel','',1,0,0,'export.csv',NULL),(37,'Manage global settings','',1,1,0,'manage.global.setting',NULL),(38,'User & Role management','',1,1,0,'role.management',NULL),(39,'View audit log','',1,0,0,'view.audit.log',NULL),(75,'Account View','',0,0,0,'accounts.view',NULL),(76,'Account Wallet','',0,0,0,'accounts.wallet',NULL),(81,'hrm','',0,0,0,'hrm.view','View employees and profiles'),(88,'hr.add','',0,0,0,'hr.add','Add a new employee'),(89,'hr.edit','',0,0,0,'hr.edit','Edit employee info'),(90,'hr.toggle','',0,0,0,'hr.toggle','Toggle employee status'),(91,'hr.export','',0,0,0,'hr.export','Export employee list'),(92,'hr.audit','',0,0,0,'hr.audit','View HR audit logs'),(99,'Dashboard','',0,0,0,'view.dashboard',NULL),(100,'clients','',0,0,0,'clients',NULL),(101,'view.all.bill','',0,0,0,'view.all.bill',NULL),(102,'view.pai.bill','',0,0,0,'view.pai.bill',NULL),(103,'view.package','',0,0,0,'view.package',NULL),(104,'view.accounts','',0,0,0,'view.accounts',NULL),(105,'wallet.approval','',0,0,0,'wallet.approval',NULL),(106,'wallet.settlement','',0,0,0,'wallet.settlement',NULL),(107,'expense.view','',0,0,0,'expense.view',NULL),(108,'expense.add','',0,0,0,'expense.add',NULL),(109,'olt.view','',0,0,0,'olt.view',NULL),(110,'sms.view','',0,0,0,'sms.view',NULL),(111,'report.view','',0,0,0,'report.view',NULL),(112,'view.wallets','',0,0,0,'view.wallets',NULL),(113,'user.permission','',0,0,0,'user.permission',NULL),(114,'tickets','',0,0,0,'tickets',NULL),(115,'due.report','',0,0,0,'due.report',NULL),(116,'due.report.pro','',0,0,0,'due.report.pro',NULL),(117,'bill.report','',0,0,0,'bill.report',NULL),(118,'payment.reports','',0,0,0,'payment.reports',NULL),(119,'income.expense','',0,0,0,'income.expense',NULL),(120,'Bbb','',0,0,0,'220033',NULL),(121,'Reseller: View','',0,0,0,'reseller.view',NULL),(122,'Reseller: Manage','',0,0,0,'reseller.manage',NULL),(123,'Reseller: Pricing','',0,0,0,'reseller.pricing',NULL),(124,'Reseller Dashboard','',0,0,0,'reseller.dashboard','Access reseller dashboard'),(125,'Reseller Clients (View)','',0,0,0,'reseller.clients.view','View reseller client list'),(126,'Reseller Clients (Add)','',0,0,0,'reseller.clients.add','Add reseller clients'),(127,'Reseller Invoices','',0,0,0,'reseller.invoices.view','View reseller invoices'),(128,'Reseller Payments','',0,0,0,'reseller.payments.view','View reseller payments'),(129,'Reseller Packages','',0,0,0,'reseller.packages.view','View reseller package rates'),(130,'Reseller Wallet','',0,0,0,'reseller.wallet.view','View reseller wallet transactions'),(131,'Reseller Top-ups','',0,0,0,'reseller.wallet.topups','View reseller top-up history'),(132,'Reseller Profile','',0,0,0,'reseller.profile.view','View reseller profile'),(133,'Reseller Clients (Renew)','',0,0,0,'reseller.clients.renew','Renew reseller clients'),(134,'Reseller Payments (Add)','',0,0,0,'reseller.payments.add','Add reseller payments'),(135,'HRM Delete','',0,0,0,'hr.delete','Delete employees');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portal_users`
--

DROP TABLE IF EXISTS `portal_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `username` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `portal_users_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portal_users`
--

LOCK TABLES `portal_users` WRITE;
/*!40000 ALTER TABLE `portal_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `portal_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reseller_package_rates`
--

DROP TABLE IF EXISTS `reseller_package_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_package_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reseller_user_id` int NOT NULL,
  `package_id` int NOT NULL,
  `sell_rate` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rpr` (`reseller_user_id`,`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_package_rates`
--

LOCK TABLES `reseller_package_rates` WRITE;
/*!40000 ALTER TABLE `reseller_package_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `reseller_package_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reseller_packages`
--

DROP TABLE IF EXISTS `reseller_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reseller_id` int NOT NULL,
  `package_id` int NOT NULL,
  `mode` enum('fixed','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'fixed',
  `price_override` decimal(10,2) DEFAULT NULL,
  `share_percent` decimal(6,3) DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rpkg` (`reseller_id`,`package_id`),
  KEY `idx_rpkg_reseller` (`reseller_id`),
  KEY `idx_rpkg_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_packages`
--

LOCK TABLES `reseller_packages` WRITE;
/*!40000 ALTER TABLE `reseller_packages` DISABLE KEYS */;
/*!40000 ALTER TABLE `reseller_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reseller_users`
--

DROP TABLE IF EXISTS `reseller_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_users` (
  `reseller_id` int NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`reseller_id`,`user_id`),
  KEY `idx_ru_reseller` (`reseller_id`),
  KEY `idx_ru_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_users`
--

LOCK TABLES `reseller_users` WRITE;
/*!40000 ALTER TABLE `reseller_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `reseller_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reseller_wallet_topups`
--

DROP TABLE IF EXISTS `reseller_wallet_topups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_wallet_topups` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `reseller_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) DEFAULT NULL,
  `txn_id` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) DEFAULT NULL,
  `ref_code` varchar(100) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rwt_reseller` (`reseller_id`),
  KEY `idx_rwt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_wallet_topups`
--

LOCK TABLES `reseller_wallet_topups` WRITE;
/*!40000 ALTER TABLE `reseller_wallet_topups` DISABLE KEYS */;
/*!40000 ALTER TABLE `reseller_wallet_topups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reseller_wallet_txns`
--

DROP TABLE IF EXISTS `reseller_wallet_txns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reseller_wallet_txns` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `reseller_id` int NOT NULL,
  `type` enum('deposit','withdraw','commission','adjust') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ref_table` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rw_reseller` (`reseller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reseller_wallet_txns`
--

LOCK TABLES `reseller_wallet_txns` WRITE;
/*!40000 ALTER TABLE `reseller_wallet_txns` DISABLE KEYS */;
/*!40000 ALTER TABLE `reseller_wallet_txns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resellers`
--

DROP TABLE IF EXISTS `resellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resellers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reseller_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `balance` decimal(12,2) DEFAULT '0.00',
  `status` tinyint DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reseller_code` (`reseller_code`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resellers`
--

LOCK TABLES `resellers` WRITE;
/*!40000 ALTER TABLE `resellers` DISABLE KEYS */;
/*!40000 ALTER TABLE `resellers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(2,1),(4,1),(5,1),(1,2),(2,2),(4,2),(1,3),(2,3),(4,3),(1,4),(2,4),(4,4),(5,4),(1,5),(2,5),(4,5),(1,21),(2,21),(4,21),(1,24),(2,24),(4,24),(5,24),(1,25),(2,25),(4,25),(5,25),(1,27),(2,27),(4,27),(1,28),(2,28),(4,28),(1,29),(2,29),(4,29),(1,30),(2,30),(4,30),(1,31),(2,31),(4,31),(1,32),(2,32),(4,32),(1,33),(2,33),(4,33),(1,34),(2,34),(4,34),(5,34),(1,35),(2,35),(4,35),(1,36),(2,36),(4,36),(1,37),(2,37),(4,37),(1,38),(2,38),(4,38),(1,39),(2,39),(4,39),(1,75),(2,75),(4,75),(5,75),(1,76),(2,76),(4,76),(5,76),(1,81),(2,81),(4,81),(5,81),(23,81),(1,88),(2,88),(4,88),(1,89),(2,89),(4,89),(5,89),(1,90),(2,90),(4,90),(1,91),(2,91),(4,91),(1,92),(2,92),(4,92),(1,99),(2,99),(4,99),(5,99),(1,100),(2,100),(4,100),(5,100),(2,101),(4,101),(2,102),(4,102),(2,103),(4,103),(5,103),(2,104),(4,104),(2,105),(4,105),(2,106),(4,106),(2,107),(4,107),(2,108),(4,108),(2,109),(4,109),(23,109),(2,110),(4,110),(2,111),(4,111),(5,111),(2,112),(4,112),(5,112),(23,112),(2,113),(4,113),(2,114),(4,114),(5,114),(2,115),(4,115),(2,116),(4,116),(1,117),(2,117),(4,117),(5,117),(2,118),(4,118),(2,119),(4,119),(1,121),(5,121),(1,122),(1,123),(5,124),(5,125),(5,126),(5,128),(5,129),(5,130),(5,131),(5,132),(5,133),(5,134),(5,135);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin',NULL,'Administrator','2025-08-25 01:15:58'),(2,'manager',NULL,'Manager','2025-08-25 01:15:58'),(3,'accounts',NULL,'Accounts','2025-08-25 01:15:58'),(4,'support',NULL,'Support','2025-08-25 01:15:58'),(5,'reseller',NULL,'Reseller','2025-08-25 01:15:58'),(6,'viewer',NULL,'Read-only Viewer','2025-08-25 01:15:58'),(23,'hr_manager',NULL,'HR Manager','2025-09-01 08:57:35');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `routers`
--

DROP TABLE IF EXISTS `routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `routers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('mikrotik','olt','switch','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_port` int DEFAULT '8728',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snmp_community` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_router_ip` (`ip`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `routers`
--

LOCK TABLES `routers` WRITE;
/*!40000 ALTER TABLE `routers` DISABLE KEYS */;
INSERT INTO `routers` VALUES (11,'Access_Router','mikrotik','157.10.243.105','Amir','Amir@0098##',1122,1,NULL,'public',NULL,1,'2025-12-13 07:24:07',NULL,NULL);
/*!40000 ALTER TABLE `routers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'BKASH_MERCHANT_NUMBER','01303326003'),(2,'BKASH_BASE_URL',''),(3,'BKASH_APP_KEY','FTn3LRJMaQ5O96GxvjPI7ie6tc'),(4,'BKASH_USERNAME','01303326003'),(5,'BKASH_WEBHOOK_IP_WHITELIST',''),(6,'BKASH_APP_SECRET','hL8gwHotEfARCDhT4VSePlsvinGQigxZ8WRiRHENcSHZE2Az0nsG'),(7,'BKASH_PASSWORD','0r(,CuLwh%3'),(8,'BKASH_RTN_WEBHOOK_TOKEN','ce87ed7e0a7c5757ece4095f831bee9c80402b2beaf7003e'),(9,'company_name','Rajamehar Online'),(10,'company_email','bdidamir@gmail.com'),(11,'company_address1','Fazlur Rahman Molla Super Market, Rajamehar Bazar, Rajamehar, Debidwer'),(12,'company_address2',''),(13,'company_address','Fazlur Rahman Molla Super Market, Rajamehar Bazar, Rajamehar, Debidwer'),(14,'company_mobile1','+8801303120098'),(15,'company_mobile2',''),(16,'company_phone1',''),(17,'company_phone2',''),(18,'company_phone','+8801303120098'),(19,'company_logo','/uploads/company/company_logo.png'),(20,'client_code_mode','auto'),(21,'show_login_brand','0'),(22,'sms_provider',''),(23,'sms_user',''),(24,'sms_sender',''),(25,'sms_password',''),(26,'sms_api_url',''),(27,'sms_api_key',''),(28,'BKASH_WEBHOOK_TOKEN','ce87ed7e0a7c5757ece4095f831bee9c80402b2beaf7003e');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settlements`
--

DROP TABLE IF EXISTS `settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settlements` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `wallet_id` int NOT NULL,
  `company_wallet_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `settled_by` int NOT NULL,
  `settled_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_settle_src` (`wallet_id`),
  KEY `fk_settle_dst` (`company_wallet_id`),
  CONSTRAINT `fk_settle_dst` FOREIGN KEY (`company_wallet_id`) REFERENCES `wallets` (`id`),
  CONSTRAINT `fk_settle_src` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlements`
--

LOCK TABLES `settlements` WRITE;
/*!40000 ALTER TABLE `settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_groups`
--

DROP TABLE IF EXISTS `sms_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_name` varchar(120) NOT NULL,
  `member_type` varchar(30) NOT NULL DEFAULT 'all',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_groups`
--

LOCK TABLES `sms_groups` WRITE;
/*!40000 ALTER TABLE `sms_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_inbox`
--

DROP TABLE IF EXISTS `sms_inbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_inbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gateway` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'httpsms',
  `msisdn_from` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `msisdn_to` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `raw_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `trx_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `sender_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ref_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `processed` tinyint(1) DEFAULT '0',
  `error_msg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_trx` (`gateway`,`trx_id`),
  CONSTRAINT `sms_inbox_chk_1` CHECK (json_valid(`meta_json`))
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_inbox`
--

LOCK TABLES `sms_inbox` WRITE;
/*!40000 ALTER TABLE `sms_inbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_inbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_queue`
--

DROP TABLE IF EXISTS `sms_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(480) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint NOT NULL DEFAULT '0',
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `scheduled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  `dedupe_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dedupe` (`dedupe_key`),
  KEY `status` (`status`),
  KEY `scheduled_at` (`scheduled_at`),
  CONSTRAINT `sms_queue_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_queue`
--

LOCK TABLES `sms_queue` WRITE;
/*!40000 ALTER TABLE `sms_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_link_tokens`
--

DROP TABLE IF EXISTS `telegram_link_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_link_tokens` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `token` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `client_id` bigint NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_link_tokens`
--

LOCK TABLES `telegram_link_tokens` WRITE;
/*!40000 ALTER TABLE `telegram_link_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_link_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_queue`
--

DROP TABLE IF EXISTS `telegram_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `template_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` enum('queued','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'queued',
  `retries` int NOT NULL DEFAULT '0',
  `send_after` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  `uniq_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`send_after`),
  KEY `idx_client` (`client_id`),
  KEY `idx_uniq` (`uniq_key`),
  CONSTRAINT `telegram_queue_chk_1` CHECK (json_valid(`payload_json`))
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_queue`
--

LOCK TABLES `telegram_queue` WRITE;
/*!40000 ALTER TABLE `telegram_queue` DISABLE KEYS */;
INSERT INTO `telegram_queue` VALUES (101,21817,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4339,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2025-12-24 23:45:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2025-12-24 23:45:35','2025-12-24 23:45:35','pay-confirm-1','No active Telegram subscriber','2025-12-24 23:45:35'),(102,21826,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4345,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2025-12-31 14:44:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2025-12-31 14:45:14','2025-12-31 14:45:14','pay-confirm-2','No active Telegram subscriber','2025-12-31 14:45:14'),(103,21826,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4345,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2025-12-31 15:18:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2025-12-31 15:18:45','2025-12-31 15:18:45','pay-confirm-3','No active Telegram subscriber','2025-12-31 15:18:45'),(104,21826,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4345,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-01 21:57:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-01 21:58:09','2026-01-01 21:58:09','pay-confirm-4','No active Telegram subscriber','2026-01-01 21:58:09'),(105,21831,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4347,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-02 15:11:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-02 15:11:22','2026-01-02 15:11:22','pay-confirm-5','No active Telegram subscriber','2026-01-02 15:11:22'),(106,21843,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4356,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-02 19:41:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-02 19:41:35','2026-01-02 19:41:35','pay-confirm-6','No active Telegram subscriber','2026-01-02 19:41:35'),(107,21844,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4357,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-02 20:19:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-02 20:19:37','2026-01-02 20:19:37','pay-confirm-7','No active Telegram subscriber','2026-01-02 20:19:37'),(108,21845,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4358,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-02 20:28:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-02 20:28:54','2026-01-02 20:28:54','pay-confirm-8','No active Telegram subscriber','2026-01-02 20:28:54'),(109,21846,'payment_confirm','{\"amount\":\"500.00\",\"invoice_id\":4359,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-02 21:17:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-02 21:18:00','2026-01-02 21:17:59','pay-confirm-9','No active Telegram subscriber','2026-01-02 21:17:59'),(110,21853,'payment_confirm','{\"amount\":\"300.00\",\"invoice_id\":4364,\"portal_link\":\"\",\"method\":\"Cash\",\"txn_id\":\"\",\"paid_at\":\"2026-01-03 08:40:00\",\"received_by\":1,\"received_by_name\":\"super administer\"}','queued',0,'2026-01-03 08:40:15','2026-01-03 08:40:15','pay-confirm-10','No active Telegram subscriber','2026-01-03 08:40:15');
/*!40000 ALTER TABLE `telegram_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_settings`
--

DROP TABLE IF EXISTS `telegram_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_settings` (
  `k` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `v` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_settings`
--

LOCK TABLES `telegram_settings` WRITE;
/*!40000 ALTER TABLE `telegram_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_subscribers`
--

DROP TABLE IF EXISTS `telegram_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_subscribers` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `client_id` bigint NOT NULL,
  `chat_id` bigint NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_client` (`client_id`),
  UNIQUE KEY `uk_chat` (`chat_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_subscribers`
--

LOCK TABLES `telegram_subscribers` WRITE;
/*!40000 ALTER TABLE `telegram_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_templates`
--

DROP TABLE IF EXISTS `telegram_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`),
  UNIQUE KEY `uk_active` (`template_key`,`active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_templates`
--

LOCK TABLES `telegram_templates` WRITE;
/*!40000 ALTER TABLE `telegram_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `telegram_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_replies`
--

DROP TABLE IF EXISTS `ticket_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_replies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_type` enum('client','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_replies`
--

LOCK TABLES `ticket_replies` WRITE;
/*!40000 ALTER TABLE `ticket_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mobile_existing` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_bill` decimal(10,2) DEFAULT NULL,
  `last_paid_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mikrotik_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uptime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_logout_time` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mac_caller_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_vendor_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connectivity_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `downloaded_data` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_data` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_mac_address` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `olt_port` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problem_category` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problem_priority` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complained_number` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `olt_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `optical_power` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onu_mac_serial` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onu_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_deregister_time` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_deregister_reasons` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `send_sms` tinyint(1) DEFAULT '0',
  `assigned_to` int DEFAULT NULL,
  `solved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permission_denies`
--

DROP TABLE IF EXISTS `user_permission_denies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permission_denies` (
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `fk_ud_perm` (`permission_id`),
  CONSTRAINT `fk_ud_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ud_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permission_denies`
--

LOCK TABLES `user_permission_denies` WRITE;
/*!40000 ALTER TABLE `user_permission_denies` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permission_denies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_registrations`
--

DROP TABLE IF EXISTS `user_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_registrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pass_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `otp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `consumed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_registrations`
--

LOCK TABLES `user_registrations` WRITE;
/*!40000 ALTER TABLE `user_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `session_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `login_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','account','manager','support','viewer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'account',
  `user_image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `mobile` (`mobile`),
  KEY `idx_users_role_id` (`role_id`),
  CONSTRAINT `fk_users_role_isp` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','21232f297a57a5a743894a0e4a801fc3',NULL,'super administer','admin',NULL,1,NULL,'2025-12-13 15:21:02',NULL,1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `wallet_id` int NOT NULL,
  `payment_id` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `direction` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `reason` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wt_wallet` (`wallet_id`),
  KEY `fk_wt_payment` (`payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_transactions`
--

LOCK TABLES `wallet_transactions` WRITE;
/*!40000 ALTER TABLE `wallet_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transfers`
--

DROP TABLE IF EXISTS `wallet_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_account_id` int NOT NULL,
  `to_account_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ref_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_account_id` (`from_account_id`),
  KEY `to_account_id` (`to_account_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_wt_status` (`status`),
  KEY `idx_wt_approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_transfers`
--

LOCK TABLES `wallet_transfers` WRITE;
/*!40000 ALTER TABLE `wallet_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime DEFAULT NULL,
  `employee_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_employee` (`employee_id`),
  UNIQUE KEY `uniq_wallet_empid` (`employee_id`),
  UNIQUE KEY `uniq_wallet_empcode` (`employee_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'isp_billing'
--
/*!50003 DROP PROCEDURE IF EXISTS `sp_sync_invoice_totals` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sync_invoice_totals`(IN p_bill_id INT)
proc: BEGIN
  DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_discount DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_paid DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_net DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_status VARCHAR(10) DEFAULT 'unpaid';
  DECLARE v_cnt INT DEFAULT 0;

  SELECT COUNT(*) INTO v_cnt FROM invoices WHERE id = p_bill_id;
  IF v_cnt = 0 THEN
    LEAVE proc;
  END IF;

  SELECT IFNULL(total,0), IFNULL(discount,0) INTO v_total, v_discount
  FROM invoices WHERE id = p_bill_id LIMIT 1;

  SELECT IFNULL(SUM(amount),0) INTO v_paid FROM payments WHERE bill_id = p_bill_id;

  SET v_net = ROUND(GREATEST(v_total - v_discount, 0), 2);
  IF v_paid + 0.01 >= v_net THEN
    SET v_status = 'paid';
  ELSEIF v_paid > 0 THEN
    SET v_status = 'partial';
  ELSE
    SET v_status = 'unpaid';
  END IF;

  UPDATE invoices
    SET paid_amount = ROUND(v_paid,2),
        status = v_status
    WHERE id = p_bill_id;

  UPDATE bills
    SET status = IF(v_paid + 0.01 >= amount, 'paid', 'due')
    WHERE id = p_bill_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-14 15:46:53

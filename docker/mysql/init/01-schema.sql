-- On-Cockpit Betting Station: database STRUCTURE only (no event, bet or account data).
-- Loaded automatically by MariaDB the first time the database volume is created.

/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(60) DEFAULT NULL,
  `password` varchar(60) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `account_date_created` datetime DEFAULT NULL,
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_remittances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_remittances` (
  `admin_remittance_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `denom_1000` int(11) DEFAULT NULL,
  `denom_500` int(11) DEFAULT NULL,
  `denom_200` int(11) DEFAULT NULL,
  `denom_100` int(11) DEFAULT NULL,
  `denom_50` int(11) DEFAULT NULL,
  `denom_20` int(11) DEFAULT NULL,
  `denom_10` int(11) DEFAULT NULL,
  `denom_5` int(11) DEFAULT NULL,
  `denom_1` int(11) DEFAULT NULL,
  PRIMARY KEY (`admin_remittance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_remittances_view`;
/*!50001 DROP VIEW IF EXISTS `admin_remittances_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `admin_remittances_view` AS SELECT
 NULL AS `admin_remittance_id`,
 NULL AS `event_id`,
 NULL AS `denom_1000`,
 NULL AS `denom_500`,
 NULL AS `denom_200`,
 NULL AS `denom_100`,
 NULL AS `denom_50`,
 NULL AS `denom_20`,
 NULL AS `denom_10`,
 NULL AS `denom_5`,
 NULL AS `denom_1`,
 NULL AS `event_name` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `admin_shorts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_shorts` (
  `admin_short_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_remittance_id` int(11) DEFAULT NULL,
  `short_amount` decimal(16,2) DEFAULT NULL,
  `short_remarks` text DEFAULT NULL,
  PRIMARY KEY (`admin_short_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `admin_name` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admins_view`;
/*!50001 DROP VIEW IF EXISTS `admins_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `admins_view` AS SELECT
 NULL AS `admin_id`,
 NULL AS `account_id`,
 NULL AS `admin_name`,
 NULL AS `username`,
 NULL AS `password`,
 NULL AS `account_type`,
 NULL AS `is_active`,
 NULL AS `account_date_created` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `bets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bets` (
  `bet_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_teller_id` int(11) DEFAULT NULL,
  `match_id` int(11) DEFAULT NULL,
  `bet_receipt_code` varchar(100) DEFAULT NULL,
  `bet_side` varchar(5) DEFAULT NULL,
  `bet_amount` decimal(16,2) DEFAULT NULL,
  `bet_win_amount` decimal(16,2) DEFAULT NULL,
  `bet_payout_amount` decimal(16,2) DEFAULT NULL,
  `bet_payout_datetime` datetime DEFAULT NULL,
  `bet_status` tinyint(1) DEFAULT NULL,
  `bet_datetime` datetime DEFAULT NULL,
  `bet_is_winner` tinyint(1) DEFAULT NULL,
  `bet_void_datetime` datetime DEFAULT NULL,
  `bet_payout_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`bet_id`),
  KEY `NewIndex1` (`event_teller_id`,`match_id`,`bet_status`,`bet_side`,`bet_receipt_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bets_view`;
/*!50001 DROP VIEW IF EXISTS `bets_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `bets_view` AS SELECT
 NULL AS `bet_id`,
 NULL AS `event_teller_id`,
 NULL AS `match_id`,
 NULL AS `bet_receipt_code`,
 NULL AS `bet_side`,
 NULL AS `bet_amount`,
 NULL AS `bet_win_amount`,
 NULL AS `bet_payout_amount`,
 NULL AS `bet_payout_datetime`,
 NULL AS `bet_status`,
 NULL AS `bet_datetime`,
 NULL AS `bet_is_winner`,
 NULL AS `bet_void_datetime`,
 NULL AS `bet_payout_by`,
 NULL AS `teller_id`,
 NULL AS `teller_balance`,
 NULL AS `teller_match_balance`,
 NULL AS `teller_name`,
 NULL AS `contact_number`,
 NULL AS `phone_uid`,
 NULL AS `match_number`,
 NULL AS `match_winner`,
 NULL AS `meron_total_bet`,
 NULL AS `wala_total_bet`,
 NULL AS `meron_odds`,
 NULL AS `wala_odds`,
 NULL AS `match_status`,
 NULL AS `match_bet_status`,
 NULL AS `meron_bet_status`,
 NULL AS `wala_bet_status`,
 NULL AS `match_created_datetime`,
 NULL AS `event_id`,
 NULL AS `event_name`,
 NULL AS `event_description`,
 NULL AS `event_date`,
 NULL AS `event_status` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `cash_ins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_ins` (
  `cash_in_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `event_teller_id` int(11) DEFAULT NULL,
  `cash_in_amount` decimal(16,2) DEFAULT NULL,
  `cash_in_datetime` datetime DEFAULT NULL,
  `cash_in_type` varchar(60) DEFAULT NULL,
  `cash_in_status` varchar(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cash_in_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_ins_view`;
/*!50001 DROP VIEW IF EXISTS `cash_ins_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `cash_ins_view` AS SELECT
 NULL AS `cash_in_id`,
 NULL AS `admin_id`,
 NULL AS `event_teller_id`,
 NULL AS `cash_in_amount`,
 NULL AS `cash_in_datetime`,
 NULL AS `cash_in_type`,
 NULL AS `cash_in_status`,
 NULL AS `admin_name`,
 NULL AS `teller_id`,
 NULL AS `teller_balance`,
 NULL AS `teller_name`,
 NULL AS `contact_number`,
 NULL AS `phone_uid`,
 NULL AS `event_name`,
 NULL AS `event_description`,
 NULL AS `event_date`,
 NULL AS `event_status`,
 NULL AS `admin_cash_on_hand`,
 NULL AS `event_id` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `cash_outs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_outs` (
  `cash_out_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_teller_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `cash_out_amount` decimal(16,2) DEFAULT NULL,
  `cash_out_status` varchar(20) DEFAULT NULL,
  `cash_out_datetime` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cash_out_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_outs_view`;
/*!50001 DROP VIEW IF EXISTS `cash_outs_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `cash_outs_view` AS SELECT
 NULL AS `cash_out_id`,
 NULL AS `event_teller_id`,
 NULL AS `admin_id`,
 NULL AS `cash_out_amount`,
 NULL AS `cash_out_status`,
 NULL AS `cash_out_datetime`,
 NULL AS `event_id`,
 NULL AS `teller_id`,
 NULL AS `teller_balance`,
 NULL AS `teller_match_balance`,
 NULL AS `event_name`,
 NULL AS `event_description`,
 NULL AS `event_date`,
 NULL AS `event_status`,
 NULL AS `admin_cash_on_hand`,
 NULL AS `teller_name`,
 NULL AS `contact_number`,
 NULL AS `phone_uid`,
 NULL AS `admin_name` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `claims` (
  `claim_id` int(11) NOT NULL AUTO_INCREMENT,
  `bet_id` int(11) DEFAULT NULL,
  `event_teller_id` int(11) DEFAULT NULL,
  `claim_amount` decimal(16,2) DEFAULT NULL,
  `claim_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`claim_id`),
  UNIQUE KEY `claims_bet_id_unique` (`bet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `claims_view`;
/*!50001 DROP VIEW IF EXISTS `claims_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `claims_view` AS SELECT
 NULL AS `claim_id`,
 NULL AS `bet_id`,
 NULL AS `event_teller_id`,
 NULL AS `claim_amount`,
 NULL AS `claim_datetime`,
 NULL AS `teller_name`,
 NULL AS `match_number`,
 NULL AS `match_winner`,
 NULL AS `meron_odds`,
 NULL AS `wala_odds`,
 NULL AS `bet_receipt_code`,
 NULL AS `bet_side` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `event_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_closings` (
  `event_closing_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `sequence_no` int(10) unsigned NOT NULL,
  `payload` longtext NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `detail_sha256` char(64) NOT NULL,
  `prev_payload_sha256` char(64) NOT NULL,
  `signature` text NOT NULL,
  `key_fingerprint` char(16) NOT NULL,
  `seal_code` varchar(19) NOT NULL,
  `detail_file` varchar(255) NOT NULL,
  `server_id` varchar(60) NOT NULL,
  `closed_by_account_id` int(11) NOT NULL,
  `approved_by_account_id` int(11) DEFAULT NULL,
  `closed_at` datetime NOT NULL,
  `downloaded_at` datetime DEFAULT NULL,
  `downloaded_by_account_id` int(11) DEFAULT NULL,
  `ack_code` varchar(200) DEFAULT NULL,
  `ack_at` datetime DEFAULT NULL,
  `ack_by_account_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`event_closing_id`),
  UNIQUE KEY `event_closings_event_id_unique` (`event_id`),
  UNIQUE KEY `event_closings_sequence_no_unique` (`sequence_no`),
  UNIQUE KEY `event_closings_payload_sha256_unique` (`payload_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017*/ /*!50003 TRIGGER event_closings_no_update BEFORE UPDATE ON event_closings FOR EACH ROW
      BEGIN
        IF NOT (NEW.event_id <=> OLD.event_id AND NEW.sequence_no <=> OLD.sequence_no
            AND NEW.payload <=> OLD.payload AND NEW.payload_sha256 <=> OLD.payload_sha256
            AND NEW.detail_sha256 <=> OLD.detail_sha256 AND NEW.prev_payload_sha256 <=> OLD.prev_payload_sha256
            AND NEW.signature <=> OLD.signature AND NEW.key_fingerprint <=> OLD.key_fingerprint
            AND NEW.seal_code <=> OLD.seal_code AND NEW.detail_file <=> OLD.detail_file
            AND NEW.server_id <=> OLD.server_id AND NEW.closed_by_account_id <=> OLD.closed_by_account_id
            AND NEW.approved_by_account_id <=> OLD.approved_by_account_id AND NEW.closed_at <=> OLD.closed_at) THEN
          SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sealed event closings cannot be modified';
        END IF;
      END 
*/;;
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
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017*/ /*!50003 TRIGGER event_closings_no_delete BEFORE DELETE ON event_closings FOR EACH ROW
      BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sealed event closings cannot be deleted';
      END 
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `event_tellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_tellers` (
  `event_teller_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `teller_id` int(11) DEFAULT NULL,
  `teller_balance` decimal(16,2) DEFAULT NULL,
  `teller_match_balance` decimal(16,2) DEFAULT NULL,
  PRIMARY KEY (`event_teller_id`),
  KEY `NewIndex1` (`event_id`,`teller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_tellers_view`;
/*!50001 DROP VIEW IF EXISTS `event_tellers_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `event_tellers_view` AS SELECT
 NULL AS `event_teller_id`,
 NULL AS `event_id`,
 NULL AS `teller_id`,
 NULL AS `teller_balance`,
 NULL AS `teller_match_balance`,
 NULL AS `event_name`,
 NULL AS `event_description`,
 NULL AS `event_date`,
 NULL AS `event_status`,
 NULL AS `account_id`,
 NULL AS `teller_name`,
 NULL AS `contact_number`,
 NULL AS `phone_uid`,
 NULL AS `username`,
 NULL AS `password`,
 NULL AS `account_type`,
 NULL AS `is_active`,
 NULL AS `account_date_created` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(60) DEFAULT NULL,
  `event_description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_status` varchar(60) DEFAULT NULL,
  `admin_cash_on_hand` decimal(16,2) DEFAULT NULL,
  `teller_initial_cash_on_hand` decimal(16,2) DEFAULT NULL,
  `prizes` decimal(16,2) DEFAULT NULL,
  `rd` decimal(16,2) DEFAULT NULL,
  `event_percentage` decimal(5,3) DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `NewIndex1` (`event_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `match_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `match_number` int(11) DEFAULT NULL,
  `match_winner` varchar(5) DEFAULT NULL,
  `meron_total_bet` decimal(16,2) DEFAULT NULL,
  `wala_total_bet` decimal(16,2) DEFAULT NULL,
  `meron_odds` float DEFAULT NULL,
  `wala_odds` float DEFAULT NULL,
  `match_status` varchar(20) DEFAULT NULL,
  `match_bet_status` varchar(20) DEFAULT NULL,
  `meron_bet_status` tinyint(1) DEFAULT NULL,
  `wala_bet_status` tinyint(1) DEFAULT NULL,
  `match_created_datetime` datetime DEFAULT NULL,
  `is_display` tinyint(1) DEFAULT NULL,
  `meron_entry` varchar(60) DEFAULT NULL,
  `wala_entry` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`match_id`),
  KEY `NewIndex1` (`event_id`,`match_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) DEFAULT NULL,
  `tokenable_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `token` varchar(64) DEFAULT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salaries` (
  `salary_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `employee_type` varchar(20) DEFAULT NULL,
  `employee_name` varchar(60) DEFAULT NULL,
  `daily_rate` decimal(16,2) DEFAULT NULL,
  `overtime_rate` decimal(16,2) DEFAULT NULL,
  `total_overtime` decimal(16,2) DEFAULT NULL,
  `bonus` decimal(16,2) DEFAULT NULL,
  PRIMARY KEY (`salary_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teller_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teller_ledger` (
  `ledger_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `event_teller_id` int(11) NOT NULL,
  `line_no` int(10) unsigned NOT NULL,
  `entry_at` datetime NOT NULL,
  `type` varchar(20) NOT NULL,
  `fight_no` int(11) DEFAULT NULL,
  `match_id` int(11) DEFAULT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `amount_in` decimal(16,2) NOT NULL DEFAULT 0.00,
  `amount_out` decimal(16,2) NOT NULL DEFAULT 0.00,
  `balance_before` decimal(16,2) NOT NULL,
  `balance_after` decimal(16,2) NOT NULL,
  `related_event_teller_id` int(11) DEFAULT NULL,
  `recorded_by_account_id` int(11) DEFAULT NULL,
  `approved_by_account_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `source_table` varchar(30) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`ledger_id`),
  UNIQUE KEY `ledger_teller_line_unique` (`event_teller_id`,`line_no`),
  UNIQUE KEY `ledger_source_unique` (`source_table`,`source_id`,`type`),
  KEY `ledger_event_teller_idx` (`event_id`,`event_teller_id`),
  KEY `ledger_reference_idx` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017*/ /*!50003 TRIGGER teller_ledger_no_update BEFORE UPDATE ON teller_ledger FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger lines cannot be modified; add an adjustment line' 
*/;;
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
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017*/ /*!50003 TRIGGER teller_ledger_no_delete BEFORE DELETE ON teller_ledger FOR EACH ROW
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger lines cannot be deleted' 
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `teller_remittances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teller_remittances` (
  `teller_remittance_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_teller_id` int(11) DEFAULT NULL,
  `denom_1000` int(11) DEFAULT NULL,
  `denom_500` int(11) DEFAULT NULL,
  `denom_200` int(11) DEFAULT NULL,
  `denom_100` int(11) DEFAULT NULL,
  `denom_50` int(11) DEFAULT NULL,
  `denom_20` int(11) DEFAULT NULL,
  `denom_10` int(11) DEFAULT NULL,
  `denom_5` int(11) DEFAULT NULL,
  `denom_1` int(11) DEFAULT NULL,
  PRIMARY KEY (`teller_remittance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teller_remittances_view`;
/*!50001 DROP VIEW IF EXISTS `teller_remittances_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `teller_remittances_view` AS SELECT
 NULL AS `teller_remittance_id`,
 NULL AS `event_teller_id`,
 NULL AS `denom_1000`,
 NULL AS `denom_500`,
 NULL AS `denom_200`,
 NULL AS `denom_100`,
 NULL AS `denom_50`,
 NULL AS `denom_20`,
 NULL AS `denom_10`,
 NULL AS `denom_5`,
 NULL AS `denom_1`,
 NULL AS `event_id`,
 NULL AS `teller_balance`,
 NULL AS `teller_match_balance`,
 NULL AS `teller_name` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `teller_shorts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teller_shorts` (
  `teller_short_id` int(11) NOT NULL AUTO_INCREMENT,
  `teller_remittance_id` int(11) DEFAULT NULL,
  `short_amount` decimal(16,2) DEFAULT NULL,
  `short_remarks` text DEFAULT NULL,
  PRIMARY KEY (`teller_short_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tellers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tellers` (
  `teller_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `teller_name` varchar(200) DEFAULT NULL,
  `contact_number` varchar(60) DEFAULT NULL,
  `phone_uid` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`teller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tellers_view`;
/*!50001 DROP VIEW IF EXISTS `tellers_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `tellers_view` AS SELECT
 NULL AS `teller_id`,
 NULL AS `account_id`,
 NULL AS `teller_name`,
 NULL AS `contact_number`,
 NULL AS `phone_uid`,
 NULL AS `username`,
 NULL AS `password`,
 NULL AS `account_type`,
 NULL AS `is_active`,
 NULL AS `account_date_created` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `transaction_type` varchar(60) DEFAULT NULL,
  `transaction_message` text DEFAULT NULL,
  `transaction_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websockets_statistics_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `websockets_statistics_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) NOT NULL,
  `peak_connection_count` int(11) NOT NULL,
  `websocket_message_count` int(11) NOT NULL,
  `api_message_count` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_teller_name` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE FUNCTION `get_teller_name`(id INT) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
    DETERMINISTIC
BEGIN
          DECLARE tel_name VARCHAR(255);

          SELECT teller_name INTO tel_name
          FROM event_tellers_view
          WHERE event_teller_id = id
          LIMIT 1;
          RETURN tel_name;
      END
;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_transaction_account_name` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE FUNCTION `get_transaction_account_name`(acc_id INT, trans_type VARCHAR(60)) RETURNS varchar(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
    DETERMINISTIC
BEGIN
          DECLARE acc_name VARCHAR(255);
          IF trans_type IN ('Web App', 'Admin App') THEN
              SELECT admin_name INTO acc_name
              FROM admins_view
              WHERE account_id = acc_id
              LIMIT 1;
          ELSEIF trans_type = 'Teller App' THEN
              SELECT teller_name INTO acc_name
              FROM event_tellers_view
              WHERE account_id = acc_id
              LIMIT 1;
          ELSE
              SET acc_name = NULL;
          END IF;
          RETURN acc_name;
      END
;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `admin_remittances_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `admin_remittances_view` AS select `admin_remittances`.`admin_remittance_id` AS `admin_remittance_id`,`admin_remittances`.`event_id` AS `event_id`,`admin_remittances`.`denom_1000` AS `denom_1000`,`admin_remittances`.`denom_500` AS `denom_500`,`admin_remittances`.`denom_200` AS `denom_200`,`admin_remittances`.`denom_100` AS `denom_100`,`admin_remittances`.`denom_50` AS `denom_50`,`admin_remittances`.`denom_20` AS `denom_20`,`admin_remittances`.`denom_10` AS `denom_10`,`admin_remittances`.`denom_5` AS `denom_5`,`admin_remittances`.`denom_1` AS `denom_1`,`events`.`event_name` AS `event_name` from (`admin_remittances` join `events` on(`admin_remittances`.`event_id` = `events`.`event_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `admins_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `admins_view` AS select `admins`.`admin_id` AS `admin_id`,`admins`.`account_id` AS `account_id`,`admins`.`admin_name` AS `admin_name`,`accounts`.`username` AS `username`,`accounts`.`password` AS `password`,`accounts`.`account_type` AS `account_type`,`accounts`.`is_active` AS `is_active`,`accounts`.`account_date_created` AS `account_date_created` from (`admins` join `accounts` on(`admins`.`account_id` = `accounts`.`account_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `bets_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `bets_view` AS select `bets`.`bet_id` AS `bet_id`,`bets`.`event_teller_id` AS `event_teller_id`,`bets`.`match_id` AS `match_id`,`bets`.`bet_receipt_code` AS `bet_receipt_code`,`bets`.`bet_side` AS `bet_side`,`bets`.`bet_amount` AS `bet_amount`,`bets`.`bet_win_amount` AS `bet_win_amount`,`bets`.`bet_payout_amount` AS `bet_payout_amount`,`bets`.`bet_payout_datetime` AS `bet_payout_datetime`,`bets`.`bet_status` AS `bet_status`,`bets`.`bet_datetime` AS `bet_datetime`,`bets`.`bet_is_winner` AS `bet_is_winner`,`bets`.`bet_void_datetime` AS `bet_void_datetime`,`bets`.`bet_payout_by` AS `bet_payout_by`,`event_tellers`.`teller_id` AS `teller_id`,`event_tellers`.`teller_balance` AS `teller_balance`,`event_tellers`.`teller_match_balance` AS `teller_match_balance`,`tellers`.`teller_name` AS `teller_name`,`tellers`.`contact_number` AS `contact_number`,`tellers`.`phone_uid` AS `phone_uid`,`matches`.`match_number` AS `match_number`,`matches`.`match_winner` AS `match_winner`,`matches`.`meron_total_bet` AS `meron_total_bet`,`matches`.`wala_total_bet` AS `wala_total_bet`,`matches`.`meron_odds` AS `meron_odds`,`matches`.`wala_odds` AS `wala_odds`,`matches`.`match_status` AS `match_status`,`matches`.`match_bet_status` AS `match_bet_status`,`matches`.`meron_bet_status` AS `meron_bet_status`,`matches`.`wala_bet_status` AS `wala_bet_status`,`matches`.`match_created_datetime` AS `match_created_datetime`,`events`.`event_id` AS `event_id`,`events`.`event_name` AS `event_name`,`events`.`event_description` AS `event_description`,`events`.`event_date` AS `event_date`,`events`.`event_status` AS `event_status` from ((((`bets` join `event_tellers` on(`bets`.`event_teller_id` = `event_tellers`.`event_teller_id`)) join `matches` on(`bets`.`match_id` = `matches`.`match_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) join `events` on(`matches`.`event_id` = `events`.`event_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `cash_ins_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `cash_ins_view` AS select `cash_ins`.`cash_in_id` AS `cash_in_id`,`cash_ins`.`admin_id` AS `admin_id`,`cash_ins`.`event_teller_id` AS `event_teller_id`,`cash_ins`.`cash_in_amount` AS `cash_in_amount`,`cash_ins`.`cash_in_datetime` AS `cash_in_datetime`,`cash_ins`.`cash_in_type` AS `cash_in_type`,`cash_ins`.`cash_in_status` AS `cash_in_status`,`admins`.`admin_name` AS `admin_name`,`event_tellers`.`teller_id` AS `teller_id`,`event_tellers`.`teller_balance` AS `teller_balance`,`tellers`.`teller_name` AS `teller_name`,`tellers`.`contact_number` AS `contact_number`,`tellers`.`phone_uid` AS `phone_uid`,`events`.`event_name` AS `event_name`,`events`.`event_description` AS `event_description`,`events`.`event_date` AS `event_date`,`events`.`event_status` AS `event_status`,`events`.`admin_cash_on_hand` AS `admin_cash_on_hand`,`event_tellers`.`event_id` AS `event_id` from ((((`cash_ins` left join `admins` on(`cash_ins`.`admin_id` = `admins`.`admin_id`)) join `event_tellers` on(`cash_ins`.`event_teller_id` = `event_tellers`.`event_teller_id`)) join `events` on(`event_tellers`.`event_id` = `events`.`event_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `cash_outs_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `cash_outs_view` AS select `cash_outs`.`cash_out_id` AS `cash_out_id`,`cash_outs`.`event_teller_id` AS `event_teller_id`,`cash_outs`.`admin_id` AS `admin_id`,`cash_outs`.`cash_out_amount` AS `cash_out_amount`,`cash_outs`.`cash_out_status` AS `cash_out_status`,`cash_outs`.`cash_out_datetime` AS `cash_out_datetime`,`event_tellers`.`event_id` AS `event_id`,`event_tellers`.`teller_id` AS `teller_id`,`event_tellers`.`teller_balance` AS `teller_balance`,`event_tellers`.`teller_match_balance` AS `teller_match_balance`,`events`.`event_name` AS `event_name`,`events`.`event_description` AS `event_description`,`events`.`event_date` AS `event_date`,`events`.`event_status` AS `event_status`,`events`.`admin_cash_on_hand` AS `admin_cash_on_hand`,`tellers`.`teller_name` AS `teller_name`,`tellers`.`contact_number` AS `contact_number`,`tellers`.`phone_uid` AS `phone_uid`,`admins`.`admin_name` AS `admin_name` from ((((`cash_outs` join `event_tellers` on(`cash_outs`.`event_teller_id` = `event_tellers`.`event_teller_id`)) left join `admins` on(`cash_outs`.`admin_id` = `admins`.`admin_id`)) join `events` on(`event_tellers`.`event_id` = `events`.`event_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `claims_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `claims_view` AS select `claims`.`claim_id` AS `claim_id`,`claims`.`bet_id` AS `bet_id`,`claims`.`event_teller_id` AS `event_teller_id`,`claims`.`claim_amount` AS `claim_amount`,`claims`.`claim_datetime` AS `claim_datetime`,`tellers`.`teller_name` AS `teller_name`,`matches`.`match_number` AS `match_number`,`matches`.`match_winner` AS `match_winner`,`matches`.`meron_odds` AS `meron_odds`,`matches`.`wala_odds` AS `wala_odds`,`bets`.`bet_receipt_code` AS `bet_receipt_code`,`bets`.`bet_side` AS `bet_side` from ((((`claims` join `event_tellers` on(`claims`.`event_teller_id` = `event_tellers`.`event_teller_id`)) join `bets` on(`claims`.`bet_id` = `bets`.`bet_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) join `matches` on(`bets`.`match_id` = `matches`.`match_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `event_tellers_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `event_tellers_view` AS select `event_tellers`.`event_teller_id` AS `event_teller_id`,`event_tellers`.`event_id` AS `event_id`,`event_tellers`.`teller_id` AS `teller_id`,`event_tellers`.`teller_balance` AS `teller_balance`,`event_tellers`.`teller_match_balance` AS `teller_match_balance`,`events`.`event_name` AS `event_name`,`events`.`event_description` AS `event_description`,`events`.`event_date` AS `event_date`,`events`.`event_status` AS `event_status`,`tellers`.`account_id` AS `account_id`,`tellers`.`teller_name` AS `teller_name`,`tellers`.`contact_number` AS `contact_number`,`tellers`.`phone_uid` AS `phone_uid`,`accounts`.`username` AS `username`,`accounts`.`password` AS `password`,`accounts`.`account_type` AS `account_type`,`accounts`.`is_active` AS `is_active`,`accounts`.`account_date_created` AS `account_date_created` from (((`event_tellers` join `events` on(`event_tellers`.`event_id` = `events`.`event_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) join `accounts` on(`tellers`.`account_id` = `accounts`.`account_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `teller_remittances_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `teller_remittances_view` AS select `teller_remittances`.`teller_remittance_id` AS `teller_remittance_id`,`teller_remittances`.`event_teller_id` AS `event_teller_id`,`teller_remittances`.`denom_1000` AS `denom_1000`,`teller_remittances`.`denom_500` AS `denom_500`,`teller_remittances`.`denom_200` AS `denom_200`,`teller_remittances`.`denom_100` AS `denom_100`,`teller_remittances`.`denom_50` AS `denom_50`,`teller_remittances`.`denom_20` AS `denom_20`,`teller_remittances`.`denom_10` AS `denom_10`,`teller_remittances`.`denom_5` AS `denom_5`,`teller_remittances`.`denom_1` AS `denom_1`,`event_tellers`.`event_id` AS `event_id`,`event_tellers`.`teller_balance` AS `teller_balance`,`event_tellers`.`teller_match_balance` AS `teller_match_balance`,`tellers`.`teller_name` AS `teller_name` from ((`teller_remittances` join `event_tellers` on(`teller_remittances`.`event_teller_id` = `event_tellers`.`event_teller_id`)) join `tellers` on(`event_tellers`.`teller_id` = `tellers`.`teller_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `tellers_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `tellers_view` AS select `tellers`.`teller_id` AS `teller_id`,`tellers`.`account_id` AS `account_id`,`tellers`.`teller_name` AS `teller_name`,`tellers`.`contact_number` AS `contact_number`,`tellers`.`phone_uid` AS `phone_uid`,`accounts`.`username` AS `username`,`accounts`.`password` AS `password`,`accounts`.`account_type` AS `account_type`,`accounts`.`is_active` AS `is_active`,`accounts`.`account_date_created` AS `account_date_created` from (`tellers` join `accounts` on(`tellers`.`account_id` = `accounts`.`account_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Record the migrations already contained in this schema.
/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0000_00_00_000000_create_websockets_statistics_entries_table',1),
(2,'2019_12_14_000001_create_personal_access_tokens_table',1),
(3,'2025_07_17_230006_create_failed_jobs_table',2),
(4,'2026_09_28_000001_create_event_closings_table',2),
(5,'2026_09_28_000002_recreate_stored_functions_as_app_user',2),
(6,'2026_09_29_000001_harden_tokens_and_claims',3),
(7,'2026_09_29_000002_create_teller_ledger',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


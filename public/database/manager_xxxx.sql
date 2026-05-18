-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.45 - MySQL Community Server - GPL
-- Server OS:                    Linux
-- HeidiSQL Version:             12.17.1.1
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table manager_0002.banks
CREATE TABLE IF NOT EXISTS `banks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `number` varchar(4) NOT NULL DEFAULT '0',
  `active` tinyint NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.banks: ~0 rows (approximately)

-- Dumping structure for table manager_0002.bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `agency` varchar(6) NOT NULL,
  `account` varchar(50) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bank_accounts_banks_idx` (`bank_id`),
  CONSTRAINT `fk_bank_accounts_banks` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.bank_accounts: ~0 rows (approximately)

-- Dumping structure for table manager_0002.bank_files
CREATE TABLE IF NOT EXISTS `bank_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bank_account_id` int DEFAULT NULL,
  `file_type` enum('REMITTANCE','RETURN_FILE') NOT NULL,
  `file_name` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_hash` char(64) DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_files_path` (`file_path`),
  KEY `idx_bank_files_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_files_file_type` (`file_type`),
  CONSTRAINT `fk_bank_files_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.bank_files: ~0 rows (approximately)

-- Dumping structure for table manager_0002.bank_slips
CREATE TABLE IF NOT EXISTS `bank_slips` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_payment_id` int DEFAULT NULL,
  `bank_account_id` int NOT NULL,
  `bank_file_id` int DEFAULT NULL,
  `status` enum('DRAFT','READY','REMITTANCE_GENERATED','SENT','REGISTERED','REJECTED','PAID','CANCELED','WRITTEN_OFF') NOT NULL DEFAULT 'DRAFT',
  `document_number` varchar(20) NOT NULL,
  `our_number` varchar(20) DEFAULT NULL,
  `our_number_digit` varchar(2) DEFAULT NULL,
  `portfolio` varchar(10) NOT NULL,
  `payer_document_type` enum('CPF','CNPJ') NOT NULL,
  `payer_document` varchar(14) NOT NULL,
  `payer_name` varchar(100) NOT NULL,
  `payer_street` varchar(120) NOT NULL,
  `payer_neighborhood` varchar(60) DEFAULT NULL,
  `payer_city` varchar(60) NOT NULL,
  `payer_state` char(2) NOT NULL,
  `payer_zip_code` varchar(8) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date NOT NULL,
  `issue_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT NULL,
  `credited_amount` decimal(12,2) DEFAULT NULL,
  `credit_date` date DEFAULT NULL,
  `interest_date` date DEFAULT NULL,
  `interest_amount_per_day` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_date` date DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `fine_date` date DEFAULT NULL,
  `fine_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `protest_code` varchar(2) DEFAULT NULL,
  `protest_days` tinyint unsigned DEFAULT NULL,
  `write_off_code` varchar(2) DEFAULT NULL,
  `write_off_days` tinyint unsigned DEFAULT NULL,
  `last_occurrence_code` varchar(2) DEFAULT NULL,
  `last_occurrence_message` varchar(255) DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_slips_financial_operation_payment` (`financial_operation_payment_id`),
  UNIQUE KEY `uq_bank_slips_account_our_number` (`bank_account_id`,`our_number`),
  KEY `idx_bank_slips_bank_account_id` (`bank_account_id`),
  KEY `idx_bank_slips_bank_file_id` (`bank_file_id`),
  KEY `idx_bank_slips_status` (`status`),
  KEY `idx_bank_slips_due_date` (`due_date`),
  KEY `idx_bank_slips_document_number` (`document_number`),
  CONSTRAINT `fk_bank_slips_bank_accounts` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_bank_files` FOREIGN KEY (`bank_file_id`) REFERENCES `bank_files` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bank_slips_financial_operations_payments` FOREIGN KEY (`financial_operation_payment_id`) REFERENCES `financial_operations_payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.bank_slips: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations
CREATE TABLE IF NOT EXISTS `financial_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.financial_operations: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_categories
CREATE TABLE IF NOT EXISTS `financial_operations_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.financial_operations_categories: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_payments
CREATE TABLE IF NOT EXISTS `financial_operations_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_id` int NOT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_financial_operations_payments_operations_idx` (`financial_operation_id`),
  CONSTRAINT `fk_financial_operations_payments_operations` FOREIGN KEY (`financial_operation_id`) REFERENCES `financial_operations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.financial_operations_payments: ~0 rows (approximately)

-- Dumping structure for table manager_0002.financial_operations_subcategories
CREATE TABLE IF NOT EXISTS `financial_operations_subcategories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `financial_operation_category_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_operations_subcategories_name_unique` (`name`),
  KEY `fk_financial_operations_subcategories_categories_idx` (`financial_operation_category_id`),
  CONSTRAINT `fk_financial_operations_subcategories_categories` FOREIGN KEY (`financial_operation_category_id`) REFERENCES `financial_operations_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.financial_operations_subcategories: ~0 rows (approximately)

-- Dumping structure for table manager_0002.schema_versions
CREATE TABLE IF NOT EXISTS `schema_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Version number (e.g.: 1.0.0)',
  `description` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schema_versions_version_unique` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table manager_0002.schema_versions: ~0 rows (approximately)
INSERT INTO `schema_versions` (`id`, `version`, `description`, `created`, `modified`) VALUES
	(1, '1.0.0', 'Initial tenant schema', '2026-05-14 23:55:58', '2026-05-14 23:55:58');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

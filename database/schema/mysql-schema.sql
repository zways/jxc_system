/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounts_payable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts_payable` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL COMMENT '供应商ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '单据类型(purchase,return)',
  `document_id` bigint unsigned NOT NULL COMMENT '单据ID',
  `document_date` date NOT NULL COMMENT '单据日期',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已付金额',
  `balance` decimal(10,2) NOT NULL COMMENT '余额',
  `due_date` date NOT NULL COMMENT '到期日期',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid' COMMENT '状态(unpaid,paid,overdue)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_payable_supplier_id_foreign` (`supplier_id`),
  KEY `accounts_payable_store_id_foreign` (`store_id`),
  CONSTRAINT `accounts_payable_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `accounts_payable_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts_receivable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts_receivable` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL COMMENT '客户ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '单据类型(sale,return)',
  `document_id` bigint unsigned NOT NULL COMMENT '单据ID',
  `document_date` date NOT NULL COMMENT '单据日期',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已付金额',
  `balance` decimal(10,2) NOT NULL COMMENT '余额',
  `due_date` date NOT NULL COMMENT '到期日期',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid' COMMENT '状态(unpaid,paid,overdue)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_receivable_customer_id_foreign` (`customer_id`),
  KEY `accounts_receivable_store_id_foreign` (`store_id`),
  CONSTRAINT `accounts_receivable_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `accounts_receivable_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web' COMMENT 'token 名称/设备',
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha256(token)',
  `abilities` json DEFAULT NULL COMMENT '能力/权限范围，可为空表示全部',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_tokens_token_hash_unique` (`token_hash`),
  KEY `api_tokens_user_id_last_used_at_index` (`user_id`,`last_used_at`),
  CONSTRAINT `api_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人姓名快照',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型: create, update, delete, restore, void, pay, collect, process, login, logout',
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作对象模型类',
  `model_id` bigint unsigned DEFAULT NULL COMMENT '操作对象ID',
  `model_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作对象可读标签(如订单号)',
  `store_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL COMMENT '变更前的值',
  `new_values` json DEFAULT NULL COMMENT '变更后的值',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '操作描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_store_id_created_at_index` (`store_id`,`created_at`),
  KEY `audit_logs_action_index` (`action`),
  CONSTRAINT `audit_logs_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_agents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `agent_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务员编号',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务员姓名',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '佣金比例',
  `territory` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '负责区域',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态(active,inactive)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_agents_store_code_unique` (`store_id`,`agent_code`),
  CONSTRAINT `business_agents_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `customer_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户编码',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '客户名称',
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地址',
  `tax_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税号',
  `credit_limit` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '信用额度',
  `outstanding_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '未结金额',
  `customer_level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '普通客户' COMMENT '客户等级',
  `payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '付款条件',
  `rating` tinyint unsigned NOT NULL DEFAULT '3' COMMENT '评级(1-5)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_store_code_unique` (`store_id`,`customer_code`),
  CONSTRAINT `customers_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '部门名称',
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '部门编码',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_store_id_code_unique` (`store_id`,`code`),
  CONSTRAINT `departments_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `employee_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工编号',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '职位',
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '部门',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `hire_date` date DEFAULT NULL COMMENT '入职日期',
  `termination_date` date DEFAULT NULL COMMENT '离职日期',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态(active,inactive)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_store_id_code_unique` (`store_id`,`employee_code`),
  CONSTRAINT `employees_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exchange_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '换货单号',
  `sale_id` bigint unsigned NOT NULL COMMENT '原销售单ID',
  `customer_id` bigint unsigned NOT NULL COMMENT '客户ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `exchange_date` date NOT NULL COMMENT '换货日期',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态(pending,completed)',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '换货原因',
  `exchanged_by` bigint unsigned NOT NULL COMMENT '换货人ID',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exchange_records_exchange_number_unique` (`exchange_number`),
  KEY `exchange_records_sale_id_foreign` (`sale_id`),
  KEY `exchange_records_customer_id_foreign` (`customer_id`),
  KEY `exchange_records_exchanged_by_foreign` (`exchanged_by`),
  KEY `exchange_records_store_id_foreign` (`store_id`),
  CONSTRAINT `exchange_records_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `exchange_records_exchanged_by_foreign` FOREIGN KEY (`exchanged_by`) REFERENCES `users` (`id`),
  CONSTRAINT `exchange_records_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales_orders` (`id`),
  CONSTRAINT `exchange_records_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易编号',
  `transaction_date` date NOT NULL COMMENT '交易日期',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类型(revenue,expense,payment,receipt)',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类别',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY' COMMENT '货币类型',
  `related_model_id` bigint unsigned DEFAULT NULL COMMENT '关联模型ID',
  `related_model_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '关联模型类型',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted' COMMENT '状态(draft,posted,voided)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '描述',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_transactions_transaction_number_unique` (`transaction_number`),
  KEY `financial_transactions_created_by_foreign` (`created_by`),
  KEY `financial_transactions_store_id_foreign` (`store_id`),
  CONSTRAINT `financial_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `financial_transactions_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '调整单号',
  `product_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `adjustment_date` date NOT NULL COMMENT '调整日期',
  `adjustment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '调整类型(increase,decrease)',
  `adjustment_reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '调整原因',
  `adjusted_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态(pending,approved,rejected)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_adjustments_adjustment_number_unique` (`adjustment_number`),
  KEY `inventory_adjustments_product_id_foreign` (`product_id`),
  KEY `inventory_adjustments_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_adjustments_adjusted_by_foreign` (`adjusted_by`),
  KEY `inventory_adjustments_store_id_foreign` (`store_id`),
  CONSTRAINT `inventory_adjustments_adjusted_by_foreign` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_adjustments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `inventory_adjustments_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustments_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_count_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_count_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_count_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `book_quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账面数量',
  `counted_quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实盘数量',
  `variance_quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '差异数量(实盘-账面)',
  `unit_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单位成本(用于金额差异)',
  `variance_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '差异金额(差异数量*单位成本)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inv_count_items_unique` (`inventory_count_id`,`product_id`),
  KEY `inventory_count_items_product_id_foreign` (`product_id`),
  CONSTRAINT `inventory_count_items_inventory_count_id_foreign` FOREIGN KEY (`inventory_count_id`) REFERENCES `inventory_counts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_count_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_counts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `count_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '盘点单号',
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cycle' COMMENT '盘点类型(cycle,frozen)',
  `count_date` date NOT NULL COMMENT '盘点日期',
  `counted_by` bigint unsigned NOT NULL COMMENT '盘点人ID',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress' COMMENT '状态(in_progress,completed,verified)',
  `variance_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '差异金额',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_counts_count_number_unique` (`count_number`),
  KEY `inventory_counts_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_counts_counted_by_foreign` (`counted_by`),
  KEY `inventory_counts_store_id_foreign` (`store_id`),
  CONSTRAINT `inventory_counts_counted_by_foreign` FOREIGN KEY (`counted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_counts_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_counts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '流水号',
  `product_id` bigint unsigned NOT NULL COMMENT '商品ID',
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类型(in,out,adjust,transfer)',
  `quantity` decimal(10,2) NOT NULL COMMENT '数量',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '单位',
  `unit_cost` decimal(10,2) DEFAULT NULL COMMENT '单位成本',
  `total_cost` decimal(10,2) DEFAULT NULL COMMENT '总成本',
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '关联单据类型',
  `reference_id` bigint unsigned DEFAULT NULL COMMENT '关联单据ID',
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '批次号',
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '序列号（序列号管理商品必填）',
  `production_date` date DEFAULT NULL COMMENT '生产日期',
  `expiry_date` date DEFAULT NULL COMMENT '有效期至',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作原因',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_transactions_transaction_number_unique` (`transaction_number`),
  KEY `inventory_transactions_product_id_foreign` (`product_id`),
  KEY `inventory_transactions_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_transactions_created_by_foreign` (`created_by`),
  KEY `inventory_transactions_store_id_foreign` (`store_id`),
  CONSTRAINT `inventory_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `store_id` bigint unsigned DEFAULT NULL COMMENT '门店ID',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标题',
  `body` text COLLATE utf8mb4_unicode_ci COMMENT '内容',
  `level` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info' COMMENT '级别(info,warning,error)',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `read_at` timestamp NULL DEFAULT NULL COMMENT '读取时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_index` (`user_id`),
  KEY `notifications_store_id_index` (`store_id`),
  KEY `notifications_is_read_created_at_index` (`is_read`,`created_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限标识，如 suppliers.read',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限名称，如 供应商-查看',
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '权限分组，如 purchase/sales/system',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名称',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分类描述',
  `parent_id` bigint unsigned DEFAULT NULL COMMENT '父分类ID',
  `level` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '分类层级',
  `sort_order` int unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_categories_parent_id_foreign` (`parent_id`),
  KEY `product_categories_store_id_foreign` (`store_id`),
  CONSTRAINT `product_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_categories_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品编码',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '商品描述',
  `category_id` bigint unsigned NOT NULL COMMENT '商品分类ID',
  `barcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '条形码',
  `specification` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格型号',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基本单位',
  `second_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '辅助单位',
  `conversion_rate` decimal(10,2) NOT NULL DEFAULT '1.00' COMMENT '单位转换率',
  `purchase_price` decimal(10,2) DEFAULT NULL COMMENT '采购价格',
  `retail_price` decimal(10,2) DEFAULT NULL COMMENT '零售价格',
  `wholesale_price` decimal(10,2) DEFAULT NULL COMMENT '批发价格',
  `min_stock` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '最低库存量',
  `max_stock` decimal(10,2) NOT NULL DEFAULT '999999.00' COMMENT '最高库存量',
  `track_serial` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否跟踪序列号',
  `track_batch` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否跟踪批次',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_store_code_unique` (`store_id`,`code`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品名称快照',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位快照',
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '数量',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `line_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '行金额(数量*单价)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `purchase_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单编号',
  `supplier_id` bigint unsigned NOT NULL COMMENT '供应商ID',
  `order_date` date NOT NULL COMMENT '订单日期',
  `expected_delivery_date` date DEFAULT NULL COMMENT '预计交货日期',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '小计金额',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '折扣',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '税额',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '运费',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态(pending,confirmed,received,cancelled)',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid' COMMENT '付款状态(unpaid,paid,partial)',
  `delivery_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '交货状态',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_order_number_unique` (`order_number`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_created_by_foreign` (`created_by`),
  KEY `purchase_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_orders_store_id_foreign` (`store_id`),
  CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色名称',
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色编码',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_store_id_code_unique` (`store_id`,`code`),
  CONSTRAINT `roles_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品名称快照',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位快照',
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '数量',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `line_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '行金额(数量*单价)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_items_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `sales_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_order_items_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单编号',
  `customer_id` bigint unsigned NOT NULL COMMENT '客户ID',
  `order_date` date NOT NULL COMMENT '订单日期',
  `delivery_date` date DEFAULT NULL COMMENT '交货日期',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '小计金额',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '折扣',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '税额',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '运费',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `order_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail' COMMENT '订单类型(retail,wholesale,pos)',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态(pending,confirmed,delivered,cancelled)',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid' COMMENT '付款状态(unpaid,paid,partial)',
  `delivery_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '交货状态',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_order_number_unique` (`order_number`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`),
  KEY `sales_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `sales_orders_store_id_foreign` (`store_id`),
  CONSTRAINT `sales_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `return_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '退货单号',
  `sale_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL COMMENT '客户ID',
  `return_date` date NOT NULL COMMENT '退货日期',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '小计金额',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '税额',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态(pending,approved,rejected)',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '退货原因',
  `returned_by` bigint unsigned NOT NULL COMMENT '退货人ID',
  `warehouse_id` bigint unsigned NOT NULL COMMENT '仓库ID',
  `store_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_returns_return_number_unique` (`return_number`),
  KEY `sales_returns_sale_id_foreign` (`sale_id`),
  KEY `sales_returns_customer_id_foreign` (`customer_id`),
  KEY `sales_returns_returned_by_foreign` (`returned_by`),
  KEY `sales_returns_warehouse_id_foreign` (`warehouse_id`),
  KEY `sales_returns_store_id_foreign` (`store_id`),
  CONSTRAINT `sales_returns_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_returned_by_foreign` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_returns_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '门店编码',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '门店名称',
  `manager` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '门店经理',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '企业联系邮箱',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '门店地址',
  `business_license` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '营业执照号',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retail' COMMENT '门店类型(retail,wholesale,online)',
  `industry` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '所属行业',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `plan` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free' COMMENT '套餐(free,basic,pro,enterprise)',
  `max_users` int NOT NULL DEFAULT '5' COMMENT '最大用户数',
  `expires_at` date DEFAULT NULL COMMENT '订阅到期时间',
  `is_tenant` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否为租户(顶级企业)',
  `parent_store_id` bigint unsigned DEFAULT NULL COMMENT '上级门店ID',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stores_store_code_unique` (`store_code`),
  KEY `stores_parent_store_id_foreign` (`parent_store_id`),
  CONSTRAINT `stores_parent_store_id_foreign` FOREIGN KEY (`parent_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL COMMENT '租户(Store)ID',
  `out_trade_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商户订单号',
  `plan` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套餐',
  `period` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1_month' COMMENT '周期: 1_month, 3_months, 1_year',
  `amount` decimal(10,2) NOT NULL COMMENT '金额(元)',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '支付渠道: alipay, wechat',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, paid, failed, closed',
  `paid_at` timestamp NULL DEFAULT NULL,
  `channel_trade_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '支付宝/微信交易号',
  `raw_notify` json DEFAULT NULL COMMENT '回调原始数据(脱敏后)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_orders_out_trade_no_unique` (`out_trade_no`),
  KEY `subscription_orders_store_id_status_index` (`store_id`,`status`),
  KEY `subscription_orders_channel_index` (`channel`),
  CONSTRAINT `subscription_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `supplier_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '供应商编码',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '供应商名称',
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地址',
  `tax_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税号',
  `credit_limit` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '信用额度',
  `outstanding_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '未结金额',
  `payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '付款条件',
  `rating` tinyint unsigned NOT NULL DEFAULT '3' COMMENT '评级(1-5)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_store_code_unique` (`store_id`,`supplier_code`),
  CONSTRAINT `suppliers_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '单位名称',
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位符号',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_store_id_name_unique` (`store_id`,`name`),
  CONSTRAINT `units_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户名',
  `real_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '真实姓名',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '电话',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enabled' COMMENT '状态(enabled,disabled)',
  `employee_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工号',
  `role_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_department_id_foreign` (`department_id`),
  KEY `users_store_id_foreign` (`store_id`),
  KEY `users_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '仓库编码',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '仓库名称',
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '仓库位置',
  `manager` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '仓库管理员',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '仓库描述',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '仓库类型(normal,frozen,liquid)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否激活',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_store_id_code_unique` (`store_id`,`code`),
  CONSTRAINT `warehouses_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_02_02_022640_create_product_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_02_02_022647_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_02_02_022650_create_suppliers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_02_02_022653_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_02_02_022656_create_purchase_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_02_02_022700_create_sales_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_02_02_022702_create_inventory_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_02_02_022706_create_employees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_02_02_022709_create_business_agents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_02_02_022713_create_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_02_02_023015_create_inventory_adjustments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_02_02_023018_create_inventory_counts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_02_02_023021_create_sales_returns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_02_02_023026_create_exchange_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_02_02_023028_create_accounts_receivable_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_02_02_023031_create_accounts_payable_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_02_02_023034_create_financial_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_02_02_023037_create_stores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_02_03_045218_add_fields_to_inventory_adjustments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_02_03_051611_add_foreign_keys_to_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_02_07_000001_create_roles_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_02_07_000002_create_departments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_02_07_000003_create_units_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_02_07_000004_add_management_fields_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_02_07_000005_create_purchase_order_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_02_07_000006_create_sales_order_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_02_07_000007_create_inventory_count_items_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_02_07_000008_create_permissions_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_02_07_000009_create_permission_role_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_02_07_000010_create_api_tokens_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_02_10_000002_create_audit_logs_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_02_09_000001_add_store_and_warehouse_to_users_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_02_09_000002_add_store_id_to_business_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_02_09_000003_add_store_id_to_master_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_02_09_000004_add_store_id_to_product_categories_and_units',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_02_09_000005_add_store_id_to_org_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_02_09_000006_add_store_id_to_roles_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_02_10_000001_add_soft_deletes_to_remaining_tables',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_02_10_100001_add_tenant_fields_to_stores_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_02_11_025454_fix_unique_constraints_to_tenant_scoped',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_02_13_100000_create_subscription_orders_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_02_23_100000_add_serial_number_to_inventory_transactions_table',11);

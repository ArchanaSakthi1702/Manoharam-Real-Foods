-- Migration Script for Manoharam Real Foods (Phase 1 & Phase 2)
-- Run this in phpMyAdmin or MySQL CLI

USE `manoharam_db`;

-- 1. Users table additions for password reset
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(255) NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `reset_token_expires_at` DATETIME NULL AFTER `reset_token`;

-- 2. Orders table additions for delivery address and payment tracking
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `delivery_address` TEXT NULL AFTER `customer_id`,
  ADD COLUMN IF NOT EXISTS `payment_method` ENUM('COD', 'ONLINE') NOT NULL DEFAULT 'COD' AFTER `total_amount`,
  ADD COLUMN IF NOT EXISTS `payment_status` ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING' AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `razorpay_order_id` VARCHAR(100) NULL AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `razorpay_payment_id` VARCHAR(100) NULL AFTER `razorpay_order_id`,
  ADD COLUMN IF NOT EXISTS `razorpay_signature` VARCHAR(255) NULL AFTER `razorpay_payment_id`,
  ADD COLUMN IF NOT EXISTS `cancellation_reason` TEXT NULL AFTER `razorpay_signature`,
  ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME NULL AFTER `cancellation_reason`;

-- 3. Order Batch Deductions table (to guarantee accurate inventory restitution on order cancellation)
CREATE TABLE IF NOT EXISTS `order_batch_deductions` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT(20) NOT NULL,
  `product_id` BIGINT(20) NOT NULL,
  `batch_id` BIGINT(20) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deduction_order` (`order_id`),
  KEY `idx_deduction_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Subscriptions table additions for lifecycle status and payment
ALTER TABLE `subscriptions`
  MODIFY COLUMN `status` ENUM('PENDING','APPROVED','ACTIVE','PAUSED','COMPLETED','CANCELLED','REJECTED') NOT NULL DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS `payment_status` ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING' AFTER `total_amount`,
  ADD COLUMN IF NOT EXISTS `razorpay_order_id` VARCHAR(100) NULL AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `razorpay_payment_id` VARCHAR(100) NULL AFTER `razorpay_order_id`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `razorpay_payment_id`,
  ADD COLUMN IF NOT EXISTS `cancellation_reason` TEXT NULL AFTER `rejection_reason`;

-- 5. Payments audit table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT(20) NOT NULL,
  `order_id` BIGINT(20) NULL,
  `subscription_id` BIGINT(20) NULL,
  `razorpay_order_id` VARCHAR(100) NOT NULL,
  `razorpay_payment_id` VARCHAR(100) NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('CREATED', 'SUCCESS', 'FAILED') NOT NULL DEFAULT 'CREATED',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_customer` (`customer_id`),
  KEY `idx_payment_order` (`order_id`),
  KEY `idx_payment_subscription` (`subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 09:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `manoharam_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `customer_id`, `created_at`) VALUES
(1, 1, '2026-07-28 12:30:38');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) NOT NULL,
  `cart_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` enum('g','kg','ml','l','piece') NOT NULL DEFAULT 'g',
  `calories_per_100g` decimal(10,2) DEFAULT 0.00,
  `protein_per_100g` decimal(10,2) DEFAULT 0.00,
  `carbs_per_100g` decimal(10,2) DEFAULT 0.00,
  `fat_per_100g` decimal(10,2) DEFAULT 0.00,
  `fiber_per_100g` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `name`, `unit`, `calories_per_100g`, `protein_per_100g`, `carbs_per_100g`, `fat_per_100g`, `fiber_per_100g`) VALUES
(1, 'Garlic', 'g', 149.00, 6.40, 33.10, 0.50, 2.10),
(2, 'Gingelly Oil', 'g', 884.00, 0.00, 0.00, 100.00, 0.00),
(3, 'Red Chilli Powder', 'g', 282.00, 12.00, 50.00, 14.00, 35.00),
(4, 'Mustard Seeds', 'g', 508.00, 26.00, 28.00, 36.00, 12.00),
(5, 'Fenugreek Powder', 'g', 323.00, 23.00, 58.00, 6.00, 25.00),
(6, 'Salt', 'g', 0.00, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `batch_no` varchar(100) NOT NULL,
  `manufactured_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `product_id`, `batch_no`, `manufactured_date`, `expiry_date`, `quantity`, `remaining_quantity`, `created_at`) VALUES
(1, 1, 'BATCH-2026-001', '2026-09-01', '2027-09-01', 100.00, 100.00, '2026-09-22 13:40:19'),
(2, 1, 'BATCH-2026-002', '2026-09-01', '2027-09-01', 110.00, 110.00, '2026-09-22 13:41:45');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('PENDING','CONFIRMED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_date`, `status`, `total_amount`) VALUES
(2, 1, '2026-07-28 18:12:16', 'PENDING', 460.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(3, 2, 4, 1.00, 240.00),
(4, 2, 1, 1.00, 220.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('g','kg') NOT NULL DEFAULT 'g',
  `selling_price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `quantity`, `unit`, `selling_price`, `description`, `image_url`, `status`) VALUES
(1, 'Garlic Pickle', 'Pickles', 500.00, 'g', 220.00, 'Traditional homemade garlic pickle prepared with fresh garlic, gingelly oil and aromatic spices.', 'https://example.com/images/garlic-pickle.jpg', 'ACTIVE'),
(2, 'Mango Pickle', 'Pickles', 500.00, 'g', 200.00, 'Authentic homemade mango pickle made with raw mangoes, gingelly oil, and traditional spices.', 'https://example.com/images/mango-pickle.jpg', 'ACTIVE'),
(3, 'Lemon Pickle', 'Pickles', 500.00, 'g', 180.00, 'Tangy and flavorful lemon pickle prepared using fresh lemons and aromatic spice blends.', 'https://example.com/images/lemon-pickle.jpg', 'ACTIVE'),
(4, 'Mixed Vegetable Pickle', 'Pickles', 500.00, 'g', 240.00, 'A spicy blend of carrots, mangoes, lime and other vegetables preserved with traditional spices.', 'https://example.com/images/mixed-vegetable-pickle.jpg', 'ACTIVE'),
(5, 'Tomato Thokku', 'Pickles', 500.00, 'g', 190.00, 'Traditional South Indian tomato thokku made with ripe tomatoes and aromatic spices.', 'https://example.com/images/tomato-thokku.jpg', 'ACTIVE'),
(6, 'Ginger Pickle', 'Pickles', 500.00, 'g', 210.00, 'Homemade ginger pickle with a tangy and spicy flavor profile.', 'https://example.com/images/ginger-pickle.jpg', 'ACTIVE'),
(7, 'Amla Pickle', 'Pickles', 500.00, 'g', 230.00, 'Nutritious Indian gooseberry pickle prepared with traditional spices and gingelly oil.', 'https://example.com/images/amla-pickle.jpg', 'ACTIVE');

-- --------------------------------------------------------

--
-- Table structure for table `recipes`
--

CREATE TABLE `recipes` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_weight_g` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipes`
--

INSERT INTO `recipes` (`id`, `product_id`, `name`, `total_weight_g`, `created_at`) VALUES
(2, 1, 'Garlic Pickle Recipe', 1000.00, '2026-07-28 12:38:53');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` bigint(20) NOT NULL,
  `recipe_id` bigint(20) NOT NULL,
  `ingredient_id` bigint(20) NOT NULL,
  `quantity_g` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_ingredients`
--

INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `quantity_g`) VALUES
(2, 2, 1, 700.00),
(3, 2, 2, 150.00),
(4, 2, 3, 80.00),
(5, 2, 4, 20.00),
(6, 2, 5, 10.00),
(7, 2, 6, 40.00);

-- --------------------------------------------------------

--
-- Table structure for table `recipe_nutrition`
--

CREATE TABLE `recipe_nutrition` (
  `id` bigint(20) NOT NULL,
  `recipe_id` bigint(20) NOT NULL,
  `calories` decimal(10,2) DEFAULT 0.00,
  `protein` decimal(10,2) DEFAULT 0.00,
  `carbs` decimal(10,2) DEFAULT 0.00,
  `fat` decimal(10,2) DEFAULT 0.00,
  `fiber` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_nutrition`
--

INSERT INTO `recipe_nutrition` (`id`, `recipe_id`, `calories`, `protein`, `carbs`, `fat`, `fiber`, `updated_at`) VALUES
(1, 2, 2728.50, 61.90, 283.10, 172.50, 47.60, '2026-07-28 12:38:53');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) NOT NULL,
  `customer_id` bigint(20) NOT NULL,
  `plan_id` bigint(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('PENDING','APPROVED','ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `weeks` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `exclude_saturday` tinyint(1) NOT NULL DEFAULT 0,
  `exclude_sunday` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `customer_id`, `plan_id`, `start_date`, `end_date`, `status`, `approved_by`, `approved_at`, `created_at`, `weeks`, `total_amount`, `exclude_saturday`, `exclude_sunday`) VALUES
(5, 2, 1, '2026-08-15', '2026-09-16', 'ACTIVE', 2, '2026-08-08 18:22:56', '2026-08-08 12:41:03', 4, 1250.00, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('VEG','NON_VEG') NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `price_per_week` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `category`, `description`, `image_url`, `status`, `price_per_week`) VALUES
(1, 'Weekly Pickle Combo', 'VEG', 'A weekly subscription plan with different homemade pickles delivered throughout the week.', NULL, 'ACTIVE', 350.00),
(3, 'Weekly Pickle Delight', 'VEG', 'Enjoy a different homemade pickle every day of the week.', NULL, 'ACTIVE', 350.00);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plan_products`
--

CREATE TABLE `subscription_plan_products` (
  `id` bigint(20) NOT NULL,
  `plan_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `delivery_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plan_products`
--

INSERT INTO `subscription_plan_products` (`id`, `plan_id`, `product_id`, `delivery_order`) VALUES
(1, 1, 1, 1),
(2, 1, 2, 2),
(5, 3, 1, 1),
(6, 3, 2, 2),
(7, 3, 3, 3),
(8, 3, 4, 4),
(9, 3, 5, 5),
(10, 3, 6, 6),
(11, 3, 7, 7);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_skips`
--

CREATE TABLE `subscription_skips` (
  `id` bigint(20) NOT NULL,
  `subscription_id` bigint(20) NOT NULL,
  `skip_date` date NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `skip_type` enum('SATURDAY','SUNDAY','CUSTOM') NOT NULL DEFAULT 'CUSTOM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_skips`
--

INSERT INTO `subscription_skips` (`id`, `subscription_id`, `skip_date`, `refund_amount`, `reason`, `created_at`, `skip_type`) VALUES
(1, 5, '2026-08-16', 0.00, 'Sunday excluded', '2026-08-08 12:41:03', 'SUNDAY'),
(2, 5, '2026-08-23', 0.00, 'Sunday excluded', '2026-08-08 12:41:03', 'SUNDAY'),
(3, 5, '2026-08-30', 0.00, 'Sunday excluded', '2026-08-08 12:41:03', 'SUNDAY'),
(4, 5, '2026-09-06', 0.00, 'Sunday excluded', '2026-08-08 12:41:03', 'SUNDAY'),
(5, 5, '2026-09-13', 0.00, 'Sunday excluded', '2026-08-08 12:41:03', 'SUNDAY'),
(6, 5, '2026-08-20', 50.00, 'Customer selected skip date', '2026-08-08 12:41:03', 'CUSTOM'),
(7, 5, '2026-08-27', 50.00, 'Customer selected skip date', '2026-08-08 12:41:03', 'CUSTOM'),
(8, 5, '2026-09-03', 50.00, 'Customer selected skip date', '2026-08-08 12:41:03', 'CUSTOM');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('ADMIN','CUSTOMER','DELIVERY_BOY') NOT NULL DEFAULT 'CUSTOMER',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(40) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `phone`, `address`, `role`, `status`, `created_at`, `email`, `password`) VALUES
(1, 'Sakthi', '9025988050', 'Sivakasi', 'CUSTOMER', 'active', '2026-07-28 12:00:16', 'sakthi@gmail.com', '$2y$10$oNEhqRSs8vOlMfh6kCNgHuBAHrTQBFDr9oGtecneTxIV3Vj521kY2'),
(2, 'Admin', '9025988060', 'Madurai', 'ADMIN', 'active', '2026-07-28 12:00:50', 'admin@gmail.com', '$2y$10$3KNcEiBUKSOzJp4c6DYeJuUT9KQg4z99YkL.gS4h0TEAO9ipT2IK2');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cart_customer` (`customer_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `fk_cartitem_product` (`product_id`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`,`batch_no`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `recipe_nutrition`
--
ALTER TABLE `recipe_nutrition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `recipe_id` (`recipe_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscription_plan_products`
--
ALTER TABLE `subscription_plan_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plan_product` (`plan_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `subscription_skips`
--
ALTER TABLE `subscription_skips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_subscription_skip_date` (`subscription_id`,`skip_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `recipes`
--
ALTER TABLE `recipes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `recipe_nutrition`
--
ALTER TABLE `recipe_nutrition`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscription_plan_products`
--
ALTER TABLE `subscription_plan_products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `subscription_skips`
--
ALTER TABLE `subscription_skips`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cartitem_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cartitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `recipes`
--
ALTER TABLE `recipes`
  ADD CONSTRAINT `recipes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `recipe_ingredients_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`),
  ADD CONSTRAINT `recipe_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `recipe_nutrition`
--
ALTER TABLE `recipe_nutrition`
  ADD CONSTRAINT `recipe_nutrition_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`);

--
-- Constraints for table `subscription_plan_products`
--
ALTER TABLE `subscription_plan_products`
  ADD CONSTRAINT `subscription_plan_products_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_plan_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_skips`
--
ALTER TABLE `subscription_skips`
  ADD CONSTRAINT `subscription_skips_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

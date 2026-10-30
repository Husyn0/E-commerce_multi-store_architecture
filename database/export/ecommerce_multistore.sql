-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 29, 2026 at 03:28 PM
-- Server version: 8.0.46-0ubuntu0.24.04.4
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecommerce_multistore`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` char(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `is_default_shipping` tinyint(1) NOT NULL DEFAULT '0',
  `is_default_billing` tinyint(1) NOT NULL DEFAULT '0',
  `default_shipping_guard` tinyint GENERATED ALWAYS AS ((case when `is_default_shipping` then 1 else NULL end)) STORED,
  `default_billing_guard` tinyint GENERATED ALWAYS AS ((case when `is_default_billing` then 1 else NULL end)) STORED,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('web','cli','webhook') COLLATE utf8mb4_unicode_ci NOT NULL,
  `host_type` enum('admin','storefront') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `tenant_id`, `actor_id`, `action`, `entity_type`, `entity_id`, `source`, `host_type`, `metadata`, `ip_address`, `created_at`) VALUES
('01a18213-a96f-7200-b1b5-e508ffb514c3', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'tenant.provisioned', 'tenant', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'cli', NULL, '{\"admin_email\": \"admin@example.test\", \"operator_ref\": \"local-admin-bootstrap\", \"credential_delivery\": \"admin_password_reset_otp\"}', NULL, '2026-10-28 10:34:49'),
('01a18217-e50e-706c-9ae1-6eb2ebbe75e9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'admin.password_reset_otp_issued', 'user', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'cli', NULL, '{\"delivery\": \"tty\", \"operator_ref\": \"local-admin-bootstrap\"}', NULL, '2026-10-28 10:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `session_token_hash` binary(32) DEFAULT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `tenant_id`, `user_id`, `session_token_hash`, `currency`, `expires_at`, `created_at`, `updated_at`) VALUES
('01a1823b-06d5-7134-9ad0-58422c5d0dbf', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 0xbb9cd3b9c9e2420ffe6d3833cdb62407ab8865fd04e25420cfee07a72a3d375e, 'USD', '2026-11-27 09:17:48', '2026-10-28 09:17:48', '2026-10-28 09:17:48');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `cart_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `product_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `variant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `variant_key` char(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (coalesce(`variant_id`,_ascii'00000000-0000-0000-0000-000000000000')) STORED NOT NULL,
  `quantity` int NOT NULL,
  `unit_price_cents` bigint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `parent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checkout_idempotency_records`
--

CREATE TABLE `checkout_idempotency_records` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `principal_type` enum('user','guest') COLLATE utf8mb4_unicode_ci NOT NULL,
  `principal_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `guest_cart_token_hash` binary(32) DEFAULT NULL,
  `order_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `human_verifications`
--

CREATE TABLE `human_verifications` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `session_token_hash` binary(32) NOT NULL,
  `provider` enum('demo','turnstile','hcaptcha') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` enum('register','checkout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `passed` tinyint(1) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `order_number` char(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('requires_payment','processing','succeeded','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requires_payment',
  `payment_provider` enum('demo','stripe') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_intent_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checkout_request_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `checkout_idempotency_expires_at` timestamp NOT NULL,
  `subtotal_cents` bigint NOT NULL,
  `tax_cents` bigint NOT NULL DEFAULT '0',
  `shipping_cents` bigint NOT NULL DEFAULT '0',
  `discount_cents` bigint NOT NULL DEFAULT '0',
  `total_cents` bigint NOT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `shipping_address_snapshot` json NOT NULL,
  `billing_address_snapshot` json NOT NULL,
  `customer_email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `order_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `product_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `variant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `product_snapshot` json NOT NULL,
  `quantity` int NOT NULL,
  `unit_price_cents` bigint NOT NULL,
  `total_cents` bigint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `provider` enum('demo','stripe') COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_payment_method_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `card_brand` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_last4` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_exp_month` tinyint UNSIGNED DEFAULT NULL,
  `card_exp_year` smallint UNSIGNED DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `default_provider_guard` tinyint GENERATED ALWAYS AS ((case when `is_default` then 1 else NULL end)) STORED,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhook_events`
--

CREATE TABLE `payment_webhook_events` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `provider` enum('demo','stripe') COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_event_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `suspended_at_receipt` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','processing','processed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` int UNSIGNED NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `locked_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `category_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `title` varchar(240) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(240) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_cents` bigint NOT NULL,
  `compare_at_price_cents` bigint DEFAULT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `weight_grams` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `product_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `product_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attributes` json NOT NULL,
  `price_cents` bigint NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `order_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `provider_refund_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_cents` bigint NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','succeeded','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subdomain` varchar(63) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `custom_domain` varchar(253) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `default_currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `default_locale` varchar(5) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `subdomain`, `custom_domain`, `status`, `default_currency`, `default_locale`, `created_at`, `updated_at`) VALUES
('6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'Demo Shop', 'shop', NULL, 'active', 'USD', 'en', '2026-10-28 08:34:48', '2026-10-28 08:34:48');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_settings`
--

CREATE TABLE `tenant_settings` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `theme_config` json NOT NULL,
  `payment_driver` enum('demo','stripe') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'demo',
  `verification_driver` enum('demo','real') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'demo',
  `human_check_driver` enum('demo','turnstile','hcaptcha') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'demo',
  `email_provider` enum('log','smtp','postmark','ses') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'log',
  `sms_provider` enum('log','twilio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'log',
  `stripe_account_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `require_email_verification` tinyint(1) NOT NULL DEFAULT '1',
  `require_phone_verification` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_settings`
--

INSERT INTO `tenant_settings` (`id`, `tenant_id`, `theme_config`, `payment_driver`, `verification_driver`, `human_check_driver`, `email_provider`, `sms_provider`, `stripe_account_id`, `require_email_verification`, `require_phone_verification`, `updated_at`) VALUES
('01a18213-a8a2-710b-8805-48af78d69710', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '[]', 'demo', 'demo', 'demo', 'log', 'log', NULL, 1, 0, '2026-10-28 10:34:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','suspended','deleted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `email`, `email_verified_at`, `phone`, `phone_verified_at`, `password_hash`, `first_name`, `last_name`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
('e94ac131-ed1a-4f68-9d2a-806384f4c38d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'admin@example.test', NULL, NULL, NULL, '$2y$12$1U6VQenOA.6.9.cYJC5FY.NzTPi41EU4odXp/DElocZsp7Hd67WDi', NULL, NULL, 'admin', 'active', '2026-10-29 13:26:11', '2026-10-28 08:34:49', '2026-10-29 13:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `session_type` enum('admin_refresh','storefront') COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `tenant_id`, `user_id`, `session_type`, `token_hash`, `user_agent`, `ip_address`, `expires_at`, `last_used_at`, `revoked_at`, `created_at`) VALUES
('01a1821b-22c7-7269-84a8-7d19400b4b11', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb89c6bdfd33318b481872d3f3d184b3c72f9c34c44d9df7aab95028e632dfcd0, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-27 08:42:58', '2026-10-28 08:42:58', '2026-10-28 08:43:51', '2026-10-28 10:42:58'),
('01a18227-573e-7012-a0c9-d4e2e4e64d85', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x04dfdfa61d10cc51166a58077cb32501d5febe92936a9abb6599eb48f89b8349, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-27 08:56:18', '2026-10-28 08:56:18', '2026-10-28 08:57:07', '2026-10-28 10:56:18'),
('01a18259-b9c7-7121-a006-5fc8b970e522', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x5ff624fcaf72bd84e782cc0c2e712430ad1e7db2d553626ddc375e50d57bd1c1, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-27 09:51:20', '2026-10-28 09:51:20', NULL, '2026-10-28 11:51:20'),
('01a18823-ee59-70e7-b340-20a9aab130bc', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x315c656526dbb826b8a2d9f57e420a455fa880ed52a6f0c182a0fdc7578a738c, 'curl/8.5.0', '127.0.0.1', '2026-11-28 12:50:18', '2026-10-29 12:50:18', '2026-10-29 13:03:47', '2026-10-29 14:50:18'),
('01a18837-5343-70e9-b4d9-07c41e94e496', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x942ef639321b6bed537c777b4bf6f6c54178f8ee08d7690dfa252383b4c3d6ee, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:11:29', '2026-10-29 13:11:29', NULL, '2026-10-29 15:11:29'),
('01a1883c-8dad-7363-bc79-eee68f835843', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x9d6e4c595d55beb50870a9c7d2ad5f3f9b352f84d36a79da0052e30f08e42901, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:17:12', '2026-10-29 13:17:12', NULL, '2026-10-29 15:17:12'),
('01a1883c-f165-7187-b4df-1b8509c04ea1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x409a83ac66f15c5b5c37c920fcee163bb78f73efa6e8a8c0511a8f93dc57fef5, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:17:37', '2026-10-29 13:17:37', '2026-10-29 13:18:22', '2026-10-29 15:17:37'),
('01a18840-7659-703f-8fc3-c9a59a3b427c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xd8e78743aa97304490d60fa44d7cdc2df0e2f185e8b34ba6b2dacdf408fdd658, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:21:28', '2026-10-29 13:21:28', NULL, '2026-10-29 15:21:28'),
('01a18843-aceb-7297-99c9-1d8ea96d4de5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb786d0d065a639a3d4e771cb44852d55b5b259c4f214791511267cf210675d81, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:24:58', '2026-10-29 13:24:58', NULL, '2026-10-29 15:24:58'),
('01a18844-c7b2-70a4-92fa-720d10207c2c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xf61da067b0a18ad0012c8547e12759e825ff52a3ac449b863b69ef938708ee1d, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:26:11', '2026-10-29 13:26:11', '2026-10-29 13:26:50', '2026-10-29 15:26:11'),
('01a18845-5f06-723a-93ae-b9c06c48e180', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb584cb7178df551d5f4e59a69c28500deb7a11996fa729b3a0ff5716e6bc427e, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:26:50', '2026-10-29 13:26:50', NULL, '2026-10-29 15:26:50');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `tenant_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `user_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `identifier` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('email','sms') COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` enum('verify_email','verify_phone','login_2fa','password_reset','guest_order_lookup') COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` binary(32) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `max_attempts` smallint UNSIGNED NOT NULL DEFAULT '5',
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `tenant_id`, `user_id`, `identifier`, `channel`, `purpose`, `code_hash`, `expires_at`, `attempts`, `max_attempts`, `used_at`, `created_at`) VALUES
('01a18217-e50a-717d-bc97-b048eaa14a76', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin@example.test', 'email', 'password_reset', 0x6648473eed826ebc7e9dcb2e01861151748532032142b22d083c00618ee480b6, '2026-10-28 08:49:26', 0, 5, '2026-10-28 08:42:46', '2026-10-28 10:39:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_addresses_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_addresses_default_shipping` (`tenant_id`,`user_id`,`default_shipping_guard`),
  ADD UNIQUE KEY `uq_addresses_default_billing` (`tenant_id`,`user_id`,`default_billing_guard`),
  ADD KEY `ix_addresses_user` (`tenant_id`,`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_audit_logs_tenant_id_id` (`tenant_id`,`id`),
  ADD KEY `ix_audit_logs_tenant_created` (`tenant_id`,`created_at`),
  ADD KEY `ix_audit_logs_tenant_source_created` (`tenant_id`,`source`,`created_at`),
  ADD KEY `ix_audit_logs_entity` (`tenant_id`,`entity_type`,`entity_id`),
  ADD KEY `ix_audit_logs_actor` (`tenant_id`,`actor_id`,`created_at`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_carts_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_carts_session_token` (`tenant_id`,`session_token_hash`),
  ADD KEY `ix_carts_user` (`tenant_id`,`user_id`),
  ADD KEY `ix_carts_token` (`tenant_id`,`session_token_hash`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_items_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_cart_items_line` (`tenant_id`,`cart_id`,`product_id`,`variant_key`),
  ADD KEY `ix_cart_items_cart` (`tenant_id`,`cart_id`),
  ADD KEY `fk_cart_items_product` (`tenant_id`,`product_id`),
  ADD KEY `fk_cart_items_variant` (`tenant_id`,`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_categories_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `ix_categories_parent_active` (`tenant_id`,`parent_id`,`is_active`);

--
-- Indexes for table `checkout_idempotency_records`
--
ALTER TABLE `checkout_idempotency_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checkout_idempotency_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_checkout_idempotency_key` (`tenant_id`,`idempotency_key`),
  ADD KEY `ix_checkout_idempotency_expires` (`tenant_id`,`expires_at`),
  ADD KEY `fk_checkout_idempotency_user` (`tenant_id`,`principal_id`),
  ADD KEY `fk_checkout_idempotency_order` (`tenant_id`,`order_id`);

--
-- Indexes for table `human_verifications`
--
ALTER TABLE `human_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_human_verifications_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_human_verifications_token` (`tenant_id`,`session_token_hash`),
  ADD KEY `ix_human_verifications_expires` (`tenant_id`,`expires_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_orders_order_number` (`tenant_id`,`order_number`),
  ADD UNIQUE KEY `uq_orders_payment_intent` (`tenant_id`,`payment_provider`,`payment_intent_id`),
  ADD KEY `ix_orders_status_created` (`tenant_id`,`status`,`created_at`),
  ADD KEY `ix_orders_user` (`tenant_id`,`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_items_tenant_id_id` (`tenant_id`,`id`),
  ADD KEY `ix_order_items_order` (`tenant_id`,`order_id`),
  ADD KEY `fk_order_items_product` (`tenant_id`,`product_id`),
  ADD KEY `fk_order_items_variant` (`tenant_id`,`variant_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_methods_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_payment_methods_default` (`tenant_id`,`user_id`,`provider`,`default_provider_guard`),
  ADD KEY `ix_payment_methods_user` (`tenant_id`,`user_id`),
  ADD KEY `ix_payment_methods_user_provider_default` (`tenant_id`,`user_id`,`provider`,`is_default`);

--
-- Indexes for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_webhook_events_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_webhook_events_dedup` (`tenant_id`,`provider`,`provider_event_id`),
  ADD KEY `ix_webhook_events_tenant_status_created` (`tenant_id`,`status`,`created_at`),
  ADD KEY `ix_webhook_events_status_created` (`status`,`created_at`),
  ADD KEY `ix_webhook_events_status_locked` (`status`,`locked_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_products_tenant_slug` (`tenant_id`,`slug`),
  ADD UNIQUE KEY `uq_products_tenant_sku` (`tenant_id`,`sku`),
  ADD KEY `ix_products_catalog` (`tenant_id`,`category_id`,`is_active`,`published_at`),
  ADD KEY `fk_products_tenant_currency` (`tenant_id`,`currency`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_images_tenant_id_id` (`tenant_id`,`id`),
  ADD KEY `ix_product_images_product_position` (`tenant_id`,`product_id`,`position`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_variants_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_product_variants_tenant_sku` (`tenant_id`,`sku`),
  ADD KEY `ix_product_variants_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refunds_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_refunds_provider_id` (`tenant_id`,`provider_refund_id`),
  ADD KEY `ix_refunds_order` (`tenant_id`,`order_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenants_subdomain` (`subdomain`),
  ADD UNIQUE KEY `uq_tenants_id_currency` (`id`,`default_currency`),
  ADD UNIQUE KEY `uq_tenants_custom_domain` (`custom_domain`);

--
-- Indexes for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_settings_tenant` (`tenant_id`),
  ADD UNIQUE KEY `uq_tenant_settings_id_tenant` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_tenant_settings_stripe_account` (`stripe_account_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  ADD KEY `ix_users_tenant_role_status` (`tenant_id`,`role`,`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_sessions_tenant_id_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uq_user_sessions_token` (`tenant_id`,`token_hash`),
  ADD KEY `ix_user_sessions_tenant_user_expires` (`tenant_id`,`user_id`,`expires_at`),
  ADD KEY `ix_user_sessions_tenant_type_user` (`tenant_id`,`session_type`,`user_id`),
  ADD KEY `ix_user_sessions_tenant_type_last_used` (`tenant_id`,`session_type`,`last_used_at`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_verification_codes_tenant_id_id` (`tenant_id`,`id`),
  ADD KEY `ix_verification_codes_identifier` (`tenant_id`,`identifier`,`purpose`,`expires_at`),
  ADD KEY `ix_verification_codes_user_purpose` (`tenant_id`,`user_id`,`purpose`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_addresses_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_actor` FOREIGN KEY (`tenant_id`,`actor_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_audit_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`tenant_id`,`cart_id`) REFERENCES `carts` (`tenant_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`tenant_id`,`product_id`) REFERENCES `products` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_cart_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_cart_items_variant` FOREIGN KEY (`tenant_id`,`variant_id`) REFERENCES `product_variants` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`tenant_id`,`parent_id`) REFERENCES `categories` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `checkout_idempotency_records`
--
ALTER TABLE `checkout_idempotency_records`
  ADD CONSTRAINT `fk_checkout_idempotency_order` FOREIGN KEY (`tenant_id`,`order_id`) REFERENCES `orders` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_checkout_idempotency_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_checkout_idempotency_user` FOREIGN KEY (`tenant_id`,`principal_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `human_verifications`
--
ALTER TABLE `human_verifications`
  ADD CONSTRAINT `fk_human_verifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`tenant_id`,`order_id`) REFERENCES `orders` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`tenant_id`,`product_id`) REFERENCES `products` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`tenant_id`,`variant_id`) REFERENCES `product_variants` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `fk_payment_methods_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_payment_methods_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  ADD CONSTRAINT `fk_webhook_events_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`tenant_id`,`category_id`) REFERENCES `categories` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_products_tenant_currency` FOREIGN KEY (`tenant_id`,`currency`) REFERENCES `tenants` (`id`, `default_currency`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`tenant_id`,`product_id`) REFERENCES `products` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_product_images_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`tenant_id`,`product_id`) REFERENCES `products` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_product_variants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_order` FOREIGN KEY (`tenant_id`,`order_id`) REFERENCES `orders` (`tenant_id`, `id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  ADD CONSTRAINT `fk_tenant_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;

--
-- Constraints for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD CONSTRAINT `fk_verification_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_verification_codes_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`, `id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

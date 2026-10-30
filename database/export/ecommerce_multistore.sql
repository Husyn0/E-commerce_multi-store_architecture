-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 30, 2026 at 06:12 PM
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

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `tenant_id`, `user_id`, `label`, `recipient_name`, `phone`, `line1`, `line2`, `city`, `state`, `postal_code`, `country_code`, `is_default_shipping`, `is_default_billing`, `created_at`, `updated_at`) VALUES
('01a18d3f-a213-73d0-966f-75ab173a8aa2', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', NULL, 'Hussein Azzam', '+96181121843', 'saddiqine main street', NULL, 'tyre', 'south', '00000', 'LB', 1, 1, '2026-10-30 12:38:40', '2026-10-30 12:38:40');

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
('01a18217-e50e-706c-9ae1-6eb2ebbe75e9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'admin.password_reset_otp_issued', 'user', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'cli', NULL, '{\"delivery\": \"tty\", \"operator_ref\": \"local-admin-bootstrap\"}', NULL, '2026-10-28 10:39:26'),
('01a18be7-c796-71a4-90e6-53138f0d9846', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18be7-c794-71b5-818b-76c9ab8a4349', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:23:05'),
('01a18bf4-71f2-70ac-8e53-718c8725e243', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:36:55'),
('01a18bf5-c966-722a-8851-46fde9d4b95c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:38:23'),
('01a18bf6-fd47-7364-90bc-fe47b8141f20', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:39:42'),
('01a18bf8-05bc-7279-a059-df99cb9522c1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18bf8-05bb-720d-b9d0-ea46134e1585', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:40:49'),
('01a18bf9-e437-7037-8063-9d2f8fcd23bf', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18bf9-e431-738c-b5c5-5ae961ce7763', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 08:42:52'),
('01a18bfb-15d9-7367-909f-dd5ebaed6fe3', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'settings.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', '{\"fields\": [\"name\", \"default_locale\", \"require_email_verification\", \"require_phone_verification\"]}', '127.0.0.1', '2026-10-30 08:44:10'),
('01a18bfc-5fb8-7260-b08f-b45357637368', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'settings.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', '{\"fields\": [\"name\", \"default_locale\", \"require_email_verification\", \"require_phone_verification\"]}', '127.0.0.1', '2026-10-30 08:45:35'),
('01a18bfc-995b-7117-94df-bc445ed17a1f', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'settings.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', '{\"fields\": [\"name\", \"default_locale\", \"require_email_verification\", \"require_phone_verification\"]}', '127.0.0.1', '2026-10-30 08:45:49'),
('01a18c37-f274-722d-9ae3-9378e34cdd86', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 09:50:39'),
('01a18cb7-c9b4-7299-a036-63fceba4306d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', '{\"stock\": 3, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 12:10:17'),
('01a18cb7-e938-73bf-b968-e4d720a147fd', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', '{\"stock\": 1, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 12:10:25'),
('01a18cb8-0a07-7180-acd6-ea13976270c6', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', '{\"stock\": 10, \"previous_stock\": 3}', '127.0.0.1', '2026-10-30 12:10:33'),
('01a18cb8-341b-70d3-8061-794194c2a688', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', '{\"stock\": 10, \"previous_stock\": 1}', '127.0.0.1', '2026-10-30 12:10:44'),
('01a18cb8-c3d7-7068-a9fa-87151175d5e4', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.published', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 12:11:21'),
('01a18cdd-8663-71ef-8030-d04095f9b3f1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 12:51:30'),
('01a18cdf-0bf3-73df-9b15-6beeb209307b', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 12:53:10'),
('01a18cf0-9f29-718e-bfc1-a426047d13d0', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:12:22'),
('01a18cf0-bdf3-70f1-9bd1-7948a7dc94d9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:12:29'),
('01a18cf0-d137-7050-85b4-09e11e9a154c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:12:34'),
('01a18cf1-5175-713c-b9ce-8383fa33d5d3', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:13:07'),
('01a18cf5-c2c0-70ae-ad1f-a70e4df57894', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:17:58'),
('01a18d00-dffe-73b5-b0c3-d02c94fe1649', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:30:07'),
('01a18d01-1a7d-7035-93fd-f3250a820c24', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:30:22'),
('01a18d08-32a8-72d6-b87f-99849a98b760', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 13:38:07'),
('01a18d1d-8b2b-702f-a448-86f13eb1511b', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18bf4-71ed-738d-a6f9-05c2143f12d9', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:01:26'),
('01a18d1e-16f7-71fc-a7af-c2d78608a61a', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:02:01'),
('01a18d1e-9855-7053-a8ae-04060bec4dde', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:02:34'),
('01a18d1f-5624-727b-a0f6-7ec7b6e46cf5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.updated', 'product', '01a18c37-f258-7110-a592-aec93f3e2104', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:03:23'),
('01a18d28-d4e2-70e9-8732-46736c5969b1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'customer.suspended', 'user', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'web', 'admin', '{\"status\": \"suspended\"}', '127.0.0.1', '2026-10-30 14:13:45'),
('01a18d28-ded6-7387-9aa5-eccfef240ac1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'customer.updated', 'user', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'web', 'admin', '{\"status\": \"active\"}', '127.0.0.1', '2026-10-30 14:13:48'),
('01a18d28-f29f-73f4-9541-076a399a7c03', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'customer.suspended', 'user', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'web', 'admin', '{\"status\": \"suspended\"}', '127.0.0.1', '2026-10-30 14:13:53'),
('01a18d3b-67f6-73ae-9b62-1ad0004c6bfd', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'customer.updated', 'user', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'web', 'admin', '{\"status\": \"active\"}', '127.0.0.1', '2026-10-30 14:34:03'),
('01a18d46-fb98-7179-9e5f-9f98611d2e30', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'cart.merged', 'cart', '01a18be3-195c-727e-9121-b65092c31ea0', 'web', 'storefront', '{\"source\": \"guest\", \"merged_line_count\": 1}', '127.0.0.1', '2026-10-30 14:46:41'),
('01a18d4b-6804-7157-9a2e-3d4c46c56e9c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18d4b-6801-7397-bd27-a515170b5ab2', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:51:31'),
('01a18d4b-db27-71fb-9f03-742854dff3fd', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.updated', 'category', '01a18be7-c794-71b5-818b-76c9ab8a4349', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:52:01'),
('01a18d4c-007c-7092-ae43-c7abb211f8ce', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.updated', 'category', '01a18bf8-05bb-720d-b9d0-ea46134e1585', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:52:10'),
('01a18d4c-b3f5-704b-bd41-f95ebf4cbe24', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'settings.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', '{\"fields\": [\"name\", \"default_locale\", \"require_email_verification\", \"require_phone_verification\"]}', '127.0.0.1', '2026-10-30 14:52:56'),
('01a18d4d-66d1-738c-94f4-e120517048b9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:53:42'),
('01a18d4d-cd14-713f-8a1b-0f185bb1f133', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 14:54:08'),
('01a18d54-3602-726b-a6cf-81f15f3f10bf', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18d54-3600-73d4-8b9a-3c2df6b8fdbc', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:01:08'),
('01a18d54-8499-7021-a5ec-89aef91f73e5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18d54-3600-73d4-8b9a-3c2df6b8fdbc', 'web', 'admin', '{\"stock\": 6, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 15:01:28'),
('01a18d56-04ba-7287-ae45-47a2abff7c39', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18d56-04b8-7100-8fe7-09fb3680dfe1', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:03:07'),
('01a18d56-1bce-73c3-be78-11394049cbc6', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.updated', 'category', '01a18bf9-e431-738c-b5c5-5ae961ce7763', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:03:13'),
('01a18d56-69a1-71de-b6eb-9180eaf01f6c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.updated', 'category', '01a18bf9-e431-738c-b5c5-5ae961ce7763', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:03:33'),
('01a18d57-19ab-7001-82ce-1d215ef15d7a', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.updated', 'category', '01a18d4b-6801-7397-bd27-a515170b5ab2', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:04:18'),
('01a18d57-a649-73b4-94dd-960994becb5a', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.created', 'category', '01a18d57-a647-738d-8b63-49d8a41d9036', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:04:54'),
('01a18d58-4616-72ac-8a22-98cd64108f4a', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'category.deleted', 'category', '01a18d57-a647-738d-8b63-49d8a41d9036', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 15:05:34'),
('01a18dee-7b0a-7041-ba9d-5b2816377414', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18dee-7b08-7359-826a-d300bed0e9f7', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:49:38'),
('01a18dee-9446-702e-8a68-0ca3c05af0d3', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18dee-7b08-7359-826a-d300bed0e9f7', 'web', 'admin', '{\"stock\": 5, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 17:49:45'),
('01a18df0-47e3-71b3-9e56-518834bedfaf', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18df0-47e1-72a6-a52b-c9c6b62301c6', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:51:36'),
('01a18df0-8ad9-7147-9b9e-c2f8ab7349f2', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.published', 'product', '01a18df0-47e1-72a6-a52b-c9c6b62301c6', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:51:54'),
('01a18df0-ac2e-7244-a537-281d4fe334e9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18df0-47e1-72a6-a52b-c9c6b62301c6', 'web', 'admin', '{\"stock\": 7, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 17:52:02'),
('01a18df2-745a-71fa-aa15-5a46aea46ef7', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'product.created', 'product', '01a18df2-7459-73f3-ac4d-24dc3080cafd', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:53:59'),
('01a18df2-8f9c-705f-aed5-c50784c6d7c5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'inventory.adjusted', 'product', '01a18df2-7459-73f3-ac4d-24dc3080cafd', 'web', 'admin', '{\"stock\": 5, \"previous_stock\": 0}', '127.0.0.1', '2026-10-30 17:54:06'),
('01a18df3-a85e-7150-850e-eea01d3719d0', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:55:18'),
('01a18df4-62f7-710a-97f4-9d95f3a0f67c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:56:06'),
('01a18df4-f960-7259-8789-b2a6ed9d644e', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'theme.updated', 'tenant_settings', '01a18213-a8a2-710b-8805-48af78d69710', 'web', 'admin', NULL, '127.0.0.1', '2026-10-30 17:56:44');

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
('01a1823b-06d5-7134-9ad0-58422c5d0dbf', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 0xbb9cd3b9c9e2420ffe6d3833cdb62407ab8865fd04e25420cfee07a72a3d375e, 'USD', '2026-11-27 09:17:48', '2026-10-28 09:17:48', '2026-10-28 09:17:48'),
('01a188a5-63df-73d6-9159-02d58db738b5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 0x4b9a6fdb62db817e0ea244886df5f3e7f0a42fc1027a6d6f69253fed0d793884, 'USD', '2026-11-28 15:11:42', '2026-10-29 15:11:42', '2026-10-29 15:11:42'),
('01a18be3-195c-727e-9121-b65092c31ea0', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', NULL, 'USD', '2026-11-29 15:56:49', '2026-10-30 06:17:58', '2026-10-30 15:56:49'),
('01a18cf4-60f9-7114-a1eb-721a39c2c9ea', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 0x1889a49427998e31c3ad8cfbb1da80eb9835d9244277a862be1a0d769ced1468, 'USD', '2026-11-29 11:16:28', '2026-10-30 11:16:28', '2026-10-30 11:16:28');

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
  `icon` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `tenant_id`, `parent_id`, `name`, `slug`, `position`, `is_active`, `created_at`, `updated_at`) VALUES
('01a18be7-c794-71b5-818b-76c9ab8a4349', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'computers', 'aa', 2, 1, '2026-10-30 06:23:05', '2026-10-30 12:52:01'),
('01a18bf8-05bb-720d-b9d0-ea46134e1585', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18be7-c794-71b5-818b-76c9ab8a4349', 'accessories', 'bb', 3, 1, '2026-10-30 06:40:49', '2026-10-30 12:52:10'),
('01a18bf9-e431-738c-b5c5-5ae961ce7763', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'Electronics', 'ccccc', 0, 1, '2026-10-30 06:42:52', '2026-10-30 13:03:33'),
('01a18d4b-6801-7397-bd27-a515170b5ab2', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d56-04b8-7100-8fe7-09fb3680dfe1', 'cocktail', 'drtdrtd', 1, 1, '2026-10-30 12:51:31', '2026-10-30 13:04:18'),
('01a18d56-04b8-7100-8fe7-09fb3680dfe1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'Food', 'cdacda', 0, 1, '2026-10-30 13:03:07', '2026-10-30 13:03:07');

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

--
-- Dumping data for table `human_verifications`
--

INSERT INTO `human_verifications` (`id`, `tenant_id`, `session_token_hash`, `provider`, `action`, `passed`, `ip_address`, `expires_at`, `created_at`) VALUES
('01a188a5-b060-7096-8352-4361d8bfed04', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 0x4b9a6fdb62db817e0ea244886df5f3e7f0a42fc1027a6d6f69253fed0d793884, 'demo', 'register', 1, '127.0.0.1', '2026-10-29 15:17:02', '2026-10-29 17:12:02'),
('01a18d28-0157-70bb-9f2c-500671ebbf30', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 0xdc871e35f3c93f27866030a238c31ee9b9981e626bf22a403c490b8518b195cd, 'demo', 'register', 1, '127.0.0.1', '2026-10-30 12:17:51', '2026-10-30 14:12:51');

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

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `tenant_id`, `category_id`, `title`, `slug`, `description`, `sku`, `price_cents`, `compare_at_price_cents`, `currency`, `stock`, `weight_grams`, `is_active`, `published_at`, `created_at`, `updated_at`) VALUES
('01a18bf4-71ed-738d-a6f9-05c2143f12d9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18be7-c794-71b5-818b-76c9ab8a4349', 'laptop', 'll', 'black laptop , 17 inch screen, core i7 13700H', '10', 9, 10, 'USD', 10, NULL, 1, '2026-10-30 06:35:00', '2026-10-30 06:36:55', '2026-10-30 12:01:26'),
('01a18c37-f258-7110-a592-aec93f3e2104', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18bf9-e431-738c-b5c5-5ae961ce7763', 'arduino', 'njh', 'dev board', NULL, 100, 101, 'USD', 10, NULL, 1, '2026-10-30 10:11:00', '2026-10-30 07:50:39', '2026-10-30 12:03:23'),
('01a18d54-3600-73d4-8b9a-3c2df6b8fdbc', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d4b-6801-7397-bd27-a515170b5ab2', 'Avocado', 'fcf', 'Avocado cocktail with milk', NULL, 500, NULL, 'USD', 6, NULL, 1, '2026-10-30 13:00:00', '2026-10-30 13:01:08', '2026-10-30 13:01:28'),
('01a18dee-7b08-7359-826a-d300bed0e9f7', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18bf8-05bb-720d-b9d0-ea46134e1585', 'keyboard', 'hdrt', 'wireless keyboard', NULL, 1, NULL, 'USD', 5, NULL, 1, '2026-10-30 15:48:00', '2026-10-30 15:49:38', '2026-10-30 15:49:45'),
('01a18df0-47e1-72a6-a52b-c9c6b62301c6', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d56-04b8-7100-8fe7-09fb3680dfe1', 'potato', 'frw', 'fried potato with katchab', 'hyjgppppppppppppppppppppppp', 5165516, NULL, 'USD', 7, NULL, 1, '2026-10-30 15:51:00', '2026-10-30 15:51:36', '2026-10-30 15:52:02'),
('01a18df2-7459-73f3-ac4d-24dc3080cafd', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d4b-6801-7397-bd27-a515170b5ab2', 'Strawberry Cocktail', 'fr', 'strawberry with banana and milk cocktail', 'yguu', 5000, NULL, 'USD', 5, NULL, 1, '2026-10-30 15:53:00', '2026-10-30 15:53:59', '2026-10-30 15:54:06');

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
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
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
('6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'Asil Shop', 'shop', NULL, 'active', 'USD', 'en', '2026-10-28 08:34:48', '2026-10-30 12:52:56');

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
('01a18213-a8a2-710b-8805-48af78d69710', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '{\"fonts\": {\"body\": \"Arial\", \"heading\": \"Times New Roman\"}, \"colors\": {\"text\": \"#242323\", \"accent\": \"#aa7d52\", \"primary\": \"#6b5b4b\", \"secondary\": \"#f0ece7\", \"background\": \"#ffffff\"}, \"logo_url\": null, \"layout_variant\": \"editorial\"}', 'demo', 'demo', 'demo', 'smtp', 'log', NULL, 1, 0, '2026-10-30 17:56:44');

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
('01a18d28-023e-70b9-96b0-4ffd51bdcb5d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'mr.hussein.ceng@gmail.com', '2026-10-30 12:12:51', '+96181121843', NULL, '$2y$12$WJlatnXjiBuS1QXXVPJ.7uqSoj5Zqt/OwY0Rk0uQqhzTnCV.gId7C', 'Husyn', 'Azzam', 'customer', 'active', '2026-10-30 12:34:27', '2026-10-30 12:12:51', '2026-10-30 16:05:34'),
('e94ac131-ed1a-4f68-9d2a-806384f4c38d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'admin@example.test', NULL, NULL, NULL, '$2y$12$1U6VQenOA.6.9.cYJC5FY.NzTPi41EU4odXp/DElocZsp7Hd67WDi', NULL, NULL, 'admin', 'active', '2026-10-30 16:07:51', '2026-10-28 08:34:49', '2026-10-30 16:07:51');

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
('01a18259-b9c7-7121-a006-5fc8b970e522', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x5ff624fcaf72bd84e782cc0c2e712430ad1e7db2d553626ddc375e50d57bd1c1, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-27 09:51:20', '2026-10-28 09:51:20', '2026-10-30 06:19:19', '2026-10-28 11:51:20'),
('01a18823-ee59-70e7-b340-20a9aab130bc', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x315c656526dbb826b8a2d9f57e420a455fa880ed52a6f0c182a0fdc7578a738c, 'curl/8.5.0', '127.0.0.1', '2026-11-28 12:50:18', '2026-10-29 12:50:18', '2026-10-29 13:03:47', '2026-10-29 14:50:18'),
('01a18837-5343-70e9-b4d9-07c41e94e496', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x942ef639321b6bed537c777b4bf6f6c54178f8ee08d7690dfa252383b4c3d6ee, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:11:29', '2026-10-29 13:11:29', NULL, '2026-10-29 15:11:29'),
('01a1883c-8dad-7363-bc79-eee68f835843', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x9d6e4c595d55beb50870a9c7d2ad5f3f9b352f84d36a79da0052e30f08e42901, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:17:12', '2026-10-29 13:17:12', NULL, '2026-10-29 15:17:12'),
('01a1883c-f165-7187-b4df-1b8509c04ea1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x409a83ac66f15c5b5c37c920fcee163bb78f73efa6e8a8c0511a8f93dc57fef5, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:17:37', '2026-10-29 13:17:37', '2026-10-29 13:18:22', '2026-10-29 15:17:37'),
('01a18840-7659-703f-8fc3-c9a59a3b427c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xd8e78743aa97304490d60fa44d7cdc2df0e2f185e8b34ba6b2dacdf408fdd658, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:21:28', '2026-10-29 13:21:28', NULL, '2026-10-29 15:21:28'),
('01a18843-aceb-7297-99c9-1d8ea96d4de5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb786d0d065a639a3d4e771cb44852d55b5b259c4f214791511267cf210675d81, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:24:58', '2026-10-29 13:24:58', NULL, '2026-10-29 15:24:58'),
('01a18844-c7b2-70a4-92fa-720d10207c2c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xf61da067b0a18ad0012c8547e12759e825ff52a3ac449b863b69ef938708ee1d, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:26:11', '2026-10-29 13:26:11', '2026-10-29 13:26:50', '2026-10-29 15:26:11'),
('01a18845-5f06-723a-93ae-b9c06c48e180', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb584cb7178df551d5f4e59a69c28500deb7a11996fa729b3a0ff5716e6bc427e, 'curl/8.5.0', '127.0.0.1', '2026-11-28 13:26:50', '2026-10-29 13:26:50', NULL, '2026-10-29 15:26:50'),
('01a18864-1451-715b-8c39-4bc11c956048', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x3d8b766faa71fe4c7f63cd6d928dd9f14727808d23d278a4f8f3480a2af65330, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:00:22', '2026-10-29 14:00:22', '2026-10-29 14:01:11', '2026-10-29 16:00:22'),
('01a18864-d263-7063-939c-872c47dfc7b9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xbd8d823de042ebfdad6bc9c007bdfa8dc416fc82c12e235643ee63dc7c5553d8, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:01:11', '2026-10-29 14:01:11', '2026-10-29 14:01:35', '2026-10-29 16:01:11'),
('01a1886e-8d0a-7357-9fea-20c8ec4d8132', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x91e8b2d35eb620b7b352dbb01244236721669b3e872d465a9a799e29927effc8, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:11:48', '2026-10-29 14:11:48', '2026-10-29 14:12:23', '2026-10-29 16:11:48'),
('01a1886f-1538-713e-84c9-ed6b7bcd24f7', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x5eb47f86d0a705a3be42baa40d535d254d24d0c7cd897c7a93b8ee2daa076eda, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:12:23', '2026-10-29 14:12:23', '2026-10-29 14:12:41', '2026-10-29 16:12:23'),
('01a1886f-5c6b-729b-bf87-7f414e60715e', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x40edff4dd95fa2e9e19585531f9a3f26e1f7eb4a8cd6c8b064f3f83bab0ae221, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:12:41', '2026-10-29 14:12:41', '2026-10-29 14:12:57', '2026-10-29 16:12:41'),
('01a18873-b9ee-707a-9a9a-1c2284cd3258', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x5973c64968c817e84e2b230e0e444b9d39283bfaf6037727f621e224f2080985, 'curl/8.5.0', '127.0.0.1', '2026-11-28 14:17:28', '2026-10-29 14:17:28', NULL, '2026-10-29 16:17:28'),
('01a1889f-3ff6-70fe-af0d-4b0638466bc8', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x8f9d9abccf505afbc653de29d7f2185cd79731158612deae9867c16a04a711eb, 'curl/8.5.0', '127.0.0.1', '2026-11-28 15:05:00', '2026-10-29 15:05:00', '2026-10-29 15:06:11', '2026-10-29 17:05:00'),
('01a188a0-56bb-7119-8b82-4786eb0d613b', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xcd1d2c12387c76301341d24cd831661a65c22ea7f2a7d91ca1488eec4bb1a319, 'curl/8.5.0', '127.0.0.1', '2026-11-28 15:06:11', '2026-10-29 15:06:11', '2026-10-29 15:06:19', '2026-10-29 17:06:11'),
('01a188aa-4b70-72b3-ae30-1cb1698f4265', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x32e1b543cc8a6dd69ec3f9dcc8c0d713f2c211ac1063cf0dd4e12f5f1cda9835, 'curl/8.5.0', '127.0.0.1', '2026-11-28 15:17:04', '2026-10-29 15:17:04', NULL, '2026-10-29 17:17:04'),
('01a188aa-6fe3-7339-991a-f5b00bd7d5a9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x6ee49067bff514f32eb0d75f8938b49cb6b8f637a8497b9d20faafa3a2ea78c0, 'curl/8.5.0', '127.0.0.1', '2026-11-28 15:17:13', '2026-10-29 15:17:13', NULL, '2026-10-29 17:17:13'),
('01a18be4-54a3-7367-85b2-69fcab3e2d18', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xc73f63b4371ed79f815ca1ba98513302d8727c4475a5aeec71b311bce63afc14, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 06:19:19', '2026-10-30 06:19:19', NULL, '2026-10-30 08:19:19'),
('01a18be5-937e-71d0-be1f-5937fec4e540', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xc539eeed9bfb28efa394c9825446cf69e9a3ce101cf37ef2972dad288b489d66, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 06:20:40', '2026-10-30 06:20:40', '2026-10-30 06:34:55', '2026-10-30 08:20:40'),
('01a18bf2-9d57-71ad-a776-5105c6ad8ac2', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xcb8c6d161169448bccc0cdc8da1a74f3a042e8d09503af88a83c3f43f386020f, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 06:34:55', '2026-10-30 06:34:55', '2026-10-30 06:45:34', '2026-10-30 08:34:55'),
('01a18bfc-5f54-7214-a800-01588093bf8d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x998564d5b60aca89eeebab7de67bf66ae04c9798dffb9c365c14fbfc05ed6c65, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 06:45:34', '2026-10-30 06:45:34', '2026-10-30 07:17:36', '2026-10-30 08:45:34'),
('01a18c19-b052-7391-ad3f-dcc78237bec9', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xc4ea26270ce1076b772582fa6241c096e620520b5f7cdeb6d0bf54f8218b8afd, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 07:17:36', '2026-10-30 07:17:36', '2026-10-30 07:50:38', '2026-10-30 09:17:36'),
('01a18c37-f193-72dd-a866-e45a469ebc82', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x93f986af43569e34c2574c2479c8741cc1d1cf636c9f65e601f6a41b83174822, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 07:50:38', '2026-10-30 07:50:38', '2026-10-30 09:51:29', '2026-10-30 09:50:39'),
('01a18ca6-95db-7130-adbf-553f78bcfe72', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x3f8258eb46d129c49fafd9bbcfea8e6d3f58e8b2de3115560f7d566f02a88dfa, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 09:51:29', '2026-10-30 09:51:29', NULL, '2026-10-30 11:51:30'),
('01a18cb7-613c-731a-a421-ba1576bc62ff', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x9362f288e6b07a7559fa7a9ce6038fb47549fee0a2bfbd18115dd77aa302b171, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 10:09:50', '2026-10-30 10:09:50', '2026-10-30 10:23:13', '2026-10-30 12:09:50'),
('01a18cc3-a1a7-70e2-ab84-de5593eb169c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x2ee211899f448a16db2887b55b9d988bc92b2861e56387f28728088815cff319, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 10:23:13', '2026-10-30 10:23:13', '2026-10-30 10:40:33', '2026-10-30 12:23:13'),
('01a18cd3-80f4-70a0-bea5-60423ddb9114', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xef842fdb42fbd9981d0b2e5cd785291b498e70329b8d338fefd6b9d59bd2dc41, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 10:40:33', '2026-10-30 10:40:33', '2026-10-30 10:50:34', '2026-10-30 12:40:33'),
('01a18cdc-ab2e-731c-8822-e8830ec454f4', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xf4c3d5d63d854da56d15715abc8c7a92fbd01170a9fa6c2692faa5390dc4ab71, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 10:50:34', '2026-10-30 10:50:34', '2026-10-30 11:03:15', '2026-10-30 12:50:34'),
('01a18ce8-496c-71e7-96f1-f79f9349a2cd', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xcbe9c9cd588d22377c24ca414d90dfb659759dad6b520e0cf269e9c8e4df1f73, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:03:15', '2026-10-30 11:03:15', '2026-10-30 11:04:21', '2026-10-30 13:03:15'),
('01a18ce9-48a6-72ec-815f-98c6ba226e40', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x676da59c9eb0a2ad79f6f6521fa20e31f074d1518356737ac249b2f30c5aa505, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:04:21', '2026-10-30 11:04:21', NULL, '2026-10-30 13:04:21'),
('01a18ce9-f150-73b5-a696-a210f31e6501', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xa1f2bf406d0d9799c3e707ec7f24f4318261ee738e90493a4ead40ed83d1f9dd, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:05:04', '2026-10-30 11:05:04', '2026-10-30 11:17:52', '2026-10-30 13:05:04'),
('01a18cf5-a922-72cc-9abf-e39055361d40', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x44c6352d4baabe65e795af7609a23fc8f88ab1efcfec578d685400dd6cac7617, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:17:52', '2026-10-30 11:17:52', '2026-10-30 11:19:01', '2026-10-30 13:17:52'),
('01a18cf6-b78f-7149-9913-aa4deb96c74e', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xc506937102dc94599c71f303c93ac974d057ffbf7d59b94692dcbe6f7ccd66a9, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:19:01', '2026-10-30 11:19:01', '2026-10-30 11:24:33', '2026-10-30 13:19:01'),
('01a18cfb-ca18-73df-b6fa-0e1ca8893aa7', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xa2225f6c6cc9b2dfd0b422d25fd48780a31b13aa8132beadc47b7955f8b64cd1, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:24:33', '2026-10-30 11:24:33', NULL, '2026-10-30 13:24:33'),
('01a18cfd-5b72-716d-8a87-f0309f7be2d5', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xf7a77979b76518f7c570baf4ea8793fb5a011eb7bf6251a0815144bd67fd0468, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:26:16', '2026-10-30 11:26:16', '2026-10-30 11:36:49', '2026-10-30 13:26:16'),
('01a18d07-028f-723a-989d-4fb46723e2e8', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xeed20d3dd127d5c621997f12f7f45c743ea0f1fed31700b911d8088439582ce4, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:36:49', '2026-10-30 11:36:49', NULL, '2026-10-30 13:36:49'),
('01a18d07-495f-73dd-9eb0-f3a24df124be', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x29b8633cd9a321f93f053d8ae0363af1bef18637aa2522723ed60b435402ab6c, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:37:07', '2026-10-30 11:37:07', '2026-10-30 11:49:31', '2026-10-30 13:37:07'),
('01a18d12-a5d2-7026-9c5b-8f43c715ff28', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x49a6696dd4ebfc7bf9cac0a4d2c4df1e0f75fad21f34f7f03b72a7a27eb7047f, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:49:31', '2026-10-30 11:49:31', NULL, '2026-10-30 13:49:31'),
('01a18d13-19bf-7097-8a78-b031aa2a9b7c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xc3f8aa48b0bf7cd54f3791b3a329a8300b6b52e426efd5cbf46c2fd480ead5c2, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 11:50:01', '2026-10-30 11:50:01', '2026-10-30 12:39:07', '2026-10-30 13:50:01'),
('01a18d28-0241-7244-90f7-f9db99117426', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'storefront', 0x9fae64cf2daa93fd9d2d52bb59674ae10ba50acf0b43bc334f1976d66fb39982, 'node', '127.0.0.1', '2026-11-29 12:12:51', '2026-10-30 12:13:35', '2026-10-30 12:13:45', '2026-10-30 14:12:51'),
('01a18d3b-c73f-71d2-97ad-91ba48c15827', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', '01a18d28-023e-70b9-96b0-4ffd51bdcb5d', 'storefront', 0xe27e135c7be07a2e697462b790dc2e07ede984949dafe0b6213787d4c64c850f, 'node', '127.0.0.1', '2026-11-29 12:34:27', '2026-10-30 16:05:41', NULL, '2026-10-30 14:34:27'),
('01a18d40-0e71-733e-82ac-360b7cddac69', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x0e75254306ee6cedeaa942dc45029ee787ac9b2f2f1793e68cf1b3213273efb5, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 12:39:07', '2026-10-30 12:39:07', NULL, '2026-10-30 14:39:07'),
('01a18d40-816f-71ff-b9e3-a6b30f03f991', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x12984afcafef553929dc02878f54e5f8df804550190e897f2dacf453917fdda4, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 12:39:37', '2026-10-30 12:39:37', '2026-10-30 12:45:40', '2026-10-30 14:39:37'),
('01a18d49-b56d-7091-b65d-7b86b54592e4', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xf124a15a4fb158cb12e26b486527216d0090711873c5e09f7719340d5b57dcc6, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 12:49:40', '2026-10-30 12:49:40', '2026-10-30 13:06:51', '2026-10-30 14:49:40'),
('01a18d59-7203-733b-b244-877db247ca5c', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xaca41d02120e6ab4b6559a0b1363bc38833dffeab2ec47a6a3b1085fe0c78699, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 13:06:51', '2026-10-30 13:06:51', '2026-10-30 15:37:41', '2026-10-30 15:06:51'),
('01a18de3-8716-701d-afcb-fe8739d47e08', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x2f4080e6cc6ed50182dcbfda16e89ab2e95d159d4537887b0c84bd23cf7b17e8, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 15:37:41', '2026-10-30 15:37:41', NULL, '2026-10-30 17:37:41'),
('01a18dec-1d3a-7096-95a6-78fd3d3df098', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xccd6f540216380f443f03d804834a06a29b8e66752df9e52f1513f0ff5a7262f, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 15:47:03', '2026-10-30 15:47:03', '2026-10-30 16:03:42', '2026-10-30 17:47:03'),
('01a18dfb-5b19-7366-99a7-6846f254dc67', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0xb0d99c182d9673bd5410fe790dd47d9ce2f8db64204c487f9c1454fa02654fbe, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 16:03:42', '2026-10-30 16:03:42', NULL, '2026-10-30 18:03:42'),
('01a18dff-25d8-7214-9edf-2255c2fd702d', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin_refresh', 0x863b19b81bc2b992f24fe56597212d08a9ea0d519b479ea28476fbde598f547c, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:158.0) Gecko/20100101 Firefox/158.0', '127.0.0.1', '2026-11-29 16:07:51', '2026-10-30 16:07:51', NULL, '2026-10-30 18:07:51');

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
('01a18217-e50a-717d-bc97-b048eaa14a76', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', 'e94ac131-ed1a-4f68-9d2a-806384f4c38d', 'admin@example.test', 'email', 'password_reset', 0x6648473eed826ebc7e9dcb2e01861151748532032142b22d083c00618ee480b6, '2026-10-28 08:49:26', 0, 5, '2026-10-28 08:42:46', '2026-10-28 10:39:26'),
('01a18d27-071c-70d3-9a54-d86583f31ea1', '6b6641e4-180a-42dc-9ec8-488d802f7bf2', NULL, 'mr.hussein.ceng@gmail.com', 'email', 'verify_email', 0x94bf6186174f00e98b92e6897eb294732108339ed9a9884db8154ac510796b66, '2026-10-30 12:21:47', 0, 5, '2026-10-30 12:12:51', '2026-10-30 14:11:47');

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

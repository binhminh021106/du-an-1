-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 01, 2025 at 01:33 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `du_an1_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint NOT NULL,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` bigint NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `email`, `password`, `role_id`, `phone`, `avatar_url`, `status`, `created_at`, `updated_at`, `deleted_at`, `email_verified_at`, `address`) VALUES
(1, 'Nguyễn Trần Bình Minh', 'minhntbpk04039@gmail.com', '$2y$12$NJ4Tr/XgHBJGGnkp2PCaEOwBvboaecww7mJXXeBdgbHvRG7LhtbYW', 11, '0213456611', NULL, 'active', '2025-11-18 18:44:30', '2025-11-26 13:04:16', '2025-11-26 13:04:16', NULL, 'Dak Lak'),
(2, 'Nguyễn Trần Bình Minh', 'minhdzwama211@gmail.com', '$2y$12$IPHkYzicNVXPb/qCwxfiPujw60pA0iTYw7PTJ/JHdxzmwZBm4xMvO', 11, '0234567890', '/uploads/admins/1764342037_6929b9158b459_bae7ce9565106d4dfd549ab0a53fbe0a.jpg', 'active', '2025-11-18 18:48:45', '2025-11-28 23:05:40', NULL, NULL, 'daklak'),
(3, 'Lê Anh Vũ', 'anhvux123@gmail.com', '$2y$12$tZGXRwztbpA1aFFLuY4t0uzlM//rjDkBhvaXe83NSFSqJAW8IbldS', 11, '0312321321', NULL, 'active', '2025-11-18 20:05:29', '2025-11-20 04:40:45', '2025-11-20 04:40:45', NULL, 'Dak Lak'),
(4, 'hieuht', 'hieuhtpk04060@gmail.com', '$2y$12$ZJb/9HQxDSLvRGojujs1tu1gtdWK87awyZaxIy5LTO2S5PsgvhiUa', 11, '0377975276', '', 'active', '2025-11-20 03:39:14', '2025-11-20 03:42:18', '2025-11-20 03:42:18', NULL, 'daklak'),
(5, 'Hoàng Thanh Hiếu', 'hieuv12321@gmail.com', '$2y$12$.nYcGEWgdWQSCYWq.UiYS.VZ4gWvvs65MKvEZWkZJHR9D8dtuYM0.', 14, '0323123435', '', 'active', '2025-11-20 04:40:05', '2025-11-26 13:03:49', '2025-11-26 13:03:49', NULL, 'Dak Lak'),
(6, 'Hoàng Thanh Hiếu', 'issehieu1115@gmail.com', '$2y$12$azxXdJtEg.xBMzP7iRVCE.vqbDcWWunHUH8sMgwkwbt.Rpf3/LFye', 11, '0377975276', '/uploads/admins/1764338472_60919957_2372338166383793_5538345772547833856_n.jpg', 'active', '2025-11-28 07:01:12', '2025-11-28 12:49:13', NULL, NULL, 'Đắk Lắk - Vệt Nam'),
(7, 'TEST1', 'TEST1@gmail.com', '$2y$12$CTpsu01tMZa6QVH6DZXo1uBfonkot74Dfxsv/qZW6L44OI.DdtYvq', 14, '0123456789', '', 'active', '2025-11-30 11:46:54', '2025-11-30 11:46:54', NULL, NULL, 'aaaa');

-- --------------------------------------------------------

--
-- Table structure for table `attributes`
--

CREATE TABLE `attributes` (
  `id` bigint NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attributes`
--

INSERT INTO `attributes` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Test1', '2025-11-25 04:12:02', '2025-11-25 04:12:02'),
(2, 'test2', '2025-11-25 04:12:12', '2025-11-25 04:12:12'),
(3, 'aaa', '2025-11-25 05:41:46', '2025-11-25 05:41:46'),
(4, 'Màu', '2025-11-25 13:29:40', '2025-11-25 13:29:40'),
(5, 'Test 3', '2025-11-27 10:27:23', '2025-11-27 10:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `attribute_values`
--

CREATE TABLE `attribute_values` (
  `id` bigint NOT NULL,
  `attribute_id` bigint NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attribute_values`
--

INSERT INTO `attribute_values` (`id`, `attribute_id`, `value`, `created_at`, `updated_at`) VALUES
(28, 1, '123', '2025-11-25 13:28:32', '2025-11-25 13:28:32'),
(29, 4, 'Đen', '2025-11-25 13:30:18', '2025-11-25 13:30:18'),
(30, 4, 'Trắng', '2025-11-25 13:30:19', '2025-11-25 13:30:19'),
(31, 1, 'Tesst1', '2025-11-26 05:39:55', '2025-11-26 05:39:55'),
(32, 2, 'aaa', '2025-11-26 06:14:56', '2025-11-26 06:14:56'),
(33, 4, 'Cam', '2025-11-26 06:25:20', '2025-11-26 06:25:20'),
(34, 4, 'Vàng', '2025-11-26 06:25:21', '2025-11-26 06:25:21'),
(35, 2, 'Đỏ', '2025-11-26 06:51:21', '2025-11-26 06:51:21'),
(36, 1, '6GB', '2025-11-26 09:29:51', '2025-11-26 09:29:51'),
(37, 1, '5GB', '2025-11-26 09:29:52', '2025-11-26 09:29:52'),
(38, 1, '1GB', '2025-11-26 09:29:54', '2025-11-26 09:29:54'),
(39, 3, 'â', '2025-11-27 10:27:40', '2025-11-27 10:27:40'),
(40, 5, 'tt', '2025-11-28 14:31:42', '2025-11-28 14:31:42'),
(41, 2, '22', '2025-11-28 14:32:12', '2025-11-28 14:32:12');

-- --------------------------------------------------------

--
-- Table structure for table `brand_slides`
--

CREATE TABLE `brand_slides` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `link_url` varchar(255) NOT NULL,
  `order_number` int NOT NULL,
  `status` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `brand_slides`
--

INSERT INTO `brand_slides` (`id`, `name`, `image_url`, `link_url`, `order_number`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'mimi', '/storage/brands/K5jgAhycNjxwROewHjJDYTvto86zAin4f2tMvTdk.png', 'mimi/1', 1, 'published', '2025-11-19 20:14:32', '2025-11-19 20:15:43', '2025-11-19 20:15:43'),
(2, 'mimi', '/storage/brands/ah9ogEd6AEr6ZaD7eTYSjKHgTBrGu7YCtw9i4Q3O.jpg', 'mimi/1', 1, 'published', '2025-11-20 03:56:07', '2025-11-28 05:49:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint NOT NULL,
  `cart_id` bigint NOT NULL,
  `variant_id` bigint NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `order_number` int NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `order_number`, `status`, `created_at`, `updated_at`, `deleted_at`, `icon`) VALUES
(1, 'Laptop Gaming ASUS ROG', 'Hiệu năng mạnh mẽ với chip Core i9 và card RTX 4080.', 8, 'active', '2025-11-17 15:01:00', '2025-11-20 05:33:01', '2025-11-20 05:33:01', ''),
(42, 'Điện thoại di động', 'Các dòng smartphone mới nhất từ Apple, Samsung, Xiaomi...', 1, 'active', '2025-11-18 12:53:07', '2025-11-30 10:31:43', NULL, '<i class=\"fa-solid fa-mobile\"></i>'),
(43, 'Laptop & Macbook', 'Máy tính xách tay văn phòng, gaming, đồ họa.', 3, 'active', '2025-11-18 12:53:07', '2025-11-30 10:31:43', NULL, '<i class=\"fa-solid fa-gamepad\"></i>'),
(44, 'Thiết bị âm thanh', 'Tai nghe, loa bluetooth, dàn âm thanh chất lượng cao.', 2, 'active', '2025-11-18 12:53:07', '2025-11-30 10:31:43', NULL, '<i class=\"fa-solid fa-headphones\"></i>'),
(45, 'Máy tính bảng', 'iPad, Samsung Tab và các loại tablet khác.', 4, 'active', '2025-11-18 12:53:07', '2025-11-30 10:31:43', NULL, '<i class=\"fa-solid fa-computer\"></i>'),
(46, 'Phụ kiện công nghệ', 'Chuột, bàn phím, cáp sạc, ốp lưng...', 5, 'active', '2025-11-18 12:53:07', '2025-11-26 22:05:16', '2025-11-26 22:05:16', ''),
(47, 'Đồng hồ thông minh', 'Apple Watch, Galaxy Watch và thiết bị đeo tay.', 6, 'disabled', '2025-11-18 12:53:07', '2025-11-29 00:40:44', NULL, ''),
(48, 'PC & Màn hình', 'Máy tính để bàn lắp ráp và màn hình máy tính.', 7, 'active', '2025-11-18 12:53:07', '2025-11-26 08:29:40', '2025-11-26 08:29:40', ''),
(50, 'Đồ thể tha', 'hihihi', 11, 'active', '2025-11-19 09:13:38', '2025-11-19 09:14:06', '2025-11-19 09:14:06', 'hhihiih');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `content` text NOT NULL,
  `parent_id` bigint DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `product_id`, `user_id`, `content`, `parent_id`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(11, 90, 58, 'Sản phẩm nhìn ổn đấy, đang phân vân không biết có nên mua không.', NULL, 'rejected', '2025-11-26 19:17:05', '2025-11-26 12:37:22', NULL),
(12, 90, 59, 'Hôm trước mới xem ở cửa hàng, thấy đẹp phết đó mọi người.', NULL, 'rejected', '2025-11-26 19:17:05', '2025-11-27 06:07:25', NULL),
(13, 90, 60, 'Ai dùng rồi cho mình xin review thực tế với ạ!', NULL, 'approved', '2025-11-26 19:17:05', '2025-11-26 19:17:05', NULL),
(14, 90, 61, 'Shop cho hỏi còn màu khác không vậy?', NULL, 'approved', '2025-11-26 19:17:05', '2025-11-26 19:17:05', NULL),
(15, 90, 62, 'Đang chờ sale để quất một em về test thử.', NULL, 'rejected', '2025-11-26 19:17:05', '2025-11-28 04:46:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `min_spend` bigint NOT NULL DEFAULT '0',
  `type` varchar(20) NOT NULL,
  `value` bigint NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `usage_limit_per_user` int DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `name`, `code`, `min_spend`, `type`, `value`, `usage_limit`, `usage_count`, `usage_limit_per_user`, `expires_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(13, '123', '1234', 0, 'percent', 1234, 1234, 0, 1234, '2025-11-18 17:00:00', '2025-11-26 11:31:30', '2025-11-26 12:34:30', NULL),
(14, '123', '123456', 0, 'percent', 12, NULL, 0, NULL, '2025-12-02 17:00:00', '2025-11-26 11:31:52', '2025-11-28 13:35:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coupon_usage`
--

CREATE TABLE `coupon_usage` (
  `id` bigint NOT NULL,
  `coupon_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `order_id` bigint NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `image_product`
--

CREATE TABLE `image_product` (
  `id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `image_product`
--

INSERT INTO `image_product` (`id`, `product_id`, `image_url`, `created_at`, `updated_at`, `deleted_at`) VALUES
(11, 90, '/product/1764162361_6926fb39d06c1_chibi.jpeg', '2025-11-26 06:06:01', '2025-11-26 09:00:57', '2025-11-26 09:00:57'),
(12, 90, '/product/1764162362_6926fb3a486b7_blog.jpg', '2025-11-26 06:06:02', '2025-11-26 09:00:57', '2025-11-26 09:00:57'),
(13, 90, '/product/1764162362_6926fb3ac00e6_bannergundam.jpg', '2025-11-26 06:06:02', '2025-11-26 09:00:57', '2025-11-26 09:00:57'),
(14, 90, '/product/1764162367_6926fb3f45240_chibi.jpeg', '2025-11-26 06:06:07', '2025-11-26 06:20:37', '2025-11-26 06:20:37'),
(15, 90, '/product/1764162367_6926fb3fb88c5_blog.jpg', '2025-11-26 06:06:07', '2025-11-26 09:00:45', '2025-11-26 09:00:45'),
(16, 90, '/product/1764162368_6926fb4032abc_blog.jpg', '2025-11-26 06:06:08', '2025-11-26 09:00:45', '2025-11-26 09:00:45'),
(17, 90, '/product/1764162368_6926fb40a11be_chibi.jpeg', '2025-11-26 06:06:08', '2025-11-26 06:59:13', '2025-11-26 06:59:13'),
(18, 90, '/product/1764162369_6926fb411ca95_bannergundam.jpg', '2025-11-26 06:06:09', '2025-11-26 09:00:45', '2025-11-26 09:00:45'),
(19, 90, '/product/1764162370_6926fb422f2d6_bannergundam.jpg', '2025-11-26 06:06:10', '2025-11-26 08:32:23', '2025-11-26 08:32:23'),
(20, 90, '/product/1764162373_6926fb45d9a6d_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:13', '2025-11-26 09:00:57', '2025-11-26 09:00:57'),
(21, 90, '/product/1764162375_6926fb47617c1_blog.jpg', '2025-11-26 06:06:15', '2025-11-26 09:01:01', '2025-11-26 09:01:01'),
(22, 90, '/product/1764162377_6926fb491f010_chibi.jpeg', '2025-11-26 06:06:17', '2025-11-26 09:01:01', '2025-11-26 09:01:01'),
(23, 90, '/product/1764162378_6926fb4a16992_bannergundam.jpg', '2025-11-26 06:06:18', '2025-11-26 09:01:01', '2025-11-26 09:01:01'),
(24, 90, '/product/1764162379_6926fb4b38836_blog.jpg', '2025-11-26 06:06:19', '2025-11-26 06:59:31', '2025-11-26 06:59:31'),
(25, 90, '/product/1764162379_6926fb4ba9bba_chibi.jpeg', '2025-11-26 06:06:19', '2025-11-26 09:01:01', '2025-11-26 09:01:01'),
(26, 90, '/product/1764162381_6926fb4d7912d_bannergundam.jpg', '2025-11-26 06:06:21', '2025-11-26 08:32:06', '2025-11-26 08:32:06'),
(27, 90, '/product/1764162382_6926fb4e65680_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:22', '2025-11-26 08:32:13', '2025-11-26 08:32:13'),
(28, 90, '/product/1764162388_6926fb54e5e46_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:28', '2025-11-26 08:32:19', '2025-11-26 08:32:19'),
(29, 90, '/product/1764162389_6926fb555fc94_chibi.jpeg', '2025-11-26 06:06:29', '2025-11-26 09:44:42', '2025-11-26 09:44:42'),
(30, 90, '/product/1764162389_6926fb55d7df7_blog.jpg', '2025-11-26 06:06:29', '2025-11-26 09:07:53', '2025-11-26 09:07:53'),
(31, 90, '/product/1764162390_6926fb5658710_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:30', '2025-11-26 09:07:53', '2025-11-26 09:07:53'),
(32, 90, '/product/1764162390_6926fb56c55c3_bannergundam.jpg', '2025-11-26 06:06:30', '2025-11-26 08:32:04', '2025-11-26 08:32:04'),
(33, 90, '/product/1764162391_6926fb574ca90_chibi.jpeg', '2025-11-26 06:06:31', '2025-11-26 08:31:58', '2025-11-26 08:31:58'),
(34, 90, '/product/1764162391_6926fb57b4dc7_blog.jpg', '2025-11-26 06:06:31', '2025-11-26 08:32:01', '2025-11-26 08:32:01'),
(35, 90, '/product/1764162392_6926fb5830ce8_bannergundam.jpg', '2025-11-26 06:06:32', '2025-11-26 08:31:56', '2025-11-26 08:31:56'),
(36, 90, '/product/1764162392_6926fb589e0d3_blog.jpg', '2025-11-26 06:06:32', '2025-11-26 09:07:53', '2025-11-26 09:07:53'),
(37, 90, '/product/1764162393_6926fb5918b46_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:33', '2025-11-26 07:25:34', '2025-11-26 07:25:34'),
(38, 90, '/product/1764162393_6926fb598219c_chibi.jpeg', '2025-11-26 06:06:33', '2025-11-26 07:25:41', '2025-11-26 07:25:41'),
(39, 90, '/product/1764162397_6926fb5d16f2f_bannergundam.jpg', '2025-11-26 06:06:37', '2025-11-26 08:32:10', '2025-11-26 08:32:10'),
(40, 90, '/product/1764162406_6926fb6680499_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:46', '2025-11-26 06:08:35', '2025-11-26 06:08:35'),
(41, 90, '/product/1764162409_6926fb69e73a1_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:49', '2025-11-26 06:20:29', '2025-11-26 06:20:29'),
(42, 90, '/product/1764162411_6926fb6b2a231_ChatGPT Image 21_27_21 15 thg 10, 2025.png', '2025-11-26 06:06:51', '2025-11-26 06:08:45', '2025-11-26 06:08:45'),
(43, 90, '/product/1764173244_692725bcda03a_Tủ kính to.jpg', '2025-11-26 09:07:24', '2025-11-26 09:44:42', '2025-11-26 09:44:42'),
(44, 90, '/product/1764173245_692725bd6efd6_tudenled.jpg', '2025-11-26 09:07:25', '2025-11-26 09:44:42', '2025-11-26 09:44:42'),
(45, 90, '/product/1764173245_692725bddb279_tukinhfigure.jpg', '2025-11-26 09:07:25', '2025-11-26 09:07:41', '2025-11-26 09:07:41'),
(46, 90, '/product/1764174851_69272c034280a_logo.png', '2025-11-26 09:34:11', '2025-11-26 09:34:22', '2025-11-26 09:34:22'),
(47, 90, '/product/1764264456_69288a08cf16f_tukinhto.jpg', '2025-11-27 10:27:36', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(48, 90, '/product/1764264457_69288a0962295_tunho.jpg', '2025-11-27 10:27:37', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(49, 90, '/product/1764264457_69288a09e10db_tudenled.jpg', '2025-11-27 10:27:37', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(50, 90, '/product/1764264458_69288a0a7b54c_tukinhfigure.jpg', '2025-11-27 10:27:38', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(51, 91, '/product/1764336287_6929a29f3a514_blog.jpg', '2025-11-28 06:24:47', '2025-11-28 07:16:05', '2025-11-28 07:16:05'),
(52, 91, '/product/1764336287_6929a29f90e25_bannergundam.jpg', '2025-11-28 06:24:47', '2025-11-28 07:16:47', '2025-11-28 07:16:47'),
(53, 93, '/product/prod_1764341062_6929b5464129e.png', '2025-11-28 07:44:22', '2025-11-28 07:44:47', '2025-11-28 07:44:47'),
(54, 93, '/product/prod_1764341062_6929b546be3cf.jpeg', '2025-11-28 07:44:22', '2025-11-28 07:44:47', '2025-11-28 07:44:47'),
(55, 95, '/product/prod_1764341193_6929b5c9c5eca.jpg', '2025-11-28 07:46:33', '2025-11-28 07:46:45', '2025-11-28 07:46:45'),
(56, 95, '/product/prod_1764341318_6929b646e71dd.jpg', '2025-11-28 07:48:38', '2025-11-28 07:49:04', '2025-11-28 07:49:04'),
(57, 95, '/product/prod_1764341319_6929b647521b1.png', '2025-11-28 07:48:39', '2025-11-28 07:49:04', '2025-11-28 07:49:04'),
(58, 95, '/product/prod_1764341377_6929b681f0972.jpg', '2025-11-28 07:49:37', '2025-11-28 07:49:50', '2025-11-28 07:49:50'),
(59, 95, '/product/prod_1764341378_6929b6825c297.jpg', '2025-11-28 07:49:38', '2025-11-28 07:49:50', '2025-11-28 07:49:50'),
(60, 96, '/product/prod_1764341620_6929b7746a6e3.jpeg', '2025-11-28 07:53:40', '2025-11-28 07:53:54', '2025-11-28 07:53:54'),
(61, 96, '/product/prod_1764341620_6929b774f003b.jpeg', '2025-11-28 07:53:40', '2025-11-28 07:53:54', '2025-11-28 07:53:54'),
(62, 97, '/product/prod_1764364451_692a10a3e236a.jpg', '2025-11-28 14:14:11', '2025-11-28 14:14:11', NULL),
(63, 97, '/product/prod_1764364452_692a10a44adf2.jpg', '2025-11-28 14:14:12', '2025-11-28 14:14:12', NULL),
(64, 97, '/product/prod_1764364452_692a10a4a4637.jpg', '2025-11-28 14:14:12', '2025-11-28 14:14:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2025_11_14_010848_create_sessions_table', 1),
(2, '2025_11_13_082251_create_test_table', 2),
(3, '2025_11_16_075325_create_personal_access_tokens_table', 2),
(4, '2025_11_18_140020_create_cache_table', 3);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` bigint NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `author_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `excerpt`, `content`, `image_url`, `slug`, `status`, `created_at`, `updated_at`, `deleted_at`, `author_name`) VALUES
(12, 'aaaaaaaaaaaaa', 'aaaaaaaaaaa', '<p>aaaaaaaaa</p>', '/storage/news/1764355981_detrungbaym.jpg', 'aaaaaaaaaaaaa', 'published', '2025-11-28 11:53:01', '2025-11-28 13:19:01', NULL, ''),
(13, 'aaaaaaaaa', 'aaaaaa', '<p>aaaa</p>', '/storage/news/1764356831_blog.jpg', 'aaaaaaaaa', 'draft', '2025-11-28 12:07:11', '2025-11-28 13:20:31', NULL, ''),
(15, 'aaaaaaaa', 'aaaaaaaa', '<p>aaaaaaaaaaa</p>', NULL, 'aaaaaaaa', 'published', '2025-11-28 12:42:59', '2025-11-28 21:51:13', '2025-11-28 21:51:13', 'aaaaaaaaa');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `id` bigint NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(150) NOT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `subtotal_amount` bigint NOT NULL,
  `shipping_fee` bigint NOT NULL,
  `discount_amount` bigint DEFAULT NULL,
  `total_amount` bigint NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `payment_status` varchar(50) NOT NULL DEFAULT 'pending',
  `coupon_id` bigint DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`id`, `user_id`, `customer_name`, `customer_phone`, `customer_email`, `shipping_address`, `status`, `subtotal_amount`, `shipping_fee`, `discount_amount`, `total_amount`, `payment_method`, `payment_status`, `coupon_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 58, 'Nguyễn Văn A', '0123456789', 'vana@example.com', 'Hà Nội', 'pending', 1500000, 30000, 0, 1530000, 'COD', 'pending', NULL, '2025-11-27 11:42:46', '2025-11-27 11:42:46', NULL),
(2, 58, 'Trần Thị B', '0987654321', 'thib@example.com', 'Hồ Chí Minh', 'pending', 2500000, 30000, 0, 2530000, 'COD', 'pending', NULL, '2025-11-27 11:42:46', '2025-11-27 11:42:46', NULL),
(3, 59, 'Lê Văn C', '0378899000', 'vanc@example.com', 'Đà Nẵng', 'pending', 3200000, 30000, 0, 3230000, 'Chuyển khoản', 'pending', NULL, '2025-11-27 11:42:46', '2025-11-27 11:42:46', NULL),
(4, 60, 'Phạm Thị D', '0901122334', 'thid@example.com', 'Cần Thơ', 'pending', 1800000, 30000, 0, 1830000, 'COD', 'pending', NULL, '2025-11-27 11:42:46', '2025-11-27 11:42:46', NULL),
(5, 62, 'Hoàng Văn E', '0912233445', 'vane@example.com', 'Hải Phòng', 'pending', 4200000, 30000, 0, 4230000, 'Chuyển khoản', 'pending', NULL, '2025-11-27 11:42:46', '2025-11-27 11:42:46', NULL),
(8, 58, 'Nguyễn Minh H', '0911223344', 'minhh@example.com', 'Đà Nẵng', 'returning', 2400000, 30000, 0, 2430000, 'COD', 'paid', NULL, '2025-11-27 12:46:36', '2025-11-27 12:46:36', NULL),
(9, 62, 'Phạm Thu I', '0905566778', 'thui@example.com', 'Hải Phòng', 'returned', 3100000, 30000, 0, 3130000, 'Chuyển khoản', 'refunded', NULL, '2025-11-27 12:46:36', '2025-11-27 12:46:36', NULL),
(10, 58, 'Lê Minh J', '0901122112', 'minhj@example.com', 'Hà Nội', 'approved', 2800000, 30000, 0, 2830000, 'COD', 'pending', NULL, '2025-11-27 12:48:03', '2025-11-27 12:48:03', NULL),
(11, 60, 'Trần Quốc K', '0933556677', 'quock@example.com', 'Cần Thơ', 'shipping', 4500000, 30000, 0, 4530000, 'Chuyển khoản', 'paid', NULL, '2025-11-27 12:48:03', '2025-11-27 12:48:03', NULL),
(12, 62, 'Phạm Gia L', '0912333444', 'gial@example.com', 'Đà Lạt', 'cancelled', 1900000, 30000, 0, 1930000, 'COD', 'cancelled', NULL, '2025-11-27 12:48:03', '2025-11-27 12:48:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint NOT NULL,
  `order_id` bigint NOT NULL,
  `variant_id` bigint NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` bigint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint NOT NULL,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `group_name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `slug`, `name`, `group_name`, `created_at`, `updated_at`) VALUES
(1, 'dashboard.access', 'Truy cập Dashboard', 'Tổng quan', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(2, 'admins.access', 'Quản lý Tài khoản nội bộ', 'Người dùng', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(3, 'users.access', 'Quản lý Khách hàng', 'Người dùng', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(4, 'categories.access', 'Quản lý Danh mục', 'Sản phẩm', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(5, 'products.access', 'Quản lý Sản phẩm', 'Sản phẩm', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(6, 'orders.access', 'Quản lý Đơn hàng', 'Bán hàng', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(7, 'news.access', 'Quản lý Tin tức', 'Nội dung', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(8, 'comments.access', 'Quản lý Bình luận', 'Tương tác', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(9, 'reviews.access', 'Quản lý Đánh giá', 'Tương tác', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(10, 'slides.access', 'Quản lý Slide & Banner', 'Giao diện', '2025-11-30 18:03:10', '2025-11-30 18:03:10'),
(11, 'coupons.access', 'Quản lý Mã giảm giá', 'Bán hàng', '2025-11-30 18:03:10', '2025-11-30 18:03:10');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 58, 'client-token', 'c768a764513cd3d920617911afb532218bf6f70b0d23e2a331006abe9868fa9d', '[\"*\"]', NULL, NULL, '2025-11-18 08:16:57', '2025-11-18 08:16:57'),
(2, 'App\\Models\\User', 58, 'client-token', '409ada0118feee684626545ac9a109532732702a002eaab01d54e95860eaac3d', '[\"*\"]', NULL, NULL, '2025-11-18 08:24:04', '2025-11-18 08:24:04'),
(3, 'App\\Models\\User', 58, 'client-token', '179fb534718fbeb849631216c1ca496e49674ed8eb7dc5909bf0e01bd162ed84', '[\"*\"]', '2025-11-19 05:40:23', NULL, '2025-11-18 08:26:39', '2025-11-19 05:40:23'),
(4, 'App\\Models\\Admin', 2, 'admin-token', '70563a529c11a51eeee9bd68d48dc82cd6fd77d6079831c972931f61f9ec8ddf', '[\"*\"]', NULL, NULL, '2025-11-18 19:10:44', '2025-11-18 19:10:44'),
(5, 'App\\Models\\Admin', 2, 'admin-token', '4975fc389d778ff6c24d86d64a4f8ff99f1c688245fdc81b1f15f3e50a063358', '[\"*\"]', NULL, NULL, '2025-11-18 19:16:08', '2025-11-18 19:16:08'),
(6, 'App\\Models\\Admin', 2, 'admin-token', '5d006f89b04502c80dc7cce0fc27f7945cbd12956541be51eb7f6cf199eaec16', '[\"*\"]', NULL, NULL, '2025-11-18 19:20:47', '2025-11-18 19:20:47'),
(7, 'App\\Models\\Admin', 2, 'admin-token', 'ce69bc38d28ae034292333e59cb82b83a891996b31e2c89ece72a634622de6df', '[\"*\"]', NULL, NULL, '2025-11-18 19:23:40', '2025-11-18 19:23:40'),
(8, 'App\\Models\\Admin', 2, 'admin-token', '647ddfb20def9e599e7352567af2feeda09024ffddba8f38890c75eb5b3756f5', '[\"*\"]', NULL, NULL, '2025-11-18 19:51:29', '2025-11-18 19:51:29'),
(9, 'App\\Models\\Admin', 2, 'admin-token', 'ea9e7dad2f26c3629d1256f0256f5920f2accca0729448f56aa69f125af67df4', '[\"*\"]', NULL, NULL, '2025-11-18 19:51:34', '2025-11-18 19:51:34'),
(10, 'App\\Models\\Admin', 2, 'admin-token', '2a3ebad3a40a7d501d10f5b958fd5a4cee1573c284766c644bdbe3732460d2bc', '[\"*\"]', NULL, NULL, '2025-11-18 19:51:44', '2025-11-18 19:51:44'),
(11, 'App\\Models\\Admin', 2, 'admin-token', 'fb25869b61b1310661b35360b73778614f0def0da18b24ce549d48f1f30bd730', '[\"*\"]', NULL, NULL, '2025-11-18 20:01:33', '2025-11-18 20:01:33'),
(12, 'App\\Models\\Admin', 3, 'admin-token', '049ed94615dc3f20e6d866dbec735140634d3c8f7a3cc26aec9c8be6b2ade46e', '[\"*\"]', NULL, NULL, '2025-11-18 20:05:44', '2025-11-18 20:05:44'),
(13, 'App\\Models\\Admin', 2, 'admin-token', '07074acfd16a873be4e6dae545b6a75e9fdf2decc9d7a949fec6022ccde7129a', '[\"*\"]', NULL, NULL, '2025-11-18 20:11:31', '2025-11-18 20:11:31'),
(14, 'App\\Models\\Admin', 3, 'admin-token', '2771cf05a238cd17968485d2f80361bc4186b071740cbaef94de9fd4bb4471eb', '[\"*\"]', NULL, NULL, '2025-11-18 20:31:52', '2025-11-18 20:31:52'),
(15, 'App\\Models\\Admin', 2, 'admin-token', '2e4f4be960506297c91eb1d2a9e0c1094bc64f73f139eb88a5e63d519c6fe056', '[\"*\"]', '2025-11-19 05:41:03', NULL, '2025-11-18 20:53:39', '2025-11-19 05:41:03'),
(16, 'App\\Models\\Admin', 2, 'admin-token', 'e698f6f2bdf3721839bd1acfd06f310110d003af88f2c68eeac3fd51a519add5', '[\"*\"]', '2025-11-19 22:18:28', NULL, '2025-11-19 05:41:29', '2025-11-19 22:18:28'),
(17, 'App\\Models\\Admin', 2, 'admin-token', 'b0e34740563dca35cef342bc9a0ad494c88f7fb1f403bb7a170f6a05cafc840e', '[\"*\"]', '2025-11-19 06:15:18', NULL, '2025-11-19 05:51:23', '2025-11-19 06:15:18'),
(18, 'App\\Models\\Admin', 2, 'admin-token', '4ead25d766bb82454bdfbce53bb33fdd942af5640cf7e926eb0aa63058ea9db8', '[\"*\"]', NULL, NULL, '2025-11-19 05:57:01', '2025-11-19 05:57:01'),
(19, 'App\\Models\\Admin', 2, 'admin-token', '54e142a1c1891b93f1793aca173e962c4502d400670f514038d045e14102abd0', '[\"*\"]', NULL, NULL, '2025-11-19 06:14:14', '2025-11-19 06:14:14'),
(20, 'App\\Models\\User', 62, 'client-token', 'b2587da3322f4c6fef01358e9a74ea1a86d57253ae516c2dc47d0fd0d9a610ab', '[\"*\"]', '2025-11-28 23:00:58', NULL, '2025-11-19 22:35:13', '2025-11-28 23:00:58'),
(21, 'App\\Models\\Admin', 2, 'admin-token', '067d3273e04d36d567034f8efcc3e977a982f151f1053f633f942da7716df181', '[\"*\"]', '2025-11-19 22:44:48', NULL, '2025-11-19 22:37:07', '2025-11-19 22:44:48'),
(22, 'App\\Models\\Admin', 2, 'admin-token', '189898dbc16da90e13e9ad84b61147e081502f6a1d979d89c9b2b178473bf24d', '[\"*\"]', '2025-11-28 07:01:30', NULL, '2025-11-19 22:54:08', '2025-11-28 07:01:30'),
(23, 'App\\Models\\Admin', 6, 'admin-token', '7df6c7ec4d2b3b02853deb08cce7d66b388ff2d2fd223955bfc51847fa36c7f3', '[\"*\"]', '2025-11-28 11:58:07', NULL, '2025-11-28 07:02:14', '2025-11-28 11:58:07'),
(24, 'App\\Models\\Admin', 6, 'admin-token', '689cb7a53b1e78ed0b24dd3248349dd6c948fbb497fef6deb58b098b8eb063b3', '[\"*\"]', NULL, NULL, '2025-11-28 11:58:13', '2025-11-28 11:58:13'),
(25, 'App\\Models\\Admin', 6, 'admin-token', 'efcbe8169a176a43489c700494ebdf83b789b4511f5aa0a8b1d9c5d5ccefc89d', '[\"*\"]', NULL, NULL, '2025-11-28 11:58:53', '2025-11-28 11:58:53'),
(26, 'App\\Models\\Admin', 6, 'admin-token', 'e9f442a67d521d3749fbed3cfc4788227fe528190402761456f477c714311c7d', '[\"*\"]', '2025-11-28 12:43:00', NULL, '2025-11-28 11:59:32', '2025-11-28 12:43:00'),
(27, 'App\\Models\\Admin', 6, 'admin-token', '3b24e8da2884b019769480b378e0e9d4b183e25d0b4187cb014fe7df4a967f14', '[\"*\"]', '2025-11-28 12:46:43', NULL, '2025-11-28 12:45:50', '2025-11-28 12:46:43'),
(28, 'App\\Models\\Admin', 6, 'admin-token', '732feffdfa84217f7320b05989461baa4ccc48176194d56a18ba11c389d40f4d', '[\"*\"]', '2025-11-28 12:49:14', NULL, '2025-11-28 12:47:52', '2025-11-28 12:49:14'),
(29, 'App\\Models\\Admin', 6, 'admin-token', 'f9ef1c37c517ec76bd69ad33cd107d38cf67346feb1aaa7e068139d9b3bfd22d', '[\"*\"]', '2025-11-28 12:55:18', NULL, '2025-11-28 12:49:21', '2025-11-28 12:55:18'),
(30, 'App\\Models\\Admin', 6, 'admin-token', 'a1345b34399c09e6227add00e4f5f6a6bc7d7cc96ca4468ca9daa6f14903331b', '[\"*\"]', '2025-11-30 11:50:11', NULL, '2025-11-28 12:55:23', '2025-11-30 11:50:11'),
(31, 'App\\Models\\User', 63, 'client-token', '1d2e1c396514ea4145b51999448c78a73d364ff72a306e405f1b8f5ab8c64679', '[\"*\"]', NULL, NULL, '2025-11-29 00:38:02', '2025-11-29 00:38:02'),
(32, 'App\\Models\\Admin', 7, 'admin-token', 'f2a1ebd05153d57e44b22ed56213b96ad4f8c77e3a72f6326051077bf4da62ca', '[\"*\"]', '2025-11-30 11:51:10', NULL, '2025-11-30 11:51:05', '2025-11-30 11:51:10'),
(33, 'App\\Models\\Admin', 6, 'admin-token', '041b91e0dc9b1ef2cd4f0b79068b9a17790915b78dafe017e6a67ce523e8660a', '[\"*\"]', '2025-11-30 12:22:06', NULL, '2025-11-30 11:51:28', '2025-11-30 12:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` bigint NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` bigint NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `sold_count` bigint DEFAULT '0',
  `favorite_count` bigint DEFAULT '0',
  `review_count` bigint DEFAULT '0',
  `average_rating` decimal(3,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `description` text NOT NULL,
  `status` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `name`, `category_id`, `thumbnail_url`, `sold_count`, `favorite_count`, `review_count`, `average_rating`, `created_at`, `updated_at`, `deleted_at`, `description`, `status`) VALUES
(82, 'abc', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 05:51:19', '2025-11-25 06:34:08', '2025-11-25 06:34:08', 'aaa', 'active'),
(83, 'aaaaaa', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:03:09', '2025-11-25 06:34:14', '2025-11-25 06:34:14', 'aaaaa', 'active'),
(84, 'aaaaaa', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:06:08', '2025-11-25 06:34:20', '2025-11-25 06:34:20', 'aaaaa', 'active'),
(85, 'aaaaaaaaa', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:06:34', '2025-11-25 07:02:40', '2025-11-25 07:02:40', 'aaaa', 'active'),
(86, 'aaaaaaaaa', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:07:02', '2025-11-25 13:32:48', '2025-11-25 13:32:48', 'aaaa', 'active'),
(87, 'aaaaaaaa', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:07:28', '2025-11-25 13:32:53', '2025-11-25 13:32:53', 'aaaa', 'active'),
(88, 'test 1', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 06:26:00', '2025-11-25 13:32:57', '2025-11-25 13:32:57', 'test 1', 'active'),
(89, 'iphone', 42, 'https://placehold.co/150x150?text=No+Img', 0, 0, 0, 0.00, '2025-11-25 13:29:27', '2025-11-25 13:33:02', '2025-11-25 13:33:02', 'Điện thoại', 'active'),
(90, 'Dien thoai', 42, '/thumbnail/1764264454_mohinhlf.jpg', 0, 0, 0, 0.00, '2025-11-26 05:39:07', '2025-11-28 07:16:43', '2025-11-28 07:16:43', 'Dien thoai', 'active'),
(91, 'Laptop', 43, '/thumbnail/1764336204_bannergundam.jpg', 0, 0, 0, 0.00, '2025-11-28 06:23:24', '2025-11-28 07:16:47', '2025-11-28 07:16:47', 'Laptop', 'active'),
(92, 'TEST', 42, '/thumbnail/thumb_1764340882_6929b492a8efd.jpg', 0, 0, 0, 0.00, '2025-11-28 07:41:22', '2025-11-28 07:44:51', '2025-11-28 07:44:51', 'TEST', 'inactive'),
(93, 'TEST', 42, '/thumbnail/1764340934_bae7ce9565106d4dfd549ab0a53fbe0a.jpg', 0, 0, 0, 0.00, '2025-11-28 07:42:14', '2025-11-28 07:44:47', '2025-11-28 07:44:47', 'TEST', 'inactive'),
(94, 'aa', 42, '/thumbnail/1764340958_snapedit_1749714609788.png', 0, 0, 0, 0.00, '2025-11-28 07:42:38', '2025-11-28 07:44:02', '2025-11-28 07:44:02', 'aaaa', 'inactive'),
(95, 'aaaaaa', 42, '/thumbnail/thumb_1764341163_6929b5aba1da1.jpg', 0, 0, 0, 0.00, '2025-11-28 07:46:03', '2025-11-28 07:49:50', '2025-11-28 07:49:50', 'aaaaa', 'inactive'),
(96, 'aaaa', 42, '/thumbnail/thumb_1764341607_6929b76741e21.jpeg', 0, 0, 0, 0.00, '2025-11-28 07:53:27', '2025-11-28 07:53:54', '2025-11-28 07:53:54', 'aaaaaa', 'inactive'),
(97, 'Điện thoại', 42, '/thumbnail/thumb_1764363062_692a0b3605f56.jpg', 0, 0, 0, 0.00, '2025-11-28 13:51:02', '2025-11-28 22:00:14', NULL, 'aaaaaaaa', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `rating` int NOT NULL,
  `content` text,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `content`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(30, 90, 58, 3, 'Sản phẩm dùng rất tốt, chất lượng vượt mong đợi. Đóng gói kỹ và giao nhanh.', 'approved', '2025-11-24 19:05:52', '2025-11-26 12:11:34', NULL),
(31, 90, 62, 5, 'Máy đẹp như mô tả, chạy mượt và pin rất trâu. Shop hỗ trợ nhiệt tình!', 'approved', '2025-11-26 19:05:52', '2025-11-26 12:11:33', NULL),
(32, 90, 58, 5, 'Sản phẩm quá chất lượng, dùng rất thích. Mượt và pin trâu!', 'approved', '2025-11-26 19:16:12', '2025-11-26 19:16:12', NULL),
(33, 90, 59, 5, 'Giao hàng nhanh, đóng gói chắc chắn. Mở hộp ra là ưng luôn.', 'approved', '2025-11-26 19:16:12', '2025-11-26 19:16:12', NULL),
(34, 90, 60, 5, 'Máy đẹp hơn mong đợi, chạy rất ổn định và không nóng.', 'approved', '2025-11-26 19:16:12', '2025-11-26 19:16:12', NULL),
(35, 90, 61, 5, 'Shop tư vấn nhiệt tình, sản phẩm đúng mô tả, giá hợp lý.', 'rejected', '2025-11-26 19:16:12', '2025-11-26 12:25:08', NULL),
(36, 90, 62, 5, 'Đáng tiền! Tính năng tốt, camera đẹp, pin dùng cả ngày.', 'approved', '2025-11-26 19:16:12', '2025-11-26 19:16:12', NULL),
(37, 90, 58, 5, 'Sản phẩm quá chất lượng, dùng rất thích. Mượt và pin trâu!', 'approved', '2025-11-26 19:26:13', '2025-11-26 19:26:13', NULL),
(38, 90, 59, 5, 'Giao hàng nhanh, đóng gói chắc chắn. Mở hộp ra là ưng luôn.', 'approved', '2025-11-26 19:26:13', '2025-11-26 19:26:13', NULL),
(39, 90, 60, 5, 'Máy đẹp hơn mong đợi, chạy rất ổn định và không nóng.', 'rejected', '2025-11-26 19:26:13', '2025-11-27 06:07:33', NULL),
(40, 90, 61, 5, 'Shop tư vấn nhiệt tình, sản phẩm đúng mô tả, giá hợp lý.', 'approved', '2025-11-26 19:26:13', '2025-11-26 19:26:13', NULL),
(41, 90, 62, 5, 'Đáng tiền! Tính năng tốt, camera đẹp, pin dùng cả ngày.', 'approved', '2025-11-26 19:26:13', '2025-11-26 19:26:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `id` bigint NOT NULL,
  `value` varchar(50) NOT NULL,
  `label` varchar(70) NOT NULL,
  `badgeClass` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`id`, `value`, `label`, `badgeClass`, `created_at`, `updated_at`, `deleted_at`) VALUES
(11, 'admin', 'Quản trị viên (Super Admin)', 'text-bg-danger', '2025-11-19 01:21:33', '2025-11-30 11:38:13', NULL),
(12, 'staff', 'Nhân viên', 'text-bg-primary', '2025-11-19 01:21:33', '2025-11-20 03:49:59', NULL),
(13, 'blogger', 'Blogger', 'text-bg-dark', '2025-11-19 01:21:33', '2025-11-20 03:50:13', NULL),
(14, 'baove', 'Bảo vệ', 'text-bg-warning', '2025-11-20 03:49:01', '2025-11-20 03:49:01', NULL),
(15, 'qc', 'Kiểm duyệt', 'text-bg-warning', '2025-11-28 07:07:28', '2025-11-28 07:07:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` bigint NOT NULL,
  `permission_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(11, 1),
(11, 2),
(11, 3),
(11, 4),
(11, 5),
(11, 6),
(11, 7),
(11, 8),
(11, 9),
(11, 10),
(11, 11);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('EnUbykrxJ6uMiH9WP7f91cqpqvD8vliObSXBbi4Z', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiUDNrNmtyWVcyRXVHNk9HQlJpMkhMeFpiTmlBY1k1eXNXM2hMaFlFSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzk6Imh0dHBzOi8vbGFyYXZlbC1iYWNrZW5kLmFwcC90ZXN0P2FnZT0yMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo0OiJuYW1lIjtzOjEwOiJCw6xuaCBNaW5oIjt9', 1763087773),
('sdtBWlGe66BMeV9g5y2oWLUFJL3Iqm4HZokhAppz', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTGVzNGZ1SzhKS1dmeWI0OFBLSlRXZkhoMkMyNkVMbnFoUnhWazNTZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHBzOi8vbGFyYXZlbC1iYWNrZW5kLmFwcCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1763114190),
('XSz4MTk9gopmo1p0gqC37akzDErrb21iL60o67C7', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSWZ5bTFSZWtOUmpwMExhclQwTWpVdHprSVNYVFU1VzlndDBBVExNUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHBzOi8vbGFyYXZlbC1iYWNrZW5kLmFwcCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1763253380);

-- --------------------------------------------------------

--
-- Table structure for table `slides`
--

CREATE TABLE `slides` (
  `id` bigint NOT NULL,
  `title` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `order_number` int NOT NULL DEFAULT '0',
  `status` varchar(50) NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` datetime DEFAULT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `slides`
--

INSERT INTO `slides` (`id`, `title`, `image_url`, `link_url`, `order_number`, `status`, `created_at`, `updated_at`, `deleted_at`, `description`) VALUES
(11, 'Sức Mạnh Mới - MacBook Pro M3 Pro', 'https://shopdunk.com/images/uploaded/th%C3%B4ng%20s%E1%BB%91%20macbook%20pro%20m3/thong-so-macbook-pro-m3%20(2).jpg', 'hinhdep/1', 1, 'active', '2025-11-17 15:11:07', '2025-11-19 19:17:56', '2025-11-20 02:17:56', ''),
(12, 'Galaxy S24 Ultra - Kỷ Nguyên AI Di Động', 'https://images.samsung.com/vn/smartphones/galaxy-s24-ultra/images/galaxy-s24-ultra-highlights-color-titanium-blue-back-mo.jpg?imbypass=true', 'hinhdep/2', 2, 'active', '2025-11-17 15:12:10', '2025-11-19 19:18:00', '2025-11-20 02:18:00', ''),
(13, 'GIẢM ĐẾN 50% - Tai Nghe & Gaming Gear', 'https://product.hstatic.net/200000680839/product/combo_68471b7331b64bfdb272290a72d22805_1024x1024.jpg', 'hinhdep/3', 3, 'active', '2025-11-17 15:12:43', '2025-11-19 19:18:05', '2025-11-20 02:18:05', ''),
(14, 'hihihi', '/storage/slides/pH7Sb3uh1Z9Fuw0ak8hnUVnI4icgNxZIdUifke81.webp', 'hinhdep/2', 4, 'published', '2025-11-19 19:14:29', '2025-11-19 19:15:35', '2025-11-20 02:15:35', 'hihihi'),
(15, 'Banner giới thiệu đồ mới', '/storage/slides/hXGjnPe1OrdIIiNkPrF8WONBp5adu95hm6RQ2JJ2.png', 'hinhdep/1', 1, 'published', '2025-11-19 19:18:42', '2025-11-30 10:42:29', NULL, 'Đồ nam chất'),
(16, 'đồ pickerball', '/storage/slides/V6WKez8gZybvZqvTlyO0mNDyEOrInY8p0uBt2dCq.jpg', 'hinhdep/2', 2, 'published', '2025-11-19 19:39:55', '2025-11-30 10:42:03', NULL, 'hihi'),
(17, 'hịhi', '/storage/slides/2ZjT2ucC0q2jmdRwLQ4DYjHdpjCrPmrz7UlgaYLS.jpg', 'hy', 3, 'draft', '2025-11-19 23:51:43', '2025-11-19 23:56:02', '2025-11-20 06:56:02', 'th');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint NOT NULL,
  `fullName` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullName`, `email`, `phone`, `password`, `avatar_url`, `status`, `created_at`, `updated_at`, `deleted_at`, `remember_token`, `email_verified_at`, `birthday`, `sex`, `google_id`) VALUES
(58, 'Nguyễn Trần Bình Minh', 'minhntbpk04039@gmail.com', '0358090730', '$2y$12$6h9S.iyL0EX8MJNRFCwcSeDTiQEqR3A4SxT72tPQs4cB2mZCXLBNO', 'http://127.0.0.1:8000/storage/avatars/pLpCZyhxaehXAtMwOrBFjajpo2Oz0f2L4atD3mHg.png', 'active', '2025-11-18 07:48:52', '2025-11-28 23:00:42', NULL, NULL, NULL, NULL, '', NULL),
(59, 'minhdeony12334', 'minhquadz1@gmail.com', '0123575643', '$2y$12$mOlbIJ9aHE.xYZcaHENJ0OLBnAR7Ry6V3aMqQSUJY8w6gycug5JDK', NULL, 'active', '2025-11-19 07:02:34', '2025-11-19 07:12:38', '2025-11-19 07:12:38', NULL, NULL, NULL, '', NULL),
(60, 'hieuht', 'test2@gmail.com', '0123456789', '$2y$12$rn9fiILfM//6hRYJZxF8T.CwPrvo4axr9VQVtW.tXc.o0kfa7Sy.C', NULL, 'active', '2025-11-19 08:19:36', '2025-11-19 23:29:58', '2025-11-19 23:29:58', NULL, NULL, NULL, '', NULL),
(61, 'NGUYEN TRAN BINH MINH', 'test3@gmail.com', '0123434323', '$2y$12$oc2ilUaZEQztxhTEAKPSTODUAy7Ub5IpKYJFVaIvKMJ07OGd.oKgu', NULL, 'active', '2025-11-19 22:16:33', '2025-11-19 22:16:38', '2025-11-19 22:16:38', NULL, NULL, NULL, '', NULL),
(62, 'Hoàng Thanh Hiếu', 'hieuv12321@gmail.com', '0377975276', '$2y$12$BYR5.zN3q87inJIBfohuI.JQfHlxqrgHjD12n6oYPHVuQs41ZNitK', 'http://127.0.0.1:8000/storage/avatars/UvhJ8Ofe4JIfgUZaVhvHQpGEs7CSyC5ZVuVXCsCB.jpg', 'active', '2025-11-19 22:35:08', '2025-11-28 07:12:33', NULL, NULL, NULL, NULL, '', NULL),
(63, 'Hoàng Thanh Hiếu', 'test2222@gmail.com', '0323123435', '$2y$12$kUAdXpm1hw95Y4wuFWcfru6YQUdW1ZWzpDQ4oQdX4bSWXOooNA.Y2', NULL, 'active', '2025-11-29 00:37:51', '2025-11-29 00:37:51', NULL, NULL, NULL, NULL, 'male', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `ward` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `variants`
--

CREATE TABLE `variants` (
  `id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `price` bigint NOT NULL,
  `original_price` bigint NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT (now()),
  `updated_at` timestamp NOT NULL DEFAULT (now()),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `variants`
--

INSERT INTO `variants` (`id`, `product_id`, `price`, `original_price`, `stock`, `created_at`, `updated_at`, `deleted_at`) VALUES
(112, 88, 1111, 1111, 1111, '2025-11-25 07:15:11', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(113, 88, 111, 111, 1111, '2025-11-25 07:15:12', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(114, 88, 123, 123, 123, '2025-11-25 07:21:35', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(115, 88, 1123, 123, 123, '2025-11-25 07:21:36', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(116, 88, 12, 112, 12, '2025-11-25 07:27:29', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(117, 88, 123, 123, 123, '2025-11-25 07:33:21', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(118, 88, 123, 123, 123, '2025-11-25 13:20:39', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(119, 88, 123, 123, 123, '2025-11-25 13:21:06', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(120, 88, 123, 123, 123, '2025-11-25 13:23:29', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(121, 88, 123, 123, 123, '2025-11-25 13:24:18', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(122, 88, 123, 123, 123, '2025-11-25 13:28:31', '2025-11-25 13:32:57', '2025-11-25 13:32:57'),
(123, 89, 1100, 1000, 12, '2025-11-25 13:30:17', '2025-11-25 13:33:02', '2025-11-25 13:33:02'),
(124, 89, 1300, 1200, 12, '2025-11-25 13:30:18', '2025-11-25 13:33:02', '2025-11-25 13:33:02'),
(125, 89, 0, 0, 0, '2025-11-25 13:31:42', '2025-11-25 13:33:02', '2025-11-25 13:33:02'),
(126, 90, 1, 1, 1, '2025-11-26 05:39:54', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(127, 90, 2222, 222, 22, '2025-11-26 06:14:57', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(128, 90, 2222, 222, 22, '2025-11-26 06:15:02', '2025-11-28 07:16:43', '2025-11-28 07:16:43'),
(129, 91, 13000000, 12000000, 12, '2025-11-28 06:24:48', '2025-11-28 07:16:47', '2025-11-28 07:16:47'),
(130, 91, 14000000, 13000000, 12, '2025-11-28 06:24:48', '2025-11-28 07:16:47', '2025-11-28 07:16:47'),
(131, 93, 0, 0, 0, '2025-11-28 07:44:23', '2025-11-28 07:44:47', '2025-11-28 07:44:47'),
(132, 95, 0, 0, 0, '2025-11-28 07:46:34', '2025-11-28 07:49:50', '2025-11-28 07:49:50'),
(133, 96, 0, 0, 0, '2025-11-28 07:53:41', '2025-11-28 07:53:53', '2025-11-28 07:53:53'),
(134, 97, 1111100, 1111110, 11110, '2025-11-28 14:14:13', '2025-11-28 14:31:42', NULL),
(135, 97, 2222220, 2222220, 111, '2025-11-28 14:22:10', '2025-11-28 14:31:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `variant_attribute_values`
--

CREATE TABLE `variant_attribute_values` (
  `id` bigint NOT NULL,
  `variant_id` bigint NOT NULL,
  `attribute_value_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `variant_attribute_values`
--

INSERT INTO `variant_attribute_values` (`id`, `variant_id`, `attribute_value_id`) VALUES
(1, 122, 28),
(2, 123, 29),
(3, 124, 30),
(66, 126, 35),
(67, 126, 36),
(68, 126, 39),
(69, 127, 35),
(70, 127, 37),
(71, 127, 39),
(72, 128, 35),
(73, 128, 38),
(74, 128, 39),
(77, 134, 40),
(78, 134, 41),
(79, 135, 40),
(80, 135, 41);

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_role` (`role_id`);

--
-- Indexes for table `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attribute_id` (`attribute_id`);

--
-- Indexes for table `brand_slides`
--
ALTER TABLE `brand_slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `categories_index_6` (`status`),
  ADD KEY `categories_index_7` (`deleted_at`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coupon_id` (`coupon_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `image_product`
--
ALTER TABLE `image_product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `image_product_index_12` (`product_id`),
  ADD KEY `image_product_index_13` (`deleted_at`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_index_14` (`user_id`),
  ADD KEY `order_index_15` (`coupon_id`),
  ADD KEY `order_index_16` (`status`),
  ADD KEY `order_index_17` (`payment_status`),
  ADD KEY `order_index_18` (`deleted_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_index_8` (`category_id`),
  ADD KEY `product_index_9` (`deleted_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `value` (`value`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_permission` (`permission_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `slides`
--
ALTER TABLE `slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `users_index_0` (`deleted_at`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_addresses_index_5` (`user_id`);

--
-- Indexes for table `variants`
--
ALTER TABLE `variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variants_index_10` (`product_id`),
  ADD KEY `variants_index_11` (`deleted_at`);

--
-- Indexes for table `variant_attribute_values`
--
ALTER TABLE `variant_attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `attribute_value_id` (`attribute_value_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `attribute_values`
--
ALTER TABLE `attribute_values`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `brand_slides`
--
ALTER TABLE `brand_slides`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `image_product`
--
ALTER TABLE `image_product`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `slides`
--
ALTER TABLE `slides`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `variants`
--
ALTER TABLE `variants`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `variant_attribute_values`
--
ALTER TABLE `variant_attribute_values`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `FK_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD CONSTRAINT `attribute_values_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`id`),
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`);

--
-- Constraints for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD CONSTRAINT `coupon_usage_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`),
  ADD CONSTRAINT `coupon_usage_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `coupon_usage_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`);

--
-- Constraints for table `image_product`
--
ALTER TABLE `image_product`
  ADD CONSTRAINT `image_product_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `order_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `variants`
--
ALTER TABLE `variants`
  ADD CONSTRAINT `variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Constraints for table `variant_attribute_values`
--
ALTER TABLE `variant_attribute_values`
  ADD CONSTRAINT `variant_attribute_values_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `variant_attribute_values_ibfk_2` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

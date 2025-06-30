-- phpMyAdmin SQL Dump
-- version 5.2.1deb1+deb12u1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 29, 2025 at 07:32 AM
-- Server version: 10.11.11-MariaDB-0+deb12u1
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tugas_claim_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read_by_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chats`
--

INSERT INTO `chats` (`id`, `sender_id`, `receiver_id`, `message`, `image_url`, `sent_at`, `is_read_by_admin`) VALUES
(1, 1, 3, 'test', NULL, '2025-06-27 05:28:23', 0),
(2, 3, 1, 'oke', NULL, '2025-06-27 05:39:27', 1),
(3, 1, 3, 'sip', NULL, '2025-06-27 05:40:08', 0),
(4, 1, 3, '1', NULL, '2025-06-27 05:40:13', 0),
(5, 1, 3, '2', NULL, '2025-06-27 05:40:14', 0),
(6, 1, 3, '3', NULL, '2025-06-27 05:40:14', 0),
(7, 1, 3, '4', NULL, '2025-06-27 05:40:15', 0),
(8, 1, 3, '5', NULL, '2025-06-27 05:40:15', 0),
(9, 3, 1, 'test', NULL, '2025-06-27 05:44:26', 1),
(10, 3, 1, 'test lah', NULL, '2025-06-27 05:44:42', 1),
(11, 1, 3, 'test', NULL, '2025-06-27 05:56:00', 1),
(12, 3, 1, 'test ', NULL, '2025-06-27 05:58:48', 1),
(13, 3, 1, 'test', NULL, '2025-06-27 05:59:06', 1),
(14, 3, 1, 'test', NULL, '2025-06-27 05:59:16', 1),
(15, 1, 3, 'ya', NULL, '2025-06-27 05:59:25', 1),
(16, 3, 1, 'test', NULL, '2025-06-27 05:59:31', 1),
(17, 3, 1, 'min', NULL, '2025-06-27 06:02:27', 1),
(18, 4, 1, 'min', NULL, '2025-06-27 06:03:07', 1),
(19, 4, 1, NULL, 'uploads/chat_images/685e34263fecd5.80747641.jpg', '2025-06-27 06:03:18', 1),
(20, 1, 4, NULL, 'uploads/chat_images/685e342f5a5760.04365364.jpg', '2025-06-27 06:03:27', 1),
(21, 4, 1, NULL, 'uploads/chat_images/685e39d7f41db3.11273973.jpg', '2025-06-27 06:27:36', 1),
(22, 4, 1, 'tolong proses depo saya min ', NULL, '2025-06-27 07:46:15', 1),
(23, 4, 1, NULL, 'uploads/chat_images/685e4c4cdce344.00760076.jpg', '2025-06-27 07:46:20', 1),
(24, 1, NULL, 'test', NULL, '2025-06-27 08:12:05', 1),
(25, 3, 1, 'test', NULL, '2025-06-27 10:40:54', 1),
(26, 3, 1, 'test', NULL, '2025-06-27 10:41:10', 1),
(27, 3, 1, NULL, 'uploads/chat_images/685eab671b99a1.89422944.jpg', '2025-06-27 14:32:07', 1),
(28, 3, 1, 'testinggg ', NULL, '2025-06-27 14:32:13', 1),
(29, 3, 1, 'test', NULL, '2025-06-27 14:55:58', 1),
(30, 3, 1, 'ping', NULL, '2025-06-27 14:56:16', 1),
(31, 1, 3, NULL, 'uploads/chat_images/685eb11a8db8c1.55311532.jpg', '2025-06-27 14:56:26', 1),
(32, 1, 3, 'pong', NULL, '2025-06-27 14:56:32', 1),
(33, 5, 1, 'admin test', NULL, '2025-06-28 03:55:34', 1),
(34, 5, 1, 'pesan masuk gak ?', NULL, '2025-06-28 03:55:55', 1),
(35, 5, 1, NULL, 'uploads/chat_images/685f67e51daad6.57103243.jpg', '2025-06-28 03:56:21', 1),
(36, 6, 1, 'min', NULL, '2025-06-28 03:57:36', 1),
(37, 1, 6, 'ya okey', NULL, '2025-06-28 03:57:44', 1),
(38, 1, 6, NULL, 'uploads/chat_images/685f683f2105a4.51405733.jpg', '2025-06-28 03:57:51', 1),
(39, 5, 1, 'min', NULL, '2025-06-28 03:59:56', 1),
(40, 1, 5, 'ok', NULL, '2025-06-28 04:00:11', 1),
(41, 3, 1, 'mau cicil produk ', NULL, '2025-06-28 06:14:57', 1),
(42, 3, 1, NULL, 'uploads/chat_images/685f888fa5ced5.70007069.png', '2025-06-28 06:15:43', 1),
(43, 3, 1, 'admin', NULL, '2025-06-28 13:10:29', 1),
(44, 3, 1, 'admin', NULL, '2025-06-28 13:10:45', 1),
(45, 5, 1, 'Tolong masukkin uang 1 milyar min ', NULL, '2025-06-28 13:19:11', 1),
(46, 3, 1, '1', NULL, '2025-06-28 13:53:33', 1),
(47, 3, 1, '2', NULL, '2025-06-28 13:53:38', 1),
(48, 3, 1, '1', NULL, '2025-06-28 14:25:06', 1),
(49, 5, 1, 'oi', NULL, '2025-06-29 04:49:07', 0),
(50, 3, 1, 'test', NULL, '2025-06-29 05:02:14', 1),
(51, 3, 1, 'oi', NULL, '2025-06-29 05:02:22', 1),
(52, 3, 1, NULL, 'uploads/chat_images/6860c9027597a3.11870629.jpg', '2025-06-29 05:02:58', 1),
(53, 1, 3, NULL, 'uploads/chat_images/6860c9152dadc9.10731469.jpg', '2025-06-29 05:03:17', 1),
(54, 1, 3, 'test', NULL, '2025-06-29 05:03:27', 1),
(55, 3, 1, '.', NULL, '2025-06-29 05:04:18', 0),
(56, 3, 1, 'test', NULL, '2025-06-29 06:43:08', 0),
(57, 3, 1, '.', NULL, '2025-06-29 06:46:11', 0);

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `claim_amount` decimal(15,2) NOT NULL,
  `commission_percentage` decimal(5,2) NOT NULL,
  `points_awarded` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `claimed_at` timestamp NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`id`, `user_id`, `product_id`, `claim_amount`, `commission_percentage`, `points_awarded`, `status`, `claimed_at`, `approved_by`, `approved_at`) VALUES
(4, 4, 17, 1111.00, 0.01, 1.00, 'approved', '2025-06-27 06:58:58', 1, '2025-06-27 06:59:20'),
(5, 4, 18, 4000000.00, 0.10, 2.00, 'approved', '2025-06-27 07:58:01', 1, '2025-06-27 07:58:27'),
(6, 3, 22, 100000.00, 0.01, 1.00, 'approved', '2025-06-27 09:29:08', 1, '2025-06-27 09:29:38'),
(7, 3, 21, 10000.00, 0.01, 1.00, 'approved', '2025-06-27 14:06:09', 1, '2025-06-27 14:47:37'),
(8, 3, 19, 1000.00, 0.01, 1.00, 'approved', '2025-06-27 14:06:20', 1, '2025-06-27 14:47:32'),
(9, 3, 20, 10000.00, 0.01, 1.00, 'approved', '2025-06-27 14:12:14', 1, '2025-06-27 14:47:28'),
(10, 3, 23, 1000.00, 0.01, 0.00, 'approved', '2025-06-27 14:35:15', 1, '2025-06-27 14:47:21'),
(11, 3, 27, 1338000.00, 0.10, 1.00, 'approved', '2025-06-28 05:58:24', 1, '2025-06-28 05:59:20'),
(12, 3, 25, 979000.00, 0.10, 0.00, 'approved', '2025-06-28 06:02:23', 1, '2025-06-28 06:02:48'),
(13, 3, 40, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 11:20:44', 1, '2025-06-28 12:07:55'),
(14, 3, 39, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 12:07:21', 1, '2025-06-28 12:07:39'),
(15, 3, 38, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 12:18:04', 1, '2025-06-28 13:25:39'),
(16, 5, 30, 150000.00, 1.00, 0.00, 'approved', '2025-06-28 13:24:06', 1, '2025-06-28 13:24:58'),
(17, 5, 33, 150000.00, 0.10, 0.00, 'rejected', '2025-06-28 13:25:01', 1, '2025-06-28 13:25:30'),
(18, 5, 33, 150000.00, 0.10, 0.00, 'rejected', '2025-06-28 13:25:55', 1, '2025-06-28 13:26:26'),
(19, 5, 35, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 13:28:41', 1, '2025-06-28 13:28:55'),
(20, 4, 28, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 13:30:06', 1, '2025-06-28 13:30:36'),
(21, 4, 33, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 13:30:16', 1, '2025-06-28 13:30:33'),
(22, 4, 37, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 13:40:00', 1, '2025-06-28 13:42:54'),
(23, 4, 34, 150000.00, 0.10, 0.00, 'approved', '2025-06-28 13:42:19', 1, '2025-06-28 13:43:00'),
(24, 3, 36, 150000.00, 0.10, 0.00, 'pending', '2025-06-28 13:54:09', NULL, NULL),
(25, 3, 32, 160000.00, 0.10, 0.00, 'approved', '2025-06-29 05:37:29', 1, '2025-06-29 05:37:57'),
(26, 3, 29, 150000.00, 0.10, 0.00, 'approved', '2025-06-29 05:39:18', 1, '2025-06-29 05:39:36'),
(27, 3, 31, 150000.00, 0.10, 0.00, 'approved', '2025-06-29 05:40:18', 1, '2025-06-29 05:40:34'),
(28, 3, 41, 150000.00, 0.10, 0.00, 'approved', '2025-06-29 05:45:29', 1, '2025-06-29 05:46:00'),
(29, 3, 42, 100000.00, 0.10, 1.00, 'pending', '2025-06-29 06:41:29', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `original_product_price` decimal(15,2) NOT NULL,
  `remaining_amount` decimal(15,2) NOT NULL,
  `commission_percentage` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `points_awarded` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installments`
--

INSERT INTO `installments` (`id`, `user_id`, `product_id`, `original_product_price`, `remaining_amount`, `commission_percentage`, `points_awarded`, `status`, `requested_at`, `approved_by`, `approved_at`, `completed_at`) VALUES
(1, 3, 28, 150000.00, 0.00, 0.0000, 0.00, 'completed', '2025-06-28 06:56:18', 1, '2025-06-28 07:01:35', NULL),
(2, 3, 26, 3187000.00, 2066500.00, 0.0000, 0.00, 'approved', '2025-06-28 07:22:45', 1, '2025-06-28 07:30:10', NULL),
(3, 3, 29, 15000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 08:21:01', 1, '2025-06-28 08:29:35', NULL),
(4, 3, 30, 150000.00, 0.00, 1.0000, 0.00, 'completed', '2025-06-28 08:34:52', 1, '2025-06-28 08:35:05', '2025-06-28 04:38:15'),
(5, 3, 31, 150000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 08:43:22', 1, '2025-06-28 08:43:50', '2025-06-28 05:11:02'),
(6, 3, 33, 150000.00, 150000.00, 0.1000, 0.00, 'approved', '2025-06-28 09:12:25', 1, '2025-06-28 09:12:44', NULL),
(7, 3, 32, 150000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 09:48:03', 1, '2025-06-28 09:48:31', '2025-06-28 05:55:28'),
(8, 3, 39, 150000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 09:51:17', 1, '2025-06-28 09:52:09', '2025-06-28 05:54:14'),
(9, 3, 37, 150000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 13:04:46', 1, '2025-06-28 13:40:25', '2025-06-29 01:46:56'),
(10, 5, 28, 150000.00, 0.00, 0.1000, 0.00, 'completed', '2025-06-28 13:17:21', 1, '2025-06-28 13:17:56', '2025-06-28 09:27:58'),
(11, 4, 26, 3187000.00, 2987000.00, 0.1000, 0.00, 'approved', '2025-06-28 13:31:49', 1, '2025-06-28 13:33:03', NULL),
(12, 5, 31, 150000.00, 150000.00, 0.1000, 0.00, 'rejected', '2025-06-28 14:57:05', 1, '2025-06-28 14:58:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `product_price` decimal(15,2) NOT NULL,
  `commission_percentage` decimal(5,2) NOT NULL,
  `points_awarded` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `image_url`, `product_price`, `commission_percentage`, `points_awarded`, `created_at`) VALUES
(17, 'test', 'test', 'uploads/product_images/product_685e40d6431330.86444541.jpg', 1111.00, 0.01, 1.00, '2025-06-27 06:57:26'),
(18, 'Pengiriman shipping ke belanda', 'printer sembarangan', 'uploads/product_images/product_685e4efeecf033.49740697.jpg', 4000000.00, 0.10, 2.00, '2025-06-27 07:57:50'),
(19, 'test', 'test', 'uploads/product_images/product_685e5ee649b717.19397918.jpg', 1000.00, 0.01, 1.00, '2025-06-27 09:05:42'),
(20, 'test 2', 'test', 'uploads/product_images/product_685e61d8dc0963.16889262.jpg', 10000.00, 0.01, 1.00, '2025-06-27 09:18:16'),
(21, 'test 3', 'test', 'uploads/product_images/product_685e61ebccdd96.00057675.jpg', 10000.00, 0.01, 1.00, '2025-06-27 09:18:35'),
(22, 'test 4', 'test', 'uploads/product_images/product_685e61fae4a124.82514034.jpg', 100000.00, 0.01, 1.00, '2025-06-27 09:18:50'),
(23, 'test', 'test', 'uploads/product_images/product_685eabd2955dc7.74498294.jpg', 100000.00, 0.10, 0.00, '2025-06-27 14:33:54'),
(25, 'EPSON SLD 830', 'Memuat barang Epson SLD830', 'uploads/product_images/product_685f831f261c41.84177423.jpg', 979000.00, 0.10, 0.00, '2025-06-28 05:52:31'),
(26, 'Printer Sublim EPSON L1110', 'Printer sumblm epson l1110', 'uploads/product_images/product_685f8398d761d5.05113059.jpg', 3187000.00, 0.10, 0.00, '2025-06-28 05:54:32'),
(27, 'Epson L310', 'epson l310', 'uploads/product_images/product_685f83c25f9a89.68176681.jpg', 1338000.00, 0.10, 1.00, '2025-06-28 05:55:14'),
(28, 'Multitask cicil', 'produk ini harus diselesaikan secara bersamaan untuk mendapatkan return', 'uploads/product_images/product_685f8783154cc7.35944667.jpeg', 150000.00, 0.10, 0.00, '2025-06-28 06:11:15'),
(29, 'test', 'test1', 'uploads/product_images/product_685fa48fa5b060.86601407.jpg', 150000.00, 0.10, 0.00, '2025-06-28 08:15:11'),
(30, 'Testi', 'testi', 'uploads/product_images/product_685fa90805f069.40344566.jpg', 150000.00, 1.00, 0.00, '2025-06-28 08:34:16'),
(31, 'Test', 'testi', 'uploads/product_images/product_685fab15667da9.75353102.jpg', 150000.00, 0.10, 0.00, '2025-06-28 08:43:01'),
(32, 'Test', 'testi', 'uploads/product_images/product_685ffb0f0e64e7.20040771.jpg', 160000.00, 0.10, 0.00, '2025-06-28 08:43:33'),
(33, 'test 5', 'test', 'uploads/product_images/product_685fae1f2752c5.19837271.jpg', 150000.00, 0.10, 0.00, '2025-06-28 08:55:59'),
(34, 'tes 1', '1', 'uploads/product_images/product_685fb7d31f0bd8.26204579.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:37:23'),
(35, 'test 2', 'test', 'uploads/product_images/product_685fb7e4ae1449.82229362.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:37:40'),
(36, 'test 3', 'test', 'uploads/product_images/product_685fb7f5becb72.90058940.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:37:57'),
(37, 'test 4', 'test', 'uploads/product_images/product_685fb80ad5c9a0.46022687.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:38:18'),
(38, 'test 5', 'testtt', 'uploads/product_images/product_685fb81fee61b9.58786082.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:38:39'),
(39, 'test 7', 'test', 'uploads/product_images/product_685fb940d93dc9.52566112.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:43:28'),
(40, 'test 8', 'test', 'uploads/product_images/product_685fb995504777.32304683.jpg', 150000.00, 0.10, 0.00, '2025-06-28 09:44:53'),
(41, 'Bang test', 'bang iwan cs awak', 'uploads/product_images/product_6860d2c643d324.76164168.jpg', 150000.00, 0.10, 0.00, '2025-06-29 05:44:38'),
(42, '0 testimoni', '1', 'uploads/product_images/product_6860d725a9b257.45345558.jpg', 100000.00, 0.10, 1.00, '2025-06-29 06:03:17'),
(43, '1 testimoni', 'bang iwan cs awak', 'uploads/product_images/product_6860dd22943628.70977908.jpg', 150000.00, 0.10, 0.00, '2025-06-29 06:28:50');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `receipt_image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `status`, `description`, `bank_name`, `account_number`, `account_name`, `created_at`, `approved_by`, `approved_at`, `receipt_image_url`) VALUES
(1, 4, 'withdraw', 9987086678.00, 'completed', NULL, 'BCA', '00011112222333', 'contoh', '2025-06-27 07:01:47', 1, '2025-06-27 07:02:31', NULL),
(2, 4, 'deposit', 50000.00, 'completed', NULL, 'BCA', '001122334455', 'Test', '2025-06-27 07:27:17', 1, '2025-06-27 07:32:42', 'uploads/deposit_receipts/receipt_685e47d57170d9.11533742.jpg'),
(3, 4, 'deposit', 5000000.00, 'completed', NULL, 'BCA', '001122334455', 'Test', '2025-06-27 07:43:32', 1, '2025-06-27 07:48:16', 'uploads/deposit_receipts/receipt_685e4ba4445de0.47694660.jpg'),
(4, 4, 'deposit', 50000.00, 'completed', NULL, 'BCA', '001122334455', 'Test', '2025-06-27 07:43:51', 1, '2025-06-27 07:48:12', 'uploads/deposit_receipts/receipt_685e4bb74a7e92.20539248.jpg'),
(5, 4, 'deposit', 10000.00, 'completed', NULL, 'mandiri', '001122334455', 'Test', '2025-06-27 07:44:07', 1, '2025-06-27 07:48:08', 'uploads/deposit_receipts/receipt_685e4bc733e371.68019412.jpg'),
(6, 4, 'deposit', 300000.00, 'completed', NULL, 'mandiri', '001122334455', 'Test', '2025-06-27 07:44:22', 1, '2025-06-27 07:48:03', 'uploads/deposit_receipts/receipt_685e4bd6f2a294.34643494.jpg'),
(7, 4, 'deposit', 50000.00, 'completed', NULL, 'mandiri', '001122334455', 'Test', '2025-06-27 07:44:34', 1, '2025-06-27 07:47:58', 'uploads/deposit_receipts/receipt_685e4be2dc4800.01766610.jpg'),
(8, 4, 'deposit', 100000.00, 'completed', NULL, '20000', '001122334455', 'Test', '2025-06-27 07:44:49', 1, '2025-06-27 07:47:52', 'uploads/deposit_receipts/receipt_685e4bf15dfcf5.95252018.jpg'),
(9, 4, 'deposit', 100000.00, 'completed', NULL, 'BCA', '001122334455', 'Test', '2025-06-27 07:45:04', 1, '2025-06-27 07:47:45', 'uploads/deposit_receipts/receipt_685e4c00ecc9d0.65427264.jpg'),
(10, 3, 'withdraw', 500000.00, 'completed', NULL, 'BCA', '12233445566', 'contoh', '2025-06-27 14:53:27', 1, '2025-06-27 14:54:10', NULL),
(11, 3, 'deposit', 9000000.00, 'completed', NULL, 'BCA', '001122334455', 'Test', '2025-06-27 14:54:51', 1, '2025-06-27 14:55:04', 'uploads/deposit_receipts/receipt_685eb0bb5a3ac9.72236556.jpg'),
(12, 5, 'deposit', 500000.00, 'completed', NULL, 'BRI', '123456', 'ramsay', '2025-06-28 04:00:54', 1, '2025-06-28 04:01:59', 'uploads/deposit_receipts/receipt_685f68f6c74211.12949468.jpg'),
(13, 5, 'withdraw', 500000.00, 'completed', NULL, 'BCA', '0123456', 'ramsay', '2025-06-28 04:02:30', 1, '2025-06-28 04:02:45', NULL),
(14, 5, 'deposit', 9000000.00, 'completed', NULL, 'BRI', '123456', 'ramsay', '2025-06-28 04:11:43', 1, '2025-06-28 04:13:15', 'uploads/deposit_receipts/receipt_685f6b7f643c93.02627200.jpg'),
(15, 5, 'deposit', 100000.00, 'completed', NULL, 'BRI', '123456', 'ramsay', '2025-06-28 04:12:18', 1, '2025-06-28 04:13:18', 'uploads/deposit_receipts/receipt_685f6ba209a2a6.41804998.jpg'),
(16, 5, 'withdraw', 9100000.00, 'completed', NULL, 'BCA', '0123456', 'ramsay', '2025-06-28 04:13:46', 1, '2025-06-28 04:13:59', NULL),
(17, 5, 'deposit', 200000.00, 'pending', NULL, 'BRI', '123456', 'ramsay', '2025-06-28 04:16:24', NULL, NULL, 'uploads/deposit_receipts/receipt_685f6c9861a477.87120509.jpg'),
(18, 5, 'deposit', 10000.00, 'completed', NULL, 'BRI', '123456', 'ramsay', '2025-06-28 04:18:08', 1, '2025-06-28 13:16:52', 'uploads/deposit_receipts/receipt_685f6d000211e0.07352764.jpg'),
(19, 3, 'withdraw', 9000000.00, 'completed', NULL, 'BCA', '651611818181', 'TEST', '2025-06-28 06:00:09', 1, '2025-06-28 06:00:25', NULL),
(20, 3, 'deposit', 1000000.00, 'completed', NULL, 'bca', '123456789', 'test', '2025-06-28 06:01:33', 1, '2025-06-28 06:02:00', 'uploads/deposit_receipts/receipt_685f853d27cc32.11139631.jpeg'),
(21, 3, 'withdraw', 100000.00, 'completed', 'Pembayaran cicilan ID 2', NULL, NULL, NULL, '2025-06-28 07:42:13', NULL, NULL, NULL),
(22, 3, 'withdraw', 20000.00, 'completed', 'Pembayaran cicilan ID 2', NULL, NULL, NULL, '2025-06-28 07:58:22', NULL, NULL, NULL),
(23, 3, 'withdraw', 150000.00, 'completed', 'Pembayaran cicilan ID 1', NULL, NULL, NULL, '2025-06-28 08:02:51', NULL, NULL, NULL),
(24, 3, 'withdraw', 15000.00, 'completed', 'Pembayaran cicilan ID 3', NULL, NULL, NULL, '2025-06-28 08:31:13', NULL, NULL, NULL),
(26, 3, 'withdraw', 150000.00, 'completed', 'Pembayaran cicilan ID 4 untuk tugas \'Testi\'', NULL, NULL, NULL, '2025-06-28 08:38:15', NULL, NULL, NULL),
(27, 3, 'deposit', 300000.00, 'completed', 'Penyelesaian cicilan tugas \'Testi\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-28 08:38:15', NULL, NULL, NULL),
(28, 3, 'withdraw', 150000.00, 'completed', 'Pembayaran cicilan ID 5 untuk tugas \'Test\'', NULL, NULL, NULL, '2025-06-28 09:11:02', NULL, NULL, NULL),
(29, 3, 'deposit', 165000.00, 'completed', 'Penyelesaian cicilan tugas \'Test\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-28 09:11:02', NULL, NULL, NULL),
(30, 3, 'withdraw', 500000.00, 'completed', 'Pembayaran cicilan ID 2 untuk tugas \'Printer Sublim EPSON L1110\'', NULL, NULL, NULL, '2025-06-28 09:11:56', NULL, NULL, NULL),
(31, 3, 'withdraw', 15000.00, 'completed', 'Pembayaran cicilan ID 8 untuk tugas \'test 7\'', NULL, NULL, NULL, '2025-06-28 09:53:03', NULL, NULL, NULL),
(32, 3, 'withdraw', 13500.00, 'completed', 'Pembayaran cicilan ID 8 untuk tugas \'test 7\'', NULL, NULL, NULL, '2025-06-28 09:53:22', NULL, NULL, NULL),
(33, 3, 'withdraw', 121499.00, 'completed', 'Pembayaran cicilan ID 8 untuk tugas \'test 7\'', NULL, NULL, NULL, '2025-06-28 09:53:51', NULL, NULL, NULL),
(34, 3, 'withdraw', 1.00, 'completed', 'Pembayaran cicilan ID 8 untuk tugas \'test 7\'', NULL, NULL, NULL, '2025-06-28 09:54:14', NULL, NULL, NULL),
(35, 3, 'deposit', 165000.00, 'completed', 'Penyelesaian cicilan tugas \'test 7\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-28 09:54:14', NULL, NULL, NULL),
(36, 3, 'withdraw', 149989.00, 'completed', 'Pembayaran cicilan ID 7 untuk tugas \'Test\'', NULL, NULL, NULL, '2025-06-28 09:54:45', NULL, NULL, NULL),
(37, 3, 'withdraw', 11.00, 'completed', 'Pembayaran cicilan ID 7 untuk tugas \'Test\'', NULL, NULL, NULL, '2025-06-28 09:55:28', NULL, NULL, NULL),
(38, 3, 'deposit', 165000.00, 'completed', 'Penyelesaian cicilan tugas \'Test\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-28 09:55:28', NULL, NULL, NULL),
(39, 5, 'withdraw', 10000.00, 'completed', 'Pembayaran cicilan ID 10 untuk tugas \'Multitask cicil\'', NULL, NULL, NULL, '2025-06-28 13:18:13', NULL, NULL, NULL),
(40, 5, 'withdraw', 140000.00, 'completed', 'Pembayaran cicilan ID 10 untuk tugas \'Multitask cicil\'', NULL, NULL, NULL, '2025-06-28 13:27:58', NULL, NULL, NULL),
(41, 5, 'deposit', 165000.00, 'completed', 'Penyelesaian cicilan tugas \'Multitask cicil\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-28 13:27:58', NULL, NULL, NULL),
(42, 4, 'withdraw', 200000.00, 'completed', 'Pembayaran cicilan ID 11 untuk tugas \'Printer Sublim EPSON L1110\'', NULL, NULL, NULL, '2025-06-28 13:41:21', NULL, NULL, NULL),
(43, 5, 'withdraw', 300000.00, 'rejected', NULL, 'BCA', '0123456', 'ramsay', '2025-06-28 14:46:17', 1, '2025-06-28 14:46:25', NULL),
(44, 5, 'withdraw', 300000.00, 'pending', NULL, 'BCA', '0123456', 'ramsay', '2025-06-28 14:56:50', NULL, NULL, NULL),
(45, 3, 'withdraw', 150000.00, 'completed', 'Pembayaran cicilan ID 9 untuk tugas \'test 4\'', NULL, NULL, NULL, '2025-06-29 05:46:56', NULL, NULL, NULL),
(46, 3, 'deposit', 165000.00, 'completed', 'Penyelesaian cicilan tugas \'test 4\' (Harga Asli + Komisi)', NULL, NULL, NULL, '2025-06-29 05:46:56', NULL, NULL, NULL),
(47, 3, 'withdraw', 500000.00, 'completed', 'Pembayaran cicilan ID 2 untuk tugas \'Printer Sublim EPSON L1110\'', NULL, NULL, NULL, '2025-06-29 05:48:02', NULL, NULL, NULL),
(48, 3, 'withdraw', 500.00, 'completed', 'Pembayaran cicilan ID 2 untuk tugas \'Printer Sublim EPSON L1110\'', NULL, NULL, NULL, '2025-06-29 05:48:28', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `points` decimal(15,2) DEFAULT 0.00,
  `membership_level` enum('Bronze','Silver','Gold','Platinum','VVIP') DEFAULT 'Bronze',
  `is_admin` tinyint(1) DEFAULT 0,
  `profile_picture_url` varchar(255) DEFAULT 'uploads/profile_pictures/default.png',
  `full_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `balance`, `points`, `membership_level`, `is_admin`, `profile_picture_url`, `full_name`, `address`, `phone_number`, `nationality`, `created_at`) VALUES
(1, 'admin', '$2y$12$aYgruowkUtXYRF4PbPLjV.a3dsW3M5izpXf6p07YO6RDr2JtOcApa', 'admin@example.com', 0.00, 0.00, 'Platinum', 1, 'uploads/profile_pictures/profile_685eaf25b190d2.74125581.jpg', '', '', '', '', '2025-06-26 15:29:26'),
(2, 'user1', '$2y$10$Q4u/K6d/HhV0hFvF/Q4b7O.Q0m.Z9p.c5aN.0Y9pP.y.3J0t3m.d', 'user1@example.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-26 15:29:26'),
(3, 'contoh', '$2y$12$uiqYxbBRzCPk7lDiK2xpaOzXp2NVrRQU6j4Ni4pbF/bpX9e2Mf752', 'ramsayygordon@gmail1.com', 813420.00, 1.00, 'VVIP', 0, 'uploads/profile_pictures/profile_6860d6ab5f15e1.32654815.jpg', 'Gordon Ramsay', 'Contoh', '0811757505', 'Indonesia Raya', '2025-06-26 15:45:45'),
(4, 'contoh2', '$2y$12$tl7xpSWqdce9Pgp1GnMi2.kV2efxtF5u7C6zWPtO5/0hshaXqgSDO', 'contoh2@gmail.com', 5920000.11, 4.00, 'Bronze', 0, 'uploads/profile_pictures/profile_685fd6189b7291.09873537.jpg', '', '', '', '', '2025-06-27 06:02:52'),
(5, 'ramsay', '$2y$12$TaCTcpZE6E4kAahoxjAfTOf1897DUXVt8p0YaFd1D5PwpuKt4boC2', 'ramsayygordon@gmail.com', 3100000.00, 0.00, 'Silver', 0, 'uploads/profile_pictures/profile_6860e9eeba8ed7.64893541.jpg', 'Gordon Ramsay', 'Amerika Serikat', '+62811757505', 'Indonesia Raya', '2025-06-28 03:43:02'),
(6, 'test1', '$2y$12$SJF08tkiHKCBG5EyP3MWhOPKSQN0V.BqISGEM5M9QbeZWfB/4Ti/6', 'test1@test.com', 20000.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-28 03:57:01'),
(7, 'contoh3', '$2y$12$1KTtJvShEQBAeoSmM2VqNuJgwcqUdCLNAcH1.LHHaRFpD.7vfpE/W', 'contoh3@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:50:03'),
(8, 'contoh4', '$2y$12$sEQpggwKA1k16T9GN.iZD.k421We4srAAuTXS5ogGDAcgsYgToVbG', 'contoh4@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:50:31'),
(9, 'contoh5', '$2y$12$xWHDsAVubGCIWmFdnsNUFetIeBh0YUTA162O8SLQBIZn1AIj63Y.q', 'contoh5@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:50:49'),
(10, 'contoh6', '$2y$12$PuNwHIgIHdSqlKsPQ.4oPOfLfhjVDduqG6Yd5moQpcTQBtT9c5F7a', 'contoh6@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:51:13'),
(11, 'contoh7', '$2y$12$ynXTavuJfuHQmuHMe3T1a.dQ6WYYIA0sDVIdT3EnpMqkPUU.44GCS', 'contoh7@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:51:24'),
(12, 'contoh8', '$2y$12$VeXMcQmVyThYQYSnbfgg8eUUtKCbIBcPecCNqCON7AB8hnm3tusTq', 'contoh8@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:51:37'),
(13, 'contoh9', '$2y$12$bCuCtrMun20RYXVkCQ4WqOlnmGT0v.25kw2OQTVQdwcPtPDokJPW6', 'contoh9@contoh.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 04:51:53'),
(14, 'testttt', '$2y$10$skP8z4meSSq1CGErcsYQGOKlVZ1S6kMOyt/m0MRa2ZSdwpZ1Zde8a', 'testttt@test.com', 0.00, 0.00, 'Bronze', 0, 'uploads/profile_pictures/default.png', NULL, NULL, NULL, NULL, '2025-06-29 05:08:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `installments_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

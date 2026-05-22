-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 15, 2026 at 05:56 PM
-- Server version: 5.7.44-log
-- PHP Version: 8.1.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nexa02`
--

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` int(11) NOT NULL,
  `userid` varchar(11) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `trial` varchar(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `max_devices` int(11) DEFAULT '1',
  `expired_at` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `device_id` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `licenses`
--

INSERT INTO `licenses` (`id`, `userid`, `license_key`, `trial`, `status`, `max_devices`, `expired_at`, `created_at`, `device_id`) VALUES
(1, '1', 'ABC-123-XYZ', '1', 'active', 1, 1778594689, '2026-04-12 13:35:58', NULL),
(2, '1', 'PRO-999-ULTIMATE', '1', 'inactive', 1, 1776177358, '2026-04-12 13:35:58', NULL),
(3, '1', 'ABC-123-XYZ3', '7', 'active', 1, 1778792166, '2026-04-13 23:41:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `license_devices`
--

CREATE TABLE `license_devices` (
  `id` int(11) NOT NULL,
  `license_id` int(11) NOT NULL,
  `device_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 hex',
  `app_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Nama app dari package.json',
  `registered_at` int(10) UNSIGNED NOT NULL,
  `last_seen_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `license_devices`
--

INSERT INTO `license_devices` (`id`, `license_id`, `device_id`, `app_id`, `registered_at`, `last_seen_at`) VALUES
(2, 2, '79a64f110bf6f787be9bafb79a4dc40abeff6a765098fca57d84fcb549c806de', 'lisensi', 1776014932, 1776122366),
(3, 1, '79a64f110bf6f787be9bafb79a4dc40abeff6a765098fca57d84fcb549c806de', 'sshftp', 1776015013, 1776041434),
(4, 3, '79a64f110bf6f787be9bafb79a4dc40abeff6a765098fca57d84fcb549c806de', 'dashboard', 1776123783, 1776242618);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `userid` varchar(11) NOT NULL DEFAULT '',
  `license_id` int(11) NOT NULL,
  `plan` varchar(50) NOT NULL,
  `amount` decimal(12,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'IDR',
  `order_id` varchar(100) DEFAULT NULL,
  `started_at` int(10) UNSIGNED NOT NULL,
  `expired_at` int(10) UNSIGNED NOT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `userid`, `license_id`, `plan`, `amount`, `currency`, `order_id`, `started_at`, `expired_at`, `status`, `created_at`) VALUES
(5, '1', 3, 'monthly', 29000.00, 'IDR', 'ORDER-MONTHLY-1776199444281', 1776199466, 1818204095, 'expired', '2026-04-14 20:44:26'),
(7, '1', 3, 'monthly', 29000.00, 'IDR', 'ORDER-MONTHLY-1776200130437', 1776200166, 1778792166, 'active', '2026-04-14 20:56:06');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `status` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instansi` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row` enum('1') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kecamatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desa` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_time` int(10) UNSIGNED DEFAULT NULL COMMENT 'Unix timestamp saat login',
  `last_activity` int(10) UNSIGNED DEFAULT NULL COMMENT 'Unix timestamp aktivitas terakhir',
  `last_seen` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT '0',
  `online_status` enum('online','away','offline') COLLATE utf8mb4_unicode_ci DEFAULT 'offline',
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `status`, `nama`, `instansi`, `jabatan`, `role`, `email`, `password`, `telepon`, `alamat`, `avatar`, `package`, `gender`, `token`, `expired`, `row`, `kecamatan`, `desa`, `nik`, `login_time`, `last_activity`, `last_seen`, `is_online`, `online_status`, `last_ip`, `session_id`) VALUES
(3, 'admin', 'dantik', NULL, NULL, 'admin', 'admin@gmail.com', 'N12345678', '081341759025', NULL, '/assets/drive/avatar/2026/04/avatar_3_1776241293.jpg', NULL, 'male', NULL, NULL, '1', NULL, NULL, '3434343232323132', NULL, NULL, NULL, 0, 'offline', NULL, NULL),
(6, 'user', 'Abdul Maskhur Saleh', NULL, NULL, 'user', 'obet.lipan@gmail.com', '$2y$10$MHZydnJwmpfQGGJC/0KYPOI1GF6W59Cvg0P.t7VmKlRkE5XRNSbIm', NULL, NULL, '/assets/drive/avatar/2026/04/avatar_6_1776157384.png', NULL, 'male', NULL, NULL, '1', NULL, NULL, NULL, NULL, NULL, NULL, 0, 'offline', NULL, NULL),
(10, 'user', 'Iyan R Saleh', NULL, NULL, 'user', 'ian.obet@gmail.com', 'N123456789', NULL, NULL, '/assets/drive/avatar/2026/04/avatar_10_1776243804.jpg', NULL, 'male', NULL, NULL, '1', NULL, NULL, NULL, NULL, NULL, NULL, 0, 'offline', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`);

--
-- Indexes for table `license_devices`
--
ALTER TABLE `license_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license_devices_license_id_index` (`license_id`),
  ADD KEY `license_devices_device_id_index` (`device_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `license_id` (`license_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_login_time_index` (`login_time`),
  ADD KEY `user_last_activity_index` (`last_activity`),
  ADD KEY `user_last_seen_index` (`last_seen`),
  ADD KEY `user_is_online_index` (`is_online`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `license_devices`
--
ALTER TABLE `license_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

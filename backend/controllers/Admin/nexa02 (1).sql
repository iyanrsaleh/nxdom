-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 15, 2026 at 04:16 AM
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
(2, '1', 'PRO-999-ULTIMATE', '1', 'active', 1, 1776177358, '2026-04-12 13:35:58', NULL),
(3, '1', 'ABC-123-XYZ3', '2', 'active', 1, 1776300095, '2026-04-13 23:41:35', NULL);

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
(4, 3, '79a64f110bf6f787be9bafb79a4dc40abeff6a765098fca57d84fcb549c806de', 'dashboard', 1776123783, 1776123783);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

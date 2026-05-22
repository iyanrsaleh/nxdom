-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 05, 2026 at 06:21 PM
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
-- Table structure for table `controllers`
--

CREATE TABLE `controllers` (
  `id` int(11) NOT NULL,
  `userid` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categori` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acinsert` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acdelete` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acupdate` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appname` varchar(125) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appid` varchar(230) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appicon` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` json DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kabupaten` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desa` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `controllers`
--

INSERT INTO `controllers` (`id`, `userid`, `categori`, `label`, `version`, `status`, `approval`, `acinsert`, `acdelete`, `acupdate`, `all`, `appname`, `appid`, `appicon`, `data`, `updated_at`, `kabupaten`, `kecamatan`, `desa`, `provinsi`) VALUES
(1, '3', 'Accses', 'Accses', '1.0.0', '1', '1', '1', '0', '1', '1', NULL, NULL, 'inventory_2', NULL, '2026-05-05 10:19:52', '0', '0', '0', '0'),
(2, '14', 'Accses', 'Accses', '1.0.0', '1', '1', '1', '0', '1', '1', NULL, NULL, 'inventory_2', NULL, '2026-05-05 10:19:58', '0', '0', '0', '0');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `controllers`
--
ALTER TABLE `controllers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `controllers_categori_index` (`categori`),
  ADD KEY `controllers_userid_index` (`userid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `controllers`
--
ALTER TABLE `controllers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

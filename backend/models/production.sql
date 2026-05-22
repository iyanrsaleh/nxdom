-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 18, 2026 at 04:19 PM
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
-- Table structure for table `production`
--

CREATE TABLE `production` (
  `id` int(11) NOT NULL,
  `authorization` varchar(255) DEFAULT NULL,
  `appid` bigint(20) DEFAULT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `method_get` tinyint(1) DEFAULT NULL,
  `method_post` tinyint(1) DEFAULT NULL,
  `method_put` tinyint(1) DEFAULT NULL,
  `method_patch` tinyint(1) DEFAULT NULL,
  `method_options` tinyint(1) DEFAULT NULL,
  `method_delete` tinyint(1) DEFAULT NULL,
  `expiration` json DEFAULT NULL,
  `build_query` json DEFAULT NULL,
  `description` text,
  `userid` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Tambahan kolom appname, developer, version
--
ALTER TABLE `production`
  ADD COLUMN `appname` varchar(255) DEFAULT NULL AFTER `description`,
  ADD COLUMN `developer` varchar(255) DEFAULT NULL AFTER `appname`,
  ADD COLUMN `version` varchar(50) DEFAULT NULL AFTER `developer`;

--
-- Indexes for table `production`
--
ALTER TABLE `production`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `production`
--
ALTER TABLE `production`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

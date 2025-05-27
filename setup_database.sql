-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: May 27, 2025 at 10:46 PM
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
-- Database: `water_quality_db`
--

CREATE DATABASE IF NOT EXISTS `water_quality_db`;
USE `water_quality_db`;

-- --------------------------------------------------------

--
-- Table structure for table `relay_states`
--

CREATE TABLE `relay_states` (
  `id` int(11) NOT NULL,
  `relay_number` int(11) NOT NULL,
  `state` tinyint(1) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `relay_states`
--

INSERT INTO `relay_states` (`id`, `relay_number`, `state`, `last_updated`) VALUES
(1, 1, 0, '2025-05-25 14:07:04'),
(2, 2, 0, '2025-05-25 14:07:00'),
(3, 3, 0, '2025-05-25 14:06:58'),
(4, 4, 0, '2025-05-25 14:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `water_readings`
--

CREATE TABLE `water_readings` (
  `id` int(11) NOT NULL,
  `turbidity` float NOT NULL,
  `tds` float NOT NULL,
  `ph` float NOT NULL,
  `temperature` float NOT NULL,
  `in` float NOT NULL,
  `reading_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `relay_states`
--
ALTER TABLE `relay_states`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `water_readings`
--
ALTER TABLE `water_readings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `relay_states`
--
ALTER TABLE `relay_states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `water_readings`
--
ALTER TABLE `water_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */; 
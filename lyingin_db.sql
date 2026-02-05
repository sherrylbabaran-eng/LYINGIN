-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 28, 2026 at 06:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lyingin_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_patient_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_role`, `actor_id`, `action`, `target_patient_id`, `created_at`) VALUES
(1, 'patient', 1, 'Registered account with ID upload', 1, '2026-01-13 10:09:14'),
(2, 'patient', 1, 'Registered account with ID upload', 1, '2026-01-13 10:23:56'),
(3, 'staff', 1, 'Staff approved patient account', 1, '2026-01-13 10:47:01'),
(4, 'staff', 1, 'Staff approved patient account', 1, '2026-01-13 12:08:59'),
(5, 'staff', 1, 'Staff approved patient account', 1, '2026-01-13 12:09:03'),
(6, 'staff', 1, 'Staff rejected patient account', 2, '2026-01-13 12:32:27'),
(7, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 13:00:23'),
(8, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 13:03:54'),
(9, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 13:17:19'),
(10, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 13:18:45'),
(11, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 14:11:20'),
(12, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 14:12:00'),
(13, 'staff', 1, 'Staff rejected patient account: hahaha', 3, '2026-01-13 14:12:18'),
(14, 'staff', 1, 'Staff approved patient account', 4, '2026-01-14 07:07:50'),
(15, 'staff', 1, 'Staff approved patient account', 4, '2026-01-14 07:31:37'),
(16, 'staff', 1, 'Staff approved patient account', 10, '2026-01-14 09:13:39'),
(17, 'staff', 1, 'Staff approved patient account', 10, '2026-01-14 09:13:59'),
(18, 'staff', 1, 'Staff approved patient account', 12, '2026-01-14 10:23:10'),
(19, 'staff', 1, 'Staff approved patient account', 0, '2026-01-14 10:48:02'),
(20, 'staff', 1, 'Staff approved patient account', 13, '2026-01-14 11:00:01'),
(21, 'staff', 1, 'Staff approved patient account', 14, '2026-01-14 11:02:19'),
(22, 'staff', 1, 'Staff approved patient account', 14, '2026-01-14 11:03:38'),
(23, 'staff', 1, 'Staff approved patient account', 15, '2026-01-14 11:03:42'),
(24, 'staff', 1, 'Staff approved patient account', 16, '2026-01-14 11:16:04'),
(25, 'staff', 1, 'Staff approved patient account', 16, '2026-01-14 11:16:34'),
(26, 'staff', 1, 'Staff approved patient account', 17, '2026-01-14 11:17:36'),
(27, 'staff', 1, 'Staff approved patient account', 17, '2026-01-14 11:19:32'),
(28, 'staff', 1, 'Staff approved patient account', 18, '2026-01-14 11:21:10'),
(29, 'staff', 1, 'Staff approved patient account', 19, '2026-01-14 11:26:10'),
(30, 'staff', 1, 'Staff approved patient account', 20, '2026-01-20 06:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` int(11) NOT NULL,
  `clinic_name` varchar(255) DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `admin_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `id_type` enum('Passport','Driver''s License','National ID','PhilHealth ID','SSS ID') NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_file` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `clinic_name`, `license_number`, `address`, `email`, `contact_number`, `admin_name`, `username`, `password`, `id_type`, `id_number`, `latitude`, `longitude`, `created_at`, `id_file`, `email_verified`, `email_verification_token`, `email_verification_expires`) VALUES
(1, 'clinic1', '12345', 'adsadas', 'calvin26@gmail.com', '096523379023', 'John Doe', 'nix', '$2y$10$qadG9hANK7dyxdT1BjVmxuDNc7nNJ5TDNGAOXuE1QrRANlA3jkYem', '', '123456789012', NULL, NULL, '2026-01-26 08:53:56', 'uploads/1769417636_makabayan.png', 0, NULL, NULL),
(2, 'clinic1', '12345', 'adsaad', 'taylorswift@gmail.com', '096523379023', 'John Doe', 'nix', '$2y$10$nXqVYfDkLPzlURiWGhZuM.XExeCuV6W40/hMRlRjmBMYzXi48Eo8i', '', '123456789012', NULL, NULL, '2026-01-26 09:20:33', 'uploads/1769419233_makabayan.png', 0, NULL, NULL),
(3, 'q', '121212', 'ad', 'taylor@gmail.com', '123123123', 'a', 'a', '$2y$10$yBBHk3ijMgKKCz2UsvE0c.V4n1erjssQWkzb2zLc5mVg0gqyyHMN6', '', '123321312331', NULL, NULL, '2026-01-28 04:58:44', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769576324_5042720.jpg', 0, '8a2974d72103a23b3813d28aaba52db40e4b039d72d6e9896e4371393d8490e3', '2026-01-29 05:58:44'),
(4, 'q', '121212', 'ad', 'taylor@gmail.com', '123123123', 'a', 'a', '$2y$10$Vu/6onsojxygBwWCEoFzBewNREURkR5KVRJFhfXjOGzx46zVNIhtK', '', '123321312331', NULL, NULL, '2026-01-28 04:58:45', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769576325_5042720.jpg', 0, NULL, NULL),
(5, 'clinic1', '12345', 'aaa', 'tandicoalessandranicole@gmail.com', '096523379023', 'John Doe', 'nix', '$2y$10$GAS9KOnoSPid603ZDJT1rui89mw3BBseKrpIM3irgGqSOSLcjT4du', '', '123456789012', NULL, NULL, '2026-01-28 05:02:46', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769576566_makabayan.png', 0, 'ea16c34fc31573fdc3344a7cc37e785ec351bc2086bea4ac523bab90ebb0ff2f', '2026-01-29 06:02:46'),
(6, 'clinic1', '12345', 'aaa', 'tandicoalessandranicole@gmail.com', '096523379023', 'John Doe', 'nix', '$2y$10$rs5zUCSxQ7r9RT.OopCGwuEj6WL3.RbGEtA1X9vLvzWt83FclEvdC', '', '123456789012', NULL, NULL, '2026-01-28 05:02:47', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769576567_makabayan.png', 0, NULL, NULL),
(7, 'clinic1', '12345', 'a', 'tandicoalessandranicole@gmail.com', '096523379023', 'John Doe', 'nix', '$2y$10$nacLr/ctxv5OWk6/FtffNOMMfOLV1y0Mmav9Q.yoHvDrYHirzOuZm', '', '123456789012', NULL, NULL, '2026-01-28 05:05:20', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769576720_makabayan.png', 1, NULL, NULL),
(8, 'q', '121212', 'aa', 'deguzman.alessandranicole@ncst.edu.ph', '2121212', 'a', 'nix', '$2y$10$zZB0iKAyTsMUatc3ELIrLuB78WL3OPPHSrwBMko.aDmYDaVK/YgLO', '', '123321312331', NULL, NULL, '2026-01-28 05:12:35', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769577155_BUTTON.png', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birthdate` date NOT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `number_of_pregnancies` int(11) DEFAULT NULL,
  `number_of_births` int(11) DEFAULT NULL,
  `last_menstrual_period` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `gestational_age_weeks` int(11) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_file_path` varchar(255) NOT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked` tinyint(1) DEFAULT 0,
  `terms_accepted` tinyint(1) NOT NULL,
  `privacy_consent` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `email`, `password_hash`, `first_name`, `middle_name`, `last_name`, `birthdate`, `civil_status`, `phone`, `barangay`, `city`, `province`, `number_of_pregnancies`, `number_of_births`, `last_menstrual_period`, `expected_delivery_date`, `gestational_age_weeks`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_number`, `id_type`, `id_file_path`, `verification_status`, `rejection_reason`, `email_verified`, `email_verification_token`, `failed_login_attempts`, `account_locked`, `terms_accepted`, `privacy_consent`, `created_at`, `is_verified`, `verified_at`) VALUES
(1, 'tandicoalessandranicole@gmail.com', '$2y$10$/YPdJcvfQ6ON8j2kMuu7heuWuKXv92h0Oy1L909Eb7ki7FXD/JSKW', 'Alessandra Nicole', 'Tandico', 'De Guzman', '2005-01-22', 'Single', '+639652379023', 'Malagasang II-A', 'imus', 'Cavite', 1, 1, '0000-00-00', '2006-10-08', 1045, 'Merl', 'Friend', '09652379023', 'PhillD', 'uploads/ids/696635be55363_liveD965A3D2-E09E-4602-BE1A-5C2793924A99.jpeg', 'verified', NULL, 0, NULL, 0, 0, 1, 1, '2026-01-13 12:08:30', 0, NULL),
(2, 'spongeybob@gmail.com', '$2y$10$oUbtte5Fnuzu/NfPc0q.f.jA30XsSV.TIz0vH8XzTS8Q6rj5aIXOC', 'Spongebob', '', 'Squarepants', '2000-01-01', 'Single', '+639652379023', 'Malagasang II-A', 'imus', 'Cavite', 1, 1, '2026-01-02', '2026-10-09', 1, 'Merl', 'Friend', '09652379023', 'PhillD', 'uploads/ids/69663b50b7f8b_BUTTON.png', 'rejected', NULL, 0, NULL, 0, 0, 1, 1, '2026-01-13 12:32:16', 0, NULL),
(3, 'taylorswift@gmail.com', '$2y$10$F3fZI5VVxMHdoJbnwQVZ3OaR4MtANUEGsLJ5hgba9MgUB..NEbG2y', 'Taylor Alison', '', 'Swift', '1989-12-13', 'Taken', '+639652379023', 'Malagasang II-A', 'imus', 'Cavite', 1, 1, '2026-01-03', '2026-10-10', 1, 'Travis', 'Husband', '09652379023', 'PhillD', 'uploads/ids/69663e2503016_liveD965A3D2-E09E-4602-BE1A-5C2793924A99.jpeg', 'rejected', 'hahaha', 0, NULL, 0, 0, 1, 1, '2026-01-13 12:44:21', 0, NULL),
(20, 'janelle25@gmail.com', '$2y$10$fuOdatIDuZPX0rpKyEpRsO6EAK7Z./e81Dm6wJBmgHiio7cDfGbH.', 'Janelle', 'Samaniego', 'Dacon', '2002-01-25', 'single', '09889465421', 'San Francisco', 'General Trias', 'Cavite', 0, 0, '2025-12-01', '2026-09-07', 7, 'Calvin Genesis Samaniego', 'Sibling', '09289754286', 'National ID', 'uploads/ids/696f1a7559a9e_liveD965A3D2-E09E-4602-BE1A-5C2793924A99.jpeg', 'verified', NULL, 0, NULL, 0, 0, 1, 1, '2026-01-20 06:02:29', 0, NULL),
(21, 'sandra012205@gmail.com', '$2y$10$3RDhzF8g.C42EMmstz3lqOyfpraU3OSE1j3bZYX5aUfN73emjHOF.', 'Alessandra Nicole', 'a', 'De Guzman', '2005-01-22', 'married', '+639652379023', 'malagasang II-A', 'Imus', 'Cavite', 1, 2, '2020-01-10', '2020-10-16', 315, 'm', '3', 'adad', 'nationak', 'uploads/ids/6975c0c081926_BUTTON.png', 'pending', NULL, 0, NULL, 0, 0, 1, 1, '2026-01-25 07:05:36', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `regpatient`
--

CREATE TABLE `regpatient` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_type` enum('passport','drivers','national','philhealth','sss') NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `id_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `regpatient`
--

INSERT INTO `regpatient` (`id`, `first_name`, `last_name`, `birthdate`, `gender`, `email`, `contact_no`, `address`, `username`, `password`, `id_type`, `id_number`, `id_file`, `created_at`, `email_verified`, `email_verification_token`, `email_verification_expires`) VALUES
(1, 'Alessandra Nicole', 'De Guzman', '2005-01-22', 'female', 'tandicoalessandranicole@gmail.com', '09235468989', 'a', 'nix', '$2y$10$wCnV59JMVM.BteiPCD92TeJd04wg8ONQhLr24iF4SdzDTbgUWIfPK', 'national', '123456789012', 'uploads/patients/1769420653_makabayan.png', '2026-01-26 09:44:13', 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_verification_token` (`email_verification_token`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_user` (`user_id`);

--
-- Indexes for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `regpatient`
--
ALTER TABLE `regpatient`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_verification_token` (`email_verification_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `regpatient`
--
ALTER TABLE `regpatient`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

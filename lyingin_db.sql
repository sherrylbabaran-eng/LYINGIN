-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 01:11 PM
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
-- Database: `lyingin_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'admin', 'admin@lyingin.local', '$2y$10$ThPcOeQxlES.PD7L0wcxOeP8Z.OTQE1plfI92Ln8LUkUYCjEzR8qO', '2026-03-02 11:42:22');

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
(8, 'q', '121212', 'aa', 'deguzman.alessandranicole@ncst.edu.ph', '2121212', 'a', 'nix', '$2y$10$zZB0iKAyTsMUatc3ELIrLuB78WL3OPPHSrwBMko.aDmYDaVK/YgLO', '', '123321312331', NULL, NULL, '2026-01-28 05:12:35', 'C:\\xampp\\htdocs\\THESIS\\LYINGIN\\auth\\api/../../uploads/clinics/1769577155_BUTTON.png', 1, NULL, NULL),
(9, 'Sandra', '121321231', 'La Paz Homes 1, Cabezas, Cabezas (Palawit), Trece Martires, Cavite, Calabarzon, 4109, Philippines', 'sandra012205@gmail.com', '09652379023', 'aaa', 'aaa', '$2y$10$LbVnPKA6/RDsFuATUj50nOWhnODNNQQvTksE8dDPldMeoUbbh0y92', '', '592143093629', 14.269574, 120.889849, '2026-03-01 13:31:23', '0fdc733797757267e19666cb219a797e.png', 0, '140107', '2026-03-01 14:41:23'),
(10, 'Sandra', '77777777777', 'MetroGate Trece Martires, Gregorio (Aliang), Trece Martires, Cavite, Calabarzon, 4109, Philippines', 'gosala6033@bultoc.com', '09652379023', 'ok', 'ok', '$2y$10$.nn5oqw04/Gjs6rJwI8opuT1sob83RTFB6hIFDvtLSVefAOEllGFC', '', '592143093629', 14.305506, 120.872683, '2026-03-01 13:49:56', '6377f24e7530de32cd4412b3897d177a.png', 0, '412470', '2026-03-01 14:59:56'),
(11, 'niew', '1211212', 'De Ocampo (Quintana I), Trece Martires, Cavite, Calabarzon, 4109, Philippines', 'jolitah212@bultoc.com', '2121212', 'hey', 'eeee', '$2y$10$kR1sCE6TZ5j7P8o065WPgu5qT2wr4B7q2ZC.YWZiP2y97FefgqEzy', '', '12312323123', 14.302312, 120.84865, '2026-03-01 13:51:42', 'a84d352b28b9c593671ade1382cef38e.png', 0, '987288', '2026-03-01 15:01:42'),
(12, 'yyyuy', '433242342', 'Pinagtipunan, General Trias, Cavite, Calabarzon, 4107, Philippines', 'lavid85683@creteanu.com', '2121212', 'seth', 'settings', '$2y$10$8J2xirKdHQeZLf0YuLNvGePhNje4e9UT5P.GUe.hG600is/YZw9dy', 'Passport', '54454545', 14.370834, 120.878863, '2026-03-01 14:01:17', '8fef0f23fa026c387c68e67b84923ca9.png', 0, '742224', '2026-03-01 15:11:17'),
(13, 'uy', '32331313', 'Unable to retrieve address', 'tewil37151@creteanu.com', '43532324', 'ey', 'yeye', '$2y$10$YqJaty78EYyGbc0JrBQPnekCwgYdv8X.Fzn9JRrO9K7r2bLSNnowK', '', '665532323', 14.20028, 120.875839, '2026-03-01 14:08:36', '5a3bd83ae95c35c2a6d310afb6bbb2e6.png', 0, '729616', '2026-03-01 15:18:36'),
(14, 'Sandrae', '777777777770', 'Unable to retrieve address', 'fivoj59081@creteanu.com', '3132313123', 'eya', 'era', '$2y$10$ij2wfPDwhDruaeWdQCoNB.pTQDU7kBoublBepnvg.JYQIdOajNi6K', '', '112332424', 14.120786, 120.920609, '2026-03-01 14:22:15', '3c271b85efe7c27e4eb07ab9232a0b2e.png', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('patient','admin','superadmin','clinic') DEFAULT 'patient',
  `type` varchar(50) DEFAULT 'info',
  `title` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_type`, `type`, `title`, `icon`, `message`, `is_read`, `created_at`) VALUES
(2, 1, 'patient', 'info', NULL, NULL, 'Your next checkup is tomorrow at 9:00 AM.', 0, '2026-02-09 14:33:42'),
(3, 1, 'superadmin', 'success', 'New Clinic Registered', 'mdi-hospital-building', 'A new lying-in clinic has been registered and is pending verification.', 0, '2026-03-01 14:15:32'),
(4, 1, 'superadmin', 'warning', 'System Maintenance', 'mdi-cog', 'Scheduled system maintenance will occur this weekend.', 0, '2026-03-01 11:15:32'),
(5, 1, 'superadmin', 'info', 'Monthly Report Available', 'mdi-chart-bar', 'The monthly statistics report for February 2026 is now available.', 0, '2026-02-28 16:15:32'),
(9, 4, 'superadmin', 'success', 'New Clinic Registered', 'mdi-hospital-building', 'A new lying-in clinic has been registered and is pending verification.', 1, '2026-03-01 14:24:49'),
(10, 4, 'superadmin', 'warning', 'System Maintenance', 'mdi-cog', 'Scheduled system maintenance will occur this weekend.', 1, '2026-03-01 11:24:49'),
(11, 4, 'superadmin', 'info', 'Monthly Report Available', 'mdi-chart-bar', 'The monthly statistics report for February 2026 is now available.', 1, '2026-02-28 16:24:49');

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
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token_hash`, `expires_at`, `created_at`) VALUES
(5, 'tavoce6806@hopesx.com', '78b973a2c96fc1851fdcc58dd92cabb52fc58ea2ddcc9a64f6a6eae46194b7ee', '2026-02-09 13:29:16', '2026-02-09 12:59:16');

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
-- Table structure for table `patient_appointments`
--

CREATE TABLE `patient_appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `clinic_name` varchar(255) NOT NULL,
  `service` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_messages`
--

CREATE TABLE `patient_messages` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_messages`
--

INSERT INTO `patient_messages` (`id`, `patient_id`, `sender_name`, `subject`, `body`, `is_read`, `created_at`) VALUES
(1, 1, 'Clinic Staff', 'Appointment Update', 'Your appointment is confirmed.', 0, '2026-02-09 14:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `patient_pregnancy_tracker`
--

CREATE TABLE `patient_pregnancy_tracker` (
  `patient_id` int(11) NOT NULL,
  `lmp_date` date NOT NULL,
  `edd_date` date DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_records`
--

CREATE TABLE `patient_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `record_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `record_date` date NOT NULL,
  `record_time` time DEFAULT NULL,
  `status` enum('pending','completed','cancelled','no_show') NOT NULL DEFAULT 'completed',
  `remarks` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancy_weekly_tips`
--

CREATE TABLE `pregnancy_weekly_tips` (
  `id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `baby_development` text NOT NULL,
  `mother_condition` text NOT NULL,
  `reminders` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prenatal_checkups`
--

CREATE TABLE `prenatal_checkups` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `gestation_week` int(11) NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `face_verified` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `regpatient`
--

INSERT INTO `regpatient` (`id`, `first_name`, `last_name`, `birthdate`, `gender`, `email`, `contact_no`, `address`, `username`, `password`, `id_type`, `id_number`, `id_file`, `created_at`, `email_verified`, `face_verified`, `profile_image`) VALUES
(1, 'Alessandra', 'Nciol', '2005-01-22', 'female', 'tavoce6806@hopesx.com', '09652379023', '9 Leonforte Street, Imus, 4103, Philippines', 'tavoce6806', '$2y$10$iYdmgcarRsaqkEV3HNPTpuvRT3uzewttU6siDnMzxWpkJiHuG0w5W', 'national', '5921430936295184', 'C:\\xampp\\htdocs\\thesis\\LYINGIN\\auth\\api/../../uploads/patients/id_69880695d6dd77.18065218_ntionl.jpg', '2026-02-08 03:44:21', 1, 1, 'uploads/patients/sample-profile.jpg'),
(2, 'Alessandra', 'Nicole', '2005-01-22', 'female', 'sedeg61473@helesco.com', '09652379023', '28 Lanciano Street, Imus, 4103, Philippines', 'sedeg61473', '$2y$10$ddSzNSsykKUUhdm/lgx0nuyCFPrsSc3At8tQPHjrwZf5e5a/DIDQ.', 'national', '5921430936295184', 'C:\\xampp\\htdocs\\thesis\\LYINGIN\\auth\\api/../../uploads/patients/id_69880f21a8d033.11873138_ntionl.jpg', '2026-02-08 04:20:49', 1, 1, NULL),
(3, 'Alessandra', 'Nicole', '2005-01-22', 'female', 'modiya5932@codgal.com', '09652379023', 'Central Market Avenue, Dasmariñas, 4114, Philippines', 'modiya5932', '$2y$10$KbuH43XFSsA0YMrjUkyrVuKWysdnb8PmPBWZW6lnunpzHPP7R7QTe', 'national', '5921430936295184', 'C:\\xampp\\htdocs\\thesis\\LYINGIN\\auth\\api/../../uploads/patients/id_6989774c9177c0.85687341_ntionl.jpg', '2026-02-09 05:57:32', 1, 1, 'uploads/patients/30e9a542079217e5cb4dcd0c358986f0_1770619611.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `superadmin`
--

CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `superadmin`
--

INSERT INTO `superadmin` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(3, 'superadmin2', 'superadmin2@lyingin.local', '$2y$10$pRPMsXPJUaR8t/7F0qc1VesVYjc6wLaZGq1zjrbT/G.Rqv2ljdDC.', '2026-03-01 07:36:37'),
(4, 'superadmin3', 'superadmin3@lyingin.local', '$2y$10$nCzaGF97ndZqvFpGeDRQre9onguNaWDeKEiVj2pdtj59LmHciEWxS', '2026-03-01 07:47:02'),
(6, 'superadmin4', 'superadmin4@lyingin.local', '$2y$10$/d15g0uRtSM.jaStV9jx1egoxjoG02Zq870sPTSDWTu0oGUgbCWLi', '2026-03-02 11:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `system_messages`
--

CREATE TABLE `system_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('admin','superadmin','clinic','system') NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_type` enum('admin','superadmin','clinic') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_messages`
--

INSERT INTO `system_messages` (`id`, `sender_id`, `sender_type`, `sender_name`, `recipient_id`, `recipient_type`, `subject`, `body`, `is_read`, `created_at`) VALUES
(1, 2, 'admin', 'Admin User', 1, 'superadmin', 'Clinic Verification Request', 'Please review the newly registered clinic in Dasmarinas, Cavite.', 0, '2026-03-01 15:45:32'),
(2, 0, 'system', 'System', 1, 'superadmin', 'Database Backup Complete', 'Automated database backup completed successfully.', 0, '2026-03-01 14:15:32'),
(3, 2, 'admin', 'Admin User', 1, 'superadmin', 'Patient Report Issue', 'There is an issue with patient report generation that needs attention.', 0, '2026-03-01 12:15:32'),
(4, 3, 'admin', 'Admin User', 4, 'superadmin', 'Clinic Verification Request', 'Please review the newly registered clinic in Dasmarinas, Cavite.', 1, '2026-03-01 15:54:58'),
(5, 0, 'system', 'System', 4, 'superadmin', 'Database Backup Complete', 'Automated database backup completed successfully.', 1, '2026-03-01 14:24:58'),
(6, 3, 'admin', 'Admin User', 4, 'superadmin', 'Patient Report Issue', 'There is an issue with patient report generation that needs attention.', 1, '2026-03-01 12:24:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

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
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_password_resets_token` (`token_hash`),
  ADD KEY `idx_password_resets_email` (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `patient_appointments`
--
ALTER TABLE `patient_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_appointments_patient` (`patient_id`),
  ADD KEY `idx_patient_appointments_date` (`appointment_date`);

--
-- Indexes for table `patient_messages`
--
ALTER TABLE `patient_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_messages_patient` (`patient_id`);

--
-- Indexes for table `patient_pregnancy_tracker`
--
ALTER TABLE `patient_pregnancy_tracker`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `patient_records`
--
ALTER TABLE `patient_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_records_patient` (`patient_id`),
  ADD KEY `idx_patient_records_date` (`record_date`);

--
-- Indexes for table `pregnancy_weekly_tips`
--
ALTER TABLE `pregnancy_weekly_tips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_week` (`week_number`);

--
-- Indexes for table `prenatal_checkups`
--
ALTER TABLE `prenatal_checkups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prenatal_patient` (`patient_id`),
  ADD KEY `idx_prenatal_date` (`checkup_date`);

--
-- Indexes for table `regpatient`
--
ALTER TABLE `regpatient`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `superadmin`
--
ALTER TABLE `superadmin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `system_messages`
--
ALTER TABLE `system_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_id`,`recipient_type`,`is_read`),
  ADD KEY `idx_sender` (`sender_id`,`sender_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `patient_appointments`
--
ALTER TABLE `patient_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_messages`
--
ALTER TABLE `patient_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patient_records`
--
ALTER TABLE `patient_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pregnancy_weekly_tips`
--
ALTER TABLE `pregnancy_weekly_tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prenatal_checkups`
--
ALTER TABLE `prenatal_checkups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regpatient`
--
ALTER TABLE `regpatient`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `superadmin`
--
ALTER TABLE `superadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_messages`
--
ALTER TABLE `system_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `patient_appointments`
--
ALTER TABLE `patient_appointments`
  ADD CONSTRAINT `fk_patient_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `regpatient` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_pregnancy_tracker`
--
ALTER TABLE `patient_pregnancy_tracker`
  ADD CONSTRAINT `fk_patient_tracker_patient` FOREIGN KEY (`patient_id`) REFERENCES `regpatient` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_records`
--
ALTER TABLE `patient_records`
  ADD CONSTRAINT `fk_patient_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `regpatient` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prenatal_checkups`
--
ALTER TABLE `prenatal_checkups`
  ADD CONSTRAINT `fk_prenatal_patient` FOREIGN KEY (`patient_id`) REFERENCES `regpatient` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

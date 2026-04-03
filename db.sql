-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: sql211.infinityfree.com
-- Üretim Zamanı: 03 Nis 2026, 10:50:29
-- Sunucu sürümü: 11.4.10-MariaDB
-- PHP Sürümü: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `if0_40197167_puantaj`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cycle_config`
--

CREATE TABLE `cycle_config` (
  `id` int(11) NOT NULL,
  `cycle_start_date` date NOT NULL COMMENT '4 haftalık döngünün başlangıç tarihi (Pazartesi)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `cycle_config`
--

INSERT INTO `cycle_config` (`id`, `cycle_start_date`, `created_at`) VALUES
(1, '2026-03-30', '2026-03-27 22:10:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `date` date NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `holidays`
--

INSERT INTO `holidays` (`id`, `name`, `date`, `is_recurring`, `created_at`) VALUES
(1, 'Yılbaşı', '2025-01-01', 1, '2026-03-27 22:10:43'),
(2, 'Ulusal Egemenlik ve Çocuk Bayramı', '2025-04-23', 1, '2026-03-27 22:10:43'),
(3, 'Emek ve Dayanışma Günü', '2025-05-01', 1, '2026-03-27 22:10:43'),
(4, 'Atatürkü Anma Gençlik ve Spor Bayramı', '2025-05-19', 1, '2026-03-27 22:10:43'),
(5, 'Zafer Bayramı', '2025-08-30', 1, '2026-03-27 22:10:43'),
(6, 'Cumhuriyet Bayramı', '2025-10-29', 1, '2026-03-27 22:10:43');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `institutions`
--

CREATE TABLE `institutions` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `institutions`
--

INSERT INTO `institutions` (`id`, `name`, `description`, `address`, `phone`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Çeşme Belediyesi', 'Çeşme Belediyesi Kültür Müdürlüğü', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', '2026-03-29 02:37:52');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `leave_records`
--

CREATE TABLE `leave_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','unpaid','maternity','marriage','bereavement','other') NOT NULL DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_title', 'Puantaj Sistemi', 'Site başlığı', '2026-03-27 22:10:43', NULL),
(2, 'company_name', 'Çeşme Belediyesi', 'Kurum adı', '2026-03-27 22:10:43', NULL),
(3, 'work_start_time', '08:30', 'Varsayılan mesai başlangıç', '2026-03-27 22:10:43', '2026-03-27 12:17:22'),
(4, 'work_end_time', '17:30', 'Varsayılan mesai bitiş', '2026-03-27 22:10:43', '2026-03-27 12:17:22'),
(5, 'break_duration', '60', 'Varsayılan öğle arası (dakika)', '2026-03-27 22:10:43', NULL),
(6, 'annual_leave_days', '14', 'Yıllık izin hakkı (gün)', '2026-03-27 22:10:43', NULL),
(7, 'theme_color', '#2563eb', 'Ana tema rengi', '2026-03-27 22:10:43', NULL),
(8, 'footer_text', '© 2026 Puantaj Sistemi. Tüm hakları saklıdır. Created by ilhan Akdeniz', 'Footer metni', '2026-03-27 22:10:43', '2026-03-29 03:09:08'),
(9, 'cycle_weeks', '4', 'Döngü süresi (hafta)', '2026-03-27 22:10:43', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `institution_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `units`
--

INSERT INTO `units` (`id`, `institution_id`, `name`, `description`, `address`, `phone`, `manager_name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Çakabey Kültür Merkezi', 'Çakabey Kültür Merkezi Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(2, 1, 'Çeşme Amfi', 'Çeşme Amfi Tiyatro Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(3, 1, 'Alaçatı Amfi', 'Alaçatı Amfi Tiyatro Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(4, 1, 'Kilise', 'Kilise Kültür Merkezi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(5, 1, 'Müze', 'Müze Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(6, 1, 'STK', 'Sivil Toplum Kuruluşları Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(7, 1, 'Ilıca Kültür Merkezi', 'Ilıca Kültür Merkezi Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL),
(8, 1, 'Reisdere Kültür Merkezi', 'Reisdere Kültür Merkezi Birimi', NULL, NULL, NULL, 1, '2026-03-27 22:10:43', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tc_no` varchar(11) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Erkek','Kadın','Diğer') DEFAULT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `unit_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `address`, `tc_no`, `birth_date`, `gender`, `role`, `unit_id`, `position`, `hire_date`, `profile_image`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'djmaster', '$2y$10$3Ru4qvvpPbm9mbC6YBl0JOvh.mCYeXFg/Lg.FKLBCyzM5wZkbnPTO', 'İlhan Akdeniz', 'djmaster35@gmail.com', '5512208104', NULL, NULL, NULL, 'Erkek', 'admin', 1, NULL, '2026-03-27', NULL, 1, '2026-03-27 12:28:52', '2026-03-29 05:14:41'),
(5, 'dilek', '$2y$10$bYm7TS2ogUV8CnK.4YDCIeyIM8QGNP4SOTerxEnLxW85Ub.rwaZ42', 'Dilek Karaoğlu', NULL, '5512208104', NULL, NULL, NULL, NULL, 'employee', 1, NULL, '2026-03-27', NULL, 1, '2026-03-27 13:11:06', '2026-03-29 04:59:50'),
(6, 'nilüfer', '$2y$10$tpjTEBeoiST1cVMLVKPznuotLuY9NLXcMnSDj7D6Xz8kGIjeY1o8C', 'Nilüfer Eriş', NULL, '5546425455', NULL, NULL, NULL, NULL, 'employee', 4, NULL, '2026-03-27', NULL, 1, '2026-03-27 13:15:27', NULL),
(7, 'test', '$2y$10$2AfeXQ64LNQ5S3SF.CbBmex5jrPDxVBLrOq8Rn0fu1zEKBI4HtBHe', 'Test Kullanıcı', 'djmaster35@gmail.com', '5512208104', NULL, '11234564561', NULL, 'Erkek', 'admin', 1, NULL, '2026-03-29', NULL, 1, '2026-03-29 00:20:35', '2026-03-29 04:54:48'),
(8, 'huseyin', '$2y$10$vMn8jCrDRnjEhfOjnZ9lQOxTi1IfBYHf9cNwz7/NEYQxKnZw6xsl2', 'Hüseyin Bağcı', NULL, NULL, NULL, NULL, NULL, NULL, 'employee', 4, NULL, '2026-03-29', NULL, 1, '2026-03-29 05:40:46', NULL),
(9, 'recep', '$2y$10$j3xCw.vBDljds..Z3gtf5OuDuUW3fAUM4D8HlCWvCJe2k3h6jtJkG', 'Recep Demir', NULL, NULL, NULL, NULL, NULL, NULL, 'employee', 4, NULL, '2026-03-29', NULL, 1, '2026-03-29 05:44:10', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `work_records`
--

CREATE TABLE `work_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `work_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `break_minutes` int(11) DEFAULT 0,
  `overtime_minutes` int(11) DEFAULT 0,
  `status` enum('present','absent','leave','sick','holiday','half_day') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `work_templates`
--

CREATE TABLE `work_templates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `day_index` int(11) NOT NULL COMMENT '0-27 arası, 4 haftalık döngü',
  `unit_id` int(11) DEFAULT NULL,
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '17:00:00',
  `break_minutes` int(11) DEFAULT 60,
  `status` enum('present','absent','leave','sick','holiday','half_day') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `work_templates`
--

INSERT INTO `work_templates` (`id`, `user_id`, `day_index`, `unit_id`, `start_time`, `end_time`, `break_minutes`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(477, 5, 0, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(478, 5, 1, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(479, 5, 2, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(480, 5, 3, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(481, 5, 4, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(482, 5, 5, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(483, 5, 6, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(484, 5, 7, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(485, 5, 8, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(486, 5, 9, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(487, 5, 10, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(488, 5, 11, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(489, 5, 12, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(490, 5, 13, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(491, 5, 14, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(492, 5, 15, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(493, 5, 16, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(494, 5, 17, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(495, 5, 18, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(496, 5, 19, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(497, 5, 20, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(498, 5, 21, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(499, 5, 22, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(500, 5, 23, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(501, 5, 24, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(502, 5, 25, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:11:06', NULL),
(503, 5, 26, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(504, 5, 27, 1, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:11:06', '2026-03-29 05:37:46'),
(589, 6, 0, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(590, 6, 1, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(591, 6, 2, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(592, 6, 3, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(593, 6, 4, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(594, 6, 5, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(595, 6, 6, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(596, 6, 7, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(597, 6, 8, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(598, 6, 9, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(599, 6, 10, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(600, 6, 11, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(601, 6, 12, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(602, 6, 13, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(603, 6, 14, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(604, 6, 15, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(605, 6, 16, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(606, 6, 17, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(607, 6, 18, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(608, 6, 19, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(609, 6, 20, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(610, 6, 21, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(611, 6, 22, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(612, 6, 23, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(613, 6, 24, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(614, 6, 25, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-27 13:15:27', NULL),
(615, 6, 26, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(616, 6, 27, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-27 13:15:27', '2026-03-29 05:39:43'),
(701, 7, 0, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(702, 7, 1, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(703, 7, 2, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(704, 7, 3, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(705, 7, 4, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(706, 7, 5, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(707, 7, 6, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(708, 7, 7, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(709, 7, 8, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(710, 7, 9, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(711, 7, 10, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(712, 7, 11, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(713, 7, 12, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(714, 7, 13, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(715, 7, 14, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(716, 7, 15, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(717, 7, 16, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(718, 7, 17, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(719, 7, 18, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(720, 7, 19, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(721, 7, 20, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(722, 7, 21, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(723, 7, 22, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(724, 7, 23, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(725, 7, 24, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(726, 7, 25, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 00:20:35', NULL),
(727, 7, 26, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(728, 7, 27, 1, NULL, NULL, 0, 'holiday', NULL, '2026-03-29 00:20:35', '2026-03-29 04:16:54'),
(1317, 4, 0, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1318, 4, 1, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1319, 4, 2, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1320, 4, 3, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1321, 4, 4, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1322, 4, 5, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1323, 4, 6, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1324, 4, 7, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1325, 4, 8, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1326, 4, 9, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1327, 4, 10, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1328, 4, 11, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1329, 4, 12, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1330, 4, 13, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1331, 4, 14, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1332, 4, 15, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1333, 4, 16, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1334, 4, 17, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1335, 4, 18, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1336, 4, 19, 1, '08:30:00', '17:30:00', 0, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1337, 4, 20, 1, '08:30:00', '17:30:00', 0, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1338, 4, 21, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1339, 4, 22, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1340, 4, 23, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1341, 4, 24, 1, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1342, 4, 25, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:28:44', NULL),
(1343, 4, 26, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1344, 4, 27, 1, '08:30:00', '17:30:00', 0, 'holiday', NULL, '2026-03-29 05:28:44', '2026-03-29 05:34:59'),
(1653, 8, 0, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1654, 8, 1, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1655, 8, 2, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1656, 8, 3, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1657, 8, 4, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1658, 8, 5, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1659, 8, 6, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1660, 8, 7, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1661, 8, 8, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1662, 8, 9, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1663, 8, 10, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1664, 8, 11, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1665, 8, 12, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1666, 8, 13, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1667, 8, 14, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1668, 8, 15, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1669, 8, 16, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1670, 8, 17, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1671, 8, 18, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1672, 8, 19, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1673, 8, 20, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1674, 8, 21, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1675, 8, 22, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1676, 8, 23, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1677, 8, 24, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1678, 8, 25, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:40:46', NULL),
(1679, 8, 26, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1680, 8, 27, 4, '08:30:00', '17:30:00', 0, 'present', NULL, '2026-03-29 05:40:46', '2026-03-29 05:42:51'),
(1765, 9, 0, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:46:40'),
(1766, 9, 1, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:46:40'),
(1767, 9, 2, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1768, 9, 3, 1, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1769, 9, 4, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1770, 9, 5, 4, NULL, NULL, 0, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1771, 9, 6, 4, NULL, NULL, 0, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1772, 9, 7, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1773, 9, 8, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1774, 9, 9, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1775, 9, 10, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1776, 9, 11, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1777, 9, 12, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1778, 9, 13, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1779, 9, 14, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:46:40'),
(1780, 9, 15, 4, '08:30:00', '17:30:00', 60, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:46:40'),
(1781, 9, 16, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1782, 9, 17, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1783, 9, 18, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1784, 9, 19, 4, NULL, NULL, 0, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1785, 9, 20, 4, NULL, NULL, 0, 'present', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1786, 9, 21, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1787, 9, 22, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1788, 9, 23, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1789, 9, 24, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1790, 9, 25, 4, '08:30:00', '17:30:00', 60, 'present', NULL, '2026-03-29 05:44:10', NULL),
(1791, 9, 26, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41'),
(1792, 9, 27, 4, NULL, NULL, 0, 'leave', NULL, '2026-03-29 05:44:10', '2026-03-29 05:48:41');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `cycle_config`
--
ALTER TABLE `cycle_config`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_date` (`date`);

--
-- Tablo için indeksler `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `leave_records`
--
ALTER TABLE `leave_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`);

--
-- Tablo için indeksler `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Tablo için indeksler `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_institution` (`institution_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_unit` (`unit_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- Tablo için indeksler `work_records`
--
ALTER TABLE `work_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_date` (`user_id`,`work_date`),
  ADD KEY `idx_date` (`work_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_unit` (`unit_id`);

--
-- Tablo için indeksler `work_templates`
--
ALTER TABLE `work_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_day` (`user_id`,`day_index`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `cycle_config`
--
ALTER TABLE `cycle_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `leave_records`
--
ALTER TABLE `leave_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `work_records`
--
ALTER TABLE `work_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=740;

--
-- Tablo için AUTO_INCREMENT değeri `work_templates`
--
ALTER TABLE `work_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1989;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `leave_records`
--
ALTER TABLE `leave_records`
  ADD CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_unit_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `work_records`
--
ALTER TABLE `work_records`
  ADD CONSTRAINT `fk_work_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `work_templates`
--
ALTER TABLE `work_templates`
  ADD CONSTRAINT `fk_template_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

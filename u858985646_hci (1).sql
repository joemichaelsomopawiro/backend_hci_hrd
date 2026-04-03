-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 26, 2026 at 03:28 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u858985646_hci`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_pin` varchar(20) DEFAULT NULL COMMENT 'PIN/UserID dari mesin absensi',
  `user_name` varchar(100) DEFAULT NULL COMMENT 'Nama user dari mesin absensi',
  `card_number` varchar(20) DEFAULT NULL COMMENT 'Nomor kartu dari mesin absensi',
  `date` date NOT NULL COMMENT 'Tanggal absensi',
  `check_in` time DEFAULT NULL COMMENT 'Waktu tap pertama (masuk)',
  `check_out` time DEFAULT NULL COMMENT 'Waktu tap terakhir (pulang)',
  `status` enum('present_ontime','present_late','absent','on_leave','sick_leave','permission') NOT NULL DEFAULT 'absent',
  `work_hours` decimal(5,2) DEFAULT NULL COMMENT 'Total jam kerja (dalam jam)',
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Jam lembur',
  `late_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Menit keterlambatan',
  `early_leave_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Menit pulang cepat',
  `total_taps` int(11) NOT NULL DEFAULT 0 COMMENT 'Total jumlah tap dalam sehari',
  `notes` text DEFAULT NULL COMMENT 'Catatan tambahan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attendance_machine_id` bigint(20) UNSIGNED NOT NULL,
  `user_pin` varchar(20) NOT NULL COMMENT 'PIN/UserID dari mesin (bisa NIK atau NumCard)',
  `user_name` varchar(100) DEFAULT NULL COMMENT 'Nama user dari mesin absensi',
  `card_number` varchar(20) DEFAULT NULL COMMENT 'Nomor kartu dari mesin absensi',
  `datetime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Tanggal dan waktu tap dari mesin',
  `verified_method` enum('card','fingerprint','face','password') NOT NULL DEFAULT 'card' COMMENT 'Metode verifikasi',
  `verified_code` int(11) NOT NULL COMMENT 'Kode verifikasi dari mesin',
  `status_code` enum('check_in','check_out','break_out','break_in','overtime_in','overtime_out') NOT NULL DEFAULT 'check_in' COMMENT 'Status dari mesin',
  `is_processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apakah sudah diproses menjadi attendance',
  `raw_data` text DEFAULT NULL COMMENT 'Data mentah dari mesin',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_machines`
--

CREATE TABLE `attendance_machines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Nama mesin absensi',
  `ip_address` varchar(15) NOT NULL COMMENT 'IP Address mesin',
  `port` int(11) NOT NULL DEFAULT 80 COMMENT 'Port untuk SOAP Web Service',
  `comm_key` varchar(10) NOT NULL DEFAULT '0' COMMENT 'Communication Key',
  `device_id` varchar(50) DEFAULT NULL COMMENT 'Device ID mesin',
  `serial_number` varchar(50) DEFAULT NULL COMMENT 'Serial number mesin',
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu sync terakhir',
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Pengaturan tambahan mesin' CHECK (json_valid(`settings`)),
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sync_logs`
--

CREATE TABLE `attendance_sync_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attendance_machine_id` bigint(20) UNSIGNED NOT NULL,
  `operation` enum('pull_data','pull_today_data','pull_current_month_data','pull_user_data','push_user','delete_user','clear_data','sync_time','restart_machine','test_connection') NOT NULL,
  `status` enum('success','failed','partial') NOT NULL DEFAULT 'failed',
  `message` text DEFAULT NULL COMMENT 'Pesan hasil operasi',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detail operasi' CHECK (json_valid(`details`)),
  `records_processed` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah record yang diproses',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu mulai operasi',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai operasi',
  `duration` decimal(8,3) DEFAULT NULL COMMENT 'Durasi operasi (detik)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `benefits`
--

CREATE TABLE `benefits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `benefit_type` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_roles`
--

CREATE TABLE `custom_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `access_level` enum('employee','manager','hr_readonly','hr_full') NOT NULL DEFAULT 'employee',
  `department` enum('hr','production','distribution','executive') DEFAULT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deadlines`
--

CREATE TABLE `deadlines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `episode_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('kreatif','musik_arr','sound_eng','produksi','editor','art_set_design','design_grafis','promotion','broadcasting','quality_control') NOT NULL,
  `deadline_date` datetime NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `reminder_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama_lengkap` varchar(255) NOT NULL,
  `nik` varchar(16) NOT NULL,
  `nip` varchar(20) DEFAULT NULL,
  `NumCard` varchar(10) DEFAULT NULL COMMENT 'Nomor kartu absensi 10 digit',
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
  `alamat` text NOT NULL,
  `status_pernikahan` enum('Belum Menikah','Menikah','Cerai') NOT NULL,
  `jabatan_saat_ini` enum('Art & Set Design','Creative','Distribution Manager','Editor','Editor Promotion','Employee','Finance','GA','General Affairs','Graphic Design','HR','Hopeline Care','Karyawan','Office Assistant','President Director','Producer','Production','Program Manager','Promotion','Quality Control','Social Media','Sound Engineer','VP President') DEFAULT 'Employee',
  `department` enum('hr','production','distribution','executive') DEFAULT NULL,
  `manager_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_from` varchar(255) DEFAULT NULL COMMENT 'Source of employee creation: manual, attendance_machine, import, etc',
  `tanggal_mulai_kerja` date NOT NULL,
  `tingkat_pendidikan` varchar(50) NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL,
  `tunjangan` decimal(15,2) DEFAULT 0.00,
  `bonus` decimal(15,2) DEFAULT 0.00,
  `nomor_bpjs_kesehatan` varchar(20) DEFAULT NULL,
  `nomor_bpjs_ketenagakerjaan` varchar(20) DEFAULT NULL,
  `npwp` varchar(20) DEFAULT NULL,
  `nomor_kontrak` varchar(50) DEFAULT NULL,
  `tanggal_kontrak_berakhir` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attendance_machine_id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `machine_user_id` varchar(255) NOT NULL COMMENT 'ID Number dari mesin',
  `name` varchar(255) NOT NULL COMMENT 'Nama dari mesin',
  `card_number` varchar(255) DEFAULT NULL COMMENT 'Card number dari mesin',
  `department` varchar(255) DEFAULT NULL COMMENT 'Department dari mesin',
  `privilege` varchar(255) DEFAULT NULL COMMENT 'Privilege dari mesin (User, Admin, etc)',
  `group_name` varchar(255) DEFAULT NULL COMMENT 'Group dari mesin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Data mentah dari mesin' CHECK (json_valid(`raw_data`)),
  `last_seen_at` timestamp NULL DEFAULT NULL COMMENT 'Terakhir terlihat di mesin',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employment_histories`
--

CREATE TABLE `employment_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_quotas`
--

CREATE TABLE `leave_quotas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `year` year(4) NOT NULL,
  `annual_leave_quota` int(11) NOT NULL DEFAULT 12,
  `annual_leave_used` int(11) NOT NULL DEFAULT 0,
  `sick_leave_quota` int(11) NOT NULL DEFAULT 12,
  `sick_leave_used` int(11) NOT NULL DEFAULT 0,
  `emergency_leave_quota` int(11) NOT NULL DEFAULT 2,
  `emergency_leave_used` int(11) NOT NULL DEFAULT 0,
  `maternity_leave_quota` int(11) NOT NULL DEFAULT 90,
  `maternity_leave_used` int(11) NOT NULL DEFAULT 0,
  `paternity_leave_quota` int(11) NOT NULL DEFAULT 7,
  `paternity_leave_used` int(11) NOT NULL DEFAULT 0,
  `marriage_leave_quota` int(11) NOT NULL DEFAULT 3,
  `marriage_leave_used` int(11) NOT NULL DEFAULT 0,
  `bereavement_leave_quota` int(11) NOT NULL DEFAULT 3,
  `bereavement_leave_used` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `leave_type` enum('annual','sick','emergency','maternity','paternity','marriage','bereavement') DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `overall_status` enum('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `employee_signature_path` varchar(255) DEFAULT NULL,
  `approver_signature_path` varchar(255) DEFAULT NULL,
  `leave_location` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `morning_reflection_attendance`
--

CREATE TABLE `morning_reflection_attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `status` enum('Hadir','Terlambat','Absen','izin','Cuti') NOT NULL DEFAULT 'Hadir',
  `join_time` timestamp NULL DEFAULT NULL,
  `testing_mode` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attendance_method` enum('online','manual') DEFAULT NULL,
  `attendance_source` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `national_holidays`
--

CREATE TABLE `national_holidays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('national','custom','weekend') NOT NULL DEFAULT 'national',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(255) NOT NULL,
  `otp_code` varchar(255) NOT NULL,
  `type` enum('register','forgot_password') NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotion_histories`
--

CREATE TABLE `promotion_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `promotion_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainings`
--

CREATE TABLE `trainings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `training_name` varchar(255) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `role` enum('Art & Set Design','Creative','Distribution Manager','Editor','Editor Promotion','Employee','Finance','GA','General Affairs','Graphic Design','HR','Hopeline Care','Karyawan','Office Assistant','President Director','Producer','Production','Program Manager','Promotion','Quality Control','Social Media','Sound Engineer','VP President') DEFAULT 'Employee',
  `access_level` enum('employee','manager','hr_readonly','hr_full','director') NOT NULL DEFAULT 'employee',
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worship_attendance`
--

CREATE TABLE `worship_attendance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worship_attendances`
--

CREATE TABLE `worship_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent','leave') NOT NULL DEFAULT 'absent',
  `join_time` time DEFAULT NULL,
  `leave_time` time DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `testing_mode` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worship_config`
--

CREATE TABLE `worship_config` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worship_configs`
--

CREATE TABLE `worship_configs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zoom_links`
--

CREATE TABLE `zoom_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `zoom_link` varchar(500) NOT NULL,
  `meeting_id` varchar(100) DEFAULT NULL,
  `passcode` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendances_employee_id_date_unique` (`employee_id`,`date`),
  ADD KEY `attendances_date_status_index` (`date`,`status`),
  ADD KEY `attendances_employee_id_date_status_index` (`employee_id`,`date`,`status`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_logs_attendance_machine_id_foreign` (`attendance_machine_id`),
  ADD KEY `attendance_logs_employee_id_datetime_index` (`datetime`),
  ADD KEY `attendance_logs_user_pin_datetime_index` (`user_pin`,`datetime`),
  ADD KEY `attendance_logs_datetime_is_processed_index` (`datetime`,`is_processed`),
  ADD KEY `attendance_logs_is_processed_index` (`is_processed`);

--
-- Indexes for table `attendance_machines`
--
ALTER TABLE `attendance_machines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendance_machines_ip_address_unique` (`ip_address`),
  ADD KEY `attendance_machines_status_last_sync_at_index` (`status`,`last_sync_at`);

--
-- Indexes for table `attendance_sync_logs`
--
ALTER TABLE `attendance_sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_sync_logs_attendance_machine_id_operation_index` (`attendance_machine_id`,`operation`),
  ADD KEY `attendance_sync_logs_status_started_at_index` (`status`,`started_at`),
  ADD KEY `attendance_sync_logs_started_at_index` (`started_at`);

--
-- Indexes for table `benefits`
--
ALTER TABLE `benefits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `benefits_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `custom_roles`
--
ALTER TABLE `custom_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `custom_roles_role_name_unique` (`role_name`),
  ADD KEY `custom_roles_created_by_foreign` (`created_by`),
  ADD KEY `custom_roles_is_active_access_level_index` (`is_active`,`access_level`),
  ADD KEY `custom_roles_department_is_active_index` (`department`,`is_active`),
  ADD KEY `custom_roles_supervisor_id_index` (`supervisor_id`);

--
-- Indexes for table `deadlines`
--
ALTER TABLE `deadlines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employees_nik_unique` (`nik`),
  ADD UNIQUE KEY `employees_nip_unique` (`nip`),
  ADD UNIQUE KEY `employees_numcard_unique` (`NumCard`),
  ADD KEY `employees_manager_id_foreign` (`manager_id`),
  ADD KEY `employees_created_from_index` (`created_from`);

--
-- Indexes for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_machine_user` (`attendance_machine_id`,`machine_user_id`),
  ADD KEY `employee_attendance_attendance_machine_id_machine_user_id_index` (`attendance_machine_id`,`machine_user_id`),
  ADD KEY `employee_attendance_machine_user_id_index` (`machine_user_id`),
  ADD KEY `employee_attendance_card_number_index` (`card_number`),
  ADD KEY `employee_attendance_name_index` (`name`),
  ADD KEY `employee_attendance_employee_id_index` (`employee_id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_documents_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `employment_histories`
--
ALTER TABLE `employment_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employment_histories_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `leave_quotas_employee_id_year_unique` (`employee_id`,`year`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_requests_employee_id_foreign` (`employee_id`),
  ADD KEY `leave_requests_approved_by_foreign` (`approved_by`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `morning_reflection_attendance`
--
ALTER TABLE `morning_reflection_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`employee_id`,`date`),
  ADD KEY `morning_reflection_attendance_date_attended_index` (`date`,`status`),
  ADD KEY `morning_reflection_attendance_user_id_date_index` (`employee_id`,`date`),
  ADD KEY `morning_reflection_attendance_attended_at_index` (`join_time`);

--
-- Indexes for table `national_holidays`
--
ALTER TABLE `national_holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `national_holidays_date_unique` (`date`),
  ADD KEY `national_holidays_created_by_foreign` (`created_by`),
  ADD KEY `national_holidays_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `otps_phone_type_index` (`phone`,`type`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `promotion_histories`
--
ALTER TABLE `promotion_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promotion_histories_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

--
-- Indexes for table `trainings`
--
ALTER TABLE `trainings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trainings_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`),
  ADD KEY `users_employee_id_foreign` (`employee_id`);

--
-- Indexes for table `worship_attendance`
--
ALTER TABLE `worship_attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `worship_attendances`
--
ALTER TABLE `worship_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `worship_attendances_user_id_date_unique` (`user_id`,`date`),
  ADD KEY `worship_attendances_created_by_foreign` (`created_by`),
  ADD KEY `worship_attendances_updated_by_foreign` (`updated_by`),
  ADD KEY `worship_attendances_date_status_index` (`date`,`status`),
  ADD KEY `worship_attendances_user_id_date_index` (`user_id`,`date`),
  ADD KEY `worship_attendances_testing_mode_index` (`testing_mode`);

--
-- Indexes for table `worship_config`
--
ALTER TABLE `worship_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `worship_config_key_unique` (`key`);

--
-- Indexes for table `worship_configs`
--
ALTER TABLE `worship_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `worship_configs_key_unique` (`key`);

--
-- Indexes for table `zoom_links`
--
ALTER TABLE `zoom_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zoom_links_is_active_index` (`is_active`),
  ADD KEY `zoom_links_created_by_index` (`created_by`),
  ADD KEY `zoom_links_updated_by_index` (`updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_machines`
--
ALTER TABLE `attendance_machines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sync_logs`
--
ALTER TABLE `attendance_sync_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `benefits`
--
ALTER TABLE `benefits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_roles`
--
ALTER TABLE `custom_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deadlines`
--
ALTER TABLE `deadlines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employment_histories`
--
ALTER TABLE `employment_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `morning_reflection_attendance`
--
ALTER TABLE `morning_reflection_attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `national_holidays`
--
ALTER TABLE `national_holidays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promotion_histories`
--
ALTER TABLE `promotion_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trainings`
--
ALTER TABLE `trainings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worship_attendance`
--
ALTER TABLE `worship_attendance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worship_attendances`
--
ALTER TABLE `worship_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worship_config`
--
ALTER TABLE `worship_config`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worship_configs`
--
ALTER TABLE `worship_configs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zoom_links`
--
ALTER TABLE `zoom_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_attendance_machine_id_foreign` FOREIGN KEY (`attendance_machine_id`) REFERENCES `attendance_machines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_sync_logs`
--
ALTER TABLE `attendance_sync_logs`
  ADD CONSTRAINT `attendance_sync_logs_attendance_machine_id_foreign` FOREIGN KEY (`attendance_machine_id`) REFERENCES `attendance_machines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `benefits`
--
ALTER TABLE `benefits`
  ADD CONSTRAINT `benefits_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_roles`
--
ALTER TABLE `custom_roles`
  ADD CONSTRAINT `custom_roles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_roles_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `custom_roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD CONSTRAINT `employee_attendance_attendance_machine_id_foreign` FOREIGN KEY (`attendance_machine_id`) REFERENCES `attendance_machines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_attendance_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employment_histories`
--
ALTER TABLE `employment_histories`
  ADD CONSTRAINT `employment_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_quotas`
--
ALTER TABLE `leave_quotas`
  ADD CONSTRAINT `leave_quotas_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `morning_reflection_attendance`
--
ALTER TABLE `morning_reflection_attendance`
  ADD CONSTRAINT `morning_reflection_attendance_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `national_holidays`
--
ALTER TABLE `national_holidays`
  ADD CONSTRAINT `national_holidays_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `national_holidays_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `promotion_histories`
--
ALTER TABLE `promotion_histories`
  ADD CONSTRAINT `promotion_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trainings`
--
ALTER TABLE `trainings`
  ADD CONSTRAINT `trainings_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `worship_attendances`
--
ALTER TABLE `worship_attendances`
  ADD CONSTRAINT `worship_attendances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `worship_attendances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `worship_attendances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

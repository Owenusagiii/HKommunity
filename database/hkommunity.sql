-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 12:51 PM
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
-- Database: `hkommunity_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--



-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `link` text DEFAULT NULL,
  `audience` enum('all','coordinators','scholars') NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--



-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coordinators`
--



-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(10) UNSIGNED NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--



-- --------------------------------------------------------

--
-- Table structure for table `duty_records`
--

CREATE TABLE `duty_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scholar_id` bigint(20) UNSIGNED NOT NULL,
  `renewal_period_id` bigint(20) UNSIGNED NOT NULL,
  `duty_date` date NOT NULL,
  `duty_type` varchar(100) NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `duty_submission_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `duty_records`
--



-- --------------------------------------------------------

--
-- Table structure for table `duty_submissions`
--

CREATE TABLE `duty_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scholar_id` bigint(20) UNSIGNED NOT NULL,
  `renewal_period_id` bigint(20) UNSIGNED NOT NULL,
  `duty_date` date NOT NULL,
  `duty_type` varchar(100) NOT NULL,
  `claimed_hours` decimal(5,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `duty_submissions`
--



-- --------------------------------------------------------

--
-- Table structure for table `duty_submission_files`
--

CREATE TABLE `duty_submission_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `submission_id` bigint(20) UNSIGNED NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `duty_submission_files`
--



-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `registration_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('requirement','duty','renewal','report','announcement','system') NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--



-- --------------------------------------------------------

--
-- Table structure for table `quick_links`
--

CREATE TABLE `quick_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `url` text NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quick_links`
--



-- --------------------------------------------------------

--
-- Table structure for table `registration_imports`
--

CREATE TABLE `registration_imports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` enum('csv','xlsx') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `imported_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_imports`
--



-- --------------------------------------------------------

--
-- Table structure for table `registration_records`
--

CREATE TABLE `registration_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `registration_import_id` bigint(20) UNSIGNED NOT NULL,
  `student_id_number` varchar(30) NOT NULL,
  `course` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL,
  `hk_discount_type` enum('HK5.5','HK7.5','HK10','HK12.5','HK17.5','HK25%','HK50%','HK55%','HK75%','HK80%') NOT NULL,
  `status` enum('valid','invalid') NOT NULL DEFAULT 'valid',
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_records`
--



-- --------------------------------------------------------

--
-- Table structure for table `renewals`
--

CREATE TABLE `renewals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scholar_id` bigint(20) UNSIGNED NOT NULL,
  `renewal_period_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('not_started','in_progress','for_review','approved','rejected','completed') NOT NULL DEFAULT 'not_started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `renewals`
--



-- --------------------------------------------------------

--
-- Table structure for table `renewal_history`
--

CREATE TABLE `renewal_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `renewal_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('not_started','in_progress','for_review','approved','rejected','completed') NOT NULL,
  `changed_by` bigint(20) UNSIGNED NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `renewal_history`
--



-- --------------------------------------------------------

--
-- Table structure for table `renewal_periods`
--

CREATE TABLE `renewal_periods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL,
  `required_duty_hours` decimal(5,2) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `renewal_periods`
--



-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `submitted_by` bigint(20) UNSIGNED NOT NULL,
  `submitted_to` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('for_review','resolved','rejected') NOT NULL DEFAULT 'for_review',
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--



-- --------------------------------------------------------

--
-- Table structure for table `requirements`
--

CREATE TABLE `requirements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requirements`
--



-- --------------------------------------------------------

--
-- Table structure for table `scholars`
--

CREATE TABLE `scholars` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `student_id_number` varchar(30) NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL,
  `hk_discount_type` enum('HK5.5','HK7.5','HK10','HK12.5','HK17.5','HK25%','HK50%','HK55%','HK75%','HK80%') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scholars`
--



-- --------------------------------------------------------

--
-- Table structure for table `scholar_registrations`
--

CREATE TABLE `scholar_registrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phinmaed_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `student_id_number` varchar(20) NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year') NOT NULL,
  `hk_discount_type` enum('HK5.5','HK7.5','HK10','HK12.5','HK17.5','HK25%','HK50%','HK55%','HK75%','HK80%') NOT NULL,
  `verification_status` enum('matched','mismatch','not_found') DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `matched_registration_record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scholar_registrations`
--



-- --------------------------------------------------------

--
-- Table structure for table `scholar_requirements`
--

CREATE TABLE `scholar_requirements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scholar_id` bigint(20) UNSIGNED NOT NULL,
  `requirement_id` bigint(20) UNSIGNED NOT NULL,
  `renewal_period_id` bigint(20) UNSIGNED NOT NULL,
  `gdrive_link` text DEFAULT NULL,
  `status` enum('assigned','submitted','approved','rejected') NOT NULL DEFAULT 'assigned',
  `rejection_reason` text DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scholar_requirements`
--



-- --------------------------------------------------------

--
-- Table structure for table `scholar_requirement_history`
--

CREATE TABLE `scholar_requirement_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scholar_requirement_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('assigned','submitted','approved','rejected') NOT NULL,
  `changed_by` bigint(20) UNSIGNED NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scholar_requirement_history`
--



-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `academic_year_id` bigint(20) UNSIGNED NOT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `semesters`
--



-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phinmaed_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','coordinator','scholar') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `phinmaed_email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'admin@phinmaed.com', '$2y$10$K/Ug8D5eF9SO/QP8YbEOM.TowC6ZrsfKT3z9L4f8aBamJh0mxElOG', 'admin', 'active', '2026-10-19 08:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `logged_in_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_logs`
--



--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_academic_years_academic_year` (`academic_year`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_created_by` (`created_by`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coordinators_user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `uq_courses_course_name` (`course_name`);

--
-- Indexes for table `duty_records`
--
ALTER TABLE `duty_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_duty_records_submission` (`duty_submission_id`),
  ADD KEY `idx_duty_records_scholar_id` (`scholar_id`),
  ADD KEY `idx_duty_records_renewal_period_id` (`renewal_period_id`),
  ADD KEY `idx_duty_records_coordinator_id` (`coordinator_id`);

--
-- Indexes for table `duty_submissions`
--
ALTER TABLE `duty_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_duty_submissions_scholar` (`scholar_id`),
  ADD KEY `idx_duty_submissions_period` (`renewal_period_id`),
  ADD KEY `idx_duty_submissions_status` (`status`),
  ADD KEY `fk_duty_submissions_reviewer` (`reviewed_by`);

--
-- Indexes for table `duty_submission_files`
--
ALTER TABLE `duty_submission_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_duty_submission_files_submission` (`submission_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_registration_id` (`registration_id`);

--
-- Indexes for table `quick_links`
--
ALTER TABLE `quick_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quick_links_created_by` (`created_by`);

--
-- Indexes for table `registration_imports`
--
ALTER TABLE `registration_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_imports_imported_by` (`imported_by`);

--
-- Indexes for table `registration_records`
--
ALTER TABLE `registration_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_records_import_id` (`registration_import_id`),
  ADD KEY `idx_registration_records_student_id_number` (`student_id_number`);

--
-- Indexes for table `renewals`
--
ALTER TABLE `renewals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_renewals_scholar_period` (`scholar_id`,`renewal_period_id`),
  ADD KEY `idx_renewals_renewal_period_id` (`renewal_period_id`);

--
-- Indexes for table `renewal_history`
--
ALTER TABLE `renewal_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_renewal_history_renewal` (`renewal_id`),
  ADD KEY `fk_renewal_history_changed_by` (`changed_by`);

--
-- Indexes for table `renewal_periods`
--
ALTER TABLE `renewal_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_renewal_periods_semester_year_level` (`semester_id`,`year_level`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_submitted_by` (`submitted_by`),
  ADD KEY `idx_reports_submitted_to` (`submitted_to`);

--
-- Indexes for table `requirements`
--
ALTER TABLE `requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scholars`
--
ALTER TABLE `scholars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_scholars_user_id` (`user_id`),
  ADD UNIQUE KEY `uq_scholars_student_id_number` (`student_id_number`),
  ADD KEY `idx_scholars_course_id` (`course_id`);

--
-- Indexes for table `scholar_registrations`
--
ALTER TABLE `scholar_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_scholar_registrations_matched_record` (`matched_registration_record_id`),
  ADD KEY `fk_scholar_registrations_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_scholar_registrations_email` (`phinmaed_email`),
  ADD KEY `idx_scholar_registrations_student_id` (`student_id_number`),
  ADD KEY `idx_scholar_registrations_status` (`status`),
  ADD KEY `idx_scholar_registrations_verification_status` (`verification_status`),
  ADD KEY `idx_scholar_registrations_course_id` (`course_id`);

--
-- Indexes for table `scholar_requirements`
--
ALTER TABLE `scholar_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_scholar_requirements_assignment` (`scholar_id`,`requirement_id`,`renewal_period_id`),
  ADD KEY `idx_scholar_requirements_requirement_id` (`requirement_id`),
  ADD KEY `idx_scholar_requirements_renewal_period_id` (`renewal_period_id`);

--
-- Indexes for table `scholar_requirement_history`
--
ALTER TABLE `scholar_requirement_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_scholar_requirement_history_requirement` (`scholar_requirement_id`),
  ADD KEY `fk_scholar_requirement_history_changed_by` (`changed_by`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_semesters_academic_year_semester` (`academic_year_id`,`semester`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_phinmaed_email` (`phinmaed_email`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_login_activities_logged_in_at` (`logged_in_at`),
  ADD KEY `idx_user_login_activities_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `duty_records`
--
ALTER TABLE `duty_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `duty_submissions`
--
ALTER TABLE `duty_submissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `duty_submission_files`
--
ALTER TABLE `duty_submission_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `quick_links`
--
ALTER TABLE `quick_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `registration_imports`
--
ALTER TABLE `registration_imports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `registration_records`
--
ALTER TABLE `registration_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `renewals`
--
ALTER TABLE `renewals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `renewal_history`
--
ALTER TABLE `renewal_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `renewal_periods`
--
ALTER TABLE `renewal_periods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `requirements`
--
ALTER TABLE `requirements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `scholars`
--
ALTER TABLE `scholars`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `scholar_registrations`
--
ALTER TABLE `scholar_registrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `scholar_requirements`
--
ALTER TABLE `scholar_requirements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `scholar_requirement_history`
--
ALTER TABLE `scholar_requirement_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD CONSTRAINT `fk_coordinators_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `duty_records`
--
ALTER TABLE `duty_records`
  ADD CONSTRAINT `fk_duty_records_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duty_records_renewal_period` FOREIGN KEY (`renewal_period_id`) REFERENCES `renewal_periods` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duty_records_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duty_records_submission` FOREIGN KEY (`duty_submission_id`) REFERENCES `duty_submissions` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `duty_submissions`
--
ALTER TABLE `duty_submissions`
  ADD CONSTRAINT `fk_duty_submissions_period` FOREIGN KEY (`renewal_period_id`) REFERENCES `renewal_periods` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duty_submissions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `coordinators` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duty_submissions_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `duty_submission_files`
--
ALTER TABLE `duty_submission_files`
  ADD CONSTRAINT `fk_duty_submission_files_submission` FOREIGN KEY (`submission_id`) REFERENCES `duty_submissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_registration` FOREIGN KEY (`registration_id`) REFERENCES `scholar_registrations` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `quick_links`
--
ALTER TABLE `quick_links`
  ADD CONSTRAINT `fk_quick_links_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `registration_imports`
--
ALTER TABLE `registration_imports`
  ADD CONSTRAINT `fk_registration_imports_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `registration_records`
--
ALTER TABLE `registration_records`
  ADD CONSTRAINT `fk_registration_records_import` FOREIGN KEY (`registration_import_id`) REFERENCES `registration_imports` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `renewals`
--
ALTER TABLE `renewals`
  ADD CONSTRAINT `fk_renewals_renewal_period` FOREIGN KEY (`renewal_period_id`) REFERENCES `renewal_periods` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_renewals_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `renewal_history`
--
ALTER TABLE `renewal_history`
  ADD CONSTRAINT `fk_renewal_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_renewal_history_renewal` FOREIGN KEY (`renewal_id`) REFERENCES `renewals` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `renewal_periods`
--
ALTER TABLE `renewal_periods`
  ADD CONSTRAINT `fk_renewal_periods_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_submitted_to` FOREIGN KEY (`submitted_to`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `scholars`
--
ALTER TABLE `scholars`
  ADD CONSTRAINT `fk_scholars_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholars_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `scholar_registrations`
--
ALTER TABLE `scholar_registrations`
  ADD CONSTRAINT `fk_scholar_registrations_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholar_registrations_matched_record` FOREIGN KEY (`matched_registration_record_id`) REFERENCES `registration_records` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholar_registrations_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `scholar_requirements`
--
ALTER TABLE `scholar_requirements`
  ADD CONSTRAINT `fk_scholar_requirements_renewal_period` FOREIGN KEY (`renewal_period_id`) REFERENCES `renewal_periods` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholar_requirements_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `requirements` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholar_requirements_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `scholar_requirement_history`
--
ALTER TABLE `scholar_requirement_history`
  ADD CONSTRAINT `fk_scholar_requirement_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_scholar_requirement_history_requirement` FOREIGN KEY (`scholar_requirement_id`) REFERENCES `scholar_requirements` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `fk_semesters_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `fk_user_login_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

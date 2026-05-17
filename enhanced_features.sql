-- Enhanced Features Database Tables

-- 1. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('request_approved','request_rejected','request_submitted','trip_reminder','general') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_request_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `related_request_id` (`related_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Trip History/Reports (using existing trips table, just add indexes for better reporting)
ALTER TABLE `trips` ADD INDEX `trip_date_idx` (`departure_date`);
ALTER TABLE `trips` ADD INDEX `driver_id_idx` (`driver_id`);
ALTER TABLE `trips` ADD INDEX `created_at_idx` (`created_at`);

-- 3. Vehicle Schedule/Availability Table
CREATE TABLE IF NOT EXISTS `vehicle_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `trip_request_id` int(11) DEFAULT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `driver_id` (`driver_id`),
  KEY `start_datetime` (`start_datetime`),
  KEY `end_datetime` (`end_datetime`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add vehicle_id to drivers table if not exists
ALTER TABLE `drivers` ADD COLUMN IF NOT EXISTS `vehicle_id` int(11) DEFAULT NULL AFTER `plate_number`;

-- Update trip_requests table to include vehicle assignment
ALTER TABLE `trip_requests` ADD COLUMN IF NOT EXISTS `assigned_vehicle_id` int(11) DEFAULT NULL AFTER `approved_at`;
ALTER TABLE `trip_requests` ADD COLUMN IF NOT EXISTS `assigned_driver_id` int(11) DEFAULT NULL AFTER `assigned_vehicle_id`;

-- Sample notification for testing
INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`, `is_read`) VALUES
(1, 'general', 'Welcome to VehiQuest', 'Your account has been created successfully. You can now submit trip requests.', 0);

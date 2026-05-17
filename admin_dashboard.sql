-- Enhanced database structure for admin dashboard

-- Trips table to store all trip tickets
CREATE TABLE IF NOT EXISTS `trips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_id` int(11) NOT NULL,
  `driver_name` varchar(100) NOT NULL,
  `vehicle_name` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `requester_name` varchar(100) NOT NULL DEFAULT '',
  `passenger_names` text,
  `passenger_name` varchar(100) NOT NULL DEFAULT '',
  `place_visited` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `authorized_by` varchar(100) NOT NULL,
  `departure_date` datetime DEFAULT NULL,
  `arrival_date` datetime DEFAULT NULL,
  `items_purchased` text,
  `gasoline_issued` decimal(10,2) DEFAULT 0,
  `gasoline_purchased` decimal(10,2) DEFAULT 0,
  `oil_issued` decimal(10,2) DEFAULT 0,
  `gear_oil` decimal(10,2) DEFAULT 0,
  `grease_issued` varchar(50),
  `speedometer_start` decimal(10,2) DEFAULT 0,
  `speedometer_end` decimal(10,2) DEFAULT 0,
  `distance_traveled` decimal(10,2) DEFAULT 0,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `driver_id` (`driver_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vehicles table
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_name` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `vehicle_type` varchar(50),
  `status` enum('available','in_use','maintenance','inactive') DEFAULT 'available',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update drivers table to link with vehicles
ALTER TABLE `drivers` ADD COLUMN IF NOT EXISTS `vehicle_id` int(11) DEFAULT NULL AFTER `plate_number`;
ALTER TABLE `drivers` ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') DEFAULT 'active' AFTER `vehicle_id`;

-- Maintenance records table
CREATE TABLE IF NOT EXISTS `maintenance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `maintenance_type` varchar(100) NOT NULL,
  `description` text,
  `cost` decimal(10,2) DEFAULT 0,
  `maintenance_date` date NOT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample vehicles data
INSERT INTO `vehicles` (`vehicle_name`, `plate_number`, `vehicle_type`, `status`) VALUES
('STAREX', 'ABC-1234', 'Van', 'available'),
('CAMPUS VAN', 'XYZ-5678', 'Van', 'available'),
('TOYOTA HIACE', 'DEF-9012', 'Van', 'available');

-- ── Migration: add requester_name & passenger_names to trips ──
-- Run these if the trips table already exists without these columns.
ALTER TABLE `trips` ADD COLUMN IF NOT EXISTS `requester_name` varchar(100) NOT NULL DEFAULT '' AFTER `plate_number`;
ALTER TABLE `trips` ADD COLUMN IF NOT EXISTS `passenger_names` text AFTER `requester_name`;

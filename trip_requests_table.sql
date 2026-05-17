-- Table for user trip requests
CREATE TABLE IF NOT EXISTS `trip_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `requester_name` varchar(100) NOT NULL,
  `requester_position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `passenger_names` text NOT NULL,
  `destination` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `trip_date` date NOT NULL,
  `departure_time` time NOT NULL,
  `return_time` time DEFAULT NULL,
  `number_of_passengers` int(11) NOT NULL,
  `special_requirements` text,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `admin_notes` text,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

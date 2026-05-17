-- Table for storing driver information
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_name` varchar(100) NOT NULL,
  `vehicle_name` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample drivers data
INSERT INTO `drivers` (`driver_name`, `vehicle_name`, `plate_number`) VALUES
('FERDINAND BALDOZ', 'STAREX', 'ABC-1234'),
('BENJIE TERNORA', 'CAMPUS VAN', 'XYZ-5678'),
('JUAN DELA CRUZ', 'TOYOTA HIACE', 'DEF-9012');

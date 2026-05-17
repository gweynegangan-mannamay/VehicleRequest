-- Database: vehiquest
-- Table structure for user_info

CREATE TABLE IF NOT EXISTS `user_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=User, 1=Admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data: Insert a test admin and user
-- Admin account: username=admin, password=admin123
INSERT INTO `user_info` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 1);

-- User account: username=user, password=user123
INSERT INTO `user_info` (`username`, `password`, `email`, `role`) VALUES
('user', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user@example.com', 0);

-- Note: The passwords above are hashed using password_hash() in PHP
-- admin123 and user123 are the plain text passwords

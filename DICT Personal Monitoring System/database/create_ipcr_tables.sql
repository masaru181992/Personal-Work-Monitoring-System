-- Create IPCR Target Categories table
CREATE TABLE IF NOT EXISTS `ipcr_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `weight` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create IPCR Targets table
CREATE TABLE IF NOT EXISTS `ipcr_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `target_quantity` int(11) NOT NULL DEFAULT 1,
  `quantity_accomplished` int(11) DEFAULT 0,
  `unit` varchar(50) DEFAULT 'unit(s)',
  `target_date` date NOT NULL,
  `status` enum('Not Started','In Progress','Completed','On Hold','Cancelled') DEFAULT 'Not Started',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `ipcr_targets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipcr_targets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `ipcr_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default IPCR categories
INSERT INTO `ipcr_categories` (`name`, `description`, `weight`) VALUES
('Strategic Priorities', 'Targets related to strategic priorities', 20.00),
('Core Functions', 'Targets related to core functions', 50.00),
('Support Functions', 'Targets related to support functions', 20.00),
('Special Assignments', 'Targets related to special assignments', 10.00);

-- Sample data for IPCR targets
INSERT INTO `ipcr_targets` (`user_id`, `category_id`, `title`, `description`, `target_quantity`, `quantity_accomplished`, `unit`, `target_date`, `status`, `priority`) VALUES
(1, 1, 'Digital Transformation Projects', 'Implement digital transformation initiatives', 5, 2, 'projects', '2023-12-31', 'In Progress', 'High'),
(1, 2, 'Client Satisfaction Survey', 'Conduct client satisfaction survey', 1, 1, 'survey', '2023-10-15', 'Completed', 'Medium'),
(1, 3, 'Training and Development', 'Attend training programs for skill enhancement', 4, 1, 'trainings', '2023-11-30', 'In Progress', 'Medium'),
(1, 4, 'Special Task Force Assignment', 'Participate in special task force for process improvement', 1, 0, 'assignment', '2023-12-15', 'Not Started', 'High');

-- Create ipcr_status_logs table
CREATE TABLE IF NOT EXISTS `ipcr_status_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `target_id` (`target_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `ipcr_status_logs_ibfk_1` FOREIGN KEY (`target_id`) REFERENCES `ipcr_targets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipcr_status_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

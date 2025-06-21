-- Create ipcr_activities junction table to link IPCR entries with activities
CREATE TABLE IF NOT EXISTS `ipcr_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ipcr_entry_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ipcr_entry_id` (`ipcr_entry_id`),
  KEY `activity_id` (`activity_id`),
  CONSTRAINT `ipcr_activities_ibfk_1` FOREIGN KEY (`ipcr_entry_id`) REFERENCES `ipcr_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ipcr_activities_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

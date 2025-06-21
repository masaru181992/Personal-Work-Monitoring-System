-- Create ipcr_entries table if it doesn't exist
CREATE TABLE IF NOT EXISTS `ipcr_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `semester` enum('1st','2nd') NOT NULL,
  `function_type` enum('Core Function','Support Function') NOT NULL DEFAULT 'Core Function',
  `success_indicators` text NOT NULL,
  `actual_accomplishments` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO `ipcr_entries` (`user_id`, `year`, `semester`, `function_type`, `success_indicators`, `actual_accomplishments`, `created_at`) VALUES
(1, 2025, '1st', 'Core Function', 'Successfully completed 3 major projects on time', 'Managed and delivered all projects before deadlines with positive feedback', NOW()),
(1, 2024, '2nd', 'Support Function', 'Achieved 96% client satisfaction rate', 'Maintained high client satisfaction through regular communication and support', NOW());
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO `ipcr_entries` (`user_id`, `year`, `semester`, `function_type`, `key_result_areas`, `success_indicators`, `actual_accomplishments`, `created_at`) VALUES
(1, 2025, '1st', 'Core Function', 'Project Management', 'Successfully completed 3 major projects on time', 'Managed and delivered all projects before deadlines with positive feedback', NOW()),
(1, 2024, '2nd', 'Support Function', 'Client Satisfaction', 'Achieved 96% client satisfaction rate', 'Maintained high client satisfaction through regular communication and support', NOW());
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO `ipcr_entries` (`user_id`, `year`, `semester`, `key_result_areas`, `success_indicators`, `actual_accomplishments`, `created_at`) VALUES
(1, 2025, '1st', 'Project Management', 'Successfully completed 3 major projects on time', 'Managed and delivered all projects before deadlines with positive feedback', NOW()),
(1, 2024, '2nd', 'Client Satisfaction', 'Achieved 96% client satisfaction rate', 'Maintained high client satisfaction through regular communication and support', NOW());

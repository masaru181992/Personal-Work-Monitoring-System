-- Create point_of_contacts table
CREATE TABLE IF NOT EXISTS `point_of_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('provincial','municipal','nga','ngo') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `officer_name` varchar(255) DEFAULT NULL,
  `officer_position` varchar(255) DEFAULT NULL,
  `officer_phone` varchar(50) DEFAULT NULL,
  `alt_focal_name` varchar(255) DEFAULT NULL,
  `alt_focal_position` varchar(255) DEFAULT NULL,
  `alt_focal_phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data for Provincial LGU
INSERT INTO `point_of_contacts` 
(`type`, `title`, `description`, `phone`, `email`, `officer_name`, `officer_position`, `officer_phone`, `alt_focal_name`, `alt_focal_position`, `alt_focal_phone`) 
VALUES 
('provincial', 'Governor\'s Office', 'Provincial Capitol Building', '(082) 555-0100', 'governors.office@province.gov.ph', 'Gov. Juan Dela Cruz', 'Governor', '09123456789', 'Atty. Maria Santos', 'Executive Assistant', '09187654321');

-- Insert sample data for Municipal LGU
INSERT INTO `point_of_contacts` 
(`type`, `title`, `description`, `phone`, `email`, `officer_name`, `officer_position`, `officer_phone`, `alt_focal_name`, `alt_focal_position`, `alt_focal_phone`) 
VALUES 
('municipal', 'Sta. Cruz Municipal Hall', 'Sta. Cruz, Davao del Sur', '(082) 555-1001', 'mayor@stacruz.gov.ph', 'Mayor Antonio Reyes', 'Municipal Mayor', '09123456780', 'Ms. Sofia Dela Cruz', 'Municipal Administrator', '09187654320'),
('municipal', 'Digos City Hall', 'Digos City, Davao del Sur', '(082) 555-1002', 'mayor@digoscity.gov.ph', 'Mayor Josef Cagas', 'City Mayor', '09123456781', 'Mr. Carlos Reyes', 'City Administrator', '09187654321'),
('municipal', 'Bansalan Municipal Hall', 'Bansalan, Davao del Sur', '(082) 555-1003', 'mayor@bansalan.gov.ph', 'Mayor Quirina Sarte', 'Municipal Mayor', '09123456782', 'Ms. Lorna Diamante', 'Municipal Administrator', '09187654322');

-- Insert sample data for NGA
INSERT INTO `point_of_contacts` 
(`type`, `title`, `description`, `phone`, `email`, `officer_name`, `officer_position`, `officer_phone`, `alt_focal_name`, `alt_focal_position`, `alt_focal_phone`) 
VALUES 
('nga', 'DILG Regional Office', 'Department of the Interior and Local Government', '(082) 555-0300', 'dilg11@dilg.gov.ph', 'Dir. Alex Roldan', 'Regional Director', '09123456800', 'Ms. Maria Luisa Bermudo', 'Asst. Regional Director', '09187654800'),
('nga', 'DICT Regional Office', 'Department of Information and Communications Technology', '(082) 555-0311', 'dict11@dict.gov.ph', 'Engr. Allan S. Cabanlong', 'Regional Director', '09123456811', 'Ms. Maria Lourdes Martinez', 'Asst. Regional Director', '09187654811');

-- Insert sample data for NGO
INSERT INTO `point_of_contacts` 
(`type`, `title`, `description`, `phone`, `email`, `officer_name`, `officer_position`, `officer_phone`, `alt_focal_name`, `alt_focal_position`, `alt_focal_phone`) 
VALUES 
('ngo', 'Red Cross', 'Humanitarian Organization', '(082) 555-0400', 'redcross@redcross.org.ph', 'Dr. Gwendolyn T. Pang', 'Chapter Administrator', '09123456900', 'Mr. Richard Gordon', 'Chairman', '09187654900');

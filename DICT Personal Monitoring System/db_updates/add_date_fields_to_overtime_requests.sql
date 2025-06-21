-- First, drop the existing table if it exists with old structure
DROP TABLE IF EXISTS overtime_requests;

-- Then create the table with the new structure
CREATE TABLE overtime_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    details TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
);

-- Optional: If you want to add these columns to an existing table without dropping it
-- ALTER TABLE overtime_requests
-- ADD COLUMN start_date DATE NOT NULL AFTER activity_id,
-- ADD COLUMN end_date DATE NOT NULL AFTER start_date;

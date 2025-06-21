-- Add 'archived' to the status ENUM in the notes table
ALTER TABLE notes 
MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'archived') NOT NULL DEFAULT 'pending';

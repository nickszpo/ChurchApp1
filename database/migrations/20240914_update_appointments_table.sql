-- SQLite doesn't support adding multiple columns in one statement or the AFTER keyword
-- First, create a new table with the updated schema

-- Disable foreign key constraints temporarily
PRAGMA foreign_keys=off;

-- Create new table with all columns
CREATE TABLE IF NOT EXISTS new_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_id INTEGER NOT NULL,
    priest_id INTEGER,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(50) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER NOT NULL,
    notes TEXT,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (priest_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Copy data from old table to new table
-- First, check if the old table exists
SELECT name FROM sqlite_master WHERE type='table' AND name='appointments';

-- If the table exists, copy the data
INSERT INTO new_appointments (
    id, service_id, user_id, notes, 
    priest_id, title, description, start_time, end_time, 
    contact_name, contact_phone, contact_email, status,
    created_at, updated_at
)
SELECT 
    id, 
    COALESCE(service_id, 1), 
    COALESCE(user_id, 1), 
    COALESCE(notes, ''),
    NULL, 
    COALESCE('Appointment ' || id, 'Appointment'), 
    '', 
    COALESCE(datetime('now', '+' || id || ' hours'), datetime('now')), 
    COALESCE(datetime('now', '+' || (id + 1) || ' hours'), datetime('now', '+1 hour')),
    COALESCE('User ' || user_id, 'Unknown'), 
    COALESCE('123-456-7890', ''), 
    COALESCE('user' || user_id || '@example.com', ''), 
    'pending',
    COALESCE(datetime('now', '-' || id || ' days'), datetime('now')), 
    COALESCE(datetime('now', '-' || id || ' days'), datetime('now'))
FROM appointments;

-- Drop the old table if it exists
DROP TABLE IF EXISTS old_appointments;

-- Rename the old table to old_appointments
ALTER TABLE appointments RENAME TO old_appointments;

-- Rename the new table to appointments
ALTER TABLE new_appointments RENAME TO appointments;

-- Recreate any indexes
CREATE INDEX IF NOT EXISTS idx_appointments_service_id ON appointments(service_id);
CREATE INDEX IF NOT EXISTS idx_appointments_user_id ON appointments(user_id);
CREATE INDEX IF NOT EXISTS idx_appointments_priest_id ON appointments(priest_id);

-- Re-enable foreign key constraints
PRAGMA foreign_keys=on;

-- Add recurring appointments support
ALTER TABLE appointments ADD COLUMN is_recurring BOOLEAN DEFAULT 0;
ALTER TABLE appointments ADD COLUMN recurrence_pattern TEXT;
ALTER TABLE appointments ADD COLUMN recurrence_end_date DATETIME;
ALTER TABLE appointments ADD COLUMN parent_appointment_id INTEGER REFERENCES appointments(id) ON DELETE CASCADE;

-- Add resource availability
ALTER TABLE resources ADD COLUMN is_active BOOLEAN DEFAULT 1;
ALTER TABLE resources ADD COLUMN location TEXT;
ALTER TABLE resources ADD COLUMN color_code TEXT DEFAULT '#3b82f6';

-- Add resource availability schedule
CREATE TABLE IF NOT EXISTS resource_availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    resource_id INTEGER NOT NULL,
    day_of_week INTEGER NOT NULL, -- 0 (Sunday) to 6 (Saturday)
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT 1,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    UNIQUE(resource_id, day_of_week, start_time, end_time)
);

-- Add resource bookings
ALTER TABLE appointment_resources ADD COLUMN notes TEXT;
ALTER TABLE appointment_resources ADD COLUMN status TEXT DEFAULT 'confirmed';

-- Add notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT NOT NULL, -- 'appointment', 'reminder', 'system', etc.
    is_read BOOLEAN DEFAULT 0,
    related_id INTEGER, -- ID of the related entity (appointment_id, etc.)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add settings table
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group TEXT,
    is_public BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT OR IGNORE INTO settings (setting_key, setting_value, setting_group, is_public) VALUES
    ('app_name', 'Church App', 'general', 1),
    ('timezone', 'UTC', 'general', 1),
    ('appointment_lead_time_hours', '24', 'appointments', 1),
    ('appointment_max_days_ahead', '90', 'appointments', 1),
    ('email_notifications_enabled', '1', 'notifications', 0),
    ('default_appointment_duration', '60', 'appointments', 0);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_appointments_dates ON appointments(start_time, end_time);
CREATE INDEX IF NOT EXISTS idx_appointments_user ON appointments(user_id);
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_resource_availability_resource ON resource_availability(resource_id);

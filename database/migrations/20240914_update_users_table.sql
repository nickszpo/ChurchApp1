-- Drop the existing users table if it exists
DROP TABLE IF EXISTS old_users;

-- Create a new users table with all required columns
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT '',
    bio TEXT,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    remember_token VARCHAR(100),
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- If there was an existing table, copy the data over
INSERT INTO users (id, full_name, email, password, is_active, created_at, updated_at)
SELECT id, full_name, email, password, is_active, created_at, updated_at 
FROM old_users;

-- Drop the old table
DROP TABLE IF EXISTS old_users;

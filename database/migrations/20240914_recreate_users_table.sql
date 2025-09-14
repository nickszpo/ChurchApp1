-- Create a backup of the current users table
ALTER TABLE users RENAME TO users_old;

-- Create new users table with all required columns
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(255) NOT NULL,
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

-- Copy data from old table, generating usernames from email
INSERT INTO users (
    id, username, full_name, email, password, phone, bio, 
    role, is_active, remember_token, email_verified_at, 
    created_at, updated_at
)
SELECT 
    id,
    substr(email, 1, instr(email, '@') - 1) as username,
    COALESCE(full_name, 'User ' || id) as full_name,
    email,
    password,
    COALESCE(phone, '') as phone,
    bio,
    COALESCE(role, 'user') as role,
    COALESCE(is_active, 1) as is_active,
    remember_token,
    email_verified_at,
    COALESCE(created_at, CURRENT_TIMESTAMP) as created_at,
    COALESCE(updated_at, CURRENT_TIMESTAMP) as updated_at
FROM users_old;

-- Drop the old table
DROP TABLE IF EXISTS users_old;

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

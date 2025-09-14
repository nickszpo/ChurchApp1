-- Add phone column if it doesn't exist
PRAGMA foreign_keys=off;

-- Check if phone column exists, if not add it
PRAGMA table_info(users);

-- Add phone column if it doesn't exist
ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT '';

-- Add bio column if it doesn't exist
ALTER TABLE users ADD COLUMN bio TEXT;

-- Add role column if it doesn't exist
ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user';

-- Add remember_token column if it doesn't exist
ALTER TABLE users ADD COLUMN remember_token VARCHAR(100);

-- Add email_verified_at column if it doesn't exist
ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL;

-- Make sure is_active exists
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT 1;

-- Add created_at if it doesn't exist
ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at if it doesn't exist
ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

PRAGMA foreign_keys=on;

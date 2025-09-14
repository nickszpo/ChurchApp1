-- First, add username column with a default value
ALTER TABLE users ADD COLUMN username VARCHAR(255) NOT NULL DEFAULT '';

-- Update existing records to set username based on email (everything before @)
UPDATE users SET username = substr(email, 1, instr(email, '@') - 1) WHERE username = '';

-- Make sure other required columns exist with default values
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(100);
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

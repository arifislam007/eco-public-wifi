#!/bin/bash
# Database initialization script
# This runs after MySQL container starts

set -e

echo "=== Initializing Database ==="

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD}" --silent; do
    echo "MySQL not ready yet, waiting..."
    sleep 2
done

echo "MySQL is ready!"

# Check if admin_users table exists and has the new columns
echo "Checking admin_users table structure..."

# Add role column if it doesn't exist
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" -e "
    ALTER TABLE admin_users 
    ADD COLUMN IF NOT EXISTS role ENUM('admin', 'reseller') DEFAULT 'admin',
    ADD COLUMN IF NOT EXISTS reseller_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active';
" 2>/dev/null || true

# Create or update admin user
echo "Setting up admin user..."
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" -e "
    INSERT INTO admin_users (username, password_hash, email, role, status) 
    VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', 'active')
    ON DUPLICATE KEY UPDATE 
        password_hash='\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        role='admin',
        status='active',
        email='admin@example.com';
"

echo "=== Database initialization complete ==="
echo "Admin user: admin"
echo "Admin password: admin123"

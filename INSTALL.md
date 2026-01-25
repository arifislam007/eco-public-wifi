# Installation Guide - Step by Step

## Quick Start Installation

### 1. System Requirements

- Ubuntu 20.04+ or Debian 11+
- 2GB RAM minimum
- 10GB disk space
- Root/sudo access

### 2. Install Base Packages

```bash
sudo apt-get update
sudo apt-get install -y \
    apache2 \
    mysql-server \
    php8.1 \
    php8.1-mysql \
    php8.1-curl \
    php8.1-mbstring \
    php8.1-xml \
    freeradius \
    freeradius-mysql \
    freeradius-utils \
    git
```

### 3. Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Login to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radius'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Import Database Schema

```bash
mysql -u radius -p radius < database/schema.sql
```

### 5. Configure FreeRADIUS

```bash
# Stop FreeRADIUS
sudo systemctl stop freeradius

# Backup original config
sudo cp -r /etc/freeradius/3.0 /etc/freeradius/3.0.backup

# Copy new configuration files
sudo cp freeradius/radiusd.conf /etc/freeradius/3.0/
sudo cp freeradius/sites-enabled/default /etc/freeradius/3.0/sites-enabled/
sudo cp freeradius/mods-enabled/sql /etc/freeradius/3.0/mods-enabled/
sudo cp freeradius/clients.conf /etc/freeradius/3.0/

# Edit SQL configuration
sudo nano /etc/freeradius/3.0/mods-enabled/sql
# Update: server, login, password

# Edit clients configuration
sudo nano /etc/freeradius/3.0/clients.conf
# Add your router IPs and shared secrets

# Set permissions
sudo chown -R freerad:freerad /etc/freeradius/3.0/
sudo chmod 640 /etc/freeradius/3.0/clients.conf

# Test configuration
sudo freeradius -X
# Look for "Ready to process requests" - press Ctrl+C

# Start FreeRADIUS
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

### 6. Deploy Portal Files

```bash
# Create web directory
sudo mkdir -p /var/www/wifi-portal
sudo cp -r portal /var/www/wifi-portal/
sudo cp -r admin /var/www/wifi-portal/

# Create logs directory
sudo mkdir -p /var/www/wifi-portal/logs
sudo chmod 775 /var/www/wifi-portal/logs

# Set ownership
sudo chown -R www-data:www-data /var/www/wifi-portal/

# Configure portal
sudo nano /var/www/wifi-portal/portal/config.php
# Update all database and RADIUS settings
```

### 7. Configure Apache

```bash
# Create virtual host
sudo nano /etc/apache2/sites-available/wifi-portal.conf
```

Paste:

```apache
<VirtualHost *:80>
    ServerName wifi.local
    DocumentRoot /var/www/wifi-portal/portal
    
    <Directory /var/www/wifi-portal/portal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    Alias /admin /var/www/wifi-portal/admin
    
    <Directory /var/www/wifi-portal/admin>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/wifi-portal-error.log
    CustomLog ${APACHE_LOG_DIR}/wifi-portal-access.log combined
</VirtualHost>
```

```bash
# Enable site and modules
sudo a2ensite wifi-portal.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 8. Change Default Passwords

```bash
# Login to MySQL
mysql -u radius -p radius

# Change admin password (replace NEW_PASSWORD_HASH)
UPDATE admin_users SET password_hash = '$2y$10$YOUR_NEW_HASH_HERE' WHERE username = 'admin';

# Or use PHP to generate hash:
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
```

### 9. Test Installation

1. **Test FreeRADIUS:**
```bash
echo "User-Name = testuser, User-Password = test123" | radclient -x localhost:1812 auth testing123
```

2. **Test Portal:**
- Open browser: `http://your-server-ip`
- Should see login page

3. **Test Admin Panel:**
- Open: `http://your-server-ip/admin`
- Login with admin credentials

### 10. Router Configuration

See README.md for router-specific configuration (MikroTik, OpenWRT, UniFi).

## Post-Installation Checklist

- [ ] Changed admin password
- [ ] Changed MySQL radius user password
- [ ] Updated FreeRADIUS clients.conf with router IPs
- [ ] Updated portal/config.php with correct credentials
- [ ] Tested user authentication
- [ ] Configured firewall (allow 1812/1813 from router IPs)
- [ ] Set up SSL/HTTPS (recommended)
- [ ] Configured automatic backups
- [ ] Tested captive portal redirect

## Troubleshooting

See README.md troubleshooting section for common issues.

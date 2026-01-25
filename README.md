# Public Wi-Fi Captive Portal with FreeRADIUS

A complete, production-ready public Wi-Fi captive portal system with FreeRADIUS authentication, designed for NGO centers, schools, training centers, and community Wi-Fi zones in Bangladesh.

## 🏗️ System Architecture

```
User Device → Wi-Fi Router/AP → Captive Portal (PHP) → FreeRADIUS → MySQL → Internet
```

## 📋 Features

- ✅ **FreeRADIUS 3.x Integration** - Industry-standard authentication
- ✅ **Responsive Captive Portal** - Bootstrap-based login page
- ✅ **User & Voucher Management** - Create users with expiry dates and time limits
- ✅ **Admin Panel** - Complete user management interface
- ✅ **Security Features** - Rate limiting, SQL injection protection, password hashing
- ✅ **Usage Tracking** - View online users and usage logs
- ✅ **Session Management** - Configurable session timeouts
- ✅ **Multi-router Support** - MikroTik, OpenWRT, UniFi compatible

## 🚀 Installation

### Quick Start with Docker (Recommended)

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Edit .env with your settings
nano .env

# 3. Start all services
docker-compose up -d

# 4. Access portal
# Portal: http://localhost
# Admin: http://localhost/admin (admin/admin123)
```

See [DOCKER.md](DOCKER.md) for detailed Docker instructions.

### Manual Installation

#### Prerequisites

- Ubuntu/Debian Linux server
- PHP 8.x with extensions: `pdo_mysql`, `radius` (optional), `openssl`
- MySQL/MariaDB 10.3+
- FreeRADIUS 3.x
- Apache/Nginx web server
- Router/AP (MikroTik, OpenWRT, or UniFi)

### Step 1: Install FreeRADIUS

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install freeradius freeradius-mysql freeradius-utils

# Start and enable FreeRADIUS
sudo systemctl enable freeradius
sudo systemctl start freeradius
```

### Step 2: Setup MySQL Database

```bash
# Login to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radius'@'localhost' IDENTIFIED BY 'radius_password';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u radius -p radius < database/schema.sql
```

### Step 3: Configure FreeRADIUS

```bash
# Backup original config
sudo cp /etc/freeradius/3.0/radiusd.conf /etc/freeradius/3.0/radiusd.conf.backup

# Copy configuration files
sudo cp freeradius/radiusd.conf /etc/freeradius/3.0/
sudo cp freeradius/sites-enabled/default /etc/freeradius/3.0/sites-enabled/
sudo cp freeradius/mods-enabled/sql /etc/freeradius/3.0/mods-enabled/
sudo cp freeradius/clients.conf /etc/freeradius/3.0/

# Edit SQL module configuration
sudo nano /etc/freeradius/3.0/mods-enabled/sql
# Update: server, login, password to match your MySQL setup

# Edit clients.conf
sudo nano /etc/freeradius/3.0/clients.conf
# Update: IP addresses and shared secrets for your routers

# Set permissions
sudo chown -R freerad:freerad /etc/freeradius/3.0/
sudo chmod 640 /etc/freeradius/3.0/clients.conf

# Test configuration
sudo freeradius -X
# Press Ctrl+C after verifying no errors

# Restart FreeRADIUS
sudo systemctl restart freeradius
```

### Step 4: Setup Web Server

#### Apache Configuration

```bash
# Install Apache and PHP
sudo apt-get install apache2 php php-mysql php-curl php-mbstring

# Enable required modules
sudo a2enmod rewrite ssl

# Create virtual host
sudo nano /etc/apache2/sites-available/wifi-portal.conf
```

Add this configuration:

```apache
<VirtualHost *:80>
    ServerName wifi.example.com
    DocumentRoot /var/www/wifi-portal/portal
    
    <Directory /var/www/wifi-portal/portal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/wifi-portal-error.log
    CustomLog ${APACHE_LOG_DIR}/wifi-portal-access.log combined
</VirtualHost>
```

```bash
# Enable site
sudo a2ensite wifi-portal.conf
sudo systemctl reload apache2
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name wifi.example.com;
    root /var/www/wifi-portal/portal;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Step 5: Deploy Portal Files

```bash
# Copy files to web directory
sudo cp -r portal /var/www/wifi-portal/
sudo cp -r admin /var/www/wifi-portal/

# Set permissions
sudo chown -R www-data:www-data /var/www/wifi-portal/
sudo chmod -R 755 /var/www/wifi-portal/
sudo chmod -R 775 /var/www/wifi-portal/logs

# Configure portal
sudo nano /var/www/wifi-portal/portal/config.php
# Update database credentials and FreeRADIUS settings
```

### Step 6: Setup HTTPS (Recommended)

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d wifi.example.com

# Auto-renewal is set up automatically
```

### Step 7: Router Configuration

#### MikroTik Configuration

```bash
# Configure Hotspot
/ip hotspot profile add name=public-wifi dns-name=wifi.example.com hotspot-address=192.168.88.1
/ip hotspot add profile=public-wifi interface=wlan1 disabled=no

# Configure RADIUS
/radius add service=hotspot address=YOUR_RADIUS_SERVER_IP secret=your_shared_secret_here
```

#### OpenWRT Configuration

Edit `/etc/config/radius`:

```
config radius 'auth'
    option server 'YOUR_RADIUS_SERVER_IP'
    option port '1812'
    option secret 'your_shared_secret_here'
```

## 🔧 Configuration

### Portal Configuration

Edit `portal/config.php`:

```php
define('PORTAL_NAME', 'Your Wi-Fi Name');
define('SUPPORT_EMAIL', 'support@example.com');
define('DB_HOST', 'localhost');
define('DB_NAME', 'radius');
define('DB_USER', 'radius');
define('DB_PASS', 'your_password');
define('RADIUS_HOST', '127.0.0.1');
define('RADIUS_PORT', 1812);
define('RADIUS_SECRET', 'your_shared_secret');
```

### FreeRADIUS Clients

Edit `/etc/freeradius/3.0/clients.conf` and add your router IPs:

```
client mikrotik {
    ipaddr = 192.168.88.1
    secret = your_shared_secret_here
    nas_type = other
}
```

## 👤 Default Credentials

**Admin Panel:**
- Username: `admin`
- Password: `admin123` (⚠️ **CHANGE THIS IMMEDIATELY!**)

**Test User:**
- Username: `testuser`
- Password: `test123`

## 📱 Usage

### Creating Users/Vouchers

1. Login to admin panel: `http://wifi.example.com/admin`
2. Go to "Users & Vouchers"
3. Enter username, password, session timeout, and optional expiry date
4. Click "Create User"

### User Login

1. Connect to Wi-Fi network
2. Browser redirects to captive portal
3. Enter username and password
4. Accept terms and conditions
5. Click "Connect to Wi-Fi"

## 🔒 Security Best Practices

1. **Change Default Passwords** - Immediately change admin and test user passwords
2. **Use HTTPS** - Enable SSL/TLS for the captive portal
3. **Strong Shared Secrets** - Use strong, unique secrets in `clients.conf`
4. **Firewall Rules** - Restrict access to FreeRADIUS port (1812/1813) to router IPs only
5. **Regular Updates** - Keep FreeRADIUS, PHP, and MySQL updated
6. **Backup Database** - Regularly backup the `radius` database

## 🐛 Troubleshooting

### FreeRADIUS Not Authenticating

```bash
# Check FreeRADIUS logs
sudo tail -f /var/log/freeradius/radius.log

# Test authentication manually
echo "User-Name = testuser, User-Password = test123" | radclient -x localhost:1812 auth testing123
```

### Portal Can't Connect to FreeRADIUS

1. Check if FreeRADIUS is running: `sudo systemctl status freeradius`
2. Verify RADIUS_HOST and RADIUS_SECRET in `config.php`
3. Check firewall: `sudo ufw status`
4. Test with radclient (see above)

### Database Connection Errors

1. Verify MySQL credentials in `config.php`
2. Check MySQL is running: `sudo systemctl status mysql`
3. Test connection: `mysql -u radius -p radius`

## 📊 Monitoring

- **Online Users**: Admin panel → Online Users
- **Usage Logs**: Admin panel → Usage Logs
- **FreeRADIUS Logs**: `/var/log/freeradius/radius.log`
- **Apache/Nginx Logs**: `/var/log/apache2/` or `/var/log/nginx/`

## 🛠️ Maintenance

### Backup Database

```bash
mysqldump -u radius -p radius > radius_backup_$(date +%Y%m%d).sql
```

### Clean Old Logs

```sql
-- Clean login attempts older than 30 days
DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean accounting records older than 90 days
DELETE FROM radacct WHERE acctstoptime < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## 📝 License

This project is provided as-is for public Wi-Fi deployments. Modify as needed for your requirements.

## 🤝 Support

For issues and questions:
- Check FreeRADIUS documentation: https://freeradius.org/documentation/
- Review router-specific documentation (MikroTik, OpenWRT, UniFi)

## 🔄 Updates

To update the system:

1. Backup database and configuration files
2. Pull latest code
3. Update database schema if needed: `mysql -u radius -p radius < database/schema.sql`
4. Test in staging environment first

---

**Built for reliable, low-cost public Wi-Fi deployments in Bangladesh** 🇧🇩

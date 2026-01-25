# Docker Deployment Guide

This guide explains how to run the Wi-Fi Captive Portal system using Docker Compose.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- At least 2GB RAM available
- Ports 80, 443, 3306, 1812, 1813 available

## Quick Start

### 1. Clone/Copy the Project

```bash
cd wifi-portal
```

### 2. Configure Environment

```bash
# Copy example environment file
cp .env.example .env

# Edit .env file with your settings
nano .env
```

Update these values in `.env`:
```env
MYSQL_ROOT_PASSWORD=your_secure_password
MYSQL_PASSWORD=radius_password
RADIUS_SECRET=your_shared_secret
PORTAL_NAME=Your Wi-Fi Name
SUPPORT_EMAIL=support@yourdomain.com
```

### 3. Build and Start Containers

```bash
# Build and start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Check status
docker-compose ps
```

### 4. Initialize Admin User

```bash
# Enter the web container
docker-compose exec web bash

# Run setup script
php /var/www/html/scripts/setup-admin.php

# Or create admin directly
php -r "echo password_hash('your_admin_password', PASSWORD_BCRYPT);"
# Then update database:
# mysql -h mysql -u radius -p radius
# UPDATE admin_users SET password_hash='YOUR_HASH' WHERE username='admin';
```

### 5. Access the Portal

- **Portal**: http://localhost
- **Admin Panel**: http://localhost/admin
- **Default Admin**: username: `admin`, password: `admin123` (change immediately!)

## Services

The Docker Compose setup includes:

1. **mysql** - MariaDB 10.11 database
   - Port: 3306
   - Auto-initializes with schema from `database/schema.sql`

2. **freeradius** - FreeRADIUS authentication server
   - Ports: 1812/udp (auth), 1813/udp (accounting)
   - Automatically configured to use MySQL

3. **web** - PHP 8.2 + Apache web server
   - Ports: 80 (HTTP), 443 (HTTPS)
   - Serves portal and admin panel

## Configuration

### Environment Variables

Edit `.env` file to configure:

```env
# Database
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=radius
MYSQL_USER=radius
MYSQL_PASSWORD=radius_password

# FreeRADIUS
RADIUS_SECRET=your_shared_secret_here

# Portal
PORTAL_NAME=Community Wi-Fi
SUPPORT_EMAIL=support@example.com
```

### FreeRADIUS Clients

Edit `freeradius/clients.conf` to add your router IPs:

```
client mikrotik {
    ipaddr = 192.168.88.1
    secret = your_shared_secret_here
    nas_type = other
}
```

Then restart FreeRADIUS:
```bash
docker-compose restart freeradius
```

## Common Commands

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f web
docker-compose logs -f freeradius
docker-compose logs -f mysql
```

### Stop Services

```bash
# Stop all services
docker-compose stop

# Stop and remove containers
docker-compose down

# Stop and remove containers + volumes (⚠️ deletes database!)
docker-compose down -v
```

### Restart Services

```bash
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart freeradius
```

### Execute Commands in Containers

```bash
# Access web container shell
docker-compose exec web bash

# Access MySQL
docker-compose exec mysql mysql -u radius -p radius

# Test FreeRADIUS
docker-compose exec web bash
echo "User-Name = testuser, User-Password = test123" | radclient -x freeradius:1812 auth testing123
```

### Backup Database

```bash
# Create backup
docker-compose exec mysql mysqldump -u radius -p radius > backup_$(date +%Y%m%d).sql

# Restore backup
docker-compose exec -T mysql mysql -u radius -p radius < backup_20240101.sql
```

## Troubleshooting

### Port Already in Use

If ports are already in use, edit `docker-compose.yml`:

```yaml
ports:
  - "8080:80"  # Change 80 to 8080
```

### FreeRADIUS Not Starting

```bash
# Check FreeRADIUS logs
docker-compose logs freeradius

# Test configuration
docker-compose exec freeradius freeradius -X

# Check MySQL connection
docker-compose exec freeradius mysql -h mysql -u radius -p radius
```

### Database Connection Errors

```bash
# Check MySQL is running
docker-compose ps mysql

# Check MySQL logs
docker-compose logs mysql

# Test connection from web container
docker-compose exec web php -r "
try {
    \$pdo = new PDO('mysql:host=mysql;dbname=radius', 'radius', 'radius_password');
    echo 'Connected successfully';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage();
}
"
```

### Permission Issues

```bash
# Fix log directory permissions
docker-compose exec web chmod -R 777 /var/www/html/logs

# Fix file ownership
docker-compose exec web chown -R www-data:www-data /var/www/html
```

## Production Deployment

### 1. Use HTTPS

Add SSL certificates and configure Apache:

```yaml
# In docker-compose.yml, add volume for SSL:
volumes:
  - ./ssl:/etc/ssl/certs:ro
```

### 2. Use Strong Passwords

Update `.env` with strong passwords:
- `MYSQL_ROOT_PASSWORD` - Strong random password
- `MYSQL_PASSWORD` - Strong random password
- `RADIUS_SECRET` - Strong random secret

### 3. Limit Network Exposure

Edit `docker-compose.yml` to remove port mappings for internal services:

```yaml
mysql:
  # Remove: ports: - "3306:3306"
  # Only accessible from Docker network
```

### 4. Regular Backups

Set up automated backups:

```bash
# Add to crontab
0 2 * * * cd /path/to/wifi-portal && docker-compose exec -T mysql mysqldump -u radius -p'password' radius > /backups/radius_$(date +\%Y\%m\%d).sql
```

### 5. Resource Limits

Add resource limits in `docker-compose.yml`:

```yaml
services:
  web:
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M
```

## Updating

```bash
# Pull latest code
git pull

# Rebuild containers
docker-compose build

# Restart services
docker-compose up -d
```

## Cleanup

```bash
# Remove all containers and volumes
docker-compose down -v

# Remove images
docker-compose down --rmi all
```

## Network Configuration

The containers communicate via a Docker bridge network (`wifi-network`). Services can reach each other by service name:
- `mysql` - Database
- `freeradius` - RADIUS server
- `web` - Web server

## External Router Configuration

Your router/AP should point to the Docker host IP for RADIUS:
- RADIUS Server: `<docker-host-ip>`
- Port: 1812
- Secret: Value from `.env` `RADIUS_SECRET`

Make sure port 1812/udp is open on the Docker host firewall.

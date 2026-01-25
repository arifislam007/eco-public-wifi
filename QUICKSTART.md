# Quick Start Guide

## Docker Deployment (Easiest)

### 1. Setup Environment

```bash
# Copy environment file
cp .env.example .env

# Edit with your settings
nano .env  # or use your favorite editor
```

Minimum required changes in `.env`:
```env
MYSQL_ROOT_PASSWORD=your_secure_password
MYSQL_PASSWORD=radius_password
RADIUS_SECRET=your_shared_secret
PORTAL_NAME=Your Wi-Fi Name
SUPPORT_EMAIL=support@yourdomain.com
```

### 2. Start Services

**Linux/Mac:**
```bash
chmod +x docker/start.sh
./docker/start.sh
```

**Windows:**
```cmd
docker\start.bat
```

**Or manually:**
```bash
docker-compose up -d
```

### 3. Access Portal

- **Portal**: http://localhost
- **Admin Panel**: http://localhost/admin
- **Default Credentials**: `admin` / `admin123` ⚠️ **Change immediately!**

### 4. Initialize Admin (Optional)

```bash
# Enter web container
docker-compose exec web bash

# Run setup script
php /var/www/html/scripts/setup-admin.php
```

### 5. Configure Router

Edit `freeradius/clients.conf` and add your router:
```
client mikrotik {
    ipaddr = 192.168.88.1
    secret = your_shared_secret_here
    nas_type = other
}
```

Restart FreeRADIUS:
```bash
docker-compose restart freeradius
```

## Manual Installation

See [INSTALL.md](INSTALL.md) for step-by-step manual installation.

## Next Steps

1. ✅ Change admin password
2. ✅ Create test users in admin panel
3. ✅ Configure router to use RADIUS
4. ✅ Test authentication
5. ✅ Set up HTTPS (production)
6. ✅ Configure backups

## Troubleshooting

```bash
# View logs
docker-compose logs -f

# Check service status
docker-compose ps

# Restart services
docker-compose restart

# Access container shell
docker-compose exec web bash
docker-compose exec mysql mysql -u radius -p radius
```

See [DOCKER.md](DOCKER.md) for detailed troubleshooting.

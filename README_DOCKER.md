# Quick Start - Docker Deployment

## Prerequisites

- Docker and Docker Compose installed
- Ports 80, 3306, 1812, 1813 available

## Installation

### Option 1: Quick Start Script

```bash
# Make script executable (if needed)
chmod +x docker/start.sh

# Run setup script
./docker/start.sh
```

### Option 2: Manual Setup

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Edit .env with your settings
nano .env

# 3. Create logs directory
mkdir -p logs
chmod 777 logs

# 4. Build and start
docker-compose up -d

# 5. View logs
docker-compose logs -f
```

## Access

- **Portal**: http://localhost
- **Admin Panel**: http://localhost/admin
- **Default Admin**: `admin` / `admin123` (⚠️ change immediately!)

## Initialize Admin

```bash
# Enter web container
docker-compose exec web bash

# Run setup script
php /var/www/html/scripts/setup-admin.php
```

Or manually:
```bash
# Generate password hash
docker-compose exec web php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"

# Update database
docker-compose exec mysql mysql -u radius -p radius
# Then: UPDATE admin_users SET password_hash='YOUR_HASH' WHERE username='admin';
```

## Common Commands

```bash
# View logs
docker-compose logs -f

# Stop services
docker-compose stop

# Restart services
docker-compose restart

# Stop and remove
docker-compose down

# Rebuild after code changes
docker-compose build
docker-compose up -d
```

## Configuration

Edit `.env` file:
- Database passwords
- RADIUS secret
- Portal name and email

Edit `freeradius/clients.conf` to add your router IPs.

## Troubleshooting

See `DOCKER.md` for detailed troubleshooting guide.

## Production Notes

1. Use strong passwords in `.env`
2. Enable HTTPS (add SSL certificates)
3. Remove port mappings for internal services
4. Set up regular database backups
5. Configure firewall rules

# Project Structure

```
wifi-portal/
│
├── database/
│   └── schema.sql                 # MySQL database schema for FreeRADIUS
│
├── freeradius/
│   ├── radiusd.conf               # Main FreeRADIUS configuration
│   ├── sites-enabled/
│   │   └── default                # Default site configuration
│   ├── mods-enabled/
│   │   └── sql                    # SQL module configuration
│   └── clients.conf               # RADIUS client definitions (routers)
│
├── portal/                        # Captive Portal (Public-facing)
│   ├── index.php                  # Login page
│   ├── success.php                # Success page after authentication
│   ├── terms.php                  # Terms and conditions page
│   ├── config.php                 # Portal configuration
│   ├── includes/
│   │   ├── auth.php               # FreeRADIUS authentication functions
│   │   └── security.php           # Security functions (rate limiting, etc.)
│   └── assets/
│       ├── css/
│       │   └── style.css          # Portal styles
│       └── img/
│           └── .gitkeep          # Place logo.png here
│
├── admin/                         # Admin Panel
│   ├── index.php                  # Admin login
│   ├── dashboard.php              # Admin dashboard
│   ├── users.php                  # User/voucher management
│   ├── online.php                 # View online users
│   ├── logs.php                  # Usage logs
│   ├── settings.php              # Admin settings
│   ├── logout.php                # Logout handler
│   ├── config.php                # Admin config (uses portal config)
│   └── includes/
│       ├── auth.php              # Admin authentication
│       └── security.php          # Security functions
│
├── scripts/                       # CLI Utilities
│   ├── setup-admin.php           # Create/update admin user
│   ├── create-user.php           # Create RADIUS user via CLI
│   └── test-radius.php           # Test FreeRADIUS authentication
│
├── logs/                          # Application logs (created at runtime)
│
├── README.md                      # Main documentation
├── INSTALL.md                     # Step-by-step installation guide
├── CHANGELOG.md                   # Version history
├── PROJECT_STRUCTURE.md           # This file
├── .htaccess                      # Apache configuration
└── .gitignore                     # Git ignore rules
```

## Key Files

### Configuration Files
- `portal/config.php` - Main configuration (database, FreeRADIUS, security)
- `freeradius/mods-enabled/sql` - FreeRADIUS MySQL connection
- `freeradius/clients.conf` - Router/AP client definitions

### Core Portal Files
- `portal/index.php` - User login page
- `portal/includes/auth.php` - Authentication logic (FreeRADIUS + MySQL fallback)
- `portal/includes/security.php` - Rate limiting, input sanitization

### Admin Panel Files
- `admin/dashboard.php` - Statistics and overview
- `admin/users.php` - Create/manage users and vouchers
- `admin/online.php` - Monitor active sessions
- `admin/logs.php` - View usage history

### Database
- `database/schema.sql` - Complete database schema including:
  - FreeRADIUS tables (radcheck, radreply, radacct, etc.)
  - Admin users table
  - Login attempts tracking

## Installation Paths

After installation, files should be placed at:
- Portal: `/var/www/wifi-portal/portal/`
- Admin: `/var/www/wifi-portal/admin/`
- FreeRADIUS config: `/etc/freeradius/3.0/`
- Logs: `/var/www/wifi-portal/logs/`

## File Permissions

```bash
# Web files
chown -R www-data:www-data /var/www/wifi-portal/
chmod -R 755 /var/www/wifi-portal/
chmod -R 775 /var/www/wifi-portal/logs

# FreeRADIUS config
chown -R freerad:freerad /etc/freeradius/3.0/
chmod 640 /etc/freeradius/3.0/clients.conf
```

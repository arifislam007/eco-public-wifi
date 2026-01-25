# Starting the Wi-Fi Portal on Windows

## Quick Start

### Option 1: Using Default Ports (if available)

```powershell
cd f:\php-project\wifi
docker-compose up -d
```

**Access:**
- Portal: http://localhost
- Admin: http://localhost/admin

### Option 2: Using Alternative Ports (if 80/3306 are in use)

```powershell
cd f:\php-project\wifi
docker-compose -f docker-compose.yml -f docker-compose.port-override.yml up -d
```

**Access:**
- Portal: http://localhost:8080
- Admin: http://localhost:8080/admin

## Step-by-Step

1. **Check if ports are available:**
   ```powershell
   netstat -ano | findstr ":80"
   netstat -ano | findstr ":3306"
   ```

2. **If ports are in use, use the port override:**
   ```powershell
   docker-compose -f docker-compose.yml -f docker-compose.port-override.yml up -d
   ```

3. **Check container status:**
   ```powershell
   docker-compose ps
   ```

4. **View logs:**
   ```powershell
   docker-compose logs -f
   ```

5. **Access the portal:**
   - Default: http://localhost (or http://localhost:8080 if using override)
   - Admin: http://localhost/admin (or http://localhost:8080/admin)

## Default Credentials

- **Admin Panel**: `admin` / `admin123` ⚠️ **Change immediately!**
- **Test User**: `testuser` / `test123`

## Troubleshooting

### Port Already in Use

If you see errors about ports being in use:
- Use the port override file (Option 2 above)
- Or stop the conflicting service:
  ```powershell
  # Find what's using port 80
  netstat -ano | findstr ":80"
  # Stop the process (replace PID with actual process ID)
  taskkill /PID <PID> /F
  ```

### Containers Not Starting

```powershell
# Check logs
docker-compose logs

# Rebuild if needed
docker-compose build --no-cache

# Restart
docker-compose restart
```

### Database Connection Issues

```powershell
# Check MySQL container
docker-compose logs mysql

# Access MySQL
docker-compose exec mysql mysql -u radius -p radius
```

## Stopping Services

```powershell
docker-compose down
```

## Full Restart

```powershell
docker-compose down
docker-compose build
docker-compose up -d
```

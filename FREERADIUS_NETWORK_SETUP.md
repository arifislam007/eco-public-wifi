# FreeRADIUS Network Setup Guide

## Problem: Cannot Connect from Internal Network

When running FreeRADIUS in Docker Desktop on Windows, the container may not be accessible from other machines on your local network due to Windows Firewall blocking the RADIUS ports.

## Solution

### Step 1: Open Windows Firewall Ports

**Option A: Run the provided script (Recommended)**

1. Right-click on `scripts/fix-windows-firewall.bat`
2. Select "Run as Administrator"
3. The script will automatically open ports 1812 and 1813 for UDP traffic

**Option B: Manual configuration**

1. Open Windows Defender Firewall with Advanced Security
2. Click "Inbound Rules" → "New Rule"
3. Select "Port" → "Next"
4. Select "UDP" and enter ports: `1812,1813` → "Next"
5. Select "Allow the connection" → "Next"
6. Apply to all profiles (Domain, Private, Public) → "Next"
7. Name it "FreeRADIUS" → "Finish"

### Step 2: Check Docker Port Binding

Ensure Docker Desktop is binding ports to all interfaces:

```bash
# Check if ports are listening
docker exec wifi-portal-freeradius netstat -tulpn | grep 1812

# Should show: 0.0.0.0:1812
```

### Step 3: Verify Network Connectivity

From another machine on your network:

```bash
# Test if port is reachable (replace with your Windows machine IP)
nc -vu <windows-ip> 1812

# Or using radtest
radtest testuser testpass <windows-ip> 0 testing123
```

### Step 4: Check Windows IP Address

On your Windows machine running Docker:

```cmd
ipconfig
```

Use the IP address of your Ethernet or Wi-Fi adapter (not the Docker virtual adapter).

## Configuration Files

The following files have been updated to allow connections from any IP:

1. **freeradius/sites-enabled/default** - Binds to 0.0.0.0 and ::
2. **freeradius/radiusd.conf** - Global listen directives updated
3. **freeradius/clients.conf** - Added client definitions for any IP (0.0.0.0/0)
4. **docker-compose.yml** - Port mappings configured

## Testing

From your MikroTik/OpenWRT router or any RADIUS client:

```
RADIUS Server: <your-windows-ip>
Port: 1812
Secret: testing123
```

## Troubleshooting

### Issue: Still cannot connect

1. **Check Windows Firewall** - Ensure the rule was created successfully
2. **Check Docker Desktop** - Restart Docker Desktop after firewall changes
3. **Check IP Address** - Use the correct Windows IP, not localhost/127.0.0.1
4. **Check Container Logs** - `docker logs wifi-portal-freeradius`

### Issue: Connection refused

The FreeRADIUS container might not be running or ports not exposed:

```bash
docker-compose ps
docker-compose logs freeradius
```

### Issue: Invalid shared secret

Make sure the RADIUS client is using the correct secret defined in `freeradius/clients.conf`:

```
secret = testing123
```

## Security Note

The current configuration allows connections from any IP address (0.0.0.0/0) with the shared secret "testing123". For production:

1. Change the shared secret to a strong password
2. Restrict client IPs to specific router/AP addresses
3. Update `freeradius/clients.conf` with specific IP addresses

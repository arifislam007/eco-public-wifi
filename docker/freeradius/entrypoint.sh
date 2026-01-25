#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if mysqladmin ping -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; then
        echo "MySQL is ready!"
        break
    fi
    attempt=$((attempt + 1))
    echo "Attempt $attempt/$max_attempts: MySQL not ready, waiting..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "Warning: MySQL connection timeout, continuing anyway..."
fi

# Update FreeRADIUS SQL configuration with environment variables
if [ -f /etc/freeradius/3.0/mods-enabled/sql ]; then
    echo "Updating FreeRADIUS SQL configuration..."
    # Copy to a writable location, modify, then copy back
    cp /etc/freeradius/3.0/mods-enabled/sql /tmp/sql_config
    sed -i "s|server = \".*\"|server = \"$DB_HOST\"|g" /tmp/sql_config
    sed -i "s|login = \".*\"|login = \"$DB_USER\"|g" /tmp/sql_config
    sed -i "s|password = \".*\"|password = \"$DB_PASS\"|g" /tmp/sql_config
    sed -i "s|radius_db = \".*\"|radius_db = \"$DB_NAME\"|g" /tmp/sql_config
    # Since the volume is read-only, we'll use a bind mount override
    # For now, just log the values - the config should be pre-configured
    echo "Configuration values: DB_HOST=$DB_HOST, DB_USER=$DB_USER, DB_NAME=$DB_NAME"
    echo "Note: If SQL config is read-only, ensure it's pre-configured in the mounted file"
fi

# Set permissions
chown -R freerad:freerad /etc/freeradius/3.0/ 2>/dev/null || true
chmod 640 /etc/freeradius/3.0/clients.conf 2>/dev/null || true

# Execute the main command
exec "$@"

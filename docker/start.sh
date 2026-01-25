#!/bin/bash
# Quick start script for Docker deployment

set -e

echo "=== Wi-Fi Portal Docker Setup ==="
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env file from example..."
    cp .env.example .env
    echo "⚠️  Please edit .env file with your configuration before continuing!"
    echo ""
    read -p "Press Enter to continue after editing .env, or Ctrl+C to exit..."
fi

# Create logs directory
mkdir -p logs
chmod 777 logs

# Build and start containers
echo "Building Docker images..."
docker-compose build

echo "Starting containers..."
docker-compose up -d

echo ""
echo "Waiting for services to be ready..."
sleep 10

# Check service status
echo ""
echo "Service Status:"
docker-compose ps

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Portal: http://localhost"
echo "Admin: http://localhost/admin"
echo ""
echo "Default admin credentials:"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo "⚠️  IMPORTANT: Change the admin password immediately!"
echo ""
echo "To view logs: docker-compose logs -f"
echo "To stop: docker-compose down"

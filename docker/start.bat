@echo off
REM Quick start script for Docker deployment (Windows)

echo === Wi-Fi Portal Docker Setup ===
echo.

REM Check if .env exists
if not exist .env (
    echo Creating .env file from example...
    copy .env.example .env
    echo.
    echo WARNING: Please edit .env file with your configuration before continuing!
    echo.
    pause
)

REM Create logs directory
if not exist logs mkdir logs

REM Build and start containers
echo Building Docker images...
docker-compose build

echo Starting containers...
docker-compose up -d

echo.
echo Waiting for services to be ready...
timeout /t 10 /nobreak >nul

REM Check service status
echo.
echo Service Status:
docker-compose ps

echo.
echo === Setup Complete ===
echo.
echo Portal: http://localhost
echo Admin: http://localhost/admin
echo.
echo Default admin credentials:
echo   Username: admin
echo   Password: admin123
echo.
echo WARNING: Change the admin password immediately!
echo.
echo To view logs: docker-compose logs -f
echo To stop: docker-compose down
echo.
pause

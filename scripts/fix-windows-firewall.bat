@echo off
:: Run this script as Administrator to open Windows Firewall ports for FreeRADIUS

echo Opening Windows Firewall ports for FreeRADIUS...
echo.

:: Add FreeRADIUS Auth port (1812 UDP)
netsh advfirewall firewall add rule name="FreeRADIUS Auth" dir=in action=allow protocol=udp localport=1812
if %errorlevel% == 0 (
    echo [OK] Added rule for port 1812/UDP (Authentication)
) else (
    echo [ERROR] Failed to add rule for port 1812. Run as Administrator!
    exit /b 1
)

:: Add FreeRADIUS Accounting port (1813 UDP)
netsh advfirewall firewall add rule name="FreeRADIUS Accounting" dir=in action=allow protocol=udp localport=1813
if %errorlevel% == 0 (
    echo [OK] Added rule for port 1813/UDP (Accounting)
) else (
    echo [ERROR] Failed to add rule for port 1813. Run as Administrator!
    exit /b 1
)

echo.
echo Windows Firewall rules added successfully!
echo.
echo You can now connect to FreeRADIUS from other machines on your network.
echo.
pause

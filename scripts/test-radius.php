<?php
/**
 * Test FreeRADIUS Connection
 * Usage: php scripts/test-radius.php [username] [password]
 */

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$username = $argv[1] ?? 'testuser';
$password = $argv[2] ?? 'test123';

echo "=== Testing FreeRADIUS Authentication ===\n\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "RADIUS Server: " . RADIUS_HOST . ":" . RADIUS_PORT . "\n\n";

echo "Testing authentication...\n";

$result = authenticateUser($username, $password);

if ($result['success']) {
    echo "✓ SUCCESS: Authentication successful!\n";
    echo "  Message: " . $result['message'] . "\n";
    exit(0);
} else {
    echo "✗ FAILED: Authentication failed!\n";
    echo "  Message: " . $result['message'] . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check if FreeRADIUS is running: sudo systemctl status freeradius\n";
    echo "2. Check FreeRADIUS logs: sudo tail -f /var/log/freeradius/radius.log\n";
    echo "3. Verify user exists in database: SELECT * FROM radcheck WHERE username = '$username';\n";
    echo "4. Test with radclient: echo 'User-Name = $username, User-Password = $password' | radclient -x localhost:1812 auth " . RADIUS_SECRET . "\n";
    exit(1);
}

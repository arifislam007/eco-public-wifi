<?php
/**
 * Payment Processing Simulation
 * Simulates payment flow and generates voucher after successful payment
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDBConnection();

// Check if there's a payment order
if (!isset($_SESSION['payment_order'])) {
    header('Location: packages.php');
    exit;
}

$payment = $_SESSION['payment_order'];
$error = '';
$success = '';

// Simulate payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // In a real implementation, this would:
    // 1. Connect to the payment gateway API
    // 2. Process the payment
    // 3. Verify the transaction
    
    // For simulation, we'll just generate a voucher
    $voucher_code = 'V' . strtoupper(substr(uniqid(), -8));
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    $duration = $_SESSION['pending_order']['duration_seconds'] ?? 7200;
    
    try {
        // Create voucher in database
        $username = 'pkg_' . $voucher_code;
        
        // Insert into radcheck
        $stmt = $pdo->prepare("
            INSERT INTO radcheck (username, attribute, op, value) 
            VALUES (?, 'Cleartext-Password', ':=', ?)
        ");
        $stmt->execute([$username, $password]);
        
        // Add session timeout
        $stmt = $pdo->prepare("
            INSERT INTO radreply (username, attribute, op, value) 
            VALUES (?, 'Session-Timeout', ':=', ?)
        ");
        $stmt->execute([$username, $duration]);
        
        // Update order with voucher info
        $stmt = $pdo->prepare("
            UPDATE package_orders 
            SET payment_status = 'paid', 
                paid_at = NOW(),
                voucher_code = ?,
                payment_transaction_id = ?
            WHERE order_code = ?
        ");
        $stmt->execute([$voucher_code, 'SIM_' . time(), $payment['order_code']]);
        
        // Store voucher for display
        $_SESSION['purchased_voucher'] = [
            'voucher_code' => $voucher_code,
            'username' => $username,
            'password' => $password,
            'duration' => $duration,
            'mobile' => $payment['mobile_number']
        ];
        
        // Clear pending order
        unset($_SESSION['pending_order']);
        unset($_SESSION['payment_order']);
        
        header('Location: payment_success.php');
        exit;
        
    } catch (PDOException $e) {
        $error = 'Error processing payment. Please try again.';
    }
}

$current_lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo getTextDirection(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(PORTAL_NAME); ?> - Processing Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-form {
            max-width: 500px;
            margin: 0 auto;
        }
        .secure-badge {
            background: #198754;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <div class="secure-badge">
                        <i class="bi bi-shield-lock"></i> Secure Payment
                    </div>
                    <h3>Complete Payment</h3>
                    <p class="text-muted">Demo Mode - No actual payment will be charged</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h5>Order: <?php echo htmlspecialchars($payment['order_code']); ?></h5>
                            <h2 class="text-success">৳<?php echo number_format($payment['amount'], 2); ?></h2>
                            <p class="text-muted"><?php echo htmlspecialchars($payment['package_name']); ?></p>
                        </div>

                        <form method="POST" class="payment-form">
                            <div class="mb-3">
                                <label class="form-label">Card Number / মোবাইল নম্বর</label>
                                <input type="text" class="form-control" placeholder="01XXXXXXXXX" disabled 
                                       value="<?php echo htmlspecialchars($payment['mobile_number']); ?>">
                                <small class="text-muted">Demo: No actual charge</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">PIN / OTP</label>
                                    <input type="password" class="form-control" placeholder="****" disabled value="1234">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gateway</label>
                                    <input type="text" class="form-control" disabled 
                                           value="<?php echo htmlspecialchars($payment['gateway']); ?>">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="confirm_payment" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle"></i> Confirm Payment (Demo)
                                </button>
                                <a href="payment.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>

                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <strong>Note:</strong> This is a demonstration. In production, this would redirect to the actual payment gateway (bKash, Nagad, Stripe, etc.) for secure payment processing.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

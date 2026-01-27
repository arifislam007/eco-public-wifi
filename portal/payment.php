<?php
/**
 * Payment Processing Page
 * Handle payment for selected WiFi package
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDBConnection();
$error = '';
$success = '';

// Check if there's a pending order
if (!isset($_SESSION['pending_order'])) {
    header('Location: packages.php');
    exit;
}

$order = $_SESSION['pending_order'];

// Get available payment gateways
$gateways = [];
try {
    $gateways = $pdo->query("
        SELECT * FROM payment_gateways 
        WHERE status = 'active'
        ORDER BY display_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
    $gateways = [];
}

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_payment'])) {
    $gateway_id = intval($_POST['gateway_id'] ?? 0);
    
    if ($gateway_id < 1) {
        $error = 'Please select a payment method.';
    } else {
        // Find the gateway
        $selected_gateway = null;
        foreach ($gateways as $gw) {
            if ($gw['id'] == $gateway_id) {
                $selected_gateway = $gw;
                break;
            }
        }
        
        if ($selected_gateway) {
            // Generate order code
            $order_code = 'WF' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            
            // Calculate duration in seconds
            $duration_seconds = $order['duration_seconds'];
            
            // Save order to database
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO package_orders 
                    (order_code, package_id, user_identifier, quantity, total_amount, duration_granted, payment_gateway, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $order_code,
                    $order['package_id'],
                    $order['mobile_number'],
                    $order['quantity'],
                    $order['total_price'],
                    $duration_seconds,
                    $selected_gateway['gateway_name']
                ]);
                
                // Store order info for payment processing
                $_SESSION['payment_order'] = [
                    'order_code' => $order_code,
                    'amount' => $order['total_price'],
                    'mobile_number' => $order['mobile_number'],
                    'gateway' => $selected_gateway['gateway_name'],
                    'package_name' => $order['package_name']
                ];
                
                // Redirect to specific gateway payment page
                // For now, show a simulated payment page
                header('Location: payment_process.php');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Error creating order. Please try again.';
            }
        } else {
            $error = 'Payment gateway not found.';
        }
    }
}

$current_lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo getTextDirection(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(PORTAL_NAME); ?> - Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-method-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .payment-method-card.selected {
            border-color: #198754;
            background-color: #f8fff9;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .price-highlight {
            font-size: 2rem;
            font-weight: bold;
            color: #198754;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="text-center mb-4">
                    <h2><i class="bi bi-credit-card"></i> Complete Your Payment</h2>
                    <p class="text-muted">পেমেন্ট সম্পূর্ণ করুন</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Order Summary -->
                <div class="order-summary mb-4">
                    <h5 class="mb-3">Order Summary / অর্ডার সারাংশ</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td>Package:</td>
                                    <td><strong><?php echo htmlspecialchars($order['package_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>Duration:</td>
                                    <td><?php echo htmlspecialchars($order['duration_text']); ?></td>
                                </tr>
                                <tr>
                                    <td>Mobile:</td>
                                    <td><?php echo htmlspecialchars($order['mobile_number']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-1">Total Amount / মোট মূল্য:</p>
                            <div class="price-highlight">৳<?php echo number_format($order['total_price'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (empty($gateways)): ?>
                    <div class="alert alert-warning">
                        <h5>Payment System Setup Required</h5>
                        <p>No payment gateways are configured yet. Please contact the administrator.</p>
                        <p class="mb-0">পেমেন্ট সিস্টেম কনফিগার করা হয়নি। অনুগ্রহ করে অ্যাডমিনের সাথে যোগাযোগ করুন।</p>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <h5 class="mb-3">Select Payment Method / পেমেন্ট পদ্ধতি নির্বাচন করুন</h5>
                        
                        <div class="row g-3 mb-4">
                            <?php foreach ($gateways as $gateway): ?>
                                <div class="col-md-6">
                                    <div class="payment-method-card" onclick="selectGateway(<?php echo $gateway['id']; ?>)">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gateway_id" 
                                                   id="gateway_<?php echo $gateway['id']; ?>" value="<?php echo $gateway['id']; ?>" required>
                                            <label class="form-check-label w-100" for="gateway_<?php echo $gateway['id']; ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($gateway['display_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo $gateway['sandbox_mode'] ? '(Test Mode)' : ''; ?>
                                                            <?php echo htmlspecialchars($gateway['description'] ?? ''); ?>
                                                        </small>
                                                    </div>
                                                    <i class="bi bi-credit-card fs-3 text-primary"></i>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="proceed_payment" class="btn btn-primary btn-lg">
                                <i class="bi bi-lock"></i> Proceed to Secure Payment
                            </button>
                            <a href="packages.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Packages
                            </a>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Trust Badges -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="bi bi-shield-check"></i> Secure SSL Encryption | 
                        <i class="bi bi-clock"></i> Instant Activation |
                        <i class="bi bi-headset"></i> 24/7 Support
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectGateway(gatewayId) {
            document.getElementById('gateway_' + gatewayId).checked = true;
            
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.getElementById('gateway_' + gatewayId).closest('.payment-method-card').classList.add('selected');
        }
    </script>
</body>
</html>

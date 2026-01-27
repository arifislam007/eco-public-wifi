<?php
/**
 * Payment Success Page
 * Display voucher after successful payment
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/i18n.php';

// Check if there's a purchased voucher
if (!isset($_SESSION['purchased_voucher'])) {
    header('Location: packages.php');
    exit;
}

$voucher = $_SESSION['purchased_voucher'];
$duration_hours = floor($voucher['duration'] / 3600);
$duration_minutes = floor(($voucher['duration'] % 3600) / 60);

$current_lang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo getTextDirection(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(PORTAL_NAME); ?> - Payment Successful</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .success-icon {
            color: #198754;
            font-size: 5rem;
        }
        .voucher-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
        }
        .voucher-code {
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 3px;
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .credentials-box {
            background: #f8f9fa;
            border: 2px dashed #198754;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                
                <h2 class="text-success mb-2">Payment Successful!</h2>
                <h4 class="text-muted mb-4">পেমেন্ট সফল হয়েছে!</h4>
                
                <div class="alert alert-success">
                    <strong>Your WiFi voucher has been generated.</strong><br>
                    আপনার WiFi ভাউচার তৈরি হয়েছে।
                </div>

                <!-- Voucher Display -->
                <div class="voucher-card">
                    <h5><i class="bi bi-wifi"></i> WiFi Voucher</h5>
                    <div class="voucher-code">
                        <?php echo htmlspecialchars($voucher['voucher_code']); ?>
                    </div>
                    <p class="mb-0">Voucher Code / ভাউচার কোড</p>
                </div>

                <!-- Login Credentials -->
                <div class="credentials-box">
                    <h5 class="mb-3">Login Credentials / লগইন তথ্য</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-end"><strong>Username:</strong></td>
                            <td class="text-start"><code class="fs-5"><?php echo htmlspecialchars($voucher['username']); ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Password:</strong></td>
                            <td class="text-start"><code class="fs-5"><?php echo htmlspecialchars($voucher['password']); ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Duration:</strong></td>
                            <td class="text-start">
                                <?php echo $duration_hours; ?> hour(s) 
                                <?php if ($duration_minutes > 0): ?>
                                    <?php echo $duration_minutes; ?> minute(s)
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Instructions -->
                <div class="card mb-4">
                    <div class="card-body text-start">
                        <h5><i class="bi bi-info-circle"></i> How to use / ব্যবহারের নিয়ম:</h5>
                        <ol>
                            <li>Save or screenshot your voucher code and credentials</li>
                            <li>Connect to the WiFi network</li>
                            <li>Enter the username and password on the login page</li>
                            <li>Click "Connect to WiFi" to start browsing</li>
                        </ol>
                        <hr>
                        <ol>
                            <li>আপনার ভাউচার কোড এবং লগইন তথ্য সংরক্ষণ করুন</li>
                            <li>WiFi নেটওয়ার্কে সংযুক্ত হন</li>
                            <li>লগইন পেজে ইউজারনেম এবং পাসওয়ার্ড দিন</li>
                            <li>"Connect to WiFi" ক্লিক করে ব্রাউজিং শুরু করুন</li>
                        </ol>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Go to Login Page
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-printer"></i> Print / Save
                    </button>
                </div>

                <div class="mt-4">
                    <p class="text-muted">
                        A confirmation SMS has been sent to <?php echo htmlspecialchars($voucher['mobile']); ?>
                    </p>
                </div>

                <!-- Clear voucher from session after display -->
                <?php unset($_SESSION['purchased_voucher']); ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

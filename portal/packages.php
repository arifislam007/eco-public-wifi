<?php
/**
 * WiFi Package Selection Page
 * Users can view and select internet packages
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

$pdo = getDBConnection();
$error = '';
$success = '';

// Get all active packages
$packages = [];
try {
    $packages = $pdo->query("
        SELECT * FROM wifi_packages 
        WHERE is_active = 1 
        ORDER BY display_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $packages = [
        [
            'id' => 1,
            'package_name' => '2 Hours Internet',
            'package_type' => 'hourly',
            'duration_value' => 2,
            'duration_unit' => 'hour',
            'base_price' => 15.00,
            'price_per_unit' => null,
            'data_limit_mb' => null,
            'speed_limit_mbps' => null,
            'description' => '2 hours of high-speed internet access',
            'is_custom' => 0
        ],
        [
            'id' => 2,
            'package_name' => '1 Day Internet',
            'package_type' => 'daily',
            'duration_value' => 1,
            'duration_unit' => 'day',
            'base_price' => 30.00,
            'price_per_unit' => null,
            'data_limit_mb' => null,
            'speed_limit_mbps' => null,
            'description' => '24 hours of high-speed internet access',
            'is_custom' => 0
        ],
        [
            'id' => 3,
            'package_name' => 'Custom Hours',
            'package_type' => 'custom_hourly',
            'duration_value' => 1,
            'duration_unit' => 'hour',
            'base_price' => 0.00,
            'price_per_unit' => 10.00,
            'data_limit_mb' => null,
            'speed_limit_mbps' => null,
            'description' => 'Choose how many hours you need. BDT 10 per hour',
            'is_custom' => 1
        ],
        [
            'id' => 4,
            'package_name' => 'Custom Days',
            'package_type' => 'custom_daily',
            'duration_value' => 1,
            'duration_unit' => 'day',
            'base_price' => 0.00,
            'price_per_unit' => 30.00,
            'data_limit_mb' => null,
            'speed_limit_mbps' => null,
            'description' => 'Choose how many days you need. BDT 30 per day',
            'is_custom' => 1
        ]
    ];
}

// Handle package selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_package'])) {
    $package_id = intval($_POST['package_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    
    if ($package_id < 1) {
        $error = 'Please select a package.';
    } elseif (empty($mobile_number)) {
        $error = 'Please enter your mobile number.';
    } elseif ($quantity < 1) {
        $error = 'Quantity must be at least 1.';
    } else {
        // Find the package
        $selected_package = null;
        foreach ($packages as $pkg) {
            if ($pkg['id'] == $package_id) {
                $selected_package = $pkg;
                break;
            }
        }
        
        if ($selected_package) {
            // Calculate total price
            if ($selected_package['is_custom'] && $selected_package['price_per_unit'] > 0) {
                $total_price = $quantity * $selected_package['price_per_unit'];
                $duration_seconds = $quantity * ($selected_package['duration_unit'] === 'hour' ? 3600 : 86400);
            } else {
                $total_price = $selected_package['base_price'];
                $duration_seconds = $selected_package['duration_value'] * ($selected_package['duration_unit'] === 'hour' ? 3600 : 86400);
            }
            
            // Store in session and redirect to payment
            $_SESSION['pending_order'] = [
                'package_id' => $package_id,
                'package_name' => $selected_package['package_name'],
                'quantity' => $quantity,
                'mobile_number' => $mobile_number,
                'total_price' => $total_price,
                'duration_seconds' => $duration_seconds,
                'duration_text' => $selected_package['is_custom'] 
                    ? "$quantity " . $selected_package['duration_unit'] . "(s)"
                    : $selected_package['duration_value'] . " " . $selected_package['duration_unit'] . "(s)"
            ];
            
            header('Location: payment.php');
            exit;
        } else {
            $error = 'Package not found.';
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
    <title><?php echo htmlspecialchars(PORTAL_NAME); ?> - <?php echo t('packages'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .package-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #0d6efd;
        }
        .price-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #198754;
        }
        .duration-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
        .feature-list li i {
            color: #198754;
            margin-right: 8px;
        }
        .recommended-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #dc3545;
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .quantity-selector {
            max-width: 150px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="index.php?lang=en" class="btn btn-sm btn-outline-secondary <?php echo $current_lang === 'en' ? 'active' : ''; ?>">English</a>
        <a href="index.php?lang=bn" class="btn btn-sm btn-outline-secondary <?php echo $current_lang === 'bn' ? 'active' : ''; ?>">বাংলা</a>
    </div>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold"><?php echo htmlspecialchars(PORTAL_NAME); ?></h1>
            <p class="lead text-muted">Choose your internet package</p>
            <p class="text-muted">মোবাইল নম্বর দিয়ে প্যাকেজ কিনুন এবং ইন্টারনেট ব্যবহার করুন</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Mobile Number Input -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title text-center mb-3">
                            <i class="bi bi-phone"></i> Enter Your Mobile Number
                        </h5>
                        <form method="POST" id="packageForm">
                            <div class="mb-3">
                                <input type="tel" class="form-control form-control-lg text-center" 
                                       name="mobile_number" id="mobile_number" required
                                       placeholder="01XXXXXXXXX" pattern="01[0-9]{9}"
                                       value="<?php echo isset($_POST['mobile_number']) ? htmlspecialchars($_POST['mobile_number']) : ''; ?>">
                                <small class="text-muted">আপনার মোবাইল নম্বর দিন (01XXXXXXXXX)</small>
                            </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Packages Grid -->
        <div class="row g-4">
            <?php foreach ($packages as $index => $package): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card package-card h-100 position-relative">
                        <?php if ($index === 1): ?>
                            <div class="recommended-badge">POPULAR</div>
                        <?php endif; ?>
                        
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h5 class="mb-0"><?php echo htmlspecialchars($package['package_name']); ?></h5>
                        </div>
                        
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <span class="price-display">
                                    ৳<?php echo $package['is_custom'] ? $package['price_per_unit'] : number_format($package['base_price'], 0); ?>
                                </span>
                                <?php if ($package['is_custom']): ?>
                                    <small class="text-muted">/<?php echo $package['duration_unit']; ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <span class="badge bg-info duration-badge mb-3">
                                <?php if ($package['is_custom']): ?>
                                    Custom <?php echo $package['duration_unit']; ?>s
                                <?php else: ?>
                                    <?php echo $package['duration_value']; ?> <?php echo $package['duration_unit']; ?>(s)
                                <?php endif; ?>
                            </span>
                            
                            <p class="text-muted small"><?php echo htmlspecialchars($package['description']); ?></p>
                            
                            <ul class="feature-list text-start">
                                <li><i class="bi bi-check-circle-fill"></i> High-speed internet</li>
                                <?php if ($package['data_limit_mb']): ?>
                                    <li><i class="bi bi-check-circle-fill"></i> <?php echo $package['data_limit_mb']; ?> MB data</li>
                                <?php else: ?>
                                    <li><i class="bi bi-check-circle-fill"></i> Unlimited data</li>
                                <?php endif; ?>
                                <?php if ($package['speed_limit_mbps']): ?>
                                    <li><i class="bi bi-check-circle-fill"></i> <?php echo $package['speed_limit_mbps']; ?> Mbps speed</li>
                                <?php else: ?>
                                    <li><i class="bi bi-check-circle-fill"></i> Full speed</li>
                                <?php endif; ?>
                                <li><i class="bi bi-check-circle-fill"></i> Instant activation</li>
                            </ul>
                            
                            <?php if ($package['is_custom']): ?>
                                <div class="mb-3">
                                    <label class="form-label">Quantity (<?php echo $package['duration_unit']; ?>s)</label>
                                    <div class="quantity-selector">
                                        <input type="number" class="form-control" name="quantity_<?php echo $package['id']; ?>" 
                                               min="1" max="30" value="1"
                                               onchange="updatePrice(<?php echo $package['id']; ?>, <?php echo $package['price_per_unit']; ?>)">
                                    </div>
                                    <div class="mt-2">
                                        <strong>Total: ৳<span id="price_<?php echo $package['id']; ?>"><?php echo $package['price_per_unit']; ?></span></strong>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="quantity_<?php echo $package['id']; ?>" value="1">
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-transparent border-0 pb-4">
                            <button type="submit" name="select_package" class="btn btn-primary btn-lg w-100"
                                    onclick="return selectPackage(<?php echo $package['id']; ?>, <?php echo $package['is_custom'] ? 'true' : 'false'; ?>)">
                                <i class="bi bi-cart"></i> Buy Now
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <input type="hidden" name="package_id" id="selected_package_id">
        <input type="hidden" name="quantity" id="selected_quantity" value="1">
        </form>

        <!-- Alternative Login Option -->
        <div class="text-center mt-5">
            <p class="text-muted">Already have a voucher or account?</p>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-in-right"></i> Login with Voucher/Account
            </a>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                Need help? Contact support at <?php echo htmlspecialchars(SUPPORT_EMAIL); ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updatePrice(packageId, pricePerUnit) {
            const quantity = document.querySelector(`[name="quantity_${packageId}"]`).value;
            const total = quantity * pricePerUnit;
            document.getElementById(`price_${packageId}`).textContent = total.toFixed(2);
        }
        
        function selectPackage(packageId, isCustom) {
            const mobileNumber = document.getElementById('mobile_number').value;
            if (!mobileNumber) {
                alert('Please enter your mobile number first.');
                document.getElementById('mobile_number').focus();
                return false;
            }
            
            document.getElementById('selected_package_id').value = packageId;
            
            if (isCustom) {
                const quantity = document.querySelector(`[name="quantity_${packageId}"]`).value;
                document.getElementById('selected_quantity').value = quantity;
            }
            
            return true;
        }
    </script>
</body>
</html>

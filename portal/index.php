<?php
/**
 * Public Wi-Fi Captive Portal - Login Page
 * Supports: Username/Password, OTP (Mobile), Voucher
 */

session_start();

// Configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/voucher.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/i18n.php';

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    setLanguage($_GET['lang']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Check if already authenticated
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: success.php');
    exit;
}

$error = '';
$success = '';
$login_method = $_GET['method'] ?? 'username'; // username, otp, voucher
$otp_sent = false;
$mobile_number = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $accept_terms = isset($_POST['accept_terms']);
    
    // Rate limiting check
    if (!checkRateLimit($_SERVER['REMOTE_ADDR'])) {
        $error = t('too_many_attempts');
    } elseif (!$accept_terms) {
        $error = t('must_accept_terms');
    } else {
        
        if ($action === 'send_otp') {
            // Send OTP
            $mobile_number = trim($_POST['mobile_number'] ?? '');
            if (empty($mobile_number)) {
                $error = t('enter_mobile_number');
            } else {
                $otp_result = generateAndSendOTP($mobile_number);
                if ($otp_result['success']) {
                    $otp_sent = true;
                    $success = t('otp_sent');
                    $_SESSION['otp_mobile'] = $mobile_number;
                } else {
                    $error = $otp_result['message'];
                }
            }
            
        } elseif ($action === 'verify_otp') {
            // Verify OTP
            $mobile_number = $_SESSION['otp_mobile'] ?? '';
            $otp_code = trim($_POST['otp_code'] ?? '');
            
            if (empty($mobile_number) || empty($otp_code)) {
                $error = t('enter_otp_code');
            } else {
                $verify_result = verifyOTP($mobile_number, $otp_code);
                if ($verify_result['success']) {
                    $username = $verify_result['username'];
                    
                    // Check concurrent sessions
                    $session_check = checkConcurrentSessions($username);
                    if (!$session_check['allowed']) {
                        $error = $session_check['message'];
                    } else {
                        // Check usage limits
                        $daily_check = checkDailyLimit($username);
                        $monthly_check = checkMonthlyLimit($username);
                        
                        if (!$daily_check['allowed']) {
                            $error = $daily_check['message'];
                        } elseif (!$monthly_check['allowed']) {
                            $error = $monthly_check['message'];
                        } else {
                            // Apply bandwidth limits
                            applyBandwidthLimits($username);
                            
                            // Create session
                            $session_id = session_id();
                            createActiveSession($username, $session_id, $_SERVER['REMOTE_ADDR']);
                            
                            logLoginAttempt($_SERVER['REMOTE_ADDR'], $username, true);
                            
                            $_SESSION['authenticated'] = true;
                            $_SESSION['username'] = $username;
                            $_SESSION['login_time'] = time();
                            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                            $_SESSION['login_method'] = 'otp';
                            
                            header('Location: success.php');
                            exit;
                        }
                    }
                } else {
                    $error = $verify_result['message'];
                }
            }
            
        } elseif ($action === 'voucher') {
            // Voucher login
            $voucher_code = trim($_POST['voucher_code'] ?? '');
            
            if (empty($voucher_code)) {
                $error = t('enter_voucher_code');
            } else {
                $voucher_result = activateVoucher($voucher_code);
                if ($voucher_result['success']) {
                    $username = $voucher_result['username'];
                    
                    // Check voucher limits
                    $voucher = validateVoucher($voucher_code);
                    if ($voucher['success']) {
                        $limits_check = checkVoucherLimits($username, $voucher['voucher']);
                        if (!$limits_check['allowed']) {
                            $error = $limits_check['message'];
                        } else {
                            // Check concurrent sessions
                            $session_check = checkConcurrentSessions($username);
                            if (!$session_check['allowed']) {
                                $error = $session_check['message'];
                            } else {
                                // Apply bandwidth limits
                                applyBandwidthLimits($username);
                                
                                // Create session
                                $session_id = session_id();
                                createActiveSession($username, $session_id, $_SERVER['REMOTE_ADDR']);
                                
                                logLoginAttempt($_SERVER['REMOTE_ADDR'], $username, true);
                                
                                $_SESSION['authenticated'] = true;
                                $_SESSION['username'] = $username;
                                $_SESSION['login_time'] = time();
                                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                                $_SESSION['login_method'] = 'voucher';
                                $_SESSION['voucher_code'] = $voucher_code;
                                
                                header('Location: success.php');
                                exit;
                            }
                        }
                    } else {
                        $error = $voucher_result['message'];
                    }
                } else {
                    $error = $voucher_result['message'];
                }
            }
            
        } else {
            // Username/Password login
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = t('enter_username_password');
            } else {
                $username = sanitizeInput($username);
                
                // Authenticate with FreeRADIUS
                $auth_result = authenticateUser($username, $password);
                
                if ($auth_result['success']) {
                    // Check concurrent sessions
                    $session_check = checkConcurrentSessions($username);
                    if (!$session_check['allowed']) {
                        $error = $session_check['message'];
                    } else {
                        // Check usage limits
                        $daily_check = checkDailyLimit($username);
                        $monthly_check = checkMonthlyLimit($username);
                        
                        if (!$daily_check['allowed']) {
                            $error = $daily_check['message'];
                        } elseif (!$monthly_check['allowed']) {
                            $error = $monthly_check['message'];
                        } else {
                            // Apply bandwidth limits
                            applyBandwidthLimits($username);
                            
                            // Create session
                            $session_id = session_id();
                            createActiveSession($username, $session_id, $_SERVER['REMOTE_ADDR']);
                            
                            logLoginAttempt($_SERVER['REMOTE_ADDR'], $username, true);
                            
                            $_SESSION['authenticated'] = true;
                            $_SESSION['username'] = $username;
                            $_SESSION['login_time'] = time();
                            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                            $_SESSION['login_method'] = 'username';
                            
                            header('Location: success.php');
                            exit;
                        }
                    }
                } else {
                    logLoginAttempt($_SERVER['REMOTE_ADDR'], $username, false);
                    $error = $auth_result['message'] ?? t('invalid_credentials');
                }
            }
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
    <title><?php echo htmlspecialchars(PORTAL_NAME); ?> - <?php echo t('login'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .lang-switcher {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .auth-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        .auth-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c757d;
            padding: 10px 20px;
        }
        .auth-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
            background: none;
        }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="?lang=en" class="btn btn-sm btn-outline-secondary <?php echo $current_lang === 'en' ? 'active' : ''; ?>">English</a>
        <a href="?lang=bn" class="btn btn-sm btn-outline-secondary <?php echo $current_lang === 'bn' ? 'active' : ''; ?>">বাংলা</a>
    </div>
    
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="assets/img/logo.png" alt="Logo" class="mb-3" style="max-height: 60px;" onerror="this.style.display='none'">
                            <h2 class="card-title mb-1"><?php echo htmlspecialchars(PORTAL_NAME); ?></h2>
                            <p class="text-muted small"><?php echo t('welcome'); ?></p>
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

                        <!-- Authentication Method Tabs -->
                        <ul class="nav nav-tabs auth-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $login_method === 'username' ? 'active' : ''; ?>" 
                                   href="?method=username"><?php echo t('login_with_username'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $login_method === 'otp' ? 'active' : ''; ?>" 
                                   href="?method=otp"><?php echo t('login_with_otp'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $login_method === 'voucher' ? 'active' : ''; ?>" 
                                   href="?method=voucher"><?php echo t('login_with_voucher'); ?></a>
                            </li>
                        </ul>

                        <!-- Username/Password Form -->
                        <?php if ($login_method === 'username'): ?>
                            <form method="POST" action="" id="loginForm">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label for="username" class="form-label"><?php echo t('username'); ?></label>
                                    <input type="text" class="form-control form-control-lg" id="username" name="username" 
                                           placeholder="<?php echo t('username'); ?>" required autofocus autocomplete="username">
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label"><?php echo t('password'); ?></label>
                                    <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                           placeholder="<?php echo t('password'); ?>" required autocomplete="current-password">
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="accept_terms" name="accept_terms" required>
                                    <label class="form-check-label" for="accept_terms">
                                        <?php echo t('accept_terms'); ?> 
                                        <a href="terms.php" target="_blank"><?php echo t('terms_link'); ?></a>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                    <?php echo t('connect_to_wifi'); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- OTP Form -->
                        <?php if ($login_method === 'otp'): ?>
                            <?php if (!$otp_sent): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="send_otp">
                                    <div class="mb-3">
                                        <label for="mobile_number" class="form-label"><?php echo t('mobile_number'); ?></label>
                                        <input type="tel" class="form-control form-control-lg" id="mobile_number" name="mobile_number" 
                                               placeholder="01XXXXXXXXX" required autofocus>
                                        <small class="text-muted"><?php echo $current_lang === 'bn' ? 'বাংলাদেশ মোবাইল নম্বর' : 'Bangladesh mobile number'; ?></small>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="accept_terms_otp" name="accept_terms" required>
                                        <label class="form-check-label" for="accept_terms_otp">
                                            <?php echo t('accept_terms'); ?> 
                                            <a href="terms.php" target="_blank"><?php echo t('terms_link'); ?></a>
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                        <?php echo t('send_otp'); ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="verify_otp">
                                    <div class="mb-3">
                                        <label for="otp_code" class="form-label"><?php echo t('otp_code'); ?></label>
                                        <input type="text" class="form-control form-control-lg text-center" id="otp_code" name="otp_code" 
                                               placeholder="000000" required autofocus maxlength="6" pattern="[0-9]{6}">
                                        <small class="text-muted"><?php echo $current_lang === 'bn' ? 'আপনার মোবাইলে পাঠানো 6 সংখ্যার কোড' : '6-digit code sent to your mobile'; ?></small>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="accept_terms_otp2" name="accept_terms" checked required>
                                        <label class="form-check-label" for="accept_terms_otp2">
                                            <?php echo t('accept_terms'); ?>
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                        <?php echo t('verify_otp'); ?>
                                    </button>
                                    <a href="?method=otp" class="btn btn-link w-100"><?php echo $current_lang === 'bn' ? 'নতুন OTP পাঠান' : 'Resend OTP'; ?></a>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Voucher Form -->
                        <?php if ($login_method === 'voucher'): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="voucher">
                                <div class="mb-3">
                                    <label for="voucher_code" class="form-label"><?php echo t('voucher_code'); ?></label>
                                    <input type="text" class="form-control form-control-lg" id="voucher_code" name="voucher_code" 
                                           placeholder="<?php echo t('voucher_code'); ?>" required autofocus>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="accept_terms_voucher" name="accept_terms" required>
                                    <label class="form-check-label" for="accept_terms_voucher">
                                        <?php echo t('accept_terms'); ?> 
                                        <a href="terms.php" target="_blank"><?php echo t('terms_link'); ?></a>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                    <?php echo t('connect_to_wifi'); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <?php echo t('need_help'); ?> <?php echo t('contact_support'); ?> 
                                <?php echo htmlspecialchars(SUPPORT_EMAIL); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <small class="text-muted">
                        Powered by FreeRADIUS &copy; <?php echo date('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

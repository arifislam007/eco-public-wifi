<?php
/**
 * Terms and Conditions Page
 */

require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - <?php echo htmlspecialchars(PORTAL_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Terms and Conditions</h3>
                    </div>
                    <div class="card-body">
                        <h5>Acceptable Use Policy</h5>
                        <p>By using this public Wi-Fi service, you agree to the following terms:</p>
                        
                        <ol>
                            <li><strong>Acceptable Use:</strong> You agree to use this service only for lawful purposes and in a manner that does not infringe the rights of others.</li>
                            
                            <li><strong>Prohibited Activities:</strong> The following activities are strictly prohibited:
                                <ul>
                                    <li>Accessing or transmitting illegal content</li>
                                    <li>Attempting to breach network security</li>
                                    <li>Spamming or sending unsolicited emails</li>
                                    <li>Downloading or sharing copyrighted material without permission</li>
                                    <li>Any activity that may harm the network or other users</li>
                                </ul>
                            </li>
                            
                            <li><strong>Privacy:</strong> While we take measures to protect your privacy, this is a public network. Do not transmit sensitive information without encryption.</li>
                            
                            <li><strong>Service Availability:</strong> We do not guarantee uninterrupted service. The service may be unavailable due to maintenance or technical issues.</li>
                            
                            <li><strong>Bandwidth Limits:</strong> Your access may be subject to time and bandwidth limitations as specified in your account.</li>
                            
                            <li><strong>Account Responsibility:</strong> You are responsible for all activities conducted through your account. Keep your credentials secure.</li>
                            
                            <li><strong>Termination:</strong> We reserve the right to terminate or suspend access for violations of these terms.</li>
                        </ol>
                        
                        <div class="alert alert-warning mt-4">
                            <strong>Disclaimer:</strong> This service is provided "as is" without warranties. We are not responsible for any damages resulting from use of this service.
                        </div>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Internationalization (i18n) Functions
 * Multi-language support for Bangla and English
 */

// Language strings
$lang_strings = [
    'en' => [
        'welcome' => 'Welcome',
        'login' => 'Login',
        'username' => 'Username',
        'password' => 'Password',
        'mobile_number' => 'Mobile Number',
        'voucher_code' => 'Voucher Code',
        'otp_code' => 'OTP Code',
        'send_otp' => 'Send OTP',
        'verify_otp' => 'Verify OTP',
        'login_with_otp' => 'Login with Mobile (OTP)',
        'login_with_voucher' => 'Login with Voucher',
        'login_with_username' => 'Login with Username',
        'connect_to_wifi' => 'Connect to Wi-Fi',
        'accept_terms' => 'I accept the Terms and Conditions',
        'terms_link' => 'Terms and Conditions',
        'invalid_credentials' => 'Invalid username or password',
        'invalid_otp' => 'Invalid or expired OTP',
        'otp_sent' => 'OTP sent to your mobile number',
        'login_success' => 'Successfully Connected!',
        'need_help' => 'Need help?',
        'contact_support' => 'Contact support at',
        'session_info' => 'Session Information',
        'connected' => 'Connected',
        'go_to_internet' => 'Go to Internet',
        'refresh_status' => 'Refresh Status',
    ],
    'bn' => [
        'welcome' => 'স্বাগতম',
        'login' => 'লগইন',
        'username' => 'ব্যবহারকারীর নাম',
        'password' => 'পাসওয়ার্ড',
        'mobile_number' => 'মোবাইল নম্বর',
        'voucher_code' => 'ভাউচার কোড',
        'otp_code' => 'OTP কোড',
        'send_otp' => 'OTP পাঠান',
        'verify_otp' => 'OTP যাচাই করুন',
        'login_with_otp' => 'মোবাইল দিয়ে লগইন (OTP)',
        'login_with_voucher' => 'ভাউচার দিয়ে লগইন',
        'login_with_username' => 'ব্যবহারকারীর নাম দিয়ে লগইন',
        'connect_to_wifi' => 'Wi-Fi এ সংযুক্ত হন',
        'accept_terms' => 'আমি শর্তাবলী গ্রহণ করছি',
        'terms_link' => 'শর্তাবলী',
        'invalid_credentials' => 'ভুল ব্যবহারকারীর নাম বা পাসওয়ার্ড',
        'invalid_otp' => 'ভুল বা মেয়াদোত্তীর্ণ OTP',
        'otp_sent' => 'আপনার মোবাইল নম্বরে OTP পাঠানো হয়েছে',
        'login_success' => 'সফলভাবে সংযুক্ত!',
        'need_help' => 'সাহায্য প্রয়োজন?',
        'contact_support' => 'সমর্থনে যোগাযোগ করুন',
        'session_info' => 'সেশন তথ্য',
        'connected' => 'সংযুক্ত',
        'go_to_internet' => 'ইন্টারনেটে যান',
        'refresh_status' => 'স্থিতি রিফ্রেশ করুন',
        'too_many_attempts' => 'অনেক বেশি লগইন প্রচেষ্টা। পরে আবার চেষ্টা করুন।',
        'must_accept_terms' => 'আপনাকে অবশ্যই শর্তাবলী গ্রহণ করতে হবে।',
        'enter_mobile_number' => 'মোবাইল নম্বর দিন',
        'enter_otp_code' => 'OTP কোড দিন',
        'enter_voucher_code' => 'ভাউচার কোড দিন',
        'enter_username_password' => 'ব্যবহারকারীর নাম এবং পাসওয়ার্ড উভয়ই দিন',
    ]
];

/**
 * Get current language
 * 
 * @return string Language code (en/bn)
 */
function getCurrentLanguage() {
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    
    // Detect from browser or default to English
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if ($lang === 'bn') {
            return 'bn';
        }
    }
    
    return 'en';
}

/**
 * Set language
 * 
 * @param string $lang Language code (en/bn)
 */
function setLanguage($lang) {
    if (in_array($lang, ['en', 'bn'])) {
        $_SESSION['language'] = $lang;
    }
}

/**
 * Get translated string
 * 
 * @param string $key Translation key
 * @param string $lang Optional language code
 * @return string Translated string
 */
function t($key, $lang = null) {
    global $lang_strings;
    
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    if (isset($lang_strings[$lang][$key])) {
        return $lang_strings[$lang][$key];
    }
    
    // Fallback to English
    if (isset($lang_strings['en'][$key])) {
        return $lang_strings['en'][$key];
    }
    
    // Return key if not found
    return $key;
}

/**
 * Get HTML direction attribute for language
 * 
 * @return string 'ltr' or 'rtl'
 */
function getTextDirection() {
    $lang = getCurrentLanguage();
    return $lang === 'bn' ? 'ltr' : 'ltr'; // Bangla uses LTR
}

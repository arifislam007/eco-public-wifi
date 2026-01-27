-- Additional Schema for Advanced Features
-- Run this after the base schema.sql

USE radius;

-- OTP/SMS Authentication Tables
CREATE TABLE IF NOT EXISTS otp_codes (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    mobile_number varchar(20) NOT NULL,
    otp_code varchar(10) NOT NULL,
    username varchar(64) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp NOT NULL,
    verified tinyint(1) DEFAULT '0',
    verified_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY mobile_number (mobile_number),
    KEY otp_code (otp_code),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mobile number to username mapping
CREATE TABLE IF NOT EXISTS mobile_users (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    mobile_number varchar(20) NOT NULL,
    username varchar(64) NOT NULL,
    verified tinyint(1) DEFAULT '0',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY mobile_number (mobile_number),
    KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enhanced Voucher Management
CREATE TABLE IF NOT EXISTS vouchers (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    voucher_code varchar(64) NOT NULL,
    username varchar(64) NOT NULL,
    password varchar(255) NOT NULL,
    voucher_type enum('time', 'data', 'unlimited') DEFAULT 'time',
    time_limit int(11) DEFAULT NULL COMMENT 'Time limit in seconds',
    data_limit bigint(20) DEFAULT NULL COMMENT 'Data limit in bytes',
    daily_limit bigint(20) DEFAULT NULL COMMENT 'Daily data limit in bytes',
    monthly_limit bigint(20) DEFAULT NULL COMMENT 'Monthly data limit in bytes',
    expiry_date datetime DEFAULT NULL,
    max_sessions int(11) DEFAULT '1' COMMENT 'Max concurrent sessions',
    status enum('active', 'used', 'expired', 'disabled') DEFAULT 'active',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at timestamp NULL DEFAULT NULL,
    expires_at timestamp NULL DEFAULT NULL,
    created_by int(11) DEFAULT NULL COMMENT 'Admin user ID',
    notes text,
    PRIMARY KEY (id),
    UNIQUE KEY voucher_code (voucher_code),
    KEY username (username),
    KEY status (status),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Groups for Policy Management
CREATE TABLE IF NOT EXISTS user_groups (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL,
    description text,
    max_sessions int(11) DEFAULT '1',
    session_timeout int(11) DEFAULT '3600',
    idle_timeout int(11) DEFAULT '600',
    daily_limit bigint(20) DEFAULT NULL,
    monthly_limit bigint(20) DEFAULT NULL,
    download_speed int(11) DEFAULT NULL COMMENT 'Speed in kbps',
    upload_speed int(11) DEFAULT NULL COMMENT 'Speed in kbps',
    burst_download int(11) DEFAULT NULL COMMENT 'Burst speed in kbps',
    burst_upload int(11) DEFAULT NULL COMMENT 'Burst speed in kbps',
    fup_enabled tinyint(1) DEFAULT '0',
    fup_threshold bigint(20) DEFAULT NULL COMMENT 'FUP threshold in bytes',
    fup_speed int(11) DEFAULT NULL COMMENT 'Speed after FUP in kbps',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY groupname (groupname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily Usage Tracking
CREATE TABLE IF NOT EXISTS daily_usage (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    usage_date date NOT NULL,
    bytes_in bigint(20) DEFAULT '0',
    bytes_out bigint(20) DEFAULT '0',
    total_bytes bigint(20) DEFAULT '0',
    session_count int(11) DEFAULT '0',
    PRIMARY KEY (id),
    UNIQUE KEY username_date (username, usage_date),
    KEY usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Monthly Usage Tracking
CREATE TABLE IF NOT EXISTS monthly_usage (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    usage_month date NOT NULL COMMENT 'First day of month',
    bytes_in bigint(20) DEFAULT '0',
    bytes_out bigint(20) DEFAULT '0',
    total_bytes bigint(20) DEFAULT '0',
    session_count int(11) DEFAULT '0',
    PRIMARY KEY (id),
    UNIQUE KEY username_month (username, usage_month),
    KEY usage_month (usage_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Active Sessions Tracking
CREATE TABLE IF NOT EXISTS active_sessions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    session_id varchar(64) NOT NULL,
    ip_address varchar(45) NOT NULL,
    mac_address varchar(17) DEFAULT NULL,
    start_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    bytes_in bigint(20) DEFAULT '0',
    bytes_out bigint(20) DEFAULT '0',
    PRIMARY KEY (id),
    UNIQUE KEY session_id (session_id),
    KEY username (username),
    KEY ip_address (ip_address),
    KEY start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS Log for OTP
CREATE TABLE IF NOT EXISTS sms_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    mobile_number varchar(20) NOT NULL,
    otp_code varchar(10) NOT NULL,
    message text,
    status enum('sent', 'failed', 'delivered') DEFAULT 'sent',
    provider varchar(50) DEFAULT NULL,
    provider_response text,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY mobile_number (mobile_number),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Policies
CREATE TABLE IF NOT EXISTS user_policies (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    max_sessions int(11) DEFAULT '1',
    session_timeout int(11) DEFAULT NULL,
    idle_timeout int(11) DEFAULT NULL,
    daily_limit bigint(20) DEFAULT NULL,
    monthly_limit bigint(20) DEFAULT NULL,
    download_speed int(11) DEFAULT NULL,
    upload_speed int(11) DEFAULT NULL,
    burst_download int(11) DEFAULT NULL,
    burst_upload int(11) DEFAULT NULL,
    fup_enabled tinyint(1) DEFAULT '0',
    fup_threshold bigint(20) DEFAULT NULL,
    fup_speed int(11) DEFAULT NULL,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Voucher Usage History
CREATE TABLE IF NOT EXISTS voucher_usage (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    voucher_id int(11) unsigned NOT NULL,
    username varchar(64) NOT NULL,
    session_id varchar(64) NOT NULL,
    start_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time timestamp NULL DEFAULT NULL,
    bytes_used bigint(20) DEFAULT '0',
    time_used int(11) DEFAULT '0' COMMENT 'Time used in seconds',
    PRIMARY KEY (id),
    KEY voucher_id (voucher_id),
    KEY username (username),
    KEY start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes to existing tables for better performance
ALTER TABLE radacct ADD INDEX idx_username_starttime (username, acctstarttime);
ALTER TABLE radacct ADD INDEX idx_stoptime (acctstoptime);

-- NAS (Network Access Server) Management Table
-- This table stores RADIUS clients (routers/APs) that can authenticate users
CREATE TABLE IF NOT EXISTS nas (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    nasname varchar(128) NOT NULL COMMENT 'IP address or hostname',
    shortname varchar(32) DEFAULT NULL COMMENT 'Short name/alias',
    type varchar(30) DEFAULT 'other' COMMENT 'NAS type (mikrotik, cisco, other)',
    ports int(5) DEFAULT NULL COMMENT 'Number of ports',
    secret varchar(60) NOT NULL DEFAULT 'secret' COMMENT 'Shared secret',
    server varchar(64) DEFAULT NULL COMMENT 'Virtual server name',
    community varchar(50) DEFAULT NULL COMMENT 'SNMP community',
    description varchar(200) DEFAULT NULL COMMENT 'Description',
    status enum('active', 'disabled') DEFAULT 'active',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL COMMENT 'Admin user ID',
    PRIMARY KEY (id),
    UNIQUE KEY nasname (nasname),
    KEY shortname (shortname),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default groups
INSERT INTO user_groups (groupname, description, max_sessions, session_timeout, download_speed, upload_speed) VALUES
('default', 'Default user group', 1, 3600, 1024, 512),
('premium', 'Premium users with higher limits', 2, 7200, 2048, 1024),
('voucher', 'Voucher users', 1, 3600, 512, 256)
ON DUPLICATE KEY UPDATE groupname=groupname;

-- Resellers Table
-- Manage resellers who can create their own users and vouchers
CREATE TABLE IF NOT EXISTS resellers (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    reseller_name varchar(128) NOT NULL,
    reseller_code varchar(32) NOT NULL COMMENT 'Unique code for voucher prefix',
    contact_person varchar(128) DEFAULT NULL,
    email varchar(255) DEFAULT NULL,
    phone varchar(20) DEFAULT NULL,
    address text,
    commission_rate decimal(5,2) DEFAULT '0.00' COMMENT 'Commission percentage',
    balance decimal(10,2) DEFAULT '0.00',
    status enum('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY reseller_code (reseller_code),
    UNIQUE KEY email (email),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update admin_users table to support roles
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role enum('admin', 'reseller') DEFAULT 'admin';
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS reseller_id int(11) unsigned DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS status enum('active', 'inactive') DEFAULT 'active';
ALTER TABLE admin_users ADD FOREIGN KEY IF NOT EXISTS (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL;

-- Update vouchers table to track reseller
ALTER TABLE vouchers ADD COLUMN IF NOT EXISTS reseller_id int(11) unsigned DEFAULT NULL;
ALTER TABLE vouchers ADD COLUMN IF NOT EXISTS voucher_prefix varchar(10) DEFAULT NULL COMMENT 'Reseller code prefix';
ALTER TABLE vouchers ADD FOREIGN KEY IF NOT EXISTS (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL;

-- Update radcheck to track reseller (for users created by resellers)
ALTER TABLE radcheck ADD COLUMN IF NOT EXISTS reseller_id int(11) unsigned DEFAULT NULL;
ALTER TABLE radcheck ADD COLUMN IF NOT EXISTS created_by_type enum('admin', 'reseller') DEFAULT 'admin';
ALTER TABLE radcheck ADD FOREIGN KEY IF NOT EXISTS (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL;

-- Reseller transactions table
CREATE TABLE IF NOT EXISTS reseller_transactions (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    reseller_id int(11) unsigned NOT NULL,
    transaction_type enum('credit', 'debit', 'commission', 'refund') NOT NULL,
    amount decimal(10,2) NOT NULL,
    description text,
    reference_id varchar(64) DEFAULT NULL,
    created_by int(11) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY reseller_id (reseller_id),
    KEY created_at (created_at),
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample reseller (optional)
INSERT INTO resellers (reseller_name, reseller_code, contact_person, email, phone, status, commission_rate) VALUES
('Demo Reseller', 'DEMO', 'John Doe', 'demo@example.com', '01700000000', 'active', 10.00)
ON DUPLICATE KEY UPDATE reseller_code=reseller_code;

-- Create a reseller user (password: reseller123)
INSERT INTO admin_users (username, password_hash, email, role, reseller_id, status) VALUES
('reseller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reseller@example.com', 'reseller', 1, 'active')
ON DUPLICATE KEY UPDATE username=username; 

-- Payment Gateways Table
-- Manage payment gateway configurations (bKash, Nagad, Stripe, etc.)
CREATE TABLE IF NOT EXISTS payment_gateways (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    gateway_name varchar(64) NOT NULL COMMENT 'Gateway identifier (e.g., bkash, nagad, stripe)',
    display_name varchar(128) NOT NULL COMMENT 'Human-readable name',
    gateway_type enum('mobile_banking', 'card', 'bank_transfer', 'crypto', 'other') DEFAULT 'mobile_banking',
    api_key varchar(255) DEFAULT NULL COMMENT 'API Key or App Key',
    api_secret varchar(255) DEFAULT NULL COMMENT 'API Secret or App Secret',
    merchant_id varchar(128) DEFAULT NULL COMMENT 'Merchant ID or Store ID',
    username varchar(128) DEFAULT NULL COMMENT 'Username for authentication',
    password varchar(255) DEFAULT NULL COMMENT 'Password for authentication',
    sandbox_mode tinyint(1) DEFAULT '1' COMMENT '1 = Sandbox/Test mode, 0 = Production',
    status enum('active', 'disabled') DEFAULT 'disabled',
    webhook_url varchar(255) DEFAULT NULL COMMENT 'Webhook URL for callbacks',
    success_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after successful payment',
    fail_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after failed payment',
    cancel_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after cancelled payment',
    currency varchar(10) DEFAULT 'BDT' COMMENT 'Currency code',
    config_json text COMMENT 'Additional configuration as JSON',
    description text COMMENT 'Description or notes',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY gateway_name (gateway_name),
    KEY status (status),
    KEY gateway_type (gateway_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- SMS Gateways Table
-- Manage SMS gateway configurations (Twilio, Nexmo, Local SMS providers)
CREATE TABLE IF NOT EXISTS sms_gateways (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    gateway_name varchar(64) NOT NULL COMMENT 'Gateway identifier (e.g., twilio, nexmo, banglalink)',
    display_name varchar(128) NOT NULL COMMENT 'Human-readable name',
    gateway_type enum('international', 'local', 'custom_api') DEFAULT 'local',
    api_key varchar(255) DEFAULT NULL COMMENT 'API Key or Account SID',
    api_secret varchar(255) DEFAULT NULL COMMENT 'API Secret or Auth Token',
    sender_id varchar(50) DEFAULT NULL COMMENT 'Sender ID or From Number',
    username varchar(128) DEFAULT NULL COMMENT 'Username for authentication',
    password varchar(255) DEFAULT NULL COMMENT 'Password for authentication',
    api_endpoint varchar(255) DEFAULT NULL COMMENT 'Custom API endpoint URL',
    status enum('active', 'disabled') DEFAULT 'disabled',
    is_default tinyint(1) DEFAULT '0' COMMENT '1 = Default gateway for OTP',
    rate_per_sms decimal(10,4) DEFAULT '0.0000' COMMENT 'Cost per SMS',
    balance decimal(10,2) DEFAULT '0.00' COMMENT 'Current balance if applicable',
    config_json text COMMENT 'Additional configuration as JSON',
    description text COMMENT 'Description or notes',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY gateway_name (gateway_name),
    KEY status (status),
    KEY is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- Insert sample payment gateways (disabled by default)
INSERT INTO payment_gateways (gateway_name, display_name, gateway_type, currency, status, description) VALUES
('bkash', 'bKash', 'mobile_banking', 'BDT', 'disabled', 'bKash Mobile Banking - Bangladesh'),
('nagad', 'Nagad', 'mobile_banking', 'BDT', 'disabled', 'Nagad Mobile Banking - Bangladesh'),
('rocket', 'Rocket (DBBL)', 'mobile_banking', 'BDT', 'disabled', 'Rocket Mobile Banking - Bangladesh'),
('stripe', 'Stripe', 'card', 'USD', 'disabled', 'Stripe Card Payments'),
('paypal', 'PayPal', 'card', 'USD', 'disabled', 'PayPal Payments')
ON DUPLICATE KEY UPDATE gateway_name=gateway_name; 

-- Insert sample SMS gateways (disabled by default)
INSERT INTO sms_gateways (gateway_name, display_name, gateway_type, status, description) VALUES
('twilio', 'Twilio', 'international', 'disabled', 'Twilio SMS Service'),
('nexmo', 'Nexmo (Vonage)', 'international', 'disabled', 'Nexmo SMS API'),
('banglalink', 'Banglalink API', 'local', 'disabled', 'Banglalink SMS Gateway'),
('grameenphone', 'Grameenphone API', 'local', 'disabled', 'Grameenphone SMS Gateway'),
('robi', 'Robi/Airtel API', 'local', 'disabled', 'Robi/Airtel SMS Gateway'),
('sslwireless', 'SSL Wireless', 'local', 'disabled', 'SSL Wireless SMS Gateway')
ON DUPLICATE KEY UPDATE gateway_name=gateway_name; 

-- WiFi Packages Table
-- Predefined and custom packages for internet access
CREATE TABLE IF NOT EXISTS wifi_packages (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    package_name varchar(128) NOT NULL,
    package_type enum('hourly', 'daily', 'custom_hourly', 'custom_daily') NOT NULL,
    duration_value int(11) NOT NULL COMMENT 'Duration in hours or days',
    duration_unit enum('hour', 'day') NOT NULL,
    base_price decimal(10,2) NOT NULL COMMENT 'Base price for fixed packages',
    price_per_unit decimal(10,2) DEFAULT NULL COMMENT 'Price per hour/day for custom packages',
    data_limit_mb int(11) DEFAULT NULL COMMENT 'Data limit in MB (NULL = unlimited)',
    speed_limit_mbps int(11) DEFAULT NULL COMMENT 'Speed limit in Mbps (NULL = unlimited)',
    description text,
    is_active tinyint(1) DEFAULT '1',
    is_custom tinyint(1) DEFAULT '0' COMMENT '1 = User can customize quantity',
    display_order int(11) DEFAULT '0',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY package_type (package_type),
    KEY is_active (is_active),
    KEY display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- Package Purchases/Orders Table
CREATE TABLE IF NOT EXISTS package_orders (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    order_code varchar(32) NOT NULL COMMENT 'Unique order reference',
    package_id int(11) unsigned NOT NULL,
    user_identifier varchar(128) NOT NULL COMMENT 'Mobile number or email',
    quantity int(11) DEFAULT '1' COMMENT 'For custom packages',
    total_amount decimal(10,2) NOT NULL,
    duration_granted int(11) NOT NULL COMMENT 'Duration in seconds',
    payment_gateway varchar(64) DEFAULT NULL,
    payment_status enum('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    payment_transaction_id varchar(255) DEFAULT NULL,
    voucher_code varchar(64) DEFAULT NULL COMMENT 'Generated voucher code after payment',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at timestamp NULL DEFAULT NULL,
    expires_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY order_code (order_code),
    KEY package_id (package_id),
    KEY payment_status (payment_status),
    KEY user_identifier (user_identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- Insert default packages
INSERT INTO wifi_packages (package_name, package_type, duration_value, duration_unit, base_price, price_per_unit, description, is_active, is_custom, display_order) VALUES
('2 Hours Internet', 'hourly', 2, 'hour', 15.00, NULL, '2 hours of high-speed internet access', 1, 0, 1),
('1 Day Internet', 'daily', 1, 'day', 30.00, NULL, '24 hours of high-speed internet access', 1, 0, 2),
('Custom Hours', 'custom_hourly', 1, 'hour', 0.00, 10.00, 'Choose how many hours you need. BDT 10 per hour', 1, 1, 3),
('Custom Days', 'custom_daily', 1, 'day', 0.00, 30.00, 'Choose how many days you need. BDT 30 per day', 1, 1, 4)
ON DUPLICATE KEY UPDATE package_name=package_name; 

-- Insert default NAS (localhost for testing)
INSERT INTO nas (nasname, shortname, type, secret, description, status) VALUES
('127.0.0.1', 'localhost', 'other', 'testing123', 'Local testing client', 'active')
ON DUPLICATE KEY UPDATE nasname=nasname;

-- Payment Gateways Table
-- Manage payment gateway configurations (bKash, Nagad, Stripe, etc.)
CREATE TABLE IF NOT EXISTS payment_gateways (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    gateway_name varchar(64) NOT NULL COMMENT 'Gateway identifier (e.g., bkash, nagad, stripe)',
    display_name varchar(128) NOT NULL COMMENT 'Human-readable name',
    gateway_type enum('mobile_banking', 'card', 'bank_transfer', 'crypto', 'other') DEFAULT 'mobile_banking',
    api_key varchar(255) DEFAULT NULL COMMENT 'API Key or App Key',
    api_secret varchar(255) DEFAULT NULL COMMENT 'API Secret or App Secret',
    merchant_id varchar(128) DEFAULT NULL COMMENT 'Merchant ID or Store ID',
    username varchar(128) DEFAULT NULL COMMENT 'Username for authentication',
    password varchar(255) DEFAULT NULL COMMENT 'Password for authentication',
    sandbox_mode tinyint(1) DEFAULT '1' COMMENT '1 = Sandbox/Test mode, 0 = Production',
    status enum('active', 'disabled') DEFAULT 'disabled',
    webhook_url varchar(255) DEFAULT NULL COMMENT 'Webhook URL for callbacks',
    success_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after successful payment',
    fail_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after failed payment',
    cancel_url varchar(255) DEFAULT NULL COMMENT 'Redirect URL after cancelled payment',
    currency varchar(10) DEFAULT 'BDT' COMMENT 'Currency code',
    config_json text COMMENT 'Additional configuration as JSON',
    description text COMMENT 'Description or notes',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY gateway_name (gateway_name),
    KEY status (status),
    KEY gateway_type (gateway_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS Gateways Table
-- Manage SMS gateway configurations (Twilio, Nexmo, Local SMS providers)
CREATE TABLE IF NOT EXISTS sms_gateways (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    gateway_name varchar(64) NOT NULL COMMENT 'Gateway identifier (e.g., twilio, nexmo, banglalink)',
    display_name varchar(128) NOT NULL COMMENT 'Human-readable name',
    gateway_type enum('international', 'local', 'custom_api') DEFAULT 'local',
    api_key varchar(255) DEFAULT NULL COMMENT 'API Key or Account SID',
    api_secret varchar(255) DEFAULT NULL COMMENT 'API Secret or Auth Token',
    sender_id varchar(50) DEFAULT NULL COMMENT 'Sender ID or From Number',
    username varchar(128) DEFAULT NULL COMMENT 'Username for authentication',
    password varchar(255) DEFAULT NULL COMMENT 'Password for authentication',
    api_endpoint varchar(255) DEFAULT NULL COMMENT 'Custom API endpoint URL',
    status enum('active', 'disabled') DEFAULT 'disabled',
    is_default tinyint(1) DEFAULT '0' COMMENT '1 = Default gateway for OTP',
    rate_per_sms decimal(10,4) DEFAULT '0.0000' COMMENT 'Cost per SMS',
    balance decimal(10,2) DEFAULT '0.00' COMMENT 'Current balance if applicable',
    config_json text COMMENT 'Additional configuration as JSON',
    description text COMMENT 'Description or notes',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by int(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY gateway_name (gateway_name),
    KEY status (status),
    KEY is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample payment gateways (disabled by default)
INSERT INTO payment_gateways (gateway_name, display_name, gateway_type, currency, status, description) VALUES
('bkash', 'bKash', 'mobile_banking', 'BDT', 'disabled', 'bKash Mobile Banking - Bangladesh'),
('nagad', 'Nagad', 'mobile_banking', 'BDT', 'disabled', 'Nagad Mobile Banking - Bangladesh'),
('rocket', 'Rocket (DBBL)', 'mobile_banking', 'BDT', 'disabled', 'Rocket Mobile Banking - Bangladesh'),
('stripe', 'Stripe', 'card', 'USD', 'disabled', 'Stripe Card Payments'),
('paypal', 'PayPal', 'card', 'USD', 'disabled', 'PayPal Payments')
ON DUPLICATE KEY UPDATE gateway_name=gateway_name;

-- Insert sample SMS gateways (disabled by default)
INSERT INTO sms_gateways (gateway_name, display_name, gateway_type, status, description) VALUES
('twilio', 'Twilio', 'international', 'disabled', 'Twilio SMS Service'),
('nexmo', 'Nexmo (Vonage)', 'international', 'disabled', 'Nexmo SMS API'),
('banglalink', 'Banglalink API', 'local', 'disabled', 'Banglalink SMS Gateway'),
('grameenphone', 'Grameenphone API', 'local', 'disabled', 'Grameenphone SMS Gateway'),
('robi', 'Robi/Airtel API', 'local', 'disabled', 'Robi/Airtel SMS Gateway'),
('sslwireless', 'SSL Wireless', 'local', 'disabled', 'SSL Wireless SMS Gateway')
ON DUPLICATE KEY UPDATE gateway_name=gateway_name;

-- WiFi Packages Table
-- Predefined and custom packages for internet access
CREATE TABLE IF NOT EXISTS wifi_packages (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    package_name varchar(128) NOT NULL,
    package_type enum('hourly', 'daily', 'custom_hourly', 'custom_daily') NOT NULL,
    duration_value int(11) NOT NULL COMMENT 'Duration in hours or days',
    duration_unit enum('hour', 'day') NOT NULL,
    base_price decimal(10,2) NOT NULL COMMENT 'Base price for fixed packages',
    price_per_unit decimal(10,2) DEFAULT NULL COMMENT 'Price per hour/day for custom packages',
    data_limit_mb int(11) DEFAULT NULL COMMENT 'Data limit in MB (NULL = unlimited)',
    speed_limit_mbps int(11) DEFAULT NULL COMMENT 'Speed limit in Mbps (NULL = unlimited)',
    description text,
    is_active tinyint(1) DEFAULT '1',
    is_custom tinyint(1) DEFAULT '0' COMMENT '1 = User can customize quantity',
    display_order int(11) DEFAULT '0',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY package_type (package_type),
    KEY is_active (is_active),
    KEY display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Package Purchases/Orders Table
CREATE TABLE IF NOT EXISTS package_orders (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    order_code varchar(32) NOT NULL COMMENT 'Unique order reference',
    package_id int(11) unsigned NOT NULL,
    user_identifier varchar(128) NOT NULL COMMENT 'Mobile number or email',
    quantity int(11) DEFAULT '1' COMMENT 'For custom packages',
    total_amount decimal(10,2) NOT NULL,
    duration_granted int(11) NOT NULL COMMENT 'Duration in seconds',
    payment_gateway varchar(64) DEFAULT NULL,
    payment_status enum('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    payment_transaction_id varchar(255) DEFAULT NULL,
    voucher_code varchar(64) DEFAULT NULL COMMENT 'Generated voucher code after payment',
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at timestamp NULL DEFAULT NULL,
    expires_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY order_code (order_code),
    KEY package_id (package_id),
    KEY payment_status (payment_status),
    KEY user_identifier (user_identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default packages
INSERT INTO wifi_packages (package_name, package_type, duration_value, duration_unit, base_price, price_per_unit, description, is_active, is_custom, display_order) VALUES
('2 Hours Internet', 'hourly', 2, 'hour', 15.00, NULL, '2 hours of high-speed internet access', 1, 0, 1),
('1 Day Internet', 'daily', 1, 'day', 30.00, NULL, '24 hours of high-speed internet access', 1, 0, 2),
('Custom Hours', 'custom_hourly', 1, 'hour', 0.00, 10.00, 'Choose how many hours you need. BDT 10 per hour', 1, 1, 3),
('Custom Days', 'custom_daily', 1, 'day', 0.00, 30.00, 'Choose how many days you need. BDT 30 per day', 1, 1, 4)
ON DUPLICATE KEY UPDATE package_name=package_name;

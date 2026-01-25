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

-- Insert default groups
INSERT INTO user_groups (groupname, description, max_sessions, session_timeout, download_speed, upload_speed) VALUES
('default', 'Default user group', 1, 3600, 1024, 512),
('premium', 'Premium users with higher limits', 2, 7200, 2048, 1024),
('voucher', 'Voucher users', 1, 3600, 512, 256)
ON DUPLICATE KEY UPDATE groupname=groupname;

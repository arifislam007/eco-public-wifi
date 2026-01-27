<?php
/**
 * Database Setup and Migration
 * Automatically creates tables that don't exist
 */

require_once __DIR__ . '/../../portal/includes/auth.php';

/**
 * Check if a table exists
 */
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if a column exists in a table
 */
function columnExists($pdo, $tableName, $columnName) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Create all required tables for the advanced features
 */
function setupDatabaseTables() {
    try {
        $pdo = getDBConnection();
        
        // 1. Create resellers table
        if (!tableExists($pdo, 'resellers')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS resellers (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // 2. Create reseller_transactions table
        if (!tableExists($pdo, 'reseller_transactions')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_transactions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // 3. Create payment_gateways table
        if (!tableExists($pdo, 'payment_gateways')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS payment_gateways (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                gateway_name varchar(64) NOT NULL COMMENT 'Gateway identifier',
                display_name varchar(128) NOT NULL COMMENT 'Human-readable name',
                gateway_type enum('mobile_banking', 'card', 'bank_transfer', 'crypto', 'other') DEFAULT 'mobile_banking',
                api_key varchar(255) DEFAULT NULL,
                api_secret varchar(255) DEFAULT NULL,
                merchant_id varchar(128) DEFAULT NULL,
                username varchar(128) DEFAULT NULL,
                password varchar(255) DEFAULT NULL,
                sandbox_mode tinyint(1) DEFAULT '1',
                status enum('active', 'disabled') DEFAULT 'disabled',
                webhook_url varchar(255) DEFAULT NULL,
                success_url varchar(255) DEFAULT NULL,
                fail_url varchar(255) DEFAULT NULL,
                cancel_url varchar(255) DEFAULT NULL,
                currency varchar(10) DEFAULT 'BDT',
                config_json text,
                description text,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY gateway_name (gateway_name),
                KEY status (status),
                KEY gateway_type (gateway_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Insert default payment gateways
            $pdo->exec("INSERT INTO payment_gateways (gateway_name, display_name, gateway_type, currency, status, description) VALUES
                ('bkash', 'bKash', 'mobile_banking', 'BDT', 'disabled', 'bKash Mobile Banking - Bangladesh'),
                ('nagad', 'Nagad', 'mobile_banking', 'BDT', 'disabled', 'Nagad Mobile Banking - Bangladesh'),
                ('rocket', 'Rocket (DBBL)', 'mobile_banking', 'BDT', 'disabled', 'Rocket Mobile Banking - Bangladesh'),
                ('stripe', 'Stripe', 'card', 'USD', 'disabled', 'Stripe Card Payments'),
                ('paypal', 'PayPal', 'card', 'USD', 'disabled', 'PayPal Payments')
                ON DUPLICATE KEY UPDATE gateway_name=gateway_name");
        }
        
        // 4. Create sms_gateways table
        if (!tableExists($pdo, 'sms_gateways')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_gateways (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                gateway_name varchar(64) NOT NULL,
                display_name varchar(128) NOT NULL,
                gateway_type enum('international', 'local', 'custom_api') DEFAULT 'local',
                api_key varchar(255) DEFAULT NULL,
                api_secret varchar(255) DEFAULT NULL,
                sender_id varchar(50) DEFAULT NULL,
                username varchar(128) DEFAULT NULL,
                password varchar(255) DEFAULT NULL,
                api_endpoint varchar(255) DEFAULT NULL,
                status enum('active', 'disabled') DEFAULT 'disabled',
                is_default tinyint(1) DEFAULT '0',
                rate_per_sms decimal(10,4) DEFAULT '0.0000',
                balance decimal(10,2) DEFAULT '0.00',
                config_json text,
                description text,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY gateway_name (gateway_name),
                KEY status (status),
                KEY is_default (is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Insert default SMS gateways
            $pdo->exec("INSERT INTO sms_gateways (gateway_name, display_name, gateway_type, status, description) VALUES
                ('twilio', 'Twilio', 'international', 'disabled', 'Twilio SMS Service'),
                ('nexmo', 'Nexmo (Vonage)', 'international', 'disabled', 'Nexmo SMS API'),
                ('banglalink', 'Banglalink API', 'local', 'disabled', 'Banglalink SMS Gateway'),
                ('grameenphone', 'Grameenphone API', 'local', 'disabled', 'Grameenphone SMS Gateway'),
                ('robi', 'Robi/Airtel API', 'local', 'disabled', 'Robi/Airtel SMS Gateway'),
                ('sslwireless', 'SSL Wireless', 'local', 'disabled', 'SSL Wireless SMS Gateway')
                ON DUPLICATE KEY UPDATE gateway_name=gateway_name");
        }
        
        // 5. Create wifi_packages table
        if (!tableExists($pdo, 'wifi_packages')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wifi_packages (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                package_name varchar(128) NOT NULL,
                package_type enum('hourly', 'daily', 'custom_hourly', 'custom_daily') NOT NULL,
                duration_value int(11) NOT NULL COMMENT 'Duration in hours or days',
                duration_unit enum('hour', 'day') NOT NULL,
                base_price decimal(10,2) NOT NULL,
                price_per_unit decimal(10,2) DEFAULT NULL,
                data_limit_mb int(11) DEFAULT NULL,
                speed_limit_mbps int(11) DEFAULT NULL,
                description text,
                is_active tinyint(1) DEFAULT '1',
                is_custom tinyint(1) DEFAULT '0',
                display_order int(11) DEFAULT '0',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY package_type (package_type),
                KEY is_active (is_active),
                KEY display_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Insert default packages
            $pdo->exec("INSERT INTO wifi_packages (package_name, package_type, duration_value, duration_unit, base_price, price_per_unit, description, is_active, is_custom, display_order) VALUES
                ('2 Hours Internet', 'hourly', 2, 'hour', 15.00, NULL, '2 hours of high-speed internet access', 1, 0, 1),
                ('1 Day Internet', 'daily', 1, 'day', 30.00, NULL, '24 hours of high-speed internet access', 1, 0, 2),
                ('Custom Hours', 'custom_hourly', 1, 'hour', 0.00, 10.00, 'Choose how many hours you need. BDT 10 per hour', 1, 1, 3),
                ('Custom Days', 'custom_daily', 1, 'day', 0.00, 30.00, 'Choose how many days you need. BDT 30 per day', 1, 1, 4)
                ON DUPLICATE KEY UPDATE package_name=package_name");
        }
        
        // 6. Create package_orders table
        if (!tableExists($pdo, 'package_orders')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS package_orders (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                order_code varchar(32) NOT NULL,
                package_id int(11) unsigned NOT NULL,
                user_identifier varchar(128) NOT NULL,
                quantity int(11) DEFAULT '1',
                total_amount decimal(10,2) NOT NULL,
                duration_granted int(11) NOT NULL,
                payment_gateway varchar(64) DEFAULT NULL,
                payment_status enum('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
                payment_transaction_id varchar(255) DEFAULT NULL,
                voucher_code varchar(64) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at timestamp NULL DEFAULT NULL,
                expires_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY order_code (order_code),
                KEY package_id (package_id),
                KEY payment_status (payment_status),
                KEY user_identifier (user_identifier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // 7. Create nas table
        if (!tableExists($pdo, 'nas')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS nas (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                nasname varchar(128) NOT NULL,
                shortname varchar(32) DEFAULT NULL,
                type varchar(30) DEFAULT 'other',
                ports int(5) DEFAULT NULL,
                secret varchar(60) NOT NULL DEFAULT 'secret',
                server varchar(64) DEFAULT NULL,
                community varchar(50) DEFAULT NULL,
                description varchar(200) DEFAULT NULL,
                status enum('active', 'disabled') DEFAULT 'active',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY nasname (nasname),
                KEY shortname (shortname),
                KEY status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Insert default NAS
            $pdo->exec("INSERT INTO nas (nasname, shortname, type, secret, description, status) VALUES
                ('127.0.0.1', 'localhost', 'other', 'testing123', 'Local testing client', 'active')
                ON DUPLICATE KEY UPDATE nasname=nasname");
        }
        
        // 8. Add columns to admin_users table
        if (!columnExists($pdo, 'admin_users', 'role')) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN role enum('admin', 'reseller') DEFAULT 'admin'");
        }
        if (!columnExists($pdo, 'admin_users', 'reseller_id')) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN reseller_id int(11) unsigned DEFAULT NULL");
        }
        if (!columnExists($pdo, 'admin_users', 'status')) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN status enum('active', 'inactive') DEFAULT 'active'");
        }
        
        // 9. Add columns to vouchers table
        if (!columnExists($pdo, 'vouchers', 'reseller_id')) {
            $pdo->exec("ALTER TABLE vouchers ADD COLUMN reseller_id int(11) unsigned DEFAULT NULL");
        }
        if (!columnExists($pdo, 'vouchers', 'voucher_prefix')) {
            $pdo->exec("ALTER TABLE vouchers ADD COLUMN voucher_prefix varchar(10) DEFAULT NULL");
        }
        
        // 10. Add columns to radcheck table
        if (!columnExists($pdo, 'radcheck', 'reseller_id')) {
            $pdo->exec("ALTER TABLE radcheck ADD COLUMN reseller_id int(11) unsigned DEFAULT NULL");
        }
        if (!columnExists($pdo, 'radcheck', 'created_by_type')) {
            $pdo->exec("ALTER TABLE radcheck ADD COLUMN created_by_type enum('admin', 'reseller') DEFAULT 'admin'");
        }
        
        // 11. Create other helper tables
        if (!tableExists($pdo, 'otp_codes')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'mobile_users')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_users (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                mobile_number varchar(20) NOT NULL,
                username varchar(64) NOT NULL,
                verified tinyint(1) DEFAULT '0',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_login timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY mobile_number (mobile_number),
                KEY username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'user_groups')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_groups (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                groupname varchar(64) NOT NULL,
                description text,
                max_sessions int(11) DEFAULT '1',
                session_timeout int(11) DEFAULT '3600',
                idle_timeout int(11) DEFAULT '600',
                daily_limit bigint(20) DEFAULT NULL,
                monthly_limit bigint(20) DEFAULT NULL,
                download_speed int(11) DEFAULT NULL,
                upload_speed int(11) DEFAULT NULL,
                burst_download int(11) DEFAULT NULL,
                burst_upload int(11) DEFAULT NULL,
                fup_enabled tinyint(1) DEFAULT '0',
                fup_threshold bigint(20) DEFAULT NULL,
                fup_speed int(11) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY groupname (groupname)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $pdo->exec("INSERT INTO user_groups (groupname, description, max_sessions, session_timeout, download_speed, upload_speed) VALUES
                ('default', 'Default user group', 1, 3600, 1024, 512),
                ('premium', 'Premium users with higher limits', 2, 7200, 2048, 1024),
                ('voucher', 'Voucher users', 1, 3600, 512, 256)
                ON DUPLICATE KEY UPDATE groupname=groupname");
        }
        
        if (!tableExists($pdo, 'daily_usage')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS daily_usage (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'monthly_usage')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                username varchar(64) NOT NULL,
                usage_month date NOT NULL,
                bytes_in bigint(20) DEFAULT '0',
                bytes_out bigint(20) DEFAULT '0',
                total_bytes bigint(20) DEFAULT '0',
                session_count int(11) DEFAULT '0',
                PRIMARY KEY (id),
                UNIQUE KEY username_month (username, usage_month),
                KEY usage_month (usage_month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'active_sessions')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS active_sessions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'sms_logs')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_logs (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'user_policies')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_policies (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        if (!tableExists($pdo, 'voucher_usage')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                voucher_id int(11) unsigned NOT NULL,
                username varchar(64) NOT NULL,
                session_id varchar(64) NOT NULL,
                start_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                end_time timestamp NULL DEFAULT NULL,
                bytes_used bigint(20) DEFAULT '0',
                time_used int(11) DEFAULT '0',
                PRIMARY KEY (id),
                KEY voucher_id (voucher_id),
                KEY username (username),
                KEY start_time (start_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        return ['success' => true, 'message' => 'All tables created successfully'];
        
    } catch (PDOException $e) {
        error_log("Database Setup Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

-- FreeRADIUS MySQL Database Schema
-- For Public Wi-Fi Captive Portal System

CREATE DATABASE IF NOT EXISTS radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE radius;

-- Table: radcheck (User authentication attributes)
CREATE TABLE IF NOT EXISTS radcheck (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radreply (User reply attributes)
CREATE TABLE IF NOT EXISTS radreply (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radusergroup (User group membership)
CREATE TABLE IF NOT EXISTS radusergroup (
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    priority int(11) NOT NULL DEFAULT '1',
    PRIMARY KEY (username, groupname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radacct (Accounting records)
CREATE TABLE IF NOT EXISTS radacct (
    radacctid bigint(21) NOT NULL AUTO_INCREMENT,
    acctsessionid varchar(64) NOT NULL DEFAULT '',
    acctuniqueid varchar(32) NOT NULL DEFAULT '',
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    realm varchar(64) DEFAULT '',
    nasipaddress varchar(15) NOT NULL DEFAULT '',
    nasportid varchar(15) DEFAULT NULL,
    nasporttype varchar(32) DEFAULT NULL,
    acctstarttime datetime DEFAULT NULL,
    acctstoptime datetime DEFAULT NULL,
    acctsessiontime int(12) DEFAULT NULL,
    acctauthentic varchar(32) DEFAULT NULL,
    connectinfo_start varchar(50) DEFAULT NULL,
    connectinfo_stop varchar(50) DEFAULT NULL,
    acctinputoctets bigint(20) DEFAULT NULL,
    acctoutputoctets bigint(20) DEFAULT NULL,
    calledstationid varchar(50) NOT NULL DEFAULT '',
    callingstationid varchar(50) NOT NULL DEFAULT '',
    acctterminatecause varchar(32) NOT NULL DEFAULT '',
    servicetype varchar(32) DEFAULT NULL,
    framedprotocol varchar(32) DEFAULT NULL,
    framedipaddress varchar(15) NOT NULL DEFAULT '',
    framedipv6address varchar(45) NOT NULL DEFAULT '',
    framedipv6prefix varchar(45) NOT NULL DEFAULT '',
    framedinterfaceid varchar(64) NOT NULL DEFAULT '',
    delegatedipv6prefix varchar(45) NOT NULL DEFAULT '',
    PRIMARY KEY (radacctid),
    UNIQUE KEY acctuniqueid (acctuniqueid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY framedipv6address (framedipv6address),
    KEY framedipv6prefix (framedipv6prefix),
    KEY delegatedipv6prefix (delegatedipv6prefix),
    KEY acctsessionid (acctsessionid),
    KEY acctsessiontime (acctsessiontime),
    KEY acctstarttime (acctstarttime),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radpostauth (Post-authentication logging)
CREATE TABLE IF NOT EXISTS radpostauth (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    pass varchar(64) NOT NULL DEFAULT '',
    reply varchar(32) NOT NULL DEFAULT '',
    authdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radgroupcheck (Group check attributes)
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: radgroupreply (Group reply attributes)
CREATE TABLE IF NOT EXISTS radgroupreply (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin users table for the admin panel
CREATE TABLE IF NOT EXISTS admin_users (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    password_hash varchar(255) NOT NULL,
    email varchar(255) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts tracking for rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    ip_address varchar(45) NOT NULL,
    username varchar(64) DEFAULT NULL,
    attempt_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success tinyint(1) DEFAULT '0',
    PRIMARY KEY (id),
    KEY ip_address (ip_address),
    KEY attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample admin user (password: admin123 - CHANGE THIS!)
-- Password hash for 'admin123' using password_hash()
INSERT INTO admin_users (username, password_hash, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com')
ON DUPLICATE KEY UPDATE username=username;

-- Sample test user (password: test123)
-- Using Cleartext-Password for FreeRADIUS (in production, use MD5-Password or better)
INSERT INTO radcheck (username, attribute, op, value) VALUES
('testuser', 'Cleartext-Password', ':=', 'test123'),
('voucher001', 'Cleartext-Password', ':=', 'voucher123')
ON DUPLICATE KEY UPDATE username=username;

-- Sample reply attributes (session timeout: 3600 seconds = 1 hour)
INSERT INTO radreply (username, attribute, op, value) VALUES
('testuser', 'Session-Timeout', ':=', '3600'),
('voucher001', 'Session-Timeout', ':=', '3600')
ON DUPLICATE KEY UPDATE username=username;

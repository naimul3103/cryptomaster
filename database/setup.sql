-- ============================================================
-- CryptoMaster — Database Setup Script
-- Run this once to create the database before starting the app.
-- Usage: mysql -u root -p < database/setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `cryptomaster`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cryptomaster`;

-- Tables are auto-created by db.php on first request (initSchema).
-- This file just ensures the database itself exists.

-- Optional: create a dedicated app user (recommended for production)
-- Replace 'your_secure_password' with a strong password.
-- CREATE USER IF NOT EXISTS 'cryptomaster_app'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE, CREATE ON `cryptomaster`.* TO 'cryptomaster_app'@'localhost';
-- FLUSH PRIVILEGES;

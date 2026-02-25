<?php
/**
 * config.example.php
 * Template de configuration — copiez ce fichier en config.php et remplissez vos valeurs.
 *
 * cp config.example.php config.php
 *
 * ⚠️  NE JAMAIS COMMITER config.php — il contient vos vraies credentials.
 *     Ce fichier (config.example.php) est safe à commiter.
 */

// ============================================================
// BASE DE DONNÉES
// ============================================================
define('DB_HOST',    'your-db-host:3306');
define('DB_NAME',    'your-db-name');
define('DB_USER',    'your-db-user');
define('DB_PASS',    'your-db-password');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// APPLICATION
// ============================================================
define('APP_NAME',    'ANYTECH Hotspot');
define('APP_VERSION', '2.0.0');
define('APP_URL',     'https://your-domain.com');

// ============================================================
// SÉCURITÉ
// ============================================================
define('ADMIN_PASSWORD', 'change-this-strong-password');
define('SESSION_TIMEOUT', 86400); // 24h en secondes
define('CSRF_SECRET', 'generate-a-random-secret-string-here');

// ============================================================
// FEDAPAY
// ============================================================
define('FEDAPAY_PUBLIC_KEY',   'pk_sandbox_or_live_YOUR_KEY');
define('FEDAPAY_SECRET_KEY',   'sk_sandbox_or_live_YOUR_KEY');
define('FEDAPAY_ENV',          'sandbox'); // 'sandbox' ou 'live'
define('FEDAPAY_CALLBACK_URL', APP_URL . '/fedapay-callback.php');
define('FEDAPAY_SUCCESS_URL',  APP_URL . '/payment-success.php');

// ============================================================
// LOGS
// ============================================================
define('LOG_DIR',  __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/app.log');

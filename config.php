<?php
/**
 * config.php
 * Configuration centralisée — DB, sécurité, app
 *
 * ⚠️  NE JAMAIS COMMITER CE FICHIER AVEC DE VRAIES CREDENTIALS
 *     Ajoutez config.php à votre .gitignore
 *
 * Usage : require_once 'config.php';  (avant auth-check.php)
 */

// ============================================================
// BASE DE DONNÉES
// ============================================================
define('DB_HOST', 'greentp171.mysql.db:3306');
define('DB_NAME', 'greentp171');
define('DB_USER', 'greentp171');
define('DB_PASS', 'TTp38xZVR5NS');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// APPLICATION
// ============================================================
define('APP_NAME',    'ANYTECH Hotspot');
define('APP_VERSION', '2.0.0');
define('APP_URL',     'https://wifizone.greentechnos.com'); // ← changer en production

// ============================================================
// SÉCURITÉ
// ============================================================
// Mot de passe admin panel (changer avant de mettre en production !)
define('ADMIN_PASSWORD', 'lunoire242@Lu'); // 
// Durée de session en secondes (24h)
define('SESSION_TIMEOUT', 86400);

// Clé secrète pour les tokens CSRF (générez une valeur aléatoire forte)
define('CSRF_SECRET', 'changez-cette-cle-csrf-en-production-' . DB_NAME);

// ============================================================
// FEDAPAY
// ============================================================
define('FEDAPAY_PUBLIC_KEY',  'pk_live_1U6SWw1tCRFxP6eopJ-PLaVU'); 
define('FEDAPAY_SECRET_KEY',  'sk_live_S93PaSt8psVOhpb6B69T0sq_');
define('FEDAPAY_ENV',         'live');                 // 'sandbox' ou 'live'
define('FEDAPAY_CALLBACK_URL', APP_URL . '/fedapay-callback.php');
define('FEDAPAY_SUCCESS_URL',  APP_URL . '/payment-success.php');

// ============================================================
// LOGS
// ============================================================
define('LOG_DIR',  __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/app.log');

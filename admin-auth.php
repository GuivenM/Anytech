<?php
/**
 * admin-auth.php — Guard superadmin ANYTECH
 *
 * IMPORTANT : session_name('anytech_admin') DOIT être appelé
 * AVANT session_start(), sur TOUTES les pages admin.
 * Cela isole la session admin de la session SaaS client.
 */

session_name('anytech_admin');
if (session_status() === PHP_SESSION_NONE) session_start();

// Config + DB
if (!defined('DB_HOST')) require_once __DIR__ . '/config.php';

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO {
        static $pdo = null;
        if ($pdo !== null) return $pdo;
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $pdo;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
    function csrf_verify(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

// ── Vérification authentification ────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin-login.php');
    exit();
}

// Timeout 2h
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > 7200) {
    session_unset();
    session_destroy();
    header('Location: admin-login.php?timeout=1');
    exit();
}
$_SESSION['admin_last_activity'] = time();
?>

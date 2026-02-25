<?php
/**
 * auth-check.php
 * Protection des pages, gestion de session, CSRF helper
 * VERSION 2.0 — utilise config.php centralisé
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charge la configuration centralisée si pas encore chargée
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// -------------------------------------------------------
// CONNEXION DB
// -------------------------------------------------------
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB connection failed: " . $e->getMessage());
        die("Erreur de connexion à la base de données.");
    }
}

// -------------------------------------------------------
// CSRF HELPERS
// -------------------------------------------------------
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

// -------------------------------------------------------
// AUTH CHECK
// -------------------------------------------------------
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Variables globales disponibles dans toutes les pages protégées
$proprietaire_id     = $_SESSION['proprietaire_id'];
$proprietaire_nom    = $_SESSION['proprietaire_nom'];
$proprietaire_email  = $_SESSION['proprietaire_email'];
$proprietaire_statut = $_SESSION['proprietaire_statut'];

// Récupère le solde à chaque chargement de page
$pdo = getDBConnection();
$stmt_solde = $pdo->prepare("SELECT solde_credits FROM proprietaires WHERE id = :id");
$stmt_solde->execute([':id' => $proprietaire_id]);
$result_solde = $stmt_solde->fetch();

if ($result_solde) {
    $proprietaire_solde = $result_solde['solde_credits'];
    $_SESSION['proprietaire_solde'] = $proprietaire_solde;
} else {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Compte suspendu
if ($proprietaire_statut === 'suspendu') {
    header('Location: account-suspended.php');
    exit();
}
?>

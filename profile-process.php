<?php
/**
 * profile-process.php
 * Traite la mise à jour du profil et du mot de passe
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

function sendResponse(bool $success, string $message, $data = null): void {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Auth check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    sendResponse(false, 'Non autorisé');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

// DB connection (mirrors auth-check pattern)
require_once 'auth-check.php';

try {
    $pdo = getDBConnection();
    $proprietaire_id_local = (int) $_SESSION['proprietaire_id'];
    $action = trim($_POST['action'] ?? '');

    // -------------------------------------------------------
    // ACTION : update_profile
    // -------------------------------------------------------
    if ($action === 'update_profile') {
        $nom_complet    = trim($_POST['nom_complet']    ?? '');
        $telephone      = trim($_POST['telephone']      ?? '');
        $nom_entreprise = trim($_POST['nom_entreprise'] ?? '');

        if (strlen($nom_complet) < 3) {
            sendResponse(false, 'Le nom complet doit contenir au moins 3 caractères');
        }
        if (strlen($telephone) < 8) {
            sendResponse(false, 'Numéro de téléphone invalide');
        }

        $sql = "UPDATE proprietaires 
                SET nom_complet    = :nom_complet,
                    telephone      = :telephone,
                    nom_entreprise = :nom_entreprise
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nom_complet'    => htmlspecialchars($nom_complet, ENT_QUOTES, 'UTF-8'),
            ':telephone'      => htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8'),
            ':nom_entreprise' => htmlspecialchars($nom_entreprise, ENT_QUOTES, 'UTF-8'),
            ':id'             => $proprietaire_id_local,
        ]);

        // Refresh session name
        $_SESSION['proprietaire_nom'] = $nom_complet;

        sendResponse(true, 'Profil mis à jour avec succès');
    }

    // -------------------------------------------------------
    // ACTION : change_password
    // -------------------------------------------------------
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password']     ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            sendResponse(false, 'Veuillez saisir votre mot de passe actuel');
        }
        if (strlen($new_password) < 6) {
            sendResponse(false, 'Le nouveau mot de passe doit contenir au moins 6 caractères');
        }
        if ($new_password !== $confirm_password) {
            sendResponse(false, 'Les mots de passe ne correspondent pas');
        }

        // Fetch current hash
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM proprietaires WHERE id = :id");
        $stmt->execute([':id' => $proprietaire_id_local]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current_password, $row['mot_de_passe'])) {
            sendResponse(false, 'Mot de passe actuel incorrect');
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE proprietaires SET mot_de_passe = :hash WHERE id = :id");
        $stmt->execute([':hash' => $new_hash, ':id' => $proprietaire_id_local]);

        sendResponse(true, 'Mot de passe modifié avec succès');
    }

    sendResponse(false, 'Action inconnue');

} catch (PDOException $e) {
    error_log("profile-process PDO error: " . $e->getMessage());
    sendResponse(false, 'Erreur de base de données. Veuillez réessayer.');
} catch (Exception $e) {
    error_log("profile-process error: " . $e->getMessage());
    sendResponse(false, 'Une erreur s\'est produite. Veuillez réessayer.');
}
?>

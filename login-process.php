<?php
/**
 * login-process.php
 * Traite la connexion d'un propriétaire et crée une session
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Configuration base de données
define('DB_HOST', 'greentp171.mysql.db:3306');
define('DB_NAME', 'greentp171');
define('DB_USER', 'greentp171');
define('DB_PASS', 'TTp38xZVR5NS');

// Fonction pour envoyer une réponse JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Récupérer les données
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    
    // Validations basiques
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Email invalide');
    }
    
    if (empty($mot_de_passe)) {
        sendResponse(false, 'Mot de passe requis');
    }
    
    // Chercher le propriétaire par email
    $sql = "SELECT 
                id, 
                nom_complet, 
                email, 
                telephone, 
                nom_entreprise,
                mot_de_passe, 
                statut
            FROM proprietaires 
            WHERE email = :email";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $proprietaire = $stmt->fetch();
    
    // Vérifier si l'utilisateur existe
    if (!$proprietaire) {
        sendResponse(false, 'Email ou mot de passe incorrect');
    }
    
    // Vérifier le mot de passe
    if (!password_verify($mot_de_passe, $proprietaire['mot_de_passe'])) {
        sendResponse(false, 'Email ou mot de passe incorrect');
    }
    
    // Vérifier le statut du compte
    if ($proprietaire['statut'] === 'suspendu') {
        sendResponse(false, 'Votre compte est suspendu. Veuillez contacter le support.');
    }
    
    if ($proprietaire['statut'] === 'expiré') {
        sendResponse(false, 'Votre abonnement a expiré. Veuillez renouveler votre abonnement.');
    }
    
    // Connexion réussie ! Créer la session
    $_SESSION['proprietaire_id'] = $proprietaire['id'];
    $_SESSION['proprietaire_nom'] = $proprietaire['nom_complet'];
    $_SESSION['proprietaire_email'] = $proprietaire['email'];
    $_SESSION['proprietaire_statut'] = $proprietaire['statut'];
    $_SESSION['logged_in'] = true;
    
    // Mettre à jour la dernière connexion
    $update_login_sql = "UPDATE proprietaires SET derniere_connexion = NOW() WHERE id = :id";
    $update_login_stmt = $pdo->prepare($update_login_sql);
    $update_login_stmt->execute([':id' => $proprietaire['id']]);
    
    // Log de connexion
    error_log("Connexion réussie - ID: {$proprietaire['id']}, Email: {$email}");
    
    sendResponse(true, 'Connexion réussie ! Redirection...', [
        'proprietaire_id' => $proprietaire['id'],
        'nom' => $proprietaire['nom_complet'],
        'email' => $proprietaire['email'],
        'statut' => $proprietaire['statut']
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    sendResponse(false, 'Erreur de base de données. Veuillez réessayer.');
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    sendResponse(false, 'Une erreur s\'est produite. Veuillez réessayer.');
}
?>
